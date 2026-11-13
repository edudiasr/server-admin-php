<?php $title = 'Verificação' ?>
<section class="auth">
  <h1>Verificação em duas etapas</h1>
  <p class="muted">Digite o código de 6 dígitos do aplicativo autenticador.</p>
  <?php if (!empty($error)): ?><div class="flash flash--erro"><?= e($error) ?></div><?php endif ?>
  <form method="post" action="/login/2fa" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Código <input name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="code"></label>
    <button class="btn">Confirmar</button>
  </form>
  <p class="muted small"><a href="/login">Voltar</a></p>
</section>
