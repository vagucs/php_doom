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

final class AmMap
{
    private const REDS = 176;
    private const RED_RANGE = 16;
    private const GREENS = 112;
    private const GRAYS = 96;
    private const BROWNS = 64;
    private const YELLOWS = 231;
    private const WHITE = 209;
    private const GRID_COLOR = 104;
    private const INIT_SCALE = 13107;
    private const PAN_INC = 4;
    private const ZOOM_IN = 66846;
    private const ZOOM_OUT = 64250;
    private const INT_MAX = 0x7FFFFFFF;

    public bool $active = false;
    public int $cheating = 0;
    public int $grid = 0;
    public int $followPlayer = 1;
    private bool $stopped = true;
    private int $lastLevel = -1;
    private int $lastEpisode = -1;
    private int $bigState = 0;
    private int $clock = 0;
    private int $fW = Defs::SCREENWIDTH;
    private int $fH = Defs::SCREENHEIGHT - Defs::SBARHEIGHT;
    private int $mX = 0;
    private int $mY = 0;
    private int $mX2 = 0;
    private int $mY2 = 0;
    private int $mW = 0;
    private int $mH = 0;
    private int $minX = 0;
    private int $minY = 0;
    private int $maxX = 0;
    private int $maxY = 0;
    private int $minScale = Defs::FRACUNIT;
    private int $maxScale = Defs::FRACUNIT;
    private int $scaleMtof = self::INIT_SCALE;
    private int $scaleFtom = Defs::FRACUNIT;
    private int $oldMX = 0;
    private int $oldMY = 0;
    private int $oldMW = 0;
    private int $oldMH = 0;
    private int $oldFollowX = self::INT_MAX;
    private int $oldFollowY = 0;
    private int $panX = 0;
    private int $panY = 0;
    private int $zoomMtof = Defs::FRACUNIT;
    private int $zoomFtom = Defs::FRACUNIT;
    /** @var list<array{int,int,int,int}> */
    private array $playerArrow;
    /** @var list<array{int,int,int,int}> */
    private array $thingTriangle;
    /** @var list<?string> */
    private array $markNumbers = [];
    /** @var list<array{int,int}> */
    private array $marks = [];
    private int $markNumber = 0;

    public function __construct()
    {
        $radius = intdiv(8 * Defs::PLAYER_RADIUS, 7);
        $q = intdiv($radius, 4);
        $e = intdiv($radius, 8);
        $this->playerArrow = [
            [-$radius + $e, 0, $radius, 0],
            [$radius, 0, $radius - intdiv($radius, 2), $q],
            [$radius, 0, $radius - intdiv($radius, 2), -$q],
            [-$radius + $e, 0, -$radius - $e, $q],
            [-$radius + $e, 0, -$radius - $e, -$q],
            [-$radius + 3 * $e, 0, -$radius + $e, $q],
            [-$radius + 3 * $e, 0, -$radius + $e, -$q],
        ];
        $this->thingTriangle = [
            [-intdiv(Defs::FRACUNIT, 2), -intdiv(7 * Defs::FRACUNIT, 10), Defs::FRACUNIT, 0],
            [Defs::FRACUNIT, 0, -intdiv(Defs::FRACUNIT, 2), intdiv(7 * Defs::FRACUNIT, 10)],
            [-intdiv(Defs::FRACUNIT, 2), intdiv(7 * Defs::FRACUNIT, 10), -intdiv(Defs::FRACUNIT, 2), -intdiv(7 * Defs::FRACUNIT, 10)],
        ];
        $this->clearMarks();
    }

    public function start(Game $game): void
    {
        if (!$this->stopped) {
            $this->stop();
        }
        $this->stopped = false;
        if ($this->lastLevel !== $game->mapn || $this->lastEpisode !== $game->episode) {
            $this->levelInit($game);
            $this->lastLevel = $game->mapn;
            $this->lastEpisode = $game->episode;
        }
        $this->initVariables($game);
        for ($i = 0; $i < 10; ++$i) {
            $n = $game->wad->checkNumForName("AMMNUM{$i}");
            $this->markNumbers[$i] = $n >= 0 ? $game->wad->cacheLumpNum($n) : null;
        }
        $this->active = true;
    }

    public function stop(): void
    {
        $this->active = false;
        $this->stopped = true;
        $this->panX = $this->panY = 0;
        $this->zoomMtof = $this->zoomFtom = Defs::FRACUNIT;
        $this->bigState = 0;
    }

    public function resetLevel(): void
    {
        $this->stop();
        $this->lastLevel = $this->lastEpisode = -1;
        $this->cheating = 0;
    }

    public function ticker(Game $game): void
    {
        if (!$this->active) {
            return;
        }
        ++$this->clock;
        if ($this->followPlayer) {
            $this->follow($game);
        }
        if ($this->zoomFtom !== Defs::FRACUNIT) {
            $this->changeScale();
        }
        if ($this->panX !== 0 || $this->panY !== 0) {
            $this->changeLocation();
        }
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb, Game $game): void
    {
        if (!$this->active) {
            return;
        }
        $limit = $this->fW * $this->fH;
        for ($i = 0; $i < $limit; ++$i) {
            $fb[$i] = 0;
        }
        if ($this->grid) {
            $this->drawGrid($fb, $game);
        }
        $this->drawWalls($fb, $game);
        $mo = $game->player->mo;
        $this->drawCharacter($fb, $this->playerArrow, 0, $mo->angle, self::WHITE, $mo->x, $mo->y);
        if ($this->cheating === 2) {
            foreach ($game->world->mobjs as $thing) {
                $this->drawCharacter(
                    $fb, $this->thingTriangle, 16 * Defs::FRACUNIT, $thing->angle,
                    self::GREENS, $thing->x, $thing->y
                );
            }
        }
        $this->put($fb, intdiv($this->fW, 2), intdiv($this->fH, 2), self::GRAYS);
        foreach ($this->marks as $i => [$mx, $my]) {
            if ($mx === -1 || ($this->markNumbers[$i] ?? null) === null) {
                continue;
            }
            $x = $this->cx($mx);
            $y = $this->cy($my);
            if ($x >= 0 && $x <= $this->fW - 5 && $y >= 0 && $y <= $this->fH - 6) {
                VVideo::drawPatch($fb, $x, $y, $this->markNumbers[$i]);
            }
        }
    }

    public function responder(string $type, int $key, Game $game): bool
    {
        if ($game->gamestate !== Defs::GS_LEVEL || $game->player === null || $game->world === null) {
            return false;
        }
        if ($type === 'keydown') {
            if (!$this->active) {
                if ($key === Keys::TAB) {
                    $this->start($game);
                    return true;
                }
                return false;
            }
            if (in_array($key, [Keys::LEFT, Keys::RIGHT], true) && !$this->followPlayer) {
                $this->panX = ($key === Keys::RIGHT ? 1 : -1) * $this->ftom(self::PAN_INC);
                return true;
            }
            if (in_array($key, [Keys::UP, Keys::DOWN], true) && !$this->followPlayer) {
                $this->panY = ($key === Keys::UP ? 1 : -1) * $this->ftom(self::PAN_INC);
                return true;
            }
            if (Keys::isMinus($key)) {
                $this->zoomMtof = self::ZOOM_OUT;
                $this->zoomFtom = self::ZOOM_IN;
                return true;
            }
            if (Keys::isPlus($key)) {
                $this->zoomMtof = self::ZOOM_IN;
                $this->zoomFtom = self::ZOOM_OUT;
                return true;
            }
            if ($key === Keys::TAB) {
                $this->stop();
                return true;
            }
            if ($key === ord('0')) {
                $this->bigState ^= 1;
                if ($this->bigState) {
                    $this->saveScale();
                    $this->minOut();
                } else {
                    $this->restoreScale($game);
                }
                return true;
            }
            if ($key === ord('f')) {
                $this->followPlayer ^= 1;
                $this->oldFollowX = self::INT_MAX;
                $game->player->setMessage($this->followPlayer ? 'Follow Mode ON' : 'Follow Mode OFF');
                return true;
            }
            if ($key === ord('g')) {
                $this->grid ^= 1;
                $game->player->setMessage($this->grid ? 'Grid ON' : 'Grid OFF');
                return true;
            }
            if ($key === ord('m')) {
                $game->player->setMessage("Marked Spot {$this->markNumber}");
                $this->marks[$this->markNumber] = [$this->mX + intdiv($this->mW, 2), $this->mY + intdiv($this->mH, 2)];
                $this->markNumber = ($this->markNumber + 1) % 10;
                return true;
            }
            if ($key === ord('c')) {
                $this->clearMarks();
                $game->player->setMessage('All Marks Cleared');
                return true;
            }
        } elseif ($type === 'keyup' && $this->active) {
            if (in_array($key, [Keys::LEFT, Keys::RIGHT], true) && !$this->followPlayer) {
                $this->panX = 0;
            } elseif (in_array($key, [Keys::UP, Keys::DOWN], true) && !$this->followPlayer) {
                $this->panY = 0;
            } elseif (Keys::isMinus($key) || Keys::isPlus($key)) {
                $this->zoomMtof = $this->zoomFtom = Defs::FRACUNIT;
            }
        }
        return false;
    }

    public function cycleIddt(): void
    {
        $this->cheating = ($this->cheating + 1) % 3;
    }

    private function levelInit(Game $game): void
    {
        $this->clearMarks();
        $this->minX = $this->minY = self::INT_MAX;
        $this->maxX = $this->maxY = -self::INT_MAX;
        foreach ($game->world->vertexes as $vertex) {
            $this->minX = min($this->minX, $vertex->x);
            $this->maxX = max($this->maxX, $vertex->x);
            $this->minY = min($this->minY, $vertex->y);
            $this->maxY = max($this->maxY, $vertex->y);
        }
        $width = max(Defs::FRACUNIT, $this->maxX - $this->minX);
        $height = max(Defs::FRACUNIT, $this->maxY - $this->minY);
        $this->minScale = min(
            Compat::fixedDiv($this->fW * Defs::FRACUNIT, $width),
            Compat::fixedDiv($this->fH * Defs::FRACUNIT, $height)
        );
        $this->maxScale = Compat::fixedDiv($this->fH * Defs::FRACUNIT, 2 * Defs::PLAYER_RADIUS);
        $this->scaleMtof = Compat::fixedDiv($this->minScale, (int) (0.7 * Defs::FRACUNIT));
        if ($this->scaleMtof > $this->maxScale) {
            $this->scaleMtof = $this->minScale;
        }
        $this->scaleFtom = Compat::fixedDiv(Defs::FRACUNIT, $this->scaleMtof);
    }

    private function initVariables(Game $game): void
    {
        $this->oldFollowX = self::INT_MAX;
        $this->clock = $this->panX = $this->panY = 0;
        $this->zoomMtof = $this->zoomFtom = Defs::FRACUNIT;
        $this->mW = $this->ftom($this->fW);
        $this->mH = $this->ftom($this->fH);
        $mo = $game->player->mo;
        $this->mX = $mo->x - intdiv($this->mW, 2);
        $this->mY = $mo->y - intdiv($this->mH, 2);
        $this->changeLocation();
        $this->saveScale();
    }

    private function clearMarks(): void
    {
        $this->marks = array_fill(0, 10, [-1, -1]);
        $this->markNumbers = array_fill(0, 10, null);
        $this->markNumber = 0;
    }

    private function ftom(int $pixels): int
    {
        return Compat::fixedMul($pixels * Defs::FRACUNIT, $this->scaleFtom);
    }

    private function mtof(int $map): int
    {
        return Compat::shar(Compat::fixedMul($map, $this->scaleMtof), 16);
    }

    private function cx(int $x): int { return $this->mtof($x - $this->mX); }
    private function cy(int $y): int { return $this->fH - $this->mtof($y - $this->mY); }

    private function saveScale(): void
    {
        [$this->oldMX, $this->oldMY, $this->oldMW, $this->oldMH] = [$this->mX, $this->mY, $this->mW, $this->mH];
    }

    private function activateScale(): void
    {
        $cx = $this->mX + intdiv($this->mW, 2);
        $cy = $this->mY + intdiv($this->mH, 2);
        $this->mW = $this->ftom($this->fW);
        $this->mH = $this->ftom($this->fH);
        $this->mX = $cx - intdiv($this->mW, 2);
        $this->mY = $cy - intdiv($this->mH, 2);
        $this->mX2 = $this->mX + $this->mW;
        $this->mY2 = $this->mY + $this->mH;
    }

    private function minOut(): void
    {
        $this->scaleMtof = $this->minScale;
        $this->scaleFtom = Compat::fixedDiv(Defs::FRACUNIT, $this->scaleMtof);
        $this->activateScale();
    }

    private function restoreScale(Game $game): void
    {
        [$this->mW, $this->mH] = [$this->oldMW, $this->oldMH];
        if ($this->followPlayer) {
            $this->mX = $game->player->mo->x - intdiv($this->mW, 2);
            $this->mY = $game->player->mo->y - intdiv($this->mH, 2);
        } else {
            [$this->mX, $this->mY] = [$this->oldMX, $this->oldMY];
        }
        $this->mX2 = $this->mX + $this->mW;
        $this->mY2 = $this->mY + $this->mH;
        $this->scaleMtof = Compat::fixedDiv($this->fW * Defs::FRACUNIT, $this->mW);
        $this->scaleFtom = Compat::fixedDiv(Defs::FRACUNIT, $this->scaleMtof);
    }

    private function changeScale(): void
    {
        $this->scaleMtof = Compat::fixedMul($this->scaleMtof, $this->zoomMtof);
        $this->scaleFtom = Compat::fixedDiv(Defs::FRACUNIT, $this->scaleMtof);
        if ($this->scaleMtof < $this->minScale) {
            $this->minOut();
        } elseif ($this->scaleMtof > $this->maxScale) {
            $this->scaleMtof = $this->maxScale;
            $this->scaleFtom = Compat::fixedDiv(Defs::FRACUNIT, $this->scaleMtof);
            $this->activateScale();
        } else {
            $this->activateScale();
        }
    }

    private function changeLocation(): void
    {
        if ($this->panX !== 0 || $this->panY !== 0) {
            $this->followPlayer = 0;
            $this->oldFollowX = self::INT_MAX;
        }
        $this->mX += $this->panX;
        $this->mY += $this->panY;
        $this->mX = min($this->maxX - intdiv($this->mW, 2), max($this->minX - intdiv($this->mW, 2), $this->mX));
        $this->mY = min($this->maxY - intdiv($this->mH, 2), max($this->minY - intdiv($this->mH, 2), $this->mY));
        $this->mX2 = $this->mX + $this->mW;
        $this->mY2 = $this->mY + $this->mH;
    }

    private function follow(Game $game): void
    {
        $mo = $game->player->mo;
        if ($this->oldFollowX !== $mo->x || $this->oldFollowY !== $mo->y) {
            $this->mX = $this->ftom($this->mtof($mo->x)) - intdiv($this->mW, 2);
            $this->mY = $this->ftom($this->mtof($mo->y)) - intdiv($this->mH, 2);
            $this->mX2 = $this->mX + $this->mW;
            $this->mY2 = $this->mY + $this->mH;
            [$this->oldFollowX, $this->oldFollowY] = [$mo->x, $mo->y];
        }
    }

    /** @param array<int,int> $fb */
    private function drawGrid(array &$fb, Game $game): void
    {
        $block = 128 * Defs::FRACUNIT;
        $start = $this->mX + (($block - (($this->mX - $game->world->bmaporgx) % $block)) % $block);
        for ($x = $start; $x < $this->mX + $this->mW; $x += $block) {
            $this->line($fb, $x, $this->mY, $x, $this->mY + $this->mH, self::GRID_COLOR);
        }
        $start = $this->mY + (($block - (($this->mY - $game->world->bmaporgy) % $block)) % $block);
        for ($y = $start; $y < $this->mY + $this->mH; $y += $block) {
            $this->line($fb, $this->mX, $y, $this->mX + $this->mW, $y, self::GRID_COLOR);
        }
    }

    /** @param array<int,int> $fb */
    private function drawWalls(array &$fb, Game $game): void
    {
        foreach ($game->world->lines as $line) {
            if ($this->cheating || ($line->flags & Defs::ML_MAPPED) !== 0) {
                if (($line->flags & Defs::ML_DONTDRAW) !== 0 && !$this->cheating) {
                    continue;
                }
                $color = null;
                if ($line->backsector === null) {
                    $color = self::REDS;
                } elseif ($line->frontsector !== null) {
                    if ($line->special === 39) {
                        $color = self::REDS + intdiv(self::RED_RANGE, 2);
                    } elseif (($line->flags & Defs::ML_SECRET) !== 0) {
                        $color = self::REDS;
                    } elseif ($line->backsector->floorheight !== $line->frontsector->floorheight) {
                        $color = self::BROWNS;
                    } elseif ($line->backsector->ceilingheight !== $line->frontsector->ceilingheight) {
                        $color = self::YELLOWS;
                    } elseif ($this->cheating) {
                        $color = self::GRAYS;
                    }
                }
                if ($color !== null) {
                    $this->line($fb, $line->v1->x, $line->v1->y, $line->v2->x, $line->v2->y, $color);
                }
            } elseif (($game->player->powers[Defs::PW_ALLMAP] ?? 0) && ($line->flags & Defs::ML_DONTDRAW) === 0) {
                $this->line($fb, $line->v1->x, $line->v1->y, $line->v2->x, $line->v2->y, self::GRAYS + 3);
            }
        }
    }

    /** @param array<int,int> $fb @param list<array{int,int,int,int}> $shape */
    private function drawCharacter(array &$fb, array $shape, int $scale, int $angle, int $color, int $x, int $y): void
    {
        foreach ($shape as [$ax, $ay, $bx, $by]) {
            if ($scale !== 0) {
                $ax = Compat::fixedMul($scale, $ax);
                $ay = Compat::fixedMul($scale, $ay);
                $bx = Compat::fixedMul($scale, $bx);
                $by = Compat::fixedMul($scale, $by);
            }
            if ($angle !== 0) {
                [$ax, $ay] = $this->rotate($ax, $ay, $angle);
                [$bx, $by] = $this->rotate($bx, $by, $angle);
            }
            $this->line($fb, $ax + $x, $ay + $y, $bx + $x, $by + $y, $color);
        }
    }

    /** @return array{int,int} */
    private function rotate(int $x, int $y, int $angle): array
    {
        $cos = Tables::fineCos($angle);
        $sin = Tables::fineSin($angle);
        return [
            Compat::fixedMul($x, $cos) - Compat::fixedMul($y, $sin),
            Compat::fixedMul($x, $sin) + Compat::fixedMul($y, $cos),
        ];
    }

    /** @param array<int,int> $fb */
    private function line(array &$fb, int $ax, int $ay, int $bx, int $by, int $color): void
    {
        $x0 = $this->cx($ax);
        $y0 = $this->cy($ay);
        $x1 = $this->cx($bx);
        $y1 = $this->cy($by);
        if (!$this->clip($x0, $y0, $x1, $y1)) {
            return;
        }
        $dx = abs($x1 - $x0);
        $sx = $x0 < $x1 ? 1 : -1;
        $dy = -abs($y1 - $y0);
        $sy = $y0 < $y1 ? 1 : -1;
        $error = $dx + $dy;
        while (true) {
            $this->put($fb, $x0, $y0, $color);
            if ($x0 === $x1 && $y0 === $y1) {
                break;
            }
            $twice = 2 * $error;
            if ($twice >= $dy) {
                $error += $dy;
                $x0 += $sx;
            }
            if ($twice <= $dx) {
                $error += $dx;
                $y0 += $sy;
            }
        }
    }

    private function clip(int &$x0, int &$y0, int &$x1, int &$y1): bool
    {
        $code = static function (int $x, int $y): int {
            return ($x < 0 ? 1 : ($x >= Defs::SCREENWIDTH ? 2 : 0))
                | ($y < 0 ? 8 : ($y >= Defs::SCREENHEIGHT - Defs::SBARHEIGHT ? 4 : 0));
        };
        for ($i = 0; $i < 8; ++$i) {
            $a = $code($x0, $y0);
            $b = $code($x1, $y1);
            if (($a | $b) === 0) {
                return true;
            }
            if (($a & $b) !== 0) {
                return false;
            }
            $out = $a ?: $b;
            if ($out & 8) {
                $x = $x0 + intdiv(($x1 - $x0) * -$y0, $y1 - $y0 ?: 1);
                $y = 0;
            } elseif ($out & 4) {
                $y = $this->fH - 1;
                $x = $x0 + intdiv(($x1 - $x0) * ($y - $y0), $y1 - $y0 ?: 1);
            } elseif ($out & 2) {
                $x = $this->fW - 1;
                $y = $y0 + intdiv(($y1 - $y0) * ($x - $x0), $x1 - $x0 ?: 1);
            } else {
                $x = 0;
                $y = $y0 + intdiv(($y1 - $y0) * -$x0, $x1 - $x0 ?: 1);
            }
            if ($out === $a) {
                $x0 = $x;
                $y0 = $y;
            } else {
                $x1 = $x;
                $y1 = $y;
            }
        }
        return false;
    }

    /** @param array<int,int> $fb */
    private function put(array &$fb, int $x, int $y, int $color): void
    {
        if ($x >= 0 && $x < $this->fW && $y >= 0 && $y < $this->fH) {
            $fb[$y * $this->fW + $x] = $color & 0xFF;
        }
    }
}

class_alias(AmMap::class, __NAMESPACE__ . '\\Automap');
