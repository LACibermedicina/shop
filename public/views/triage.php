<?php $pageTitle = 'Triagem IA · shop.m3d.pro'; ob_start(); ?>
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-6">
  <h1 class="text-xl font-bold">Triagem por IA — Anamnese prévia</h1>
  <p class="text-sm text-slate-500 mb-4">Descreva seus sintomas e histórico. A IA (GPT-4o) montará um resumo estruturado para o médico antes da consulta.</p>
  <div id="chat" class="h-80 overflow-y-auto space-y-2 border rounded-xl p-3 bg-slate-50 text-sm"></div>
  <form id="chatForm" class="mt-3 flex gap-2">
    <input id="chatInput" autocomplete="off" class="flex-1 rounded-lg border border-slate-300 p-2" placeholder="Ex.: dor de cabeça há 3 dias, com febre leve...">
    <button class="px-5 py-2 rounded-lg bg-cyan-600 text-white font-semibold hover:bg-cyan-700">Enviar</button>
  </form>
  <div class="mt-6 flex items-center justify-between border-t pt-4">
    <span id="triageStatus" class="text-xs text-slate-400"></span>
    <a id="goPay" class="hidden px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700" href="?r=checkout&p=CONSULTA_AVULSA&appt=<?= $appt['id'] ?>">Ir para o pagamento →</a>
  </div>
</div>
<script>
  const APPT = <?= (int)$appt['id'] ?>;
  const chat = document.getElementById('chat'), input = document.getElementById('chatInput');
  function add(role, text) {
    const div = document.createElement('div');
    div.className = role === 'user' ? 'ml-auto max-w-[80%] bg-cyan-600 text-white rounded-xl px-3 py-2'
                                    : 'mr-auto max-w-[80%] bg-white border rounded-xl px-3 py-2';
    div.textContent = text; chat.appendChild(div); chat.scrollTop = chat.scrollHeight;
  }
  async function pollSummary() {
    const r = await fetch(`<?= env('PY_SERVICE_URL', 'http://127.0.0.1:8001') ?>/triage/summary/${APPT}`, {
      headers: {'X-Session-Email': '<?= e(Auth::user()['email']) ?>'}
    }).catch(() => null);
    if (r && r.ok) {
      const j = await r.json();
      if (j.ready) {
        document.getElementById('triageStatus').textContent = '✅ Resumo da anamnese enviado ao médico.';
        document.getElementById('goPay').classList.remove('hidden');
        add('assistant', 'Resumo estruturado:\n' + j.summary);
        return;
      }
    }
    setTimeout(pollSummary, 4000);
  }
  document.getElementById('chatForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = input.value.trim(); if (!msg) return;
    add('user', msg); input.value = '';
    add('assistant', '…');
    try {
      const r = await fetch(`<?= env('PY_SERVICE_URL', 'http://127.0.0.1:8001') ?>/triage/chat`, {
        method: 'POST', headers: {'Content-Type': 'application/json', 'X-Session-Email': '<?= e(Auth::user()['email']) ?>'},
        body: JSON.stringify({appointment_id: APPT, message: msg})
      });
      const j = await r.json();
      chat.lastChild.remove(); add('assistant', j.reply ?? 'Erro temporário. Tente novamente.');
      if (j.done) setTimeout(pollSummary, 800);
    } catch { chat.lastChild.remove(); add('assistant', 'Serviço de IA indisponível no momento.'); }
  });
  add('assistant', 'Olá! Sou a assistente de triagem da M3D. Conte qual é a sua queixa principal, quando começou e quais medicamentos você usa.');
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
