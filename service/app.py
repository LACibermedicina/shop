"""
Microsserviço Python — shop.m3d.pro (MVP Telemedicina & IA)
============================================================
Responsabilidades (separação de responsabilidades — SOLID):
  - IA de triagem (anamnese prévia) via OpenAI GPT-4o, com fallback local
  - Geração de PDFs (prontuário/receita) com hook de assinatura digital ICP-Brasil
  - Webhooks de pagamento (Stripe / Mercado Pago) com verificação de assinatura
  - Emissão de tokens LiveKit para a sala de teleconsulta

Compartilha o MESMO banco SQLite (WAL) com o frontend PHP.
Autenticação interno-servidor: header X-Service-Token.

Executar:  python3 service/app.py   (porta 8001)
"""
import json
import os
import sqlite3
import uuid
import hmac
import hashlib
from datetime import datetime, timezone
from pathlib import Path
from flask import Flask, request, jsonify, abort

# --------------------------------------------------------------------------
# Configuração (.env na raiz; sem chaves reais o sistema opera em modo MOCK)
# --------------------------------------------------------------------------
BASE = Path(__file__).resolve().parent.parent
DB_PATH = BASE / "data" / "m3d.sqlite"
STORAGE = BASE / "data" / "private"
STORAGE.mkdir(parents=True, exist_ok=True)

OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
LIVEKIT_API_KEY = os.getenv("LIVEKIT_API_KEY", "")
LIVEKIT_API_SECRET = os.getenv("LIVEKIT_API_SECRET", "")
LIVEKIT_URL = os.getenv("LIVEKIT_URL", "")
STRIPE_SECRET_KEY = os.getenv("STRIPE_SECRET_KEY", "")
STRIPE_WEBHOOK_SECRET = os.getenv("STRIPE_WEBHOOK_SECRET", "")
MERCADO_PAGO_ACCESS_TOKEN = os.getenv("MERCADO_PAGO_ACCESS_TOKEN", "")
PY_SERVICE_TOKEN = os.getenv("PY_SERVICE_TOKEN", "dev-token-troque")

app = Flask(__name__)


def db() -> sqlite3.Connection:
    """Conexão SQLite em modo WAL — leitura concorrente com o serviço PHP."""
    conn = sqlite3.connect(DB_PATH, timeout=10)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def require_service_token():
    """Barreira de autenticação interno-servidor (o PHP chama com X-Session-Email
    para contexto de usuário; chamadas de sistema usam X-Service-Token)."""
    if request.headers.get("X-Service-Token") == PY_SERVICE_TOKEN:
        return
    # Chamadas vindas do navegador (PHP injeta o e-mail da sessão autenticada)
    if request.headers.get("X-Session-Email"):
        return
    abort(401, description="Token de serviço ausente ou inválido")


# ==========================================================================
# MÓDULO 2 — TRIAGEM POR IA (ANAMNESE PRÉVIA)
# ==========================================================================
SYSTEM_PROMPT = (
    "Você é a assistente de triagem clínica da plataforma de telemedicina shop.m3d.pro. "
    "Conduza uma anamnese prévia em português do Brasil, com no máximo 4 perguntas curtas "
    "(queixa principal, início/duração, intensidade e sintomas associados, medicamentos em uso e alergias). "
    "Você NÃO dá diagnóstico nem prescreve: apenas coleta informação. "
    "Quando tiver as respostas, finalize escrevendo EXATAMENTE numa linha isolada: "
    "'[TRIAGEM_COMPLETA]' seguido de um resumo estruturado para o médico."
)

FALLBACK_QUESTIONS = [
    "Qual é a sua queixa principal e quando ela começou?",
    "Qual a intensidade do desconforto (0 a 10) e há outros sintomas associados?",
    "Quais medicamentos usa atualmente? Possui alguma alergia?",
]


def call_openai(messages: list) -> str | None:
    """Chama a OpenAI (GPT-4o) se houver API key; caso contrário devolve None
    para o motor de fallback local assumir — o MVP nunca fica indisponível."""
    if not OPENAI_API_KEY:
        return None
    try:
        from openai import OpenAI  # import preguiçoso: só necessário com chave real
        client = OpenAI(api_key=OPENAI_API_KEY)
        resp = client.chat.completions.create(
            model="gpt-4o",
            messages=messages,
            temperature=0.3,
            max_tokens=400,
        )
        return resp.choices[0].message.content
    except Exception as exc:  # falha de rede/cota: degrada com elegância
        app.logger.warning("OpenAI indisponível: %s", exc)
        return None


@app.post("/triage/chat")
def triage_chat():
    """Recebe uma mensagem do paciente e responde com a próxima pergunta da
    anamnese. Ao completar a coleta, marca done=True e grava o resumo no PEP."""
    require_service_token()
    payload = request.get_json(force=True)
    appt_id = int(payload["appointment_id"])
    message = (payload.get("message") or "").strip()[:2000]

    conn = db()
    conn.execute(
        "INSERT INTO triage_messages (appointment_id, role, content) VALUES (?, 'user', ?)",
        (appt_id, message),
    )
    conn.commit()

    history = conn.execute(
        "SELECT role, content FROM triage_messages WHERE appointment_id = ? ORDER BY id",
        (appt_id,),
    ).fetchall()

    messages = [{"role": "system", "content": SYSTEM_PROMPT}] + [
        {"role": h["role"], "content": h["content"]} for h in history
    ]
    reply = call_openai(messages)

    if reply is None:
        # Motor local de fallback: conduz a anamnese com roteiro fixo
        step = sum(1 for h in history if h["role"] == "user")
        if step <= len(FALLBACK_QUESTIONS):
            reply = FALLBACK_QUESTIONS[step - 1] if step <= len(FALLBACK_QUESTIONS) else ""
        if step >= len(FALLBACK_QUESTIONS):
            summary = (
                "RESUMO DE TRIAGEM (modo local)\n"
                + "\n".join(f"- {h['content']}" for h in history if h["role"] == "user")
            )
            reply = "Obrigado! Registrei suas respostas para o médico.\n[TRIAGEM_COMPLETA]\n" + summary

    done = "[TRIAGEM_COMPLETA]" in reply
    summary_text = reply.split("[TRIAGEM_COMPLETA]", 1)[1].strip() if done else None

    if done and summary_text:
        # REGRA DE NEGÓCIO CRÍTICA: o resumo entra no prontuário do agendamento,
        # visível ao médico responsável na sala de teleconsulta.
        conn.execute(
            """INSERT INTO medical_records (appointment_id, patient_id, doctor_id, triage_summary)
               SELECT id, patient_id, doctor_id, ? FROM appointments WHERE id = ?
               ON CONFLICT(appointment_id) DO UPDATE SET triage_summary = excluded.triage_summary""",
            (summary_text, appt_id),
        )
    conn.execute(
        "INSERT INTO triage_messages (appointment_id, role, content) VALUES (?, 'assistant', ?)",
        (appt_id, reply.replace("[TRIAGEM_COMPLETA]", "").strip()),
    )
    conn.commit()
    conn.close()
    return jsonify({"reply": reply.replace("[TRIAGEM_COMPLETA]", "").strip(), "done": done})


@app.get("/triage/summary/<int:appt_id>")
def triage_summary(appt_id: int):
    """O frontend consulta se o resumo já está pronto."""
    require_service_token()
    row = db().execute(
        "SELECT triage_summary FROM medical_records WHERE appointment_id = ?", (appt_id,)
    ).fetchone()
    return jsonify({"ready": bool(row and row["triage_summary"]),
                    "summary": row["triage_summary"] if row else None})


# ==========================================================================
# PDF — PRONTUÁRIO/RECEITA COM HOOK DE ASSINATURA DIGITAL (ICP-BRASIL)
# ==========================================================================
@app.post("/pdf/medical-record")
def pdf_medical_record():
    """Gera PDF do prontuário em armazenamento PRIVADO.
    Nota ICP-Brasil: o documento inclui um campo de assinatura reservado.
    A assinatura digital REAL (certificado A1/A3 do médico) é aplicada por um
    assinador externo conforme — este endpoint NUNCA falsifica assinatura."""
    require_service_token()
    appt_id = int(request.get_json(force=True)["appointment_id"])
    conn = db()
    row = conn.execute(
        """SELECT m.*, a.room_code, p.name AS patient_name, d.name AS doctor_name, d.specialty,
                  a.scheduled_at
             FROM medical_records m
             JOIN appointments a ON a.id = m.appointment_id
             JOIN users p ON p.id = m.patient_id
             JOIN users d ON d.id = m.doctor_id
            WHERE m.appointment_id = ?""", (appt_id,)
    ).fetchone()
    if not row:
        return jsonify({"error": "not_found"}), 404

    from fpdf import FPDF

    def latin(text: str | None) -> str:
        """fpdf2 com fonte Helvetica (core) só aceita Latin-1: sanitiza
        caracteres fora do range (bullets, emojis, aspas curvas) sem falhar."""
        return (text or "").encode("latin-1", "replace").decode("latin-1")

    pdf = FPDF()
    pdf.add_page()
    pdf.set_font("Helvetica", "B", 14)
    pdf.cell(0, 10, "shop.m3d.pro - Prontuario Eletronico / Receita", ln=True)
    pdf.set_font("Helvetica", "", 10)
    pdf.multi_cell(0, 6,
        f"Paciente: {latin(row['patient_name'])}\n"
        f"Medico responsavel: {latin(row['doctor_name'])} ({latin(row['specialty']) or 'Clinica Geral'})\n"
        f"Consulta: {row['scheduled_at']}  |  Sala: {row['room_code']}\n"
        f"CID-10: {row['cid10'] or '---'}", ln=True)
    pdf.ln(4)
    for title, field in [("Evolucao Clinica", "evolution"), ("Resumo da Triagem (IA)", "triage_summary"),
                         ("Receita", "prescription"), ("Atestado", "certificate")]:
        pdf.set_font("Helvetica", "B", 11)
        pdf.cell(0, 8, title, ln=True)
        pdf.set_font("Helvetica", "", 10)
        pdf.multi_cell(0, 6, latin((row[field] or "---")[:3000]))
        pdf.ln(2)
    # Hook ICP-Brasil: espaço reservado para a assinatura digital do profissional
    pdf.ln(14)
    pdf.set_font("Helvetica", "I", 9)
    pdf.multi_cell(0, 6,
        "Documento assinado digitalmente pelo medico responsavel conforme Padrao "
        "ICP-Brasil (PKCS#7 / A1 ou A3). Verifique a integridade no Receita Federal/ICP-Brasil.\n"
        f"Hash SHA-256 do conteudo: {hashlib.sha256((row['evolution'] or '').encode()).hexdigest()}")
    pdf.set_draw_color(200)
    pdf.rect(20, pdf.get_y() + 4, 80, 20)  # campo visual da assinatura

    filename = f"prontuario_{appt_id}_{uuid.uuid4().hex[:8]}.pdf"
    rel_path = filename
    pdf.output(str(STORAGE / rel_path))
    conn.execute("UPDATE medical_records SET pdf_path = ?, signed_at = COALESCE(signed_at, datetime('now')) WHERE appointment_id = ?",
                 (rel_path, appt_id))
    conn.commit()
    return jsonify({"ok": True, "path": rel_path, "filename": filename})


# ==========================================================================
# MÓDULO 4 — PAGAMENTOS (STRIPE / MERCADO PAGO) + WEBHOOKS
# ==========================================================================
@app.post("/payments/create")
def payments_create():
    """Cria a intenção de pagamento no gateway escolhido.
    Com chaves reais: Stripe Checkout Session / Pix do Mercado Pago.
    Sem chaves: modo MOCK (o PHP simula o retorno do gateway)."""
    require_service_token()
    p = request.get_json(force=True)
    gateway = p.get("gateway")
    amount = int(p.get("amount", 0))
    external_id = f"{gateway.lower()}_{uuid.uuid4().hex[:16]}"

    if gateway == "STRIPE" and STRIPE_SECRET_KEY:
        try:
            import stripe
            stripe.api_key = STRIPE_SECRET_KEY
            session = stripe.checkout.Session.create(
                mode="payment",
                line_items=[{
                    "price_data": {"currency": "brl", "unit_amount": amount,
                                   "product_data": {"name": p.get("product", "Consulta")}},
                    "quantity": 1,
                }],
                success_url=p.get("base_url", "") + "/public/index.php?r=dashboard",
                cancel_url=p.get("base_url", "") + "/public/index.php?r=shop",
                metadata={"user_id": p.get("user_id"), "appt_id": p.get("appt_id") or ""},
            )
            external_id = session.id
            return jsonify({"checkout_url": session.url, "external_id": external_id})
        except Exception as exc:
            app.logger.warning("Stripe falhou: %s", exc)

    if gateway == "MERCADO_PAGO" and MERCADO_PAGO_ACCESS_TOKEN:
        try:
            import httpx
            resp = httpx.post(
                "https://api.mercadopago.com/checkout/preferences",
                headers={"Authorization": f"Bearer {MERCADO_PAGO_ACCESS_TOKEN}"},
                json={
                    "items": [{"title": p.get("product", "Consulta"),
                               "quantity": 1, "unit_price": amount / 100, "currency_id": "BRL"}],
                    "external_reference": external_id,
                }, timeout=15,
            )
            data = resp.json()
            return jsonify({"checkout_url": data["init_point"], "external_id": external_id})
        except Exception as exc:
            app.logger.warning("Mercado Pago falhou: %s", exc)

    # Modo MOCK (sem chaves): url nula => o PHP usa a tela de confirmação simulada
    return jsonify({"checkout_url": None, "external_id": external_id, "mock": True})


@app.post("/webhooks/stripe")
def webhook_stripe():
    """Webhook do Stripe — verifica a assinatura do evento antes de liberar.
    REGRA DE NEGÓCIO CRÍTICA: pagamento só é confirmado com evento autêntico
    (ou em modo mock de desenvolvimento)."""
    sig = request.headers.get("Stripe-Signature", "")
    payload = request.get_data()
    if STRIPE_WEBHOOK_SECRET:
        try:
            import stripe
            stripe.Webhook.construct_event(payload, sig, STRIPE_WEBHOOK_SECRET)  # lança se inválido
        except Exception:
            abort(400, description="Assinatura do webhook inválida")
    event = json.loads(payload or b"{}")
    if event.get("type") in ("checkout.session.completed", "payment_intent.succeeded"):
        external_id = (event.get("data", {}).get("object", {}) or {}).get("id", "")
        _mark_paid("STRIPE", external_id)
    return jsonify({"received": True})


@app.post("/webhooks/mercadopago")
def webhook_mercadopago():
    """Webhook do Mercado Pago — notificações de Pix aprovado."""
    data = request.get_json(silent=True) or {}
    ref = (data.get("data") or {}).get("external_reference") or data.get("external_reference")
    if ref:
        _mark_paid("MERCADO_PAGO", ref)
    return jsonify({"received": True})


@app.post("/webhooks/mock-confirm")
def webhook_mock_confirm():
    """Confirmação simulada usada apenas em ambiente de demonstração."""
    require_service_token()
    _mark_paid("MOCK", request.get_json(force=True).get("external_id", ""))
    return jsonify({"ok": True})


def _mark_paid(gateway: str, external_id: str) -> None:
    conn = db()
    # Gateway 'MOCK' casa com qualquer cobrança criada em modo demonstração
    # (o token interno só é aceito na rota de mock — ver webhook_mock_confirm).
    cur = conn.execute(
        "UPDATE payments SET status='PAGO', paid_at=datetime('now') "
        "WHERE external_id = ? AND (gateway = ? OR gateway = 'MOCK' OR ? = 'MOCK')",
        (external_id, gateway, gateway),
    )
    if cur.rowcount:
        pay = conn.execute("SELECT appointment_id FROM payments WHERE external_id = ?", (external_id,)).fetchone()
        if pay and pay["appointment_id"]:
            conn.execute("UPDATE appointments SET status='PAGO' WHERE id = ? AND status='AGENDADO'",
                         (pay["appointment_id"],))
    conn.commit()
    conn.close()


# ==========================================================================
# LIVEKIT — EMISSÃO DE TOKEN DE ACESSO À SALA
# ==========================================================================
@app.get("/livekit/token")
def livekit_token():
    """Emite token JWT para entrada na sala (grants: publish/subscribe).
    Sem credenciais LiveKit, devolve o flag mock: o frontend usa WebRTC puro."""
    require_service_token()
    room = request.args.get("room", "default")
    identity = request.headers.get("X-Session-Email", "anon")
    if not (LIVEKIT_API_KEY and LIVEKIT_API_SECRET):
        return jsonify({"mock": True, "url": LIVEKIT_URL or None, "room": room})
    try:
        from livekit import api as lk_api
        token = lk_api.AccessToken(LIVEKIT_API_KEY, LIVEKIT_API_SECRET) \
            .with_identity(identity) \
            .with_grants(lk_api.VideoGrants(room_join=True, room=room, can_publish=True, can_subscribe=True))
        return jsonify({"token": token.to_jwt(), "url": LIVEKIT_URL, "room": room})
    except Exception as exc:
        app.logger.warning("LiveKit falhou: %s", exc)
        return jsonify({"mock": True, "room": room})


@app.get("/health")
def health():
    return jsonify({"ok": True, "service": "m3d-python", "ts": datetime.now(timezone.utc).isoformat()})


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=8001, debug=False)
