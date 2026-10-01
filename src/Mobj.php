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

class Mobj
{
    public int $momx = 0;
    public int $momy = 0;
    public int $momz = 0;
    public ?Player $player = null;
    public bool $alive = true;
    public int $reactiontime = 0;
    public int $lastlook = 0;
    public ?Mobj $target = null;
    public int $movedir = 8;
    public int $movecount = 0;
    public int $threshold = 0;
    public string $aiState = '';
    public int $frame = 0;
    public int $tics = 0;
    public int $chaseTics = 0;
    public bool $justAttacked = false;
    public int $damage = 0;
    public int $tmx = 0;
    public int $tmy = 0;
    public ?Mobj $pickup = null;
    public ?Mobj $bnext = null;
    public ?Mobj $bprev = null;
    public bool $blocklinked = false;
    public int $bindex = -1;
    public string $attackKind = 'hitscan';
    public bool $didFire = false;
    public string $missileKind = '';
    public ?Mobj $tracer = null;
    public bool $easySkip = false;
    public int $istate = 0;
    public mixed $spawnpoint = null;
    public int $doomednum = -1;

    public function __construct(
        public int $x = 0,
        public int $y = 0,
        public int $z = 0,
        public int $angle = 0,
        int $momx = 0,
        int $momy = 0,
        int $momz = 0,
        public int $radius = Defs::PLAYER_RADIUS,
        public int $height = Defs::PLAYER_HEIGHT,
        public int $floorz = 0,
        public int $ceilingz = 0,
        public int $flags = Defs::MF_SOLID | Defs::MF_SHOOTABLE | Defs::MF_PICKUP | Defs::MF_DROPOFF,
        public int $health = 100,
        ?Player $player = null,
        public int $type = 1,
        public string $sprite = '',
        public ?array $info = null,
        bool $alive = true,
        int $reactiontime = 0,
        ?Mobj $target = null,
        int $movedir = 8,
        int $movecount = 0,
        string $aiState = '',
        int $frame = 0,
        int $tics = 0,
        int $chaseTics = 0,
        bool $justAttacked = false,
        int $damage = 0,
    ) {
        $this->momx = $momx;
        $this->momy = $momy;
        $this->momz = $momz;
        $this->player = $player;
        $this->alive = $alive;
        $this->reactiontime = $reactiontime;
        $this->target = $target;
        $this->movedir = $movedir;
        $this->movecount = $movecount;
        $this->aiState = $aiState;
        $this->frame = $frame;
        $this->tics = $tics;
        $this->chaseTics = $chaseTics;
        $this->justAttacked = $justAttacked;
        $this->damage = $damage;
        $this->tmx = $x;
        $this->tmy = $y;
    }

    /** @var array<int,array{string,int,int,int,int,string,mixed}>|null */
    private static ?array $infoCache = null;

    /** @return array<int,array{string,int,int,int,int,string,mixed}> */
    public static function infoTable(): array
    {
        if (self::$infoCache !== null) {
            return self::$infoCache;
        }
        $enemy = Defs::MF_SOLID | Defs::MF_SHOOTABLE;
        $special = Defs::MF_SPECIAL;
        $solid = Defs::MF_SOLID;
        self::$infoCache = [
            3004 => ['POSSA1', 20, 56, 20, $enemy, 'enemy', 'posit1'],
            9 => ['SPOSA1', 20, 56, 30, $enemy, 'enemy', 'posit1'],
            3001 => ['TROOA1', 20, 56, 60, $enemy, 'enemy', 'bgsit1'],
            3002 => ['SARGA1', 30, 56, 150, $enemy, 'enemy', 'sgtsit'],
            58 => ['SARGA1', 30, 56, 150, $enemy | Defs::MF_SHADOW, 'enemy', 'sgtsit'],
            65 => ['CPOSA1', 20, 56, 70, $enemy, 'enemy', 'posit2'],
            3003 => ['BOSSA1', 24, 64, 1000, $enemy, 'enemy', 'brssit'],
            3005 => ['HEADA1', 31, 56, 400, $enemy | Defs::MF_FLOAT | Defs::MF_NOGRAVITY, 'enemy', 'cacsit'],
            3006 => ['SKULA1', 16, 56, 100, $enemy | Defs::MF_FLOAT | Defs::MF_NOGRAVITY, 'enemy', 'sklatk'],
            16 => ['CYBRA1', 40, 110, 4000, $enemy, 'enemy', 'cybsit'],
            7 => ['SPIDA1', 128, 100, 3000, $enemy, 'enemy', 'spisit'],
            68 => ['BSPIA1', 64, 64, 500, $enemy, 'enemy', 'bspsit'],
            69 => ['BOS2A1', 24, 64, 500, $enemy, 'enemy', 'kntsit'],
            64 => ['VILEA1', 20, 56, 700, $enemy, 'enemy', 'vilsit'],
            66 => ['SKELA1', 20, 56, 500, $enemy, 'enemy', 'skesit'],
            67 => ['FATTA1', 48, 64, 600, $enemy, 'enemy', 'mansit'],
            71 => ['PAINA1', 31, 56, 400, $enemy | Defs::MF_FLOAT | Defs::MF_NOGRAVITY, 'enemy', 'pesit'],
            84 => ['SSWVA1', 20, 56, 50, $enemy, 'enemy', 'posit1'],
            72 => ['KEENA1', 16, 72, 100, $enemy | Defs::MF_NOGRAVITY, 'enemy', 'keenpn'],
            87 => ['', 20, 32, 1000, Defs::MF_NOBLOCKMAP | Defs::MF_NOSECTOR, 'bosstarget', null],
            88 => ['BBRNA1', 16, 16, 250, $enemy, 'enemy', 'bossit'],
            89 => ['', 20, 32, 1000, Defs::MF_NOBLOCKMAP | Defs::MF_NOSECTOR, 'braineye', null],
            2035 => ['BAR1A0', 10, 42, 20, $enemy, 'enemy', null],
            2011 => ['STIMA0', 20, 16, 0, $special, 'health', 10],
            2012 => ['MEDIA0', 20, 16, 0, $special, 'health', 25],
            2014 => ['BON1A0', 20, 16, 0, $special, 'bonus_h', 1],
            2015 => ['BON2A0', 20, 16, 0, $special, 'bonus_a', 1],
            2018 => ['ARM1A0', 20, 16, 0, $special, 'armor', 1],
            2019 => ['ARM2A0', 20, 16, 0, $special, 'armor', 2],
            83 => ['MEGAA0', 20, 16, 0, $special, 'mega', 0],
            2013 => ['SOULA0', 20, 16, 0, $special, 'soul', 0],
            2022 => ['PINVA0', 20, 16, 0, $special, 'item', 'Invulnerability'],
            2023 => ['PSTRA0', 20, 16, 0, $special, 'berserk', 0],
            2024 => ['PINSA0', 20, 16, 0, $special, 'item', 'Partial invisibility'],
            2025 => ['SUITA0', 20, 16, 0, $special, 'item', 'Radiation shielding'],
            2026 => ['PMAPA0', 20, 16, 0, $special, 'item', 'Computer area map'],
            2045 => ['PVISA0', 20, 16, 0, $special, 'item', 'Light amplification visor'],
            5 => ['BKEYA0', 20, 16, 0, $special, 'key', Defs::IT_BLUECARD],
            6 => ['YKEYA0', 20, 16, 0, $special, 'key', Defs::IT_YELLOWCARD],
            13 => ['RKEYA0', 20, 16, 0, $special, 'key', Defs::IT_REDCARD],
            40 => ['BSKUA0', 20, 16, 0, $special, 'key', Defs::IT_BLUESKULL],
            39 => ['YSKUA0', 20, 16, 0, $special, 'key', Defs::IT_YELLOWSKULL],
            38 => ['RSKUA0', 20, 16, 0, $special, 'key', Defs::IT_REDSKULL],
            2001 => ['SHOTA0', 20, 16, 0, $special, 'weapon', Defs::WP_SHOTGUN],
            82 => ['SGN2A0', 20, 16, 0, $special, 'weapon', Defs::WP_SUPERSHOTGUN],
            2002 => ['MGUNA0', 20, 16, 0, $special, 'weapon', Defs::WP_CHAINGUN],
            2003 => ['LAUNA0', 20, 16, 0, $special, 'weapon', Defs::WP_MISSILE],
            2004 => ['PLASA0', 20, 16, 0, $special, 'weapon', Defs::WP_PLASMA],
            2005 => ['CSAWA0', 20, 16, 0, $special, 'weapon', Defs::WP_CHAINSAW],
            2006 => ['BFUGA0', 20, 16, 0, $special, 'weapon', Defs::WP_BFG],
            2007 => ['CLIPA0', 20, 16, 0, $special, 'ammo', [Defs::AM_CLIP, 1]],
            2048 => ['AMMOA0', 20, 16, 0, $special, 'ammo', [Defs::AM_CLIP, 5]],
            2008 => ['SHELA0', 20, 16, 0, $special, 'ammo', [Defs::AM_SHELL, 1]],
            2049 => ['SBOXA0', 20, 16, 0, $special, 'ammo', [Defs::AM_SHELL, 5]],
            2047 => ['CELLA0', 20, 16, 0, $special, 'ammo', [Defs::AM_CELL, 1]],
            17 => ['CELPA0', 20, 16, 0, $special, 'ammo', [Defs::AM_CELL, 5]],
            2010 => ['ROCKA0', 20, 16, 0, $special, 'ammo', [Defs::AM_MISL, 1]],
            2046 => ['BROKA0', 20, 16, 0, $special, 'ammo', [Defs::AM_MISL, 5]],
            8 => ['BPAKA0', 20, 16, 0, $special, 'backpack', 0],
            2028 => ['COLUA0', 16, 16, 0, $solid, 'deco', null],
            85 => ['TLMPA0', 16, 16, 0, $solid, 'deco', null],
            86 => ['TLP2A0', 16, 16, 0, $solid, 'deco', null],
            48 => ['ELECA0', 16, 16, 0, $solid, 'deco', null],
            30 => ['COL1A0', 16, 16, 0, $solid, 'deco', null],
            31 => ['COL2A0', 16, 16, 0, $solid, 'deco', null],
            32 => ['COL3A0', 16, 16, 0, $solid, 'deco', null],
            33 => ['COL4A0', 16, 16, 0, $solid, 'deco', null],
            35 => ['CANDA0', 16, 16, 0, 0, 'deco', null],
            37 => ['CBRAA0', 16, 16, 0, $solid, 'deco', null],
            41 => ['CEYEA0', 16, 16, 0, $solid, 'deco', null],
            42 => ['FSKUA0', 16, 16, 0, $solid, 'deco', null],
            43 => ['TRE1A0', 16, 16, 0, $solid, 'deco', null],
            47 => ['SMITA0', 16, 16, 0, $solid, 'deco', null],
            54 => ['TRE2A0', 32, 16, 0, $solid, 'deco', null],
            10 => ['PLAYW0', 16, 16, 0, 0, 'deco', null],
            12 => ['PLAYW0', 16, 16, 0, 0, 'deco', null],
            15 => ['PLAYN0', 16, 16, 0, 0, 'deco', null],
            24 => ['POL5A0', 16, 16, 0, 0, 'deco', null],
            25 => ['POL1A0', 16, 16, 0, $solid, 'deco', null],
            26 => ['POL6A0', 16, 16, 0, $solid, 'deco', null],
            27 => ['POL4A0', 16, 16, 0, $solid, 'deco', null],
            28 => ['POL2A0', 16, 16, 0, $solid, 'deco', null],
            34 => ['CANDA0', 16, 16, 0, 0, 'deco', null],
            14 => ['TFOGA0', 20, 16, 0, 0, 'teleport', null],
            36 => ['CBRAA0', 16, 16, 0, $solid, 'deco', null],
            46 => ['TREDA0', 16, 16, 0, 0, 'deco', null],
            55 => ['GOR1A0', 16, 16, 0, 0, 'deco', null],
            56 => ['GOR2A0', 16, 16, 0, 0, 'deco', null],
            57 => ['GOR3A0', 16, 16, 0, 0, 'deco', null],
            59 => ['GOR5A0', 16, 16, 0, 0, 'deco', null],
        ];
        return self::$infoCache;
    }

    public static function patchHitPoints(int $doomed, int $hp): void
    {
        self::infoTable();
        if (isset(self::$infoCache[$doomed])) {
            self::$infoCache[$doomed][3] = $hp;
        }
    }

    public static function skillBit(int $skill): int
    {
        if ($skill <= Defs::SK_EASY) {
            return 1;
        }
        return ($skill === Defs::SK_NIGHTMARE || $skill >= Defs::SK_HARD) ? 4 : 2;
    }

    /** @return array{int,int} */
    public static function spawnMapThings(World $world, int $skill, ?object $game = null): array
    {
        $bit = self::skillBit($skill);
        $kills = 0;
        $items = 0;
        $table = self::infoTable();
        $nomonsters = (bool) ($game->nomonsters ?? false);
        foreach ($world->things as $mt) {
            if ($mt->type === 11) {
                continue;
            }
            if (in_array($mt->type, [1, 2, 3, 4], true)) {
                if ($mt->type === 1 && $game !== null && $game->player === null) {
                    $game->player = Player::spawnPlayer($world, $mt);
                }
                continue;
            }
            if (!($mt->options & $bit) || ($mt->options & 16)) {
                continue;
            }
            $typ = Info::mobjTypeForDoomednum($mt->type);
            if ($typ < 0) {
                continue;
            }
            Info::boot();
            $flags = (int) Info::$liveMobjinfo[$typ][Info::MI_FLAGS];
            if ($nomonsters && (($flags & Defs::MF_COUNTKILL) || $typ === Info::MT_SKULL)) {
                continue;
            }
            $z = ($flags & Defs::MF_SPAWNCEILING) ? Thinker::ONCEILINGZ : Thinker::ONFLOORZ;
            $mo = Thinker::spawnMobj($world, $mt->x * Defs::FRACUNIT, $mt->y * Defs::FRACUNIT, $z, $typ, $game);
            if ($mo->tics > 0) {
                $mo->tics = 1 + (Enemy::publicRandom() % $mo->tics);
            }
            $mo->angle = Compat::asU32(intdiv($mt->angle, 45) * 0x20000000);
            $mo->spawnpoint = $mt;
            if ($mt->options & Defs::MTF_AMBUSH) {
                $mo->flags |= Defs::MF_AMBUSH;
            }
            if (isset($table[$mt->type])) {
                [, , , , , $kind, $extra] = $table[$mt->type];
                $mo->info = [$kind, $extra];
            }
            if ($mo->flags & Defs::MF_COUNTKILL) {
                ++$kills;
            }
            if ($mo->flags & Defs::MF_COUNTITEM) {
                ++$items;
            }
        }
        return [$kills, $items];
    }

    public static function giveAmmo(Player $p, int $ammo, int $num): bool
    {
        if ($p->ammo[$ammo] >= $p->maxammo[$ammo]) {
            return false;
        }
        $p->ammo[$ammo] = min($p->maxammo[$ammo], $p->ammo[$ammo] + Player::CLIPAMMO[$ammo] * $num);
        return true;
    }

    public static function touchSpecial(object $game, self $special, self $toucher): void
    {
        $p = $toucher->player;
        if ($p === null || !$special->alive) {
            return;
        }
        $pickup = self::infoTable()[$special->doomednum] ?? null;
        if ($special->info && $special->info[0]) {
            [$kind, $extra] = $special->info;
        } elseif ($pickup) {
            [$kind, $extra] = [$pickup[5], $pickup[6]];
        } else {
            [$kind, $extra] = ['deco', null];
        }
        $taken = true;
        $sfx = 'itemup';
        if ($kind === 'health') {
            if ($p->health >= Defs::MAXHEALTH) {
                $taken = false;
            } else {
                $p->health = min(Defs::MAXHEALTH, $p->health + (int) $extra);
                $p->mo->health = $p->health;
                $p->setMessage($extra === 10 ? 'Picked up a stimpack.' : 'Picked up a medikit.');
            }
        } elseif ($kind === 'bonus_h') {
            $p->health = min(200, $p->health + 1);
            $p->mo->health = $p->health;
            $p->setMessage('You pick up a health bonus.');
        } elseif ($kind === 'bonus_a') {
            $p->armorpoints = min(200, $p->armorpoints + 1);
            if (!$p->armortype) {
                $p->armortype = 1;
            }
            $p->setMessage('You pick up an armor bonus.');
        } elseif ($kind === 'armor') {
            $points = $extra === 1 ? 100 : 200;
            if ($p->armorpoints >= $points) {
                $taken = false;
            } else {
                $p->armorpoints = $points;
                $p->armortype = (int) $extra;
                $p->setMessage($extra === 1 ? 'Picked up the armor.' : 'Picked up the MegaArmor!');
            }
        } elseif ($kind === 'soul') {
            $p->health = min(200, $p->health + 100);
            $p->mo->health = $p->health;
            $p->setMessage('Supercharge!');
        } elseif ($kind === 'mega') {
            $p->health = $p->mo->health = 200;
            $p->armorpoints = 200;
            $p->armortype = 2;
            $p->setMessage('MegaSphere!');
        } elseif ($kind === 'berserk') {
            $taken = Player::givePower($p, Defs::PW_STRENGTH);
            if ($taken) {
                if ($p->readyweapon !== Defs::WP_FIST) {
                    $p->pendingweapon = Defs::WP_FIST;
                }
                $p->setMessage('Berserk!');
                $sfx = 'getpow';
            }
        } elseif ($kind === 'key') {
            $p->cards[(int) $extra] = true;
            $names = [
                Defs::IT_BLUECARD => 'You picked up a blue keycard.',
                Defs::IT_YELLOWCARD => 'You picked up a yellow keycard.',
                Defs::IT_REDCARD => 'You picked up a red keycard.',
                Defs::IT_BLUESKULL => 'You picked up a blue skull key.',
                Defs::IT_YELLOWSKULL => 'You picked up a yellow skull key.',
                Defs::IT_REDSKULL => 'You picked up a red skull key.',
            ];
            $p->setMessage($names[(int) $extra] ?? 'You picked up a key.');
        } elseif ($kind === 'weapon') {
            $w = (int) $extra;
            $p->weaponowned[$w] = true;
            if ($p->readyweapon !== $w) {
                $p->pendingweapon = $w;
            }
            if (in_array($w, [Defs::WP_SHOTGUN, Defs::WP_SUPERSHOTGUN], true)) {
                self::giveAmmo($p, Defs::AM_SHELL, 1);
            } elseif ($w === Defs::WP_CHAINGUN) {
                self::giveAmmo($p, Defs::AM_CLIP, 1);
            } elseif ($w === Defs::WP_MISSILE) {
                self::giveAmmo($p, Defs::AM_MISL, 1);
            } elseif (in_array($w, [Defs::WP_PLASMA, Defs::WP_BFG], true)) {
                self::giveAmmo($p, Defs::AM_CELL, 1);
            }
            $names = [
                Defs::WP_SHOTGUN => 'You got the shotgun!',
                Defs::WP_SUPERSHOTGUN => 'You got the super shotgun!',
                Defs::WP_CHAINGUN => 'You got the chaingun!',
                Defs::WP_MISSILE => 'You got the rocket launcher!',
                Defs::WP_PLASMA => 'You got the plasma gun!',
                Defs::WP_BFG => 'You got the BFG9000!',
                Defs::WP_CHAINSAW => 'A chainsaw!  Find some meat!',
            ];
            $p->setMessage($names[$w] ?? 'You got a weapon!');
            $game->startSound('wpnup');
        } elseif ($kind === 'ammo') {
            [$ammo, $num] = $extra;
            $taken = self::giveAmmo($p, $ammo, $num);
            if ($taken) {
                $p->setMessage('Picked up some ammo.');
            }
        } elseif ($kind === 'backpack') {
            for ($i = 0; $i < 4; ++$i) {
                if ($p->maxammo[$i] < 400) {
                    $p->maxammo[$i] *= 2;
                }
                self::giveAmmo($p, $i, 1);
            }
            $p->setMessage('You picked up a backpack full of ammo!');
        } elseif ($kind === 'item') {
            $powers = [
                'Invulnerability' => [Defs::PW_INVULNERABILITY, 'Invulnerability!'],
                'Partial invisibility' => [Defs::PW_INVISIBILITY, 'Partial Invisibility'],
                'Radiation shielding' => [Defs::PW_IRONFEET, 'Radiation Shielding Suit'],
                'Computer area map' => [Defs::PW_ALLMAP, 'Computer Area Map'],
                'Light amplification visor' => [Defs::PW_INFRARED, 'Light Amplification Visor'],
            ];
            $pair = $powers[(string) $extra] ?? null;
            if ($pair === null) {
                $p->setMessage((string) $extra);
            } else {
                $taken = Player::givePower($p, $pair[0]);
                if ($taken) {
                    $p->setMessage($pair[1]);
                    $sfx = 'getpow';
                }
            }
        } else {
            $taken = false;
        }
        if ($taken) {
            if ($kind !== 'weapon') {
                $game->startSound($sfx);
            }
            $p->bonuscount += 6;
            if ($special->flags & Defs::MF_COUNTITEM) {
                ++$p->itemcount;
            }
            $special->alive = false;
            $special->flags = 0;
            $i = array_search($special, $game->world->mobjs, true);
            if ($i !== false) {
                array_splice($game->world->mobjs, $i, 1);
            }
        }
    }
}
