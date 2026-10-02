<?php $pageTitle = 'Shop · shop.m3d.pro'; ob_start(); ?>
<h1 class="text-2xl font-bold mb-1">Shop — Marketplace M3D</h1>
<p class="text-sm text-slate-500 mb-6">Consultas avulsas para pacientes e assinatura de módulos de IA para clínicas. Checkout transparente via Stripe (cartão) ou Mercado Pago (Pix).</p>
<div class="grid md:grid-cols-3 gap-4">
  <?php foreach ($products as $p): ?>
    <div class="bg-white rounded-2xl shadow-sm border p-5 flex flex-col">
      <span class="text-xs px-2 py-0.5 rounded bg-cyan-50 text-cyan-700 w-fit"><?= e($p['audience']) ?></span>
      <h3 class="font-semibold mt-2"><?= e($p['name']) ?></h3>
      <p class="text-sm text-slate-500 mt-1 flex-1"><?= e($p['description']) ?></p>
      <div class="mt-4 flex items-center justify-between">
        <span class="text-xl font-bold"><?= money((int)$p['price_cents']) ?></span>
        <a href="?r=checkout&p=<?= e($p['code']) ?>" class="px-4 py-2 rounded-lg bg-cyan-600 text-white text-sm font-semibold hover:bg-cyan-700">Comprar</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
