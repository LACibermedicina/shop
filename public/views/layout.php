<?php /** Layout base — Tailwind via CDN + identidade visual M3D. */ ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'shop.m3d.pro') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">
<header class="bg-white border-b border-slate-200">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="?r=home" class="flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-cyan-600 text-white grid place-items-center font-bold">M3D</span>
      <span class="font-semibold text-lg">shop.m3d.pro</span>
      <span class="text-xs text-slate-400 hidden sm:inline">Telemedicina &amp; IA</span>
    </a>
    <nav class="flex items-center gap-4 text-sm">
      <?php if (Auth::user()): ?>
        <a href="?r=dashboard" class="hover:text-cyan-700">Dashboard</a>
        <a href="?r=shop" class="hover:text-cyan-700">Shop</a>
        <?php if (Auth::role() === 'ADMIN'): ?><a href="?r=admin" class="hover:text-cyan-700">Admin</a><?php endif; ?>
        <span class="text-slate-500"><?= e(Auth::user()['name']) ?> <em class="not-italic text-xs px-2 py-0.5 rounded bg-cyan-50 text-cyan-700"><?= e(Auth::role()) ?></em></span>
        <a href="?r=logout" class="text-red-600 hover:underline">Sair</a>
      <?php else: ?>
        <a href="?r=login" class="hover:text-cyan-700">Entrar</a>
        <a href="?r=register" class="px-3 py-1.5 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700">Criar conta</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="flex-1 max-w-6xl w-full mx-auto px-4 py-6">
  <?php if ($f = flash()): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $f['type'] === 'err' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
      <?= e($f['msg']) ?>
    </div>
  <?php endif; ?>
  <?= $content ?? '' ?>
</main>
<footer class="py-6 text-center text-xs text-slate-400 border-t border-slate-200">
  shop.m3d.pro · MVP Telemedicina &amp; Cibermedicina com IA · LGPD/HIPAA by design · TLS 1.3 no proxy
</footer>
</body>
</html>
