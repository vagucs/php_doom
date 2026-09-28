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
 * Entry: php doom.php [-iwad DOOM1.WAD] [-fps] [-warp 1 1] [-crt]
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "php_doom needs PHP 8.1 or newer (this is " . PHP_VERSION . ")\n");
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "php_doom is a CLI program, not a web app. Run: php doom.php\n");
    exit(1);
}

$composer = __DIR__ . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    require __DIR__ . '/src/autoload.php';
}

exit(\Doom\Game::main($argv));
