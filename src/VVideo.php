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

/** V_DrawPatch and helpers (v_video.prg) on an integer-array framebuffer. */
final class VVideo
{
    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public static function patchSize(string $patch): array
    {
        return [
            Bin::i16($patch, 0),
            Bin::i16($patch, 2),
            Bin::i16($patch, 4),
            Bin::i16($patch, 6),
        ];
    }

    /**
     * @param array<int, int> $fb
     */
    public static function drawPatch(
        array &$fb,
        int $x,
        int $y,
        string $patch,
        bool $flipped = false,
    ): void {
        [$width, , $left, $top] = self::patchSize($patch);
        $x -= $left;
        $y -= $top;
        $destTop = $y * Defs::SCREENWIDTH + $x;
        $patchLength = strlen($patch);
        $screenSize = Defs::SCREENWIDTH * Defs::SCREENHEIGHT;

        for ($col = 0; $col < $width; ++$col) {
            $srcCol = $flipped ? $width - 1 - $col : $col;
            $column = Bin::u32($patch, 8 + $srcCol * 4);
            while ($column < $patchLength) {
                $topDelta = ord($patch[$column]);
                if ($topDelta === 0xFF) {
                    break;
                }
                $length = ord($patch[$column + 1]);
                $source = $column + 3;
                $dest = $destTop + $topDelta * Defs::SCREENWIDTH;
                for ($i = 0; $i < $length; ++$i) {
                    if ($dest >= 0 && $dest < $screenSize) {
                        $fb[$dest] = ord($patch[$source]);
                    }
                    ++$source;
                    $dest += Defs::SCREENWIDTH;
                }
                $column += $length + 4;
            }
            ++$destTop;
        }
    }

    /**
     * @param array<int, int> $fb
     */
    public static function drawPatchDirect(array &$fb, int $x, int $y, string $patch): void
    {
        self::drawPatch($fb, $x, $y, $patch);
    }

    /**
     * @param array<int, int> $dest
     * @param array<int, int>|string $src
     */
    public static function copyRect(
        array &$dest,
        array|string $src,
        int $srcx,
        int $srcy,
        int $width,
        int $height,
        int $destx,
        int $desty,
    ): void {
        for ($row = 0; $row < $height; ++$row) {
            $source = ($srcy + $row) * Defs::SCREENWIDTH + $srcx;
            $target = ($desty + $row) * Defs::SCREENWIDTH + $destx;
            for ($col = 0; $col < $width; ++$col) {
                $value = is_string($src) ? ord($src[$source + $col]) : $src[$source + $col];
                $dest[$target + $col] = $value;
            }
        }
    }

    /**
     * @param array<int, int> $fb
     */
    public static function fill(array &$fb, int $color = 0): void
    {
        $fb = array_fill(0, count($fb), $color & 0xFF);
    }
}
