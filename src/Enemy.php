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

final class Enemy
{
    private const DI_EAST = 0;
    private const DI_NE = 1;
    private const DI_NORTH = 2;
    private const DI_NW = 3;
    private const DI_WEST = 4;
    private const DI_SW = 5;
    private const DI_SOUTH = 6;
    private const DI_SE = 7;
    private const DI_NODIR = 8;

    private const XSPEED = [
        Defs::FRACUNIT, 47000, 0, -47000, -Defs::FRACUNIT, -47000, 0, 47000,
    ];
    private const YSPEED = [
        0, 47000, Defs::FRACUNIT, 47000, 0, -47000, -Defs::FRACUNIT, -47000,
    ];
    private const OPPOSITE = [4, 5, 6, 7, 0, 1, 2, 3, 8];
    private const DIAGS = [self::DI_NW, self::DI_NE, self::DI_SW, self::DI_SE];

    private static function profiles(): array
    {
        return [
            3004 => [8, 'hitscan', 'posit1', 'podth1', 'pistol', 7, 5, 4],
            9 => [8, 'shotgun', 'posit2', 'podth2', 'shotgn', 7, 5, 3],
            3001 => [8, 'imp', 'bgsit1', 'bgdth1', 'claw', 7, 6, 3],
            3002 => [10, 'melee', 'sgtsit', 'sgtdth', 'sgtatk', 7, 6, 2],
            58 => [10, 'melee', 'sgtsit', 'sgtdth', 'sgtatk', 7, 6, 2],
            3003 => [8, 'baron', 'brssit', 'brsdth', 'claw', 7, 7, 3],
            3005 => [8, 'caco', 'cacsit', 'cacdth', 'claw', 5, 6, 3],
            3006 => [8, 'skull', 'sklatk', 'firxpl', 'sklatk', 5, 6, 3],
            16 => [16, 'rocket', 'cybsit', 'cybdth', 'rlaunc', 5, 9, 3],
            7 => [12, 'spider', 'spisit', 'spidth', 'shotgn', 5, 10, 3],
            68 => [12, 'plasma', 'bspsit', 'bspdth', 'plasma', 5, 7, 3],
            65 => [8, 'chaingun', 'posit2', 'podth2', 'shotgn', 7, 7, 3],
            69 => [8, 'baron', 'kntsit', 'kntdth', 'claw', 7, 7, 3],
            84 => [8, 'hitscan', 'posit1', 'podth1', 'pistol', 7, 5, 3],
            64 => [15, 'vile', 'vilsit', 'vildth', 'vilatk', 7, 9, 2],
            66 => [10, 'revenant', 'skesit', 'skedth', 'skeswg', 7, 5, 2],
            67 => [8, 'mancubus', 'mansit', 'mandth', 'manatk', 7, 7, 3],
            71 => [8, 'pain', 'pesit', 'pedth', 'pesit', 5, 6, 3],
            72 => [0, 'keen', 'keenpn', 'keendt', null, 0, 6, 4],
            88 => [0, 'brain', 'bossit', 'bosdth', null, 0, 3, 4],
            2035 => [0, 'none', null, 'barexp', null, 0, 5, 4],
        ];
    }

    private const TRACEANGLE = 0xC000000;
    private static int $brainTargetOn = 0;

    /** m_random rndtable. P_Random increments first, so index 0 is never the first draw. */
    private const RNDTABLE = [
        0, 8, 109, 220, 222, 241, 149, 107, 75, 248, 254, 140, 16, 66,
        74, 21, 211, 47, 80, 242, 154, 27, 205, 128, 161, 89, 77, 36,
        95, 110, 85, 48, 212, 140, 211, 249, 22, 79, 200, 50, 28, 188,
        52, 140, 202, 120, 68, 145, 62, 70, 184, 190, 91, 197, 152, 224,
        149, 104, 25, 178, 252, 182, 202, 182, 141, 197, 4, 81, 181, 242,
        145, 42, 39, 227, 156, 198, 225, 193, 219, 93, 122, 175, 249, 0,
        175, 143, 70, 239, 46, 246, 163, 53, 163, 109, 168, 135, 2, 235,
        25, 92, 20, 145, 138, 77, 69, 166, 78, 176, 173, 212, 166, 113,
        94, 161, 41, 50, 239, 49, 111, 164, 70, 60, 2, 37, 171, 75,
        136, 156, 11, 56, 42, 146, 138, 229, 73, 146, 77, 61, 98, 196,
        135, 106, 63, 197, 195, 86, 96, 203, 113, 101, 170, 247, 181, 113,
        80, 250, 108, 7, 255, 237, 129, 226, 79, 107, 112, 166, 103, 241,
        24, 223, 239, 120, 198, 58, 60, 82, 128, 3, 184, 66, 143, 224,
        145, 224, 81, 206, 163, 45, 63, 90, 168, 114, 59, 33, 159, 95,
        28, 139, 123, 98, 125, 196, 15, 70, 194, 253, 54, 14, 109, 226,
        71, 17, 161, 93, 186, 87, 244, 138, 20, 52, 123, 251, 26, 36,
        17, 46, 52, 231, 232, 76, 31, 221, 84, 37, 216, 165, 212, 106,
        197, 242, 98, 43, 39, 175, 254, 145, 190, 84, 118, 222, 187, 136,
        120, 163, 236, 249,
    ];
    private static int $prndindex = 0;

    private static function random(): int
    {
        self::$prndindex = (self::$prndindex + 1) & 255;
        return self::RNDTABLE[self::$prndindex];
    }

    public static function clearRandom(): void
    {
        self::$prndindex = 0;
    }

    private static function walkTics(Mobj $mo): int
    {
        return self::profiles()[$mo->type][7] ?? 3;
    }

    public static function tickEnemies(World $world, object $game): void
    {
        $p = $game->player;
        if (!$p || !$p->mo) {
            return;
        }
        foreach (array_values($world->mobjs) as $mo) {
            if ($mo === $p->mo) {
                continue;
            }
            Thinker::mobjThinker($world, $mo, $game);
        }
    }

    private static function tickStand(Mobj $mo): void
    {
        if ($mo->tics > 0) {
            --$mo->tics;
            return;
        }
        $mo->frame = ($mo->frame + 1) % ($mo->type === 3005 ? 1 : 2);
        $mo->tics = 10;
    }

    public static function killMonster(Mobj $mo, object $game, ?Mobj $source): void
    {
        Info::boot();
        $info = Info::$liveMobjinfo[$mo->type];
        $mo->flags &= ~(Defs::MF_SHOOTABLE | Defs::MF_FLOAT | Defs::MF_SKULLFLY);
        $mo->flags |= Defs::MF_CORPSE | Defs::MF_DROPOFF;
        $mo->height >>= 2;
        if ($source?->player && ($mo->flags & Defs::MF_COUNTKILL)) {
            ++$source->player->killcount;
        }
        $xds = (int) $info[Info::MI_XDEATHSTATE];
        $st = ($mo->health < -(int) $info[Info::MI_SPAWNHEALTH] && $xds)
            ? $xds : (int) $info[Info::MI_DEATHSTATE];
        Thinker::setMobjState($mo, $st, $game->world, $game);
        if ($mo->alive) {
            $mo->tics -= self::random() & 3;
            if ($mo->tics < 1) {
                $mo->tics = 1;
            }
            $drop = match ($mo->type) {
                Info::MT_POSSESSED => Info::MT_CLIP,
                Info::MT_SHOTGUY => Info::MT_SHOTGUN,
                Info::MT_CHAINGUY => Info::MT_CHAINGUN,
                default => null,
            };
            if ($drop !== null) {
                $item = Thinker::spawnMobj($game->world, $mo->x, $mo->y, Thinker::ONFLOORZ, $drop, $game);
                $item->flags |= Defs::MF_DROPPED;
            }
        }
    }

    private static function tickDead(World $world, Mobj $mo, object $game): void
    {
        if ($mo->aiState !== 'die') {
            return;
        }
        if ($mo->tics > 0) {
            --$mo->tics;
            return;
        }
        $prof = self::profiles()[$mo->type] ?? null;
        [$d0, $dn] = $prof ? [$prof[5], $prof[6]] : [7, 5];
        if ($mo->sprite === 'BEXP') {
            [$d0, $dn] = [0, 5];
        }
        if ($mo->frame + 1 < $d0 + $dn) {
            ++$mo->frame;
            $mo->tics = 5;
            return;
        }
        $mo->aiState = 'dead';
        if ($mo->type === 72) {
            self::keenDie($world, $mo, $game);
        } elseif (in_array($mo->type, [67, 68, 3003, 16, 7], true)) {
            self::bossDeath($world, $mo, $game);
        }
        // Vanilla BEXP ends in S_NULL — remove the barrel or the last blast frame stays on screen.
        if ($mo->sprite === 'BEXP') {
            $i = array_search($mo, $world->mobjs, true);
            if ($i !== false) {
                array_splice($world->mobjs, $i, 1);
            }
        }
    }

    public static function noiseAlert(World $world, ?Mobj $emitter, ?object $game = null): void
    {
        if (!$emitter) {
            return;
        }
        $sec = Collision::pointInSubsector($world, $emitter->x, $emitter->y)->sector;
        ++$world->validcount;
        self::recursiveSound($world, $sec, 0, $emitter);
    }

    private static function recursiveSound(World $world, Sector $sec, int $blocks, Mobj $target): void
    {
        if ($sec->validcount === $world->validcount && $sec->soundtraversed <= $blocks + 1) {
            return;
        }
        $sec->validcount = $world->validcount;
        $sec->soundtraversed = $blocks + 1;
        $sec->soundtarget = $target;
        foreach ($sec->lines as $ln) {
            if (!($ln->flags & Defs::ML_TWOSIDED)) {
                continue;
            }
            [$top, $bottom] = Collision::lineOpening($ln);
            if ($top - $bottom <= 0) {
                continue;
            }
            $other = $ln->frontsector === $sec ? $ln->backsector : $ln->frontsector;
            if (!$other) {
                continue;
            }
            if ($ln->flags & Defs::ML_SOUNDBLOCK) {
                if ($blocks === 0) {
                    self::recursiveSound($world, $other, 1, $target);
                }
            } else {
                self::recursiveSound($world, $other, $blocks, $target);
            }
        }
    }

    private static function look(World $world, Mobj $mo, Mobj $player, object $game): void
    {
        $mo->threshold = 0;
        if (!($player->flags & Defs::MF_SHOOTABLE)) {
            return;
        }
        $see = false;
        $sec = Collision::pointInSubsector($world, $mo->x, $mo->y)->sector;
        $target = $sec->soundtarget;
        if ($target && ($target->flags & Defs::MF_SHOOTABLE)) {
            $mo->target = $target;
            $see = ($mo->flags & Defs::MF_AMBUSH)
                ? Collision::checkSight($world, $mo, $target)
                : true;
        }
        if (!$see && !self::lookForPlayer($world, $mo, $player)) {
            return;
        }
        $mo->movedir = self::DI_NODIR;
        $mo->movecount = 0;
        Info::boot();
        $seeSfx = Info::$liveMobjinfo[$mo->type][Info::MI_SEESOUND] ?? '';
        if ($seeSfx === 'posit1' || $seeSfx === 'posit2' || $seeSfx === 'posit3') {
            $seeSfx = ['posit1', 'posit2', 'posit3'][self::random() % 3];
        } elseif ($seeSfx === 'bgsit1' || $seeSfx === 'bgsit2') {
            $seeSfx = ['bgsit1', 'bgsit2'][self::random() % 2];
        }
        if ($seeSfx) {
            $game->startSound((string) $seeSfx);
        }
        Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SEESTATE], $world, $game);
    }

    private static function lookForPlayer(World $world, Mobj $mo, Mobj $p, bool $allaround = false): bool
    {
        if ($p->health <= 0) {
            return false;
        }
        $c = 0;
        $stop = ($mo->lastlook - 1) & 3;
        while (true) {
            if ($mo->lastlook !== 0) {
                $mo->lastlook = ($mo->lastlook + 1) & 3;
                continue;
            }
            if ($c === 2 || $mo->lastlook === $stop) {
                return false;
            }
            $c++;
            if ($p->health <= 0 || !($p->flags & Defs::MF_SHOOTABLE)) {
                $mo->lastlook = ($mo->lastlook + 1) & 3;
                continue;
            }
            if (!Collision::checkSight($world, $mo, $p)) {
                $mo->lastlook = ($mo->lastlook + 1) & 3;
                continue;
            }
            if (!$allaround) {
                $angle = Compat::asU32(Collision::angleTo($mo->x, $mo->y, $p->x, $p->y) - $mo->angle);
                if ($angle > Defs::ANG90 && $angle < Defs::ANG270
                    && Collision::approxDistance($p->x - $mo->x, $p->y - $mo->y) > Defs::MELEERANGE) {
                    $mo->lastlook = ($mo->lastlook + 1) & 3;
                    continue;
                }
            }
            $mo->target = $p;
            return true;
        }
    }

    private static function chase(World $world, Mobj $mo, Mobj $player, object $game): void
    {
        if ($mo->reactiontime) {
            --$mo->reactiontime;
        }
        if ($mo->threshold) {
            if ($mo->target === null || $mo->target->health <= 0) {
                $mo->threshold = 0;
            } else {
                --$mo->threshold;
            }
        }
        if ($mo->movedir < 8) {
            self::faceMoveDir($mo);
        }
        $target = $mo->target;
        if (!$target || $target->health <= 0 || !($target->flags & Defs::MF_SHOOTABLE)) {
            if (!self::lookForPlayer($world, $mo, $player, true)) {
                Info::boot();
                Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SPAWNSTATE], $world, $game);
            }
            return;
        }
        if ($mo->flags & Defs::MF_JUSTATTACKED) {
            $mo->flags &= ~Defs::MF_JUSTATTACKED;
            if ($game->skill !== Defs::SK_NIGHTMARE && !($game->fastparm ?? false)) {
                self::newChaseDir($world, $mo, $game);
            }
            return;
        }
        $dist = Collision::approxDistance($target->x - $mo->x, $target->y - $mo->y);
        Info::boot();
        $info = Info::$liveMobjinfo[$mo->type];
        $meleeSt = (int) $info[Info::MI_MELEESTATE];
        $missSt = (int) $info[Info::MI_MISSILESTATE];
        $speed = (int) $info[Info::MI_SPEED];
        if ($meleeSt && $dist < Defs::MELEERANGE - 20 * Defs::FRACUNIT + $target->radius
            && Collision::checkSight($world, $mo, $target)) {
            $sfx = $info[Info::MI_ATTACKSOUND] ?? '';
            if ($sfx) {
                $game->startSound((string) $sfx);
            }
            Thinker::setMobjState($mo, $meleeSt, $world, $game);
            return;
        }
        if ($missSt && ($game->skill === Defs::SK_NIGHTMARE || ($game->fastparm ?? false) || $mo->movecount === 0)
            && self::missileOk($world, $mo, $target, $dist, (bool) $meleeSt)) {
            Thinker::setMobjState($mo, $missSt, $world, $game);
            $mo->flags |= Defs::MF_JUSTATTACKED;
            return;
        }
        if (--$mo->movecount < 0 || !self::move($world, $mo, $speed, $game)) {
            self::newChaseDir($world, $mo, $game);
        }
        $act = $info[Info::MI_ACTIVESOUND] ?? '';
        if ($act && self::random() < 3) {
            $game->startSound((string) $act);
        }
    }

    private static function missileOk(World $w, Mobj $mo, Mobj $target, int $dist, bool $melee): bool
    {
        if ($mo->flags & Defs::MF_JUSTHIT) {
            $mo->flags &= ~Defs::MF_JUSTHIT;
            return true;
        }
        if (!Collision::checkSight($w, $mo, $target) || $mo->reactiontime) {
            return false;
        }
        $d = $dist - 64 * Defs::FRACUNIT;
        if (!$melee) {
            $d -= 128 * Defs::FRACUNIT;
        }
        $d >>= 16;
        if ($mo->type === Info::MT_VILE && $d > 14 * 64) {
            return false;
        }
        if ($mo->type === Info::MT_UNDEAD) {
            if ($d < 196) {
                return false;
            }
            $d >>= 1;
        }
        if ($mo->type === Info::MT_CYBORG || $mo->type === Info::MT_SPIDER || $mo->type === Info::MT_SKULL) {
            $d >>= 1;
        }
        if ($d > 200) {
            $d = 200;
        }
        if ($mo->type === Info::MT_CYBORG && $d > 160) {
            $d = 160;
        }
        return self::random() >= $d;
    }

    private static function startAttack(Mobj $mo, string $kind): void
    {
        $mo->aiState = 'attack';
        $mo->tics = 26;
        $mo->frame = 4;
        $mo->attackKind = $kind;
        $mo->didFire = false;
    }

    private static function tickAttack(World $world, Mobj $mo, object $game): void
    {
        $t = $mo->target;
        if (!$t || $t->health <= 0) {
            $mo->aiState = 'look';
            $mo->frame = 0;
            $mo->tics = 10;
            return;
        }
        self::faceTarget($mo, $t);
        if (!$mo->didFire && $mo->tics <= 16) {
            self::doAttack($world, $mo, $game);
            $mo->didFire = true;
            $mo->frame = 5;
        }
        if (--$mo->tics <= 0) {
            $kind = $mo->attackKind;
            if (in_array($kind, ['chaingun', 'spider'], true)
                && self::refireOk($world, $mo, $kind === 'chaingun' ? 40 : 10)) {
                $mo->tics = 8;
                $mo->didFire = false;
                return;
            }
            $mo->aiState = 'chase';
            $mo->frame = 0;
            $mo->movecount = 15 + (self::random() & 15);
        }
    }

    private static function doAttack(World $world, Mobj $mo, object $game): void
    {
        $kind = $mo->attackKind;
        self::faceTarget($mo, $mo->target);
        if ($kind === 'melee') {
            if (Collision::approxDistance($mo->target->x - $mo->x, $mo->target->y - $mo->y)
                < Defs::MELEERANGE + $mo->radius) {
                $game->damageMobj($mo->target, $mo, (self::random() % 8 + 1) * 3);
            }
        } elseif ($kind === 'shotgun') {
            $game->startSound('shotgn');
            $faced = $mo->angle;
            $slope = Collision::aimSlope($world, $mo, $faced, Defs::MISSILERANGE);
            for ($i = 0; $i < 3; ++$i) {
                $mo->angle = Compat::asU32($faced + ((self::random() - self::random()) << 20));
                Collision::lineAttack($world, $mo, (self::random() % 5 + 1) * 3, $game, Defs::MISSILERANGE, $mo->angle, $slope);
            }
            $mo->angle = $faced;
        } elseif (in_array($kind, ['imp', 'baron', 'caco'], true)) {
            if (Collision::approxDistance($mo->target->x - $mo->x, $mo->target->y - $mo->y)
                < Defs::MELEERANGE + $mo->radius) {
                $game->startSound('claw');
                $game->damageMobj($mo->target, $mo, (self::random() % 8 + 1) * 3);
            } else {
                $game->startSound('firsht');
                self::spawnMissile(
                    $world,
                    $mo,
                    $mo->target,
                    $kind === 'baron' ? 'BAL2' : 'BAL1',
                    ($kind === 'baron' ? 15 : 10) * Defs::FRACUNIT,
                    $kind === 'baron' ? 8 : 3,
                    'ball'
                );
            }
        } elseif ($kind === 'rocket') {
            $game->startSound('rlaunc');
            self::spawnMissile($world, $mo, $mo->target, 'MISL', 20 * Defs::FRACUNIT, 20, 'rocket');
        } elseif ($kind === 'plasma') {
            $game->startSound('plasma');
            self::spawnMissile($world, $mo, $mo->target, 'APLS', 25 * Defs::FRACUNIT, 5, 'plasma');
        } elseif ($kind === 'skull') {
            self::skullAttack($mo, $game);
        } elseif ($kind === 'chaingun') {
            $game->startSound('shotgn');
            $faced = $mo->angle;
            $slope = Collision::aimSlope($world, $mo, $faced, Defs::MISSILERANGE);
            $mo->angle = Compat::asU32($faced + ((self::random() - self::random()) << 20));
            Collision::lineAttack($world, $mo, (self::random() % 5 + 1) * 3, $game, Defs::MISSILERANGE, $mo->angle, $slope);
            $mo->angle = $faced;
        } elseif ($kind === 'spider') {
            $game->startSound('shotgn');
            $faced = $mo->angle;
            $slope = Collision::aimSlope($world, $mo, $faced, Defs::MISSILERANGE);
            for ($i = 0; $i < 3; ++$i) {
                $mo->angle = Compat::asU32($faced + ((self::random() - self::random()) << 20));
                Collision::lineAttack($world, $mo, (self::random() % 5 + 1) * 3, $game, Defs::MISSILERANGE, $mo->angle, $slope);
            }
            $mo->angle = $faced;
        } elseif ($kind === 'revenant') {
            if (Collision::approxDistance($mo->target->x - $mo->x, $mo->target->y - $mo->y)
                < Defs::MELEERANGE + $mo->radius) {
                $game->startSound('skeswg');
                $game->damageMobj($mo->target, $mo, (self::random() % 8 + 1) * 6);
            } else {
                $game->startSound('skeatk');
                $miss = self::spawnMissile($world, $mo, $mo->target, 'FATB', 10 * Defs::FRACUNIT, 10, 'tracer');
                $miss->tracer = $mo->target;
            }
        } elseif ($kind === 'mancubus') {
            $game->startSound('firsht');
            $spread = intdiv(Defs::ANG90, 8);
            foreach ([-$spread, 0, $spread] as $da) {
                self::spawnMissile(
                    $world,
                    $mo,
                    $mo->target,
                    'MANF',
                    20 * Defs::FRACUNIT,
                    8,
                    'fat',
                    Compat::asU32($mo->angle + $da)
                );
            }
        } elseif ($kind === 'pain') {
            $game->startSound('sklatk');
            self::painShootSkull($world, $mo, $game, $mo->angle);
        } elseif ($kind === 'vile') {
            self::vileAttack($world, $mo, $game);
        } else {
            $game->startSound('pistol');
            $faced = $mo->angle;
            $slope = Collision::aimSlope($world, $mo, $faced, Defs::MISSILERANGE);
            $mo->angle = Compat::asU32($faced + ((self::random() - self::random()) << 20));
            Collision::lineAttack($world, $mo, (self::random() % 5 + 1) * 3, $game, Defs::MISSILERANGE, $mo->angle, $slope);
            $mo->angle = $faced;
        }
    }

    private static function refireOk(World $world, Mobj $mo, int $keep): bool
    {
        if (self::random() < $keep) {
            return true;
        }
        $t = $mo->target;
        return $t && $t->health > 0 && Collision::checkSight($world, $mo, $t);
    }

    private static function skullAttack(Mobj $mo, object $game): void
    {
        $dest = $mo->target;
        if (!$dest) {
            return;
        }
        $mo->flags |= Defs::MF_SKULLFLY;
        $game->startSound('sklatk');
        self::faceTarget($mo, $dest);
        $mo->momx = Compat::fixedMul(Defs::SKULLSPEED, Tables::fineCos($mo->angle));
        $mo->momy = Compat::fixedMul(Defs::SKULLSPEED, Tables::fineSin($mo->angle));
        $dist = Collision::approxDistance($dest->x - $mo->x, $dest->y - $mo->y);
        $steps = max(1, Defs::SKULLSPEED ? intdiv($dist, Defs::SKULLSPEED) : 1);
        $mo->momz = (int) (($dest->z + ($dest->height >> 1) - $mo->z) / $steps);
    }

    private static function tickSkullFly(World $world, Mobj $mo, object $game): void
    {
        if ($mo->momx === 0 && $mo->momy === 0) {
            $mo->flags &= ~Defs::MF_SKULLFLY;
            $mo->momz = 0;
            Info::boot();
            Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SPAWNSTATE], $world, $game);
            return;
        }
        if (!Collision::tryMove($world, $mo, $mo->x + $mo->momx, $mo->y + $mo->momy, $game)) {
            if ($mo->flags & Defs::MF_SKULLFLY) {
                $mo->momx = $mo->momy = 0;
            }
            return;
        }
        $mo->z += $mo->momz;
        if ($mo->z <= $mo->floorz || $mo->z + $mo->height > $mo->ceilingz) {
            $mo->flags &= ~Defs::MF_SKULLFLY;
            $mo->momx = $mo->momy = $mo->momz = 0;
            Info::boot();
            Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SPAWNSTATE], $world, $game);
        }
    }

    private static function faceTarget(Mobj $mo, Mobj $t): void
    {
        $mo->angle = Collision::angleTo($mo->x, $mo->y, $t->x, $t->y);
        if ($t->flags & Defs::MF_SHADOW) {
            $mo->angle = Compat::asU32($mo->angle + (self::random() - self::random()) * 2097152);
        }
    }

    private static function faceMoveDir(Mobj $mo): void
    {
        if ($mo->movedir < 0 || $mo->movedir >= 8) {
            return;
        }
        $mo->angle = Compat::asU32($mo->angle & 3758096384);
        $want = Compat::asU32($mo->movedir * Defs::ANG45);
        $delta = Compat::asI32($mo->angle - $want);
        if ($delta > 0) {
            $mo->angle = Compat::asU32($mo->angle - Defs::ANG45);
        } elseif ($delta < 0) {
            $mo->angle = Compat::asU32($mo->angle + Defs::ANG45);
        }
    }

    private static function move(World $world, Mobj $mo, int $speed, object $game): bool
    {
        if ($mo->movedir < 0 || $mo->movedir >= 8) {
            return false;
        }
        $nx = $mo->x + $speed * self::XSPEED[$mo->movedir];
        $ny = $mo->y + $speed * self::YSPEED[$mo->movedir];
        if (!Collision::tryMove($world, $mo, $nx, $ny, $game)) {
            if (($mo->flags & Defs::MF_FLOAT) && Collision::$floatOk) {
                if ($mo->z < Collision::$tmFloorZ) {
                    $mo->z += 4 * Defs::FRACUNIT;
                } else {
                    $mo->z -= 4 * Defs::FRACUNIT;
                }
                $mo->flags |= Defs::MF_INFLOAT;
                return true;
            }
            if (Collision::$lastSpechit === []) {
                return false;
            }
            $mo->movedir = self::DI_NODIR;
            for ($i = count(Collision::$lastSpechit) - 1; $i >= 0; $i--) {
                $ln = Collision::$lastSpechit[$i];
                if ($ln->special) {
                    $game->useSpecial($ln, $mo, 0);
                    return true;
                }
            }
            return false;
        }
        $mo->flags &= ~Defs::MF_INFLOAT;
        if (($mo->flags & Defs::MF_FLOAT) === 0) {
            $mo->z = $mo->floorz;
        }
        return true;
    }

    private static function newChaseDir(World $world, Mobj $mo, object $game): void
    {
        $t = $mo->target;
        if (!$t) {
            return;
        }
        $old = $mo->movedir;
        $turn = self::OPPOSITE[$old] ?? self::DI_NODIR;
        $dx = $t->x - $mo->x;
        $dy = $t->y - $mo->y;
        $d2 = $dx > 10 * Defs::FRACUNIT
            ? self::DI_EAST
            : ($dx < -10 * Defs::FRACUNIT ? self::DI_WEST : self::DI_NODIR);
        $d3 = $dy < -10 * Defs::FRACUNIT
            ? self::DI_SOUTH
            : ($dy > 10 * Defs::FRACUNIT ? self::DI_NORTH : self::DI_NODIR);
        Info::boot();
        $speed = (int) Info::$liveMobjinfo[$mo->type][Info::MI_SPEED];
        if ($d2 !== self::DI_NODIR && $d3 !== self::DI_NODIR) {
            $mo->movedir = self::DIAGS[($dy < 0 ? 2 : 0) + ($dx > 0 ? 1 : 0)];
            if ($mo->movedir !== $turn && self::move($world, $mo, $speed, $game)) {
                $mo->movecount = self::random() & 15;
                return;
            }
        }
        if (self::random() > 200 || abs($dy) > abs($dx)) {
            [$d2, $d3] = [$d3, $d2];
        }
        if ($d2 === $turn) {
            $d2 = self::DI_NODIR;
        }
        if ($d3 === $turn) {
            $d3 = self::DI_NODIR;
        }
        foreach ([$d2, $d3, $old] as $d) {
            if ($d !== self::DI_NODIR) {
                $mo->movedir = $d;
                if (self::move($world, $mo, $speed, $game)) {
                    $mo->movecount = self::random() & 15;
                    return;
                }
            }
        }
        $dirs = (self::random() & 1) ? range(0, 7) : range(7, 0);
        foreach ($dirs as $d) {
            if ($d !== $turn) {
                $mo->movedir = $d;
                if (self::move($world, $mo, $speed, $game)) {
                    $mo->movecount = self::random() & 15;
                    return;
                }
            }
        }
        if ($turn !== self::DI_NODIR) {
            $mo->movedir = $turn;
            if (self::move($world, $mo, $speed, $game)) {
                $mo->movecount = self::random() & 15;
                return;
            }
        }
        $mo->movedir = self::DI_NODIR;
        $mo->movecount = self::random() & 15;
    }

    private static function spawnMissile(
        World $world,
        Mobj $src,
        Mobj $dest,
        string $sprite,
        int $speed,
        int $damage,
        string $kind = 'ball',
        ?int $ang = null
    ): Mobj {
        $a = $ang ?? Collision::angleTo($src->x, $src->y, $dest->x, $dest->y);
        if ($ang === null && ($dest->flags & Defs::MF_SHADOW)) {
            $a = Compat::asU32($a + (self::random() - self::random()) * 1048576);
        }
        $typ = match ($kind) {
            'rocket' => Info::MT_ROCKET,
            'plasma' => $sprite === 'APLS' ? Info::MT_ARACHPLAZ : Info::MT_PLASMA,
            'bfg' => Info::MT_BFG,
            'tracer' => Info::MT_TRACER,
            'fat' => Info::MT_FATSHOT,
            'spawncube' => Info::MT_SPAWNSHOT,
            'baron' => Info::MT_BRUISERSHOT,
            'caco' => Info::MT_HEADSHOT,
            default => Info::MT_TROOPSHOT,
        };
        Info::boot();
        $spd = (int) Info::$liveMobjinfo[$typ][Info::MI_SPEED];
        $dist = Collision::approxDistance($dest->x - $src->x, $dest->y - $src->y);
        $steps = max(1, $spd ? intdiv($dist, $spd) : 1);
        $mo = Thinker::spawnMobj($world, $src->x, $src->y, $src->z + 32 * Defs::FRACUNIT, $typ, null);
        $mo->target = $src;
        $mo->angle = $a;
        $mo->momx = Compat::fixedMul($spd, Tables::fineCos($a));
        $mo->momy = Compat::fixedMul($spd, Tables::fineSin($a));
        $mo->momz = (int) (($dest->z - $src->z) / $steps);
        $mo->missileKind = $kind;
        if ($kind === 'tracer') {
            $mo->tracer = $dest;
        }
        self::checkMissileSpawn($mo);
        return $mo;
    }

    /** P_CheckMissileSpawn: burn one P_Random, then nudge out of the shooter. */
    private static function checkMissileSpawn(Mobj $mo): void
    {
        $mo->tics -= self::random() & 3;
        if ($mo->tics < 1) {
            $mo->tics = 1;
        }
        $mo->x += $mo->momx >> 1;
        $mo->y += $mo->momy >> 1;
        $mo->z += $mo->momz >> 1;
    }

    public static function spawnPlayerMissile(
        World $world,
        Mobj $src,
        string $sprite,
        int $speed,
        int $damage,
        string $kind
    ): void {
        $typ = match ($kind) {
            'rocket' => Info::MT_ROCKET,
            'plasma' => Info::MT_PLASMA,
            default => Info::MT_BFG,
        };
        $aim = Collision::missileAim($world, $src);
        $ang = $aim['angle'];
        Info::boot();
        $spd = (int) Info::$liveMobjinfo[$typ][Info::MI_SPEED];
        $mo = Thinker::spawnMobj($world, $src->x, $src->y, $src->z + 32 * Defs::FRACUNIT, $typ, null);
        $mo->target = $src;
        $mo->angle = $ang;
        $mo->momx = Compat::fixedMul($spd, Tables::fineCos($ang));
        $mo->momy = Compat::fixedMul($spd, Tables::fineSin($ang));
        $mo->momz = Compat::fixedMul($spd, $aim['slope']);
        $mo->missileKind = $kind;
        self::checkMissileSpawn($mo);
    }

    private static function tickMissile(World $world, Mobj $mo, object $game): void
    {
        $kind = $mo->missileKind !== '' ? $mo->missileKind : (string) ($mo->info[1] ?? '');
        if ($kind === 'tracer' && (($game->leveltime & 3) === 0)) {
            self::tracerHome($mo);
        }
        if ($kind === 'spawncube') {
            $dest = $mo->tracer;
            $mo->x += $mo->momx;
            $mo->y += $mo->momy;
            $mo->z += $mo->momz;
            if (!$dest || Collision::approxDistance($dest->x - $mo->x, $dest->y - $mo->y) < 24 * Defs::FRACUNIT) {
                self::spawnFly($world, $mo, $game);
            }
            return;
        }
        $nx = $mo->x + $mo->momx;
        $ny = $mo->y + $mo->momy;
        $chk = Collision::checkPosition($world, $mo, $nx, $ny);
        if ($chk->blocked || !Collision::tryMove($world, $mo, $nx, $ny, $game)) {
            self::explodeMissile($world, $mo, $game, $chk->hitThing === $mo ? null : $chk->hitThing);
            return;
        }
        $mo->z += $mo->momz;
        if ($mo->z <= $mo->floorz || $mo->z + $mo->height > $mo->ceilingz) {
            self::explodeMissile($world, $mo, $game, null);
            return;
        }
        $mo->frame = ($mo->frame + 1) & 1;
    }

    private static function radiusAttack(World $world, Mobj $spot, ?Mobj $source, int $damage, object $game): void
    {
        foreach (array_values($world->mobjs) as $o) {
            if ($o === $spot || !($o->flags & Defs::MF_SHOOTABLE) || in_array($o->type, [Info::MT_CYBORG, Info::MT_SPIDER], true)) {
                continue;
            }
            $dx = abs($o->x - $spot->x);
            $dy = abs($o->y - $spot->y);
            $dist = ($dx > $dy ? $dx : $dy) - $o->radius;
            if ($dist < 0) {
                $dist = 0;
            }
            $dist >>= 16;
            if ($dist >= $damage) {
                continue;
            }
            if (Collision::checkSight($world, $o, $spot)) {
                $game->damageMobj($o, $source ?? $spot, $damage - $dist, $spot);
            }
        }
    }

    private static function bfgSpray(World $world, Mobj $ball, object $game): void
    {
        $shooter = $ball->target;
        if (!$shooter) {
            return;
        }
        for ($i = 0; $i < 40; ++$i) {
            $an = Compat::asU32($shooter->angle - intdiv(Defs::ANG90, 2) + intdiv(Defs::ANG90, 40) * $i);
            $target = Collision::aimLineAttack($world, $shooter, $an, 16 * 64 * Defs::FRACUNIT);
            if (!$target) {
                continue;
            }
            $damage = 0;
            for ($j = 0; $j < 15; ++$j) {
                $damage += (self::random() & 7) + 1;
            }
            $game->damageMobj($target, $shooter, $damage, $ball);
        }
    }

    private static function explodeBarrel(World $world, Mobj $barrel, object $game): void
    {
        self::radiusAttack($world, $barrel, $barrel, 128, $game);
    }

    private static function spriteAndFrame(string $name): array
    {
        $n = strtoupper($name);
        $spr = substr($n, 0, 4);
        $frame = (strlen($n) >= 5 && $n[4] >= 'A' && $n[4] <= ']') ? ord($n[4]) - ord('A') : 0;
        return [$spr, $frame];
    }

    private static function tracerHome(Mobj $mo): void
    {
        $dest = $mo->tracer;
        if (!$dest || $dest->health <= 0) {
            return;
        }
        $exact = Collision::angleTo($mo->x, $mo->y, $dest->x, $dest->y);
        $diff = ($exact - $mo->angle) & 0xffffffff;
        if ($diff > 0x80000000) {
            $mo->angle = Compat::asU32($mo->angle - self::TRACEANGLE);
            if ((($exact - $mo->angle) & 0xffffffff) < 0x80000000) {
                $mo->angle = $exact;
            }
        } else {
            $mo->angle = Compat::asU32($mo->angle + self::TRACEANGLE);
            if ((($exact - $mo->angle) & 0xffffffff) > 0x80000000) {
                $mo->angle = $exact;
            }
        }
        $speed = 10 * Defs::FRACUNIT;
        $mo->momx = Compat::fixedMul($speed, Tables::fineCos($mo->angle));
        $mo->momy = Compat::fixedMul($speed, Tables::fineSin($mo->angle));
        $dist = Collision::approxDistance($dest->x - $mo->x, $dest->y - $mo->y);
        $steps = max(1, $speed ? intdiv($dist, $speed) : 1);
        $mo->momz = (int) (($dest->z + 40 * Defs::FRACUNIT - $mo->z) / $steps);
    }

    private static function vileChase(World $world, Mobj $mo, object $game): bool
    {
        foreach ($world->mobjs as $other) {
            if ($other === $mo || !($other->flags & Defs::MF_CORPSE)) {
                continue;
            }
            if ($other->aiState !== 'dead' || $other->health > 0) {
                continue;
            }
            $prof = self::profiles()[$other->type] ?? null;
            if (!$prof || in_array($prof[1], ['none', 'keen', 'brain', 'skull'], true)) {
                continue;
            }
            $maxdist = $mo->radius + $other->radius;
            if (abs($other->x - $mo->x) > $maxdist || abs($other->y - $mo->y) > $maxdist) {
                continue;
            }
            $info = Mobj::infoTable()[$other->type] ?? null;
            if (!$info) {
                continue;
            }
            [$sprite, $rad, $h, $health, $flags, $kind, $extra] = $info;
            $other->flags = $flags | Defs::MF_SHOOTABLE | Defs::MF_SOLID;
            if ($other->type !== 2035) {
                $other->flags |= Defs::MF_COUNTKILL;
            }
            $other->health = $health ?: 1000;
            $other->height = $h * Defs::FRACUNIT;
            $other->radius = $rad * Defs::FRACUNIT;
            $other->aiState = 'look';
            $other->tics = 10;
            $other->target = null;
            $other->alive = true;
            $other->z = $other->floorz;
            [$spr4, $sprframe] = self::spriteAndFrame($sprite);
            $other->sprite = $spr4;
            $other->frame = $sprframe;
            $game->startSound('slop');
            if ($extra) {
                $game->startSound($extra);
            }
            return true;
        }
        return false;
    }

    private static function vileAttack(World $world, Mobj $mo, object $game): void
    {
        $dest = $mo->target;
        if (!$dest || !Collision::checkSight($world, $mo, $dest)) {
            return;
        }
        $game->startSound('vilatk');
        $game->damageMobj($dest, $mo, 20);
        self::radiusAttack($world, $dest, $mo, 70, $game);
    }

    private static function painShootSkull(World $world, Mobj $actor, object $game, int $ang): void
    {
        $n = 0;
        foreach ($world->mobjs as $other) {
            if ($other->type === Info::MT_SKULL && $other->health > 0) {
                ++$n;
            }
        }
        if ($n >= 21) {
            return;
        }
        $pre = 4 * Defs::FRACUNIT + intdiv(3 * $actor->radius, 2);
        $x = $actor->x + Compat::fixedMul($pre, Tables::fineCos($ang));
        $y = $actor->y + Compat::fixedMul($pre, Tables::fineSin($ang));
        $skull = Thinker::spawnMobj($world, $x, $y, $actor->z, Info::MT_SKULL, $game);
        $skull->angle = $ang;
        $chk = Collision::checkPosition($world, $skull, $skull->x, $skull->y);
        if ($chk->blocked) {
            $game->damageMobj($skull, $actor, 10000, $actor);
            return;
        }
        $skull->target = $actor->target;
        self::skullAttack($skull, $game);
    }

    private static function painDie(World $world, Mobj $mo, object $game): void
    {
        foreach ([Defs::ANG90, Defs::ANG90 * 2, Defs::ANG270] as $da) {
            self::painShootSkull($world, $mo, $game, Compat::asU32($mo->angle + $da));
        }
    }

    private static function aliveOfType(World $world, int $typ): bool
    {
        foreach ($world->mobjs as $other) {
            if ($other->type === $typ && $other->health > 0) {
                return true;
            }
        }
        return false;
    }

    private static function commercial(object $game): bool
    {
        return $game->wad->checkNumForName('MAP01') >= 0;
    }

    private static function bossDeath(World $world, Mobj $mo, object $game): void
    {
        if (self::aliveOfType($world, $mo->type)) {
            return;
        }
        $spec = $game->specials;
        if (self::commercial($game) && $game->mapn === 7) {
            if ($mo->type === Info::MT_FATSO) {
                $spec->doFloorTag(666, [Specials::class, 'lowestFloor'], -1);
            } elseif ($mo->type === Info::MT_BABY) {
                $spec->raiseToTextureTag(667);
            }
            return;
        }
        if (self::commercial($game)) {
            return;
        }
        if ($game->episode === 1 && $game->mapn === 8 && $mo->type === Info::MT_BRUISER) {
            $spec->doFloorTag(666, [Specials::class, 'lowestFloor'], -1);
        } elseif ($game->episode === 2 && $game->mapn === 8 && $mo->type === Info::MT_CYBORG) {
            $spec->exitRequested = true;
        } elseif ($game->episode === 3 && $game->mapn === 8 && $mo->type === Info::MT_SPIDER) {
            $spec->exitRequested = true;
        } elseif ($game->episode === 4 && $game->mapn === 6 && $mo->type === Info::MT_CYBORG) {
            $spec->doFloorTag(666, [Specials::class, 'lowestFloor'], -1);
        } elseif ($game->episode === 4 && $game->mapn === 8 && $mo->type === Info::MT_BRUISER) {
            $spec->doFloorTag(666, [Specials::class, 'lowestFloor'], -1);
        }
    }

    private static function keenDie(World $world, Mobj $mo, object $game): void
    {
        if (self::aliveOfType($world, Info::MT_KEEN)) {
            return;
        }
        $game->specials->doDoorTag(666, Defs::VLD_BLAZEOPEN);
    }

    private static function brainDie(object $game): void
    {
        $game->specials->exitRequested = true;
    }

    private static function tickBrainEye(World $world, Mobj $mo, object $game): void
    {
        if ($mo->tics > 0) {
            --$mo->tics;
            return;
        }
        $mo->tics = 150;
        self::brainSpit($world, $mo, $game);
    }

    private static function brainSpit(World $world, Mobj $actor, object $game): void
    {
        if (($game->skill ?? 2) <= Defs::SK_EASY) {
            $actor->easySkip = !($actor->easySkip ?? false);
            if ($actor->easySkip) {
                return;
            }
        }
        $targs = [];
        foreach ($world->mobjs as $m) {
            if ($m->type === Info::MT_BOSSTARGET) {
                $targs[] = $m;
            }
        }
        if (!$targs) {
            return;
        }
        $dest = $targs[self::$brainTargetOn % count($targs)];
        ++self::$brainTargetOn;
        $game->startSound('bospit');
        $miss = self::spawnMissile($world, $actor, $dest, 'BOSF', 10 * Defs::FRACUNIT, 0, 'spawncube');
        $miss->tracer = $dest;
        $miss->flags |= Defs::MF_NOCLIP;
    }

    private static function spawnFly(World $world, Mobj $cube, object $game): void
    {
        $dest = $cube->target ?? $cube;
        $r = self::random();
        if ($r < 50) {
            $typ = Info::MT_TROOP;
        } elseif ($r < 90) {
            $typ = Info::MT_SERGEANT;
        } elseif ($r < 120) {
            $typ = Info::MT_SHADOWS;
        } elseif ($r < 130) {
            $typ = Info::MT_PAIN;
        } elseif ($r < 160) {
            $typ = Info::MT_HEAD;
        } elseif ($r < 162) {
            $typ = Info::MT_VILE;
        } elseif ($r < 172) {
            $typ = Info::MT_UNDEAD;
        } elseif ($r < 192) {
            $typ = Info::MT_BABY;
        } elseif ($r < 222) {
            $typ = Info::MT_FATSO;
        } elseif ($r < 246) {
            $typ = Info::MT_KNIGHT;
        } else {
            $typ = Info::MT_BRUISER;
        }
        $game->startSound('telept');
        $spawned = Thinker::spawnMobj($world, $dest->x, $dest->y, $dest->z, $typ, $game);
        $spawned->angle = $dest->angle;
        Thinker::removeMobj($world, $cube);
    }

    private static function spawnType(World $world, int $typ, int $x, int $y, int $z, int $angle): ?Mobj
    {
        $info = Mobj::infoTable()[$typ] ?? null;
        if (!$info) {
            return null;
        }
        [$sprite, $rad, $h, $health, $flags, $kind, $extra] = $info;
        if ($kind === 'enemy' && $typ !== 2035) {
            $flags |= Defs::MF_COUNTKILL;
        }
        [$spr4, $sprframe] = self::spriteAndFrame($sprite);
        $sub = Collision::pointInSubsector($world, $x, $y);
        $mo = new Mobj(
            x: $x,
            y: $y,
            z: $z ?: $sub->sector->floorheight,
            angle: Compat::asU32($angle),
            radius: $rad * Defs::FRACUNIT,
            height: $h * Defs::FRACUNIT,
            floorz: $sub->sector->floorheight,
            ceilingz: $sub->sector->ceilingheight,
            flags: $flags,
            health: $health ?: 1000,
            type: $typ,
            sprite: $spr4,
            info: [$kind, $extra],
            aiState: $kind === 'enemy' ? 'look' : '',
            frame: $sprframe,
            tics: $kind === 'enemy' ? 10 : 0,
            reactiontime: $kind === 'enemy' ? 8 : 0
        );
        if ($typ === 72) {
            $mo->z = $mo->ceilingz - $mo->height;
        }
        $world->mobjs[] = $mo;
        return $mo;
    }

    public static function publicRandom(): int
    {
        return self::random();
    }

    private static function trunc2(int $n): int
    {
        $n = Compat::asI32($n);
        if ($n < 0) {
            return -intdiv(-$n, 2);
        }
        return intdiv($n, 2);
    }

    /** P_XYMovement: half-steps above MAXMOVE/2, then friction on the floor. */
    public static function pXyMovement(World $world, Mobj $mo, object $game): void
    {
        if ($mo->momx === 0 && $mo->momy === 0) {
            if ($mo->flags & Defs::MF_SKULLFLY) {
                $mo->flags &= ~Defs::MF_SKULLFLY;
                $mo->momx = $mo->momy = $mo->momz = 0;
                Info::boot();
                Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SPAWNSTATE], $world, $game);
            }
            return;
        }
        if ($mo->momx > Defs::MAXMOVE) {
            $mo->momx = Defs::MAXMOVE;
        } elseif ($mo->momx < -Defs::MAXMOVE) {
            $mo->momx = -Defs::MAXMOVE;
        }
        if ($mo->momy > Defs::MAXMOVE) {
            $mo->momy = Defs::MAXMOVE;
        } elseif ($mo->momy < -Defs::MAXMOVE) {
            $mo->momy = -Defs::MAXMOVE;
        }
        $xmove = $mo->momx;
        $ymove = $mo->momy;
        $half = intdiv(Defs::MAXMOVE, 2);
        while ($xmove !== 0 || $ymove !== 0) {
            if ($xmove > $half || $ymove > $half) {
                $ptryx = $mo->x + self::trunc2($xmove);
                $ptryy = $mo->y + self::trunc2($ymove);
                $xmove = self::trunc2($xmove);
                $ymove = self::trunc2($ymove);
            } else {
                $ptryx = $mo->x + $xmove;
                $ptryy = $mo->y + $ymove;
                $xmove = $ymove = 0;
            }
            if ($mo->flags & Defs::MF_NOCLIP) {
                Collision::unsetThingPosition($world, $mo);
                $mo->x = $ptryx;
                $mo->y = $ptryy;
                Collision::setThingPosition($world, $mo);
                continue;
            }
            if (Collision::tryMove($world, $mo, $ptryx, $ptryy, $game)) {
                continue;
            }
            if ($mo->player !== null) {
                Collision::slideMove($world, $mo, $mo->momx, $mo->momy, $game);
            } elseif ($mo->flags & Defs::MF_MISSILE) {
                $line = Collision::$ceilingLine;
                $sky = -1;
                if (isset($game->res) && $game->res !== null) {
                    $sky = $game->res->skyflatnum;
                }
                if ($line !== null && $line->backsector !== null && $line->backsector->ceilingpic === $sky) {
                    Thinker::removeMobj($world, $mo);
                    return;
                }
                self::explodeMissile($world, $mo, $game, null);
                return;
            } else {
                $mo->momx = $mo->momy = 0;
            }
        }
        $player = $mo->player;
        if ($player !== null && ($player->cheats & Defs::CF_NOMOMENTUM)) {
            $mo->momx = $mo->momy = 0;
            return;
        }
        if ($mo->flags & (Defs::MF_MISSILE | Defs::MF_SKULLFLY)) {
            return;
        }
        if ($mo->z > $mo->floorz) {
            return;
        }
        if ($mo->flags & Defs::MF_CORPSE) {
            if (
                $mo->momx > intdiv(Defs::FRACUNIT, 4)
                || $mo->momx < -intdiv(Defs::FRACUNIT, 4)
                || $mo->momy > intdiv(Defs::FRACUNIT, 4)
                || $mo->momy < -intdiv(Defs::FRACUNIT, 4)
            ) {
                $sec = Collision::pointInSubsector($world, $mo->x, $mo->y)->sector;
                if ($mo->floorz !== $sec->floorheight) {
                    return;
                }
            }
        }
        if (
            -Defs::STOPSPEED < $mo->momx && $mo->momx < Defs::STOPSPEED
            && -Defs::STOPSPEED < $mo->momy && $mo->momy < Defs::STOPSPEED
            && ($player === null || ($player->cmd->forwardmove === 0 && $player->cmd->sidemove === 0))
        ) {
            if ($player !== null) {
                $n = $mo->istate - Info::S_PLAY_RUN1;
                if ($n >= 0 && $n < 4) {
                    Info::boot();
                    Thinker::setMobjState($mo, Info::S_PLAY, $world, $game);
                }
            }
            $mo->momx = $mo->momy = 0;
        } else {
            $mo->momx = Compat::fixedMul($mo->momx, Defs::FRICTION);
            $mo->momy = Compat::fixedMul($mo->momy, Defs::FRICTION);
        }
    }

    public static function missileXy(World $world, Mobj $mo, object $game): void
    {
        self::pXyMovement($world, $mo, $game);
    }

    public static function skullXy(World $world, Mobj $mo, object $game): void
    {
        self::pXyMovement($world, $mo, $game);
    }

    public static function groundXy(World $world, Mobj $mo, object $game): void
    {
        self::pXyMovement($world, $mo, $game);
    }

    /** P_ZMovement. Gravity applies only while airborne, and the first tic is doubled. */
    public static function mobjZ(Mobj $mo, World $world, object $game): void
    {
        $player = $mo->player;
        if ($player !== null && $mo->z < $mo->floorz) {
            $player->viewheight -= $mo->floorz - $mo->z;
            $player->deltaviewheight = Compat::shar(Defs::VIEWHEIGHT - $player->viewheight, 3);
        }
        $mo->z += $mo->momz;
        if (($mo->flags & Defs::MF_FLOAT) && $mo->target !== null && !($mo->flags & (Defs::MF_SKULLFLY | Defs::MF_INFLOAT))) {
            $dist = Collision::approxDistance($mo->x - $mo->target->x, $mo->y - $mo->target->y);
            $delta = ($mo->target->z + Compat::shar($mo->height, 1)) - $mo->z;
            if ($delta < 0 && $dist < -($delta * 3)) {
                $mo->z -= 4 * Defs::FRACUNIT;
            } elseif ($delta > 0 && $dist < ($delta * 3)) {
                $mo->z += 4 * Defs::FRACUNIT;
            }
        }
        if ($mo->z <= $mo->floorz) {
            if ($mo->momz < 0) {
                if ($player !== null && $mo->momz < -Defs::GRAVITY * 8) {
                    $player->deltaviewheight = Compat::shar($mo->momz, 3);
                    $game->startSound('oof');
                }
                $mo->momz = 0;
            }
            $mo->z = $mo->floorz;
            if (($mo->flags & Defs::MF_SKULLFLY) && !($mo->flags & Defs::MF_MISSILE)) {
                $mo->momz = -$mo->momz;
            }
            if (($mo->flags & Defs::MF_MISSILE) && !($mo->flags & Defs::MF_NOCLIP)) {
                self::missileFloorHit($world, $mo, $game);
                return;
            }
        } elseif (!($mo->flags & Defs::MF_NOGRAVITY)) {
            if ($mo->momz === 0) {
                $mo->momz = -Defs::GRAVITY * 2;
            } else {
                $mo->momz -= Defs::GRAVITY;
            }
        }
        if ($mo->z + $mo->height > $mo->ceilingz) {
            if ($mo->momz > 0) {
                $mo->momz = 0;
            }
            $mo->z = $mo->ceilingz - $mo->height;
            if ($mo->flags & Defs::MF_SKULLFLY) {
                $mo->momz = -$mo->momz;
            }
            if (($mo->flags & Defs::MF_MISSILE) && !($mo->flags & Defs::MF_NOCLIP)) {
                self::missileFloorHit($world, $mo, $game);
            }
        }
    }

    /**
     * The shot reached the raised floor under the monster after the XY test
     * had already missed. Check again at the snapped height, then explode.
     */
    private static function missileFloorHit(World $world, Mobj $mo, object $game): void
    {
        self::explodeMissile($world, $mo, $game, null);
    }

    /** The blast reached this body, including a shot that died on the floor under it. */
    public static function missileReaches(Mobj $mo, Mobj $other, int $x, int $y, int $z): bool
    {
        if ($other === $mo || $other === $mo->target || $other->health <= 0) {
            return false;
        }
        if (($other->flags & Defs::MF_SHOOTABLE) === 0) {
            return false;
        }
        $reach = $other->radius + $mo->radius;
        if (abs($other->x - $x) >= $reach || abs($other->y - $y) >= $reach) {
            return false;
        }
        $slack = 64 * Defs::FRACUNIT;
        $z0 = $z;
        $z1 = $z + $mo->momz;
        $low = min($z0, $z1) - $slack;
        $high = max($z0, $z1) + $mo->height + $slack;
        if ($z <= $mo->floorz) {
            $low = min($low, $mo->floorz - $slack);
            $high = max($high, $mo->floorz + $slack);
        }
        return $low <= $other->z + $other->height && $high >= $other->z;
    }

    private static function missileVictim(World $world, Mobj $mo): ?Mobj
    {
        $spots = [[$mo->x, $mo->y, $mo->z]];
        if ($mo->tmx !== $mo->x || $mo->tmy !== $mo->y) {
            $spots[] = [$mo->tmx, $mo->tmy, $mo->z];
        }
        $best = null;
        $bestDist = PHP_INT_MAX;
        foreach ($world->mobjs as $other) {
            foreach ($spots as [$x, $y, $z]) {
                if (!self::missileReaches($mo, $other, $x, $y, $z)) {
                    continue;
                }
                $dist = max(abs($other->x - $x), abs($other->y - $y));
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $other;
                }
            }
        }
        return $best;
    }

    private static function explodeMissile(World $world, Mobj $mo, object $game, ?Mobj $hit): void
    {
        $hit = $hit ?? self::missileVictim($world, $mo);
        if ($hit) {
            Info::boot();
            $dmg = $mo->damage ?: (int) Info::$liveMobjinfo[$mo->type][Info::MI_DAMAGE];
            $game->damageMobj($hit, $mo->target ?? $mo, $dmg * ((self::random() % 8) + 1), $mo);
        }
        $mo->momx = $mo->momy = $mo->momz = 0;
        $mo->flags &= ~Defs::MF_MISSILE;
        Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_DEATHSTATE], $world, $game);
    }

    public static function callAction(string $name, Mobj $mo, World $world, ?object $game): void
    {
        $pl = $game->player->mo ?? null;
        Info::boot();
        if ($name === 'Look') {
            if ($pl) {
                self::look($world, $mo, $pl, $game);
            }
        } elseif ($name === 'Chase') {
            if ($pl) {
                self::chase($world, $mo, $pl, $game);
            }
        } elseif ($name === 'VileChase') {
            if (!self::vileChase($world, $mo, $game) && $pl) {
                self::chase($world, $mo, $pl, $game);
            }
        } elseif ($name === 'FaceTarget') {
            if ($mo->target) {
                self::faceTarget($mo, $mo->target);
            }
        } elseif ($name === 'Fall') {
            $mo->flags &= ~Defs::MF_SOLID;
        } elseif ($name === 'Scream') {
            $s = Info::$liveMobjinfo[$mo->type][Info::MI_DEATHSOUND] ?? '';
            if ($s) {
                $game->startSound((string) $s);
            }
        } elseif ($name === 'XScream') {
            $game->startSound('slop');
        } elseif ($name === 'Pain') {
            $s = Info::$liveMobjinfo[$mo->type][Info::MI_PAINSOUND] ?? '';
            if ($s) {
                $game->startSound((string) $s);
            }
        } elseif ($name === 'Explode') {
            self::radiusAttack($world, $mo, $mo->target, 128, $game);
        } elseif (in_array($name, ['PosAttack', 'SPosAttack', 'CPosAttack', 'TroopAttack', 'SargAttack',
            'HeadAttack', 'BruisAttack', 'SkullAttack', 'CyberAttack', 'BspiAttack',
            'PainAttack', 'CPosRefire', 'SpidRefire'], true)) {
            $map = [
                'PosAttack' => 'hitscan', 'SPosAttack' => 'shotgun', 'CPosAttack' => 'chaingun',
                'TroopAttack' => 'imp', 'SargAttack' => 'melee', 'HeadAttack' => 'caco',
                'BruisAttack' => 'baron', 'SkullAttack' => 'skull', 'CyberAttack' => 'rocket',
                'BspiAttack' => 'plasma', 'PainAttack' => 'pain',
            ];
            if (isset($map[$name])) {
                $mo->attackKind = $map[$name];
                self::doAttack($world, $mo, $game);
            } elseif ($name === 'CPosRefire' || $name === 'SpidRefire') {
                if ($mo->target) {
                    self::faceTarget($mo, $mo->target);
                }
                $keep = $name === 'CPosRefire' ? 40 : 10;
                if (self::random() < $keep) {
                    return;
                }
                if (!$mo->target || $mo->target->health <= 0 || !Collision::checkSight($world, $mo, $mo->target)) {
                    Thinker::setMobjState($mo, (int) Info::$liveMobjinfo[$mo->type][Info::MI_SEESTATE], $world, $game);
                }
            }
        } elseif (in_array($name, ['Metal', 'BabyMetal', 'Hoof'], true)) {
            $game->startSound($name === 'Metal' ? 'metal' : ($name === 'Hoof' ? 'hoof' : 'bspwlk'));
            if ($pl) {
                self::chase($world, $mo, $pl, $game);
            }
        } elseif ($name === 'PainDie') {
            $mo->flags &= ~Defs::MF_SOLID;
            self::painDie($world, $mo, $game);
        } elseif ($name === 'KeenDie') {
            $mo->flags &= ~Defs::MF_SOLID;
            self::keenDie($world, $mo, $game);
        } elseif ($name === 'BossDeath') {
            self::bossDeath($world, $mo, $game);
        } elseif ($name === 'VileStart') {
            $game->startSound('vilatk');
        } elseif ($name === 'VileAttack') {
            self::vileAttack($world, $mo, $game);
        } elseif ($name === 'SkelWhoosh') {
            if ($mo->target) {
                self::faceTarget($mo, $mo->target);
            }
            $game->startSound('skeswg');
        } elseif ($name === 'SkelFist' || $name === 'SkelMissile' || $name === 'FatRaise'
            || $name === 'FatAttack1' || $name === 'FatAttack2' || $name === 'FatAttack3') {
            $mo->attackKind = $name === 'SkelFist' ? 'melee' : ($name === 'SkelMissile' ? 'revenant' : 'mancubus');
            if ($name === 'FatRaise') {
                $game->startSound('manatk');
            } else {
                self::doAttack($world, $mo, $game);
            }
        } elseif ($name === 'Tracer') {
            if (($game->leveltime & 3) === 0) {
                self::tracerHome($mo);
            }
        } elseif ($name === 'BrainAwake') {
            $game->startSound('bossit');
        } elseif ($name === 'BrainSpit') {
            self::brainSpit($world, $mo, $game);
        } elseif ($name === 'SpawnSound') {
            $game->startSound('boscub');
            self::aSpawnFly($world, $mo, $game);
        } elseif ($name === 'SpawnFly') {
            self::aSpawnFly($world, $mo, $game);
        } elseif ($name === 'BrainDie') {
            self::brainDie($game);
        } elseif ($name === 'BrainPain') {
            $game->startSound('bospn');
        } elseif ($name === 'BrainScream') {
            $game->startSound('bosdth');
        } elseif ($name === 'BFGSpray') {
            self::bfgSpray($world, $mo, $game);
        }
    }

    private static function aSpawnFly(World $world, Mobj $mo, object $game): void
    {
        --$mo->reactiontime;
        if ($mo->reactiontime !== 0) {
            return;
        }
        self::spawnFly($world, $mo, $game);
    }
}
