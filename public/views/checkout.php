<?php $pageTitle = 'Checkout · shop.m3d.pro'; ob_start(); ?>
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-xl font-bold mb-1">Checkout</h1>
  <p class="text-sm text-slate-500 mb-6"><?= e($product['name']) ?> — <strong><?= money((int)$product['price_cents']) ?></strong></p>
  <form method="post" action="?r=checkout&p=<?= e($product['code']) ?><?= isset($_GET['appt']) ? '&appt=' . (int)$_GET['appt'] : '' ?>" class="space-y-4">
    <?= csrf_field() ?>
    <label class="block text-sm font-medium">Forma de pagamento
      <select name="gateway" class="mt-1 w-full rounded-lg border border-slate-300 p-2">
        <option value="STRIPE">Cartão de crédito — Stripe</option>
        <option value="MERCADO_PAGO">Pix — Mercado Pago</option>
      </select>
    </label>
    <p class="text-xs text-slate-400">O pagamento é confirmado por <strong>webhook</strong> do gateway (rota <code>/webhooks/&lt;gateway&gt;</code> no microsserviço Python). Sem chaves configuradas, o sistema roda em modo demonstração.</p>
    <button class="w-full bg-emerald-600 text-white rounded-lg py-2 font-semibold hover:bg-emerald-700">Pagar <?= money((int)$product['price_cents']) ?></button>
  </form>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
