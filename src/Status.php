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
    /** @var list<string> */
    private array $faces = [];
    private string $godFace;
    private ?string $deadFace;
    /** @var list<?string> */
    private array $font = [];

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
        $fallback = $wad->cacheLumpName('STFST00');
        for ($pain = 0; $pain < 5; ++$pain) {
            $this->faces[] = $this->optional("STFST{$pain}0") ?? $fallback;
        }
        $this->godFace = $this->optional('STFGOD0') ?? $fallback;
        $this->deadFace = $this->optional('STFDEAD0');
        for ($ch = Defs::HU_FONTSTART; $ch <= Defs::HU_FONTEND; ++$ch) {
            $this->font[] = $this->optional(sprintf('STCFN%03d', $ch));
        }
    }

    private function optional(string $name): ?string
    {
        $n = $this->wad->checkNumForName($name);
        return $n >= 0 ? $this->wad->cacheLumpNum($n) : null;
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

        $health = min(100, max(0, (int) $player->health));
        $pain = $player->health <= 0 ? 4 : min(4, intdiv((100 - $health) * 5, 101));
        if ($player->health <= 0) {
            VVideo::drawPatch($fb, self::FACE_X, self::FACE_Y, $this->deadFace ?? $this->faces[4]);
        } elseif (($player->cheats & Defs::CF_GODMODE) !== 0) {
            VVideo::drawPatch($fb, self::FACE_X, self::FACE_Y, $this->godFace);
        } else {
            VVideo::drawPatch($fb, self::FACE_X, self::FACE_Y, $this->faces[$pain]);
        }

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
