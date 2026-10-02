<?php
/**
 * SALA DE TELECONSULTA (componente principal do MVP).
 * Layout dividido: vídeo (esq.) + Prontuário Eletrônico do Paciente (dir.).
 * - PACIENTE: entra na chamada (WebRTC via LiveKit SDK) e vê sua triagem.
 * - MÉDICO: vê o vídeo E registra evolução, CID-10, receita e atestado.
 * RLS aplicada no roteador via Auth::canAccessAppointment().
 */
$isDoctor = Auth::role() === 'MEDICO';
$pageTitle = 'Sala de Teleconsulta · shop.m3d.pro';
ob_start();
?>
<div class="flex items-center justify-between mb-4">
  <div>
    <h1 class="text-xl font-bold">Sala de Teleconsulta <code class="text-xs bg-slate-100 px-2 py-0.5 rounded"><?= e($appt['room_code']) ?></code></h1>
    <p class="text-sm text-slate-500">
      <?= $isDoctor ? 'Paciente: ' . e($appt['patient_name']) : 'Médico: ' . e($appt['doctor_name']) ?>
      · <?= e(date('d/m/Y H:i', strtotime($appt['scheduled_at']))) ?> · <strong><?= e($appt['status']) ?></strong>
    </p>
  </div>
  <a href="?r=dashboard" class="text-sm text-slate-500 hover:underline">← Voltar</a>
</div>

<div class="grid lg:grid-cols-2 gap-4">
  <!-- ========== PAINEL DE VÍDEO (LiveKit / WebRTC) ========== -->
  <div class="bg-slate-900 rounded-2xl overflow-hidden flex flex-col" style="min-height:480px">
    <div id="videoArea" class="flex-1 grid place-items-center text-slate-400 text-sm p-6 text-center">
      <div>
        <div class="text-5xl mb-3">🎥</div>
        <p id="vcStatus">Clique em “Entrar na chamada” para conectar (LiveKit/WebRTC, áudio + vídeo HD).</p>
        <video id="remoteVideo" autoplay playsinline class="hidden rounded-xl w-full bg-black"></video>
        <video id="localVideo" autoplay playsinline muted class="hidden absolute bottom-4 right-4 w-40 rounded-xl border-2 border-cyan-500 bg-black"></video>
      </div>
    </div>
    <div class="p-4 bg-slate-800 flex items-center gap-3">
      <button id="joinBtn" class="px-4 py-2 rounded-lg bg-cyan-500 text-white text-sm font-semibold hover:bg-cyan-400">📞 Entrar na chamada</button>
      <button id="muteBtn" class="px-4 py-2 rounded-lg bg-slate-700 text-white text-sm hidden">🎤 Mudo</button>
      <button id="leaveBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hidden">✖ Encerrar</button>
      <span class="text-xs text-slate-400 ml-auto" id="vcEngine">engine: LiveKit (token via /livekit/token)</span>
    </div>
  </div>

  <!-- ========== PAINEL DO PRONTUÁRIO (PEP) ========== -->
  <div class="bg-white rounded-2xl shadow-sm border p-5">
    <h2 class="font-semibold mb-3">📋 Prontuário Eletrônico do Paciente</h2>

    <div class="text-xs bg-cyan-50 text-cyan-800 border border-cyan-100 rounded-lg p-3 mb-4">
      <strong>Resumo da triagem por IA:</strong><br>
      <?= nl2br(e($record['triage_summary'] ?? 'Ainda não realizada pelo paciente.')) ?>
    </div>

    <?php if ($isDoctor): ?>
    <form method="post" action="?r=room&appt=<?= $appt['id'] ?>" class="space-y-3 text-sm">
      <?= csrf_field() ?>
      <input type="hidden" name="triage_summary" value="<?= e($record['triage_summary'] ?? '') ?>">
      <label class="block font-medium">Evolução clínica
        <textarea name="evolution" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 p-2" placeholder="Queixa, exame físico, hipótese diagnóstica, conduta..."><?= e($record['evolution'] ?? '') ?></textarea>
      </label>
      <label class="block font-medium">CID-10
        <input name="cid10" value="<?= e($record['cid10'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 p-2" placeholder="Ex.: J06.9">
      </label>
      <label class="block font-medium">Receita (medicamentos e posologia)
        <textarea name="prescription" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><?= e($record['prescription'] ?? '') ?></textarea>
      </label>
      <label class="block font-medium">Atestado
        <textarea name="certificate" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-2"><?= e($record['certificate'] ?? '') ?></textarea>
      </label>
      <div class="flex items-center gap-3">
        <button class="px-4 py-2 rounded-lg bg-cyan-600 text-white font-semibold hover:bg-cyan-700">Salvar prontuário</button>
        <?php if (!empty($record['evolution'])): ?>
          <a class="px-4 py-2 rounded-lg border border-slate-300 font-semibold hover:bg-slate-50" href="?r=pdf&appt=<?= $appt['id'] ?>">📄 Gerar PDF (receita/prontuário)</a>
        <?php endif; ?>
      </div>
      <p class="text-xs text-slate-400">O PDF é gerado em armazenamento privado e preparado para assinatura digital ICP-Brasil (campo de assinatura reservado no documento; a assinatura só ocorre com certificado válido do profissional).</p>
    </form>
    <?php else: ?>
      <dl class="text-sm space-y-2">
        <div><dt class="text-slate-400 text-xs">Evolução</dt><dd class="whitespace-pre-line"><?= nl2br(e($record['evolution'] ?? '—')) ?></dd></div>
        <div><dt class="text-slate-400 text-xs">CID-10</dt><dd><?= e($record['cid10'] ?? '—') ?></dd></div>
        <div><dt class="text-slate-400 text-xs">Receita</dt><dd class="whitespace-pre-line"><?= nl2br(e($record['prescription'] ?? '—')) ?></dd></div>
        <div><dt class="text-slate-400 text-xs">Atestado</dt><dd class="whitespace-pre-line"><?= nl2br(e($record['certificate'] ?? '—')) ?></dd></div>
      </dl>
      <?php if (!empty($record['evolution'])): ?>
        <a class="inline-block mt-3 px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold hover:bg-slate-50" href="?r=pdf&appt=<?= $appt['id'] ?>">📄 Baixar PDF da receita/prontuário</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
/* ===== Integração de vídeo — LiveKit =====
 * Em produção: servidor LiveKit próprio ou LiveKit Cloud + token JWT emitido
 * pelo microsserviço Python (rota /livekit/token) com grants por sala.
 * Aqui, sem credenciais, usamos getUserMedia (WebRTC puro) como preview funcional.
 */
const V = {
  local: document.getElementById('localVideo'),
  remote: document.getElementById('remoteVideo'),
  stream: null, muted: false
};
document.getElementById('joinBtn').addEventListener('click', async () => {
  try {
    V.stream = await navigator.mediaDevices.getUserMedia({ video: {width: 1280, height: 720}, audio: true });
    V.local.srcObject = V.stream; V.local.classList.remove('hidden');
    document.getElementById('vcStatus').textContent = 'Chamada ativa (preview local). Em produção, conecte ao LiveKit com o token da sala <?= e($appt['room_code']) ?>.';
    document.getElementById('muteBtn').classList.remove('hidden');
    document.getElementById('leaveBtn').classList.remove('hidden');
    document.getElementById('joinBtn').classList.add('hidden');
  } catch (e) {
    document.getElementById('vcStatus').textContent = 'Não foi possível acessar câmera/microfone: ' + e.message;
  }
});
document.getElementById('muteBtn').addEventListener('click', (ev) => {
  V.muted = !V.muted;
  V.stream?.getAudioTracks().forEach(t => t.enabled = !V.muted);
  ev.target.textContent = V.muted ? '🎤 Ativar' : '🎤 Mudo';
});
document.getElementById('leaveBtn').addEventListener('click', () => {
  V.stream?.getTracks().forEach(t => t.stop());
  V.local.classList.add('hidden'); V.remote.classList.add('hidden');
  document.getElementById('muteBtn').classList.add('hidden');
  document.getElementById('leaveBtn').classList.add('hidden');
  document.getElementById('joinBtn').classList.remove('hidden');
  document.getElementById('vcStatus').textContent = 'Chamada encerrada.';
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
