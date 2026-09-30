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

use Throwable;

final class Game
{
    private const IWADS = ['DOOM1.WAD', 'doom1.wad', 'DOOM.WAD', 'doom.wad', 'DOOM2.WAD', 'doom2.wad', 'PLUTONIA.WAD', 'TNT.WAD', 'freedoom1.wad', 'freedoom2.wad'];
    private const WEAPON_PATCH = [0 => 'PUNGA0', 1 => 'PISGA0', 2 => 'SHTGA0', 3 => 'CHGGA0', 4 => 'MISGA0', 5 => 'PLSGA0', 6 => 'BFGGA0', 7 => 'SAWGA0', 8 => 'SHT2A0'];
    private const WEAPON_FIRE_BODY = [0 => 'PUNGB0', 1 => 'PISGB0', 2 => 'SHTGB0', 3 => 'CHGGB0', 4 => 'MISGB0', 5 => 'PLSGB0', 6 => 'BFGGB0', 7 => 'SAWGB0', 8 => 'SHT2B0'];
    private const WEAPON_FIRE_PATCH = [1 => 'PISFA0', 2 => 'SHTFA0', 3 => 'CHGFA0', 4 => 'MISFA0', 5 => 'PLSFA0', 6 => 'BFGFA0', 8 => 'SHT2F0'];

    public Wad $wad;
    public Video $video;
    public Sound $sound;
    public ?Resources $res = null;
    public ?Renderer $renderer = null;
    public ?World $world = null;
    public ?Specials $specials = null;
    public ?Status $status = null;
    public ?object $player = null;
    public int $gamestate = Defs::GS_TITLE;
    public int $episode = 1;
    public int $mapn = 1;
    public int $skill = Defs::SK_MEDIUM;
    public int $leveltime = 0;
    /** @var array<int,true> SDL keycodes currently held */
    public array $keys = [];
    public bool $running = true;
    public bool $showFps = false;
    public bool $nomonsters = false;
    public bool $fullscreen = false;
    public bool $crt = false;
    public string $iwadPath = '';
    public ?Menu $menu = null;
    public bool $showMessages = true;
    public int $detailLevel = 0;
    public int $screenSize = 7;
    public int $mouseSensitivity = 5;
    public int $totalkills = 0;
    public int $totalitems = 0;
    public int $totalsecret = 0;
    public ?Intermission $wi = null;
    public ?Finale $finale = null;
    public Wipe $wipe;
    public bool $wiping = false;
    public bool $forceWipe = false;
    public AmMap $automap;
    private int $turnheld = 0;
    private ?string $titlePatch = null;
    private int $palette = -1;
    private ?string $playpal = null;
    private ?int $wipeState = Defs::GS_TITLE;
    private int $nextMap = 1;
    /** @var array<string,int> */
    private array $cheats = ['iddqd' => 0, 'idkfa' => 0, 'idfa' => 0, 'iddt' => 0, 'idclip' => 0, 'idspispopd' => 0];

    public function __construct()
    {
        $this->wad = new Wad();
        $this->video = new Video();
        $this->sound = new Sound();
        $this->wipe = new Wipe();
        $this->automap = new AmMap();
    }

    public function startSound(string $name): void
    {
        $this->sound->play($name);
    }

    public function touchSpecial(object $special, object $toucher): void
    {
        Mobj::touchSpecial($this, $special, $toucher);
    }

    public function useSpecial(object $line, object $thing, int $side): void
    {
        $this->specials?->useSpecial($line, $thing, $side);
    }

    public function crossSpecial(object $line, int $side, object $thing): void
    {
        $this->specials?->crossSpecial($line, $side, $thing);
    }

    public function damageMobj(object $target, ?object $source, int $damage, ?object $inflictor = null): void
    {
        if (!$target->alive || ($target->flags & Defs::MF_SHOOTABLE) === 0) {
            return;
        }
        if ($target->player !== null && $this->skill === Defs::SK_BABY) {
            $damage >>= 1;
        }
        $origin = $inflictor ?? $source;
        $skipSaw = $source?->player !== null && $source->player->readyweapon === Defs::WP_CHAINSAW;
        if ($origin !== null && ($target->flags & Defs::MF_NOCLIP) === 0 && !$skipSaw) {
            $angle = Collision::angleTo($origin->x, $origin->y, $target->x, $target->y);
            $thrust = $damage * intdiv(Defs::FRACUNIT, 8);
            $target->momx += Compat::fixedMul($thrust, Tables::fineCos($angle));
            $target->momy += Compat::fixedMul($thrust, Tables::fineSin($angle));
        }
        if ($target->player !== null) {
            $p = $target->player;
            if (($p->cheats & Defs::CF_GODMODE) !== 0 && $damage < 1000) {
                return;
            }
            if ($p->armortype) {
                $saved = intdiv($damage, $p->armortype === 1 ? 3 : 2);
                if ($p->armorpoints <= $saved) {
                    $saved = $p->armorpoints;
                    $p->armortype = 0;
                }
                $p->armorpoints -= $saved;
                $damage -= $saved;
            }
            $p->health -= $damage;
            $target->health = $p->health;
            $p->damagecount = min(100, $p->damagecount + $damage);
            if ($p->health <= 0) {
                $p->health = 0;
                $p->playerstate = Defs::PST_DEAD;
                $target->alive = false;
                $target->flags &= ~(Defs::MF_SOLID | Defs::MF_SHOOTABLE);
                $this->startSound('pldeth');
            } else {
                $this->startSound('plpain');
            }
            return;
        }
        $target->health -= $damage;
        if ($target->health <= 0) {
            Enemy::killMonster($target, $this, $source);
        } else {
            $this->startSound('popain');
            if ($source !== null) {
                $target->target = $source;
                if (in_array($target->aiState, ['', 'look'], true)) {
                    $target->aiState = 'chase';
                    $target->reactiontime = 0;
                }
            }
        }
    }

    public function loadLevel(bool $carry = false): void
    {
        if ($this->res === null) {
            throw new \LogicException('resources not initialized');
        }
        $previous = $carry ? $this->player : null;
        $this->world = new World();
        $this->world->setupLevel($this->wad, $this->res, $this->episode, $this->mapn);
        $this->specials = new Specials($this->world, $this->res, $this->sound);
        $start = $this->world->playerStart();
        if ($start === null) {
            throw new \RuntimeException('no player 1 start');
        }
        $this->player = Player::spawnPlayer($this->world, $start);
        if ($previous !== null) {
            $this->carryPlayer($previous);
        }
        $this->player->killcount = $this->player->itemcount = $this->player->secretcount = 0;
        $this->totalkills = $this->totalitems = 0;
        $this->totalsecret = count(array_filter($this->world->sectors, static fn($s) => $s->special === 9));
        if (!$this->nomonsters) {
            [$this->totalkills, $this->totalitems] = Mobj::spawnMapThings($this->world, $this->skill);
        }
        $this->leveltime = 0;
        $this->gamestate = Defs::GS_LEVEL;
        $this->specials->exitRequested = false;
        $this->specials->secretExit = false;
        $this->wi = null;
        $this->finale = null;
        $this->sound->playLevelMusic($this->episode, $this->mapn);
        $this->automap->resetLevel();
        fwrite(STDOUT, "Entering E{$this->episode}M{$this->mapn}\n");
    }

    private function carryPlayer(object $previous): void
    {
        $p = $this->player;
        foreach (['health', 'armorpoints', 'armortype', 'ammo', 'maxammo', 'weaponowned', 'readyweapon', 'cheats', 'didsecret'] as $field) {
            $p->{$field} = is_array($previous->{$field}) ? array_values($previous->{$field}) : $previous->{$field};
        }
        $p->mo->health = $p->health;
        $p->pendingweapon = Defs::WP_NOCHANGE;
        $p->pspriteState = 'up';
        $p->pspriteSy = 128 * Defs::FRACUNIT;
        $p->pspriteBody = '';
        $p->cards = array_fill(0, 6, false);
        $p->damagecount = $p->bonuscount = $p->extralight = 0;
        $p->playerstate = Defs::PST_LIVE;
    }

    private function commercial(): bool
    {
        return $this->wad->checkNumForName('MAP01') >= 0;
    }

    public function completeLevel(): void
    {
        $this->automap->stop();
        $p = $this->player;
        if ($p !== null) {
            $p->cards = array_fill(0, 6, false);
            $p->damagecount = $p->bonuscount = $p->extralight = 0;
        }
        $secret = $this->specials?->secretExit ?? false;
        $commercial = $this->commercial();
        if (!$commercial && $this->mapn === 8) {
            $this->finale = new Finale($this);
            $this->gamestate = Defs::GS_FINALE;
            return;
        }
        if (!$commercial && $this->mapn === 9 && $p !== null) {
            $p->didsecret = true;
        }
        if ($commercial) {
            $next = $secret && $this->mapn === 15 ? 30 : ($secret && $this->mapn === 31 ? 31 : (in_array($this->mapn, [31, 32], true) ? 15 : $this->mapn));
        } else {
            $next = $secret ? 8 : ($this->mapn === 9 ? ([1 => 3, 2 => 5, 3 => 6, 4 => 2][$this->episode] ?? 0) : $this->mapn);
        }
        $this->nextMap = $next + 1;
        $wbs = new WbStart($this->episode - 1, $this->mapn - 1, $next, max(1, $this->totalkills), max(1, $this->totalitems), max(1, $this->totalsecret),
            Intermission::parTime($this->episode, $this->mapn, $commercial), $p?->killcount ?? 0, $p?->itemcount ?? 0, $p?->secretcount ?? 0, $this->leveltime, (bool) ($p?->didsecret ?? false), $commercial);
        $this->wi = new Intermission($this, $wbs);
        $this->gamestate = Defs::GS_INTERMISSION;
    }

    public function worldDone(): void
    {
        if (($this->specials?->secretExit ?? false) && $this->player !== null) {
            $this->player->didsecret = true;
        }
        $this->mapn = $this->nextMap;
        $lump = $this->commercial() ? sprintf('MAP%02d', $this->mapn) : "E{$this->episode}M{$this->mapn}";
        if ($this->wad->checkNumForName($lump) < 0) {
            $this->returnToTitle();
            return;
        }
        $this->loadLevel(true);
    }

    public function nextMap(): void
    {
        $this->completeLevel();
    }

    public function startNewGame(int $skill, int $episode, int $map): void
    {
        $this->skill = $skill;
        $this->episode = $episode;
        $this->mapn = $map;
        $this->loadLevel();
    }

    public function saveGame(int $slot, string $description): bool
    {
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null || $this->world === null) {
            return false;
        }
        $ok = Saveg::writeSave($this, $slot, $description);
        if ($ok) {
            $this->player->setMessage('game saved.');
        }
        return $ok;
    }

    public function loadGame(int $slot): bool
    {
        if (!Saveg::readAndRestore($this, $slot)) {
            return false;
        }
        $this->palette = -1;
        $this->keys = [];
        $this->automap->resetLevel();
        $this->player?->setMessage('game loaded.');
        fwrite(STDOUT, "Loaded E{$this->episode}M{$this->mapn}\n");
        return true;
    }

    public function returnToTitle(): void
    {
        $this->gamestate = Defs::GS_TITLE;
        $this->player = $this->world = $this->wi = $this->finale = null;
        $this->automap->resetLevel();
        $this->sound->playTitleMusic();
        $this->menu?->clear();
    }

    public function buildTiccmd(): Ticcmd
    {
        $cmd = new Ticcmd();
        $speed = (isset($this->keys[Keys::LSHIFT]) || isset($this->keys[Keys::RSHIFT])) ? 1 : 0;
        $strafe = isset($this->keys[Keys::LALT]) || isset($this->keys[Keys::RALT]);
        $turning = isset($this->keys[Keys::RIGHT]) || isset($this->keys[Keys::LEFT]);
        $this->turnheld = $turning ? $this->turnheld + 1 : 0;
        $turnSpeed = $this->turnheld < 6 ? 2 : $speed;
        if ($strafe) {
            if (isset($this->keys[Keys::RIGHT])) {
                $cmd->sidemove += Player::SIDEMOVE[$speed];
            }
            if (isset($this->keys[Keys::LEFT])) {
                $cmd->sidemove -= Player::SIDEMOVE[$speed];
            }
        } else {
            if (isset($this->keys[Keys::RIGHT])) {
                $cmd->angleturn -= Player::ANGLETURN[$turnSpeed];
            }
            if (isset($this->keys[Keys::LEFT])) {
                $cmd->angleturn += Player::ANGLETURN[$turnSpeed];
            }
        }
        if (isset($this->keys[Keys::UP])) {
            $cmd->forwardmove += Player::FORWARDMOVE[$speed];
        }
        if (isset($this->keys[Keys::DOWN])) {
            $cmd->forwardmove -= Player::FORWARDMOVE[$speed];
        }
        if (isset($this->keys[Keys::COMMA])) {
            $cmd->sidemove -= Player::SIDEMOVE[$speed];
        }
        if (isset($this->keys[Keys::PERIOD])) {
            $cmd->sidemove += Player::SIDEMOVE[$speed];
        }
        if (isset($this->keys[Keys::LCTRL]) || isset($this->keys[Keys::RCTRL])) {
            $cmd->buttons |= Defs::BT_ATTACK;
        }
        if (isset($this->keys[Keys::SPACE]) || isset($this->keys[ord('e')])) {
            $cmd->buttons |= Defs::BT_USE;
        }
        $weapons = [2 => Defs::WP_PISTOL, 3 => Defs::WP_SHOTGUN, 4 => Defs::WP_CHAINGUN, 5 => Defs::WP_MISSILE, 6 => Defs::WP_PLASMA, 7 => Defs::WP_BFG];
        if (isset($this->keys[ord('1')])) {
            $weapon = $this->player?->readyweapon === Defs::WP_CHAINSAW ? Defs::WP_FIST : (($this->player?->weaponowned[Defs::WP_CHAINSAW] ?? false) ? Defs::WP_CHAINSAW : Defs::WP_FIST);
            $cmd->buttons |= Defs::BT_CHANGE | ($weapon << Defs::BT_WEAPONSHIFT);
        } else {
            foreach ($weapons as $number => $weapon) {
                if (isset($this->keys[ord((string) $number)])) {
                    $cmd->buttons |= Defs::BT_CHANGE | ($weapon << Defs::BT_WEAPONSHIFT);
                    break;
                }
            }
        }
        return $cmd;
    }

    public function runTic(): void
    {
        if ($this->wiping) {
            if ($this->wipe->tick(1, $this->video->fb)) {
                $this->wiping = false;
            }
            return;
        }
        $this->menu?->ticker();
        $this->sound->update();
        if ($this->gamestate === Defs::GS_TITLE) {
            return;
        }
        if ($this->gamestate === Defs::GS_INTERMISSION) {
            $this->wi?->ticker();
            if ($this->wi?->done) {
                $this->worldDone();
            }
            return;
        }
        if ($this->gamestate === Defs::GS_FINALE) {
            $this->finale?->ticker();
            if ($this->finale?->done) {
                $this->returnToTitle();
            }
            return;
        }
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null) {
            return;
        }
        $this->player->cmd = $this->menu?->active ? new Ticcmd() : $this->buildTiccmd();
        Player::playerThink($this->world, $this->player, $this, $this->leveltime);
        Enemy::tickEnemies($this->world, $this);
        $this->specials?->tick();
        if ($this->specials?->exitRequested) {
            $this->startSound('swtchx');
            $this->completeLevel();
            return;
        }
        ++$this->leveltime;
        $this->automap->ticker($this);
    }

    public function draw(): void
    {
        $fb = &$this->video->fb;
        if ($this->wiping) {
            $this->menu?->draw($fb);
            $this->applyPalette();
            $this->video->present();
            return;
        }
        $need = $this->forceWipe || $this->gamestate !== $this->wipeState;
        $this->forceWipe = false;
        if ($need) {
            $this->wipe->captureStart($fb);
        }
        $this->drawFrame($fb);
        if ($need) {
            $this->wipe->captureEnd($fb);
            $this->wipe->begin($fb);
            $this->wipeState = $this->gamestate;
            $this->wiping = true;
        }
        $this->menu?->draw($fb);
        if ($this->video->showFps && $this->status !== null) {
            $this->status->drawText($fb, 250, 1, $this->video->fpsValue . ' FPS');
        }
        $this->applyPalette();
        $this->video->present();
    }

    /** @param array<int,int> $fb */
    private function drawFrame(array &$fb): void
    {
        if ($this->gamestate === Defs::GS_TITLE && $this->titlePatch !== null) {
            VVideo::fill($fb, 0);
            VVideo::drawPatch($fb, 0, 0, $this->titlePatch);
            return;
        }
        if ($this->gamestate === Defs::GS_INTERMISSION && $this->wi !== null) {
            $this->wi->draw($fb);
            return;
        }
        if ($this->gamestate === Defs::GS_FINALE && $this->finale !== null) {
            $this->finale->draw($fb);
            return;
        }
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null) {
            VVideo::fill($fb, 0);
            return;
        }
        if ($this->automap->active) {
            $this->automap->draw($fb, $this);
        } else {
            $mo = $this->player->mo;
            VVideo::fill($fb, 0);
            $this->renderer->setupFrame($mo->x, $mo->y, $this->player->viewz, $mo->angle, $this->player->extralight);
            $this->renderer->render($this->world, $fb);
            Sprites::drawSprites($this->renderer, $this->world, $fb);
            $this->renderer->drawMasked();
            $this->drawWeapon($fb);
        }
        if ($this->status !== null && ($this->automap->active || $this->renderer->screenblocks < 11)) {
            $this->status->draw($fb, $this->player, $this->showMessages);
        }
    }

    private function applyPalette(): void
    {
        $palette = 0;
        $p = $this->player;
        if ($p !== null && $this->gamestate === Defs::GS_LEVEL) {
            if ($p->damagecount) {
                $palette = min(7, ($p->damagecount + 7) >> 3) + 1;
            } elseif ($p->bonuscount) {
                $palette = min(3, ($p->bonuscount + 7) >> 3) + 9;
            }
        }
        if ($palette === $this->palette) {
            return;
        }
        $this->palette = $palette;
        if ($this->playpal !== null) {
            $raw = substr($this->playpal, $palette * 768, 768);
            if (strlen($raw) === 768) {
                $this->video->setPaletteRaw($raw);
            }
        }
    }

    /** @param array<int,int> $fb */
    private function drawWeapon(array &$fb): void
    {
        [$x, $y] = Sprites::weaponPspriteXy($this->player, $this->leveltime);
        $body = $this->player->pspriteBody ?: ((in_array($this->player->pspriteState, ['atk', 'fire'], true) ? self::WEAPON_FIRE_BODY[$this->player->readyweapon] ?? null : null) ?? self::WEAPON_PATCH[$this->player->readyweapon] ?? 'PISGA0');
        $n = $this->wad->checkNumForName($body);
        if ($n < 0) {
            $n = $this->wad->checkNumForName('PISGA0');
        }
        if ($n >= 0) {
            Sprites::drawPsprite($this->renderer, $fb, $this->wad->cacheLumpNum($n), $x, $y);
        }
        $flash = $this->player->flashTics > 0 ? $this->player->pspriteFlash : '';
        if ($flash === '' && $this->player->pspriteState === 'fire') {
            $flash = self::WEAPON_FIRE_PATCH[$this->player->readyweapon] ?? '';
        }
        if ($flash !== '' && ($n = $this->wad->checkNumForName($flash)) >= 0) {
            Sprites::drawPsprite($this->renderer, $fb, $this->wad->cacheLumpNum($n), $x, $y);
        }
    }

    public function applyViewSize(): void
    {
        $this->renderer?->setViewSize($this->screenSize + 3, $this->detailLevel);
    }

    /** M_SizeDisplay: same control as Options → Screen Size (not the OS window). */
    public function sizeDisplay(int $choice): void
    {
        $next = max(0, min(8, $this->screenSize + ($choice ? 1 : -1)));
        if ($next === $this->screenSize) {
            return;
        }
        $this->screenSize = $next;
        $this->applyViewSize();
        $this->startSound('stnmov');
    }

    public function handleEvent(string $type, int $key = 0, string $unicodeChar = ''): void
    {
        if ($type === 'quit') {
            $this->running = false;
            return;
        }
        if ($type === 'keydown') {
            if (in_array($key, [Keys::LALT, Keys::RALT, Keys::LSHIFT, Keys::RSHIFT, Keys::LCTRL, Keys::RCTRL], true)) {
                $this->keys[$key] = true;
            }
            if (in_array($key, [Keys::RETURN, Keys::KP_ENTER], true) && (isset($this->keys[Keys::LALT]) || isset($this->keys[Keys::RALT]))) {
                $this->video->toggleFullscreen();
                $this->fullscreen = $this->video->fullscreen;
                return;
            }
            if ($this->menu?->responder($key, $unicodeChar)) {
                return;
            }
            if ($this->automap->responder($type, $key, $this)) {
                return;
            }
            if (Keys::isPlus($key) || Keys::isMinus($key) || in_array($unicodeChar, ['+', '=', '-', '_'], true)) {
                if (!$this->automap->active) {
                    $plus = Keys::isPlus($key) || $unicodeChar === '+' || $unicodeChar === '=';
                    $this->sizeDisplay($plus ? 1 : 0);
                }
                return;
            }
            $this->keys[$key] = true;
            if (in_array($key, [Keys::RETURN, Keys::KP_ENTER], true) && $this->gamestate === Defs::GS_TITLE) {
                $this->loadLevel();
            } elseif ($key === Keys::F11) {
                $this->video->showFps = !$this->video->showFps;
            } else {
                $this->feedCheat($unicodeChar);
            }
        } elseif ($type === 'keyup') {
            unset($this->keys[$key]);
            $this->automap->responder($type, $key, $this);
        }
    }

    private function feedCheat(string $input): void
    {
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null || $this->skill === Defs::SK_NIGHTMARE) {
            return;
        }
        $char = strtolower($input);
        if (strlen($char) !== 1 || !ctype_alpha($char)) {
            return;
        }
        foreach ($this->cheats as $sequence => $position) {
            if ($char === ($sequence[$position] ?? '')) {
                if (++$position >= strlen($sequence)) {
                    $this->cheats[$sequence] = 0;
                    switch ($sequence) {
                        case 'iddqd':
                            $this->cheatGod();
                            break;
                        case 'idkfa':
                            $this->cheatAmmo(true);
                            break;
                        case 'idfa':
                            $this->cheatAmmo(false);
                            break;
                        case 'iddt':
                            if ($this->automap->active) {
                                $this->automap->cycleIddt();
                            }
                            break;
                        case 'idclip':
                        case 'idspispopd':
                            $this->cheatNoclip();
                            break;
                    }
                } else {
                    $this->cheats[$sequence] = $position;
                }
            } else {
                $this->cheats[$sequence] = $char === $sequence[0] ? 1 : 0;
            }
        }
    }

    private function cheatGod(): void
    {
        $p = $this->player;
        $p->cheats ^= Defs::CF_GODMODE;
        if (($p->cheats & Defs::CF_GODMODE) !== 0) {
            $p->health = 100;
            $p->mo->health = 100;
            $p->setMessage('Degreelessness Mode On');
        } else {
            $p->setMessage('Degreelessness Mode Off');
        }
    }

    private function cheatAmmo(bool $keys): void
    {
        $p = $this->player;
        $p->armorpoints = 200;
        $p->armortype = 2;
        $p->weaponowned = array_fill(0, count($p->weaponowned), true);
        foreach ($p->ammo as $i => $_) {
            $p->ammo[$i] = $p->maxammo[$i];
        }
        if ($keys) {
            $p->cards = array_fill(0, 6, true);
        }
        $p->setMessage($keys ? 'Very Happy Ammo Added' : 'Ammo Added');
    }

    private function cheatNoclip(): void
    {
        $p = $this->player;
        $p->cheats ^= Defs::CF_NOCLIP;
        if (($p->cheats & Defs::CF_NOCLIP) !== 0) {
            $p->mo->flags |= Defs::MF_NOCLIP;
        } else {
            $p->mo->flags &= ~Defs::MF_NOCLIP;
        }
        $p->setMessage(($p->cheats & Defs::CF_NOCLIP) !== 0 ? 'No Clipping Mode ON' : 'No Clipping Mode OFF');
    }

    public static function main(array $argv): int
    {
        try {
            $game = new self();
            $iwad = self::parseArgs($argv, $game);
            $path = self::findIwad($iwad);
            $game->iwadPath = $path;
            fwrite(STDOUT, "IWAD: {$path}\n");
            $game->wad->addFile($path);
            Tables::initTables();
            $game->res = new Resources($game->wad);
            $game->res->init();
            $game->renderer = new Renderer($game->res);
            $game->applyViewSize();
            $game->video->init($game->fullscreen, 'DOOM (PHP)');
            $game->video->showFps = $game->showFps;
            $game->video->crt = $game->crt;
            $game->playpal = $game->wad->cacheLumpName('PLAYPAL');
            $game->video->setPalette($game->playpal);
            $game->sound->init($game->wad);
            $game->sound->output = $game->video;
            $game->menu = new Menu($game->wad, $game->sound, $game);
            if ($game->wad->checkNumForName('TITLEPIC') >= 0) {
                $game->titlePatch = $game->wad->cacheLumpName('TITLEPIC');
            }
            $game->status = new Status($game->wad);
            if (in_array('-warp', $argv, true)) {
                $game->loadLevel();
            } else {
                $game->startSound('swtchn');
                $game->sound->playTitleMusic();
            }
            $tickMs = 1000 / Defs::TICRATE;
            $accum = 0.0;
            $last = $game->video->ticksMs();
            while ($game->running) {
                foreach ($game->video->pollEvents() as $event) {
                    $game->handleEvent($event['type'], (int) ($event['key'] ?? $event['sym'] ?? 0), (string) ($event['text'] ?? ''));
                }
                $now = $game->video->ticksMs();
                $accum += $now - $last;
                $last = $now;
                while ($accum >= $tickMs) {
                    $game->runTic();
                    $accum -= $tickMs;
                }
                $game->draw();
                if ($accum < $tickMs / 2) {
                    usleep(1000);
                }
            }
            $game->sound->stopMusic();
            $game->video->shutdown();
            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . "\n");
            return 1;
        }
    }

    private static function parseArgs(array $argv, self $game): ?string
    {
        $iwad = null;
        for ($i = 1, $n = count($argv); $i < $n; ++$i) {
            $arg = $argv[$i];
            if ($arg === '-iwad' && isset($argv[$i + 1])) {
                $iwad = $argv[++$i];
            } elseif ($arg === '-fps') {
                $game->showFps = true;
            } elseif ($arg === '-nomonsters') {
                $game->nomonsters = true;
            } elseif ($arg === '-warp' && isset($argv[$i + 2])) {
                $game->episode = (int) $argv[++$i];
                $game->mapn = (int) $argv[++$i];
            } elseif ($arg === '-skill' && isset($argv[$i + 1])) {
                $game->skill = (int) $argv[++$i];
            } elseif ($arg === '-fullscreen') {
                $game->fullscreen = true;
            } elseif ($arg === '-crt') {
                $game->crt = true;
            } elseif (!str_starts_with($arg, '-') && str_ends_with(strtolower($arg), '.wad')) {
                $iwad = $arg;
            }
        }
        return $iwad;
    }

    private static function findIwad(?string $explicit): string
    {
        if ($explicit !== null) {
            if (is_file($explicit)) {
                return realpath($explicit) ?: $explicit;
            }
            throw new \RuntimeException("IWAD not found: {$explicit}");
        }
        $roots = [getcwd(), __DIR__, dirname(__DIR__), dirname(__DIR__, 2)];
        $env = getenv('DOOMWADDIR') ?: getenv('DOOMWADPATH');
        if ($env) {
            $roots = array_merge(explode(PATH_SEPARATOR, $env), $roots);
        }
        foreach ($roots as $root) {
            foreach (self::IWADS as $name) {
                $path = $root . DIRECTORY_SEPARATOR . $name;
                if (is_file($path)) {
                    return realpath($path) ?: $path;
                }
            }
        }
        throw new \RuntimeException('No IWAD found. Put doom1.wad in this folder or pass -iwad file.wad');
    }
}
