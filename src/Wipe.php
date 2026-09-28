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

final class Wipe
{
    /** @var array<int,int> */
    private array $start = [];
    /** @var array<int,int> */
    private array $end = [];
    /** @var array<int,int> */
    private array $y = [];
    public bool $active = false;

    /** @param array<int,int> $fb */
    public function captureStart(array $fb): void
    {
        $this->start = $fb;
    }

    /** @param array<int,int> $fb */
    public function captureEnd(array $fb): void
    {
        $this->end = $fb;
    }

    /** @param array<int,int> $fb */
    public function begin(array &$fb): void
    {
        $fb = $this->start;
        $columns = intdiv(Defs::SCREENWIDTH, 2);
        $this->y = array_fill(0, $columns, 0);
        $this->y[0] = -random_int(0, 15);
        for ($i = 1; $i < $columns; ++$i) {
            $next = $this->y[$i - 1] + random_int(-1, 1);
            $this->y[$i] = $next > 0 ? 0 : ($next === -16 ? -15 : $next);
        }
        $this->active = true;
    }

    /** @param array<int,int> $fb */
    public function tick(int $tics, array &$fb): bool
    {
        $height = Defs::SCREENHEIGHT;
        $width = Defs::SCREENWIDTH;
        $done = true;
        for ($tic = 0; $tic < max(1, $tics); ++$tic) {
            foreach ($this->y as $column => $y) {
                $x = $column * 2;
                if ($y < 0) {
                    for ($row = 0; $row < $height; ++$row) {
                        $off = $row * $width + $x;
                        $fb[$off] = $this->start[$off];
                        $fb[$off + 1] = $this->start[$off + 1];
                    }
                    $this->y[$column] = $y + 1;
                    $done = false;
                    continue;
                }
                if ($y >= $height) {
                    continue;
                }
                $dy = $y < 16 ? $y + 1 : 8;
                $dy = min($dy, $height - $y);
                for ($row = 0; $row < $dy; ++$row) {
                    $off = ($y + $row) * $width + $x;
                    $fb[$off] = $this->end[$off];
                    $fb[$off + 1] = $this->end[$off + 1];
                }
                $y += $dy;
                $this->y[$column] = $y;
                for ($row = $y; $row < $height; ++$row) {
                    $src = ($row - $y) * $width + $x;
                    $dst = $row * $width + $x;
                    $fb[$dst] = $this->start[$src];
                    $fb[$dst + 1] = $this->start[$src + 1];
                }
                $done = false;
            }
        }
        if ($done) {
            $fb = $this->end;
            $this->active = false;
        }
        return $done;
    }
}
