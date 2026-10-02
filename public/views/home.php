<?php $pageTitle = 'Início · shop.m3d.pro'; ob_start(); ?>
<section class="py-10 text-center">
  <h1 class="text-4xl font-extrabold text-slate-900">Telemedicina &amp; Cibermedicina<br><span class="text-cyan-600">com Inteligência Artificial</span></h1>
  <p class="mt-4 text-slate-500 max-w-2xl mx-auto">Consultas por vídeo HD, triagem automatizada por IA antes do atendimento, prontuário eletrônico com receitas e atestados em PDF, e um marketplace de módulos de IA para clínicas.</p>
  <div class="mt-8 flex gap-3 justify-center">
    <a href="?r=register" class="px-6 py-3 rounded-xl bg-cyan-600 text-white font-semibold hover:bg-cyan-700">Começar agora</a>
    <a href="?r=login" class="px-6 py-3 rounded-xl border border-slate-300 font-semibold hover:bg-slate-100">Já tenho conta</a>
  </div>
</section>
<section class="grid md:grid-cols-4 gap-4 py-8">
  <?php
  $feats = [
    ['🤖', 'Triagem por IA', 'Anamnese prévia via chatbot (GPT-4o) que entrega um resumo estruturado ao médico.'],
    ['🎥', 'Sala HD', 'Vídeo/áudio de baixa latência (LiveKit/WebRTC) com o PEP ao lado.'],
    ['🔒', 'LGPD / HIPAA', 'Isolamento de dados por perfil (RLS), TLS 1.3 e armazenamento privado.'],
    ['💳', 'Checkout', 'Stripe (cartão) e Mercado Pago (Pix) com webhooks de confirmação.'],
  ];
  foreach ($feats as [$icon, $t, $d]): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
      <div class="text-3xl"><?= $icon ?></div>
      <h3 class="font-semibold mt-2"><?= $t ?></h3>
      <p class="text-sm text-slate-500 mt-1"><?= $d ?></p>
    </div>
  <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php';
