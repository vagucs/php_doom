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
    public ?object $ceilingline = null;
    /** @var int[] */
    public array $bbox = [0, 0, 0, 0];
}

final class DivLine
{
    public function __construct(
        public int $x,
        public int $y,
        public int $dx,
        public int $dy,
    ) {}
}

final class Collision
{
    public static bool $floatOk = false;
    public static int $tmFloorZ = 0;
    /** @var Line[] */
    public static array $lastSpechit = [];
    public static ?object $ceilingLine = null;
    private static bool $earlyout = false;
    /** @var list<array{frac:int,isaline:bool,line:?Line,thing:?Mobj}> */
    private static array $intercepts = [];
    private static int $traceX = 0;
    private static int $traceY = 0;
    private static int $traceDx = 0;
    private static int $traceDy = 0;
    private const INT_MAX = 0x7FFFFFFF;

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

    public static function unsetThingPosition(World $world, Mobj $thing): void
    {
        if (!$thing->blocklinked) {
            return;
        }
        $nxt = $thing->bnext;
        $prev = $thing->bprev;
        if ($nxt !== null) {
            $nxt->bprev = $prev;
        }
        if ($prev !== null) {
            $prev->bnext = $nxt;
        } elseif (($world->blocklinks[$thing->bindex] ?? null) === $thing) {
            $world->blocklinks[$thing->bindex] = $nxt;
        }
        $thing->bnext = null;
        $thing->bprev = null;
        $thing->blocklinked = false;
    }

    public static function setThingPosition(World $world, Mobj $thing): void
    {
        $thing->bnext = null;
        $thing->bprev = null;
        $thing->blocklinked = false;
        if (($thing->flags & Defs::MF_NOBLOCKMAP) || $world->blocklinks === []) {
            return;
        }
        $bx = Compat::shar($thing->x - $world->bmaporgx, Defs::MAPBLOCKSHIFT);
        $by = Compat::shar($thing->y - $world->bmaporgy, Defs::MAPBLOCKSHIFT);
        if ($bx < 0 || $by < 0 || $bx >= $world->bmapwidth || $by >= $world->bmapheight) {
            return;
        }
        $i = $by * $world->bmapwidth + $bx;
        $head = $world->blocklinks[$i] ?? null;
        $thing->bnext = $head;
        if ($head !== null) {
            $head->bprev = $thing;
        }
        $world->blocklinks[$i] = $thing;
        $thing->bindex = $i;
        $thing->blocklinked = true;
    }

    private static function blockThings(World $world, int $x, int $y, callable $func): bool
    {
        if ($x < 0 || $y < 0 || $x >= $world->bmapwidth || $y >= $world->bmapheight) {
            return true;
        }
        $mo = $world->blocklinks[$y * $world->bmapwidth + $x] ?? null;
        while ($mo !== null) {
            $nxt = $mo->bnext;
            if (!$func($mo)) {
                return false;
            }
            $mo = $nxt;
        }
        return true;
    }

    private static function blockLines(World $world, int $x, int $y, callable $func): bool
    {
        if ($x < 0 || $y < 0 || $x >= $world->bmapwidth || $y >= $world->bmapheight) {
            return true;
        }
        $offset = $world->blockmap[$y * $world->bmapwidth + $x] ?? 0;
        $lump = $world->blockmapShorts;
        while ($offset >= 0 && $offset < count($lump)) {
            $n = $lump[$offset];
            $offset++;
            if ($n === 0xFFFF) {
                return true;
            }
            if ($n >= count($world->lines)) {
                continue;
            }
            $ld = $world->lines[$n];
            if ($ld->validcount === $world->validcount) {
                continue;
            }
            $ld->validcount = $world->validcount;
            if (!$func($ld)) {
                return false;
            }
        }
        return true;
    }

    private static function pitThing(World $world, Mobj $tm, Mobj $other, ?object $game): bool
    {
        if (($other->flags & (Defs::MF_SOLID | Defs::MF_SPECIAL | Defs::MF_SHOOTABLE)) === 0) {
            return true;
        }
        $dist = $other->radius + $tm->radius;
        if (abs($other->x - $tm->tmx) >= $dist || abs($other->y - $tm->tmy) >= $dist || $other === $tm) {
            return true;
        }
        if ($tm->flags & Defs::MF_SKULLFLY) {
            if ($game !== null) {
                $game->damageMobj($other, $tm, ((Enemy::publicRandom() % 8) + 1) * ($tm->damage ?: 0), $tm);
            }
            $tm->flags &= ~Defs::MF_SKULLFLY;
            $tm->momx = $tm->momy = $tm->momz = 0;
            Thinker::setMobjState($tm, Info::MOBJINFO[$tm->type][Info::MI_SPAWNSTATE], $world, $game);
            return false;
        }
        if ($tm->flags & Defs::MF_MISSILE) {
            $target = $tm->target;
            if ($target !== null && self::sameSpecies($target, $other)) {
                if ($other === $target) {
                    return true;
                }
                if ($other->type !== Info::MT_PLAYER) {
                    return false;
                }
            }
            if (($other->flags & Defs::MF_SHOOTABLE) === 0) {
                return ($other->flags & Defs::MF_SOLID) === 0;
            }
            if (!Enemy::missileReaches($tm, $other, $tm->tmx, $tm->tmy, $tm->z)) {
                return true;
            }
            return false;
        }
        if ($other->flags & Defs::MF_SPECIAL) {
            if (($tm->flags & Defs::MF_PICKUP) && $game !== null) {
                $game->touchSpecial($other, $tm);
            }
            return ($other->flags & Defs::MF_SOLID) === 0;
        }
        return ($other->flags & Defs::MF_SOLID) === 0;
    }

    private static function sameSpecies(Mobj $target, Mobj $other): bool
    {
        if ($target->type === $other->type) {
            return true;
        }
        if ($target->type === Info::MT_KNIGHT && $other->type === Info::MT_BRUISER) {
            return true;
        }
        return $target->type === Info::MT_BRUISER && $other->type === Info::MT_KNIGHT;
    }

    private static function pitLine(Mobj $tm, MoveCheck $chk, Line $ld): bool
    {
        $box = $chk->bbox;
        if ($box[Defs::BOXRIGHT] <= $ld->bbox[Defs::BOXLEFT]
            || $box[Defs::BOXLEFT] >= $ld->bbox[Defs::BOXRIGHT]
            || $box[Defs::BOXTOP] <= $ld->bbox[Defs::BOXBOTTOM]
            || $box[Defs::BOXBOTTOM] >= $ld->bbox[Defs::BOXTOP]
        ) {
            return true;
        }
        if (self::boxOnLineSide($box, $ld) !== -1) {
            return true;
        }
        if ($ld->backsector === null) {
            return false;
        }
        if (($tm->flags & Defs::MF_MISSILE) === 0) {
            if ($ld->flags & Defs::ML_BLOCKING) {
                return false;
            }
            if ($tm->player === null && ($ld->flags & Defs::ML_BLOCKMONSTERS)) {
                return false;
            }
        }
        [$top, $bottom, $low] = self::lineOpening($ld);
        if ($top < $chk->ceilingz) {
            $chk->ceilingz = $top;
            $chk->ceilingline = $ld;
        }
        if ($bottom > $chk->floorz) {
            $chk->floorz = $bottom;
        }
        if ($low < $chk->dropoffz) {
            $chk->dropoffz = $low;
        }
        if ($ld->special) {
            $chk->spechit[] = $ld;
        }
        return true;
    }

    public static function checkPosition(World $world, Mobj $thing, int $x, int $y, ?object $game = null): MoveCheck
    {
        $chk = new MoveCheck();
        $thing->tmx = $x;
        $thing->tmy = $y;
        $r = $thing->radius;
        $chk->bbox = [
            Defs::BOXLEFT => $x - $r,
            Defs::BOXRIGHT => $x + $r,
            Defs::BOXBOTTOM => $y - $r,
            Defs::BOXTOP => $y + $r,
        ];
        $sec = self::pointInSubsector($world, $x, $y)->sector;
        $chk->floorz = $chk->dropoffz = $sec->floorheight;
        $chk->ceilingz = $sec->ceilingheight;
        $world->validcount++;
        if (($thing->flags & Defs::MF_NOCLIP) || $world->blocklinks === []) {
            return $chk;
        }
        $box = $chk->bbox;
        $orgx = $world->bmaporgx;
        $orgy = $world->bmaporgy;
        $xl = Compat::shar($box[Defs::BOXLEFT] - $orgx - Defs::MAXRADIUS, Defs::MAPBLOCKSHIFT);
        $xh = Compat::shar($box[Defs::BOXRIGHT] - $orgx + Defs::MAXRADIUS, Defs::MAPBLOCKSHIFT);
        $yl = Compat::shar($box[Defs::BOXBOTTOM] - $orgy - Defs::MAXRADIUS, Defs::MAPBLOCKSHIFT);
        $yh = Compat::shar($box[Defs::BOXTOP] - $orgy + Defs::MAXRADIUS, Defs::MAPBLOCKSHIFT);
        for ($bx = $xl; $bx <= $xh; $bx++) {
            for ($by = $yl; $by <= $yh; $by++) {
                if (!self::blockThings($world, $bx, $by, fn ($th) => self::pitThing($world, $thing, $th, $game))) {
                    $chk->blocked = true;
                    return $chk;
                }
            }
        }
        $xl = Compat::shar($box[Defs::BOXLEFT] - $orgx, Defs::MAPBLOCKSHIFT);
        $xh = Compat::shar($box[Defs::BOXRIGHT] - $orgx, Defs::MAPBLOCKSHIFT);
        $yl = Compat::shar($box[Defs::BOXBOTTOM] - $orgy, Defs::MAPBLOCKSHIFT);
        $yh = Compat::shar($box[Defs::BOXTOP] - $orgy, Defs::MAPBLOCKSHIFT);
        for ($bx = $xl; $bx <= $xh; $bx++) {
            for ($by = $yl; $by <= $yh; $by++) {
                if (!self::blockLines($world, $bx, $by, fn ($ld) => self::pitLine($thing, $chk, $ld))) {
                    $chk->blocked = true;
                    return $chk;
                }
            }
        }
        return $chk;
    }

    public static function tryMove(World $world, Mobj $thing, int $x, int $y, ?object $game = null): bool
    {
        self::$floatOk = false;
        self::$ceilingLine = null;
        $chk = self::checkPosition($world, $thing, $x, $y, $game);
        self::$lastSpechit = $chk->spechit;
        self::$tmFloorZ = $chk->floorz;
        self::$ceilingLine = $chk->ceilingline;
        if ($chk->blocked) {
            return false;
        }
        if (($thing->flags & Defs::MF_NOCLIP) === 0) {
            if ($chk->ceilingz - $chk->floorz < $thing->height) {
                return false;
            }
            self::$floatOk = true;
            if (($thing->flags & Defs::MF_TELEPORT) === 0 && $chk->ceilingz - $thing->z < $thing->height) {
                return false;
            }
            if (($thing->flags & Defs::MF_TELEPORT) === 0 && $chk->floorz - $thing->z > Defs::MAXSTEP) {
                return false;
            }
            if (($thing->flags & (Defs::MF_DROPOFF | Defs::MF_FLOAT)) === 0 && $chk->floorz - $chk->dropoffz > Defs::MAXSTEP) {
                return false;
            }
        }
        self::unsetThingPosition($world, $thing);
        $oldx = $thing->x;
        $oldy = $thing->y;
        $thing->floorz = $chk->floorz;
        $thing->ceilingz = $chk->ceilingz;
        $thing->x = $x;
        $thing->y = $y;
        self::setThingPosition($world, $thing);
        if ($game !== null && (($thing->flags & (Defs::MF_TELEPORT | Defs::MF_NOCLIP)) === 0)) {
            for ($i = count($chk->spechit) - 1; $i >= 0; $i--) {
                $ln = $chk->spechit[$i];
                $side = self::pointOnLineSide($thing->x, $thing->y, $ln);
                $oldside = self::pointOnLineSide($oldx, $oldy, $ln);
                if ($side !== $oldside && $ln->special) {
                    $game->crossSpecial($ln, $oldside, $thing);
                }
            }
        }
        return true;
    }

    private static function divTrace(): DivLine
    {
        return new DivLine(self::$traceX, self::$traceY, self::$traceDx, self::$traceDy);
    }

    /** P_PointOnDivlineSide. */
    public static function pointOnDivlineSide(int $x, int $y, DivLine $line): int
    {
        if ($line->dx === 0) {
            if ($x <= $line->x) {
                return $line->dy > 0 ? 1 : 0;
            }
            return $line->dy < 0 ? 1 : 0;
        }
        if ($line->dy === 0) {
            if ($y <= $line->y) {
                return $line->dx < 0 ? 1 : 0;
            }
            return $line->dx > 0 ? 1 : 0;
        }
        $dx = $x - $line->x;
        $dy = $y - $line->y;
        $xor = Compat::asU32(
            Compat::asU32($line->dy) ^ Compat::asU32($line->dx) ^ Compat::asU32($dx) ^ Compat::asU32($dy)
        );
        if (($xor & 0x80000000) !== 0) {
            $cross = Compat::asU32(Compat::asU32($line->dy) ^ Compat::asU32($dx));
            return ($cross & 0x80000000) !== 0 ? 1 : 0;
        }
        $left = Compat::fixedMul(Compat::shar($line->dy, 8), Compat::shar($dx, 8));
        $right = Compat::fixedMul(Compat::shar($dy, 8), Compat::shar($line->dx, 8));
        return $right < $left ? 0 : 1;
    }

    /** P_InterceptVector: frac of v2 along v1. */
    public static function interceptVector(DivLine $v2, DivLine $v1): int
    {
        $den = Compat::asI32(
            Compat::fixedMul(Compat::shar($v1->dy, 8), $v2->dx) - Compat::fixedMul(Compat::shar($v1->dx, 8), $v2->dy)
        );
        if ($den === 0) {
            return 0;
        }
        $num = Compat::asI32(
            Compat::fixedMul(Compat::shar($v1->x - $v2->x, 8), $v1->dy)
            + Compat::fixedMul(Compat::shar($v2->y - $v1->y, 8), $v1->dx)
        );
        return Compat::fixedDiv($num, $den);
    }

    private static function addLineIntercept(Line $ld): bool
    {
        $big = 16 * Defs::FRACUNIT;
        $dx = self::$traceDx;
        $dy = self::$traceDy;
        if ($dx > $big || $dy > $big || $dx < -$big || $dy < -$big) {
            $tr = self::divTrace();
            $s1 = self::pointOnDivlineSide($ld->v1->x, $ld->v1->y, $tr);
            $s2 = self::pointOnDivlineSide($ld->v2->x, $ld->v2->y, $tr);
        } else {
            $s1 = self::pointOnLineSide(self::$traceX, self::$traceY, $ld);
            $s2 = self::pointOnLineSide(self::$traceX + $dx, self::$traceY + $dy, $ld);
        }
        if ($s1 === $s2) {
            return true;
        }
        $frac = self::interceptVector(self::divTrace(), new DivLine($ld->v1->x, $ld->v1->y, $ld->dx, $ld->dy));
        if ($frac < 0) {
            return true;
        }
        if (self::$earlyout && $frac < Defs::FRACUNIT && $ld->backsector === null) {
            return false;
        }
        self::$intercepts[] = ['frac' => $frac, 'isaline' => true, 'line' => $ld, 'thing' => null];
        return true;
    }

    private static function addThingIntercept(Mobj $thing): bool
    {
        $tr = self::divTrace();
        $positive = Compat::asI32(Compat::asU32($tr->dx) ^ Compat::asU32($tr->dy)) > 0;
        if ($positive) {
            $x1 = $thing->x - $thing->radius;
            $y1 = $thing->y + $thing->radius;
            $x2 = $thing->x + $thing->radius;
            $y2 = $thing->y - $thing->radius;
        } else {
            $x1 = $thing->x - $thing->radius;
            $y1 = $thing->y - $thing->radius;
            $x2 = $thing->x + $thing->radius;
            $y2 = $thing->y + $thing->radius;
        }
        if (self::pointOnDivlineSide($x1, $y1, $tr) === self::pointOnDivlineSide($x2, $y2, $tr)) {
            return true;
        }
        $frac = self::interceptVector($tr, new DivLine($x1, $y1, $x2 - $x1, $y2 - $y1));
        if ($frac < 0) {
            return true;
        }
        self::$intercepts[] = ['frac' => $frac, 'isaline' => false, 'line' => null, 'thing' => $thing];
        return true;
    }

    private static function traverseIntercepts(callable $func, int $maxfrac): bool
    {
        $count = count(self::$intercepts);
        while ($count > 0) {
            --$count;
            $dist = self::INT_MAX;
            $chosen = null;
            foreach (self::$intercepts as $i => $scan) {
                if ($scan['frac'] < $dist) {
                    $dist = $scan['frac'];
                    $chosen = $i;
                }
            }
            if ($dist > $maxfrac) {
                return true;
            }
            if ($chosen === null || !$func(self::$intercepts[$chosen])) {
                return false;
            }
            self::$intercepts[$chosen]['frac'] = self::INT_MAX;
        }
        return true;
    }

    private static function abs32(int $n): int
    {
        $n = Compat::asI32($n);
        return $n < 0 ? -$n : $n;
    }

    /** P_PathTraverse: blockmap DDA, then intercepts from nearest to farthest. */
    public static function pathTraverse(
        World $world,
        int $x1,
        int $y1,
        int $x2,
        int $y2,
        int $flags,
        callable $trav
    ): bool {
        self::$earlyout = ($flags & Defs::PT_EARLYOUT) !== 0;
        $world->validcount++;
        self::$intercepts = [];
        $orgx = $world->bmaporgx;
        $orgy = $world->bmaporgy;
        if ((($x1 - $orgx) & (Defs::MAPBLOCKSIZE - 1)) === 0) {
            $x1 += Defs::FRACUNIT;
        }
        if ((($y1 - $orgy) & (Defs::MAPBLOCKSIZE - 1)) === 0) {
            $y1 += Defs::FRACUNIT;
        }
        self::$traceX = $x1;
        self::$traceY = $y1;
        self::$traceDx = Compat::asI32($x2 - $x1);
        self::$traceDy = Compat::asI32($y2 - $y1);
        $x1 = Compat::asI32($x1 - $orgx);
        $y1 = Compat::asI32($y1 - $orgy);
        $xt1 = Compat::shar($x1, Defs::MAPBLOCKSHIFT);
        $yt1 = Compat::shar($y1, Defs::MAPBLOCKSHIFT);
        $x2m = Compat::asI32($x2 - $orgx);
        $y2m = Compat::asI32($y2 - $orgy);
        $xt2 = Compat::shar($x2m, Defs::MAPBLOCKSHIFT);
        $yt2 = Compat::shar($y2m, Defs::MAPBLOCKSHIFT);
        if ($xt2 > $xt1) {
            $mapxstep = 1;
            $partial = Defs::FRACUNIT - (Compat::shar($x1, Defs::MAPBTOFRAC) & (Defs::FRACUNIT - 1));
            $ystep = Compat::fixedDiv(Compat::asI32($y2m - $y1), self::abs32(Compat::asI32($x2m - $x1)));
        } elseif ($xt2 < $xt1) {
            $mapxstep = -1;
            $partial = Compat::shar($x1, Defs::MAPBTOFRAC) & (Defs::FRACUNIT - 1);
            $ystep = Compat::fixedDiv(Compat::asI32($y2m - $y1), self::abs32(Compat::asI32($x2m - $x1)));
        } else {
            $mapxstep = 0;
            $partial = Defs::FRACUNIT;
            $ystep = 256 * Defs::FRACUNIT;
        }
        $yintercept = Compat::asI32(Compat::shar($y1, Defs::MAPBTOFRAC) + Compat::fixedMul($partial, $ystep));
        if ($yt2 > $yt1) {
            $mapystep = 1;
            $partial = Defs::FRACUNIT - (Compat::shar($y1, Defs::MAPBTOFRAC) & (Defs::FRACUNIT - 1));
            $xstep = Compat::fixedDiv(Compat::asI32($x2m - $x1), self::abs32(Compat::asI32($y2m - $y1)));
        } elseif ($yt2 < $yt1) {
            $mapystep = -1;
            $partial = Compat::shar($y1, Defs::MAPBTOFRAC) & (Defs::FRACUNIT - 1);
            $xstep = Compat::fixedDiv(Compat::asI32($x2m - $x1), self::abs32(Compat::asI32($y2m - $y1)));
        } else {
            $mapystep = 0;
            $partial = Defs::FRACUNIT;
            $xstep = 256 * Defs::FRACUNIT;
        }
        $xintercept = Compat::asI32(Compat::shar($x1, Defs::MAPBTOFRAC) + Compat::fixedMul($partial, $xstep));
        $mapx = $xt1;
        $mapy = $yt1;
        for ($count = 0; $count < 64; $count++) {
            if (($flags & Defs::PT_ADDLINES) !== 0) {
                if (!self::blockLines($world, $mapx, $mapy, [self::class, 'addLineIntercept'])) {
                    return false;
                }
            }
            if (($flags & Defs::PT_ADDTHINGS) !== 0) {
                if (!self::blockThings($world, $mapx, $mapy, [self::class, 'addThingIntercept'])) {
                    return false;
                }
            }
            if ($mapx === $xt2 && $mapy === $yt2) {
                break;
            }
            if (Compat::shar($yintercept, Defs::FRACBITS) === $mapy) {
                $yintercept = Compat::asI32($yintercept + $ystep);
                $mapx += $mapxstep;
            } elseif (Compat::shar($xintercept, Defs::FRACBITS) === $mapx) {
                $xintercept = Compat::asI32($xintercept + $xstep);
                $mapy += $mapystep;
            }
        }
        return self::traverseIntercepts($trav, Defs::FRACUNIT);
    }

    /** P_HitSlideLine: keep the part of the move that runs along the wall. */
    private static function hitSlideLine(Mobj $thing, Line $line, int $tmx, int $tmy): array
    {
        if ($line->dy === 0) {
            return [$tmx, 0];
        }
        if ($line->dx === 0) {
            return [0, $tmy];
        }
        $lineangle = self::angleTo(0, 0, $line->dx, $line->dy);
        if (self::pointOnLineSide($thing->x, $thing->y, $line) === 1) {
            $lineangle = Compat::asU32($lineangle + Defs::ANG180);
        }
        $delta = Compat::asU32(self::angleTo(0, 0, $tmx, $tmy) - $lineangle);
        if ($delta > Defs::ANG180) {
            $delta = Compat::asU32($delta + Defs::ANG180);
        }
        $newlen = Compat::fixedMul(self::approxDistance($tmx, $tmy), Tables::fineCos($delta));
        return [Compat::fixedMul($newlen, Tables::fineCos($lineangle)), Compat::fixedMul($newlen, Tables::fineSin($lineangle))];
    }

    private static function stairstep(World $world, Mobj $thing, ?object $game): void
    {
        if (!self::tryMove($world, $thing, $thing->x, $thing->y + $thing->momy, $game)) {
            self::tryMove($world, $thing, $thing->x + $thing->momx, $thing->y, $game);
        }
    }

    /** P_SlideMove: ride the wall instead of dropping the blocked axis. */
    public static function slideMove(World $world, Mobj $thing, int $momx, int $momy, ?object $game = null): void
    {
        if (abs($momx) > Defs::MAXMOVE) {
            $momx = $momx > 0 ? Defs::MAXMOVE : -Defs::MAXMOVE;
        }
        if (abs($momy) > Defs::MAXMOVE) {
            $momy = $momy > 0 ? Defs::MAXMOVE : -Defs::MAXMOVE;
        }
        $thing->momx = $momx;
        $thing->momy = $momy;
        $hitcount = 0;
        while (true) {
            if (++$hitcount === 3) {
                self::stairstep($world, $thing, $game);
                return;
            }
            $leadx = $thing->momx > 0 ? $thing->x + $thing->radius : $thing->x - $thing->radius;
            $trailx = $thing->momx > 0 ? $thing->x - $thing->radius : $thing->x + $thing->radius;
            $leady = $thing->momy > 0 ? $thing->y + $thing->radius : $thing->y - $thing->radius;
            $traily = $thing->momy > 0 ? $thing->y - $thing->radius : $thing->y + $thing->radius;
            $best = ['frac' => Defs::FRACUNIT + 1, 'line' => null];
            $mx = $thing->momx;
            $my = $thing->momy;
            $slideTrav = static function (array $inn) use ($thing, &$best): bool {
                /** @var Line $li */
                $li = $inn['line'];
                $blocking = false;
                if (($li->flags & Defs::ML_TWOSIDED) === 0) {
                    if (self::pointOnLineSide($thing->x, $thing->y, $li) !== 0) {
                        return true;
                    }
                    $blocking = true;
                } else {
                    [$opentop, $openbottom] = self::lineOpening($li);
                    if ($opentop - $openbottom < $thing->height) {
                        $blocking = true;
                    } elseif ($opentop - $thing->z < $thing->height) {
                        $blocking = true;
                    } elseif ($openbottom - $thing->z > 24 * Defs::FRACUNIT) {
                        $blocking = true;
                    }
                }
                if (!$blocking) {
                    return true;
                }
                if ($inn['frac'] < $best['frac']) {
                    $best['frac'] = $inn['frac'];
                    $best['line'] = $li;
                }
                return false;
            };
            self::pathTraverse($world, $leadx, $leady, $leadx + $mx, $leady + $my, Defs::PT_ADDLINES, $slideTrav);
            self::pathTraverse($world, $trailx, $leady, $trailx + $mx, $leady + $my, Defs::PT_ADDLINES, $slideTrav);
            self::pathTraverse($world, $leadx, $traily, $leadx + $mx, $traily + $my, Defs::PT_ADDLINES, $slideTrav);
            if ($best['frac'] === Defs::FRACUNIT + 1 || $best['line'] === null) {
                self::stairstep($world, $thing, $game);
                return;
            }
            $best['frac'] -= 0x800;
            if ($best['frac'] > 0) {
                $newx = Compat::fixedMul($thing->momx, $best['frac']);
                $newy = Compat::fixedMul($thing->momy, $best['frac']);
                if (!self::tryMove($world, $thing, $thing->x + $newx, $thing->y + $newy, $game)) {
                    self::stairstep($world, $thing, $game);
                    return;
                }
            }
            $best['frac'] = Defs::FRACUNIT - ($best['frac'] + 0x800);
            if ($best['frac'] > Defs::FRACUNIT) {
                $best['frac'] = Defs::FRACUNIT;
            }
            if ($best['frac'] <= 0) {
                return;
            }
            [$tmx, $tmy] = self::hitSlideLine(
                $thing,
                $best['line'],
                Compat::fixedMul($thing->momx, $best['frac']),
                Compat::fixedMul($thing->momy, $best['frac']),
            );
            $thing->momx = $tmx;
            $thing->momy = $tmy;
            if (self::tryMove($world, $thing, $thing->x + $tmx, $thing->y + $tmy, $game)) {
                return;
            }
        }
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

    /** P_UseLines + PTR_UseTraverse along the blockmap. */
    public static function useLines(World $world, Player $player, object $game): void
    {
        $mo = $player->mo;
        $x1 = $mo->x;
        $y1 = $mo->y;
        $x2 = $x1 + Compat::shar(Defs::USERANGE, Defs::FRACBITS) * Tables::fineCos($mo->angle);
        $y2 = $y1 + Compat::shar(Defs::USERANGE, Defs::FRACBITS) * Tables::fineSin($mo->angle);
        $use = static function (array $inn) use ($mo, $game): bool {
            /** @var Line $ln */
            $ln = $inn['line'];
            if (!$ln->special) {
                [$opentop, $openbottom] = self::lineOpening($ln);
                if ($opentop - $openbottom <= 0) {
                    $game->startSound('noway');
                    return false;
                }
                return true;
            }
            $side = self::pointOnLineSide($mo->x, $mo->y, $ln) === 1 ? 1 : 0;
            $game->useSpecial($ln, $mo, $side);
            return false;
        };
        self::pathTraverse($world, $x1, $y1, $x2, $y2, Defs::PT_ADDLINES, $use);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function shotEnds(Mobj $source, int $angle, int $attackrange): array
    {
        $x2 = $source->x + Compat::shar($attackrange, Defs::FRACBITS) * Tables::fineCos($angle);
        $y2 = $source->y + Compat::shar($attackrange, Defs::FRACBITS) * Tables::fineSin($angle);
        $shootz = $source->z + ($source->height >> 1) + 8 * Defs::FRACUNIT;
        return [$x2, $y2, $shootz];
    }

    /** PTR_AimTraverse over P_PathTraverse.
     * @return array{slope:int,target:?Mobj}
     */
    private static function aim(World $world, Mobj $source, int $angle, int $range): array
    {
        [$x2, $y2, $shootz] = self::shotEnds($source, $angle, $range);
        $window = intdiv(100 * Defs::FRACUNIT, 160);
        $state = ['top' => $window, 'bottom' => -$window, 'slope' => 0, 'target' => null];
        $trav = static function (array $inn) use ($source, $range, $shootz, &$state): bool {
            if ($inn['isaline']) {
                /** @var Line $li */
                $li = $inn['line'];
                if (($li->flags & Defs::ML_TWOSIDED) === 0) {
                    return false;
                }
                [$opentop, $openbottom] = self::lineOpening($li);
                if ($openbottom >= $opentop) {
                    return false;
                }
                $dist = Compat::fixedMul($range, $inn['frac']);
                $front = $li->frontsector;
                $back = $li->backsector;
                if ($back === null || $front->floorheight !== $back->floorheight) {
                    $slope = Compat::fixedDiv($openbottom - $shootz, $dist);
                    if ($slope > $state['bottom']) {
                        $state['bottom'] = $slope;
                    }
                }
                if ($back === null || $front->ceilingheight !== $back->ceilingheight) {
                    $slope = Compat::fixedDiv($opentop - $shootz, $dist);
                    if ($slope < $state['top']) {
                        $state['top'] = $slope;
                    }
                }
                return $state['top'] > $state['bottom'];
            }
            /** @var Mobj $th */
            $th = $inn['thing'];
            if ($th === $source || ($th->flags & Defs::MF_SHOOTABLE) === 0) {
                return true;
            }
            $dist = Compat::fixedMul($range, $inn['frac']);
            $thingtop = Compat::fixedDiv($th->z + $th->height - $shootz, $dist);
            if ($thingtop < $state['bottom']) {
                return true;
            }
            $thingbot = Compat::fixedDiv($th->z - $shootz, $dist);
            if ($thingbot > $state['top']) {
                return true;
            }
            if ($thingtop > $state['top']) {
                $thingtop = $state['top'];
            }
            if ($thingbot < $state['bottom']) {
                $thingbot = $state['bottom'];
            }
            $sum = $thingtop + $thingbot;
            $slope = intdiv($sum, 2);
            if ($sum < 0 && ($sum % 2) !== 0) {
                --$slope;
            }
            $state['slope'] = $slope;
            $state['target'] = $th;
            return false;
        };
        self::pathTraverse($world, $source->x, $source->y, $x2, $y2, Defs::PT_ADDLINES | Defs::PT_ADDTHINGS, $trav);
        if ($state['target'] !== null) {
            return ['slope' => $state['slope'], 'target' => $state['target']];
        }
        return ['slope' => 0, 'target' => null];
    }

    public static function aimSlope(World $world, Mobj $source, int $angle, int $range): int
    {
        return self::aim($world, $source, $angle, $range)['slope'];
    }

    public static function bulletSlope(World $world, Mobj $source): int
    {
        return self::missileAim($world, $source)['slope'];
    }

    /**
     * P_SpawnPlayerMissile: aim straight, then a step left and right.
     * The angle that finds the target is the one the missile flies.
     *
     * @return array{angle:int,slope:int}
     */
    public static function missileAim(World $world, Mobj $source): array
    {
        $base = $source->angle;
        $span = 16 * 64 * Defs::FRACUNIT;
        $shifted = Compat::asU32($base + (1 << 26));
        foreach ([$base, $shifted, Compat::asU32($shifted - (2 << 26))] as $ang) {
            $aimed = self::aim($world, $source, $ang, $span);
            if ($aimed['target'] !== null) {
                return ['angle' => $ang, 'slope' => $aimed['slope']];
            }
        }
        return ['angle' => $base, 'slope' => 0];
    }

    public static function lineAttack(
        World $world,
        Mobj $source,
        int $damage,
        ?object $game,
        int $range,
        ?int $angle = null,
        ?int $slope = null
    ): bool {
        $ang = $angle ?? $source->angle;
        $aimslope = $slope === null ? self::aim($world, $source, $ang, $range)['slope'] : $slope;
        [$x2, $y2, $shootz] = self::shotEnds($source, $ang, $range);
        $sky = -1;
        if ($game !== null && isset($game->res) && $game->res !== null) {
            $sky = $game->res->skyflatnum;
        }
        $hit = false;
        $shoot = static function (array $inn) use (
            $world,
            $source,
            $game,
            $damage,
            $range,
            $aimslope,
            $shootz,
            $sky,
            &$hit
        ): bool {
            if ($inn['isaline']) {
                /** @var Line $li */
                $li = $inn['line'];
                if ($li->special && $game !== null) {
                    $game->shootSpecial($li, $source);
                }
                $hitLine = false;
                if (($li->flags & Defs::ML_TWOSIDED) === 0) {
                    $hitLine = true;
                } else {
                    [$opentop, $openbottom] = self::lineOpening($li);
                    $dist = Compat::fixedMul($range, $inn['frac']);
                    $front = $li->frontsector;
                    $back = $li->backsector;
                    if ($back === null) {
                        if (Compat::fixedDiv($openbottom - $shootz, $dist) > $aimslope) {
                            $hitLine = true;
                        } elseif (Compat::fixedDiv($opentop - $shootz, $dist) < $aimslope) {
                            $hitLine = true;
                        }
                    } elseif (
                        $front->floorheight !== $back->floorheight
                        && Compat::fixedDiv($openbottom - $shootz, $dist) > $aimslope
                    ) {
                        $hitLine = true;
                    } elseif (
                        $front->ceilingheight !== $back->ceilingheight
                        && Compat::fixedDiv($opentop - $shootz, $dist) < $aimslope
                    ) {
                        $hitLine = true;
                    }
                }
                if (!$hitLine) {
                    return true;
                }
                $frac = $inn['frac'] - Compat::fixedDiv(4 * Defs::FRACUNIT, $range);
                $x = self::$traceX + Compat::fixedMul(self::$traceDx, $frac);
                $y = self::$traceY + Compat::fixedMul(self::$traceDy, $frac);
                $z = $shootz + Compat::fixedMul($aimslope, Compat::fixedMul($frac, $range));
                $front = $li->frontsector;
                if ($front !== null && $front->ceilingpic === $sky) {
                    if ($z > $front->ceilingheight) {
                        return false;
                    }
                    if ($li->backsector !== null && $li->backsector->ceilingpic === $sky) {
                        return false;
                    }
                }
                self::spawnPuff($world, $x, $y, $z, $game, $range);
                return false;
            }
            /** @var Mobj $th */
            $th = $inn['thing'];
            if ($th === $source || ($th->flags & Defs::MF_SHOOTABLE) === 0) {
                return true;
            }
            $dist = Compat::fixedMul($range, $inn['frac']);
            if (Compat::fixedDiv($th->z + $th->height - $shootz, $dist) < $aimslope) {
                return true;
            }
            if (Compat::fixedDiv($th->z - $shootz, $dist) > $aimslope) {
                return true;
            }
            $frac = $inn['frac'] - Compat::fixedDiv(10 * Defs::FRACUNIT, $range);
            $x = self::$traceX + Compat::fixedMul(self::$traceDx, $frac);
            $y = self::$traceY + Compat::fixedMul(self::$traceDy, $frac);
            $z = $shootz + Compat::fixedMul($aimslope, Compat::fixedMul($frac, $range));
            if (($th->flags & Defs::MF_NOBLOOD) !== 0) {
                self::spawnPuff($world, $x, $y, $z, $game, $range);
            } else {
                self::spawnBlood($world, $x, $y, $z, $game, $damage);
            }
            if ($game !== null && $damage) {
                $game->damageMobj($th, $source, $damage, $source);
            }
            $hit = true;
            return false;
        };
        self::pathTraverse($world, $source->x, $source->y, $x2, $y2, Defs::PT_ADDLINES | Defs::PT_ADDTHINGS, $shoot);
        return $hit;
    }

    /** P_SpawnPuff. The random draws matter even when the puff is only a thinker. */
    private static function spawnPuff(World $world, int $x, int $y, int $z, ?object $game, int $attackrange): void
    {
        $z += (Enemy::publicRandom() - Enemy::publicRandom()) * 1024;
        $th = Thinker::spawnMobj($world, $x, $y, $z, Info::MT_PUFF, $game);
        $th->momz = Defs::FRACUNIT;
        $th->tics -= Enemy::publicRandom() & 3;
        if ($th->tics < 1) {
            $th->tics = 1;
        }
        if ($attackrange === Defs::MELEERANGE) {
            Thinker::setMobjState($th, Info::S_PUFF3, $world, $game);
        }
    }

    /** P_SpawnBlood. */
    private static function spawnBlood(World $world, int $x, int $y, int $z, ?object $game, int $damage): void
    {
        $z += (Enemy::publicRandom() - Enemy::publicRandom()) * 1024;
        $th = Thinker::spawnMobj($world, $x, $y, $z, Info::MT_BLOOD, $game);
        $th->momz = Defs::FRACUNIT * 2;
        $th->tics -= Enemy::publicRandom() & 3;
        if ($th->tics < 1) {
            $th->tics = 1;
        }
        if ($damage <= 12 && $damage >= 9) {
            Thinker::setMobjState($th, Info::S_BLOOD2, $world, $game);
        } elseif ($damage < 9) {
            Thinker::setMobjState($th, Info::S_BLOOD3, $world, $game);
        }
    }

    public static function aimLineAttack(World $world, Mobj $source, int $angle, int $range): ?Mobj
    {
        return self::aim($world, $source, $angle, $range)['target'];
    }

    /** P_CheckSight: REJECT, then the BSP, closing the vertical window at each opening. */
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
        $eye = $a->z + $a->height - ($a->height >> 2);
        $st = [
            'top' => ($b->z + $b->height) - $eye,
            'bottom' => $b->z - $eye,
            'z' => $eye,
            'x' => $a->x,
            'y' => $a->y,
            'dx' => Compat::asI32($b->x - $a->x),
            'dy' => Compat::asI32($b->y - $a->y),
            't2x' => $b->x,
            't2y' => $b->y,
        ];
        ++$world->validcount;
        if ($world->numnodes === 0) {
            return self::crossSightSubsector($world, 0, $st);
        }
        return self::crossSightBsp($world, $world->numnodes - 1, $st);
    }

    /** P_DivlineSide. 2 means the point is on the line. */
    private static function sightSide(int $x, int $y, int $lx, int $ly, int $ldx, int $ldy): int
    {
        $dx = Compat::shar($x - $lx, Defs::FRACBITS);
        $dy = Compat::shar($y - $ly, Defs::FRACBITS);
        $left = Compat::asI32(Compat::shar($ldy, Defs::FRACBITS) * $dx);
        $right = Compat::asI32($dy * Compat::shar($ldx, Defs::FRACBITS));
        if ($right < $left) {
            return 0;
        }
        if ($left === $right) {
            return 2;
        }
        return 1;
    }

    /** @param array{top:int,bottom:int,z:int,x:int,y:int,dx:int,dy:int,t2x:int,t2y:int} $st */
    private static function crossSightSubsector(World $world, int $num, array &$st): bool
    {
        if ($num < 0 || $num >= count($world->subsectors)) {
            $num = 0;
        }
        $sub = $world->subsectors[$num];
        $nSeg = $sub->firstline;
        for ($count = $sub->numlines; $count > 0; --$count, ++$nSeg) {
            $seg = $world->segs[$nSeg] ?? null;
            $line = $seg?->linedef;
            if ($seg === null || $line === null || $line->v1 === null || $line->v2 === null) {
                continue;
            }
            if ($line->validcount === $world->validcount) {
                continue;
            }
            $line->validcount = $world->validcount;
            $s1 = self::sightSide($line->v1->x, $line->v1->y, $st['x'], $st['y'], $st['dx'], $st['dy']);
            $s2 = self::sightSide($line->v2->x, $line->v2->y, $st['x'], $st['y'], $st['dx'], $st['dy']);
            if ($s1 === $s2) {
                continue;
            }
            $ldx = Compat::asI32($line->v2->x - $line->v1->x);
            $ldy = Compat::asI32($line->v2->y - $line->v1->y);
            $s1 = self::sightSide($st['x'], $st['y'], $line->v1->x, $line->v1->y, $ldx, $ldy);
            $s2 = self::sightSide($st['t2x'], $st['t2y'], $line->v1->x, $line->v1->y, $ldx, $ldy);
            if ($s1 === $s2) {
                continue;
            }
            if ($line->backsector === null || ($line->flags & Defs::ML_TWOSIDED) === 0) {
                return false;
            }
            $front = $seg->frontsector;
            $back = $seg->backsector;
            if ($front === null || $back === null) {
                return false;
            }
            if ($front->floorheight === $back->floorheight && $front->ceilingheight === $back->ceilingheight) {
                continue;
            }
            $opentop = min($front->ceilingheight, $back->ceilingheight);
            $openbottom = max($front->floorheight, $back->floorheight);
            if ($openbottom >= $opentop) {
                return false;
            }
            $frac = self::interceptVector(
                new DivLine($st['x'], $st['y'], $st['dx'], $st['dy']),
                new DivLine($line->v1->x, $line->v1->y, $ldx, $ldy)
            );
            if ($front->floorheight !== $back->floorheight) {
                $slope = Compat::fixedDiv($openbottom - $st['z'], $frac);
                if ($slope > $st['bottom']) {
                    $st['bottom'] = $slope;
                }
            }
            if ($front->ceilingheight !== $back->ceilingheight) {
                $slope = Compat::fixedDiv($opentop - $st['z'], $frac);
                if ($slope < $st['top']) {
                    $st['top'] = $slope;
                }
            }
            if ($st['top'] <= $st['bottom']) {
                return false;
            }
        }
        return true;
    }

    /** @param array{top:int,bottom:int,z:int,x:int,y:int,dx:int,dy:int,t2x:int,t2y:int} $st */
    private static function crossSightBsp(World $world, int $bspnum, array &$st): bool
    {
        if (($bspnum & Defs::NF_SUBSECTOR) !== 0 || $bspnum < 0) {
            if (($bspnum & 0xFFFF) === 0xFFFF) {
                return self::crossSightSubsector($world, 0, $st);
            }
            return self::crossSightSubsector($world, $bspnum & 0x7FFF, $st);
        }
        $node = $world->nodes[$bspnum] ?? null;
        if ($node === null) {
            return true;
        }
        $side = self::sightSide($st['x'], $st['y'], $node->x, $node->y, $node->dx, $node->dy);
        if ($side === 2) {
            $side = 0;
        }
        if (!self::crossSightBsp($world, $node->children[$side], $st)) {
            return false;
        }
        $other = self::sightSide($st['t2x'], $st['t2y'], $node->x, $node->y, $node->dx, $node->dy);
        if ($side === $other) {
            return true;
        }
        return self::crossSightBsp($world, $node->children[$side ^ 1], $st);
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
            if ($crush) {
                $thing->health -= 10;
                if ($thing->player !== null) {
                    $thing->player->health = $thing->health;
                }
                if ($thing->health <= 0) {
                    $thing->flags &= ~Defs::MF_SOLID;
                    $thing->height = 0;
                }
            }
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
