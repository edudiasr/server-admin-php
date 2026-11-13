<?php

declare(strict_types=1);

/**
 * Dados fictícios pra ver o painel funcionando: bin/console demo
 * Cria admin/moderador/suporte com a senha "demonstracao123" e uma lista de jogadores inventados.
 *
 * @var Admin\App $app
 */

use Admin\Domain\Role;

if ($app->users->count() > 0) {
    fwrite(STDERR, "o banco já tem usuários; a demo só roda num banco vazio\n");
    exit(1);
}

$password = 'demonstracao123';
$ids = [
    'admin' => $app->users->create('admin', $password, Role::Admin),
    'moderador' => $app->users->create('lobo', $password, Role::Moderator),
    'suporte' => $app->users->create('raposa', $password, Role::Support),
];

$names = ['Corvo', 'Gavião', 'Coruja', 'Tatu', 'Capivara', 'Jaguar', 'Sabiá', 'Lontra', 'Quati', 'Tucano',
    'Arara', 'Cutia', 'Tamanduá', 'Jacaré', 'Bem-te-vi', 'Sucuri', 'Ariranha', 'Seriema', 'Paca', 'Mico'];
$notes = ['aprovado no Discord', 'veio do evento de sábado', 'indicado pelo Lobo', '', 'voltou depois de um tempo fora'];

// SteamIDs inventados (prefixo de conta individual + sequência), não são de ninguém
$steam = fn (int $n) => '765611980000' . str_pad((string) (10000 + $n), 5, '0', STR_PAD_LEFT);

$start = $app->clock->now();
foreach ($names as $i => $name) {
    $app->clock->advance(-86400 * (20 - $i));
    $app->whitelist->add($i % 3 === 0 ? $ids['suporte'] : $ids['moderador'], $steam($i), $name, $notes[$i % count($notes)]);
    $app->clock->advance(86400 * (20 - $i));
}

$bans = [
    [40, 'Pirata', 'Uso de cheat (aimbot) confirmado pelo replay', 'perm', 'moderador'],
    [41, 'Fantasma', 'Duplicação de itens', '30', 'moderador'],
    [42, 'Trovão', 'Ofensas no chat global depois de aviso', '3', 'moderador'],
    [43, 'Vulto', 'Combat log repetido', '7', 'admin'],
    [44, 'Sombra', 'Destruição de base fora da regra de raid', '1', 'moderador'],
];
foreach ($bans as [$n, $name, $reason, $duration, $by]) {
    $app->bans->create($ids[$by], $steam($n), $name, $reason, $duration);
    $app->audit->record($ids[$by], 'ban.criar', 'steam', $steam($n), ['nome' => $name, 'motivo' => $reason], '10.0.0.' . $n);
}
// um banimento antigo que já expirou e um revogado, pra as abas não ficarem vazias
$app->clock->advance(-86400 * 10);
$app->bans->create($ids['moderador'], $steam(45), 'Neblina', 'Spam de convite pra outro servidor', '1');
$app->clock->advance(86400 * 10);
$revoked = $app->bans->create($ids['moderador'], $steam(46), 'Brisa', 'Suspeita de cheat', '30');
$app->bans->revoke($ids['admin'], (int) $revoked['id'], 'Replay analisado: era só lag, sem cheat');
$app->audit->record($ids['admin'], 'ban.revogar', 'steam', $steam(46), ['nome' => 'Brisa', 'motivo' => 'Replay analisado: era só lag, sem cheat'], '10.0.0.1');

echo "demo criada: usuários admin, lobo (moderador) e raposa (suporte), senha \"$password\"\n";
