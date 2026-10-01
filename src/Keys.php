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

namespace Doom;

final class Keys
{
    public const TAB = 9;
    public const ESC = 27;
    public const RETURN = 13;
    public const SPACE = 32;
    public const BACKSPACE = 8;

    public const F1 = 0x4000003A;
    public const F2 = 0x4000003D;
    public const F3 = 0x4000003E;
    public const F11 = 0x40000044;
    public const LEFT = 0x40000050;
    public const RIGHT = 0x4000004F;
    public const UP = 0x40000052;
    public const DOWN = 0x40000051;
    public const LSHIFT = 0x400000E1;
    public const RSHIFT = 0x400000E5;
    public const LCTRL = 0x400000E0;
    public const RCTRL = 0x400000E4;
    public const LALT = 0x400000E2;
    public const RALT = 0x400000E6;
    public const PLUS = 43;
    public const EQUALS = 61;
    public const MINUS = 45;
    public const COMMA = 44;
    public const PERIOD = 46;
    public const KP_PLUS = 0x40000057;
    public const KP_MINUS = 0x40000056;
    public const KP_ENTER = 0x40000058;

    public static function isMinus(int $key): bool
    {
        return in_array($key, [self::MINUS, self::KP_MINUS], true);
    }

    public static function isPlus(int $key): bool
    {
        return in_array($key, [self::PLUS, self::EQUALS, self::KP_PLUS], true);
    }

    public static function letter(string $letter): int
    {
        return ord(strtolower($letter)[0]);
    }

    public static function digit(int $digit): int
    {
        return ord((string) max(0, min(9, $digit)));
    }
}
