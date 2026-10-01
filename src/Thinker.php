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
 *
 * P_SetMobjState / P_MobjThinker / P_SpawnMobj (p_mobj.prg).
 */
declare(strict_types=1);

namespace Doom;

final class Thinker
{
    public const ONFLOORZ = -0x80000000;
    public const ONCEILINGZ = 0x7FFFFFFF;

    /** @var list<int>|null */
    private static ?array $sargTics = null;
    /** @var array<int, int>|null */
    private static ?array $shotSpeed = null;

    public static function spawnMobj(World $world, int $x, int $y, int $z, int $typ, ?object $game = null): Mobj
    {
        Info::boot();
        $info = Info::$liveMobjinfo[$typ];
        $sub = Collision::pointInSubsector($world, $x, $y);
        $mo = new Mobj(
            x: $x,
            y: $y,
            z: 0,
            radius: (int) $info[Info::MI_RADIUS],
            height: (int) $info[Info::MI_HEIGHT],
            floorz: $sub->sector->floorheight,
            ceilingz: $sub->sector->ceilingheight,
            flags: (int) $info[Info::MI_FLAGS],
            health: (int) $info[Info::MI_SPAWNHEALTH],
            type: $typ,
        );
        $mo->doomednum = (int) $info[Info::MI_DOOMEDNUM];
        $mo->damage = (int) $info[Info::MI_DAMAGE];
        $mo->alive = true;
        if ($game !== null && ($game->skill ?? Defs::SK_MEDIUM) !== Defs::SK_NIGHTMARE) {
            $mo->reactiontime = (int) $info[Info::MI_REACTIONTIME];
        }
        $mo->lastlook = Enemy::publicRandom() % 4;
        $flags = (int) $info[Info::MI_FLAGS];
        if ($z === self::ONCEILINGZ || (($flags & Defs::MF_SPAWNCEILING) && $z === self::ONFLOORZ)) {
            $mo->z = $mo->ceilingz - $mo->height;
        } elseif ($z === self::ONFLOORZ) {
            $mo->z = $mo->floorz;
        } else {
            $mo->z = $z;
        }
        $world->mobjs[] = $mo;
        Collision::setThingPosition($world, $mo);
        self::setMobjState($mo, (int) $info[Info::MI_SPAWNSTATE], $world, $game);
        return $mo;
    }

    public static function removeMobj(World $world, Mobj $mo): void
    {
        Collision::unsetThingPosition($world, $mo);
        $mo->alive = false;
        $mo->istate = Info::S_NULL;
        $mo->flags = 0;
        $i = array_search($mo, $world->mobjs, true);
        if ($i !== false) {
            array_splice($world->mobjs, (int) $i, 1);
        }
    }

    public static function setMobjState(Mobj $mo, int $state, World $world, ?object $game): bool
    {
        $safety = 0;
        while (true) {
            if ($state === Info::S_NULL) {
                self::removeMobj($world, $mo);
                return false;
            }
            $st = Info::$liveStates[$state];
            $mo->istate = $state;
            $mo->tics = (int) $st[2];
            $mo->sprite = Info::SPRNAMES[(int) $st[0]];
            $mo->frame = (int) $st[1];
            $act = Info::ACTIONS[(int) $st[3]];
            if ($act !== '') {
                Enemy::callAction($act, $mo, $world, $game);
                if (!$mo->alive) {
                    return false;
                }
            }
            $state = (int) $st[4];
            if ($mo->tics !== 0) {
                return true;
            }
            if (++$safety > 100) {
                return true;
            }
        }
    }

    public static function mobjThinker(World $world, Mobj $mo, object $game): void
    {
        if ($mo->momx || $mo->momy || ($mo->flags & Defs::MF_SKULLFLY)) {
            Enemy::pXyMovement($world, $mo, $game);
            if (!$mo->alive) {
                return;
            }
        }
        if ($mo->z !== $mo->floorz || $mo->momz) {
            Enemy::mobjZ($mo, $world, $game);
            if (!$mo->alive) {
                return;
            }
        }
        if ($mo->tics !== -1) {
            --$mo->tics;
            if ($mo->tics <= 0) {
                self::setMobjState($mo, (int) Info::$liveStates[$mo->istate][4], $world, $game);
            }
            return;
        }
        if (($mo->flags & Defs::MF_COUNTKILL) === 0) {
            return;
        }
        if (!($game->respawnmonsters ?? false)) {
            return;
        }
        ++$mo->movecount;
        if ($mo->movecount < 12 * Defs::TICRATE) {
            return;
        }
        if ((($game->leveltime ?? 0) & 31) !== 0) {
            return;
        }
        if (Enemy::publicRandom() > 4) {
            return;
        }
        self::nightmareRespawn($world, $mo, $game);
    }

    public static function nightmareRespawn(World $world, Mobj $mo, object $game): void
    {
        $sp = $mo->spawnpoint;
        if ($sp === null) {
            return;
        }
        $x = $sp->x * Defs::FRACUNIT;
        $y = $sp->y * Defs::FRACUNIT;
        $chk = Collision::checkPosition($world, $mo, $x, $y);
        if ($chk->blocked) {
            return;
        }
        self::spawnMobj($world, $mo->x, $mo->y, $mo->floorz, Info::MT_TFOG, $game);
        $game->startSound('telept');
        $sub = Collision::pointInSubsector($world, $x, $y);
        self::spawnMobj($world, $x, $y, $sub->sector->floorheight, Info::MT_TFOG, $game);
        $game->startSound('telept');
        $z = ((int) Info::$liveMobjinfo[$mo->type][Info::MI_FLAGS] & Defs::MF_SPAWNCEILING)
            ? self::ONCEILINGZ : self::ONFLOORZ;
        $spawned = self::spawnMobj($world, $x, $y, $z, $mo->type, $game);
        $spawned->spawnpoint = $sp;
        $spawned->angle = Compat::asU32(intdiv($sp->angle, 45) * 0x20000000);
        if ($sp->options & Defs::MTF_AMBUSH) {
            $spawned->flags |= Defs::MF_AMBUSH;
        }
        $spawned->reactiontime = 18;
        self::removeMobj($world, $mo);
    }

    public static function applyFast(object $game): void
    {
        Info::boot();
        $want = (bool) (($game->fastparm ?? false) || $game->skill === Defs::SK_NIGHTMARE);
        $game->respawnmonsters = $game->skill === Defs::SK_NIGHTMARE || (bool) ($game->respawnparm ?? false);
        if (($game->_fastOn ?? null) === $want) {
            return;
        }
        if (self::$sargTics === null) {
            self::$sargTics = [];
            for ($i = Info::S_SARG_RUN1; $i <= Info::S_SARG_PAIN2; $i++) {
                self::$sargTics[] = (int) Info::$liveStates[$i][2];
            }
            self::$shotSpeed = [
                Info::MT_BRUISERSHOT => (int) Info::$liveMobjinfo[Info::MT_BRUISERSHOT][Info::MI_SPEED],
                Info::MT_HEADSHOT => (int) Info::$liveMobjinfo[Info::MT_HEADSHOT][Info::MI_SPEED],
                Info::MT_TROOPSHOT => (int) Info::$liveMobjinfo[Info::MT_TROOPSHOT][Info::MI_SPEED],
            ];
        }
        $game->_fastOn = $want;
        foreach (self::$sargTics as $i => $base) {
            $st = Info::S_SARG_RUN1 + $i;
            Info::$liveStates[$st][2] = $want ? max(1, intdiv($base, 2)) : $base;
        }
        $fast = 20 * Defs::FRACUNIT;
        foreach (self::$shotSpeed as $mt => $spd) {
            Info::$liveMobjinfo[$mt][Info::MI_SPEED] = $want ? $fast : $spd;
        }
    }
}
