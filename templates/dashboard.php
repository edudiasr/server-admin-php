<?php
use Admin\Domain\Permission;

$title = 'Visão geral';
?>
<header class="head">
  <h1>Visão geral</h1>
  <?php if ($user->can(Permission::Export)): ?>
  <div class="head__actions">
    <a class="btn btn--ghost" href="/exportar/whitelist.txt">Baixar whitelist.txt</a>
    <a class="btn btn--ghost" href="/exportar/ban.txt">Baixar ban.txt</a>
  </div>
  <?php endif ?>
</header>

<div class="stats">
  <a class="stat" href="/whitelist"><b><?= $whitelisted ?></b><span>na whitelist</span></a>
  <a class="stat" href="/bans"><b><?= $bans['active'] ?></b><span>banimentos ativos</span></a>
  <a class="stat" href="/bans"><b><?= $bans['expiring'] ?></b><span>vencem nos próximos 7 dias</span></a>
  <div class="stat"><b><?= $bans['today'] ?></b><span>banimentos nas últimas 24 h</span></div>
</div>

<?php if ($recent): ?>
<section class="card">
  <h2>Últimas ações da equipe</h2>
  <?php require __DIR__ . '/_audit_rows.php' ?>
  <p><a href="/auditoria">Ver tudo →</a></p>
</section>
<?php endif ?>
