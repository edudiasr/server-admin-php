<?php

declare(strict_types=1);

namespace Admin\Domain;

/**
 * Gera o ban.txt e o whitelist.txt que o servidor de DayZ lê da pasta do perfil:
 * um SteamID64 por linha, e o que vem depois de // é comentário.
 */
final class ServerFiles
{
    /** @param list<array<string, mixed>> $bans */
    public static function banTxt(array $bans, string $generatedAt): string
    {
        $lines = ["// ban.txt gerado pelo painel em $generatedAt UTC", '// ' . count($bans) . ' banimento(s) ativo(s)'];
        foreach ($bans as $ban) {
            $until = $ban['expires_at'] ? 'até ' . $ban['expires_at'] : 'permanente';
            $lines[] = sprintf('%s // %s: %s (%s)', $ban['steam_id'], self::clean($ban['player_name']), self::clean($ban['reason']), $until);
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<array<string, mixed>> $entries */
    public static function whitelistTxt(array $entries, string $generatedAt): string
    {
        $lines = ["// whitelist.txt gerado pelo painel em $generatedAt UTC", '// ' . count($entries) . ' jogador(es)'];
        foreach ($entries as $entry) {
            $lines[] = $entry['steam_id'] . ' // ' . self::clean($entry['player_name']);
        }

        return implode("\n", $lines) . "\n";
    }

    /** Nome com quebra de linha viraria uma linha nova no arquivo: tira antes. */
    private static function clean(string $text): string
    {
        return trim(preg_replace('/[\r\n\t]+/', ' ', $text));
    }
}
