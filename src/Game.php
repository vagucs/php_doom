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
    public bool $fastparm = false;
    public bool $respawnparm = false;
    public bool $respawnmonsters = false;
    public ?bool $_fastOn = null;
    public bool $fullscreen = false;
    public bool $crt = false;
    public string $iwadPath = '';
    public ?Menu $menu = null;
    public bool $showMessages = true;
    public int $detailLevel = 0;
    public int $screenSize = 7;
    public int $mouseSensitivity = 5;
    public bool $useMouse = true;
    public int $mouseX = 0;
    public int $mouseY = 0;
    public bool $mouseFire = false;
    public bool $nosound = false;
    public bool $nomusic = false;
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
    private ?string $creditPatch = null;
    private ?string $pagePatch = null;
    private int $pageTic = 0;
    private int $demoSequence = -1;
    private bool $advancedemo = false;
    private bool $demoPlayback = false;
    public bool $demoRecording = false;
    public string $demoName = '';
    public bool $singledemo = false;
    public bool $timingdemo = false;
    public int $timedemoStart = 0;
    public int $gametic = 0;
    public ?string $recordName = null;
    public ?string $playdemoName = null;
    public ?string $timedemoName = null;
    /** @var list<string> */
    public array $pwadFiles = [];
    private string $demoBuffer = '';
    private int $demoP = 0;
    private const DEMOMARKER = 0x80;
    private int $palette = -1;
    private ?string $playpal = null;
    private ?int $wipeState = Defs::GS_TITLE;
    private int $nextMap = 1;

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

    public function shootSpecial(object $line, object $thing): void
    {
        $this->specials?->shootSpecial($line, $thing);
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
            Info::boot();
            $mass = (int) Info::$liveMobjinfo[$target->type][Info::MI_MASS] ?: 100;
            $thrust = intdiv($damage * intdiv(Defs::FRACUNIT, 8) * 100, $mass);
            if (
                $damage < 40
                && $damage > $target->health
                && $target->z - $origin->z > 64 * Defs::FRACUNIT
                && (Enemy::publicRandom() & 1)
            ) {
                $angle = Compat::asU32($angle + Defs::ANG180);
                $thrust *= 4;
            }
            $target->momx += Compat::fixedMul($thrust, Tables::fineCos($angle));
            $target->momy += Compat::fixedMul($thrust, Tables::fineSin($angle));
        }
        if ($target->player !== null) {
            $p = $target->player;
            if ((($p->cheats & Defs::CF_GODMODE) !== 0 || $p->powers[Defs::PW_INVULNERABILITY]) && $damage < 1000) {
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
            $p->attacker = $source;
            if ($p->health <= 0) {
                $p->health = 0;
                $p->playerstate = Defs::PST_DEAD;
                $target->alive = false;
                $target->flags &= ~(Defs::MF_SOLID | Defs::MF_SHOOTABLE);
                $this->startSound('pldeth');
            } else {
                $this->startSound('plpain');
                $this->painOrWake($target, $source);
            }
            return;
        }
        $target->health -= $damage;
        if ($target->health <= 0) {
            Enemy::killMonster($target, $this, $source);
            return;
        }
        $this->painOrWake($target, $source);
    }

    private function painOrWake(object $target, ?object $source): void
    {
        Info::boot();
        $info = Info::$liveMobjinfo[$target->type];
        if (Enemy::publicRandom() < (int) $info[Info::MI_PAINCHANCE] && ($target->flags & Defs::MF_SKULLFLY) === 0) {
            $target->flags |= Defs::MF_JUSTHIT;
            if ((int) $info[Info::MI_PAINSTATE]) {
                Thinker::setMobjState($target, (int) $info[Info::MI_PAINSTATE], $this->world, $this);
            }
        }
        $target->reactiontime = 0;
        if ($source !== null && $source !== $target && $target->player === null) {
            $target->target = $source;
            if ($target->istate === (int) $info[Info::MI_SPAWNSTATE] && (int) $info[Info::MI_SEESTATE]) {
                Thinker::setMobjState($target, (int) $info[Info::MI_SEESTATE], $this->world, $this);
            }
        }
    }

    public function loadLevel(bool $carry = false): void
    {
        if ($this->res === null) {
            throw new \LogicException('resources not initialized');
        }
        $previous = $carry ? $this->player : null;
        Enemy::clearRandom();
        $this->world = new World();
        $this->world->setupLevel($this->wad, $this->res, $this->episode, $this->mapn);
        $this->specials = new Specials($this->world, $this->res, $this->sound);
        $this->player = null;
        [$this->totalkills, $this->totalitems] = Mobj::spawnMapThings($this->world, $this->skill, $this);
        if ($this->player === null) {
            throw new \RuntimeException('no player 1 start');
        }
        if ($previous !== null) {
            $this->carryPlayer($previous);
        }
        $this->player->killcount = $this->player->itemcount = $this->player->secretcount = 0;
        $this->totalsecret = count(array_filter($this->world->sectors, static fn($s) => $s->special === 9));
        Thinker::applyFast($this);
        $this->leveltime = 0;
        $this->gamestate = Defs::GS_LEVEL;
        $this->specials->exitRequested = false;
        $this->specials->secretExit = false;
        $this->wi = null;
        $this->finale = null;
        $this->sound->playLevelMusic($this->episode, $this->mapn);
        $this->automap->resetLevel();
        $this->status?->reset($this->player);
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

    public function worldDone(bool $fromFinale = false): void
    {
        $secret = $this->specials?->secretExit ?? false;
        if ($secret && $this->player !== null) {
            $this->player->didsecret = true;
        }
        if (!$fromFinale && $this->commercial() && Finale::commercialFinaleMap($this->mapn, $secret)) {
            $this->finale = new Finale($this);
            $this->gamestate = Defs::GS_FINALE;
            return;
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
        $this->demoPlayback = false;
        $this->advancedemo = false;
        $this->demoBuffer = '';
        $this->skill = $skill;
        $this->episode = $episode;
        $this->mapn = $map;
        $this->_fastOn = null;
        Thinker::applyFast($this);
        $this->loadLevel();
        if ($this->demoRecording) {
            $this->beginRecording();
        }
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
        $this->demoPlayback = false;
        $this->advancedemo = false;
        $this->demoBuffer = '';
        if (!Saveg::readAndRestore($this, $slot)) {
            return false;
        }
        $this->palette = -1;
        $this->keys = [];
        $this->automap->resetLevel();
        $this->status?->reset($this->player);
        $this->player?->setMessage('game loaded.');
        fwrite(STDOUT, "Loaded E{$this->episode}M{$this->mapn}\n");
        return true;
    }

    public function returnToTitle(): void
    {
        $this->player = $this->world = $this->wi = $this->finale = null;
        $this->automap->resetLevel();
        $this->menu?->clear();
        $this->startTitle();
    }

    public function startTitle(): void
    {
        $this->demoPlayback = false;
        $this->demoBuffer = '';
        $this->demoP = 0;
        $this->demoSequence = -1;
        $this->advancedemo = true;
        $this->doAdvanceDemo();
    }

    private function pageTicker(): void
    {
        --$this->pageTic;
        if ($this->pageTic < 0) {
            $this->advancedemo = true;
        }
    }

    private function doAdvanceDemo(): void
    {
        $this->advancedemo = false;
        $this->demoPlayback = false;
        $this->demoSequence = ($this->demoSequence + 1) % 6;
        switch ($this->demoSequence) {
            case 0:
                $this->pageTic = $this->commercial() ? Defs::TICRATE * 11 : 170;
                $this->gamestate = Defs::GS_TITLE;
                $this->pagePatch = $this->titlePatch;
                $this->sound->playTitleMusic();
                break;
            case 1:
                if (!$this->playDemo('demo1')) {
                    $this->advancedemo = true;
                    $this->doAdvanceDemo();
                }
                break;
            case 2:
                $this->pageTic = 200;
                $this->gamestate = Defs::GS_TITLE;
                $this->pagePatch = $this->creditPatch ?? $this->titlePatch;
                break;
            case 3:
                if (!$this->playDemo('demo2')) {
                    $this->advancedemo = true;
                    $this->doAdvanceDemo();
                }
                break;
            case 4:
                $this->pageTic = $this->commercial() ? Defs::TICRATE * 11 : 200;
                $this->gamestate = Defs::GS_TITLE;
                $this->pagePatch = $this->titlePatch;
                if ($this->commercial()) {
                    $this->sound->playTitleMusic();
                }
                break;
            default:
                if (!$this->playDemo('demo3')) {
                    $this->advancedemo = true;
                    $this->doAdvanceDemo();
                }
                break;
        }
    }

    private function playDemo(string $name): bool
    {
        $data = $this->loadDemoBytes($name);
        if ($data === null || strlen($data) < 13) {
            return false;
        }
        $this->demoBuffer = $data;
        $this->demoP = 0;
        $demoVersion = ord($data[$this->demoP++]);
        if ($demoVersion <= 4) {
            $this->demoP = 0;
        }
        $demoSkill = ord($data[$this->demoP++]);
        $demoEpisode = ord($data[$this->demoP++]);
        $demoMap = ord($data[$this->demoP++]);
        $this->demoP += 5;
        $this->demoP += 4;
        if ($demoSkill <= 4) {
            $this->skill = $demoSkill;
        }
        if ($demoEpisode >= 1) {
            $this->episode = $demoEpisode;
        }
        if ($demoMap >= 1) {
            $this->mapn = $demoMap;
        }
        $this->loadLevel(false);
        $this->demoPlayback = true;
        if ($this->timingdemo) {
            $this->timedemoStart = $this->video->ticksMs();
            $this->gametic = 0;
        }
        return true;
    }

    private function loadDemoBytes(string $name): ?string
    {
        foreach ([$name, $name . '.lmp'] as $path) {
            if (is_file($path)) {
                $data = file_get_contents($path);
                return $data === false ? null : $data;
            }
        }
        if ($this->wad->checkNumForName($name) < 0) {
            return null;
        }
        return $this->wad->cacheLumpName($name);
    }

    private function beginRecording(): void
    {
        $this->demoBuffer = chr(109)
            . chr($this->skill & 0xFF)
            . chr($this->episode & 0xFF)
            . chr($this->mapn & 0xFF)
            . chr(0)
            . chr($this->respawnparm ? 1 : 0)
            . chr($this->fastparm ? 1 : 0)
            . chr($this->nomonsters ? 1 : 0)
            . chr(0)
            . "\x01\x00\x00\x00";
        $this->demoP = strlen($this->demoBuffer);
        $this->demoRecording = true;
    }

    private function writeDemoTiccmd(Ticcmd $cmd): void
    {
        $this->demoBuffer .= chr($cmd->forwardmove & 0xFF)
            . chr($cmd->sidemove & 0xFF)
            . chr(($cmd->angleturn >> 8) & 0xFF)
            . chr($cmd->buttons & 0xFF);
        $this->demoP = strlen($this->demoBuffer);
    }

    private function finishRecording(): void
    {
        if (!$this->demoRecording) {
            return;
        }
        $this->demoBuffer .= chr(self::DEMOMARKER);
        $name = $this->demoName !== '' ? $this->demoName : 'demo.lmp';
        if (!str_ends_with(strtolower($name), '.lmp')) {
            $name .= '.lmp';
        }
        if (@file_put_contents($name, $this->demoBuffer) === false) {
            fwrite(STDOUT, "Demo write failed: {$name}\n");
        } else {
            fwrite(STDOUT, "Demo {$name} recorded\n");
        }
        $this->demoRecording = false;
    }

    private function checkDemoStatus(): void
    {
        if ($this->timingdemo) {
            $now = $this->video->ticksMs();
            $real = max(1, intdiv(($now - $this->timedemoStart) * Defs::TICRATE, 1000));
            $fps = ($this->gametic * Defs::TICRATE) / $real;
            fwrite(STDOUT, sprintf("timed %d gametics in %d realtics (%.1f fps)\n", $this->gametic, $real, $fps));
            $this->timingdemo = false;
            $this->demoPlayback = false;
            $this->running = false;
            return;
        }
        if ($this->demoPlayback) {
            $this->demoPlayback = false;
            if ($this->singledemo) {
                $this->running = false;
            } else {
                $this->advancedemo = true;
            }
            return;
        }
        if ($this->demoRecording) {
            $this->finishRecording();
            $this->running = false;
        }
    }

    private function demoSByte(): int
    {
        $n = ord($this->demoBuffer[$this->demoP++]);
        return $n >= 128 ? $n - 256 : $n;
    }

    private function readDemoTiccmd(): Ticcmd
    {
        $cmd = new Ticcmd();
        if ($this->demoBuffer === '' || $this->demoP + 4 > strlen($this->demoBuffer)
            || ord($this->demoBuffer[$this->demoP]) === self::DEMOMARKER) {
            $this->checkDemoStatus();
            return $cmd;
        }
        $cmd->forwardmove = $this->demoSByte();
        $cmd->sidemove = $this->demoSByte();
        $cmd->angleturn = ord($this->demoBuffer[$this->demoP++]) << 8;
        if ($cmd->angleturn >= 32768) {
            $cmd->angleturn -= 65536;
        }
        $cmd->buttons = ord($this->demoBuffer[$this->demoP++]);
        return $cmd;
    }

    private function beginPlay(): void
    {
        $this->demoPlayback = false;
        $this->advancedemo = false;
        $this->demoBuffer = '';
        $this->loadLevel(false);
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
        if ($this->useMouse) {
            $sens = ($this->mouseSensitivity + 5) / 10.0;
            $mx = (int) ($this->mouseX * $sens);
            $my = (int) ($this->mouseY * $sens);
            $cmd->forwardmove += $my;
            if ($cmd->forwardmove > 127) {
                $cmd->forwardmove = 127;
            }
            if ($cmd->forwardmove < -127) {
                $cmd->forwardmove = -127;
            }
            if ($strafe) {
                $cmd->sidemove += $mx * 2;
                if ($cmd->sidemove > 127) {
                    $cmd->sidemove = 127;
                }
                if ($cmd->sidemove < -127) {
                    $cmd->sidemove = -127;
                }
            } else {
                $cmd->angleturn -= $mx * 8;
            }
            if ($this->mouseFire) {
                $cmd->buttons |= Defs::BT_ATTACK;
            }
            $this->mouseX = 0;
            $this->mouseY = 0;
        }
        return $cmd;
    }

    public function runTic(): void
    {
        ++$this->gametic;
        $this->syncMouseGrab();
        if ($this->wiping) {
            if ($this->wipe->tick(1, $this->video->fb)) {
                $this->wiping = false;
            }
            return;
        }
        $this->menu?->ticker();
        $this->sound->update();
        if ($this->advancedemo) {
            $this->doAdvanceDemo();
        }
        if ($this->gamestate === Defs::GS_TITLE) {
            $this->pageTicker();
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
                if ($this->finale->action === 'worlddone') {
                    $this->worldDone(true);
                } else {
                    $this->returnToTitle();
                }
            }
            return;
        }
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null) {
            return;
        }
        if ($this->demoPlayback) {
            $this->player->cmd = $this->readDemoTiccmd();
        } elseif ($this->menu?->active) {
            $this->player->cmd = new Ticcmd();
        } else {
            $this->player->cmd = $this->buildTiccmd();
            if ($this->demoRecording) {
                $this->writeDemoTiccmd($this->player->cmd);
            }
        }
        Player::playerThink($this->world, $this->player, $this, $this->leveltime);
        if ($this->player->playerstate === Defs::PST_REBORN) {
            $this->loadLevel(false);
            return;
        }
        Enemy::tickEnemies($this->world, $this);
        $this->specials?->tick();
        if ($this->specials?->exitRequested) {
            $this->startSound('swtchx');
            $this->completeLevel();
            return;
        }
        ++$this->leveltime;
        $this->automap->ticker($this);
        $this->status?->ticker($this->player);
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
        if ($this->gamestate === Defs::GS_TITLE && $this->pagePatch !== null) {
            VVideo::fill($fb, 0);
            VVideo::drawPatch($fb, 0, 0, $this->pagePatch);
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
            $this->renderer->setupFrame($mo->x, $mo->y, $this->player->viewz, $mo->angle, $this->player->extralight, $this->player->fixedcolormap);
            $this->renderer->render($this->world, $fb);
            Sprites::drawSprites($this->renderer, $this->world, $fb);
            $this->renderer->drawMasked();
            if ($this->player->playerstate !== Defs::PST_DEAD
                || $this->player->pspriteSy < Sprites::WEAPONBOTTOM) {
                $this->drawWeapon($fb);
            }
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
            $cnt = $p->damagecount;
            if ($p->powers[Defs::PW_STRENGTH]) {
                $bzc = 12 - intdiv($p->powers[Defs::PW_STRENGTH], 64);
                if ($bzc > $cnt) {
                    $cnt = $bzc;
                }
            }
            if ($cnt) {
                $palette = min(7, ($cnt + 7) >> 3) + 1;
            } elseif ($p->bonuscount) {
                $palette = min(3, ($p->bonuscount + 7) >> 3) + 9;
            } elseif ($p->powers[Defs::PW_IRONFEET] > 4 * 32 || ($p->powers[Defs::PW_IRONFEET] & 8) !== 0) {
                $palette = Defs::RADIATIONPAL;
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

    private function syncMouseGrab(): void
    {
        $want = $this->useMouse
            && $this->gamestate === Defs::GS_LEVEL
            && !$this->demoPlayback
            && !($this->menu?->active ?? false);
        $this->video->setRelativeMouse($want);
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

    public function handleEvent(string $type, int $key = 0, string $unicodeChar = '', int $dx = 0, int $dy = 0, int $button = 0): void
    {
        if ($type === 'quit') {
            if ($this->demoRecording) {
                $this->finishRecording();
            }
            $this->running = false;
            return;
        }
        if ($type === 'mousemotion') {
            if ($this->useMouse) {
                $this->mouseX += $dx;
                $this->mouseY += -$dy;
            }
            return;
        }
        if ($type === 'mousedown') {
            if ($button === 1) {
                $this->mouseFire = true;
                if ($this->finale !== null && $this->gamestate === Defs::GS_FINALE) {
                    $this->finale->responder();
                }
            }
            return;
        }
        if ($type === 'mouseup') {
            if ($button === 1) {
                $this->mouseFire = false;
            }
            return;
        }
        if ($type === 'keydown') {
            if (in_array($key, [Keys::LALT, Keys::RALT, Keys::LSHIFT, Keys::RSHIFT, Keys::LCTRL, Keys::RCTRL, Keys::SPACE, Keys::RETURN, Keys::KP_ENTER, ord('e')], true)) {
                $this->keys[$key] = true;
            }
            if (in_array($key, [Keys::RETURN, Keys::KP_ENTER], true) && (isset($this->keys[Keys::LALT]) || isset($this->keys[Keys::RALT]))) {
                $this->video->toggleFullscreen();
                $this->fullscreen = $this->video->fullscreen;
                return;
            }
            if ($this->finale !== null && $this->gamestate === Defs::GS_FINALE && $this->finale->responder()) {
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
            if ($key === Keys::F11) {
                $this->video->showFps = !$this->video->showFps;
            } elseif ($this->demoPlayback || $this->gamestate === Defs::GS_TITLE) {
                $this->beginPlay();
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
        if ($this->gamestate !== Defs::GS_LEVEL || $this->player === null) {
            return;
        }
        $char = strtolower($input);
        if (strlen($char) !== 1 || !ctype_alnum($char)) {
            return;
        }
        $nightmare = $this->skill === Defs::SK_NIGHTMARE;
        foreach (Deh::get()->cheats as $cheat) {
            $param = $cheat->feed($char);
            if ($param === null) {
                continue;
            }
            if ($nightmare && !in_array($cheat->action, ['clev', 'iddt'], true)) {
                continue;
            }
            $this->doCheat($cheat->action, $param);
        }
    }

    private function doCheat(string $action, string $param): void
    {
        switch ($action) {
            case 'god':
                $this->cheatGod();
                break;
            case 'kfa':
                $this->cheatAmmo(true);
                break;
            case 'fa':
                $this->cheatAmmo(false);
                break;
            case 'noclip':
            case 'noclip2':
                $this->cheatNoclip();
                break;
            case 'iddt':
                if ($this->automap->active) {
                    $this->automap->cycleIddt();
                }
                break;
            case 'behold':
                $this->player->setMessage('invin visis rad allmap lite amp');
                break;
            case 'beholdv':
            case 'beholds':
            case 'beholdi':
            case 'beholdr':
            case 'beholda':
            case 'beholdl':
                $this->cheatBehold(strpos('vsiral', $action[6]));
                break;
            case 'choppers':
                $this->player->weaponowned[Defs::WP_CHAINSAW] = true;
                $this->player->pendingweapon = Defs::WP_CHAINSAW;
                $this->player->powers[Defs::PW_INVULNERABILITY] = 1;
                $this->player->setMessage("... doesn't suck - GM");
                break;
            case 'mypos':
                $mo = $this->player->mo;
                $this->player->setMessage(sprintf('ang=0x%x;x,y=(0x%x,0x%x)', $mo->angle, $mo->x, $mo->y));
                break;
            case 'clev':
                $this->cheatClev($param);
                break;
            case 'mus':
                $this->cheatMus($param);
                break;
        }
    }

    private function cheatGod(): void
    {
        $p = $this->player;
        $deh = Deh::get();
        $p->cheats ^= Defs::CF_GODMODE;
        if (($p->cheats & Defs::CF_GODMODE) !== 0) {
            $p->health = $deh->godModeHealth;
            $p->mo->health = $deh->godModeHealth;
            $p->setMessage('Degreelessness Mode On');
        } else {
            $p->setMessage('Degreelessness Mode Off');
        }
    }

    private function cheatAmmo(bool $keys): void
    {
        $p = $this->player;
        $deh = Deh::get();
        $p->armorpoints = $keys ? $deh->idkfaArmor : $deh->idfaArmor;
        $p->armortype = $keys ? $deh->idkfaArmorClass : $deh->idfaArmorClass;
        $p->weaponowned = array_fill(0, count($p->weaponowned), true);
        $p->maxammo = $deh->maxammo;
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

    private function cheatBehold(int|false $pw): void
    {
        if ($pw === false || $pw < 0) {
            return;
        }
        $p = $this->player;
        if (!$p->powers[$pw]) {
            Player::givePower($p, $pw);
            if ($pw === Defs::PW_STRENGTH && $p->readyweapon !== Defs::WP_FIST) {
                $p->pendingweapon = Defs::WP_FIST;
            }
        } elseif ($pw === Defs::PW_STRENGTH) {
            $p->powers[$pw] = 0;
        } else {
            $p->powers[$pw] = 1;
        }
        $p->setMessage('Power-up Toggled');
    }

    private function cheatClev(string $param): void
    {
        if (strlen($param) < 2 || !ctype_digit($param)) {
            return;
        }
        $a = (int) $param[0];
        $b = (int) $param[1];
        if ($this->commercial()) {
            $episode = 1;
            $mapn = $a * 10 + $b;
            $lump = sprintf('MAP%02d', $mapn);
        } else {
            $episode = $a;
            $mapn = $b;
            $lump = "E{$episode}M{$mapn}";
        }
        if ($episode < 1 || $mapn < 1 || $this->wad->checkNumForName($lump) < 0) {
            return;
        }
        $this->player->setMessage('Changing Level...');
        $this->startNewGame($this->skill, $episode, $mapn);
    }

    private function cheatMus(string $param): void
    {
        if (strlen($param) < 2 || !ctype_digit($param)) {
            return;
        }
        $a = (int) $param[0];
        $b = (int) $param[1];
        if ($this->commercial()) {
            $mapn = $a * 10 + $b;
            $tracks = Sound::doom2Music();
            if ($mapn < 1 || $mapn > count($tracks)) {
                $this->player->setMessage('IMPOSSIBLE SELECTION');
                return;
            }
            $name = $tracks[$mapn - 1];
        } else {
            if ($a < 1 || $b < 1 || $b > 9) {
                $this->player->setMessage('IMPOSSIBLE SELECTION');
                return;
            }
            $name = "e{$a}m{$b}";
        }
        if (!$this->sound->hasMusic($name)) {
            $this->player->setMessage('IMPOSSIBLE SELECTION');
            return;
        }
        $this->sound->changeMusic($name, true);
        $this->player->setMessage('Music Change');
    }

    public static function main(array $argv): int
    {
        try {
            $game = new self();
            Config::load($game);
            $iwad = self::parseArgs($argv, $game);
            $path = self::findIwad($iwad);
            $game->iwadPath = $path;
            fwrite(STDOUT, "IWAD: {$path}\n");
            $game->wad->addFile($path);
            foreach ($game->pwadFiles as $extra) {
                fwrite(STDOUT, "PWAD: {$extra}\n");
                $game->wad->addFile($extra);
            }
            Deh::get()->loadAfterIwad($game->wad, $path);
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
            if ($game->nosound) {
                $game->sound->enabled = false;
                $game->sound->musicEnabled = false;
            }
            if ($game->nomusic) {
                $game->sound->musicEnabled = false;
            }
            $game->sound->init($game->wad);
            $game->sound->output = $game->video;
            $game->menu = new Menu($game->wad, $game->sound, $game);
            if ($game->wad->checkNumForName('TITLEPIC') >= 0) {
                $game->titlePatch = $game->wad->cacheLumpName('TITLEPIC');
            }
            if ($game->wad->checkNumForName('CREDIT') >= 0) {
                $game->creditPatch = $game->wad->cacheLumpName('CREDIT');
            }
            $game->pagePatch = $game->titlePatch;
            $game->status = new Status($game->wad);
            if ($game->recordName !== null) {
                $game->demoName = $game->recordName;
                $game->demoRecording = true;
                $game->startNewGame($game->skill, $game->episode, $game->mapn);
            } elseif ($game->timedemoName !== null) {
                $game->timingdemo = true;
                $game->singledemo = true;
                if (!$game->playDemo($game->timedemoName)) {
                    fwrite(STDOUT, "timedemo not found: {$game->timedemoName}\n");
                    $game->video->shutdown();
                    return 1;
                }
            } elseif ($game->playdemoName !== null) {
                $game->singledemo = true;
                if (!$game->playDemo($game->playdemoName)) {
                    fwrite(STDOUT, "playdemo not found: {$game->playdemoName}\n");
                    $game->video->shutdown();
                    return 1;
                }
            } elseif (in_array('-warp', $argv, true)) {
                $game->loadLevel();
            } else {
                $game->startTitle();
            }
            $tickMs = 1000 / Defs::TICRATE;
            $accum = 0.0;
            $last = $game->video->ticksMs();
            while ($game->running) {
                foreach ($game->video->pollEvents() as $event) {
                    $game->handleEvent(
                        $event['type'],
                        (int) ($event['key'] ?? $event['sym'] ?? 0),
                        (string) ($event['text'] ?? ''),
                        (int) ($event['dx'] ?? 0),
                        (int) ($event['dy'] ?? 0),
                        (int) ($event['button'] ?? 0),
                    );
                }
                $now = $game->video->ticksMs();
                $accum += $now - $last;
                $last = $now;
                if ($game->timingdemo) {
                    $game->runTic();
                } else {
                    while ($accum >= $tickMs) {
                        $game->runTic();
                        $accum -= $tickMs;
                    }
                }
                $game->draw();
                if (!$game->timingdemo && $accum < $tickMs / 2) {
                    usleep(1000);
                }
            }
            if ($game->demoRecording) {
                $game->finishRecording();
            }
            Config::save($game);
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
            } elseif ($arg === '-fast') {
                $game->fastparm = true;
            } elseif ($arg === '-respawn') {
                $game->respawnparm = true;
            } elseif ($arg === '-warp' && isset($argv[$i + 2])) {
                $game->episode = (int) $argv[++$i];
                $game->mapn = (int) $argv[++$i];
            } elseif ($arg === '-skill' && isset($argv[$i + 1])) {
                $game->skill = (int) $argv[++$i];
            } elseif ($arg === '-fullscreen') {
                $game->fullscreen = true;
            } elseif ($arg === '-crt') {
                $game->crt = true;
            } elseif ($arg === '-deh') {
                while (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    Deh::get()->files[] = $argv[++$i];
                }
            } elseif ($arg === '-nodeh') {
                Deh::get()->nodeh = true;
            } elseif ($arg === '-dehlump') {
                Deh::get()->dehlump = true;
            } elseif ($arg === '-nocheats') {
                Deh::get()->applyCheats = false;
            } elseif ($arg === '-file') {
                while (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '-')) {
                    $game->pwadFiles[] = $argv[++$i];
                }
            } elseif ($arg === '-record' && isset($argv[$i + 1])) {
                $game->recordName = $argv[++$i];
            } elseif ($arg === '-playdemo' && isset($argv[$i + 1])) {
                $game->playdemoName = $argv[++$i];
            } elseif ($arg === '-timedemo' && isset($argv[$i + 1])) {
                $game->timedemoName = $argv[++$i];
            } elseif ($arg === '-nosound') {
                $game->nosound = true;
            } elseif ($arg === '-nomusic') {
                $game->nomusic = true;
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
