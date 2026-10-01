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

final class CheatSeq
{
    public int $charsRead = 0;
    public string $paramBuf = '';

    public function __construct(
        public string $action,
        public string $sequence,
        public int $paramChars = 0,
        public ?string $dehName = null,
    ) {
    }

    public function feed(string $ch): ?string
    {
        $seq = $this->sequence;
        if ($seq === '') {
            return null;
        }
        if ($this->charsRead < strlen($seq)) {
            if ($ch === $seq[$this->charsRead]) {
                ++$this->charsRead;
            } else {
                $this->charsRead = $ch === $seq[0] ? 1 : 0;
            }
            if ($this->charsRead < strlen($seq)) {
                return null;
            }
            if ($this->paramChars <= 0) {
                $this->charsRead = 0;
                return '';
            }
            return null;
        }
        if (strlen($this->paramBuf) < $this->paramChars) {
            $this->paramBuf .= $ch;
        }
        if (strlen($this->paramBuf) >= $this->paramChars) {
            $buf = $this->paramBuf;
            $this->charsRead = 0;
            $this->paramBuf = '';
            return $buf;
        }
        return null;
    }
}

final class Deh
{
    public static ?self $instance = null;

    /** @var string[] */
    public array $files = [];
    public bool $nodeh = false;
    public bool $dehlump = false;
    public bool $applyCheats = true;
    public bool $allowLongStrings = false;
    public bool $allowLongCheats = false;
    public bool $allowExtendedStrings = false;
    /** @var array<string,string> */
    public array $replacements = [];
    /** @var CheatSeq[] */
    public array $cheats = [];
    public int $initialHealth = 100;
    public int $initialBullets = 50;
    public int $maxHealth = 200;
    public int $maxArmor = 200;
    public int $greenArmorClass = 1;
    public int $blueArmorClass = 2;
    public int $maxSoulsphere = 200;
    public int $soulsphereHealth = 100;
    public int $megasphereHealth = 200;
    public int $godModeHealth = 100;
    public int $idfaArmor = 200;
    public int $idfaArmorClass = 2;
    public int $idkfaArmor = 200;
    public int $idkfaArmorClass = 2;
    public int $bfgCellsPerShot = 40;
    public int $speciesInfighting = 0;
    /** @var int[] */
    public array $maxammo = [200, 50, 300, 50];
    /** @var int[] */
    public array $clipammo = [10, 4, 20, 1];

    private const SIGS = [
        'Patch File for DeHackEd v2.3',
        'Patch File for DeHackEd v3.0',
    ];
    private const THING_DOOMED = [
        2 => 3004, 3 => 9, 4 => 64, 6 => 66, 9 => 67, 12 => 3001, 13 => 3002,
        15 => 3005, 16 => 3003, 18 => 69, 19 => 3006, 20 => 7, 21 => 68, 22 => 16,
        23 => 71, 24 => 84, 25 => 72, 31 => 2035,
    ];
    private const BEX = [
        'STSTR_DQDON' => 'Degreelessness Mode On',
        'STSTR_DQDOFF' => 'Degreelessness Mode Off',
        'STSTR_KFAADDED' => 'Very Happy Ammo Added',
        'STSTR_FAADDED' => 'Ammo Added',
        'STSTR_NCON' => 'No Clipping Mode ON',
        'STSTR_NCOFF' => 'No Clipping Mode OFF',
        'STSTR_BEHOLD' => 'invin visis rad allmap lite amp',
        'STSTR_BEHOLDX' => 'Power-up Toggled',
        'STSTR_CHOPPERS' => "... doesn't suck - GM",
        'STSTR_CLEV' => 'Changing Level...',
        'STSTR_MUS' => 'Music Change',
        'STSTR_NOMUS' => 'IMPOSSIBLE SELECTION',
        'GOTSTIM' => 'Picked up a stimpack.',
        'GOTMEDIKIT' => 'Picked up a medikit.',
        'GOTHTHBONUS' => 'You pick up a health bonus.',
        'GOTARMBONUS' => 'You pick up an armor bonus.',
        'GOTARMOR' => 'Picked up the armor.',
        'GOTMEGA' => 'Picked up the MegaArmor!',
        'GOTSUPER' => 'Supercharge!',
        'GOTMSPHERE' => 'MegaSphere!',
        'GOTBERSERK' => 'Berserk!',
        'GOTINVUL' => 'Invulnerability!',
        'GOTINVIS' => 'Partial Invisibility',
        'GOTSUIT' => 'Radiation Shielding Suit',
        'GOTMAP' => 'Computer Area Map',
        'GOTVISOR' => 'Light Amplification Visor',
        'GGSAVED' => 'game saved.',
        'AMSTR_FOLLOWON' => 'Follow Mode ON',
        'AMSTR_FOLLOWOFF' => 'Follow Mode OFF',
        'AMSTR_GRIDON' => 'Grid ON',
        'AMSTR_GRIDOFF' => 'Grid OFF',
        'AMSTR_MARKSCLEARED' => 'All Marks Cleared',
        'MSGOFF' => 'Messages Off',
        'MSGON' => 'Messages On',
        'DETAILHI' => 'High detail',
        'DETAILLO' => 'Low detail',
        'GOTSHOTGUN' => 'You got the shotgun!',
        'GOTSHOTGUN2' => 'You got the super shotgun!',
        'GOTCHAINGUN' => 'You got the chaingun!',
        'GOTLAUNCHER' => 'You got the rocket launcher!',
        'GOTPLASMA' => 'You got the plasma gun!',
        'GOTBFG9000' => 'You got the BFG9000!',
        'GOTCHAINSAW' => 'A chainsaw!  Find some meat!',
    ];
    private const MISC = [
        'Initial Health' => 'initialHealth',
        'Initial Bullets' => 'initialBullets',
        'Max Health' => 'maxHealth',
        'Max Armor' => 'maxArmor',
        'Green Armor Class' => 'greenArmorClass',
        'Blue Armor Class' => 'blueArmorClass',
        'Max Soulsphere' => 'maxSoulsphere',
        'Soulsphere Health' => 'soulsphereHealth',
        'Megasphere Health' => 'megasphereHealth',
        'God Mode Health' => 'godModeHealth',
        'IDFA Armor' => 'idfaArmor',
        'IDFA Armor Class' => 'idfaArmorClass',
        'IDKFA Armor' => 'idkfaArmor',
        'IDKFA Armor Class' => 'idkfaArmorClass',
        'BFG Cells/Shot' => 'bfgCellsPerShot',
    ];

    public function __construct()
    {
        $this->cheats = self::makeCheats();
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    public static function string(string $text): string
    {
        return self::get()->replacements[$text] ?? $text;
    }

    /** @return int[] */
    public static function powerTics(): array
    {
        $t = Defs::TICRATE;
        return [30 * $t, 1, 60 * $t, 60 * $t, 1, 120 * $t];
    }

    /** @return CheatSeq[] */
    public static function makeCheats(): array
    {
        return [
            new CheatSeq('god', 'iddqd', 0, 'iddqd'),
            new CheatSeq('kfa', 'idkfa', 0, 'idkfa'),
            new CheatSeq('fa', 'idfa', 0, 'idfa'),
            new CheatSeq('noclip2', 'idclip', 0, 'idclip'),
            new CheatSeq('noclip', 'idspispopd', 0, 'idspispopd'),
            new CheatSeq('iddt', 'iddt'),
            new CheatSeq('beholdv', 'idbeholdv'),
            new CheatSeq('beholds', 'idbeholds'),
            new CheatSeq('beholdi', 'idbeholdi'),
            new CheatSeq('beholdr', 'idbeholdr'),
            new CheatSeq('beholda', 'idbeholda'),
            new CheatSeq('beholdl', 'idbeholdl'),
            new CheatSeq('behold', 'idbehold', 0, 'idbehold'),
            new CheatSeq('choppers', 'idchoppers', 0, 'idchoppers'),
            new CheatSeq('mypos', 'idmypos', 0, 'idmypos'),
            new CheatSeq('clev', 'idclev', 2, 'idclev'),
            new CheatSeq('mus', 'idmus', 2, 'idmus'),
        ];
    }

    public function loadAfterIwad(Wad $wad, string $iwadPath): void
    {
        if (!$this->nodeh) {
            foreach ($wad->lumps as $i => $lump) {
                if ($lump->name === 'DEHACKED') {
                    $this->loadLump($wad, $i);
                }
            }
        }
        foreach ($this->files as $path) {
            if (is_file($path)) {
                $this->loadFile($path);
            } else {
                fwrite(STDOUT, "DEH_LoadFile: Unable to open {$path}\n");
            }
        }
    }

    public function loadFile(string $path): void
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            fwrite(STDOUT, "DEH_LoadFile: Unable to open {$path}\n");
            return;
        }
        fwrite(STDOUT, " loading {$path}\n");
        $this->parse($raw, $path);
    }

    public function loadLump(Wad $wad, int $lumpnum): void
    {
        $name = $wad->lumpName($lumpnum);
        fwrite(STDOUT, " loading lump {$name}\n");
        $this->parse($wad->cacheLumpNum($lumpnum), $name);
    }

    private function parse(string $data, string $name): void
    {
        $this->allowLongStrings = $this->allowLongCheats = $this->allowExtendedStrings = false;
        $ctx = ['data' => $data, 'pos' => 0, 'line' => 1, 'name' => $name];
        $first = $this->readLine($ctx, false);
        if ($first === null || !in_array(trim($first), self::SIGS, true)) {
            fwrite(STDOUT, "{$name}: This is not a valid dehacked patch file!\n");
            return;
        }
        $section = null;
        $tag = null;
        while (true) {
            $line = $this->readLine($ctx, $section === '[STRINGS]');
            if ($line === null) {
                return;
            }
            $stripped = ltrim($line, " \t");
            if (str_starts_with($stripped, '#')) {
                $this->comment($stripped);
                continue;
            }
            if (trim($stripped) === '') {
                $section = $tag = null;
                continue;
            }
            if ($section !== null) {
                $this->parseLine($ctx, $section, $stripped, $tag);
            } else {
                $word = preg_split('/\s+/', $stripped, 2)[0];
                if (strcasecmp($word, '[STRINGS]') === 0 && !$this->allowExtendedStrings) {
                    $section = null;
                    continue;
                }
                $section = $word;
                $tag = $this->startSection($ctx, $word, $stripped);
            }
        }
    }

    /** @param array{data:string,pos:int,line:int,name:string} $ctx */
    private function getChar(array &$ctx): int
    {
        if ($ctx['pos'] >= strlen($ctx['data'])) {
            return -1;
        }
        $ch = $ctx['data'][$ctx['pos']];
        $ctx['pos']++;
        if ($ch === "\n") {
            ++$ctx['line'];
        }
        return ord($ch);
    }

    /** @param array{data:string,pos:int,line:int,name:string} $ctx */
    private function readLine(array &$ctx, bool $extended): ?string
    {
        if ($ctx['pos'] >= strlen($ctx['data'])) {
            return null;
        }
        $parts = '';
        while (true) {
            $buf = '';
            while ($ctx['pos'] < strlen($ctx['data'])) {
                $ch = $ctx['data'][$ctx['pos']];
                $ctx['pos']++;
                if ($ch === "\n") {
                    ++$ctx['line'];
                    break;
                }
                if ($ch !== "\r") {
                    $buf .= $ch;
                }
            }
            if ($extended && str_ends_with($buf, '\\')) {
                $parts .= substr($buf, 0, -1) . "\n";
                if ($ctx['pos'] >= strlen($ctx['data'])) {
                    break;
                }
                continue;
            }
            $parts .= $buf;
            break;
        }
        return $parts;
    }

    private function comment(string $comment): void
    {
        if (str_contains($comment, '*allow-long-strings*')) {
            $this->allowLongStrings = true;
        }
        if (str_contains($comment, '*allow-long-cheats*')) {
            $this->allowLongCheats = true;
        }
        if (str_contains($comment, '*allow-extended-strings*')) {
            $this->allowExtendedStrings = true;
        }
    }

    /** @param array{data:string,pos:int,line:int,name:string} $ctx */
    private function startSection(array &$ctx, string $word, string $line): mixed
    {
        $key = strtolower($word);
        if ($key === 'thing' || $key === 'ammo') {
            $tok = preg_split('/\s+/', trim($line));
            return isset($tok[1]) ? (int) $tok[1] : null;
        }
        if ($key === 'text') {
            $this->parseText($ctx, $line);
        }
        return null;
    }

    /** @param array{data:string,pos:int,line:int,name:string} $ctx */
    private function parseLine(array &$ctx, string $section, string $line, mixed $tag): void
    {
        $key = strtolower($section);
        if ($key === 'misc') {
            $this->parseMisc($line);
        } elseif ($key === 'thing') {
            $this->parseThing($line, $tag);
        } elseif ($key === 'ammo') {
            $this->parseAmmo($line, $tag);
        } elseif ($key === 'cheat') {
            $this->parseCheat($line);
        } elseif ($key === '[strings]') {
            $this->parseBex($line);
        }
    }

    /** @return array{0:string,1:string}|null */
    private function assignment(string $line): ?array
    {
        $eq = strpos($line, '=');
        if ($eq === false) {
            return null;
        }
        return [trim(substr($line, 0, $eq)), trim(substr($line, $eq + 1))];
    }

    private function parseMisc(string $line): void
    {
        $asg = $this->assignment($line);
        if ($asg === null) {
            return;
        }
        $value = (int) $asg[1];
        if (strcasecmp($asg[0], 'Monsters Infight') === 0) {
            $this->speciesInfighting = $value === 221 ? 1 : 0;
            return;
        }
        foreach (self::MISC as $label => $field) {
            if (strcasecmp($label, $asg[0]) === 0) {
                $this->{$field} = $value;
                return;
            }
        }
    }

    private function parseThing(string $line, mixed $tag): void
    {
        $asg = $this->assignment($line);
        if ($asg === null || !is_int($tag) || strcasecmp($asg[0], 'Hit points') !== 0) {
            return;
        }
        $doomed = self::THING_DOOMED[$tag] ?? null;
        if ($doomed === null) {
            return;
        }
        $table = Mobj::infoTable();
        if (!isset($table[$doomed])) {
            return;
        }
        Mobj::patchHitPoints($doomed, (int) $asg[1]);
    }

    private function parseAmmo(string $line, mixed $tag): void
    {
        $asg = $this->assignment($line);
        if ($asg === null || !is_int($tag) || $tag < 0 || $tag > 3) {
            return;
        }
        $value = (int) $asg[1];
        if (strcasecmp($asg[0], 'Max ammo') === 0) {
            $this->maxammo[$tag] = $value;
        } elseif (strcasecmp($asg[0], 'Per ammo') === 0) {
            $this->clipammo[$tag] = $value;
        }
    }

    private function parseCheat(string $line): void
    {
        $asg = $this->assignment($line);
        if ($asg === null || !$this->applyCheats) {
            return;
        }
        $cheat = null;
        foreach ($this->cheats as $item) {
            if ($item->dehName === strtolower($asg[0])) {
                $cheat = $item;
                break;
            }
        }
        if ($cheat === null) {
            return;
        }
        $seq = '';
        $len = strlen($asg[1]);
        for ($i = 0; $i < $len; ++$i) {
            $code = ord($asg[1][$i]);
            if ($code === 0 || $code === 0xFF) {
                break;
            }
            if (!$this->allowLongCheats && ($i + 1) > strlen($cheat->sequence)) {
                break;
            }
            $seq .= $asg[1][$i];
        }
        $cheat->sequence = $seq;
        $cheat->charsRead = 0;
        $cheat->paramBuf = '';
    }

    private function parseBex(string $line): void
    {
        $asg = $this->assignment($line);
        if ($asg === null) {
            return;
        }
        $original = self::BEX[strtoupper($asg[0])] ?? null;
        if ($original === null) {
            return;
        }
        $this->replacements[$original] = str_replace('\\n', "\n", $asg[1]);
    }

    /** @param array{data:string,pos:int,line:int,name:string} $ctx */
    private function parseText(array &$ctx, string $line): void
    {
        $parts = preg_split('/\s+/', trim($line));
        if ($parts === false || count($parts) < 3) {
            return;
        }
        $nFrom = (int) $parts[1];
        $nTo = (int) $parts[2];
        $src = $dst = '';
        for ($i = 0; $i < $nFrom; ++$i) {
            $code = $this->getChar($ctx);
            if ($code < 0) {
                break;
            }
            $src .= chr($code);
        }
        for ($i = 0; $i < $nTo; ++$i) {
            $code = $this->getChar($ctx);
            if ($code < 0) {
                break;
            }
            $dst .= chr($code);
        }
        $this->replacements[$src] = $dst;
    }
}
