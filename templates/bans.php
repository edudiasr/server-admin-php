<?php
use Admin\Domain\Permission;

$title = 'Banimentos';
$canCreate = $user->can(Permission::BansCreate);
$canRevoke = $user->can(Permission::BansRevoke);
$tabs = ['ativos' => 'Ativos', 'expirados' => 'Expirados', 'revogados' => 'Revogados', 'todos' => 'Todos'];
$durations = ['1' => '1 dia', '3' => '3 dias', '7' => '7 dias', '30' => '30 dias', 'perm' => 'Permanente'];
?>
<header class="head">
  <div>
    <h1>Banimentos</h1>
    <p class="muted"><?= $result['total'] ?> <?= mb_strtolower($tabs[$status]) ?></p>
  </div>
  <form class="search" method="get" action="/bans">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input name="busca" value="<?= e($search) ?>" placeholder="Nome ou SteamID" aria-label="Buscar">
    <button class="btn btn--ghost">Buscar</button>
  </form>
</header>

<nav class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="?<?= e(http_build_query(['status' => $key, 'busca' => $search])) ?>" class="<?= $key === $status ? 'is-active' : '' ?>"><?= $label ?></a>
  <?php endforeach ?>
</nav>

<?php if ($canCreate): ?>
<details class="card">
  <summary>Banir jogador</summary>
  <form method="post" action="/bans" class="grid-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>SteamID64 ou link do perfil <input name="steam_id" required></label>
    <label>Nome no jogo <input name="nome" required maxlength="64"></label>
    <label class="span-2">Motivo <input name="motivo" required minlength="5" maxlength="200" placeholder="vai aparecer no ban.txt e no histórico"></label>
    <label>Duração
      <select name="duracao">
        <?php foreach ($durations as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach ?>
      </select>
    </label>
    <div><button class="btn btn--danger">Banir</button></div>
  </form>
</details>
<?php endif ?>

<?php if ($result['rows']): ?>
<div class="scroll"><table class="table">
  <thead><tr><th>Jogador</th><th>Motivo</th><th>Período</th><th>Situação</th><?php if ($canRevoke): ?><th></th><?php endif ?></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $ban):
      $state = $ban['revoked_at'] ? 'revogado' : (($ban['expires_at'] && $ban['expires_at'] <= $now) ? 'expirado' : 'ativo'); ?>
    <tr>
      <td><strong><?= e($ban['player_name']) ?></strong><br><span class="mono small"><?= e($ban['steam_id']) ?></span></td>
      <td><?= e($ban['reason']) ?><br><span class="muted small">por <?= e($ban['created_by_name']) ?></span></td>
      <td class="nowrap small"><?= e(data_br($ban['created_at'])) ?><br><?= $ban['expires_at'] ? 'até ' . e(data_br($ban['expires_at'])) : '<b>permanente</b>' ?></td>
      <td>
        <span class="pill pill--<?= $state ?>"><?= $state ?></span>
        <?php if ($ban['revoked_at']): ?>
          <div class="muted small"><?= e($ban['revoked_by_name']) ?>: “<?= e($ban['revoke_reason']) ?>”</div>
        <?php endif ?>
      </td>
      <?php if ($canRevoke): ?>
      <td class="right">
        <?php if ($state === 'ativo'): ?>
        <form method="post" action="/bans/<?= (int) $ban['id'] ?>/revogar" class="inline-form">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input name="motivo" required minlength="5" maxlength="200" placeholder="motivo da revogação" aria-label="Motivo da revogação">
          <button class="btn btn--ghost btn--small">Revogar</button>
        </form>
        <?php endif ?>
      </td>
      <?php endif ?>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php else: ?>
<p class="empty">Nenhum banimento <?= mb_strtolower($tabs[$status]) === 'todos' ? '' : mb_strtolower($tabs[$status]) ?><?= $search !== '' ? ' para "' . e($search) . '"' : '' ?>.</p>
<?php endif ?>

<?php $total = $result['total']; $query = ['status' => $status, 'busca' => $search]; require __DIR__ . '/_pagination.php' ?>
