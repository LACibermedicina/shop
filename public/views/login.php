<?php $pageTitle = 'Login · shop.m3d.pro'; ob_start(); ?>
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-xl font-bold mb-1">Entrar na plataforma</h1>
  <p class="text-sm text-slate-500 mb-6">Paciente · Médico · Administrador</p>
  <form method="post" action="?r=login" class="space-y-4">
    <?= csrf_field() ?>
    <label class="block text-sm font-medium">E-mail
      <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-300 p-2" placeholder="voce@exemplo.com">
    </label>
    <label class="block text-sm font-medium">Senha
      <input type="password" name="password" required minlength="6" class="mt-1 w-full rounded-lg border border-slate-300 p-2">
    </label>
    <button class="w-full bg-cyan-600 text-white rounded-lg py-2 font-semibold hover:bg-cyan-700">Entrar</button>
  </form>
  <p class="text-sm mt-4 text-slate-500">Não tem conta? <a class="text-cyan-700 hover:underline" href="?r=register">Cadastre-se</a></p>
  <div class="mt-6 text-xs text-slate-400 border-t pt-4">
    Contas de demonstração (seed):<br>
    paciente@m3d.pro · medico@m3d.pro · admin@m3d.pro — senha: <code>m3d12345</code>
  </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
