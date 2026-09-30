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

final class WbStart
{
    public function __construct(
        public int $epsd,
        public int $last,
        public int $next,
        public int $maxkills,
        public int $maxitems,
        public int $maxsecret,
        public int $partime,
        public int $skills,
        public int $sitems,
        public int $ssecret,
        public int $stime,
        public bool $didsecret,
        public bool $commercial,
    ) {}
}

final class WiAnim
{
    /** @param list<?string> $patches */
    public function __construct(
        public int $type,
        public int $period,
        public int $frames,
        public int $x,
        public int $y,
        public int $data,
        public array $patches,
        public int $current = -1,
        public int $nextTic = 0,
    ) {}
}

final class Intermission
{
    public const NO_STATE = -1;
    public const STAT_COUNT = 0;
    public const SHOW_NEXT = 1;
    private const ALWAYS = 0;
    private const LEVEL = 2;
    private const PARS = [
        [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        [0, 30, 75, 120, 90, 165, 180, 180, 30, 165],
        [0, 90, 90, 90, 120, 90, 360, 240, 30, 170],
        [0, 90, 45, 90, 150, 90, 90, 165, 30, 135],
    ];
    private const CPARS = [
        30, 90, 120, 120, 90, 150, 120, 120, 270, 90, 210, 150, 150, 150, 210, 150,
        420, 150, 210, 150, 240, 150, 180, 150, 150, 300, 330, 420, 300, 180, 120, 30,
    ];
    private const NODES = [
        [[185, 164], [148, 143], [69, 122], [209, 102], [116, 89], [166, 55], [71, 56], [135, 29], [71, 24]],
        [[254, 25], [97, 50], [188, 64], [128, 78], [214, 92], [133, 130], [208, 136], [148, 140], [235, 158]],
        [[156, 168], [48, 154], [174, 95], [265, 75], [130, 48], [279, 23], [198, 48], [140, 25], [281, 136]],
    ];
    private const ANIMS = [
        [
            [0, 11, 3, 224, 104, 0], [0, 11, 3, 184, 160, 0], [0, 11, 3, 112, 136, 0], [0, 11, 3, 72, 112, 0],
            [0, 11, 3, 88, 96, 0], [0, 11, 3, 64, 48, 0], [0, 11, 3, 192, 40, 0], [0, 11, 3, 136, 16, 0],
            [0, 11, 3, 80, 16, 0], [0, 11, 3, 64, 24, 0],
        ],
        [
            [2, 11, 1, 128, 136, 1], [2, 11, 1, 128, 136, 2], [2, 11, 1, 128, 136, 3], [2, 11, 1, 128, 136, 4],
            [2, 11, 1, 128, 136, 5], [2, 11, 1, 128, 136, 6], [2, 11, 1, 128, 136, 7], [0, 11, 3, 192, 144, 8],
            [2, 11, 1, 128, 136, 8],
        ],
        [
            [0, 11, 3, 104, 168, 0], [0, 11, 3, 40, 136, 0], [0, 11, 3, 160, 96, 0], [0, 11, 3, 104, 80, 0],
            [0, 11, 3, 120, 32, 0], [0, 8, 3, 40, 0, 0],
        ],
    ];

    private Game $game;
    private WbStart $wbs;
    private int $state = self::STAT_COUNT;
    private int $accelerate = 0;
    private int $spState = 1;
    private int $kills = -1;
    private int $items = -1;
    private int $secret = -1;
    private int $time = -1;
    private int $par = -1;
    private int $pause = Defs::TICRATE;
    private int $count = 0;
    private int $backgroundCount = 0;
    private bool $pointer = false;
    public bool $done = false;
    /** @var array<string,?string> */
    private array $patches = [];
    /** @var list<?string> */
    private array $numbers = [];
    /** @var list<?string> */
    private array $levelNames = [];
    /** @var list<WiAnim> */
    private array $animations = [];
    private ?string $background;

    public function __construct(Game $game, WbStart $wbs)
    {
        $this->game = $game;
        $this->wbs = $wbs;
        foreach ([
            'finished' => 'WIF',
            'entering' => 'WIENTER',
            'kills' => 'WIOSTK',
            'items' => 'WIOSTI',
            'secret' => 'WISCRT2',
            'percent' => 'WIPCNT',
            'colon' => 'WICOLON',
            'time' => 'WITIME',
            'par' => 'WIPAR',
            'sucks' => 'WISUCKS',
            'minus' => 'WIMINUS',
            'splat' => 'WISPLAT',
            'yah0' => 'WIURH0',
            'yah1' => 'WIURH1',
        ] as $key => $name) {
            $this->patches[$key] = $this->lump($name);
        }
        for ($i = 0; $i < 10; ++$i) {
            $this->numbers[] = $this->lump("WINUM{$i}");
        }
        $this->background = $this->lump($wbs->commercial || $wbs->epsd === 3 ? 'INTERPIC' : "WIMAP{$wbs->epsd}")
            ?? $this->lump('INTERPIC');
        $maps = $wbs->commercial ? 32 : 9;
        for ($i = 0; $i < $maps; ++$i) {
            $this->levelNames[] = $this->lump($wbs->commercial ? sprintf('CWILV%02d', $i) : "WILV{$wbs->epsd}{$i}");
        }
        if (!$wbs->commercial && $wbs->epsd < 3) {
            foreach (self::ANIMS[$wbs->epsd] as $j => [$type, $period, $frames, $x, $y, $data]) {
                $images = [];
                for ($i = 0; $i < $frames; ++$i) {
                    $images[] = $wbs->epsd === 1 && $j === 8
                        ? ($this->animations[4]->patches[$i] ?? null)
                        : $this->lump(sprintf('WIA%d%02d%02d', $wbs->epsd, $j, $i));
                }
                $this->animations[] = new WiAnim($type, $period, $frames, $x, $y, $data, $images);
            }
        }
        $this->initAnimated();
    }

    public static function parTime(int $episode, int $map, bool $commercial): int
    {
        if ($commercial) {
            return Defs::TICRATE * self::CPARS[max(0, min(count(self::CPARS) - 1, $map - 1))];
        }
        if ($episode >= 1 && $episode <= 3 && $map >= 1 && $map <= 9) {
            return Defs::TICRATE * self::PARS[$episode][$map];
        }
        return Defs::TICRATE * 30;
    }

    public function ticker(): void
    {
        ++$this->backgroundCount;
        if ($this->backgroundCount === 1) {
            $this->game->sound->changeMusic($this->wbs->commercial ? 'dm2int' : 'inter', true);
        }
        $this->checkAccelerate();
        if ($this->state === self::STAT_COUNT) {
            $this->updateStats();
        } elseif ($this->state === self::SHOW_NEXT) {
            $this->updateShowNext();
        } else {
            $this->updateNoState();
        }
    }

    private function checkAccelerate(): void
    {
        if ($this->game->menu?->active) {
            return;
        }
        $attack = isset($this->game->keys[Keys::LCTRL]) || isset($this->game->keys[Keys::RCTRL]);
        $use = isset($this->game->keys[Keys::SPACE]) || isset($this->game->keys[ord('e')])
            || isset($this->game->keys[Keys::RETURN]) || isset($this->game->keys[Keys::KP_ENTER]);
        $player = $this->game->player;
        if ($player === null) {
            if ($attack || $use) {
                $this->accelerate = 1;
            }
            return;
        }
        if ($attack && !$player->attackdown) {
            $this->accelerate = 1;
        }
        if ($use && !$player->usedown) {
            $this->accelerate = 1;
        }
        $player->attackdown = $attack;
        $player->usedown = $use;
    }

    private function updateStats(): void
    {
        $this->updateAnimated();
        $w = $this->wbs;
        if ($this->accelerate && $this->spState !== 10) {
            $this->accelerate = 0;
            $this->kills = self::pct($w->skills, $w->maxkills);
            $this->items = self::pct($w->sitems, $w->maxitems);
            $this->secret = self::pct($w->ssecret, $w->maxsecret);
            $this->time = intdiv($w->stime, Defs::TICRATE);
            $this->par = intdiv($w->partime, Defs::TICRATE);
            $this->game->startSound('barexp');
            $this->spState = 10;
            return;
        }
        if (($this->spState & 1) !== 0) {
            if (--$this->pause === 0) {
                ++$this->spState;
                $this->pause = Defs::TICRATE;
                match ($this->spState) {
                    2 => $this->kills = 0,
                    4 => $this->items = 0,
                    6 => $this->secret = 0,
                    8 => $this->time = $this->par = 0,
                    default => 0,
                };
            }
            return;
        }
        if ($this->spState === 10) {
            if ($this->accelerate) {
                $this->game->startSound('wpnup');
                if ($w->commercial) {
                    $this->initNoState();
                } else {
                    $this->initShowNext();
                }
            }
            return;
        }
        $field = match ($this->spState) {
            2 => 'kills',
            4 => 'items',
            6 => 'secret',
            default => 'time',
        };
        if (($this->backgroundCount & 3) === 0) {
            $this->game->startSound('pistol');
        }
        if ($field === 'time') {
            $this->time += 3;
            $this->par += 3;
            $this->time = min($this->time, intdiv($w->stime, 35));
            $this->par = min($this->par, intdiv($w->partime, 35));
            if ($this->time >= intdiv($w->stime, 35) && $this->par >= intdiv($w->partime, 35)) {
                $this->finishCount();
            }
        } else {
            $target = self::pct($w->{$field === 'secret' ? 'ssecret' : ($field === 'items' ? 'sitems' : 'skills')}, $w->{'max' . $field});
            $this->{$field} += 2;
            if ($this->{$field} >= $target) {
                $this->{$field} = $target;
                $this->finishCount();
            }
        }
    }

    private function finishCount(): void
    {
        $this->game->startSound('barexp');
        ++$this->spState;
    }

    private static function pct(int $value, int $max): int
    {
        return intdiv($value * 100, max(1, $max));
    }

    private function initShowNext(): void
    {
        $this->state = self::SHOW_NEXT;
        $this->accelerate = 0;
        $this->count = 4 * 35;
        $this->initAnimated();
    }

    private function initNoState(): void
    {
        $this->state = self::NO_STATE;
        $this->accelerate = 0;
        $this->count = 10;
    }

    private function updateShowNext(): void
    {
        $this->updateAnimated();
        if (--$this->count === 0 || $this->accelerate) {
            $this->initNoState();
        } else {
            $this->pointer = ($this->count & 31) < 20;
        }
    }

    private function updateNoState(): void
    {
        $this->updateAnimated();
        if (--$this->count === 0) {
            $this->done = true;
        }
    }

    private function initAnimated(): void
    {
        foreach ($this->animations as $animation) {
            $animation->current = -1;
            $animation->nextTic = $this->backgroundCount + 1 + ($animation->type === self::ALWAYS ? random_int(0, max(0, $animation->period - 1)) : 0);
        }
    }

    private function updateAnimated(): void
    {
        foreach ($this->animations as $i => $a) {
            if ($this->backgroundCount === $a->nextTic) {
                if ($a->type === self::ALWAYS) {
                    $a->current = ($a->current + 1) % $a->frames;
                    $a->nextTic = $this->backgroundCount + $a->period;
                } elseif (!($this->state === self::STAT_COUNT && $i === 7) && $this->wbs->next === $a->data) {
                    $a->current = min($a->frames - 1, $a->current + 1);
                    $a->nextTic = $this->backgroundCount + $a->period;
                }
            }
        }
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb): void
    {
        $this->drawBackground($fb);
        if ($this->state === self::STAT_COUNT) {
            $this->drawStats($fb);
        } else {
            $this->drawNext($fb);
        }
    }

    /** @param array<int,int> $fb */
    private function drawBackground(array &$fb): void
    {
        if ($this->background !== null) {
            VVideo::drawPatch($fb, 0, 0, $this->background);
        }
        foreach ($this->animations as $a) {
            if ($a->current >= 0 && ($a->patches[$a->current] ?? null) !== null) {
                VVideo::drawPatch($fb, $a->x, $a->y, $a->patches[$a->current]);
            }
        }
    }

    /** @param array<int,int> $fb */
    private function drawStats(array &$fb): void
    {
        $this->drawFinished($fb);
        $line = 24;
        foreach ([['kills', $this->kills, 50], ['items', $this->items, 50 + $line], ['secret', $this->secret, 50 + 2 * $line]] as [$name, $value, $y]) {
            if ($this->patches[$name] !== null) {
                VVideo::drawPatch($fb, 50, $y, $this->patches[$name]);
            }
            $this->drawPercent($fb, 270, $y, $value);
        }
        if ($this->patches['time'] !== null) {
            VVideo::drawPatch($fb, 16, 168, $this->patches['time']);
        }
        $this->drawTime($fb, 144, 168, $this->time);
        if ($this->wbs->epsd < 3) {
            if ($this->patches['par'] !== null) {
                VVideo::drawPatch($fb, 176, 168, $this->patches['par']);
            }
            $this->drawTime($fb, 304, 168, $this->par);
        }
    }

    /** @param array<int,int> $fb */
    private function drawFinished(array &$fb): void
    {
        $y = 2;
        $name = $this->levelNames[$this->wbs->last] ?? null;
        if ($name !== null) {
            [$w, $h] = VVideo::patchSize($name);
            VVideo::drawPatch($fb, intdiv(320 - $w, 2), $y, $name);
            $y += intdiv(5 * $h, 4);
        }
        if ($this->patches['finished'] !== null) {
            [$w] = VVideo::patchSize($this->patches['finished']);
            VVideo::drawPatch($fb, intdiv(320 - $w, 2), $y, $this->patches['finished']);
        }
    }

    /** @param array<int,int> $fb */
    private function drawNext(array &$fb): void
    {
        if ($this->state === self::NO_STATE) {
            $this->pointer = true;
        }
        if (!$this->wbs->commercial && $this->wbs->epsd < 3) {
            $last = $this->wbs->last === 8 ? $this->wbs->next - 1 : $this->wbs->last;
            for ($i = 0; $i <= $last; ++$i) {
                $this->drawNode($fb, $i, [$this->patches['splat']]);
            }
            if ($this->wbs->didsecret) {
                $this->drawNode($fb, 8, [$this->patches['splat']]);
            }
            if ($this->pointer) {
                $this->drawNode($fb, $this->wbs->next, [$this->patches['yah0'], $this->patches['yah1']]);
            }
        }
        if (!$this->wbs->commercial || $this->wbs->next !== 30) {
            $this->drawEntering($fb);
        }
    }

    /** @param array<int,int> $fb */
    private function drawEntering(array &$fb): void
    {
        $y = 2;
        $p = $this->patches['entering'];
        if ($p !== null) {
            [$w, $h] = VVideo::patchSize($p);
            VVideo::drawPatch($fb, intdiv(320 - $w, 2), $y, $p);
            $y += intdiv(5 * $h, 4);
        }
        $p = $this->levelNames[$this->wbs->next] ?? null;
        if ($p !== null) {
            [$w] = VVideo::patchSize($p);
            VVideo::drawPatch($fb, intdiv(320 - $w, 2), $y, $p);
        }
    }

    /** @param array<int,int> $fb @param list<?string> $patches */
    private function drawNode(array &$fb, int $number, array $patches): void
    {
        $node = self::NODES[$this->wbs->epsd][$number] ?? null;
        if ($node === null) {
            return;
        }
        foreach ($patches as $p) {
            if ($p !== null) {
                [$w, $h, $left, $top] = VVideo::patchSize($p);
                if ($node[0] - $left >= 0 && $node[0] - $left + $w < 320 && $node[1] - $top >= 0 && $node[1] - $top + $h < 200) {
                    VVideo::drawPatch($fb, $node[0], $node[1], $p);
                    return;
                }
            }
        }
    }

    /** @param array<int,int> $fb */
    private function drawPercent(array &$fb, int $x, int $y, int $value): void
    {
        if ($value < 0) {
            return;
        }
        if ($this->patches['percent'] !== null) {
            VVideo::drawPatch($fb, $x, $y, $this->patches['percent']);
        }
        $this->drawNumber($fb, $x, $y, $value, -1);
    }

    /** @param array<int,int> $fb */
    private function drawNumber(array &$fb, int $x, int $y, int $n, int $digits): int
    {
        $width = $this->numbers[0] !== null ? VVideo::patchSize($this->numbers[0])[0] : 8;
        if ($digits < 0) {
            $digits = $n === 0 ? 1 : strlen((string) abs($n));
        }
        $negative = $n < 0;
        $n = abs($n);
        while ($digits-- > 0) {
            $x -= $width;
            $p = $this->numbers[$n % 10];
            if ($p !== null) {
                VVideo::drawPatch($fb, $x, $y, $p);
            }
            $n = intdiv($n, 10);
        }
        if ($negative && $this->patches['minus'] !== null) {
            $x -= 8;
            VVideo::drawPatch($fb, $x, $y, $this->patches['minus']);
        }
        return $x;
    }

    /** @param array<int,int> $fb */
    private function drawTime(array &$fb, int $x, int $y, int $t): void
    {
        if ($t < 0) {
            return;
        }
        if ($t > 61 * 59) {
            $p = $this->patches['sucks'];
            if ($p !== null) {
                [$w] = VVideo::patchSize($p);
                VVideo::drawPatch($fb, $x - $w, $y, $p);
            }
            return;
        }
        $div = 1;
        do {
            $n = intdiv($t, $div) % 60;
            $x = $this->drawNumber($fb, $x, $y, $n, 2) - 8;
            $div *= 60;
            if (($div === 60 || intdiv($t, $div) > 0) && $this->patches['colon'] !== null) {
                VVideo::drawPatch($fb, $x, $y, $this->patches['colon']);
            }
        } while (intdiv($t, $div) > 0);
    }

    private function lump(string $name): ?string
    {
        $n = $this->game->wad->checkNumForName($name);
        return $n < 0 ? null : $this->game->wad->cacheLumpNum($n);
    }
}

class_alias(Intermission::class, __NAMESPACE__ . '\\WiStuff');
