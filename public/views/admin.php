<?php $pageTitle = 'Admin · shop.m3d.pro'; ob_start(); ?>
<h1 class="text-2xl font-bold mb-6">Painel Administrativo</h1>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <?php
  $cards = [
    ['Usuários', $stats['users'], '👥'],
    ['Agendamentos', $stats['appts'], '🗓️'],
    ['Pagamentos aprovados', $stats['paid'], '✅'],
    ['Receita', money($stats['revenue']), '💰'],
  ];
  foreach ($cards as [$t, $v, $i]): ?>
    <div class="bg-white rounded-2xl shadow-sm border p-4">
      <div class="text-2xl"><?= $i ?></div>
      <div class="text-2xl font-bold mt-1"><?= $v ?></div>
      <div class="text-xs text-slate-400"><?= $t ?></div>
    </div>
  <?php endforeach; ?>
</div>
<h2 class="text-lg font-semibold mb-3">Trilha de auditoria (LGPD)</h2>
<div class="bg-white rounded-2xl shadow-sm border overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Quando</th><th class="p-3">Ator</th><th class="p-3">Entidade</th><th class="p-3">Ação</th><th class="p-3">Detalhe</th><th class="p-3">IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr class="border-t">
        <td class="p-3 whitespace-nowrap"><?= e($l['created_at']) ?></td>
        <td class="p-3"><?= e($l['actor'] ?? 'sistema') ?></td>
        <td class="p-3"><?= e($l['entity']) ?>#<?= e((string)$l['entity_id']) ?></td>
        <td class="p-3"><?= e($l['action']) ?></td>
        <td class="p-3 text-slate-500"><?= e($l['reason'] ?? '') ?></td>
        <td class="p-3 text-slate-400"><?= e($l['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; if (!$logs): ?>
      <tr><td colspan="6" class="p-6 text-center text-slate-400">Sem eventos.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
