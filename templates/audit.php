<?php $title = 'Auditoria' ?>
<header class="head">
  <div>
    <h1>Auditoria</h1>
    <p class="muted">Tudo que a equipe faz no painel fica registrado aqui. O registro não pode ser editado nem apagado pelo painel.</p>
  </div>
  <form class="search" method="get" action="/auditoria">
    <select name="acao" aria-label="Ação">
      <option value="">Todas as ações</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $a === $action ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach ?>
    </select>
    <select name="usuario" aria-label="Usuário">
      <option value="">Toda a equipe</option>
      <?php foreach ($people as $p): ?><option value="<?= $p->id ?>" <?= $p->id === $userId ? 'selected' : '' ?>><?= e($p->username) ?></option><?php endforeach ?>
    </select>
    <button class="btn btn--ghost">Filtrar</button>
  </form>
</header>

<?php if ($result['rows']): ?>
  <?php $recent = $result['rows']; require __DIR__ . '/_audit_rows.php' ?>
<?php else: ?>
  <p class="empty">Nada registrado com esses filtros.</p>
<?php endif ?>

<?php $total = $result['total']; $query = array_filter(['acao' => $action, 'usuario' => $userId]); require __DIR__ . '/_pagination.php' ?>
