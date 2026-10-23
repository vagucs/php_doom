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

/** finesine / finetangent / tantoangle, os mesmos números de tables.prg. */
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

        self::$finesine = TablesData::finesine();
        self::$finetangent = TablesData::finetangent();
        self::$tantoangle = TablesData::tantoangle();
        $quarter = intdiv(Defs::FINEANGLES, 4);
        self::$finecosine = array_slice(self::$finesine, $quarter, Defs::FINEANGLES);
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
        $index = (Compat::asU32($angle) >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK;
        return self::$finesine[$index + intdiv(Defs::FINEANGLES, 4)];
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
