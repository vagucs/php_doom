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

final class Finale
{
    private const TEXT_SPEED = 3;
    private const TEXT_WAIT = 250;
    private const TEXT = 0;
    private const ART = 1;
    private const E1 = "Once you beat the big badasses and\nclean out the moon base you're supposed\nto win, aren't you? Aren't you? Where's\nyour fat reward and ticket home? What\nthe hell is this? It's not supposed to\nend this way!\n\nIt stinks like rotten meat, but looks\nlike the lost Deimos base.  Looks like\nyou're stuck on The Shores of Hell.\nThe only way out is through.\n\nTo continue the DOOM experience, play\nThe Shores of Hell and its amazing\nsequel, Inferno!\n";
    private const E2 = "You've done it! The hideous cyber-\ndemon lord that ruled the lost Deimos\nmoon base has been slain and you\nare triumphant! But ... where are\nyou? You clamber to the edge of the\nmoon and look down to see the awful\ntruth.\n\nDeimos floats above Hell itself!\nYou've never heard of anyone escaping\nfrom Hell, but you'll make the bastards\nsorry they ever heard of you! Quickly,\nyou rappel down to the surface of\nHell.\n\nNow, it's on to the final chapter of\nDOOM! -- Inferno.\n";
    private const E3 = "The loathsome spiderdemon that\nmasterminded the invasion of the moon\nbases and caused so much death has had\nits ass kicked for all time.\n\nA hidden doorway opens and you enter.\nYou've proven too tough for Hell to\ncontain, and now Hell at last plays\nfair -- for you emerge from the door\nto see the green fields of Earth!\nHome at last.\n\nYou wonder what's been happening on\nEarth while you were battling evil\nunleashed. It's good that no Hell-\nspawn could have come through that\ndoor with you ...\n";
    private const C1 = "You have won! Your victory has enabled\nhumankind to evacuate Earth and escape\nthe nightmare.  Now you are the only\nhuman left on the face of the planet.\nCan you defeat the final enemy and\nreturn to Earth, or will you just\nrot here with the rest of the walking\ndead?\n";

    private object $game;
    private int $stage = self::TEXT;
    private int $count = 0;
    public bool $done = false;
    private string $text;
    private string $flat;
    private ?string $flatLump = null;
    private ?string $art = null;
    private ?string $pfub1 = null;
    private ?string $pfub2 = null;
    private int $lastBunnyStage = -1;

    public function __construct(object $game)
    {
        $this->game = $game;
        $commercial = $game->wad->checkNumForName('MAP01') >= 0;
        if ($commercial) {
            $this->text = self::C1;
            $this->flat = 'SLIME16';
            $game->sound->changeMusic('read_m', true);
        } else {
            $this->text = [1 => self::E1, 2 => self::E2, 3 => self::E3][$game->episode] ?? self::E1;
            $this->flat = [1 => 'FLOOR4_8', 2 => 'SFLR6_1', 3 => 'MFLR8_4', 4 => 'MFLR8_3'][$game->episode] ?? 'FLOOR4_8';
            $game->sound->changeMusic('victor', true);
        }
        $this->flatLump = $this->lump($this->flat);
        $art = match ($game->episode) {
            2 => 'VICTORY2',
            4 => 'ENDPIC',
            default => $this->has('CREDIT') ? 'CREDIT' : 'HELP2',
        };
        $this->art = $this->lump($art) ?? $this->lump('HELP1');
        if ($game->episode === 3 && !$commercial) {
            $this->pfub1 = $this->lump('PFUB1');
            $this->pfub2 = $this->lump('PFUB2');
        }
    }

    public function ticker(): void
    {
        ++$this->count;
        if ($this->stage === self::TEXT && $this->count > strlen($this->text) * self::TEXT_SPEED + self::TEXT_WAIT) {
            $this->stage = self::ART;
            $this->count = 0;
            $this->game->forceWipe = true;
            if ($this->game->episode === 3) {
                $this->game->sound->changeMusic('bunny', true);
            }
        } elseif ($this->stage === self::ART && $this->wantSkip() && $this->count > 10) {
            $this->done = true;
        }
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb): void
    {
        if ($this->stage === self::ART) {
            if ($this->game->episode === 3 && $this->pfub1 !== null && $this->pfub2 !== null) {
                $this->drawBunny($fb);
            } elseif ($this->art !== null) {
                VVideo::fill($fb, 0);
                VVideo::drawPatch($fb, 0, 0, $this->art);
            }
            return;
        }
        $this->fillFlat($fb);
        $shown = intdiv($this->count, self::TEXT_SPEED);
        $x = $y = 10;
        foreach (str_split($this->text) as $i => $char) {
            if ($i >= $shown) {
                break;
            }
            if ($char === "\n") {
                $x = 10;
                $y += 11;
                continue;
            }
            $code = ord(strtoupper($char));
            if ($char === ' ' || $code < Defs::HU_FONTSTART || $code > Defs::HU_FONTEND) {
                $x += 4;
                continue;
            }
            $patch = $this->lump(sprintf('STCFN%03d', $code));
            if ($patch === null) {
                $x += 4;
                continue;
            }
            [$width] = VVideo::patchSize($patch);
            if ($x + $width > Defs::SCREENWIDTH) {
                break;
            }
            VVideo::drawPatch($fb, $x, $y, $patch);
            $x += $width;
        }
    }

    /** @param array<int,int> $fb */
    private function fillFlat(array &$fb): void
    {
        if ($this->flatLump === null || strlen($this->flatLump) < 4096) {
            VVideo::fill($fb, 0);
            return;
        }
        for ($y = 0; $y < Defs::SCREENHEIGHT; ++$y) {
            $row = ($y & 63) << 6;
            for ($x = 0; $x < Defs::SCREENWIDTH; ++$x) {
                $fb[$y * Defs::SCREENWIDTH + $x] = ord($this->flatLump[$row + ($x & 63)]);
            }
        }
    }

    /** @param array<int,int> $fb */
    private function drawBunny(array &$fb): void
    {
        VVideo::fill($fb, 0);
        $scroll = max(0, min(320, 320 - intdiv($this->count - 230, 2)));
        for ($x = 0; $x < Defs::SCREENWIDTH; ++$x) {
            $column = $x + $scroll;
            $this->drawPatchColumn($fb, $x, $column < 320 ? $this->pfub2 : $this->pfub1, $column < 320 ? $column : $column - 320);
        }
        if ($this->count < 1130) {
            return;
        }
        $stage = $this->count < 1180 ? 0 : min(6, intdiv($this->count - 1180, 5));
        if ($stage > $this->lastBunnyStage) {
            $this->game->sound->play('pistol');
            $this->lastBunnyStage = $stage;
        }
        $patch = $this->lump("END{$stage}");
        if ($patch !== null) {
            VVideo::drawPatch($fb, intdiv(320 - 104, 2), intdiv(200 - 64, 2), $patch);
        }
    }

    /** @param array<int,int> $fb */
    private function drawPatchColumn(array &$fb, int $x, string $patch, int $column): void
    {
        if ($column < 0) {
            return;
        }
        $offsetPos = 8 + $column * 4;
        if ($offsetPos + 4 > strlen($patch)) {
            return;
        }
        $offset = unpack('V', substr($patch, $offsetPos, 4))[1];
        while ($offset < strlen($patch) && ord($patch[$offset]) !== 255) {
            $top = ord($patch[$offset]);
            $length = ord($patch[$offset + 1]);
            $source = $offset + 3;
            for ($i = 0; $i < $length && $top + $i < 200; ++$i) {
                $fb[($top + $i) * 320 + $x] = ord($patch[$source + $i]);
            }
            $offset += $length + 4;
        }
    }

    private function wantSkip(): bool
    {
        if ($this->game->menu?->active) {
            return false;
        }
        foreach ([Keys::LCTRL, Keys::RCTRL, Keys::SPACE, Keys::RETURN, Keys::KP_ENTER, ord('e')] as $key) {
            if (isset($this->game->keys[$key])) {
                return true;
            }
        }
        return false;
    }

    private function has(string $name): bool
    {
        return $this->game->wad->checkNumForName($name) >= 0;
    }

    private function lump(string $name): ?string
    {
        $n = $this->game->wad->checkNumForName($name);
        return $n < 0 ? null : $this->game->wad->cacheLumpNum($n);
    }
}
