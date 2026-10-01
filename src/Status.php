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

/** Status bar (st_stuff / st_lib), including vanilla HUD face widget. */
final class Status
{
    private const AMMO_X = 44;
    private const AMMO_Y = 171;
    private const HEALTH_X = 90;
    private const HEALTH_Y = 171;
    private const ARMOR_X = 221;
    private const ARMOR_Y = 171;
    private const FACE_X = 143;
    private const FACE_Y = 168;
    private const ARMS_X = 111;
    private const ARMS_Y = 172;
    private const KEY_X = 239;

    private const ST_NUMPAINFACES = 5;
    private const ST_NUMSTRAIGHTFACES = 3;
    private const ST_NUMTURNFACES = 2;
    private const ST_NUMSPECIALFACES = 3;
    private const ST_FACESTRIDE = self::ST_NUMSTRAIGHTFACES + self::ST_NUMTURNFACES + self::ST_NUMSPECIALFACES;
    private const ST_TURNOFFSET = self::ST_NUMSTRAIGHTFACES;
    private const ST_OUCHOFFSET = self::ST_TURNOFFSET + self::ST_NUMTURNFACES;
    private const ST_EVILGRINOFFSET = self::ST_OUCHOFFSET + 1;
    private const ST_RAMPAGEOFFSET = self::ST_EVILGRINOFFSET + 1;
    private const ST_GODFACE = self::ST_NUMPAINFACES * self::ST_FACESTRIDE;
    private const ST_DEADFACE = self::ST_GODFACE + 1;
    private const ST_EVILGRINCOUNT = 70;
    private const ST_STRAIGHTFACECOUNT = 17;
    private const ST_TURNCOUNT = 35;
    private const ST_RAMPAGEDELAY = 70;
    private const ST_MUCHPAIN = 20;

    private object $wad;
    private string $sbar;
    /** @var list<string> */
    private array $tallNum = [];
    /** @var list<string> */
    private array $shortNum = [];
    private string $percent;
    /** @var list<?string> */
    private array $keys = [];
    private ?string $armsBg;
    /** @var list<string> */
    private array $armsOff = [];
    /** @var list<?string> */
    private array $faces = [];
    private string $fallbackFace;
    /** @var list<?string> */
    private array $font = [];

    private int $faceIndex = 0;
    private int $faceCount = 0;
    private int $facePriority = 0;
    private int $oldHealth = -1;
    private int $painOldHealth = -1;
    private int $lastCalc = 0;
    private int $lastAttackDown = -1;
    /** @var bool[] */
    private array $oldWeaponsOwned = [];
    private int $rnd = 1;

    public function __construct(object $wad)
    {
        $this->wad = $wad;
        $this->sbar = $wad->cacheLumpName('STBAR');
        for ($i = 0; $i < 10; ++$i) {
            $this->tallNum[] = $wad->cacheLumpName("STTNUM{$i}");
            $this->shortNum[] = $wad->cacheLumpName("STYSNUM{$i}");
        }
        $this->percent = $wad->cacheLumpName('STTPRCNT');
        for ($i = 0; $i < 6; ++$i) {
            $this->keys[] = $this->optional("STKEYS{$i}");
        }
        $this->armsBg = $this->optional('STARMS');
        for ($i = 2; $i < 8; ++$i) {
            $this->armsOff[] = $wad->cacheLumpName("STGNUM{$i}");
        }
        $this->fallbackFace = $wad->cacheLumpName('STFST00');
        for ($pain = 0; $pain < self::ST_NUMPAINFACES; ++$pain) {
            for ($look = 0; $look < self::ST_NUMSTRAIGHTFACES; ++$look) {
                $this->faces[] = $this->optional("STFST{$pain}{$look}");
            }
            $this->faces[] = $this->optional("STFTR{$pain}0");
            $this->faces[] = $this->optional("STFTL{$pain}0");
            $this->faces[] = $this->optional("STFOUCH{$pain}");
            $this->faces[] = $this->optional("STFEVL{$pain}");
            $this->faces[] = $this->optional("STFKILL{$pain}");
        }
        $this->faces[] = $this->optional('STFGOD0');
        $this->faces[] = $this->optional('STFDEAD0');
        for ($ch = Defs::HU_FONTSTART; $ch <= Defs::HU_FONTEND; ++$ch) {
            $this->font[] = $this->optional(sprintf('STCFN%03d', $ch));
        }
        $this->reset(null);
    }

    public function reset(?object $player): void
    {
        $this->faceIndex = 0;
        $this->faceCount = 0;
        $this->facePriority = 0;
        $this->oldHealth = -1;
        $this->painOldHealth = -1;
        $this->lastCalc = 0;
        $this->lastAttackDown = -1;
        $this->oldWeaponsOwned = $player !== null ? $player->weaponowned : array_fill(0, 9, false);
    }

    public function ticker(?object $player): void
    {
        if ($player === null) {
            return;
        }
        $this->rnd = ($this->rnd * 1103515245 + 12345) & Compat::MASK32;
        $stRandom = ($this->rnd >> 16) & 255;
        $this->updateFaceWidget($player, $stRandom);
        $this->oldHealth = $player->health;
    }

    private function optional(string $name): ?string
    {
        $n = $this->wad->checkNumForName($name);
        return $n >= 0 ? $this->wad->cacheLumpNum($n) : null;
    }

    private function facePatch(int $index): string
    {
        $patch = $this->faces[$index] ?? null;
        return $patch ?? $this->fallbackFace;
    }

    private function calcPainOffset(object $player): int
    {
        $health = min(100, max(0, (int) $player->health));
        if ($health !== $this->painOldHealth) {
            $this->lastCalc = self::ST_FACESTRIDE * intdiv((100 - $health) * self::ST_NUMPAINFACES, 101);
            $this->painOldHealth = $health;
        }
        return $this->lastCalc;
    }

    private function updateFaceWidget(object $player, int $stRandom): void
    {
        if ($this->facePriority < 10 && $player->health <= 0) {
            $this->facePriority = 9;
            $this->faceIndex = self::ST_DEADFACE;
            $this->faceCount = 1;
        }

        if ($this->facePriority < 9 && $player->bonuscount) {
            $doEvilGrin = false;
            $n = min(count($this->oldWeaponsOwned), count($player->weaponowned));
            for ($i = 0; $i < $n; ++$i) {
                if ($this->oldWeaponsOwned[$i] !== $player->weaponowned[$i]) {
                    $doEvilGrin = true;
                    $this->oldWeaponsOwned[$i] = $player->weaponowned[$i];
                }
            }
            if ($doEvilGrin) {
                $this->facePriority = 8;
                $this->faceCount = self::ST_EVILGRINCOUNT;
                $this->faceIndex = $this->calcPainOffset($player) + self::ST_EVILGRINOFFSET;
            }
        }

        if ($this->facePriority < 8 && $player->damagecount && $player->attacker !== null
            && $player->mo !== null && $player->attacker !== $player->mo) {
            $this->facePriority = 7;
            if ($player->health - $this->oldHealth > self::ST_MUCHPAIN) {
                $this->faceCount = self::ST_TURNCOUNT;
                $this->faceIndex = $this->calcPainOffset($player) + self::ST_OUCHOFFSET;
            } else {
                $badguyangle = Collision::angleTo(
                    $player->mo->x, $player->mo->y, $player->attacker->x, $player->attacker->y
                );
                if (Compat::asU32($badguyangle) > Compat::asU32($player->mo->angle)) {
                    $diffang = Compat::asU32($badguyangle - $player->mo->angle);
                    $turnRight = $diffang > Compat::asU32(Defs::ANG180);
                } else {
                    $diffang = Compat::asU32($player->mo->angle - $badguyangle);
                    $turnRight = $diffang <= Compat::asU32(Defs::ANG180);
                }
                $this->faceCount = self::ST_TURNCOUNT;
                $this->faceIndex = $this->calcPainOffset($player);
                if ($diffang < Compat::asU32(Defs::ANG45)) {
                    $this->faceIndex += self::ST_RAMPAGEOFFSET;
                } elseif ($turnRight) {
                    $this->faceIndex += self::ST_TURNOFFSET;
                } else {
                    $this->faceIndex += self::ST_TURNOFFSET + 1;
                }
            }
        }

        if ($this->facePriority < 7 && $player->damagecount) {
            if ($player->health - $this->oldHealth > self::ST_MUCHPAIN) {
                $this->facePriority = 7;
                $this->faceCount = self::ST_TURNCOUNT;
                $this->faceIndex = $this->calcPainOffset($player) + self::ST_OUCHOFFSET;
            } else {
                $this->facePriority = 6;
                $this->faceCount = self::ST_TURNCOUNT;
                $this->faceIndex = $this->calcPainOffset($player) + self::ST_RAMPAGEOFFSET;
            }
        }

        if ($this->facePriority < 6) {
            if ($player->attackdown) {
                if ($this->lastAttackDown === -1) {
                    $this->lastAttackDown = self::ST_RAMPAGEDELAY;
                } else {
                    --$this->lastAttackDown;
                    if ($this->lastAttackDown === 0) {
                        $this->facePriority = 5;
                        $this->faceIndex = $this->calcPainOffset($player) + self::ST_RAMPAGEOFFSET;
                        $this->faceCount = 1;
                        $this->lastAttackDown = 1;
                    }
                }
            } else {
                $this->lastAttackDown = -1;
            }
        }

        if ($this->facePriority < 5 && (($player->cheats & Defs::CF_GODMODE) !== 0 || $player->powers[Defs::PW_INVULNERABILITY])) {
            $this->facePriority = 4;
            $this->faceIndex = self::ST_GODFACE;
            $this->faceCount = 1;
        }

        if ($this->faceCount === 0) {
            $this->faceIndex = $this->calcPainOffset($player) + ($stRandom % 3);
            $this->faceCount = self::ST_STRAIGHTFACECOUNT;
            $this->facePriority = 0;
        }
        --$this->faceCount;
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb, object $player, bool $showMessages = true): void
    {
        VVideo::drawPatch($fb, 0, 168, $this->sbar);
        if ($this->armsBg !== null) {
            VVideo::drawPatch($fb, 104, 168, $this->armsBg);
        }
        $weaponAmmo = Player::WEAPON_AMMO[$player->readyweapon] ?? null;
        $ammo = $weaponAmmo === null ? 0 : ($player->ammo[$weaponAmmo] ?? 0);
        $this->number($fb, self::AMMO_X, self::AMMO_Y, $ammo, 3, $this->tallNum);
        $this->number($fb, self::HEALTH_X, self::HEALTH_Y, $player->health, 3, $this->tallNum);
        VVideo::drawPatch($fb, self::HEALTH_X, self::HEALTH_Y, $this->percent);
        $this->number($fb, self::ARMOR_X, self::ARMOR_Y, $player->armorpoints, 3, $this->tallNum);
        VVideo::drawPatch($fb, self::ARMOR_X, self::ARMOR_Y, $this->percent);

        $owned = [
            ($player->weaponowned[Defs::WP_SHOTGUN] ?? false)
                || ($player->weaponowned[Defs::WP_SUPERSHOTGUN] ?? false),
            $player->weaponowned[Defs::WP_CHAINGUN] ?? false,
            $player->weaponowned[Defs::WP_MISSILE] ?? false,
            $player->weaponowned[Defs::WP_PLASMA] ?? false,
            $player->weaponowned[Defs::WP_BFG] ?? false,
            false,
        ];
        for ($i = 0; $i < 6; ++$i) {
            $x = self::ARMS_X + ($i % 3) * 12;
            $y = self::ARMS_Y + intdiv($i, 3) * 10;
            if ($owned[$i]) {
                $this->digit($fb, $x, $y, $i + 2, $this->shortNum);
            } else {
                VVideo::drawPatch($fb, $x, $y, $this->armsOff[$i]);
            }
        }

        VVideo::drawPatch($fb, self::FACE_X, self::FACE_Y, $this->facePatch($this->faceIndex));

        $slots = [
            [Defs::IT_BLUECARD, Defs::IT_BLUESKULL],
            [Defs::IT_YELLOWCARD, Defs::IT_YELLOWSKULL],
            [Defs::IT_REDCARD, Defs::IT_REDSKULL],
        ];
        foreach ($slots as $slot => [$card, $skull]) {
            if (($player->cards[$card] ?? false) || ($player->cards[$skull] ?? false)) {
                $index = ($player->cards[$skull] ?? false) ? $skull : $card;
                if ($this->keys[$index] !== null) {
                    VVideo::drawPatch($fb, self::KEY_X, 171 + $slot * 10, $this->keys[$index]);
                }
            }
        }
        $order = [Defs::AM_CLIP, Defs::AM_SHELL, Defs::AM_CELL, Defs::AM_MISL];
        $ammoPos = [[288, 173], [288, 179], [288, 191], [288, 185]];
        $maxPos = [[314, 173], [314, 179], [314, 191], [314, 185]];
        foreach ($order as $i => $type) {
            $this->number($fb, $ammoPos[$i][0], $ammoPos[$i][1], $player->ammo[$type], 3, $this->shortNum);
            $this->number($fb, $maxPos[$i][0], $maxPos[$i][1], $player->maxammo[$type], 3, $this->shortNum);
        }
        if ($showMessages && $player->message !== '') {
            $this->drawText($fb, 0, 0, $player->message);
        }
    }

    /** @param array<int,int> $fb @param list<string> $font */
    private function digit(array &$fb, int $x, int $y, int $n, array $font): void
    {
        VVideo::drawPatch($fb, $x, $y, $font[max(0, min(9, $n))]);
    }

    /** @param array<int,int> $fb @param list<string> $font */
    private function number(array &$fb, int $x, int $y, int $value, int $digits, array $font): void
    {
        [$width] = VVideo::patchSize($font[0]);
        $x -= $width;
        $value = abs($value);
        for ($i = 0; $i < $digits; ++$i) {
            VVideo::drawPatch($fb, $x, $y, $font[$value % 10]);
            $x -= $width;
            $value = intdiv($value, 10);
            if ($value === 0) {
                break;
            }
        }
    }

    /** @param array<int,int> $fb */
    public function drawText(array &$fb, int $x, int $y, string $text): void
    {
        foreach (str_split(strtoupper($text)) as $ch) {
            $index = ord($ch) - Defs::HU_FONTSTART;
            $patch = $index >= 0 && $index < count($this->font) ? $this->font[$index] : null;
            if ($patch === null) {
                $x += 4;
                continue;
            }
            VVideo::drawPatch($fb, $x, $y, $patch);
            [$width] = VVideo::patchSize($patch);
            $x += $width;
        }
    }
}

class_alias(Status::class, __NAMESPACE__ . '\\StatusBar');
