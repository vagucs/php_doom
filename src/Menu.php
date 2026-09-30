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

final class MenuItem
{
    public function __construct(
        public int $status,
        public string $name,
        public string $action,
        public int $alpha = 0,
    ) {}
}

final class MenuDef
{
    /** @param list<MenuItem> $items */
    public function __construct(
        public array $items,
        public string $routine,
        public int $x,
        public int $y,
        public int $lastOn = 0,
        public ?string $previous = null,
    ) {}
}

final class Menu
{
    private const LINE_HEIGHT = 16;
    private object $wad;
    private Sound $sound;
    private Game $game;
    public bool $active = false;
    private string $screen = 'main';
    private int $itemOn = 0;
    private int $skull = 0;
    private int $skullTics = 8;
    private int $episode = 0;
    private ?string $message = null;
    private bool $confirm = false;
    private ?string $messageAction = null;
    /** @var list<string> */
    private array $saveStrings;
    /** @var list<bool> */
    private array $saveOk;
    private bool $enteringSave = false;
    private int $saveSlot = 0;
    private string $oldSave = '';
    private int $saveIndex = 0;
    /** @var array<string,MenuDef> */
    private array $menus;

    public function __construct(object $wad, Sound $sound, Game $game)
    {
        $this->wad = $wad;
        $this->sound = $sound;
        $this->game = $game;
        $this->saveStrings = array_fill(0, 6, Defs::LOADSAVEEMPTY);
        $this->saveOk = array_fill(0, 6, false);
        $items = static fn (array $rows): array => array_map(
            static fn ($r) => new MenuItem($r[0], $r[1], $r[2], $r[3] ?? 0),
            $rows
        );
        $this->menus = [
            'main' => new MenuDef($items([
                [1, 'M_NGAME', 'newgame'],
                [1, 'M_OPTION', 'options'],
                [1, 'M_LOADG', 'loadgame'],
                [1, 'M_SAVEG', 'savegame'],
                [1, 'M_RDTHIS', 'readthis'],
                [1, 'M_QUITG', 'quit'],
            ]), 'main', 97, 64),
            'episode' => new MenuDef($items([
                [1, 'M_EPI1', 'episode'],
                [1, 'M_EPI2', 'episode'],
                [1, 'M_EPI3', 'episode'],
                [1, 'M_EPI4', 'episode'],
            ]), 'episode', 48, 63, 0, 'main'),
            'skill' => new MenuDef($items([
                [1, 'M_JKILL', 'skill'],
                [1, 'M_ROUGH', 'skill'],
                [1, 'M_HURT', 'skill'],
                [1, 'M_ULTRA', 'skill'],
                [1, 'M_NMARE', 'skill'],
            ]), 'skill', 48, 63, 2, 'episode'),
            'options' => new MenuDef($items([
                [1, 'M_ENDGAM', 'endgame'],
                [1, 'M_MESSG', 'messages'],
                [1, 'M_DETAIL', 'detail'],
                [2, 'M_SCRNSZ', 'scrnsize'],
                [-1, '', ''],
                [2, 'M_MSENS', 'mousesens'],
                [-1, '', ''],
                [1, 'M_SVOL', 'sound'],
            ]), 'options', 60, 37, 0, 'main'),
            'sound' => new MenuDef($items([
                [2, 'M_SFXVOL', 'sfxvol'],
                [-1, '', ''],
                [2, 'M_MUSVOL', 'musvol'],
                [-1, '', ''],
            ]), 'sound', 80, 64, 0, 'options'),
            'load' => new MenuDef(array_fill(0, 6, null), 'load', 80, 54, 0, 'main'),
            'save' => new MenuDef(array_fill(0, 6, null), 'save', 80, 54, 0, 'main'),
            'read1' => new MenuDef([new MenuItem(1, '', 'read2')], 'read1', 280, 185, 0, 'main'),
            'read2' => new MenuDef([new MenuItem(1, '', 'finishread')], 'read2', 330, 175, 0, 'read1'),
        ];
        for ($i = 0; $i < 6; ++$i) {
            $this->menus['load']->items[$i] = new MenuItem(1, '', 'loadslot');
            $this->menus['save']->items[$i] = new MenuItem(1, '', 'saveslot');
        }
        if (!$this->hasEpisodes()) {
            $this->menus['skill']->previous = 'main';
        }
    }

    public function ticker(): void
    {
        if ($this->active && --$this->skullTics <= 0) {
            $this->skull ^= 1;
            $this->skullTics = 8;
        }
    }

    public function start(): void
    {
        if ($this->active) {
            return;
        }
        $this->active = true;
        $this->screen = 'main';
        $this->itemOn = $this->menus['main']->lastOn;
        $this->message = null;
        $this->enteringSave = false;
        $this->sound->play('swtchn');
    }

    public function clear(): void
    {
        $this->active = false;
        $this->message = null;
        $this->enteringSave = false;
    }

    public function responder(int $key, string $char = ''): bool
    {
        if ($this->enteringSave) {
            return $this->saveStringKey($key, $char);
        }
        if ($this->message !== null) {
            if ($this->confirm) {
                if (in_array($key, [ord('y'), Keys::RETURN, Keys::KP_ENTER], true)) {
                    $action = $this->messageAction;
                    $this->message = null;
                    if ($action === 'quit') {
                        $this->game->running = false;
                    } elseif ($action === 'endgame') {
                        $this->game->returnToTitle();
                    }
                } elseif (in_array($key, [ord('n'), Keys::ESC], true)) {
                    $this->message = null;
                }
                return true;
            }
            if ($key !== 0) {
                $this->message = null;
            }
            return true;
        }
        if ($key === Keys::F2) {
            $this->action('savegame', 0);
            return true;
        }
        if ($key === Keys::F3) {
            $this->action('loadgame', 0);
            return true;
        }
        if (!$this->active) {
            if ($key === Keys::ESC) {
                $this->start();
                return true;
            }
            if ((Keys::isMinus($key) || Keys::isPlus($key) || in_array($char, ['+', '=', '-', '_'], true))
                && !$this->game->automap->active) {
                $this->game->sizeDisplay(Keys::isPlus($key) || $char === '+' || $char === '=' ? 1 : 0);
                return true;
            }
            return false;
        }
        $menu = $this->menus[$this->screen];
        if ($key === Keys::ESC) {
            $menu->lastOn = $this->itemOn;
            $this->clear();
            $this->sound->play('swtchx');
            return true;
        }
        if ($key === Keys::BACKSPACE) {
            $menu->lastOn = $this->itemOn;
            if ($menu->previous !== null) {
                $this->go($menu->previous);
            } else {
                $this->clear();
            }
            $this->sound->play('swtchx');
            return true;
        }
        if ($key === Keys::UP || $key === Keys::DOWN) {
            $step = $key === Keys::DOWN ? 1 : -1;
            $count = count($menu->items);
            do {
                $this->itemOn = ($this->itemOn + $step + $count) % $count;
            } while ($menu->items[$this->itemOn]->status === -1);
            $this->sound->play('pstop');
            return true;
        }
        if ($key === Keys::LEFT || $key === Keys::RIGHT || Keys::isMinus($key) || Keys::isPlus($key)) {
            $item = $menu->items[$this->itemOn];
            if ($item->status === 2) {
                $this->sound->play('stnmov');
                $this->action($item->action, ($key === Keys::RIGHT || Keys::isPlus($key)) ? 1 : 0);
                return true;
            }
            if (Keys::isMinus($key) || Keys::isPlus($key)) {
                return false;
            }
            return true;
        }
        if ($key === Keys::RETURN || $key === Keys::KP_ENTER) {
            $item = $menu->items[$this->itemOn];
            if ($item->status !== 0) {
                $menu->lastOn = $this->itemOn;
                $this->sound->play('pistol');
                $this->action($item->action, $item->status === 2 ? 1 : $this->itemOn);
            }
            return true;
        }
        return true;
    }

    private function action(string $action, int $choice): void
    {
        switch ($action) {
            case 'newgame':
                $this->episode = 0;
                $this->go($this->hasEpisodes() ? 'episode' : 'skill');
                break;
            case 'options':
            case 'sound':
                $this->go($action);
                break;
            case 'loadgame':
                $this->openSlots('load');
                break;
            case 'savegame':
                if ($this->game->gamestate === Defs::GS_LEVEL && $this->game->player !== null) {
                    $this->openSlots('save');
                } else {
                    $this->sound->play('oof');
                }
                break;
            case 'loadslot':
                if ($this->saveOk[$choice] && $this->game->loadGame($choice)) {
                    $this->clear();
                } else {
                    $this->sound->play('oof');
                }
                break;
            case 'saveslot':
                $this->beginSaveName($choice);
                break;
            case 'readthis':
                $this->go('read1');
                break;
            case 'read2':
                $this->go($this->has('HELP1') && $this->screen === 'read1' ? 'read2' : 'main');
                break;
            case 'finishread':
                $this->go('main');
                break;
            case 'quit':
                $this->setConfirm('ARE YOU SURE YOU WANT TO QUIT?', 'quit');
                break;
            case 'endgame':
                if ($this->game->gamestate === Defs::GS_LEVEL) {
                    $this->setConfirm('END GAME?', 'endgame');
                } else {
                    $this->sound->play('oof');
                }
                break;
            case 'messages':
                $this->game->showMessages = !$this->game->showMessages;
                $this->game->player?->setMessage($this->game->showMessages ? 'Messages On' : 'Messages Off');
                break;
            case 'detail':
                $this->game->detailLevel ^= 1;
                $this->game->applyViewSize();
                $this->game->player?->setMessage($this->game->detailLevel === 0 ? 'High detail' : 'Low detail');
                break;
            case 'scrnsize':
                $this->game->sizeDisplay($choice);
                break;
            case 'mousesens':
                $this->game->mouseSensitivity = max(0, min(9, $this->game->mouseSensitivity + ($choice ? 1 : -1)));
                break;
            case 'sfxvol':
                $this->sound->setSfxVolume($this->sound->sfxVolume + ($choice ? 1 : -1));
                break;
            case 'musvol':
                $this->sound->setMusicVolume($this->sound->musicVolume + ($choice ? 1 : -1));
                break;
            case 'episode':
                if (!$this->has('E2M1') && $choice !== 0) {
                    $this->message = 'ONLY AVAILABLE IN THE REGISTERED VERSION.';
                    $this->confirm = false;
                    $this->go('read1');
                } else {
                    $this->episode = $choice;
                    $this->go('skill');
                }
                break;
            case 'skill':
                $this->game->startNewGame($choice, $this->episode + 1, 1);
                $this->clear();
                break;
        }
    }

    private function setConfirm(string $message, string $action): void
    {
        $this->message = $message;
        $this->confirm = true;
        $this->messageAction = $action;
    }

    private function go(string $screen): void
    {
        $this->menus[$this->screen]->lastOn = $this->itemOn;
        $this->screen = $screen;
        $this->itemOn = $this->menus[$screen]->lastOn;
    }

    private function openSlots(string $screen): void
    {
        for ($i = 0; $i < 6; ++$i) {
            [$description, $ok] = Saveg::readSlotDescription($this->game, $i);
            $this->saveStrings[$i] = $description;
            $this->saveOk[$i] = $ok;
            $this->menus['load']->items[$i]->status = $ok ? 1 : 0;
            $this->menus['save']->items[$i]->status = 1;
        }
        $this->message = null;
        $this->enteringSave = false;
        if (!$this->active) {
            $this->active = true;
            $this->screen = $screen;
            $this->itemOn = $this->menus[$screen]->lastOn;
        } else {
            $this->go($screen);
        }
        $this->sound->play('swtchn');
    }

    private function beginSaveName(int $slot): void
    {
        $this->enteringSave = true;
        $this->saveSlot = $slot;
        $this->oldSave = $this->saveStrings[$slot];
        if ($this->saveStrings[$slot] === Defs::LOADSAVEEMPTY) {
            $this->saveStrings[$slot] = '';
        }
        $this->saveIndex = strlen($this->saveStrings[$slot]);
    }

    private function saveStringKey(int $key, string $char): bool
    {
        $slot = $this->saveSlot;
        if ($key === Keys::BACKSPACE) {
            if ($this->saveIndex > 0) {
                $this->saveStrings[$slot] = substr($this->saveStrings[$slot], 0, --$this->saveIndex);
            }
            return true;
        }
        if ($key === Keys::ESC) {
            $this->enteringSave = false;
            $this->saveStrings[$slot] = $this->oldSave;
            return true;
        }
        if ($key === Keys::RETURN || $key === Keys::KP_ENTER) {
            $this->enteringSave = false;
            if ($this->saveStrings[$slot] !== '') {
                if ($this->game->saveGame($slot, $this->saveStrings[$slot])) {
                    $this->clear();
                } else {
                    $this->sound->play('oof');
                }
            } else {
                $this->saveStrings[$slot] = $this->oldSave;
            }
            return true;
        }
        $char = strtoupper($char);
        if (strlen($char) !== 1) {
            return true;
        }
        $code = ord($char);
        if ($char !== ' ' && ($code < Defs::HU_FONTSTART || $code > Defs::HU_FONTEND)) {
            return true;
        }
        if ($code >= 32 && $code <= 127 && $this->saveIndex < Defs::SAVESTRINGSIZE - 1
            && $this->stringWidth($this->saveStrings[$slot]) < (Defs::SAVESTRINGSIZE - 2) * 8) {
            $this->saveStrings[$slot] .= $char;
            ++$this->saveIndex;
        }
        return true;
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb): void
    {
        if (!$this->active) {
            return;
        }
        if ($this->message !== null) {
            $this->writeText($fb, 10, 80, $this->message . ($this->confirm ? '  (Y/N)' : ''));
            return;
        }
        $menu = $this->menus[$this->screen];
        $headers = [
            'main' => ['M_DOOM', 94, 2],
            'skill' => ['M_NEWG', 96, 14],
            'episode' => ['M_EPISOD', 54, 38],
            'options' => ['M_OPTTTL', 108, 15],
            'sound' => ['M_SVOL', 60, 38],
            'load' => ['M_LOADG', 72, 28],
            'save' => ['M_SAVEG', 72, 28],
        ];
        if (isset($headers[$menu->routine])) {
            [$name, $x, $y] = $headers[$menu->routine] + [null, 0, 0];
        }
        if (isset($name) && ($patch = $this->patch($name)) !== null) {
            VVideo::drawPatch($fb, $x, $y, $patch);
        }
        if ($menu->routine === 'read1' || $menu->routine === 'read2') {
            $name = $menu->routine === 'read2' ? 'HELP1' : ($this->has('HELP2') ? 'HELP2' : ($this->has('HELP1') ? 'HELP1' : ($this->has('HELP') ? 'HELP' : 'CREDIT')));
            if (($patch = $this->patch($name)) !== null) {
                VVideo::drawPatch($fb, 0, 0, $patch);
            }
        } elseif ($menu->routine === 'load' || $menu->routine === 'save') {
            $this->drawSlots($fb, $menu);
        } else {
            foreach ($menu->items as $i => $item) {
                if ($item->name !== '' && ($patch = $this->patch($item->name)) !== null) {
                    VVideo::drawPatch($fb, $menu->x, $menu->y + $i * self::LINE_HEIGHT, $patch);
                }
            }
        }
        if ($menu->routine === 'options') {
            $msg = $this->game->showMessages ? 'M_MSGON' : 'M_MSGOFF';
            if (($p = $this->patch($msg)) !== null) {
                VVideo::drawPatch($fb, $menu->x + 120, $menu->y + self::LINE_HEIGHT, $p);
            }
            $det = $this->game->detailLevel === 0 ? 'M_GDHIGH' : 'M_GDLOW';
            if (($p = $this->patch($det)) !== null) {
                VVideo::drawPatch($fb, $menu->x + 175, $menu->y + self::LINE_HEIGHT * 2, $p);
            }
            $this->thermo($fb, $menu->x, $menu->y + 64, 9, $this->game->screenSize);
            $this->thermo($fb, $menu->x, $menu->y + 96, 10, $this->game->mouseSensitivity);
        } elseif ($menu->routine === 'sound') {
            $this->thermo($fb, $menu->x, $menu->y + 16, 16, $this->sound->sfxVolume);
            $this->thermo($fb, $menu->x, $menu->y + 48, 16, $this->sound->musicVolume);
        }
        if (!in_array($menu->routine, ['read1', 'read2'], true) && ($patch = $this->patch($this->skull ? 'M_SKULL2' : 'M_SKULL1')) !== null) {
            VVideo::drawPatch($fb, $menu->x - 32, $menu->y - 5 + $this->itemOn * 16, $patch);
        }
    }

    private function hasEpisodes(): bool
    {
        return !$this->has('MAP01') && $this->has('E2M1');
    }

    private function has(string $name): bool
    {
        return $this->wad->checkNumForName($name) >= 0;
    }

    private function patch(string $name): ?string
    {
        $n = $this->wad->checkNumForName($name);
        return $n < 0 ? null : $this->wad->cacheLumpNum($n);
    }

    /** @param array<int,int> $fb */
    private function drawSlots(array &$fb, MenuDef $menu): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $y = $menu->y + 16 * $i;
            $x = $menu->x;
            foreach (['M_LSLEFT', ...array_fill(0, Defs::SAVESTRINGSIZE, 'M_LSCNTR'), 'M_LSRGHT'] as $j => $name) {
                if (($p = $this->patch($name)) !== null) {
                    VVideo::drawPatch($fb, $x - ($j === 0 ? 8 : 0), $y + 7, $p);
                }
                if ($j > 0) {
                    $x += 8;
                }
            }
            $end = $this->writeText($fb, $menu->x, $y, $this->saveStrings[$i]);
            if ($this->enteringSave && $i === $this->saveSlot) {
                $this->writeText($fb, $end, $y, '_');
            }
        }
    }

    /** @param array<int,int> $fb */
    private function thermo(array &$fb, int $x, int $y, int $width, int $dot): void
    {
        foreach (['M_THERML', ...array_fill(0, $width, 'M_THERMM'), 'M_THERMR'] as $name) {
            if (($p = $this->patch($name)) !== null) {
                VVideo::drawPatch($fb, $x, $y, $p);
            }
            $x += 8;
        }
        if (($p = $this->patch('M_THERMO')) !== null) {
            VVideo::drawPatch($fb, $x - ($width + 2) * 8 + 8 + max(0, min($width - 1, $dot)) * 8, $y, $p);
        }
    }

    private function stringWidth(string $text): int
    {
        $buffer = null;
        return $this->writeText($buffer, 0, 0, $text);
    }

    /** @param ?array<int,int> $fb */
    private function writeText(?array &$fb, int $x, int $y, string $text): int
    {
        foreach (str_split(strtoupper($text)) as $char) {
            $patch = $char === ' ' ? null : $this->patch(sprintf('STCFN%03d', ord($char)));
            if ($patch !== null) {
                if ($fb !== null) {
                    VVideo::drawPatch($fb, $x, $y, $patch);
                }
                [$width] = VVideo::patchSize($patch);
                $x += max(4, $width);
            } else {
                $x += $char === ' ' ? 4 : 8;
            }
            if ($fb !== null && $x > 300) {
                $x = 10;
                $y += 10;
            }
        }
        return $x;
    }
}
