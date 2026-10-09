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

final class VerticalDoor
{
    public bool $dead = false;

    public function __construct(
        public Sector $sector,
        public int $type,
        public int $direction,
        public int $topheight,
        public int $speed,
        public int $topwait,
        public int $topcountdown = 0,
    ) {}
}

final class Plat
{
    public bool $dead = false;

    public function __construct(
        public Sector $sector,
        public int $type,
        public int $status,
        public int $speed,
        public int $low,
        public int $high,
        public int $wait,
        public int $count = 0,
    ) {}
}

final class FloorMove
{
    public bool $dead = false;
    public bool $crush = false;
    public ?int $floorpic = null;

    public function __construct(
        public Sector $sector,
        public int $direction,
        public int $dest,
        public int $speed,
    ) {}
}

final class CeilingMove
{
    public bool $dead = false;
    public bool $crush = false;
    public int $ctype = 0;
    public int $topheight = 0;
    public int $bottomheight = 0;

    public function __construct(
        public Sector $sector,
        public int $direction,
        public int $dest,
        public int $speed,
    ) {}
}

final class LightThinker
{
    public bool $dead = false;
    public int $count = 0;
    public int $minlight = 0;
    public int $maxlight = 0;
    public int $darktime = 0;
    public int $brighttime = 0;
    public int $maxtime = 64;
    public int $mintime = 7;
    public int $direction = -1;

    public function __construct(public Sector $sector, public string $kind) {}
}

final class Button
{
    public function __construct(
        public Line $line,
        public string $where,
        public int $texture,
        public int $timer,
    ) {}
}

final class Specials
{
    public array $thinkers = [];
    /** @var LightThinker[] */
    public array $lights = [];
    /** @var Line[] */
    public array $scrollLines = [];
    public array $buttons = [];
    public bool $exitRequested = false;
    public bool $secretExit = false;
    public array $switchMap = [];

    private const SWITCH_PAIRS = [
        ['SW1BRCOM', 'SW2BRCOM'], ['SW1BRN1', 'SW2BRN1'], ['SW1BRN2', 'SW2BRN2'],
        ['SW1BRNGN', 'SW2BRNGN'], ['SW1BROWN', 'SW2BROWN'], ['SW1COMM', 'SW2COMM'],
        ['SW1COMP', 'SW2COMP'], ['SW1DIRT', 'SW2DIRT'], ['SW1EXIT', 'SW2EXIT'],
        ['SW1GRAY', 'SW2GRAY'], ['SW1GRAY1', 'SW2GRAY1'], ['SW1METAL', 'SW2METAL'],
        ['SW1PIPE', 'SW2PIPE'], ['SW1SLAD', 'SW2SLAD'], ['SW1STARG', 'SW2STARG'],
        ['SW1STON1', 'SW2STON1'], ['SW1STON2', 'SW2STON2'], ['SW1STONE', 'SW2STONE'],
        ['SW1STRTN', 'SW2STRTN'], ['SW1BLUE', 'SW2BLUE'], ['SW1CMT', 'SW2CMT'],
        ['SW1GARG', 'SW2GARG'], ['SW1GSTON', 'SW2GSTON'], ['SW1HOT', 'SW2HOT'],
        ['SW1LION', 'SW2LION'], ['SW1SATYR', 'SW2SATYR'], ['SW1SKIN', 'SW2SKIN'],
        ['SW1VINE', 'SW2VINE'], ['SW1WOOD', 'SW2WOOD'], ['SW1PANEL', 'SW2PANEL'],
        ['SW1ROCK', 'SW2ROCK'], ['SW1MET2', 'SW2MET2'], ['SW1WDMET', 'SW2WDMET'],
        ['SW1BRIK', 'SW2BRIK'], ['SW1MOD1', 'SW2MOD1'], ['SW1ZIM', 'SW2ZIM'],
        ['SW1STON6', 'SW2STON6'], ['SW1TEK', 'SW2TEK'], ['SW1MARB', 'SW2MARB'],
        ['SW1SKULL', 'SW2SKULL'],
    ];

    public function __construct(public World $world, public object $res, public object $sound)
    {
        foreach (self::SWITCH_PAIRS as [$a, $b]) {
            $ia = $res->textureNumForName($a);
            $ib = $res->textureNumForName($b);
            if ($ia || $ib) {
                $this->switchMap[$ia] = $ib;
                $this->switchMap[$ib] = $ia;
            }
        }
        $this->spawnSpecials();
    }

    /** T_MovePlane + P_ChangeSector (p_floor.prg): move the plane then carry things with it. */
    private function movePlane(Sector $s, int $speed, int $dest, int $plane, int $dir, bool $crush = false): int
    {
        $prop = $plane ? 'ceilingheight' : 'floorheight';
        $last = $s->$prop;
        $past = false;
        if ($dir === -1) {
            if ($last - $speed < $dest) {
                $s->$prop = $dest;
                $past = true;
            } else {
                $s->$prop -= $speed;
            }
        } elseif ($last + $speed > $dest) {
            $s->$prop = $dest;
            $past = true;
        } else {
            $s->$prop += $speed;
        }
        $nofit = Collision::changeSector($this->world, $s, $crush);
        if ($nofit) {
            if (!$crush || $past) {
                $s->$prop = $last;
                Collision::changeSector($this->world, $s, $crush);
            }
            return $past ? Defs::RESULT_PASTDEST : Defs::RESULT_CRUSHED;
        }
        return $past ? Defs::RESULT_PASTDEST : Defs::RESULT_OK;
    }

    public static function surroundingSectors(Sector $s): array
    {
        $out = [];
        foreach ($s->lines as $ln) {
            $o = $ln->frontsector === $s ? $ln->backsector : $ln->frontsector;
            if ($o && $o !== $s && !in_array($o, $out, true)) {
                $out[] = $o;
            }
        }
        return $out;
    }

    public static function lowestCeiling(Sector $s): int
    {
        $h = 0x7fffffff;
        foreach (self::surroundingSectors($s) as $o) {
            $h = min($h, $o->ceilingheight);
        }
        return $h === 0x7fffffff ? $s->ceilingheight : $h;
    }

    public static function lowestFloor(Sector $s): int
    {
        $h = $s->floorheight;
        foreach (self::surroundingSectors($s) as $o) {
            $h = min($h, $o->floorheight);
        }
        return $h;
    }

    public static function highestFloor(Sector $s): int
    {
        $h = -500 * Defs::FRACUNIT;
        foreach (self::surroundingSectors($s) as $o) {
            $h = max($h, $o->floorheight);
        }
        return $h;
    }

    public static function nextHighestFloor(Sector $s, int $cur): int
    {
        $h = 0x7fffffff;
        foreach (self::surroundingSectors($s) as $o) {
            if ($o->floorheight > $cur) {
                $h = min($h, $o->floorheight);
            }
        }
        return $h === 0x7fffffff ? $cur : $h;
    }

    public static function highestCeiling(Sector $s): int
    {
        $h = $s->ceilingheight;
        foreach (self::surroundingSectors($s) as $o) {
            $h = max($h, $o->ceilingheight);
        }
        return $h;
    }

    public static function raiseFloorDest(Sector $s): int
    {
        $dest = self::lowestCeiling($s);
        return $dest <= $s->ceilingheight ? $dest : $s->ceilingheight;
    }

    public static function raiseFloorCrushDest(Sector $s): int
    {
        return self::raiseFloorDest($s) - 8 * Defs::FRACUNIT;
    }

    public static function minSurroundingLight(Sector $s, int $max): int
    {
        foreach (self::surroundingSectors($s) as $o) {
            $max = min($max, $o->lightlevel);
        }
        return $max;
    }

    public static function maxSurroundingLight(Sector $s): int
    {
        $h = $s->lightlevel;
        foreach (self::surroundingSectors($s) as $o) {
            $h = max($h, $o->lightlevel);
        }
        return $h;
    }

    public function sectorsFromTag(int $tag): array
    {
        return $tag
            ? array_values(array_filter($this->world->sectors, fn ($s) => $s->tag === $tag))
            : [];
    }

    public function tick(): void
    {
        $this->tickLights();
        foreach ($this->scrollLines as $ln) {
            if ($ln->sides[0]) {
                $ln->sides[0]->textureoffset += Defs::FRACUNIT;
            }
        }
        $alive = [];
        foreach ($this->thinkers as $t) {
            if ($t->dead) {
                continue;
            }
            if ($t instanceof VerticalDoor) {
                $this->tickDoor($t);
            } elseif ($t instanceof Plat) {
                $this->tickPlat($t);
            } elseif ($t instanceof FloorMove) {
                $this->tickFloor($t);
            } elseif ($t instanceof CeilingMove) {
                $this->tickCeiling($t);
            }
            if (!$t->dead) {
                $alive[] = $t;
            }
        }
        $this->thinkers = $alive;
        foreach ($this->buttons as $i => $b) {
            if (--$b->timer <= 0) {
                $side = $b->line->sides[0];
                if ($side) {
                    $prop = $b->where . 'texture';
                    $side->$prop = $b->texture;
                }
                unset($this->buttons[$i]);
            }
        }
        $this->buttons = array_values($this->buttons);
    }

    private function tickDoor(VerticalDoor $d): void
    {
        if ($d->direction === 0) {
            if (--$d->topcountdown <= 0) {
                if (in_array($d->type, [Defs::VLD_NORMAL, Defs::VLD_BLAZERAISE, Defs::VLD_CLOSE], true)) {
                    $d->direction = -1;
                    $this->sound->play($d->type === Defs::VLD_BLAZERAISE ? 'bdcls' : 'dorcls');
                } elseif (in_array($d->type, [Defs::VLD_CLOSE30, Defs::VLD_RAISEIN5], true)) {
                    $d->direction = 1;
                    $this->sound->play('doropn');
                }
            }
            return;
        }
        $dest = $d->direction === 1 ? $d->topheight : $d->sector->floorheight;
        if ($this->movePlane($d->sector, $d->speed, $dest, 1, $d->direction) !== Defs::RESULT_PASTDEST) {
            return;
        }
        if ($d->direction === 1) {
            if (in_array($d->type, [Defs::VLD_NORMAL, Defs::VLD_BLAZERAISE], true)) {
                $d->direction = 0;
                $d->topcountdown = $d->topwait;
            } else {
                $d->sector->specialdata = null;
                $d->dead = true;
            }
        } elseif ($d->type === Defs::VLD_CLOSE30) {
            $d->direction = 0;
            $d->topcountdown = Defs::TICRATE * 30;
        } else {
            $d->sector->specialdata = null;
            $d->dead = true;
        }
    }

    private function tickPlat(Plat $p): void
    {
        if ($p->status === Defs::PLAT_WAITING) {
            if (--$p->count <= 0) {
                $p->status = $p->sector->floorheight <= $p->low ? Defs::PLAT_UP : Defs::PLAT_DOWN;
                $this->sound->play('pstart');
            }
            return;
        }
        $up = $p->status === Defs::PLAT_UP;
        if ($this->movePlane($p->sector, $p->speed, $up ? $p->high : $p->low, 0, $up ? 1 : -1) !== Defs::RESULT_PASTDEST) {
            return;
        }
        if (!$up || $p->type === Defs::PLAT_PERPETUAL) {
            $p->status = Defs::PLAT_WAITING;
            $p->count = $p->wait;
            $this->sound->play('pstop');
        } else {
            $p->sector->specialdata = null;
            $p->dead = true;
            $this->sound->play('pstop');
        }
    }

    private function tickFloor(FloorMove $f): void
    {
        if ($this->movePlane($f->sector, $f->speed, $f->dest, 0, $f->direction, $f->crush) === Defs::RESULT_PASTDEST) {
            if ($f->floorpic !== null) {
                $f->sector->floorpic = $f->floorpic;
            }
            $f->sector->specialdata = null;
            $f->dead = true;
        }
    }

    private function tickCeiling(CeilingMove $c): void
    {
        $dest = $c->ctype ? ($c->direction === 1 ? $c->topheight : $c->bottomheight) : $c->dest;
        $res = $this->movePlane($c->sector, $c->speed, $dest, 1, $c->direction, $c->crush);
        $bounce = in_array($c->ctype, [Defs::CEIL_CRUSHANDRAISE, Defs::CEIL_FASTCRUSH, Defs::CEIL_SILENTCRUSH], true);
        if ($res === Defs::RESULT_PASTDEST) {
            if ($bounce) {
                if ($c->direction === -1) {
                    $c->direction = 1;
                    $c->speed = Defs::CEILSPEED * ($c->ctype === Defs::CEIL_FASTCRUSH ? 2 : 1);
                } else {
                    $c->direction = -1;
                }
                if ($c->ctype === Defs::CEIL_SILENTCRUSH) {
                    $this->sound->play('pstop');
                }
            } else {
                $c->sector->specialdata = null;
                $c->dead = true;
            }
        } elseif ($res === Defs::RESULT_CRUSHED && $bounce) {
            $c->speed = max(1, intdiv(Defs::CEILSPEED, 8));
        }
    }

    private function spawnDoor(Sector $s, int $type, bool $reverse = false): bool
    {
        if ($s->specialdata) {
            if ($s->specialdata instanceof VerticalDoor
                && in_array($type, [Defs::VLD_NORMAL, Defs::VLD_BLAZERAISE], true)) {
                $s->specialdata->direction = $s->specialdata->direction === -1 ? 1 : -1;
                return true;
            }
            return false;
        }
        $close = in_array($type, [Defs::VLD_CLOSE, Defs::VLD_BLAZECLOSE, Defs::VLD_CLOSE30], true);
        $d = new VerticalDoor(
            $s,
            $type,
            $reverse || $close ? -1 : 1,
            self::lowestCeiling($s) - 4 * Defs::FRACUNIT,
            Defs::VDOORSPEED * ($type >= Defs::VLD_BLAZERAISE ? 4 : 1),
            Defs::VDOORWAIT
        );
        if ($type === Defs::VLD_CLOSE30) {
            $d->topheight = $s->ceilingheight;
        }
        $s->specialdata = $d;
        $this->thinkers[] = $d;
        $this->sound->play(
            $d->direction === 1
                ? ($type < Defs::VLD_BLAZERAISE ? 'doropn' : 'bdopn')
                : ($type < Defs::VLD_BLAZERAISE ? 'dorcls' : 'bdcls')
        );
        return true;
    }

    public function doDoor(Line $line, int $type, bool $reverse = false): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($this->spawnDoor($s, $type, $reverse)) {
                $ok = true;
            }
        }
        return $ok;
    }

    private function lockedBlazeDoor(Line $line, Mobj $thing, int $sp): void
    {
        $p = $thing->player;
        if (!$p) {
            return;
        }
        if ($sp === 99 || $sp === 133) {
            $card = Defs::IT_BLUECARD;
            $skull = Defs::IT_BLUESKULL;
            $name = 'blue';
        } elseif ($sp === 134 || $sp === 135) {
            $card = Defs::IT_REDCARD;
            $skull = Defs::IT_REDSKULL;
            $name = 'red';
        } else {
            $card = Defs::IT_YELLOWCARD;
            $skull = Defs::IT_YELLOWSKULL;
            $name = 'yellow';
        }
        if (!($p->cards[$card] || $p->cards[$skull])) {
            $p->message = "You need a $name key to open this door";
            $this->sound->play('oof');
            return;
        }
        if ($this->doDoor($line, Defs::VLD_BLAZEOPEN)) {
            $this->changeSwitch($line, ($sp === 99 || $sp === 134 || $sp === 136) ? 1 : 0);
        }
    }

    public function verticalDoor(Line $line, Mobj $thing): void
    {
        $p = $thing->player;
        $sp = $line->special;
        $locks = [
            [[26, 32], Defs::IT_BLUECARD, Defs::IT_BLUESKULL, 'blue'],
            [[27, 34], Defs::IT_YELLOWCARD, Defs::IT_YELLOWSKULL, 'yellow'],
            [[28, 33], Defs::IT_REDCARD, Defs::IT_REDSKULL, 'red'],
        ];
        foreach ($locks as [$nums, $card, $skull, $name]) {
            if (in_array($sp, $nums, true) && $p && !($p->cards[$card] || $p->cards[$skull])) {
                $p->message = "You need a $name key to open this door";
                $this->sound->play('oof');
                return;
            }
        }
        $s = $line->sides[1]?->sector;
        if (!$s) {
            return;
        }
        if (in_array($sp, [1, 26, 27, 28], true)) {
            $type = Defs::VLD_NORMAL;
        } elseif (in_array($sp, [31, 32, 33, 34], true)) {
            $type = Defs::VLD_OPEN;
            $line->special = 0;
        } elseif ($sp === 117) {
            $type = Defs::VLD_BLAZERAISE;
        } elseif ($sp === 118) {
            $type = Defs::VLD_BLAZEOPEN;
            $line->special = 0;
        } else {
            $type = Defs::VLD_NORMAL;
        }
        $this->spawnDoor($s, $type);
    }

    public function doPlatDwus(Line $line, bool $blaze = false): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $p = new Plat(
                $s,
                $blaze ? Defs::PLAT_BLAZEDWUS : Defs::PLAT_DWUS,
                Defs::PLAT_DOWN,
                Defs::PLATSPEED * ($blaze ? 8 : 1),
                self::lowestFloor($s),
                $s->floorheight,
                Defs::PLATWAIT * Defs::TICRATE
            );
            if ($p->low === $p->high) {
                $p->low = $p->high - 8 * Defs::FRACUNIT;
            }
            $s->specialdata = $p;
            $this->thinkers[] = $p;
            $this->sound->play('pstart');
            $ok = true;
        }
        return $ok;
    }

    private function tagLine(int $tag): Line
    {
        $ln = new Line();
        $ln->tag = $tag;
        return $ln;
    }

    public function doFloorTag(int $tag, callable $dest, int $dir, ?int $speed = null, bool $crush = false): bool
    {
        return $this->doFloor($this->tagLine($tag), $dest, $dir, $speed, $crush);
    }

    public function doDoorTag(int $tag, int $type): bool
    {
        return $this->doDoor($this->tagLine($tag), $type);
    }

    public function raiseToTextureTag(int $tag): bool
    {
        return $this->raiseToTexture($this->tagLine($tag));
    }

    public function doFloor(Line $line, callable $dest, int $dir, ?int $speed = null, bool $crush = false): bool
    {
        $ok = false;
        $spd = $speed ?? Defs::FLOORSPEED;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $f = new FloorMove($s, $dir, $dest($s), $spd);
            $f->crush = $crush;
            $s->specialdata = $f;
            $this->thinkers[] = $f;
            $ok = true;
        }
        return $ok;
    }

    public function doCeiling(Line $line, callable $dest, int $dir = -1, ?int $speed = null, bool $crush = false): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $c = new CeilingMove($s, $dir, $dest($s), $speed ?? Defs::CEILSPEED);
            $c->crush = $crush;
            $s->specialdata = $c;
            $this->thinkers[] = $c;
            $ok = true;
        }
        return $ok;
    }

    public function doStairs(Line $line, int $step, int $speed): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $height = $s->floorheight + $step;
            $f = new FloorMove($s, 1, $height, $speed);
            $s->specialdata = $f;
            $this->thinkers[] = $f;
            $ok = true;
            $texture = $s->floorpic;
            $cur = $s;
            while (true) {
                $next = null;
                foreach ($cur->lines as $ln) {
                    if (!($ln->flags & Defs::ML_TWOSIDED)) {
                        continue;
                    }
                    $o = $ln->frontsector === $cur ? $ln->backsector : $ln->frontsector;
                    if ($o && $o !== $cur && $o->floorpic === $texture && !$o->specialdata) {
                        $next = $o;
                        break;
                    }
                }
                if (!$next) {
                    break;
                }
                $height += $step;
                $f = new FloorMove($next, 1, $height, $speed);
                $next->specialdata = $f;
                $this->thinkers[] = $f;
                $cur = $next;
            }
        }
        return $ok;
    }

    public function spawnSpecials(): void
    {
        foreach ($this->world->sectors as $s) {
            $sp = $s->special;
            if ($sp === 1) {
                $this->spawnLightFlash($s);
            } elseif ($sp === 2) {
                $this->spawnStrobe($s, Defs::FASTDARK, false);
            } elseif ($sp === 3) {
                $this->spawnStrobe($s, Defs::SLOWDARK, false);
            } elseif ($sp === 4) {
                $this->spawnStrobe($s, Defs::FASTDARK, false);
                $s->special = 4;
            } elseif ($sp === 8) {
                $this->spawnGlow($s);
            } elseif ($sp === 10) {
                $this->spawnDoorCloseIn30($s);
            } elseif ($sp === 12) {
                $this->spawnStrobe($s, Defs::SLOWDARK, true);
            } elseif ($sp === 13) {
                $this->spawnStrobe($s, Defs::FASTDARK, true);
            } elseif ($sp === 14) {
                $this->spawnDoorRaiseIn5($s);
            } elseif ($sp === 17) {
                $this->spawnFireFlicker($s);
            }
        }
        foreach ($this->world->lines as $ln) {
            if ($ln->special === 48) {
                $this->scrollLines[] = $ln;
            }
        }
    }

    private function pRandom(): int
    {
        return Enemy::publicRandom();
    }

    public function doCrusher(Line $line, int $ctype): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $top = $s->ceilingheight;
            $bottom = $s->floorheight;
            $crush = $ctype !== Defs::CEIL_RAISETOHIGHEST;
            $speed = Defs::CEILSPEED * ($ctype === Defs::CEIL_FASTCRUSH ? 2 : 1);
            $dir = -1;
            $dest = $bottom;
            if ($ctype === Defs::CEIL_RAISETOHIGHEST) {
                $dest = self::highestCeiling($s);
                $dir = 1;
                $crush = false;
            } elseif ($ctype !== Defs::CEIL_LOWERTOFLOOR) {
                $bottom += 8 * Defs::FRACUNIT;
                $dest = $bottom;
            }
            $c = new CeilingMove($s, $dir, $dest, $speed);
            $c->crush = $crush;
            $c->ctype = $ctype;
            $c->topheight = $top;
            $c->bottomheight = $bottom;
            $s->specialdata = $c;
            $this->thinkers[] = $c;
            $ok = true;
        }
        return $ok;
    }

    public function doDonut(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s1) {
            if ($s1->specialdata || !$s1->lines) {
                continue;
            }
            $ln = $s1->lines[0];
            $s2 = $ln->frontsector === $s1 ? $ln->backsector : $ln->frontsector;
            if (!$s2) {
                continue;
            }
            $s3 = null;
            foreach ($s2->lines as $edge) {
                if ($edge->backsector && $edge->backsector !== $s1) {
                    $s3 = $edge->backsector;
                    break;
                }
            }
            if (!$s3) {
                continue;
            }
            if ($this->startFloor($s2, $s3->floorheight, 1, intdiv(Defs::FLOORSPEED, 2), false, $s3->floorpic)) {
                $ok = true;
            }
            if ($this->startFloor($s1, $s3->floorheight, -1, intdiv(Defs::FLOORSPEED, 2))) {
                $ok = true;
            }
        }
        return $ok;
    }

    private function startFloor(Sector $s, int $dest, int $dir, int $speed, bool $crush = false, ?int $pic = null): bool
    {
        if ($s->specialdata) {
            return false;
        }
        $f = new FloorMove($s, $dir, $dest, $speed);
        $f->crush = $crush;
        $f->floorpic = $pic;
        $s->specialdata = $f;
        $this->thinkers[] = $f;
        return true;
    }

    public function doPlatPerpetual(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $low = self::lowestFloor($s);
            $high = self::highestFloor($s);
            $p = new Plat($s, Defs::PLAT_PERPETUAL, $this->pRandom() & 1, Defs::PLATSPEED, min($low, $s->floorheight), max($high, $s->floorheight), Defs::PLATWAIT * Defs::TICRATE);
            $s->specialdata = $p;
            $this->thinkers[] = $p;
            $this->sound->play('pstart');
            $ok = true;
        }
        return $ok;
    }

    public function doPlatRaise(Line $line, int $amount = 0): bool
    {
        $ok = false;
        $pic = $line->sides[0]?->sector?->floorpic;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $high = $amount ? $s->floorheight + $amount : self::nextHighestFloor($s, $s->floorheight);
            if ($pic !== null) {
                $s->floorpic = $pic;
            }
            $p = new Plat($s, Defs::PLAT_DWUS, Defs::PLAT_UP, intdiv(Defs::PLATSPEED, 2), $s->floorheight, $high, 0);
            $s->specialdata = $p;
            $this->thinkers[] = $p;
            $this->sound->play('pstart');
            $ok = true;
        }
        return $ok;
    }

    public function stopPlat(Line $line): bool
    {
        $ok = false;
        foreach ($this->thinkers as $t) {
            if ($t instanceof Plat && !$t->dead && $t->sector->tag === $line->tag) {
                $t->status = Defs::PLAT_WAITING;
                $t->count = 0x7fffffff;
                $ok = true;
            }
        }
        return $ok;
    }

    public function raiseToTexture(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $min = 0x7fffffff;
            foreach ($s->lines as $ln) {
                if (!($ln->flags & Defs::ML_TWOSIDED)) {
                    continue;
                }
                foreach ($ln->sides as $side) {
                    if ($side && $side->bottomtexture > 0) {
                        $h = $this->res->textureHeight($side->bottomtexture);
                        if ($h > 0 && $h < $min) {
                            $min = $h;
                        }
                    }
                }
            }
            if ($min === 0x7fffffff) {
                $min = 64 * Defs::FRACUNIT;
            }
            if ($this->startFloor($s, $s->floorheight + $min, 1, Defs::FLOORSPEED)) {
                $ok = true;
            }
        }
        return $ok;
    }

    public function lowerAndChange(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $dest = self::lowestFloor($s);
            $pic = $s->floorpic;
            foreach (self::surroundingSectors($s) as $o) {
                if ($o->floorheight === $dest) {
                    $pic = $o->floorpic;
                    break;
                }
            }
            if ($this->startFloor($s, $dest, -1, Defs::FLOORSPEED, false, $pic)) {
                $ok = true;
            }
        }
        return $ok;
    }

    public function lightTurnOn(Line $line, int $bright): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            $s->lightlevel = $bright ?: self::maxSurroundingLight($s);
            $ok = true;
        }
        return $ok;
    }

    public function turnTagLightsOff(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            $s->lightlevel = self::minSurroundingLight($s, $s->lightlevel);
            $ok = true;
        }
        return $ok;
    }

    public function startLightStrobing(Line $line): bool
    {
        $ok = false;
        foreach ($this->sectorsFromTag($line->tag) as $s) {
            if ($s->specialdata) {
                continue;
            }
            $this->spawnStrobe($s, Defs::SLOWDARK, false);
            $ok = true;
        }
        return $ok;
    }

    private function spawnLightFlash(Sector $s): void
    {
        $s->special = 0;
        $l = new LightThinker($s, 'flash');
        $l->maxlight = $s->lightlevel;
        $l->minlight = self::minSurroundingLight($s, $s->lightlevel);
        $l->count = ($this->pRandom() & $l->maxtime) + 1;
        $this->lights[] = $l;
    }

    private function spawnStrobe(Sector $s, int $dark, bool $sync): void
    {
        $s->special = 0;
        $min = self::minSurroundingLight($s, $s->lightlevel);
        $l = new LightThinker($s, 'strobe');
        $l->maxlight = $s->lightlevel;
        $l->minlight = $min === $s->lightlevel ? 0 : $min;
        $l->darktime = $dark;
        $l->brighttime = Defs::STROBEBRIGHT;
        $l->count = $sync ? 1 : ($this->pRandom() & 7) + 1;
        $this->lights[] = $l;
    }

    private function spawnGlow(Sector $s): void
    {
        $s->special = 0;
        $l = new LightThinker($s, 'glow');
        $l->maxlight = $s->lightlevel;
        $l->minlight = self::minSurroundingLight($s, $s->lightlevel);
        $this->lights[] = $l;
    }

    private function spawnFireFlicker(Sector $s): void
    {
        $s->special = 0;
        $l = new LightThinker($s, 'fire');
        $l->maxlight = $s->lightlevel;
        $l->minlight = self::minSurroundingLight($s, $s->lightlevel) + 16;
        $l->count = 4;
        $this->lights[] = $l;
    }

    private function spawnDoorCloseIn30(Sector $s): void
    {
        if ($s->specialdata) {
            return;
        }
        $s->special = 0;
        $d = new VerticalDoor($s, Defs::VLD_CLOSE, 0, $s->ceilingheight, Defs::VDOORSPEED, Defs::VDOORWAIT, 30 * Defs::TICRATE);
        $s->specialdata = $d;
        $this->thinkers[] = $d;
    }

    private function spawnDoorRaiseIn5(Sector $s): void
    {
        if ($s->specialdata) {
            return;
        }
        $s->special = 0;
        $d = new VerticalDoor($s, Defs::VLD_RAISEIN5, 0, self::lowestCeiling($s) - 4 * Defs::FRACUNIT, Defs::VDOORSPEED, Defs::VDOORWAIT, 5 * 60 * Defs::TICRATE);
        $s->specialdata = $d;
        $this->thinkers[] = $d;
    }

    private function tickLights(): void
    {
        foreach ($this->lights as $l) {
            if ($l->kind === 'glow') {
                if ($l->direction === -1) {
                    $l->sector->lightlevel -= Defs::GLOWSPEED;
                    if ($l->sector->lightlevel <= $l->minlight) {
                        $l->sector->lightlevel += Defs::GLOWSPEED;
                        $l->direction = 1;
                    }
                } else {
                    $l->sector->lightlevel += Defs::GLOWSPEED;
                    if ($l->sector->lightlevel >= $l->maxlight) {
                        $l->sector->lightlevel -= Defs::GLOWSPEED;
                        $l->direction = -1;
                    }
                }
                continue;
            }
            if (--$l->count !== 0) {
                continue;
            }
            if ($l->kind === 'flash') {
                if ($l->sector->lightlevel === $l->maxlight) {
                    $l->sector->lightlevel = $l->minlight;
                    $l->count = ($this->pRandom() & $l->mintime) + 1;
                } else {
                    $l->sector->lightlevel = $l->maxlight;
                    $l->count = ($this->pRandom() & $l->maxtime) + 1;
                }
            } elseif ($l->kind === 'strobe') {
                if ($l->sector->lightlevel === $l->minlight) {
                    $l->sector->lightlevel = $l->maxlight;
                    $l->count = $l->brighttime;
                } else {
                    $l->sector->lightlevel = $l->minlight;
                    $l->count = $l->darktime;
                }
            } elseif ($l->kind === 'fire') {
                $amount = ($this->pRandom() & 3) * 16;
                $l->sector->lightlevel = $l->sector->lightlevel - $amount < $l->minlight
                    ? $l->minlight : $l->maxlight - $amount;
                $l->count = 4;
            }
        }
    }

    public function shootSpecial(Line $line, Mobj $thing): void
    {
        $sp = $line->special;
        if ($sp === 24 && $this->doFloor($line, [self::class, 'raiseFloorDest'], 1)) {
            $this->changeSwitch($line, 0);
        } elseif ($sp === 46) {
            $this->doDoor($line, Defs::VLD_OPEN);
            $this->changeSwitch($line, 1);
        } elseif ($sp === 47 && $this->doPlatRaise($line, 0)) {
            $this->changeSwitch($line, 0);
        }
    }

    public function changeSwitch(Line $line, int $again): void
    {
        $side = $line->sides[0];
        if (!$side) {
            return;
        }
        if (!$again) {
            $line->special = 0;
        }
        $sound = $line->special === 11 ? 'swtchx' : 'swtchn';
        foreach (['top', 'mid', 'bottom'] as $where) {
            $prop = $where . 'texture';
            $tex = $side->$prop;
            if (array_key_exists($tex, $this->switchMap)) {
                if ($again) {
                    $this->buttons[] = new Button($line, $where, $tex, Defs::BUTTONTIME);
                }
                $side->$prop = $this->switchMap[$tex];
                $this->sound->play($sound);
                return;
            }
        }
        $this->sound->play($sound);
    }

    public function useSpecial(Line $line, Mobj $thing, int $side): void
    {
        if ($side !== 0) {
            return;
        }
        $sp = $line->special;
        if (in_array($sp, [1, 26, 27, 28, 31, 32, 33, 34, 117, 118], true)) {
            $this->verticalDoor($line, $thing);
            return;
        }
        if (in_array($sp, [99, 133, 134, 135, 136, 137], true)) {
            $this->lockedBlazeDoor($line, $thing, $sp);
            return;
        }
        if ($sp === 11 || $sp === 51) {
            $this->changeSwitch($line, 0);
            $this->exitRequested = true;
            if ($sp === 51) {
                $this->secretExit = true;
            }
            return;
        }
        $once = [
            29 => fn () => $this->doDoor($line, Defs::VLD_NORMAL),
            50 => fn () => $this->doDoor($line, Defs::VLD_CLOSE),
            103 => fn () => $this->doDoor($line, Defs::VLD_OPEN),
            111 => fn () => $this->doDoor($line, Defs::VLD_BLAZERAISE),
            112 => fn () => $this->doDoor($line, Defs::VLD_BLAZEOPEN),
            113 => fn () => $this->doDoor($line, Defs::VLD_BLAZECLOSE),
            21 => fn () => $this->doPlatDwus($line),
            122 => fn () => $this->doPlatDwus($line, true),
            18 => fn () => $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1),
            23 => fn () => $this->doFloor($line, [self::class, 'lowestFloor'], -1),
            71 => fn () => $this->doFloor($line, [self::class, 'highestFloor'], -1),
            101 => fn () => $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1),
            102 => fn () => $this->doFloor($line, [self::class, 'highestFloor'], -1),
            7 => fn () => $this->doStairs($line, 8 * Defs::FRACUNIT, intdiv(Defs::FLOORSPEED, 4)),
            127 => fn () => $this->doStairs($line, 16 * Defs::FRACUNIT, Defs::FLOORSPEED * 4),
            41 => fn () => $this->doCrusher($line, Defs::CEIL_LOWERTOFLOOR),
            49 => fn () => $this->doCrusher($line, Defs::CEIL_CRUSHANDRAISE),
            9 => fn () => $this->doDonut($line),
            14 => fn () => $this->doPlatRaise($line, 32 * Defs::FRACUNIT),
            15 => fn () => $this->doPlatRaise($line, 24 * Defs::FRACUNIT),
            20 => fn () => $this->doPlatRaise($line, 0),
            55 => fn () => $this->doFloor($line, [self::class, 'raiseFloorCrushDest'], 1, null, true),
            101 => fn () => $this->doFloor($line, [self::class, 'raiseFloorDest'], 1),
            131 => fn () => $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1, Defs::FLOORSPEED * 4),
            140 => fn () => $this->doFloor($line, fn ($s) => $s->floorheight + 512 * Defs::FRACUNIT, 1),
        ];
        $repeat = [
            42 => fn () => $this->doDoor($line, Defs::VLD_CLOSE),
            61 => fn () => $this->doDoor($line, Defs::VLD_OPEN),
            63 => fn () => $this->doDoor($line, Defs::VLD_NORMAL),
            62 => fn () => $this->doPlatDwus($line),
            114 => fn () => $this->doDoor($line, Defs::VLD_BLAZERAISE),
            115 => fn () => $this->doDoor($line, Defs::VLD_BLAZEOPEN),
            116 => fn () => $this->doDoor($line, Defs::VLD_BLAZECLOSE),
            120 => fn () => $this->doPlatDwus($line, true),
            123 => fn () => $this->doPlatDwus($line, true),
            45 => fn () => $this->doFloor($line, [self::class, 'highestFloor'], -1),
            60 => fn () => $this->doFloor($line, [self::class, 'lowestFloor'], -1),
            64 => fn () => $this->doFloor($line, [self::class, 'raiseFloorDest'], 1),
            70 => fn () => $this->doFloor($line, [self::class, 'highestFloor'], -1, Defs::FLOORSPEED * 4),
            43 => fn () => $this->doCrusher($line, Defs::CEIL_LOWERTOFLOOR),
            65 => fn () => $this->doFloor($line, [self::class, 'raiseFloorCrushDest'], 1, null, true),
            66 => fn () => $this->doPlatRaise($line, 24 * Defs::FRACUNIT),
            67 => fn () => $this->doPlatRaise($line, 32 * Defs::FRACUNIT),
            68 => fn () => $this->doPlatRaise($line, 0),
            69 => fn () => $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1),
            132 => fn () => $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1, Defs::FLOORSPEED * 4),
            138 => fn () => $this->lightTurnOn($line, 255),
            139 => fn () => $this->lightTurnOn($line, 35),
        ];
        if (isset($once[$sp])) {
            if ($once[$sp]()) {
                $this->changeSwitch($line, 0);
            }
        } elseif (isset($repeat[$sp]) && $repeat[$sp]()) {
            $this->changeSwitch($line, 1);
        }
    }

    public function crossSpecial(Line $line, int $side, Mobj $thing): void
    {
        $sp = $line->special;
        $clear = true;
        if ($sp === 2) {
            $this->doDoor($line, Defs::VLD_OPEN);
        } elseif ($sp === 3) {
            $this->doDoor($line, Defs::VLD_CLOSE);
        } elseif ($sp === 4) {
            $this->doDoor($line, Defs::VLD_NORMAL);
        } elseif ($sp === 5) {
            $this->doFloor($line, [self::class, 'raiseFloorDest'], 1);
        } elseif ($sp === 6) {
            $this->doCrusher($line, Defs::CEIL_FASTCRUSH);
        } elseif ($sp === 8) {
            $this->doStairs($line, 8 * Defs::FRACUNIT, intdiv(Defs::FLOORSPEED, 4));
        } elseif ($sp === 10) {
            $this->doPlatDwus($line);
        } elseif ($sp === 12) {
            $this->lightTurnOn($line, 0);
        } elseif ($sp === 13) {
            $this->lightTurnOn($line, 255);
        } elseif ($sp === 16) {
            $this->doDoor($line, Defs::VLD_CLOSE30, true);
        } elseif ($sp === 17) {
            $this->startLightStrobing($line);
        } elseif ($sp === 19) {
            $this->doFloor($line, [self::class, 'highestFloor'], -1);
        } elseif ($sp === 22) {
            $this->doPlatRaise($line, 0);
        } elseif ($sp === 25) {
            $this->doCrusher($line, Defs::CEIL_CRUSHANDRAISE);
        } elseif ($sp === 30) {
            $this->raiseToTexture($line);
        } elseif ($sp === 35) {
            $this->lightTurnOn($line, 35);
        } elseif ($sp === 36) {
            $this->doFloor($line, [self::class, 'highestFloor'], -1, Defs::FLOORSPEED * 4);
        } elseif ($sp === 37) {
            $this->lowerAndChange($line);
        } elseif ($sp === 38) {
            $this->doFloor($line, [self::class, 'lowestFloor'], -1);
        } elseif ($sp === 39) {
            $this->teleport($line, $side, $thing);
        } elseif ($sp === 40) {
            $this->doCrusher($line, Defs::CEIL_RAISETOHIGHEST);
            $this->doFloor($line, [self::class, 'lowestFloor'], -1);
        } elseif ($sp === 44) {
            $this->doCrusher($line, Defs::CEIL_LOWERANDCRUSH);
        } elseif ($sp === 52) {
            $this->exitRequested = true;
            $clear = false;
        } elseif ($sp === 53) {
            $this->doPlatPerpetual($line);
        } elseif ($sp === 54) {
            $this->stopPlat($line);
        } elseif ($sp === 56) {
            $this->doFloor($line, [self::class, 'raiseFloorCrushDest'], 1, null, true);
        } elseif ($sp === 57) {
            $this->stopPlat($line);
        } elseif ($sp === 58) {
            $this->doFloor($line, fn ($s) => $s->floorheight + 24 * Defs::FRACUNIT, 1);
        } elseif ($sp === 59) {
            $this->doFloor($line, fn ($s) => $s->floorheight + 24 * Defs::FRACUNIT, 1);
        } elseif ($sp === 72) {
            $this->doCrusher($line, Defs::CEIL_LOWERANDCRUSH);
            $clear = false;
        } elseif ($sp === 73) {
            $this->doCrusher($line, Defs::CEIL_CRUSHANDRAISE);
            $clear = false;
        } elseif ($sp === 77) {
            $this->doCrusher($line, Defs::CEIL_FASTCRUSH);
            $clear = false;
        } elseif ($sp === 79) {
            $this->lightTurnOn($line, 35);
            $clear = false;
        } elseif ($sp === 80) {
            $this->lightTurnOn($line, 0);
            $clear = false;
        } elseif ($sp === 81) {
            $this->lightTurnOn($line, 255);
            $clear = false;
        } elseif ($sp === 82) {
            $this->doFloor($line, [self::class, 'lowestFloor'], -1);
            $clear = false;
        } elseif ($sp === 87) {
            $this->doPlatPerpetual($line);
            $clear = false;
        } elseif ($sp === 86) {
            $this->doDoor($line, Defs::VLD_OPEN);
            $clear = false;
        } elseif ($sp === 88) {
            $this->doPlatDwus($line);
            $clear = false;
        } elseif ($sp === 90) {
            $this->doDoor($line, Defs::VLD_NORMAL);
            $clear = false;
        } elseif ($sp === 91) {
            $this->doFloor($line, [self::class, 'raiseFloorDest'], 1);
            $clear = false;
        } elseif ($sp === 92) {
            $this->doFloor($line, fn ($s) => $s->floorheight + 24 * Defs::FRACUNIT, 1);
            $clear = false;
        } elseif ($sp === 94) {
            $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1, null, true);
            $clear = false;
        } elseif ($sp === 97) {
            $this->teleport($line, $side, $thing);
            $clear = false;
        } elseif ($sp === 98) {
            $this->doFloor($line, [self::class, 'highestFloor'], -1, Defs::FLOORSPEED * 4);
            $clear = false;
        } elseif ($sp === 100) {
            $this->doStairs($line, 16 * Defs::FRACUNIT, Defs::FLOORSPEED * 4);
        } elseif ($sp === 104) {
            $this->turnTagLightsOff($line);
        } elseif ($sp === 105) {
            $this->doDoor($line, Defs::VLD_BLAZERAISE);
            $clear = false;
        } elseif ($sp === 106) {
            $this->doDoor($line, Defs::VLD_BLAZEOPEN);
            $clear = false;
        } elseif ($sp === 107) {
            $this->doDoor($line, Defs::VLD_BLAZECLOSE);
            $clear = false;
        } elseif ($sp === 108) {
            $this->doDoor($line, Defs::VLD_BLAZERAISE);
        } elseif ($sp === 109) {
            $this->doDoor($line, Defs::VLD_BLAZEOPEN);
        } elseif ($sp === 110) {
            $this->doDoor($line, Defs::VLD_BLAZECLOSE);
        } elseif ($sp === 119) {
            $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1);
        } elseif ($sp === 120) {
            $this->doPlatDwus($line, true);
            $clear = false;
        } elseif ($sp === 121) {
            $this->doPlatDwus($line, true);
        } elseif ($sp === 124) {
            $this->exitRequested = $this->secretExit = true;
            $clear = false;
        } elseif ($sp === 125) {
            if ($thing->player === null) {
                $this->teleport($line, $side, $thing);
            }
        } elseif ($sp === 126) {
            if ($thing->player === null) {
                $this->teleport($line, $side, $thing);
            }
            $clear = false;
        } elseif ($sp === 128) {
            $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1);
            $clear = false;
        } elseif ($sp === 129) {
            $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1, Defs::FLOORSPEED * 4);
            $clear = false;
        } elseif ($sp === 130) {
            $this->doFloor($line, fn ($s) => self::nextHighestFloor($s, $s->floorheight), 1, Defs::FLOORSPEED * 4);
        } elseif ($sp === 141) {
            $this->doCrusher($line, Defs::CEIL_SILENTCRUSH);
        } else {
            $clear = false;
        }
        if ($clear) {
            $line->special = 0;
        }
    }

    /** EV_Teleport (p_telept.prg): walk special 39 onto MT_TELEPORTMAN (thing 14). */
    private function teleport(Line $line, int $side, Mobj $thing): void
    {
        if ($side === 1 || ($thing->flags & Defs::MF_MISSILE) !== 0) {
            return;
        }
        $tag = $line->tag;
        foreach ($this->world->sectors as $i => $sector) {
            if ($sector->tag !== $tag) {
                continue;
            }
            foreach ($this->world->mobjs as $dest) {
                if ($dest->type !== Info::MT_TELEPORTMAN) {
                    continue;
                }
                $destSector = Collision::pointInSubsector($this->world, $dest->x, $dest->y)->sector;
                if ($destSector !== $sector && $destSector->iSector !== $i) {
                    continue;
                }
                $thing->momx = $thing->momy = $thing->momz = 0;
                Collision::unsetThingPosition($this->world, $thing);
                $thing->x = $dest->x;
                $thing->y = $dest->y;
                $ss = Collision::pointInSubsector($this->world, $thing->x, $thing->y);
                $thing->floorz = $ss->sector->floorheight;
                $thing->ceilingz = $ss->sector->ceilingheight;
                $thing->z = $thing->floorz;
                $thing->angle = $dest->angle;
                Collision::setThingPosition($this->world, $thing);
                if ($thing->player !== null) {
                    $thing->player->viewz = $thing->z + $thing->player->viewheight;
                    $thing->reactiontime = 18;
                }
                $this->sound->play('telept');
                return;
            }
        }
    }
}
