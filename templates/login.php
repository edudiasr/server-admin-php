<?php $title = 'Entrar' ?>
<section class="auth">
  <h1><?= e($serverName) ?></h1>
  <p class="muted">Painel da equipe</p>
  <?php if (!empty($error)): ?><div class="flash flash--erro"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="/login" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Usuário <input name="usuario" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
    <label>Senha <input name="senha" type="password" autocomplete="current-password" required></label>
    <button class="btn">Entrar</button>
  </form>
</section>
