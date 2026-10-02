<?php
/**
 * Helper de view (two-step rendering):
 * 1) o arquivo da página captura seu HTML em $content via output buffering;
 * 2) o layout envolve o conteúdo.
 * Uso no topo de cada página:  <?php $pageTitle='...'; ob_start(); ?>
 * Uso no fim:                  <?php $content = ob_get_clean(); require __DIR__.'/layout.php';
 */
