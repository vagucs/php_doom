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

final class MoveCheck
{
    public int $floorz = 0;
    public int $ceilingz = 0;
    public int $dropoffz = 0;
    /** @var Line[] */
    public array $spechit = [];
    public bool $blocked = false;
    public ?object $hitThing = null;
}

final class Collision
{
    public static function pointOnSide(int $x, int $y, Node $node): int
    {
        $dx = Compat::asI32($x - $node->x);
        $dy = Compat::asI32($y - $node->y);
        $left = Compat::asI32($node->dy >> 16) * $dx;
        $right = $dy * Compat::asI32($node->dx >> 16);
        return $right >= $left ? 1 : 0;
    }

    public static function pointInSubsector(World $world, int $x, int $y): Subsector
    {
        $num = $world->numnodes - 1;
        if ($num < 0) {
            return $world->subsectors[0];
        }
        while (($num & Defs::NF_SUBSECTOR) === 0) {
            $node = $world->nodes[$num];
            $num = $node->children[self::pointOnSide($x, $y, $node)];
        }
        return $world->subsectors[$num & ~Defs::NF_SUBSECTOR];
    }

    public static function pointOnLineSide(int $x, int $y, Line $line): int
    {
        if ($line->dx === 0) {
            if ($x <= $line->v1->x) {
                return $line->dy > 0 ? 1 : 0;
            }
            return $line->dy < 0 ? 1 : 0;
        }
        if ($line->dy === 0) {
            if ($y <= $line->v1->y) {
                return $line->dx < 0 ? 1 : 0;
            }
            return $line->dx > 0 ? 1 : 0;
        }
        $dx = Compat::asI32($x - $line->v1->x);
        $dy = Compat::asI32($y - $line->v1->y);
        $left = Compat::fixedMul($line->dy >> Defs::FRACBITS, $dx);
        $right = Compat::fixedMul($dy, $line->dx >> Defs::FRACBITS);
        return $right < $left ? 0 : 1;
    }

    /** @param int[] $box */
    public static function boxOnLineSide(array $box, Line $line): int
    {
        if ($line->dx === 0) {
            $p1 = $box[Defs::BOXRIGHT] < $line->v1->x ? 0 : 1;
            $p2 = $box[Defs::BOXLEFT] < $line->v1->x ? 0 : 1;
            if ($line->dy > 0) {
                $p1 ^= 1;
                $p2 ^= 1;
            }
        } elseif ($line->dy === 0) {
            $p1 = $box[Defs::BOXTOP] > $line->v1->y ? 0 : 1;
            $p2 = $box[Defs::BOXBOTTOM] > $line->v1->y ? 0 : 1;
            if ($line->dx < 0) {
                $p1 ^= 1;
                $p2 ^= 1;
            }
        } elseif (($line->dy > 0) === ($line->dx > 0)) {
            $p1 = self::pointOnLineSide($box[Defs::BOXLEFT], $box[Defs::BOXTOP], $line);
            $p2 = self::pointOnLineSide($box[Defs::BOXRIGHT], $box[Defs::BOXBOTTOM], $line);
        } else {
            $p1 = self::pointOnLineSide($box[Defs::BOXRIGHT], $box[Defs::BOXTOP], $line);
            $p2 = self::pointOnLineSide($box[Defs::BOXLEFT], $box[Defs::BOXBOTTOM], $line);
        }
        return $p1 === $p2 ? $p1 : -1;
    }

    /** @return array{int,int,int} */
    public static function lineOpening(Line $line): array
    {
        if ($line->backsector === null) {
            return [0, 0, 0];
        }
        $front = $line->frontsector;
        $back = $line->backsector;
        $top = min($front->ceilingheight, $back->ceilingheight);
        return $front->floorheight > $back->floorheight
            ? [$top, $front->floorheight, $back->floorheight]
            : [$top, $back->floorheight, $front->floorheight];
    }

    private static function thingBlocks(Mobj $tm, Mobj $other): bool
    {
        if ($other === $tm || (($tm->flags & Defs::MF_MISSILE) && $other === $tm->target)) {
            return false;
        }
        if (($other->flags & (Defs::MF_SOLID | Defs::MF_SPECIAL | Defs::MF_SHOOTABLE)) === 0) {
            return false;
        }
        $dist = $other->radius + $tm->radius;
        if (abs($other->x - $tm->tmx) >= $dist || abs($other->y - $tm->tmy) >= $dist) {
            return false;
        }
        if ($tm->z >= $other->z + $other->height || $tm->z + $tm->height <= $other->z) {
            return false;
        }
        if ($other->flags & Defs::MF_SPECIAL) {
            if ($tm->flags & Defs::MF_PICKUP) {
                $tm->pickup = $other;
            }
            return (bool) ($other->flags & Defs::MF_SOLID);
        }
        return (bool) ($other->flags & Defs::MF_SOLID);
    }

    public static function checkPosition(World $world, Mobj $thing, int $x, int $y): MoveCheck
    {
        $chk = new MoveCheck();
        $thing->tmx = $x;
        $thing->tmy = $y;
        $thing->pickup = null;
        $r = $thing->radius;
        $box = [
            Defs::BOXLEFT => $x - $r,
            Defs::BOXRIGHT => $x + $r,
            Defs::BOXBOTTOM => $y - $r,
            Defs::BOXTOP => $y + $r,
        ];
        $sec = self::pointInSubsector($world, $x, $y)->sector;
        $chk->floorz = $chk->dropoffz = $sec->floorheight;
        $chk->ceilingz = $sec->ceilingheight;
        if ($thing->flags & Defs::MF_NOCLIP) {
            return $chk;
        }
        foreach ($world->mobjs as $other) {
            if ($other === $thing || !$other->alive) {
                continue;
            }
            if (self::thingBlocks($thing, $other)) {
                $chk->blocked = true;
                $chk->hitThing = $other;
                return $chk;
            }
            if ($thing->pickup !== null) {
                break;
            }
        }
        foreach ($world->lines as $ln) {
            if ($box[Defs::BOXRIGHT] <= $ln->bbox[Defs::BOXLEFT]
                || $box[Defs::BOXLEFT] >= $ln->bbox[Defs::BOXRIGHT]
                || $box[Defs::BOXTOP] <= $ln->bbox[Defs::BOXBOTTOM]
                || $box[Defs::BOXBOTTOM] >= $ln->bbox[Defs::BOXTOP]
            ) {
                continue;
            }
            if (self::boxOnLineSide($box, $ln) !== -1) {
                continue;
            }
            if ($ln->backsector === null) {
                $chk->blocked = true;
                return $chk;
            }
            if (($thing->flags & Defs::MF_MISSILE) === 0) {
                if ($ln->flags & Defs::ML_BLOCKING) {
                    $chk->blocked = true;
                    return $chk;
                }
                if ($thing->player === null && ($ln->flags & Defs::ML_BLOCKMONSTERS)) {
                    $chk->blocked = true;
                    return $chk;
                }
            }
            [$top, $bottom, $low] = self::lineOpening($ln);
            $chk->ceilingz = min($chk->ceilingz, $top);
            $chk->floorz = max($chk->floorz, $bottom);
            $chk->dropoffz = min($chk->dropoffz, $low);
            if ($ln->special) {
                $chk->spechit[] = $ln;
            }
        }
        return $chk;
    }

    public static function tryMove(World $world, Mobj $thing, int $x, int $y, ?object $game = null): bool
    {
        $chk = self::checkPosition($world, $thing, $x, $y);
        if ($chk->blocked) {
            return false;
        }
        if (($thing->flags & Defs::MF_NOCLIP) === 0) {
            if ($chk->ceilingz - $chk->floorz < $thing->height || $chk->ceilingz - $thing->z < $thing->height || $chk->floorz - $thing->z > Defs::MAXSTEP) {
                return false;
            }
            if (($thing->flags & (Defs::MF_DROPOFF | Defs::MF_FLOAT)) === 0 && $chk->floorz - $chk->dropoffz > Defs::MAXSTEP) {
                return false;
            }
        }
        $oldx = $thing->x;
        $oldy = $thing->y;
        $thing->floorz = $chk->floorz;
        $thing->ceilingz = $chk->ceilingz;
        $thing->x = $x;
        $thing->y = $y;
        if ($thing->pickup !== null && $game !== null) {
            $game->touchSpecial($thing->pickup, $thing);
        }
        if ($game !== null && (($thing->flags & Defs::MF_NOCLIP) === 0)) {
            foreach ($chk->spechit as $ln) {
                $side = self::pointOnLineSide($thing->x, $thing->y, $ln);
                if ($side !== self::pointOnLineSide($oldx, $oldy, $ln) && $ln->special) {
                    $game->crossSpecial($ln, self::pointOnLineSide($oldx, $oldy, $ln), $thing);
                }
            }
        }
        return true;
    }

    public static function slideMove(World $world, Mobj $thing, int $momx, int $momy, ?object $game = null): void
    {
        $momx = max(-Defs::MAXMOVE, min(Defs::MAXMOVE, $momx));
        $momy = max(-Defs::MAXMOVE, min(Defs::MAXMOVE, $momy));
        if (self::tryMove($world, $thing, $thing->x + $momx, $thing->y + $momy, $game)) {
            return;
        }
        self::tryMove($world, $thing, $thing->x + $momx, $thing->y, $game);
        self::tryMove($world, $thing, $thing->x, $thing->y + $momy, $game);
    }

    public static function interceptFrac(int $x1, int $y1, int $x2, int $y2, Line $line): ?int
    {
        $u = Defs::FRACUNIT;
        $ax = $x1 / $u;
        $ay = $y1 / $u;
        $bx = $x2 / $u;
        $by = $y2 / $u;
        $cx = $line->v1->x / $u;
        $cy = $line->v1->y / $u;
        $dx = $line->v2->x / $u;
        $dy = $line->v2->y / $u;
        $den = ($bx - $ax) * ($dy - $cy) - ($by - $ay) * ($dx - $cx);
        if (abs($den) < 1e-8) {
            return null;
        }
        $t = (($cx - $ax) * ($dy - $cy) - ($cy - $ay) * ($dx - $cx)) / $den;
        $v = (($cx - $ax) * ($by - $ay) - ($cy - $ay) * ($bx - $ax)) / $den;
        return ($t < 0 || $t > 1 || $v < 0 || $v > 1) ? null : (int) ($t * $u);
    }

    public static function useLines(World $world, Player $player, object $game): void
    {
        $mo = $player->mo;
        $x1 = $mo->x;
        $y1 = $mo->y;
        $x2 = $x1 + Compat::shar(Defs::USERANGE, Defs::FRACBITS) * Tables::fineCos($mo->angle);
        $y2 = $y1 + Compat::shar(Defs::USERANGE, Defs::FRACBITS) * Tables::fineSin($mo->angle);
        $hits = [];
        foreach ($world->lines as $ln) {
            $f = self::interceptFrac($x1, $y1, $x2, $y2, $ln);
            if ($f !== null && $f >= 0 && $f <= Defs::FRACUNIT) {
                $hits[] = [$f, $ln];
            }
        }
        usort($hits, fn($a, $b) => $a[0] <=> $b[0]);
        foreach ($hits as [, $ln]) {
            if (!$ln->special) {
                $blocked = $ln->backsector === null;
                if (!$blocked) {
                    [$top, $bottom] = self::lineOpening($ln);
                    $blocked = $top - $bottom <= 0;
                }
                if ($blocked) {
                    $game->startSound('noway');
                    return;
                }
                continue;
            }
            $game->useSpecial($ln, $mo, self::pointOnLineSide($mo->x, $mo->y, $ln));
            return;
        }
    }

    public static function lineAttack(World $world, Mobj $source, int $damage, ?object $game, int $range): bool
    {
        $x1 = $source->x;
        $y1 = $source->y;
        $x2 = $x1 + Compat::shar($range, Defs::FRACBITS) * Tables::fineCos($source->angle);
        $y2 = $y1 + Compat::shar($range, Defs::FRACBITS) * Tables::fineSin($source->angle);
        $hits = [];
        foreach ($world->lines as $ln) {
            $f = self::interceptFrac($x1, $y1, $x2, $y2, $ln);
            if ($f === null || $f <= 0 || $f > Defs::FRACUNIT) {
                continue;
            }
            $solid = $ln->backsector === null;
            if (!$solid) {
                [$top, $bottom] = self::lineOpening($ln);
                $solid = $top - $bottom <= 32 * Defs::FRACUNIT || (bool) ($ln->flags & Defs::ML_BLOCKING);
            }
            if ($solid) {
                $hits[] = [$f, 'line', $ln];
            }
        }
        foreach ($world->mobjs as $other) {
            if ($other === $source || (($other->flags & Defs::MF_SHOOTABLE) === 0)) {
                continue;
            }
            $f = self::thingHitFrac($x1, $y1, $x2, $y2, $other);
            if ($f !== null) {
                $hits[] = [$f, 'thing', $other];
            }
        }
        usort($hits, fn($a, $b) => $a[0] <=> $b[0]);
        foreach ($hits as [, $kind, $obj]) {
            if ($kind === 'thing') {
                if ($game) {
                    $game->damageMobj($obj, $source, $damage);
                }
                return true;
            }
            if ($obj->special === 46 && $game) {
                $game->useSpecial($obj, $source, 0);
            }
            return false;
        }
        return false;
    }

    private static function thingHitFrac(int $x1, int $y1, int $x2, int $y2, Mobj $mo): ?int
    {
        $vx = (float) ($x2 - $x1);
        $vy = (float) ($y2 - $y1);
        $wx = (float) ($mo->x - $x1);
        $wy = (float) ($mo->y - $y1);
        $den = $vx * $vx + $vy * $vy;
        if ($den <= 0.0) {
            return null;
        }
        $tn = $wx * $vx + $wy * $vy;
        if ($tn < 0.0 || $tn > $den) {
            return null;
        }
        $px = (int) ($x1 + $tn * $vx / $den);
        $py = (int) ($y1 + $tn * $vy / $den);
        if (self::approxDistance($mo->x - $px, $mo->y - $py) > $mo->radius + 4 * Defs::FRACUNIT) {
            return null;
        }
        return (int) ($tn * Defs::FRACUNIT / $den);
    }

    public static function checkSight(World $world, Mobj $a, Mobj $b): bool
    {
        $s1 = self::pointInSubsector($world, $a->x, $a->y)->sector;
        $s2 = self::pointInSubsector($world, $b->x, $b->y)->sector;
        $n = count($world->sectors);
        $rej = $world->rejectmatrix;
        if ($n && $rej !== '') {
            $p = $s1->iSector * $n + $s2->iSector;
            $byte = $p >> 3;
            if ($byte < strlen($rej) && (ord($rej[$byte]) & (1 << ($p & 7)))) {
                return false;
            }
        }
        if ($s1 === $s2) {
            return true;
        }
        foreach ($world->lines as $ln) {
            if ($ln->backsector !== null) {
                [$top, $bottom] = self::lineOpening($ln);
                if ($top - $bottom > 0) {
                    continue;
                }
            }
            $f = self::interceptFrac($a->x, $a->y, $b->x, $b->y, $ln);
            if ($f !== null && $f > intdiv(Defs::FRACUNIT, 64) && $f < Defs::FRACUNIT - intdiv(Defs::FRACUNIT, 64)) {
                return false;
            }
        }
        return true;
    }

    /** P_ThingHeightClip: ride a moving floor / squeeze under a moving ceiling. */
    public static function thingHeightClip(World $world, Mobj $thing): bool
    {
        $onFloor = $thing->z === $thing->floorz;
        $chk = self::checkPosition($world, $thing, $thing->x, $thing->y);
        $thing->floorz = $chk->floorz;
        $thing->ceilingz = $chk->ceilingz;
        if ($onFloor) {
            $thing->z = $thing->floorz;
        } elseif ($thing->z + $thing->height > $thing->ceilingz) {
            $thing->z = $thing->ceilingz - $thing->height;
        }
        if ($thing->player !== null) {
            $thing->player->viewz = $thing->z + $thing->player->viewheight;
        }
        return $thing->ceilingz - $thing->floorz >= $thing->height;
    }

    /** P_ChangeSector: after a floor/ceiling move, carry or crush things in that sector. */
    public static function changeSector(World $world, Sector $sector, bool $crush): bool
    {
        $nofit = false;
        foreach ($world->mobjs as $thing) {
            if (self::pointInSubsector($world, $thing->x, $thing->y)->sector !== $sector) {
                continue;
            }
            if (self::thingHeightClip($world, $thing)) {
                continue;
            }
            if ($thing->health <= 0) {
                $thing->flags &= ~Defs::MF_SOLID;
                $thing->height = 0;
                continue;
            }
            if (($thing->flags & Defs::MF_SHOOTABLE) === 0) {
                continue;
            }
            $nofit = true;
        }
        return $nofit;
    }

    public static function approxDistance(int $dx, int $dy): int
    {
        $dx = abs($dx);
        $dy = abs($dy);
        if ($dx < $dy) {
            [$dx, $dy] = [$dy, $dx];
        }
        return $dx + intdiv($dy, 2);
    }

    public static function angleTo(int $x1, int $y1, int $x2, int $y2): int
    {
        $x = Compat::asI32($x2 - $x1);
        $y = Compat::asI32($y2 - $y1);
        if ($x === 0 && $y === 0) {
            return 0;
        }
        $ta = Tables::$tantoangle;
        if ($x >= 0) {
            if ($y >= 0) {
                return $x > $y ? $ta[Tables::slopeDiv($y, $x)] : Compat::asU32(Defs::ANG90 - 1 - $ta[Tables::slopeDiv($x, $y)]);
            }
            $y = -$y;
            return $x > $y ? Compat::asU32(-$ta[Tables::slopeDiv($y, $x)]) : Compat::asU32(0xC0000000 + $ta[Tables::slopeDiv($x, $y)]);
        }
        $x = -$x;
        if ($y >= 0) {
            return $x > $y ? Compat::asU32(Defs::ANG180 - 1 - $ta[Tables::slopeDiv($y, $x)]) : Compat::asU32(Defs::ANG90 + $ta[Tables::slopeDiv($x, $y)]);
        }
        $y = -$y;
        return $x > $y ? Compat::asU32(Defs::ANG180 + $ta[Tables::slopeDiv($y, $x)]) : Compat::asU32(0xC0000000 - 1 - $ta[Tables::slopeDiv($x, $y)]);
    }
}
