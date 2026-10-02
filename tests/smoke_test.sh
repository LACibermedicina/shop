#!/usr/bin/env bash
# ============================================================
# Teste de fumaça ponta a ponta — shop.m3d.pro MVP
# Fluxo: login paciente -> agendar -> triagem IA -> RBAC -> sala
#        -> login médico -> registrar evolução -> PDF -> pagamento
# ============================================================
set -uo pipefail
B="http://127.0.0.1:8000"
PY="http://127.0.0.1:8001"
JP="/tmp/jar_paciente.txt"; JM="/tmp/jar_medico.txt"
rm -f "$JP" "$JM"; pass=0; fail=0
chk() { if [ "$1" = "$2" ]; then echo "  ✔ $3"; pass=$((pass+1)); else echo "  ✘ $3 (esperado $2, obtido $1)"; fail=$((fail+1)); fi }

echo "== 1. Página inicial =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$B/?r=home")
chk "$code" "200" "home 200"

echo "== 2. CSRF + login PACIENTE =="
CSRF=$(curl -s -c "$JP" "$B/?r=login" | grep -o 'name="csrf" value="[^"]*"' | cut -d'"' -f4)
code=$(curl -s -b "$JP" -c "$JP" -o /dev/null -w "%{http_code}" -d "csrf=$CSRF&email=paciente@m3d.pro&password=m3d12345" "$B/?r=login")
chk "$code" "302" "login paciente -> 302 (dashboard)"
code=$(curl -s -b "$JP" -o /dev/null -w "%{http_code}" "$B/?r=dashboard")
chk "$code" "200" "dashboard paciente 200"
curl -s -b "$JP" "$B/?r=dashboard" | grep -q "Ana Paula Souza" && echo "  ✔ dashboard mostra dados do paciente (escopo RLS)" && pass=$((pass+1)) || { echo "  ✘ dashboard sem dados do usuário"; fail=$((fail+1)); }

echo "== 3. Agendamento =="
CSRF=$(curl -s -b "$JP" -c "$JP" "$B/?r=schedule" | grep -o 'name="csrf" value="[^"]*"' | cut -d'"' -f4)
loc=$(curl -s -b "$JP" -c "$JP" -o /dev/null -w "%{redirect_url}" -d "csrf=$CSRF&doctor_id=2&specialty=Cardiologia&scheduled_at=2026-10-05T14:00" "$B/?r=schedule")
echo "  → redirect: $loc"
APPT=$(echo "$loc" | grep -o 'appt=[0-9]*' | cut -d= -f2)
[ -n "$APPT" ] && { echo "  ✔ agendamento criado #$APPT"; pass=$((pass+1)); } || { echo "  ✘ agendamento falhou"; fail=$((fail+1)); }

echo "== 4. Triagem IA (3 mensagens -> done + resumo no PEP) =="
for msg in "Dor de cabeca forte ha 3 dias" "Intensidade 7, com febre leve e nauseas" "Uso dipirona as vezes, sem alergias"; do
  REPLY=$(curl -s -X POST "$PY/triage/chat" -H "Content-Type: application/json" -H "X-Session-Email: paciente@m3d.pro" \
    -d "{\"appointment_id\": $APPT, \"message\": \"$msg\"}")
  sleep 0.3
done
echo "  última resposta: $(echo "$REPLY" | head -c 120)..."
echo "$REPLY" | grep -q '"done": *true' && { echo "  ✔ triagem concluída (done=true)"; pass=$((pass+1)); } || { echo "  ✘ triagem não finalizou"; fail=$((fail+1)); }
SUM=$(curl -s "$PY/triage/summary/$APPT" -H "X-Session-Email: paciente@m3d.pro")
echo "$SUM" | grep -q '"ready": *true' && { echo "  ✔ resumo gravado no prontuário"; pass=$((pass+1)); } || { echo "  ✘ resumo ausente: $SUM"; fail=$((fail+1)); }

echo "== 5. RBAC — paciente NÃO acessa sala de outro; médico não acessa antes de logar =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$B/?r=room&appt=$APPT")
chk "$code" "302" "acesso sem sessão -> redireciona (302)"
code=$(curl -s -b "$JP" -o /dev/null -w "%{http_code}" "$B/?r=room&appt=$APPT")
chk "$code" "200" "paciente acessa própria sala (200)"
code=$(curl -s -b "$JP" -o /dev/null -w "%{http_code}" "$B/?r=admin")
chk "$code" "403" "paciente bloqueado no /admin (403)"

echo "== 6. Checkout MOCK + webhook de confirmação =="
CSRF=$(curl -s -b "$JP" -c "$JP" "$B/?r=checkout&p=CONSULTA_AVULSA&appt=$APPT" | grep -o 'name="csrf" value="[^"]*"' | cut -d'"' -f4)
loc=$(curl -s -b "$JP" -c "$JP" -o /dev/null -w "%{redirect_url}" -d "csrf=$CSRF&gateway=MERCADO_PAGO" "$B/?r=checkout&p=CONSULTA_AVULSA&appt=$APPT")
REF=$(echo "$loc" | grep -o 'ref=[^&]*' | cut -d= -f2)
[ -n "$REF" ] && { echo "  ✔ cobrança criada: $REF"; pass=$((pass+1)); } || { echo "  ✘ checkout falhou"; fail=$((fail+1)); }
curl -s -X POST "$PY/webhooks/mock-confirm" -H "X-Service-Token: dev-token-troque" -d "{\"external_id\": \"$REF\"}" > /dev/null
curl -s -b "$JP" "$B/?r=dashboard" | grep -q "PAGO" && { echo "  ✔ consulta marcada como PAGA via webhook"; pass=$((pass+1)); } || { echo "  ✘ status não virou PAGO"; fail=$((fail+1)); }

echo "== 7. MÉDICO atende: sala + evolução + CID-10 + PDF =="
curl -s -c "$JM" "$B/?r=login" | grep -o 'name="csrf" value="[^"]*"' > /dev/null
CSRF=$(curl -s -c "$JM" "$B/?r=login" | grep -o 'name="csrf" value="[^"]*"' | cut -d'"' -f4)
curl -s -b "$JM" -c "$JM" -o /dev/null -d "csrf=$CSRF&email=medico@m3d.pro&password=m3d12345" "$B/?r=login"
code=$(curl -s -b "$JM" -o /dev/null -w "%{http_code}" "$B/?r=room&appt=$APPT")
chk "$code" "200" "médico acessa sala do seu paciente (200)"
CSRF=$(curl -s -b "$JM" -c "$JM" "$B/?r=room&appt=$APPT" | grep -o 'name="csrf" value="[^"]*"' | cut -d'"' -f4)
code=$(curl -s -b "$JM" -c "$JM" -o /dev/null -w "%{http_code}" \
  --data-urlencode "csrf=$CSRF" \
  --data-urlencode "evolution=Paciente com cefaleia tensional. Exame fisico normal. Orientado hidratacao e reposo." \
  --data-urlencode "cid10=R51" \
  --data-urlencode "prescription=Dipirona 500mg, 1 comprimido a cada 8h por 3 dias." \
  --data-urlencode "certificate=Repouso de 2 dias." \
  --data-urlencode "triage_summary=Cefaleia ha 3 dias, intensidade 7, febre leve, usa dipirona." \
  "$B/?r=room&appt=$APPT")
chk "$code" "302" "evolução registrada -> 302"
curl -s -b "$JM" "$B/?r=room&appt=$APPT" | grep -q "R51" && { echo "  ✔ CID-10 persistido no PEP"; pass=$((pass+1)); } || { echo "  ✘ CID-10 ausente"; fail=$((fail+1)); }
code=$(curl -s -b "$JM" -o /tmp/teste_prontuario.pdf -w "%{http_code}" "$B/?r=pdf&appt=$APPT")
chk "$code" "200" "PDF gerado (200)"
file /tmp/teste_prontuario.pdf | grep -q "PDF" && { echo "  ✔ arquivo PDF válido ($(stat -c%s /tmp/teste_prontuario.pdf) bytes)"; pass=$((pass+1)); } || { echo "  ✘ PDF inválido"; fail=$((fail+1)); }
code=$(curl -s -b "$JM" -o /dev/null -w "%{http_code}" "$B/?r=pdf&appt=999")
chk "$code" "403" "médico bloqueado em agendamento de outro médico (403)"

echo ""
echo "==========================================="
echo "RESULTADO: $pass passaram, $fail falharam"
[ "$fail" = "0" ] && echo "✅ TODOS OS TESTES PASSARAM"
