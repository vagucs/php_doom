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

/** finesine / finetangent / tantoangle (tables.c generated at runtime). */
final class Tables
{
    /** @var array<int, int> */
    public static array $finesine = [];
    /** @var array<int, int> */
    public static array $finecosine = [];
    /** @var array<int, int> */
    public static array $finetangent = [];
    /** @var array<int, int> */
    public static array $tantoangle = [];

    public static function initTables(): void
    {
        if (self::$finesine !== []) {
            return;
        }

        $sinCount = intdiv(Defs::FINEANGLES, 4) * 5;
        for ($i = 0; $i < $sinCount; ++$i) {
            $a = ($i + 0.5) * M_PI * 2.0 / Defs::FINEANGLES;
            self::$finesine[$i] = (int) (Defs::FRACUNIT * sin($a));
        }
        self::$finecosine = array_slice(self::$finesine, intdiv(Defs::FINEANGLES, 4));

        $tanCount = intdiv(Defs::FINEANGLES, 2);
        for ($i = 0; $i < $tanCount; ++$i) {
            $a = ($i - intdiv(Defs::FINEANGLES, 4) + 0.5) * M_PI * 2.0 / Defs::FINEANGLES;
            $raw = Defs::FRACUNIT * tan($a);
            if (!is_finite($raw)) {
                $value = $a > 0.0 ? 0x7FFFFFFF : -0x7FFFFFFF;
            } elseif ($raw > 0x7FFFFFFF) {
                $value = 0x7FFFFFFF;
            } elseif ($raw < -0x7FFFFFFF) {
                $value = -0x7FFFFFFF;
            } else {
                $value = (int) $raw;
            }
            self::$finetangent[$i] = $value;
        }

        for ($i = 0; $i <= Defs::SLOPERANGE; ++$i) {
            self::$tantoangle[$i] = Compat::asU32(
                (int) (atan($i / Defs::SLOPERANGE) / (M_PI * 2.0) * 0xFFFFFFFF)
            );
        }
    }

    public static function fineSin(int $angle): int
    {
        self::initTables();
        $index = (Compat::asU32($angle) >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK;
        return self::$finesine[$index];
    }

    public static function fineCos(int $angle): int
    {
        self::initTables();
        $index = (
            (Compat::asU32($angle) >> Defs::ANGLETOFINESHIFT)
            + intdiv(Defs::FINEANGLES, 4)
        ) & Defs::FINEMASK;
        return self::$finesine[$index];
    }

    public static function slopeDiv(int $num, int $den): int
    {
        if ($den < 512) {
            return Defs::SLOPERANGE;
        }
        $answer = intdiv($num << 3, $den >> 8);
        return $answer > Defs::SLOPERANGE ? Defs::SLOPERANGE : $answer;
    }
}
