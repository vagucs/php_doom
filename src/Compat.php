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

/** 32-bit wrap, shifts and 16.16 fixed-point (Harbour xhb_compat / m_fixed). */
final class Compat
{
    public const MASK32 = 0xFFFFFFFF;
    public const MASK31 = 0x7FFFFFFF;

    public static function asU32(int $n): int
    {
        return $n & self::MASK32;
    }

    public static function asI32(int $n): int
    {
        $n &= self::MASK32;
        return $n >= 0x80000000 ? $n - 0x100000000 : $n;
    }

    public static function ushr(int $n, int $bits): int
    {
        $n &= self::MASK32;
        if ($bits <= 0) {
            return $n;
        }
        if ($bits >= 32) {
            return 0;
        }
        return $n >> $bits;
    }

    public static function shar(int $n, int $bits): int
    {
        $n = self::asI32($n);
        if ($bits <= 0) {
            return $n;
        }
        if ($bits >= 31) {
            return $n < 0 ? -1 : 0;
        }
        return $n >> $bits;
    }

    public static function fixedMul(int $a, int $b): int
    {
        return self::asI32((self::asI32($a) * self::asI32($b)) >> Defs::FRACBITS);
    }

    public static function fixedDiv(int $a, int $b): int
    {
        $a = self::asI32($a);
        $b = self::asI32($b);
        if ($b === 0) {
            return $a >= 0 ? 0x7FFFFFFF : -0x80000000;
        }
        $absA = $a < 0 ? -$a : $a;
        $absB = $b < 0 ? -$b : $b;
        if (($absA >> 14) >= $absB) {
            return (($a ^ $b) < 0) ? -0x80000000 : 0x7FFFFFFF;
        }

        return self::asI32(self::floorDiv($a << 16, $b));
    }

    public static function absFixed(int $n): int
    {
        $n = self::asI32($n);
        return $n < 0 ? -$n : $n;
    }

    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        if (($a % $b) !== 0 && (($a < 0) !== ($b < 0))) {
            --$q;
        }
        return $q;
    }
}
