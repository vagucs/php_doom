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

/** Binary helpers for little-endian DOOM data structures. */
final class Bin
{
    public static function i16(string $data, int $off): int
    {
        $value = self::u16($data, $off);
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    public static function u16(string $data, int $off): int
    {
        return unpack('vvalue', $data, $off)['value'];
    }

    public static function u32(string $data, int $off): int
    {
        return unpack('Vvalue', $data, $off)['value'];
    }

    public static function i32(string $data, int $off): int
    {
        return Compat::asI32(self::u32($data, $off));
    }

    public static function name8(string $data, int $off = 0): string
    {
        $raw = substr($data, $off, 8);
        $nul = strpos($raw, "\0");
        if ($nul !== false) {
            $raw = substr($raw, 0, $nul);
        }
        return strtoupper(rtrim($raw, ' '));
    }
}
