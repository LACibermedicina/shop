# shop.m3d.pro — MVP de Telemedicina & Cibermedicina com IA

Sistema completo de telemedicina construído com **Python + PHP + SQLite**
conforme solicitado, mapeando cada requisito do plano original
(Next.js/Supabase/Prisma) para equivalentes funcionais nesta stack.

## Mapa de substituições técnicas (stack solicitada -> implementada)

| Plano original | Implementação neste MVP | Motivo |
|---|---|---|
| Next.js App Router + TS | PHP 8 (front controller `public/index.php` = roteador, views por módulo) | Stack pedida: Python + PHP |
| Tailwind CSS / Shadcn UI | Tailwind via CDN + componentes minimalistas acessíveis | Mesma linguagem visual |
| Supabase PostgreSQL + RLS | SQLite (WAL) + **App-Level RLS** em `src/Auth.php` (escopo obrigatório por role) | SQLite não tem RLS nativa; isolamento equivalente na camada de aplicação |
| Prisma/Drizzle ORM | PDO prepared statements + `database/schema.sql` | — |
| LiveKit | `service/app.py` rota `/livekit/token` (grants por sala) + preview WebRTC puro (getUserMedia) no frontend | Sem credenciais, cai para WebRTC puro |
| OpenAI GPT-4o + LangChain | `/triage/chat` com GPT-4o (system prompt clínico) + motor local de fallback | O MVP nunca fica indisponível sem chave |
| Stripe + Mercado Pago (Pix) | Stripe Checkout + Pix via preferências MP, **webhooks** com verificação de assinatura; modo MOCK sem chaves | — |
| Supabase Storage privado | `data/private/` (fora do webroot, servido apenas com sessão autorizada) | — |
| TLS 1.3 | `deploy/nginx-m3d.conf` — `ssl_protocols TLSv1.3` + HSTS | Camada de borda |
| Assinatura ICP-Brasil | Campo de assinatura reservado no PDF + hash SHA-256 — **não falsifica assinatura**; integra com assinador externo (certificado A1/A3) | Conformidade jurídica |

## Arquitetura

```
Navegador ──TLS 1.3──> nginx ──> PHP (UI, auth, RBAC, agendamento, shop)
                                   │  (mesmo SQLite em modo WAL)
                                   ▼
                                Python (FastAPI/Flask) :8001
                                  ├─ /triage/chat        IA de triagem (GPT-4o)
                                  ├─ /triage/summary/<id>
                                  ├─ /pdf/medical-record PDF prontuário/receita
                                  ├─ /payments/create   Stripe | Mercado Pago | MOCK
                                  ├─ /webhooks/stripe   confirmação assinada
                                  ├─ /webhooks/mercadopago
                                  └─ /livekit/token     token de sala de vídeo
```

## Módulos funcionais (escopo do plano)

1. **Autenticação e perfis (RBAC)** — login/cadastro com roles `PACIENTE`,
   `MEDICO`, `ADMIN`; dashboard distinto por role (paciente: histórico +
   agenda; médico: prontuários + fila de espera; admin: métricas + auditoria).
2. **Triagem por IA (anamnese prévia)** — chatbot na tela pós-agendamento;
   coleta queixa, histórico e medicamentos; grava resumo estruturado no PEP
   visível ao médico antes da consulta.
3. **Sala de teleconsulta** — interface dividida: vídeo (LiveKit/WebRTC) de um
   lado e PEP do outro; registro de evolução, CID-10, receita e atestado;
   PDF em armazenamento privado com hook ICP-Brasil.
4. **Financeiro (marketplace)** — página Shop (consultas avulsas e assinaturas
   de módulos de IA para clínicas), checkout transparente e webhooks de
   confirmação que atualizam o status da consulta.

## Como executar (desenvolvimento)

```bash
cd m3d-telemedicina
chmod +x run.sh && ./run.sh
# Frontend:  http://localhost:8000
# Serviço:   http://127.0.0.1:8001/health
```

Contas de demonstração (senha `m3d12345`):
`admin@m3d.pro` · `medico@m3d.pro` · `paciente@m3d.pro`

Fluxo de teste ponta a ponta: login como paciente → agendar → triagem IA →
pagar (modo demonstração) → login como médico → atender na sala → registrar
evolução/CID-10 → gerar PDF.

## Segurança implementada

- Senhas com bcrypt (`password_hash`), `session_regenerate_id` no login
- Proteção CSRF em todos os formulários (`csrf_token`/`csrf_check`)
- 100% prepared statements (sem concatenação de SQL)
- **App-Level RLS**: toda query clínica/financeira é escopada por role e ID
  (`patientScope`/`doctorScope`/`canAccessAppointment`)
- Trilha de auditoria (`audit_log`) para logins e acesso a prontuários
- Armazenamento privado (`data/private`) fora do webroot
- Headers de segurança + TLS 1.3 no nginx (HSTS)
- Webhooks com verificação de assinatura do gateway

## LGPD

Dados de saúde isolados por perfil, auditoria de acessos, armazenamento
privado e consentimento informado no cadastro. Em produção: revisar DPO,
retenção e política de privacidade antes do go-live.
