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
    public ?Mobj $attacker = null;
    public int $extralight = 0;
    public int $fixedcolormap = 0;
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
    /** @var int[] */
    public array $powers = [0, 0, 0, 0, 0, 0];

    public function __construct(?Mobj $mo = null, int $cheats = 0)
    {
        $this->mo = $mo;
        $this->cheats = $cheats;
        $this->cmd = new Ticcmd();
    }

    public function setMessage(string $text): void
    {
        $this->message = Deh::string($text);
        $this->messageTics = 4 * Defs::TICRATE;
    }

    public static function givePower(self $p, int $power): bool
    {
        if ($power === Defs::PW_INVULNERABILITY) {
            $p->powers[$power] = Defs::INVULNTICS;
            return true;
        }
        if ($power === Defs::PW_INVISIBILITY) {
            $p->powers[$power] = Defs::INVISTICS;
            if ($p->mo !== null) {
                $p->mo->flags |= Defs::MF_SHADOW;
            }
            return true;
        }
        if ($power === Defs::PW_INFRARED) {
            $p->powers[$power] = Defs::INFRATICS;
            return true;
        }
        if ($power === Defs::PW_IRONFEET) {
            $p->powers[$power] = Defs::IRONTICS;
            return true;
        }
        if ($power === Defs::PW_STRENGTH) {
            if ($p->health < Defs::MAXHEALTH) {
                $p->health = min(Defs::MAXHEALTH, $p->health + 100);
                if ($p->mo !== null) {
                    $p->mo->health = $p->health;
                }
            }
            $p->powers[$power] = 1;
            return true;
        }
        if ($p->powers[$power]) {
            return false;
        }
        $p->powers[$power] = 1;
        return true;
    }

    public static function spawnPlayer(World $world, MapThing $start, int $cheats = 0): self
    {
        $x = $start->x * Defs::FRACUNIT;
        $y = $start->y * Defs::FRACUNIT;
        $sec = Collision::pointInSubsector($world, $x, $y)->sector;
        $mo = new Mobj(x: $x, y: $y, z: $sec->floorheight, angle: Compat::asU32(intdiv($start->angle, 45) * 0x20000000), floorz: $sec->floorheight, ceilingz: $sec->ceilingheight);
        $p = new self($mo, $cheats);
        $mo->player = $p;
        $deh = Deh::get();
        $p->health = $deh->initialHealth;
        $mo->health = $deh->initialHealth;
        $p->ammo = [$deh->initialBullets, 0, 0, 0];
        $p->maxammo = $deh->maxammo;
        if ($cheats & 1) {
            $mo->flags |= Defs::MF_NOCLIP;
        }
        $p->viewz = $mo->z + Defs::VIEWHEIGHT;
        $mo->lastlook = Enemy::publicRandom() % 4;
        $world->mobjs[] = $mo;
        Collision::setThingPosition($world, $mo);
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
        if ($mo->z > $mo->floorz) {
            $p->viewz = min($mo->z + $p->viewheight, $mo->ceilingz - 4 * Defs::FRACUNIT);
            return;
        }
        $angle = (intdiv(Defs::FINEANGLES, 20) * $leveltime) & Defs::FINEMASK;
        $bob = Compat::fixedMul(intdiv($p->bob, 2), Tables::$finesine[$angle] ?? 0);
        if ($p->playerstate === Defs::PST_LIVE) {
            $p->viewheight += $p->deltaviewheight;
            if ($p->viewheight > Defs::VIEWHEIGHT) {
                $p->viewheight = Defs::VIEWHEIGHT;
                $p->deltaviewheight = 0;
            }
            if ($p->viewheight < intdiv(Defs::VIEWHEIGHT, 2)) {
                $p->viewheight = intdiv(Defs::VIEWHEIGHT, 2);
                if ($p->deltaviewheight <= 0) {
                    $p->deltaviewheight = 1;
                }
            }
            if ($p->deltaviewheight) {
                $p->deltaviewheight += intdiv(Defs::FRACUNIT, 4) ?: 1;
            }
        }
        $p->viewz = min($mo->z + $p->viewheight + $bob, $mo->ceilingz - 4 * Defs::FRACUNIT);
    }

    public static function xyMovement(World $world, Mobj $mo, object $game): void
    {
        Enemy::pXyMovement($world, $mo, $game);
    }

    public static function zMovement(Mobj $mo, World $world, object $game): void
    {
        Enemy::mobjZ($mo, $world, $game);
    }

    private static function specialSector(World $world, self $p, object $game, int $time): void
    {
        $mo = $p->mo;
        $sec = Collision::pointInSubsector($world, $mo->x, $mo->y)->sector;
        if ($mo->z !== $sec->floorheight || !$sec->special) {
            return;
        }
        if ($sec->special === 9) {
            ++$p->secretcount;
            $sec->special = 0;
            return;
        }
        if (in_array($sec->special, [5, 7, 4, 16, 11], true)) {
            if ($p->powers[Defs::PW_IRONFEET]) {
                return;
            }
            if (($time & 0x1f) === 0) {
            $damage = $sec->special === 7 ? 5 : ($sec->special === 5 ? 10 : 20);
            $game->damageMobj($mo, null, $damage);
            if ($sec->special === 11 && $p->health <= 10 && $game->specials) {
                $game->specials->exitRequested = true;
            }
            }
        }
    }

    private static function deathThink(World $world, self $p, object $game, int $leveltime): void
    {
        $mo = $p->mo;
        $cmd = $p->cmd;
        if ($p->viewheight > 6 * Defs::FRACUNIT) {
            $p->viewheight -= Defs::FRACUNIT;
        }
        if ($p->viewheight < 6 * Defs::FRACUNIT) {
            $p->viewheight = 6 * Defs::FRACUNIT;
        }
        $p->deltaviewheight = 0;
        self::xyMovement($world, $mo, $game);
        self::zMovement($mo, $world, $game);
        self::calcHeight($p, $leveltime);
        if ($p->attacker !== null && $p->attacker !== $mo) {
            $angle = Collision::angleTo($mo->x, $mo->y, $p->attacker->x, $p->attacker->y);
            $delta = Compat::asU32($angle - $mo->angle);
            $ang5 = intdiv(Defs::ANG90, 18);
            if ($delta < Compat::asU32($ang5) || $delta > Compat::asU32(-$ang5)) {
                $mo->angle = $angle;
                if ($p->damagecount) {
                    --$p->damagecount;
                }
            } elseif ($delta < Compat::asU32(Defs::ANG180)) {
                $mo->angle = Compat::asU32($mo->angle + $ang5);
            } else {
                $mo->angle = Compat::asU32($mo->angle - $ang5);
            }
        } elseif ($p->damagecount) {
            --$p->damagecount;
        }
        self::weaponThink($p, $game);
        if ($cmd->buttons & Defs::BT_USE) {
            $p->playerstate = Defs::PST_REBORN;
        }
    }

    public static function playerThink(World $world, self $p, object $game, int $leveltime): void
    {
        $mo = $p->mo;
        $cmd = $p->cmd;
        if ($p->playerstate === Defs::PST_DEAD) {
            self::deathThink($world, $p, $game, $leveltime);
            return;
        }
        $mo->angle = Compat::asU32($mo->angle + ($cmd->angleturn << 16));
        if ($mo->z <= $mo->floorz) {
            if ($cmd->forwardmove) {
                self::thrust($mo, $mo->angle, $cmd->forwardmove * 2048);
            }
            if ($cmd->sidemove) {
                self::thrust($mo, Compat::asU32($mo->angle - Defs::ANG90), $cmd->sidemove * 2048);
            }
        }
        self::xyMovement($world, $mo, $game);
        self::zMovement($mo, $world, $game);
        self::calcHeight($p, $leveltime);
        self::specialSector($world, $p, $game, $leveltime);
        if ($cmd->buttons & Defs::BT_USE) {
            if (!$p->usedown) {
                Collision::useLines($world, $p, $game);
                $p->usedown = true;
            }
        } else {
            $p->usedown = false;
        }
        if ($cmd->buttons & Defs::BT_CHANGE) {
            $w = ($cmd->buttons & Defs::BT_WEAPONMASK) >> Defs::BT_WEAPONSHIFT;
            if ($w >= 0 && $w <= Defs::WP_SUPERSHOTGUN && $p->weaponowned[$w] && $w !== $p->readyweapon) {
                $p->pendingweapon = $w;
            }
        }
        self::weaponThink($p, $game);
        if ($p->powers[Defs::PW_STRENGTH]) {
            ++$p->powers[Defs::PW_STRENGTH];
        }
        if ($p->powers[Defs::PW_INVULNERABILITY]) {
            --$p->powers[Defs::PW_INVULNERABILITY];
        }
        if ($p->powers[Defs::PW_INVISIBILITY]) {
            --$p->powers[Defs::PW_INVISIBILITY];
            if ($p->powers[Defs::PW_INVISIBILITY] === 0 && $p->mo !== null) {
                $p->mo->flags &= ~Defs::MF_SHADOW;
            }
        }
        if ($p->powers[Defs::PW_INFRARED]) {
            --$p->powers[Defs::PW_INFRARED];
        }
        if ($p->powers[Defs::PW_IRONFEET]) {
            --$p->powers[Defs::PW_IRONFEET];
        }
        $inv = $p->powers[Defs::PW_INVULNERABILITY];
        $ir = $p->powers[Defs::PW_INFRARED];
        if ($inv) {
            $p->fixedcolormap = ($inv > 4 * 32 || ($inv & 8) !== 0) ? Defs::INVERSECOLORMAP : 0;
        } elseif ($ir) {
            $p->fixedcolormap = ($ir > 4 * 32 || ($ir & 8) !== 0) ? 1 : 0;
        } else {
            $p->fixedcolormap = 0;
        }
        if ($p->damagecount) {
            --$p->damagecount;
        }
        if ($p->bonuscount) {
            --$p->bonuscount;
        }
        if ($p->messageTics && --$p->messageTics <= 0) {
            $p->message = '';
        }
    }

    private static function weaponAmmo(): array
    {
        return self::WEAPON_AMMO;
    }

    private static function weaponPatch(): array
    {
        return [
            Defs::WP_FIST => 'PUNGA0',
            Defs::WP_PISTOL => 'PISGA0',
            Defs::WP_SHOTGUN => 'SHTGA0',
            Defs::WP_CHAINGUN => 'CHGGA0',
            Defs::WP_MISSILE => 'MISGA0',
            Defs::WP_PLASMA => 'PLSGA0',
            Defs::WP_BFG => 'BFGGA0',
            Defs::WP_CHAINSAW => 'SAWGC0',
            Defs::WP_SUPERSHOTGUN => 'SHT2A0',
        ];
    }

    private static function attackSequences(): array
    {
        return [
            Defs::WP_FIST => [['PUNGB0', 4, 0, '', 0, 0], ['PUNGC0', 4, 1, '', 0, 0], ['PUNGD0', 5, 0, '', 0, 0], ['PUNGC0', 4, 0, '', 0, 0], ['PUNGB0', 5, 0, '', 0, 0]],
            Defs::WP_PISTOL => [['PISGA0', 4, 0, '', 0, 0], ['PISGB0', 6, 1, 'PISFA0', 7, 1], ['PISGC0', 4, 0, '', 0, 0], ['PISGB0', 5, 0, '', 0, 0]],
            Defs::WP_SHOTGUN => [['SHTGA0', 3, 0, '', 0, 0], ['SHTGA0', 7, 1, 'SHTFA0', 7, 1], ['SHTGB0', 5, 0, '', 0, 0], ['SHTGC0', 5, 0, '', 0, 0], ['SHTGD0', 4, 0, '', 0, 0], ['SHTGC0', 5, 0, '', 0, 0], ['SHTGB0', 5, 0, '', 0, 0], ['SHTGA0', 3, 0, '', 0, 0], ['SHTGA0', 7, 0, '', 0, 0]],
            Defs::WP_CHAINGUN => [['CHGGA0', 4, 1, 'CHGFA0', 5, 1], ['CHGGB0', 4, 1, 'CHGFB0', 5, 2]],
            Defs::WP_MISSILE => [['MISGB0', 8, 0, 'MISFA0', 15, 1], ['MISGB0', 12, 1, '', 0, 2]],
            Defs::WP_PLASMA => [['PLSGA0', 3, 1, 'PLSFA0', 4, 1], ['PLSGB0', 20, 0, '', 0, 0, true]],
            Defs::WP_BFG => [['BFGGA0', 20, 0, '', 0, 0], ['BFGGB0', 10, 0, 'BFGFA0', 17, 1], ['BFGGB0', 10, 1, '', 0, 2], ['BFGGB0', 20, 0, '', 0, 0]],
            Defs::WP_CHAINSAW => [['SAWGA0', 4, 1, '', 0, 0], ['SAWGB0', 4, 1, '', 0, 0]],
            Defs::WP_SUPERSHOTGUN => [['SHT2A0', 3, 0, '', 0, 0], ['SHT2A0', 7, 1, 'SHT2I0', 9, 1], ['SHT2B0', 7, 0, '', 0, 0], ['SHT2C0', 7, 0, '', 0, 0], ['SHT2D0', 7, 0, '', 0, 0], ['SHT2E0', 7, 0, '', 0, 0], ['SHT2F0', 7, 0, '', 0, 0], ['SHT2G0', 6, 0, '', 0, 0], ['SHT2H0', 6, 0, '', 0, 0], ['SHT2A0', 5, 0, '', 0, 0]],
        ];
    }

    private static function weaponThink(self $p, object $game): void
    {
        if ($p->playerstate === Defs::PST_DEAD || $p->health <= 0) {
            self::lowerWeapon($p, $game);
            return;
        }
        $firing = (bool) ($p->cmd->buttons & Defs::BT_ATTACK);
        $ammoMap = self::weaponAmmo();
        $ammo = $ammoMap[$p->readyweapon] ?? null;
        $need = self::ammoNeeded($p->readyweapon);
        $can = $ammo === null || $p->ammo[$ammo] >= $need;
        if (!$can) {
            foreach ([Defs::WP_PISTOL, Defs::WP_SHOTGUN, Defs::WP_CHAINGUN, Defs::WP_MISSILE, Defs::WP_PLASMA, Defs::WP_BFG, Defs::WP_FIST] as $w) {
                $a = $ammoMap[$w] ?? null;
                if ($p->weaponowned[$w] && ($a === null || $p->ammo[$a] >= self::ammoNeeded($w))) {
                    $p->pendingweapon = $w;
                    break;
                }
            }
        }
        if ($p->flashTics > 0 && --$p->flashTics <= 0) {
            $p->pspriteFlash = '';
            $p->extralight = 0;
        }
        if ($p->pspriteState === 'fire') {
            $p->pspriteState = 'atk';
        }
        if ($p->pspriteState === 'atk') {
            if ($firing) {
                $p->attackdown = true;
            }
            if ($p->pspriteTics > 0) {
                --$p->pspriteTics;
            }
            if ($p->pspriteTics > 0) {
                return;
            }
            ++$p->pspriteStep;
            self::enterAttackStep($p, $game, $ammo, $firing, $can);
            return;
        }
        if ($p->pendingweapon !== Defs::WP_NOCHANGE || $p->pspriteState === 'down') {
            self::lowerWeapon($p, $game);
            return;
        }
        if ($p->pspriteState === 'up') {
            self::raiseWeapon($p, $game);
            return;
        }
        if ($firing && $can && (!$p->attackdown || !in_array($p->readyweapon, [Defs::WP_MISSILE, Defs::WP_BFG], true))) {
            $p->pspriteState = 'atk';
            $p->pspriteStep = 0;
            $p->pspriteSy = Sprites::WEAPONTOP;
            $p->attackdown = true;
            self::enterAttackStep($p, $game, $ammo, $firing, $can);
            return;
        }
        if ($p->pspriteState !== 'ready') {
            self::startReady($p, $game);
            return;
        }
        self::tickReady($p, $game);
        if (!$firing) {
            $p->attackdown = false;
            $p->refire = 0;
        }
    }

    private static function lowerWeapon(self $p, object $game): void
    {
        $p->pspriteState = 'down';
        $patch = self::weaponPatch();
        if (!$p->pspriteBody) {
            $p->pspriteBody = $patch[$p->readyweapon] ?? 'PISGA0';
        }
        $p->pspriteSy += Sprites::LOWERSPEED;
        if ($p->pspriteSy < Sprites::WEAPONBOTTOM) {
            return;
        }
        $p->pspriteSy = Sprites::WEAPONBOTTOM;
        if ($p->playerstate === Defs::PST_DEAD || $p->health <= 0) {
            return;
        }
        if ($p->pendingweapon !== Defs::WP_NOCHANGE) {
            $p->readyweapon = $p->pendingweapon;
            $p->pendingweapon = Defs::WP_NOCHANGE;
        }
        if ($p->readyweapon === Defs::WP_CHAINSAW) {
            $game->startSound('sawup');
        }
        $p->pspriteState = 'up';
        $p->pspriteBody = $patch[$p->readyweapon] ?? 'PISGA0';
    }

    private static function raiseWeapon(self $p, object $game): void
    {
        $p->pspriteSy -= Sprites::RAISESPEED;
        $patch = self::weaponPatch();
        if (!$p->pspriteBody) {
            $p->pspriteBody = $patch[$p->readyweapon] ?? 'PISGA0';
        }
        if ($p->pspriteSy > Sprites::WEAPONTOP) {
            return;
        }
        $p->pspriteSy = Sprites::WEAPONTOP;
        self::startReady($p, $game);
    }

    private static function startReady(self $p, object $game): void
    {
        $p->pspriteState = 'ready';
        $p->pspriteStep = 0;
        if ($p->readyweapon !== Defs::WP_CHAINSAW) {
            $p->pspriteBody = self::weaponPatch()[$p->readyweapon] ?? 'PISGA0';
            $p->pspriteTics = 0;
            return;
        }
        $p->pspriteBody = 'SAWGC0';
        $p->pspriteTics = 4;
        $game->startSound('sawidl');
    }

    private static function tickReady(self $p, object $game): void
    {
        if ($p->readyweapon !== Defs::WP_CHAINSAW) {
            $p->pspriteBody = self::weaponPatch()[$p->readyweapon] ?? 'PISGA0';
            return;
        }
        if ($p->pspriteTics > 0 && --$p->pspriteTics > 0) {
            return;
        }
        $p->pspriteStep = ($p->pspriteStep + 1) % 2;
        $p->pspriteBody = $p->pspriteStep ? 'SAWGD0' : 'SAWGC0';
        $p->pspriteTics = 4;
        if (!$p->pspriteStep) {
            $game->startSound('sawidl');
        }
    }

    private static function enterAttackStep(self $p, object $game, ?int $ammo, bool $firing, bool $can): void
    {
        $seq = self::attackSequences()[$p->readyweapon] ?? self::attackSequences()[Defs::WP_PISTOL];
        while (true) {
            if ($p->pspriteStep >= count($seq)) {
                if ($firing && $can && $p->pendingweapon === Defs::WP_NOCHANGE) {
                    $p->pspriteStep = 0;
                    continue;
                }
                self::startReady($p, $game);
                if (!$firing) {
                    $p->attackdown = false;
                    $p->refire = 0;
                }
                return;
            }
            $step = $seq[$p->pspriteStep];
            [$body, $tics, $fire, $flash, $ft, $light] = $step;
            // A_ReFire runs when the state is entered. Held fire skips the cooldown.
            if (!empty($step[6]) && $firing && $can && $p->pendingweapon === Defs::WP_NOCHANGE && $p->health > 0) {
                $p->pspriteStep = 0;
                continue;
            }
            $p->pspriteBody = $body;
            $p->pspriteTics = $tics;
            if ($ft) {
                $p->pspriteFlash = $flash;
                $p->flashTics = $ft;
            }
            if ($light) {
                $p->extralight = $light;
            }
            if ($fire) {
                self::doShot($p, $game, $ammo);
            }
            if ($tics > 0) {
                return;
            }
            ++$p->pspriteStep;
        }
    }

    private static function ammoNeeded(int $weapon): int
    {
        return $weapon === Defs::WP_BFG ? Deh::get()->bfgCellsPerShot : 1;
    }

    /** P_GunShot. */
    private static function gunShot(self $p, object $game, bool $accurate): bool
    {
        $mo = $p->mo;
        $slope = Collision::bulletSlope($game->world, $mo);
        $damage = 5 * ((Enemy::publicRandom() % 3) + 1);
        $angle = $mo->angle;
        if (!$accurate) {
            $angle = Compat::asU32($angle + (Enemy::publicRandom() - Enemy::publicRandom()) * 262144);
        }
        return Collision::lineAttack($game->world, $mo, $damage, $game, Defs::MISSILERANGE, $angle, $slope);
    }

    private static function doShot(self $p, object $game, ?int $ammo): void
    {
        $need = self::ammoNeeded($p->readyweapon);
        if ($ammo !== null) {
            if ($p->ammo[$ammo] < $need) {
                return;
            }
            $p->ammo[$ammo] -= $need;
        }
        $mo = $p->mo;
        $weapon = $p->readyweapon;
        $hit = false;
        if ($mo && in_array($weapon, [Defs::WP_MISSILE, Defs::WP_PLASMA, Defs::WP_BFG], true)) {
            if ($weapon === Defs::WP_PLASMA) {
                $p->pspriteFlash = (Enemy::publicRandom() & 1) !== 0 ? 'PLSFB0' : 'PLSFA0';
                $p->flashTics = 4;
            }
            if ($weapon === Defs::WP_MISSILE) {
                Enemy::spawnPlayerMissile($game->world, $mo, 'MISL', 20 * Defs::FRACUNIT, 20, 'rocket');
                $game->startSound('rlaunc');
            } elseif ($weapon === Defs::WP_PLASMA) {
                Enemy::spawnPlayerMissile($game->world, $mo, 'PLSS', 25 * Defs::FRACUNIT, 5, 'plasma');
                $game->startSound('plasma');
            } else {
                Enemy::spawnPlayerMissile($game->world, $mo, 'BFS1', 25 * Defs::FRACUNIT, 100, 'bfg');
                $game->startSound('bfg');
            }
            ++$p->refire;
            $p->attackdown = true;
            Enemy::noiseAlert($game->world, $mo, $game);
            return;
        }
        if ($mo && $weapon === Defs::WP_FIST) {
            $damage = ((Enemy::publicRandom() % 10) + 1) * 2;
            if ($p->powers[Defs::PW_STRENGTH]) {
                $damage *= 10;
            }
            $angle = Compat::asU32($mo->angle + (Enemy::publicRandom() - Enemy::publicRandom()) * 262144);
            $hit = Collision::lineAttack($game->world, $mo, $damage, $game, Defs::MELEERANGE, $angle);
            if ($hit) {
                $game->startSound('punch');
            }
        } elseif ($mo && $weapon === Defs::WP_CHAINSAW) {
            $damage = 2 * ((Enemy::publicRandom() % 10) + 1);
            $angle = Compat::asU32($mo->angle + (Enemy::publicRandom() - Enemy::publicRandom()) * 262144);
            $hit = Collision::lineAttack($game->world, $mo, $damage, $game, Defs::MELEERANGE + 1, $angle);
            $game->startSound($hit ? 'sawhit' : 'sawful');
        } elseif ($mo && $weapon === Defs::WP_SHOTGUN) {
            $game->startSound('shotgn');
            for ($i = 0; $i < 7; ++$i) {
                if (self::gunShot($p, $game, false)) {
                    $hit = true;
                }
            }
        } elseif ($mo && $weapon === Defs::WP_SUPERSHOTGUN) {
            $game->startSound('dshtgn');
            $slope = Collision::bulletSlope($game->world, $mo);
            for ($i = 0; $i < 20; ++$i) {
                $damage = 5 * ((Enemy::publicRandom() % 3) + 1);
                $angle = Compat::asU32($mo->angle + (Enemy::publicRandom() - Enemy::publicRandom()) * 524288);
                $pellet = $slope + (Enemy::publicRandom() - Enemy::publicRandom()) * 32;
                if (Collision::lineAttack($game->world, $mo, $damage, $game, Defs::MISSILERANGE, $angle, $pellet)) {
                    $hit = true;
                }
            }
            unset($slope);
        } elseif ($mo) {
            $game->startSound('pistol');
            self::gunShot($p, $game, $p->refire === 0);
        }
        ++$p->refire;
        $p->attackdown = true;
        if ($mo) {
            Enemy::noiseAlert($game->world, $mo, $game);
        }
    }

    public static function currentWeaponPatch(self $p): string
    {
        if ($p->pspriteBody) {
            return $p->pspriteBody;
        }
        if (in_array($p->pspriteState, ['atk', 'fire'], true)) {
            $fire = [
                Defs::WP_FIST => 'PUNGC0',
                Defs::WP_PISTOL => 'PISGB0',
                Defs::WP_SHOTGUN => 'SHTGA0',
                Defs::WP_CHAINGUN => 'CHGGB0',
                Defs::WP_MISSILE => 'MISGB0',
                Defs::WP_PLASMA => 'PLSGA0',
                Defs::WP_BFG => 'BFGGB0',
                Defs::WP_CHAINSAW => 'SAWGA0',
                Defs::WP_SUPERSHOTGUN => 'SHT2A0',
            ];
            return $fire[$p->readyweapon] ?? 'PISGA0';
        }
        return self::weaponPatch()[$p->readyweapon] ?? 'PISGA0';
    }
}
