<?php
/**
 * DOOM generic portado de Harbour para PHP CLI com SDL2.
 *
 * Por Wagner Nunes da Silva
 *
 * vagucs@bol.com.br
 * vagucs@vagucs.com.br
 * vagucs@gmail.com
 *
 * www.vagucs.com.br
 *
 * default.cfg subset (m_misc): mouse, volumes, messages, screenblocks.
 */
declare(strict_types=1);

namespace Doom;

final class Config
{
    public static function path(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . 'default.cfg';
    }

    public static function load(object $game): void
    {
        $path = self::path();
        if (!is_file($path)) {
            return;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return;
        }
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if ($parts === false || count($parts) < 2) {
                continue;
            }
            $key = $parts[0];
            if (!is_numeric($parts[1])) {
                continue;
            }
            $n = (int) $parts[1];
            match ($key) {
                'mouse_sensitivity' => $game->mouseSensitivity = max(0, min(9, $n)),
                'sfx_volume' => $game->sound->sfxVolume = max(0, min(15, $n)),
                'music_volume' => $game->sound->musicVolume = max(0, min(15, $n)),
                'show_messages' => $game->showMessages = $n !== 0,
                'use_mouse' => $game->useMouse = $n !== 0,
                'screenblocks' => $game->screenSize = max(0, min(8, $n - 3)),
                default => null,
            };
        }
    }

    public static function save(object $game): void
    {
        $body = "mouse_sensitivity\t\t" . (int) $game->mouseSensitivity . "\n"
            . "sfx_volume\t\t" . (int) $game->sound->sfxVolume . "\n"
            . "music_volume\t\t" . (int) $game->sound->musicVolume . "\n"
            . "show_messages\t\t" . ($game->showMessages ? 1 : 0) . "\n"
            . "use_mouse\t\t" . ($game->useMouse ? 1 : 0) . "\n"
            . "screenblocks\t\t" . ((int) $game->screenSize + 3) . "\n";
        @file_put_contents(self::path(), $body);
    }
}
