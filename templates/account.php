<?php $title = 'Minha conta' ?>
<header class="head"><h1>Minha conta</h1></header>

<div class="cols">
  <section class="card">
    <h2>Trocar senha</h2>
    <form method="post" action="/conta/senha" class="stack">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label>Senha atual <input name="atual" type="password" required autocomplete="current-password"></label>
      <label>Senha nova <input name="nova" type="password" required minlength="12" autocomplete="new-password"></label>
      <label>Repita a senha nova <input name="confirmacao" type="password" required minlength="12" autocomplete="new-password"></label>
      <button class="btn">Trocar</button>
    </form>
  </section>

  <section class="card">
    <h2>Verificação em duas etapas</h2>
    <?php if ($user->totpEnabled): ?>
      <p><span class="pill pill--ativo">ligada</span> O login pede o código do aplicativo depois da senha.</p>
      <form method="post" action="/conta/2fa/desligar" class="stack" data-confirm="Desligar a verificação em duas etapas?">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label>Sua senha, pra confirmar <input name="senha" type="password" required autocomplete="current-password"></label>
        <button class="btn btn--danger">Desligar</button>
      </form>
    <?php else: ?>
      <ol class="steps">
        <li>No aplicativo autenticador (Google Authenticator, Authy, 1Password…), adicione uma conta com a chave:
          <code class="secret"><?= e(trim(chunk_split($totpSecret, 4, ' '))) ?></code>
          <span class="muted small">ou abra este link no celular: <a href="<?= e($totpUri) ?>">otpauth://…</a></span>
        </li>
        <li>Digite o código que o aplicativo mostrar:</li>
      </ol>
      <form method="post" action="/conta/2fa" class="inline-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input name="codigo" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" required class="code" aria-label="Código">
        <button class="btn">Ligar</button>
      </form>
    <?php endif ?>
  </section>
</div>
