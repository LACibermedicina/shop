<?php $pageTitle = 'Criar conta · shop.m3d.pro'; ob_start(); ?>
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-xl font-bold mb-6">Criar conta</h1>
  <form method="post" action="?r=register" class="space-y-4">
    <?= csrf_field() ?>
    <label class="block text-sm font-medium">Nome completo
      <input name="name" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
    </label>
    <label class="block text-sm font-medium">E-mail
      <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-300 p-2">
    </label>
    <label class="block text-sm font-medium">Senha (mín. 8 caracteres)
      <input type="password" name="password" required minlength="8" class="mt-1 w-full rounded-lg border border-slate-300 p-2">
    </label>
    <label class="block text-sm font-medium">Perfil
      <select name="role" class="mt-1 w-full rounded-lg border border-slate-300 p-2">
        <option value="PACIENTE">Paciente</option>
        <option value="MEDICO">Médico</option>
      </select>
    </label>
    <label class="block text-sm font-medium">Especialidade (se médico)
      <input name="specialty" class="mt-1 w-full rounded-lg border border-slate-300 p-2" placeholder="Clínica Geral">
    </label>
    <p class="text-xs text-slate-400">Ao continuar você concorda com o tratamento de dados conforme a LGPD. Dados de saúde são armazenados de forma isolada por perfil (App-Level RLS).</p>
    <button class="w-full bg-cyan-600 text-white rounded-lg py-2 font-semibold hover:bg-cyan-700">Cadastrar</button>
  </form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
