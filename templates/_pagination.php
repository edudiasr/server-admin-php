<?php
/** @var int $total @var int $perPage @var int $currentPage @var array $query */
$pages = max(1, (int) ceil($total / $perPage));
if ($pages > 1): ?>
<nav class="pager" aria-label="Paginação">
  <?php if ($currentPage > 1): ?><a href="<?= e(pagina_url($query, $currentPage - 1)) ?>">← Anterior</a><?php endif ?>
  <span>Página <?= $currentPage ?> de <?= $pages ?></span>
  <?php if ($currentPage < $pages): ?><a href="<?= e(pagina_url($query, $currentPage + 1)) ?>">Próxima →</a><?php endif ?>
</nav>
<?php endif ?>
