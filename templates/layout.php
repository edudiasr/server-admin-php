<?php
use Admin\Domain\Permission;

/** @var Admin\Domain\User|null $user */
$user ??= null;
$path ??= '';
$nav = [
    ['/', 'Visão geral', null],
    ['/whitelist', 'Whitelist', Permission::WhitelistView],
    ['/bans', 'Banimentos', Permission::BansView],
    ['/auditoria', 'Auditoria', Permission::AuditView],
    ['/usuarios', 'Equipe', Permission::UsersManage],
];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Painel') ?> · <?= e($serverName) ?></title>
<link rel="stylesheet" href="/assets/painel.css">
<script src="/assets/painel.js" defer></script>
</head>
<body class="<?= $user ? 'app' : 'bare' ?>">
<?php if ($user): ?>
<aside class="side">
  <div class="brand"><span class="brand__dot"></span><?= e($serverName) ?></div>
  <nav>
    <?php foreach ($nav as [$href, $label, $perm]): ?>
      <?php if ($perm === null || $user->can($perm)): ?>
        <a href="<?= $href ?>" class="<?= ($href === '/' ? $path === '/' : str_starts_with($path, $href)) ? 'is-active' : '' ?>"><?= $label ?></a>
      <?php endif ?>
    <?php endforeach ?>
  </nav>
  <div class="side__me">
    <a href="/conta" class="<?= $path === '/conta' ? 'is-active' : '' ?>">
      <strong><?= e($user->username) ?></strong>
      <span><?= e($user->role->label()) ?></span>
    </a>
    <form method="post" action="/sair">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="link">Sair</button>
    </form>
  </div>
</aside>
<?php endif ?>
<main class="main">
  <?php foreach ($flashes ?? [] as $flash): ?>
    <div class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
  <?php endforeach ?>
  <?= $content ?>
</main>
</body>
</html>
