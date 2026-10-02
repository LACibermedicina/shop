<?php $pageTitle = 'Agendar · shop.m3d.pro'; ob_start(); ?>
<div class="max-w-lg mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-xl font-bold mb-6">Agendar teleconsulta</h1>
  <form method="post" action="?r=schedule" class="space-y-4">
    <?= csrf_field() ?>
    <label class="block text-sm font-medium">Médico
      <select name="doctor_id" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
        <?php foreach ($doctors as $d): ?>
          <option value="<?= $d['id'] ?>"><?= e($d['name']) ?> — <?= e($d['specialty'] ?? 'Clínica Geral') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block text-sm font-medium">Especialidade
      <input name="specialty" class="mt-1 w-full rounded-lg border border-slate-300 p-2" placeholder="Clínica Geral">
    </label>
    <label class="block text-sm font-medium">Data e hora
      <input type="datetime-local" name="scheduled_at" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
    </label>
    <p class="text-xs text-slate-400">Após agendar, você passará pela <strong>triagem por IA</strong> (anamnese prévia) e pelo pagamento da consulta avulsa.</p>
    <button class="w-full bg-cyan-600 text-white rounded-lg py-2 font-semibold hover:bg-cyan-700">Agendar e iniciar triagem</button>
  </form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
