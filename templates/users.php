<?php $title = 'Equipe' ?>
<header class="head">
  <div>
    <h1>Equipe</h1>
    <p class="muted">Administrador faz tudo. Moderador bane, revoga e exporta. Suporte cuida da whitelist e só consulta banimentos.</p>
  </div>
</header>

<details class="card">
  <summary>Novo usuário</summary>
  <form method="post" action="/usuarios" class="grid-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Usuário <input name="usuario" required pattern="[a-zA-Z0-9_.\-]{3,40}" autocomplete="off"></label>
    <label>Senha inicial <input name="senha" type="password" required minlength="12" autocomplete="new-password"></label>
    <label>Cargo
      <select name="cargo"><?php foreach ($roles as $r): ?><option value="<?= $r->value ?>"><?= e($r->label()) ?></option><?php endforeach ?></select>
    </label>
    <div><button class="btn">Criar</button></div>
  </form>
</details>

<div class="scroll"><table class="table">
  <thead><tr><th>Usuário</th><th>Cargo</th><th>2FA</th><th>Último acesso</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($people as $p): ?>
    <tr class="<?= $p->active ? '' : 'is-off' ?>">
      <td><strong><?= e($p->username) ?></strong><?= $p->id === $user->id ? ' <span class="muted small">(você)</span>' : '' ?><?= $p->active ? '' : ' <span class="pill pill--revogado">desativado</span>' ?></td>
      <td>
        <form method="post" action="/usuarios/<?= $p->id ?>/cargo" class="inline-form">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <select name="cargo" aria-label="Cargo de <?= e($p->username) ?>">
            <?php foreach ($roles as $r): ?><option value="<?= $r->value ?>" <?= $r === $p->role ? 'selected' : '' ?>><?= e($r->label()) ?></option><?php endforeach ?>
          </select>
          <button class="btn btn--ghost btn--small">Salvar</button>
        </form>
      </td>
      <td><?= $p->totpEnabled ? '<span class="pill pill--ativo">ligado</span>' : '<span class="muted small">desligado</span>' ?></td>
      <td class="small nowrap"><?= e(data_br($p->lastLoginAt)) ?></td>
      <td class="right">
        <?php if ($p->id !== $user->id): ?>
        <form method="post" action="/usuarios/<?= $p->id ?>/ativo" data-confirm="<?= $p->active ? 'Desativar' : 'Reativar' ?> <?= e($p->username) ?>?">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="btn btn--small <?= $p->active ? 'btn--danger' : 'btn--ghost' ?>"><?= $p->active ? 'Desativar' : 'Reativar' ?></button>
        </form>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
