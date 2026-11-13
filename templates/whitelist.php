<?php
use Admin\Domain\Permission;

$title = 'Whitelist';
$canEdit = $user->can(Permission::WhitelistEdit);
?>
<header class="head">
  <div>
    <h1>Whitelist</h1>
    <p class="muted"><?= $result['total'] ?> jogador(es)<?= $search !== '' ? ' encontrados' : '' ?></p>
  </div>
  <form class="search" method="get" action="/whitelist">
    <input name="busca" value="<?= e($search) ?>" placeholder="Nome ou SteamID" aria-label="Buscar">
    <button class="btn btn--ghost">Buscar</button>
  </form>
</header>

<?php if ($canEdit): ?>
<details class="card" <?= $result['total'] === 0 ? 'open' : '' ?>>
  <summary>Adicionar jogador</summary>
  <form method="post" action="/whitelist" class="grid-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>SteamID64 ou link do perfil <input name="steam_id" required placeholder="7656119…"></label>
    <label>Nome no jogo <input name="nome" required maxlength="64"></label>
    <label class="span-2">Observação <input name="observacao" maxlength="200" placeholder="ex.: aprovado no Discord por @fulano"></label>
    <div><button class="btn">Adicionar</button></div>
  </form>
</details>
<?php endif ?>

<?php if ($result['rows']): ?>
<div class="scroll"><table class="table">
  <thead><tr><th>Jogador</th><th>SteamID64</th><th>Observação</th><th>Adicionado</th><?php if ($canEdit): ?><th></th><?php endif ?></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $row): ?>
    <tr>
      <td><strong><?= e($row['player_name']) ?></strong></td>
      <td class="mono"><a href="https://steamcommunity.com/profiles/<?= e($row['steam_id']) ?>" target="_blank" rel="noopener noreferrer"><?= e($row['steam_id']) ?></a></td>
      <td class="muted"><?= e($row['note']) ?></td>
      <td class="nowrap small"><?= e(data_br($row['created_at'])) ?><br><span class="muted">por <?= e($row['added_by_name']) ?></span></td>
      <?php if ($canEdit): ?>
      <td class="right">
        <form method="post" action="/whitelist/<?= (int) $row['id'] ?>/remover" data-confirm="Tirar <?= e($row['player_name']) ?> da whitelist?">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="btn btn--danger btn--small">Remover</button>
        </form>
      </td>
      <?php endif ?>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php else: ?>
<p class="empty"><?= $search !== '' ? 'Ninguém encontrado com "' . e($search) . '".' : 'Nenhum jogador na whitelist ainda.' ?></p>
<?php endif ?>

<?php $total = $result['total']; $query = ['busca' => $search]; require __DIR__ . '/_pagination.php' ?>
