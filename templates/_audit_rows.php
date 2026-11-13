<?php
/** @var list<array<string, mixed>> $recent */
$labels = [
    'login' => 'entrou no painel',
    'logout' => 'saiu do painel',
    'login.falhou' => 'errou a senha',
    'login.bloqueado' => 'foi bloqueado por excesso de tentativas',
    'login.2fa_falhou' => 'errou o código de verificação',
    'whitelist.adicionar' => 'adicionou à whitelist',
    'whitelist.remover' => 'removeu da whitelist',
    'ban.criar' => 'baniu',
    'ban.revogar' => 'revogou o banimento de',
    'exportar.ban' => 'baixou o ban.txt',
    'exportar.whitelist' => 'baixou o whitelist.txt',
    'usuario.criar' => 'criou o usuário',
    'usuario.cargo' => 'mudou o cargo de',
    'usuario.desativar' => 'desativou',
    'usuario.ativar' => 'reativou',
    'conta.senha' => 'trocou a própria senha',
    'conta.2fa_ligar' => 'ligou a verificação em duas etapas',
    'conta.2fa_desligar' => 'desligou a verificação em duas etapas',
];
?>
<div class="scroll"><table class="table">
  <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Detalhes</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $row):
      $details = json_decode($row['details'], true) ?: [];
      $who = $row['username'] ?? ($row['target_type'] === 'usuario' ? $row['target_id'] : '—'); ?>
    <tr class="<?= str_contains($row['action'], 'falhou') || str_contains($row['action'], 'bloqueado') ? 'is-warn' : '' ?>">
      <td class="nowrap"><?= e(data_br($row['created_at'])) ?></td>
      <td><?= e($who) ?></td>
      <td><?= e($labels[$row['action']] ?? $row['action']) ?>
        <?php if (isset($details['nome'])): ?> <strong><?= e($details['nome']) ?></strong><?php endif ?>
        <?php if (isset($details['usuario'])): ?> <strong><?= e($details['usuario']) ?></strong><?php endif ?>
      </td>
      <td class="muted small">
        <?php if (isset($details['motivo'])): ?>“<?= e($details['motivo']) ?>”<?php endif ?>
        <?php if (isset($details['de'], $details['para'])): ?><?= e($details['de']) ?> → <?= e($details['para']) ?><?php endif ?>
        <?php if (array_key_exists('ate', $details)): ?> · <?= $details['ate'] ? 'até ' . e(data_br($details['ate'])) : 'permanente' ?><?php endif ?>
        <?php if ($row['target_type'] === 'steam'): ?><span class="mono"><?= e($row['target_id']) ?></span><?php endif ?>
      </td>
      <td class="mono small"><?= e($row['ip']) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
