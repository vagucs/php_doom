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
 *
 * End-of-episode / Doom 2 texts + MAP30 cast (f_finale.prg).
 */
declare(strict_types=1);

namespace Doom;

final class Finale
{
    private const TEXT_SPEED = 3;
    private const TEXT_WAIT = 250;
    private const TEXT = 0;
    private const ART = 1;
    private const CAST = 2;

    private const E1TEXT = "Once you beat the big badasses and\nclean out the moon base you're supposed\nto win, aren't you? Aren't you? Where's\nyour fat reward and ticket home? What\nthe hell is this? It's not supposed to\nend this way!\n\nIt stinks like rotten meat, but looks\nlike the lost Deimos base.  Looks like\nyou're stuck on The Shores of Hell.\nThe only way out is through.\n\nTo continue the DOOM experience, play\nThe Shores of Hell and its amazing\nsequel, Inferno!\n";
    private const E2TEXT = "You've done it! The hideous cyber-\ndemon lord that ruled the lost Deimos\nmoon base has been slain and you\nare triumphant! But ... where are\nyou? You clamber to the edge of the\nmoon and look down to see the awful\ntruth.\n\nDeimos floats above Hell itself!\nYou've never heard of anyone escaping\nfrom Hell, but you'll make the bastards\nsorry they ever heard of you! Quickly,\nyou rappel down to  the surface of\nHell.\n\nNow, it's on to the final chapter of\nDOOM! -- Inferno.\n";
    private const E3TEXT = "The loathsome spiderdemon that\nmasterminded the invasion of the moon\nbases and caused so much death has had\nits ass kicked for all time.\n\nA hidden doorway opens and you enter.\nYou've proven too tough for Hell to\ncontain, and now Hell at last plays\nfair -- for you emerge from the door\nto see the green fields of Earth!\nHome at last.\n\nYou wonder what's been happening on\nEarth while you were battling evil\nunleashed. It's good that no Hell-\nspawn could have come through that\ndoor with you ...\n";
    private const E4TEXT = "the spider mastermind must have sent forth\nits legions of hellspawn before your\nfinal confrontation with that terrible\nbeast from hell.  but you stepped forward\nand brought forth eternal damnation and\nsuffering upon the horde as a true hero\nwould in the face of something so evil.\n\nbesides, someone was gonna pay for what\nhappened to daisy, your pet rabbit.\n\nbut now, you see spread before you more\npotential pain and gibbitude as a nation\nof demons run amok among our cities.\n\nnext stop, hell on earth!";
    private const C1TEXT = "YOU HAVE ENTERED DEEPLY INTO THE INFESTED\nSTARPORT. BUT SOMETHING IS WRONG. THE\nMONSTERS HAVE BROUGHT THEIR OWN REALITY\nWITH THEM, AND THE STARPORT'S TECHNOLOGY\nIS BEING SUBVERTED BY THEIR PRESENCE.\n\nAHEAD, YOU SEE AN OUTPOST OF HELL, A\nFORTIFIED ZONE. IF YOU CAN GET PAST IT,\nYOU CAN PENETRATE INTO THE HAUNTED HEART\nOF THE STARBASE AND FIND THE CONTROLLING\nSWITCH WHICH HOLDS EARTH'S POPULATION\nHOSTAGE.";
    private const C2TEXT = "YOU HAVE WON! YOUR VICTORY HAS ENABLED\nHUMANKIND TO EVACUATE EARTH AND ESCAPE\nTHE NIGHTMARE.  NOW YOU ARE THE ONLY\nHUMAN LEFT ON THE FACE OF THE PLANET.\nCANNIBAL MUTATIONS, CARNIVOROUS ALIENS,\nAND EVIL SPIRITS ARE YOUR ONLY NEIGHBORS.\nYOU SIT BACK AND WAIT FOR DEATH, CONTENT\nTHAT YOU HAVE SAVED YOUR SPECIES.\n\nBUT THEN, EARTH CONTROL BEAMS DOWN A\nMESSAGE FROM SPACE: \"SENSORS HAVE LOCATED\nTHE SOURCE OF THE ALIEN INVASION. IF YOU\nGO THERE, YOU MAY BE ABLE TO BLOCK THEIR\nENTRY.  THE ALIEN BASE IS IN THE HEART OF\nYOUR OWN HOME CITY, NOT FAR FROM THE\nSTARPORT.\" SLOWLY AND PAINFULLY YOU GET\nUP AND RETURN TO THE FRAY.";
    private const C3TEXT = "YOU ARE AT THE CORRUPT HEART OF THE CITY,\nSURROUNDED BY THE CORPSES OF YOUR ENEMIES.\nYOU SEE NO WAY TO DESTROY THE CREATURES'\nENTRYWAY ON THIS SIDE, SO YOU CLENCH YOUR\nTEETH AND PLUNGE THROUGH IT.\n\nTHERE MUST BE A WAY TO CLOSE IT ON THE\nOTHER SIDE. WHAT DO YOU CARE IF YOU'VE\nGOT TO GO THROUGH HELL TO GET TO IT?";
    private const C4TEXT = "THE HORRENDOUS VISAGE OF THE BIGGEST\nDEMON YOU'VE EVER SEEN CRUMBLES BEFORE\nYOU, AFTER YOU PUMP YOUR ROCKETS INTO\nHIS EXPOSED BRAIN. THE MONSTER SHRIVELS\nUP AND DIES, ITS THRASHING LIMBS\nDEVASTATING UNTOLD MILES OF HELL'S\nSURFACE.\n\nYOU'VE DONE IT. THE INVASION IS OVER.\nEARTH IS SAVED. HELL IS A WRECK. YOU\nWONDER WHERE BAD FOLKS WILL GO WHEN THEY\nDIE, NOW. WIPING THE SWEAT FROM YOUR\nFOREHEAD YOU BEGIN THE LONG TREK BACK\nHOME. REBUILDING EARTH OUGHT TO BE A\nLOT MORE FUN THAN RUINING IT WAS.\n";
    private const C5TEXT = "CONGRATULATIONS, YOU'VE FOUND THE SECRET\nLEVEL! LOOKS LIKE IT'S BEEN BUILT BY\nHUMANS, RATHER THAN DEMONS. YOU WONDER\nWHO THE INMATES OF THIS CORNER OF HELL\nWILL BE.";
    private const C6TEXT = "CONGRATULATIONS, YOU'VE FOUND THE\nSUPER SECRET LEVEL!  YOU'D BETTER\nBLAZE THROUGH THIS ONE!\n";
    private const P1TEXT = "You gloat over the steaming carcass of the\nGuardian.  With its death, you've wrested\nthe Accelerator from the stinking claws\nof Hell.  You relax and glance around the\nroom.  Damn!  There was supposed to be at\nleast one working prototype, but you can't\nsee it. The demons must have taken it.\n\nYou must find the prototype, or all your\nstruggles will have been wasted. Keep\nmoving, keep fighting, keep killing.\nOh yes, keep living, too.";
    private const P2TEXT = "Even the deadly Arch-Vile labyrinth could\nnot stop you, and you've gotten to the\nprototype Accelerator which is soon\nefficiently and permanently deactivated.\n\nYou're good at that kind of thing.";
    private const P3TEXT = "You've bashed and battered your way into\nthe heart of the devil-hive.  Time for a\nSearch-and-Destroy mission, aimed at the\nGatekeeper, whose foul offspring is\ncascading to Earth.  Yeah, he's bad. But\nyou know who's worse!\n\nGrinning evilly, you check your gear, and\nget ready to give the bastard a little Hell\nof your own making!";
    private const P4TEXT = "The Gatekeeper's evil face is splattered\nall over the place.  As its tattered corpse\ncollapses, an inverted Gate forms and\nsucks down the shards of the last\nprototype Accelerator, not to mention the\nfew remaining demons.  You're done. Hell\nhas gone back to pounding bad dead folks \ninstead of good live ones.  Remember to\ntell your grandkids to put a rocket\nlauncher in your coffin. If you go to Hell\nwhen you die, you'll need it for some\nfinal cleaning-up ...";
    private const P5TEXT = "You've found the second-hardest level we\ngot. Hope you have a saved game a level or\ntwo previous.  If not, be prepared to die\naplenty. For master marines only.";
    private const P6TEXT = "Betcha wondered just what WAS the hardest\nlevel we had ready for ya?  Now you know.\nNo one gets out alive.";
    private const T1TEXT = "You've fought your way out of the infested\nexperimental labs.   It seems that UAC has\nonce again gulped it down.  With their\nhigh turnover, it must be hard for poor\nold UAC to buy corporate health insurance\nnowadays..\n\nAhead lies the military complex, now\nswarming with diseased horrors hot to get\ntheir teeth into you. With luck, the\ncomplex still has some warlike ordnance\nlaying around.";
    private const T2TEXT = "You hear the grinding of heavy machinery\nahead.  You sure hope they're not stamping\nout new hellspawn, but you're ready to\nream out a whole herd if you have to.\nThey might be planning a blood feast, but\nyou feel about as mean as two thousand\nmaniacs packed into one mad killer.\n\nYou don't plan to go down easy.";
    private const T3TEXT = "The vista opening ahead looks real damn\nfamiliar. Smells familiar, too -- like\nfried excrement. You didn't like this\nplace before, and you sure as hell ain't\nplanning to like it now. The more you\nbrood on it, the madder you get.\nHefting your gun, an evil grin trickles\nonto your face. Time to take some names.";
    private const T4TEXT = "Suddenly, all is silent, from one horizon\nto the other. The agonizing echo of Hell\nfades away, the nightmare sky turns to\nblue, the heaps of monster corpses start \nto evaporate along with the evil stench \nthat filled the air. Jeeze, maybe you've\ndone it. Have you really won?\n\nSomething rumbles in the distance.\nA blue light begins to glow inside the\nruined skull of the demon-spitter.";
    private const T5TEXT = "What now? Looks totally different. Kind\nof like King Tut's condo. Well,\nwhatever's here can't be any worse\nthan usual. Can it?  Or maybe it's best\nto let sleeping gods lie..";
    private const T6TEXT = "Time for a vacation. You've burst the\nbowels of hell and by golly you're ready\nfor a break. You mutter to yourself,\nMaybe someone else can kick Hell's ass\nnext time around. Ahead lies a quiet town,\nwith peaceful flowing water, quaint\nbuildings, and presumably no Hellspawn.\n\nAs you step off the transport, you hear\nthe stomp of a cyberdemon's iron shoe.";

    /** @var list<array{0:string,1:int}> */
    private const CASTORDER = [
        ['ZOMBIEMAN', Info::MT_POSSESSED],
        ['SHOTGUN GUY', Info::MT_SHOTGUY],
        ['HEAVY WEAPON DUDE', Info::MT_CHAINGUY],
        ['IMP', Info::MT_TROOP],
        ['DEMON', Info::MT_SERGEANT],
        ['LOST SOUL', Info::MT_SKULL],
        ['CACODEMON', Info::MT_HEAD],
        ['HELL KNIGHT', Info::MT_KNIGHT],
        ['BARON OF HELL', Info::MT_BRUISER],
        ['ARACHNOTRON', Info::MT_BABY],
        ['PAIN ELEMENTAL', Info::MT_PAIN],
        ['REVENANT', Info::MT_UNDEAD],
        ['MANCUBUS', Info::MT_FATSO],
        ['ARCH-VILE', Info::MT_VILE],
        ['THE SPIDER MASTERMIND', Info::MT_SPIDER],
        ['THE CYBERDEMON', Info::MT_CYBORG],
        ['OUR HERO', Info::MT_PLAYER],
    ];

    /** @var array<int,string> */
    private const CAST_SFX = [
        Info::S_PLAY_ATK1 => 'dshtgn',
        Info::S_POSS_ATK2 => 'pistol',
        Info::S_SPOS_ATK2 => 'shotgn',
        Info::S_VILE_ATK2 => 'vilatk',
        Info::S_SKEL_FIST2 => 'skeswg',
        Info::S_SKEL_FIST4 => 'skepch',
        Info::S_SKEL_MISS2 => 'skeatk',
        Info::S_FATT_ATK8 => 'firsht',
        Info::S_FATT_ATK5 => 'firsht',
        Info::S_FATT_ATK2 => 'firsht',
        Info::S_CPOS_ATK2 => 'shotgn',
        Info::S_CPOS_ATK3 => 'shotgn',
        Info::S_CPOS_ATK4 => 'shotgn',
        Info::S_TROO_ATK3 => 'claw',
        Info::S_SARG_ATK2 => 'sgtatk',
        Info::S_BOSS_ATK2 => 'firsht',
        Info::S_BOS2_ATK2 => 'firsht',
        Info::S_HEAD_ATK2 => 'firsht',
        Info::S_SKULL_ATK2 => 'sklatk',
        Info::S_SPID_ATK2 => 'shotgn',
        Info::S_SPID_ATK3 => 'shotgn',
        Info::S_BSPI_ATK2 => 'plasma',
        Info::S_CYBER_ATK2 => 'rlaunc',
        Info::S_CYBER_ATK4 => 'rlaunc',
        Info::S_CYBER_ATK6 => 'rlaunc',
        Info::S_PAIN_ATK3 => 'sklatk',
    ];

    private object $game;
    private int $stage = self::TEXT;
    private int $count = 0;
    public bool $done = false;
    public string $action = '';
    private bool $commercial = false;
    private string $text;
    private string $flat;
    private ?string $flatLump = null;
    private ?string $art = null;
    private ?string $pfub1 = null;
    private ?string $pfub2 = null;
    private ?string $bossback = null;
    private int $lastBunnyStage = -1;
    private int $castnum = 0;
    private int $caststate = Info::S_NULL;
    private int $casttics = 0;
    private bool $castdeath = false;
    private int $castframes = 0;
    private int $castonmelee = 0;
    private bool $castattacking = false;

    public function __construct(object $game)
    {
        $this->game = $game;
        Info::boot();
        $commercial = $game->wad->checkNumForName('MAP01') >= 0;
        $this->commercial = $commercial;
        if ($commercial) {
            $screens = self::missionScreens((string) ($game->iwadPath ?? ''));
            [$this->flat, $this->text] = $screens[$game->mapn] ?? ['SLIME16', self::C1TEXT];
            $game->sound->changeMusic('read_m', true);
        } else {
            $this->text = [1 => self::E1TEXT, 2 => self::E2TEXT, 3 => self::E3TEXT, 4 => self::E4TEXT][$game->episode] ?? self::E1TEXT;
            $this->flat = [1 => 'FLOOR4_8', 2 => 'SFLR6_1', 3 => 'MFLR8_4', 4 => 'MFLR8_3'][$game->episode] ?? 'FLOOR4_8';
            $game->sound->changeMusic('victor', true);
        }
        $this->flatLump = $this->lump($this->flat);
        if ($commercial) {
            $art = $this->has('CREDIT') ? 'CREDIT' : 'HELP2';
        } elseif ($game->episode === 2) {
            $art = 'VICTORY2';
        } elseif ($game->episode === 4) {
            $art = 'ENDPIC';
        } else {
            $art = $this->has('CREDIT') ? 'CREDIT' : 'HELP2';
        }
        $this->art = $this->lump($art) ?? $this->lump('HELP1');
        if ($game->episode === 3 && !$commercial) {
            $this->pfub1 = $this->lump('PFUB1');
            $this->pfub2 = $this->lump('PFUB2');
        }
        $this->bossback = $this->lump('BOSSBACK');
    }

    public static function commercialFinaleMap(int $mapn, bool $secret): bool
    {
        if (in_array($mapn, [6, 11, 20, 30], true)) {
            return true;
        }
        return $secret && in_array($mapn, [15, 31], true);
    }

    public function ticker(): void
    {
        if ($this->commercial && $this->stage === self::TEXT && $this->count > 50 && $this->wantSkip()) {
            if ($this->game->mapn === 30) {
                $this->startCast();
            } else {
                $this->action = 'worlddone';
                $this->done = true;
                return;
            }
        }
        ++$this->count;
        if ($this->stage === self::CAST) {
            $this->castTicker();
            return;
        }
        if ($this->commercial) {
            return;
        }
        if ($this->stage === self::TEXT) {
            if ($this->count > strlen($this->text) * self::TEXT_SPEED + self::TEXT_WAIT) {
                $this->stage = self::ART;
                $this->count = 0;
                $this->game->forceWipe = true;
                if ($this->game->episode === 3) {
                    $this->game->sound->changeMusic('bunny', true);
                }
            }
        } elseif ($this->stage === self::ART) {
            $skipAfter = $this->pfub1 !== null && $this->pfub2 !== null ? 1130 : 10;
            if ($this->wantSkip() && $this->count > $skipAfter) {
                $this->done = true;
                $this->action = 'title';
            }
        }
    }

    public function responder(): bool
    {
        if ($this->stage !== self::CAST || $this->castdeath) {
            return false;
        }
        if (!$this->wantSkip()) {
            return false;
        }
        Info::boot();
        $info = Info::MOBJINFO[self::CASTORDER[$this->castnum][1]];
        $this->castdeath = true;
        $this->caststate = (int) $info[Info::MI_DEATHSTATE];
        $this->casttics = (int) Info::STATES[$this->caststate][2];
        if ($this->casttics === -1) {
            $this->casttics = 15;
        }
        $this->castframes = 0;
        $this->castattacking = false;
        return true;
    }

    /** @param array<int,int> $fb */
    public function draw(array &$fb): void
    {
        if ($this->stage === self::CAST) {
            $this->drawCast($fb);
            return;
        }
        if ($this->stage === self::ART) {
            if ($this->game->episode === 3 && $this->pfub1 !== null && $this->pfub2 !== null) {
                $this->drawBunny($fb);
            } elseif ($this->art !== null) {
                VVideo::fill($fb, 0);
                VVideo::drawPatch($fb, 0, 0, $this->art);
            }
            return;
        }
        $this->drawText($fb);
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function missionScreens(string $iwadPath): array
    {
        $name = strtolower(basename($iwadPath));
        if (str_contains($name, 'tnt')) {
            return [
                6 => ['SLIME16', self::T1TEXT],
                11 => ['RROCK14', self::T2TEXT],
                20 => ['RROCK07', self::T3TEXT],
                30 => ['RROCK17', self::T4TEXT],
                15 => ['RROCK13', self::T5TEXT],
                31 => ['RROCK19', self::T6TEXT],
            ];
        }
        if (str_contains($name, 'plut')) {
            return [
                6 => ['SLIME16', self::P1TEXT],
                11 => ['RROCK14', self::P2TEXT],
                20 => ['RROCK07', self::P3TEXT],
                30 => ['RROCK17', self::P4TEXT],
                15 => ['RROCK13', self::P5TEXT],
                31 => ['RROCK19', self::P6TEXT],
            ];
        }
        return [
            6 => ['SLIME16', self::C1TEXT],
            11 => ['RROCK14', self::C2TEXT],
            20 => ['RROCK07', self::C3TEXT],
            30 => ['RROCK17', self::C4TEXT],
            15 => ['RROCK13', self::C5TEXT],
            31 => ['RROCK19', self::C6TEXT],
        ];
    }

    private function startCast(): void
    {
        Info::boot();
        $this->game->forceWipe = true;
        $this->castnum = 0;
        $info = $this->castInfo();
        $this->caststate = (int) $info[Info::MI_SEESTATE];
        $this->casttics = (int) Info::STATES[$this->caststate][2];
        $this->castdeath = false;
        $this->stage = self::CAST;
        $this->castframes = 0;
        $this->castonmelee = 0;
        $this->castattacking = false;
        $this->game->sound->changeMusic('evil', true);
    }

    /** @return array<int,mixed> */
    private function castInfo(): array
    {
        Info::boot();
        return Info::MOBJINFO[self::CASTORDER[$this->castnum][1]];
    }

    private function stopAttack(): void
    {
        $this->castattacking = false;
        $this->castframes = 0;
        $this->caststate = (int) $this->castInfo()[Info::MI_SEESTATE];
    }

    private function castTicker(): void
    {
        --$this->casttics;
        if ($this->casttics > 0) {
            return;
        }
        $st = Info::STATES[$this->caststate];
        if ((int) $st[2] === -1 || (int) $st[4] === Info::S_NULL) {
            ++$this->castnum;
            $this->castdeath = false;
            if ($this->castnum >= count(self::CASTORDER)) {
                $this->castnum = 0;
            }
            $this->caststate = (int) $this->castInfo()[Info::MI_SEESTATE];
            $this->castframes = 0;
        } else {
            if ($this->caststate === Info::S_PLAY_ATK1) {
                $this->stopAttack();
            } else {
                $nxt = (int) $st[4];
                $this->caststate = $nxt;
                ++$this->castframes;
                $sfx = self::CAST_SFX[$nxt] ?? null;
                if ($sfx !== null) {
                    $this->game->sound->play($sfx);
                }
            }
        }
        if ($this->castframes === 12) {
            $this->castattacking = true;
            $info = $this->castInfo();
            $this->caststate = $this->castonmelee ? (int) $info[Info::MI_MELEESTATE] : (int) $info[Info::MI_MISSILESTATE];
            $this->castonmelee ^= 1;
            if ($this->caststate === Info::S_NULL) {
                $this->caststate = $this->castonmelee ? (int) $info[Info::MI_MELEESTATE] : (int) $info[Info::MI_MISSILESTATE];
            }
        }
        if ($this->castattacking) {
            if ($this->castframes === 24 || $this->caststate === (int) $this->castInfo()[Info::MI_SEESTATE]) {
                $this->stopAttack();
            }
        }
        $this->casttics = (int) Info::STATES[$this->caststate][2];
        if ($this->casttics === -1) {
            $this->casttics = 15;
        }
    }

    /** @param array<int,int> $fb */
    private function drawCast(array &$fb): void
    {
        VVideo::fill($fb, 0);
        if ($this->bossback !== null) {
            VVideo::drawPatch($fb, 0, 0, $this->bossback);
        }
        $this->castPrint($fb, self::CASTORDER[$this->castnum][0]);
        $st = Info::STATES[$this->caststate];
        $sprIndex = (int) $st[0];
        $spr = ($sprIndex >= 0 && $sprIndex < count(Info::SPRNAMES)) ? Info::SPRNAMES[$sprIndex] : '';
        $frame = (int) $st[1] & Info::FF_FRAMEMASK;
        if ($this->game->res === null) {
            return;
        }
        $found = Sprites::lookupSprite($this->game->res, $spr, 0, 0, $frame);
        if ($found === null) {
            return;
        }
        [$lump, $flip] = $found;
        $patch = $this->game->wad->cacheLumpNum($lump);
        VVideo::drawPatch($fb, 160, 170, $patch, (bool) $flip);
    }

    /** @param array<int,int> $fb */
    private function castPrint(array &$fb, string $text): void
    {
        $width = 0;
        foreach (str_split($text) as $ch) {
            $code = ord(strtoupper($ch));
            if ($ch === ' ' || $code < Defs::HU_FONTSTART || $code > Defs::HU_FONTEND) {
                $width += 4;
                continue;
            }
            $p = $this->font($code);
            if ($p === null) {
                $width += 4;
                continue;
            }
            [$w] = VVideo::patchSize($p);
            $width += $w;
        }
        $cx = 160 - intdiv($width, 2);
        foreach (str_split($text) as $ch) {
            $code = ord(strtoupper($ch));
            if ($ch === ' ' || $code < Defs::HU_FONTSTART || $code > Defs::HU_FONTEND) {
                $cx += 4;
                continue;
            }
            $p = $this->font($code);
            if ($p === null) {
                $cx += 4;
                continue;
            }
            [$w] = VVideo::patchSize($p);
            VVideo::drawPatch($fb, $cx, 180, $p);
            $cx += $w;
        }
    }

    /** @param array<int,int> $fb */
    private function drawText(array &$fb): void
    {
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
            $p = $this->font($code);
            if ($p === null) {
                $x += 4;
                continue;
            }
            [$width] = VVideo::patchSize($p);
            if ($x + $width > Defs::SCREENWIDTH) {
                break;
            }
            VVideo::drawPatch($fb, $x, $y, $p);
            $x += $width;
        }
    }

    private function font(int $code): ?string
    {
        if ($code - Defs::HU_FONTSTART < 0 || $code - Defs::HU_FONTSTART >= Defs::HU_FONTSIZE) {
            return null;
        }
        return $this->lump(sprintf('STCFN%03d', $code));
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
        if ($this->game->mouseFire) {
            return true;
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
