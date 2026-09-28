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
 */
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Doom\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $aliases = [
        'Resources' => 'RData.php',
        'TexPatch' => 'RData.php',
        'Texture' => 'RData.php',
        'Renderer' => 'Render.php',
        'RenderClipRange' => 'Render.php',
        'Visplane' => 'Render.php',
        'DrawSeg' => 'Render.php',
        'Intermission' => 'WiStuff.php',
        'WbStart' => 'WiStuff.php',
        'WiAnim' => 'WiStuff.php',
        'SpriteFrame' => 'Sprites.php',
        'Ticcmd' => 'Player.php',
        'VerticalDoor' => 'Specials.php',
        'Plat' => 'Specials.php',
        'FloorMove' => 'Specials.php',
        'CeilingMove' => 'Specials.php',
        'Button' => 'Specials.php',
        'MenuItem' => 'Menu.php',
        'MenuDef' => 'Menu.php',
        'MoveCheck' => 'Collision.php',
        'Lump' => 'Wad.php',
        'Vertex' => 'World.php',
        'Sector' => 'World.php',
        'Side' => 'World.php',
        'Line' => 'World.php',
        'Seg' => 'World.php',
        'Subsector' => 'World.php',
        'Node' => 'World.php',
        'MapThing' => 'World.php',
    ];
    $file = $aliases[$relative] ?? ($relative . '.php');
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $file);
    if (is_file($path)) {
        require_once $path;
    }
});
