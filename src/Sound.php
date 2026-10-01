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

final class Sound
{
    private const DOOM2_MUSIC = [
        'runnin', 'stalks', 'countd', 'betwee', 'doom', 'the_da', 'shawn', 'ddtblu',
        'in_cit', 'dead', 'stlks2', 'theda2', 'doom2', 'ddtbl2', 'runni2', 'dead2',
        'stlks3', 'romero', 'shawn2', 'messag', 'count2', 'ddtbl3', 'ampie', 'theda3',
        'adrian', 'messg2', 'romer2', 'tense', 'shawn3', 'openin', 'evil', 'ultima',
    ];

    private ?object $wad = null;
    /** @var array<string,?string> 16-bit little-endian mono PCM at 11025 Hz */
    private array $cache = [];
    private ?string $musicPath = null;
    private string $musicName = '';
    private bool $musicLoop = false;
    private bool $mciPlaying = false;
    private mixed $winmm = null;
    public ?Video $output = null;
    public bool $enabled = true;
    public bool $musicEnabled = true;
    public int $sfxVolume = 8;
    public int $musicVolume = 8;

    public function init(object $wad): void
    {
        $this->wad = $wad;
        if (PHP_OS_FAMILY === 'Windows' && extension_loaded('FFI')) {
            try {
                $this->winmm = \FFI::cdef(
                    'unsigned long mciSendStringA(const char*, char*, unsigned int, void*);',
                    'winmm.dll'
                );
            } catch (Throwable) {
                $this->winmm = null;
            }
        }
    }

    /**
     * Warms the PCM cache. Video/SDL can fetch the bytes with getPcm() and
     * submit them to its mixer without coupling this module to SDL.
     */
    public function play(string $name): void
    {
        if (!$this->enabled) {
            return;
        }
        $pcm = $this->getPcm($name);
        if ($pcm !== null && $this->output !== null) {
            $this->output->playSfx($pcm, $this->sfxVolume);
        }
    }

    public function getPcm(string $name): ?string
    {
        $key = strtolower($name);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        if ($this->wad === null) {
            return $this->cache[$key] = null;
        }
        $lump = 'DS' . strtoupper(substr($key, 0, 6));
        $number = $this->wad->checkNumForName($lump);
        if ($number < 0) {
            return $this->cache[$key] = null;
        }
        return $this->cache[$key] = self::decodeDs($this->wad->cacheLumpNum($number));
    }

    public function playTitleMusic(): void
    {
        if ($this->wad === null) {
            return;
        }
        if ($this->wad->checkNumForName('MAP01') >= 0) {
            $this->changeMusic('dm2ttl', false);
        } elseif ($this->wad->checkNumForName('D_INTROA') >= 0) {
            $this->changeMusic('introa', false);
        } else {
            $this->changeMusic('intro', false);
        }
    }

    public function playLevelMusic(int $episode, int $map): void
    {
        if ($this->wad === null) {
            return;
        }
        $name = $this->wad->checkNumForName('MAP01') >= 0
            ? self::DOOM2_MUSIC[(max(1, $map) - 1) % count(self::DOOM2_MUSIC)]
            : "e{$episode}m{$map}";
        $this->changeMusic($name, true);
    }

    /** @return string[] */
    public static function doom2Music(): array
    {
        return self::DOOM2_MUSIC;
    }

    public function hasMusic(string $name): bool
    {
        if ($this->wad === null || $name === '') {
            return false;
        }
        return $this->wad->checkNumForName('D_' . strtoupper(substr($name, 0, 6))) >= 0;
    }

    public function changeMusic(string $name, bool $looping = true): void
    {
        if (!$this->musicEnabled || $this->wad === null || $name === '' || strtolower($name) === $this->musicName) {
            return;
        }
        $number = $this->wad->checkNumForName('D_' . strtoupper(substr($name, 0, 6)));
        if ($number < 0) {
            return;
        }
        class_exists(Mus2Mid::class);
        $midi = mus2mid($this->wad->cacheLumpNum($number));
        if ($midi === null || $midi === '') {
            return;
        }
        $this->stopMusic();
        $path = tempnam(sys_get_temp_dir(), 'doom_');
        if ($path === false) {
            return;
        }
        $midPath = $path . '.mid';
        @unlink($path);
        if (file_put_contents($midPath, $midi) === false) {
            @unlink($midPath);
            return;
        }
        $this->musicPath = $midPath;
        $this->musicName = strtolower($name);
        $this->musicLoop = $looping;
        $quoted = str_replace('/', '\\', $midPath);
        $this->mci('close doommus');
        $opened = $this->mci('open "' . $quoted . '" type sequencer alias doommus') === 0
            || $this->mci('open "' . $quoted . '" alias doommus') === 0;
        $this->mciPlaying = $opened && $this->mci('play doommus from 0') === 0;
        if ($this->mciPlaying) {
            $this->setMusicVolume($this->musicVolume);
        }
    }

    public function setSfxVolume(int $volume): void
    {
        $this->sfxVolume = max(0, min(15, $volume));
    }

    public function setMusicVolume(int $volume): void
    {
        $this->musicVolume = max(0, min(15, $volume));
        if ($this->mciPlaying) {
            $this->mci('setaudio doommus volume to ' . intdiv($this->musicVolume * 1000, 15));
        }
    }

    public function stopMusic(): void
    {
        if ($this->mciPlaying) {
            $this->mci('close doommus');
        }
        $this->mciPlaying = false;
        $this->musicName = '';
        if ($this->musicPath !== null) {
            @unlink($this->musicPath);
            $this->musicPath = null;
        }
    }

    public function update(): void
    {
        if (!$this->mciPlaying || !$this->musicLoop || $this->musicPath === null) {
            return;
        }
        $mode = strtolower($this->mciStatus('status doommus mode'));
        if ($mode !== '' && $mode !== 'playing') {
            $this->mci('play doommus from 0');
        }
    }

    private function mci(string $command): int
    {
        if ($this->winmm === null) {
            return 1;
        }
        try {
            $call = 'mciSendStringA';
            return (int) $this->winmm->{$call}($command, null, 0, null);
        } catch (Throwable) {
            return 1;
        }
    }

    private function mciStatus(string $command): string
    {
        if ($this->winmm === null) {
            return '';
        }
        try {
            $buffer = $this->winmm->new('char[64]');
            $call = 'mciSendStringA';
            if ((int) $this->winmm->{$call}($command, $buffer, 64, null) !== 0) {
                return '';
            }
            return \FFI::string($buffer);
        } catch (Throwable) {
            return '';
        }
    }

    private static function decodeDs(string $data): ?string
    {
        $size = strlen($data);
        if ($size < 8 || ord($data[0]) !== 3 || ord($data[1]) !== 0) {
            return null;
        }
        $rate = ord($data[2]) | (ord($data[3]) << 8);
        $length = unpack('V', substr($data, 4, 4))[1];
        if ($rate <= 0 || $length > $size - 8 || $length <= 48) {
            return null;
        }
        // Vanilla sound lumps have 16 pad samples at both ends.
        $samples = substr($data, 16, $length - 32);
        if ($samples === '') {
            return null;
        }
        $sourceLength = strlen($samples);
        $count = $rate === 11025 ? $sourceLength : max(1, intdiv($sourceLength * 11025, $rate));
        $pcm = '';
        for ($i = 0; $i < $count; ++$i) {
            $source = min($sourceLength - 1, intdiv($i * $sourceLength, $count));
            $sample = (ord($samples[$source]) - 128) << 8;
            $pcm .= pack('v', $sample & 0xFFFF);
        }
        return $pcm;
    }
}
