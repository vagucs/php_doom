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

final class Ticcmd
{
    public function __construct(
        public int $forwardmove = 0,
        public int $sidemove = 0,
        public int $angleturn = 0,
        public int $buttons = 0,
    ) {}
}

final class Player
{
    public const FORWARDMOVE = [0x19, 0x32];
    public const SIDEMOVE = [0x18, 0x28];
    public const ANGLETURN = [640, 1280, 320];
    public const MAXAMMO = [200, 50, 300, 50];
    public const CLIPAMMO = [10, 4, 20, 1];
    public const WEAPON_AMMO = [
        Defs::WP_PISTOL => Defs::AM_CLIP,
        Defs::WP_SHOTGUN => Defs::AM_SHELL,
        Defs::WP_SUPERSHOTGUN => Defs::AM_SHELL,
        Defs::WP_CHAINGUN => Defs::AM_CLIP,
        Defs::WP_MISSILE => Defs::AM_MISL,
        Defs::WP_PLASMA => Defs::AM_CELL,
        Defs::WP_BFG => Defs::AM_CELL,
    ];

    public ?Mobj $mo;
    public Ticcmd $cmd;
    public int $playerstate = Defs::PST_LIVE;
    public int $viewz = 0;
    public int $viewheight = Defs::VIEWHEIGHT;
    public int $deltaviewheight = 0;
    public int $bob = 0;
    public int $health = 100;
    public int $armorpoints = 0;
    public int $armortype = 0;
    /** @var int[] */ public array $ammo = [50, 0, 0, 0];
    /** @var int[] */ public array $maxammo = self::MAXAMMO;
    /** @var bool[] */ public array $weaponowned = [true, true, false, false, false, false, false, false, false];
    public int $pendingweapon = Defs::WP_NOCHANGE;
    public int $readyweapon = Defs::WP_PISTOL;
    /** @var bool[] */ public array $cards = [false, false, false, false, false, false];
    public int $cheats = 0;
    public string $message = '';
    public int $messageTics = 0;
    public bool $attackdown = false;
    public bool $usedown = false;
    public int $damagecount = 0;
    public int $bonuscount = 0;
    public int $extralight = 0;
    public int $refire = 0;
    public int $killcount = 0;
    public int $itemcount = 0;
    public int $secretcount = 0;
    public bool $didsecret = false;
    public int $pspriteY = 32;
    public int $pspriteSy = 128 * Defs::FRACUNIT;
    public string $pspriteState = 'up';
    public int $pspriteTics = 0;
    public int $pspriteStep = 0;
    public string $pspriteBody = '';
    public string $pspriteFlash = '';
    public int $flashTics = 0;

    public function __construct(?Mobj $mo = null, int $cheats = 0)
    {
        $this->mo = $mo; $this->cheats = $cheats; $this->cmd = new Ticcmd();
    }

    public function setMessage(string $text): void
    {
        $this->message = $text; $this->messageTics = 4 * Defs::TICRATE;
    }

    public static function spawnPlayer(World $world, MapThing $start, int $cheats = 0): self
    {
        $x = $start->x * Defs::FRACUNIT; $y = $start->y * Defs::FRACUNIT;
        $sec = Collision::pointInSubsector($world, $x, $y)->sector;
        $mo = new Mobj(x: $x, y: $y, z: $sec->floorheight, angle: Compat::asU32(intdiv($start->angle, 45) * 0x20000000), floorz: $sec->floorheight, ceilingz: $sec->ceilingheight);
        $p = new self($mo, $cheats); $mo->player = $p;
        if ($cheats & 1) $mo->flags |= Defs::MF_NOCLIP;
        $p->viewz = $mo->z + Defs::VIEWHEIGHT; $world->mobjs[] = $mo;
        return $p;
    }

    public static function thrust(Mobj $mo, int $angle, int $move): void
    {
        $mo->momx += Compat::fixedMul($move, Tables::fineCos($angle));
        $mo->momy += Compat::fixedMul($move, Tables::fineSin($angle));
    }

    public static function calcHeight(self $p, int $leveltime): void
    {
        $mo = $p->mo;
        $p->bob = intdiv(Compat::fixedMul($mo->momx, $mo->momx) + Compat::fixedMul($mo->momy, $mo->momy), 4);
        $p->bob = min($p->bob, Defs::MAXBOB);
        if ($mo->z > $mo->floorz) { $p->viewz = min($mo->z + $p->viewheight, $mo->ceilingz - 4 * Defs::FRACUNIT); return; }
        $angle = (intdiv(Defs::FINEANGLES, 20) * $leveltime) & Defs::FINEMASK;
        $bob = Compat::fixedMul(intdiv($p->bob, 2), Tables::$finesine[$angle] ?? 0);
        if ($p->playerstate === Defs::PST_LIVE) {
            $p->viewheight += $p->deltaviewheight;
            if ($p->viewheight > Defs::VIEWHEIGHT) { $p->viewheight = Defs::VIEWHEIGHT; $p->deltaviewheight = 0; }
            if ($p->viewheight < intdiv(Defs::VIEWHEIGHT, 2)) { $p->viewheight = intdiv(Defs::VIEWHEIGHT, 2); if ($p->deltaviewheight <= 0) $p->deltaviewheight = 1; }
            if ($p->deltaviewheight) $p->deltaviewheight += intdiv(Defs::FRACUNIT, 4) ?: 1;
        }
        $p->viewz = min($mo->z + $p->viewheight + $bob, $mo->ceilingz - 4 * Defs::FRACUNIT);
    }

    public static function xyMovement(World $world, Mobj $mo, object $game): void
    {
        if ($mo->momx === 0 && $mo->momy === 0) return;
        Collision::slideMove($world, $mo, $mo->momx, $mo->momy, $game);
        if ($mo->player && abs($mo->momx) < Defs::STOPSPEED && abs($mo->momy) < Defs::STOPSPEED && $mo->player->cmd->forwardmove === 0 && $mo->player->cmd->sidemove === 0) {
            $mo->momx = $mo->momy = 0; return;
        }
        $mo->momx = Compat::fixedMul($mo->momx, Defs::FRICTION); $mo->momy = Compat::fixedMul($mo->momy, Defs::FRICTION);
    }

    public static function zMovement(Mobj $mo): void
    {
        $mo->z += $mo->momz;
        if ($mo->z <= $mo->floorz) { $mo->z = $mo->floorz; $mo->momz = 0; } else $mo->momz -= Defs::GRAVITY;
        if ($mo->z + $mo->height > $mo->ceilingz) { $mo->z = $mo->ceilingz - $mo->height; $mo->momz = 0; }
    }

    private static function specialSector(World $world, self $p, object $game, int $time): void
    {
        $mo = $p->mo; $sec = Collision::pointInSubsector($world, $mo->x, $mo->y)->sector;
        if ($mo->z !== $sec->floorheight || !$sec->special) return;
        if ($sec->special === 9) { ++$p->secretcount; $sec->special = 0; return; }
        if (in_array($sec->special, [5, 7, 4, 16, 11], true) && (($time & 0x1f) === 0)) {
            $damage = $sec->special === 7 ? 5 : ($sec->special === 5 ? 10 : 20);
            $game->damageMobj($mo, null, $damage);
            if ($sec->special === 11 && $p->health <= 10 && $game->specials) $game->specials->exitRequested = true;
        }
    }

    public static function playerThink(World $world, self $p, object $game, int $leveltime): void
    {
        $mo = $p->mo; $cmd = $p->cmd;
        if ($p->playerstate === Defs::PST_DEAD) {
            if ($p->viewheight > 6 * Defs::FRACUNIT) $p->viewheight -= Defs::FRACUNIT;
            self::calcHeight($p, $leveltime);
            if ($cmd->buttons & Defs::BT_USE) { $p->playerstate = Defs::PST_LIVE; $p->health = $mo->health = 100; $mo->alive = true; $mo->flags |= Defs::MF_SHOOTABLE | Defs::MF_SOLID; }
            return;
        }
        $mo->angle = Compat::asU32($mo->angle + ($cmd->angleturn << 16));
        if ($mo->z <= $mo->floorz) {
            if ($cmd->forwardmove) self::thrust($mo, $mo->angle, $cmd->forwardmove * 2048);
            if ($cmd->sidemove) self::thrust($mo, Compat::asU32($mo->angle - Defs::ANG90), $cmd->sidemove * 2048);
        }
        self::xyMovement($world, $mo, $game); self::zMovement($mo); self::calcHeight($p, $leveltime); self::specialSector($world, $p, $game, $leveltime);
        if ($cmd->buttons & Defs::BT_USE) { if (!$p->usedown) { Collision::useLines($world, $p, $game); $p->usedown = true; } } else $p->usedown = false;
        if ($cmd->buttons & Defs::BT_CHANGE) {
            $w = ($cmd->buttons & Defs::BT_WEAPONMASK) >> Defs::BT_WEAPONSHIFT;
            if ($w >= 0 && $w <= Defs::WP_SUPERSHOTGUN && $p->weaponowned[$w] && $w !== $p->readyweapon) $p->pendingweapon = $w;
        }
        self::weaponThink($p, $game);
        if ($p->damagecount) --$p->damagecount; if ($p->bonuscount) --$p->bonuscount;
        if ($p->messageTics && --$p->messageTics <= 0) $p->message = '';
    }

    private static function weaponAmmo(): array
    {
        return self::WEAPON_AMMO;
    }
    private static function weaponPatch(): array
    {
        return [Defs::WP_FIST=>'PUNGA0',Defs::WP_PISTOL=>'PISGA0',Defs::WP_SHOTGUN=>'SHTGA0',Defs::WP_CHAINGUN=>'CHGGA0',Defs::WP_MISSILE=>'MISGA0',Defs::WP_PLASMA=>'PLSGA0',Defs::WP_BFG=>'BFGGA0',Defs::WP_CHAINSAW=>'SAWGC0',Defs::WP_SUPERSHOTGUN=>'SHT2A0'];
    }
    private static function attackSequences(): array
    {
        return [
            Defs::WP_FIST=>[['PUNGB0',4,0,'',0,0],['PUNGC0',4,1,'',0,0],['PUNGD0',5,0,'',0,0],['PUNGC0',4,0,'',0,0],['PUNGB0',5,0,'',0,0]],
            Defs::WP_PISTOL=>[['PISGA0',4,0,'',0,0],['PISGB0',6,1,'PISFA0',7,1],['PISGC0',4,0,'',0,0],['PISGB0',5,0,'',0,0]],
            Defs::WP_SHOTGUN=>[['SHTGA0',3,0,'',0,0],['SHTGA0',7,1,'SHTFA0',7,1],['SHTGB0',5,0,'',0,0],['SHTGC0',5,0,'',0,0],['SHTGD0',4,0,'',0,0],['SHTGC0',5,0,'',0,0],['SHTGB0',5,0,'',0,0],['SHTGA0',3,0,'',0,0],['SHTGA0',7,0,'',0,0]],
            Defs::WP_CHAINGUN=>[['CHGGA0',4,1,'CHGFA0',5,1],['CHGGB0',4,1,'CHGFB0',5,2]],
            Defs::WP_MISSILE=>[['MISGB0',8,0,'MISFA0',15,1],['MISGB0',12,1,'',0,2]],
            Defs::WP_PLASMA=>[['PLSGA0',3,1,'PLSFA0',4,1],['PLSGB0',20,0,'',0,0]],
            Defs::WP_BFG=>[['BFGGA0',20,0,'',0,0],['BFGGB0',10,0,'BFGFA0',17,1],['BFGGB0',10,1,'',0,2],['BFGGB0',20,0,'',0,0]],
            Defs::WP_CHAINSAW=>[['SAWGA0',4,1,'',0,0],['SAWGB0',4,1,'',0,0]],
            Defs::WP_SUPERSHOTGUN=>[['SHT2A0',3,0,'',0,0],['SHT2A0',7,1,'SHT2I0',9,1],['SHT2B0',7,0,'',0,0],['SHT2C0',7,0,'',0,0],['SHT2D0',7,0,'',0,0],['SHT2E0',7,0,'',0,0],['SHT2F0',7,0,'',0,0],['SHT2G0',6,0,'',0,0],['SHT2H0',6,0,'',0,0],['SHT2A0',5,0,'',0,0]],
        ];
    }

    private static function weaponThink(self $p, object $game): void
    {
        $firing = (bool)($p->cmd->buttons & Defs::BT_ATTACK); $ammoMap = self::weaponAmmo(); $ammo = $ammoMap[$p->readyweapon] ?? null;
        $can = $ammo === null || $p->ammo[$ammo] > 0;
        if (!$can) {
            foreach ([Defs::WP_PISTOL,Defs::WP_SHOTGUN,Defs::WP_CHAINGUN,Defs::WP_MISSILE,Defs::WP_PLASMA,Defs::WP_BFG,Defs::WP_FIST] as $w) {
                $a = $ammoMap[$w] ?? null; if ($p->weaponowned[$w] && ($a === null || $p->ammo[$a] > 0)) { $p->pendingweapon = $w; break; }
            }
        }
        if ($p->flashTics > 0 && --$p->flashTics <= 0) { $p->pspriteFlash = ''; $p->extralight = 0; }
        if ($p->pspriteState === 'fire') $p->pspriteState = 'atk';
        if ($p->pspriteState === 'atk') {
            if ($firing) $p->attackdown = true; if ($p->pspriteTics > 0) --$p->pspriteTics;
            if ($p->pspriteTics > 0) return; ++$p->pspriteStep; self::enterAttackStep($p, $game, $ammo, $firing, $can); return;
        }
        if ($p->pendingweapon !== Defs::WP_NOCHANGE || $p->pspriteState === 'down') { self::lowerWeapon($p, $game); return; }
        if ($p->pspriteState === 'up') { self::raiseWeapon($p, $game); return; }
        if ($firing && $can && (!$p->attackdown || !in_array($p->readyweapon, [Defs::WP_MISSILE, Defs::WP_BFG], true))) {
            $p->pspriteState='atk'; $p->pspriteStep=0; $p->pspriteSy=Sprites::WEAPONTOP; $p->attackdown=true; self::enterAttackStep($p,$game,$ammo,$firing,$can); return;
        }
        if ($p->pspriteState !== 'ready') { self::startReady($p, $game); return; }
        self::tickReady($p, $game); if (!$firing) { $p->attackdown=false; $p->refire=0; }
    }

    private static function lowerWeapon(self $p, object $game): void
    {
        $p->pspriteState='down'; $patch=self::weaponPatch(); if (!$p->pspriteBody) $p->pspriteBody=$patch[$p->readyweapon]??'PISGA0';
        $p->pspriteSy += Sprites::LOWERSPEED; if ($p->pspriteSy < Sprites::WEAPONBOTTOM) return;
        $p->pspriteSy=Sprites::WEAPONBOTTOM; if ($p->pendingweapon !== Defs::WP_NOCHANGE) { $p->readyweapon=$p->pendingweapon; $p->pendingweapon=Defs::WP_NOCHANGE; }
        if ($p->readyweapon===Defs::WP_CHAINSAW) $game->startSound('sawup');
        $p->pspriteState='up'; $p->pspriteBody=$patch[$p->readyweapon]??'PISGA0';
    }
    private static function raiseWeapon(self $p, object $game): void
    {
        $p->pspriteSy -= Sprites::RAISESPEED; $patch=self::weaponPatch(); if (!$p->pspriteBody) $p->pspriteBody=$patch[$p->readyweapon]??'PISGA0';
        if ($p->pspriteSy > Sprites::WEAPONTOP) return; $p->pspriteSy=Sprites::WEAPONTOP; self::startReady($p,$game);
    }
    private static function startReady(self $p, object $game): void
    {
        $p->pspriteState='ready'; $p->pspriteStep=0;
        if ($p->readyweapon!==Defs::WP_CHAINSAW) { $p->pspriteBody=self::weaponPatch()[$p->readyweapon]??'PISGA0'; $p->pspriteTics=0; return; }
        $p->pspriteBody='SAWGC0'; $p->pspriteTics=4; $game->startSound('sawidl');
    }
    private static function tickReady(self $p, object $game): void
    {
        if ($p->readyweapon!==Defs::WP_CHAINSAW) { $p->pspriteBody=self::weaponPatch()[$p->readyweapon]??'PISGA0'; return; }
        if ($p->pspriteTics > 0 && --$p->pspriteTics > 0) return;
        $p->pspriteStep=($p->pspriteStep+1)%2; $p->pspriteBody=$p->pspriteStep?'SAWGD0':'SAWGC0'; $p->pspriteTics=4;
        if (!$p->pspriteStep) $game->startSound('sawidl');
    }
    private static function enterAttackStep(self $p, object $game, ?int $ammo, bool $firing, bool $can): void
    {
        $seq=self::attackSequences()[$p->readyweapon]??self::attackSequences()[Defs::WP_PISTOL];
        while (true) {
            if ($p->pspriteStep>=count($seq)) { if ($firing&&$can&&$p->pendingweapon===Defs::WP_NOCHANGE) { $p->pspriteStep=0; continue; } self::startReady($p,$game); if(!$firing){$p->attackdown=false;$p->refire=0;} return; }
            [$body,$tics,$fire,$flash,$ft,$light]=$seq[$p->pspriteStep]; $p->pspriteBody=$body; $p->pspriteTics=$tics;
            if($ft){$p->pspriteFlash=$flash;$p->flashTics=$ft;} if($light)$p->extralight=$light; if($fire)self::doShot($p,$game,$ammo);
            if($tics>0)return; ++$p->pspriteStep;
        }
    }
    private static function doShot(self $p, object $game, ?int $ammo): void
    {
        if($ammo!==null){if($p->ammo[$ammo]<=0)return;--$p->ammo[$ammo];}
        $shots=[Defs::WP_FIST=>[2,Defs::MELEERANGE,null],Defs::WP_CHAINSAW=>[3,Defs::MELEERANGE,'sawful'],Defs::WP_PISTOL=>[5,Defs::MISSILERANGE,'pistol'],Defs::WP_SHOTGUN=>[7,Defs::MISSILERANGE,'shotgn'],Defs::WP_SUPERSHOTGUN=>[8,Defs::MISSILERANGE,'dshtgn'],Defs::WP_CHAINGUN=>[5,Defs::MISSILERANGE,'pistol'],Defs::WP_MISSILE=>[20,Defs::MISSILERANGE,'rlaunc'],Defs::WP_PLASMA=>[5,Defs::MISSILERANGE,'plasma'],Defs::WP_BFG=>[100,Defs::MISSILERANGE,'bfg']];
        [$dmg,$range,$sfx]=$shots[$p->readyweapon]??[5,Defs::MISSILERANGE,'pistol']; $hit=false;
        if($p->mo){$pellets=$p->readyweapon===Defs::WP_SHOTGUN?7:($p->readyweapon===Defs::WP_SUPERSHOTGUN?20:1);$shot=$dmg*(($game->leveltime&7)+1);
            if($p->readyweapon===Defs::WP_CHAINSAW){$shot=2*(($game->leveltime%10)+1);$range=Defs::MELEERANGE+1;}
            for($i=0;$i<$pellets;++$i)if(Collision::lineAttack($game->world,$p->mo,$shot,$game,$range))$hit=true;
        }
        if($p->readyweapon===Defs::WP_CHAINSAW)$game->startSound($hit?'sawhit':'sawful'); elseif($p->readyweapon===Defs::WP_FIST){if($hit)$game->startSound('punch');} elseif($sfx)$game->startSound($sfx);
        ++$p->refire;$p->attackdown=true;Enemy::noiseAlert($game->world,$p->mo,$game);
    }

    public static function currentWeaponPatch(self $p): string
    {
        if($p->pspriteBody)return $p->pspriteBody;
        if(in_array($p->pspriteState,['atk','fire'],true)){ $fire=[Defs::WP_FIST=>'PUNGC0',Defs::WP_PISTOL=>'PISGB0',Defs::WP_SHOTGUN=>'SHTGA0',Defs::WP_CHAINGUN=>'CHGGB0',Defs::WP_MISSILE=>'MISGB0',Defs::WP_PLASMA=>'PLSGA0',Defs::WP_BFG=>'BFGGB0',Defs::WP_CHAINSAW=>'SAWGA0',Defs::WP_SUPERSHOTGUN=>'SHT2A0']; return $fire[$p->readyweapon]??'PISGA0';}
        return self::weaponPatch()[$p->readyweapon]??'PISGA0';
    }
}
