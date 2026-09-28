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

use RuntimeException;

/** WAD loader (w_wad / w_file_stdc). Lump names are 8-byte, case-insensitive. */
final class Lump
{
    public function __construct(
        public string $name,
        public int $position,
        public int $size,
        public ?string $cache = null,
        public string $wadPath = '',
    ) {
    }
}

final class Wad
{
    /** @var array<int, Lump> */
    public array $lumps = [];
    /** @var array<string, int> */
    private array $index = [];

    public function addFile(string $path): void
    {
        $absolute = realpath($path);
        if ($absolute === false) {
            throw new RuntimeException("WAD not found: {$path}");
        }

        $file = fopen($absolute, 'rb');
        if ($file === false) {
            throw new RuntimeException("cannot open WAD: {$absolute}");
        }
        try {
            $header = fread($file, 12);
            if ($header === false || strlen($header) !== 12) {
                throw new RuntimeException("invalid WAD header: {$absolute}");
            }
            $ident = substr($header, 0, 4);
            if ($ident !== 'IWAD' && $ident !== 'PWAD') {
                throw new RuntimeException("not a WAD: {$absolute}");
            }
            $numLumps = Bin::u32($header, 4);
            $infoTableOfs = Bin::u32($header, 8);
            if (fseek($file, $infoTableOfs) !== 0) {
                throw new RuntimeException("invalid WAD directory offset: {$absolute}");
            }
            $directoryLength = $numLumps * 16;
            $directory = $directoryLength === 0 ? '' : fread($file, $directoryLength);
            if ($directory === false || strlen($directory) !== $directoryLength) {
                throw new RuntimeException("truncated WAD directory: {$absolute}");
            }
        } finally {
            fclose($file);
        }

        $start = count($this->lumps);
        for ($i = 0; $i < $numLumps; ++$i) {
            $off = $i * 16;
            $this->lumps[] = new Lump(
                Bin::name8($directory, $off + 8),
                Bin::u32($directory, $off),
                Bin::u32($directory, $off + 4),
                null,
                $absolute,
            );
        }
        for ($i = $start, $count = count($this->lumps); $i < $count; ++$i) {
            $this->index[$this->lumps[$i]->name] = $i;
        }
    }

    public function numLumps(): int
    {
        return count($this->lumps);
    }

    public function checkNumForName(string $name): int
    {
        $nul = strpos($name, "\0");
        if ($nul !== false) {
            $name = substr($name, 0, $nul);
        }
        $key = substr(rtrim(strtoupper($name), ' '), 0, 8);
        return $this->index[$key] ?? -1;
    }

    public function getNumForName(string $name): int
    {
        $number = $this->checkNumForName($name);
        if ($number < 0) {
            throw new RuntimeException("lump not found: {$name}");
        }
        return $number;
    }

    public function lumpLength(int $num): int
    {
        return $this->lumps[$num]->size;
    }

    public function cacheLumpNum(int $num): string
    {
        $lump = $this->lumps[$num];
        if ($lump->cache === null) {
            $file = fopen($lump->wadPath, 'rb');
            if ($file === false) {
                throw new RuntimeException("cannot open WAD: {$lump->wadPath}");
            }
            try {
                if (fseek($file, $lump->position) !== 0) {
                    throw new RuntimeException("invalid lump offset: {$lump->name}");
                }
                $data = $lump->size === 0 ? '' : fread($file, $lump->size);
                if ($data === false || strlen($data) !== $lump->size) {
                    throw new RuntimeException("truncated lump: {$lump->name}");
                }
                $lump->cache = $data;
            } finally {
                fclose($file);
            }
        }
        return $lump->cache;
    }

    public function cacheLumpName(string $name): string
    {
        return $this->cacheLumpNum($this->getNumForName($name));
    }

    public function lumpName(int $num): string
    {
        return $this->lumps[$num]->name;
    }
}
