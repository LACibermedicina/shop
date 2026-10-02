<?php
/** Dashboard — renderização distinta por role (RBAC). */
$u = Auth::user();
$pageTitle = 'Dashboard · shop.m3d.pro';
ob_start();
?>
<h1 class="text-2xl font-bold mb-1">Olá, <?= e($u['name']) ?> 👋</h1>
<p class="text-sm text-slate-500 mb-6">Perfil: <strong><?= e($u['role']) ?></strong></p>

<?php if (Auth::role() === 'PACIENTE'): ?>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Minhas consultas</h2>
    <a href="?r=schedule" class="px-4 py-2 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700">+ Agendar consulta</a>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Data</th><th class="p-3">Médico</th><th class="p-3">Status</th><th class="p-3">Ações</th></tr></thead>
      <tbody>
      <?php foreach ($appointments as $a): ?>
        <tr class="border-t">
          <td class="p-3"><?= e(date('d/m/Y H:i', strtotime($a['scheduled_at']))) ?></td>
          <td class="p-3"><?= e($a['doctor_name']) ?> <span class="text-slate-400">(<?= e($a['specialty'] ?? '—') ?>)</span></td>
          <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-cyan-50 text-cyan-700"><?= e($a['status']) ?></span></td>
          <td class="p-3 space-x-2 whitespace-nowrap">
            <a class="text-cyan-700 hover:underline" href="?r=triage&appt=<?= $a['id'] ?>">Triagem IA</a>
            <a class="text-cyan-700 hover:underline" href="?r=room&appt=<?= $a['id'] ?>">Entrar na sala</a>
            <?php if ($a['status'] === 'AGENDADO'): ?>
              <a class="text-emerald-700 hover:underline" href="?r=checkout&p=CONSULTA_AVULSA&appt=<?= $a['id'] ?>">Pagar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$appointments): ?>
        <tr><td colspan="4" class="p-6 text-center text-slate-400">Nenhuma consulta agendada ainda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h2 class="text-lg font-semibold mt-8 mb-3">Meus prontuários e documentos</h2>
  <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Data</th><th class="p-3">Médico</th><th class="p-3">CID-10</th><th class="p-3">PDF</th></tr></thead>
      <tbody>
      <?php foreach ($records as $r): ?>
        <tr class="border-t">
          <td class="p-3"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
          <td class="p-3"><?= e($r['doctor_name']) ?></td>
          <td class="p-3"><?= e($r['cid10'] ?? '—') ?></td>
          <td class="p-3"><a class="text-cyan-700 hover:underline" href="?r=pdf&appt=<?= $r['appointment_id'] ?>">Baixar</a></td>
        </tr>
      <?php endforeach; if (!$records): ?>
        <tr><td colspan="4" class="p-6 text-center text-slate-400">Nenhum documento ainda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif (Auth::role() === 'MEDICO'): ?>
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">Fila de atendimento</h2>
    <span class="text-sm text-slate-400">Especialidade: <?= e($u['specialty'] ?? '—') ?></span>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Data</th><th class="p-3">Paciente</th><th class="p-3">Status</th><th class="p-3">Sala</th></tr></thead>
      <tbody>
      <?php foreach ($appointments as $a): ?>
        <tr class="border-t">
          <td class="p-3"><?= e(date('d/m/Y H:i', strtotime($a['scheduled_at']))) ?></td>
          <td class="p-3"><?= e($a['patient_name']) ?></td>
          <td class="p-3"><span class="px-2 py-0.5 rounded text-xs bg-cyan-50 text-cyan-700"><?= e($a['status']) ?></span></td>
          <td class="p-3"><a class="px-3 py-1.5 rounded-lg bg-cyan-600 text-white text-xs font-semibold hover:bg-cyan-700" href="?r=room&appt=<?= $a['id'] ?>">Atender (vídeo + PEP)</a></td>
        </tr>
      <?php endforeach; if (!$appointments): ?>
        <tr><td colspan="4" class="p-6 text-center text-slate-400">Nenhum paciente na fila.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h2 class="text-lg font-semibold mt-8 mb-3">Meus prontuários recentes</h2>
  <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Data</th><th class="p-3">Paciente</th><th class="p-3">CID-10</th><th class="p-3">Evolução</th></tr></thead>
      <tbody>
      <?php foreach ($records as $r): ?>
        <tr class="border-t">
          <td class="p-3"><?= e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
          <td class="p-3"><?= e($r['patient_name']) ?></td>
          <td class="p-3"><?= e($r['cid10'] ?? '—') ?></td>
          <td class="p-3 max-w-md truncate text-slate-500"><?= e($r['evolution'] ?? '—') ?></td>
        </tr>
      <?php endforeach; if (!$records): ?>
        <tr><td colspan="4" class="p-6 text-center text-slate-400">Nenhum registro ainda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: /* ADMIN */ ?>
  <p class="text-slate-500">Visão administrativa consolidada — <a class="text-cyan-700 hover:underline" href="?r=admin">abrir painel Admin</a>.</p>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
