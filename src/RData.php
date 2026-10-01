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

/** Textures, flats, colormaps (r_data.c). */
final class TexPatch
{
    public function __construct(
        public int $originx,
        public int $originy,
        public int $patch,
    ) {
    }
}

final class Texture
{
    /** @var array<int, TexPatch> */
    public array $patches = [];
    public int $widthmask = 0;
    /** @var array<int, int> */
    public array $columnofs = [];
    /** @var array<int, int>|null */
    public ?array $composite = null;
    /** @var array<int, int> */
    public array $colLump = [];
    /** @var array<int, int> */
    public array $colOfs = [];

    public function __construct(
        public string $name,
        public int $width,
        public int $height,
    ) {
    }
}

final class Resources
{
    /** @var array<int, Texture> */
    public array $textures = [];
    /** @var array<string, int> */
    public array $texIndex = [];
    public int $flatsFirst = 0;
    public int $flatsLast = 0;
    /** @var array<int, int> */
    public array $flattranslation = [];
    /** @var array<int, int> */
    public array $texturetranslation = [];
    public string $colormaps = '';
    public int $skytexture = 0;
    public int $skyflatnum = 0;
    /** @var array<int, string> */
    private array $patchCache = [];
    /** @var array<mixed> */
    public array $sprites = [];

    public function __construct(public Wad $wad)
    {
    }

    public function init(): void
    {
        $this->initTextures();
        $this->initFlats();
        $this->colormaps = $this->wad->cacheLumpName('COLORMAP');
        $this->skyflatnum = $this->flatNumForName('F_SKY1');
        $this->skytexture = $this->textureNumForName('SKY1');

        $spritesClass = __NAMESPACE__ . '\\Sprites';
        if (class_exists($spritesClass) && is_callable([$spritesClass, 'initSpriteDefs'])) {
            /** @var array<mixed> $sprites */
            $sprites = $spritesClass::initSpriteDefs($this->wad);
            $this->sprites = $sprites;
        } else {
            // TODO: call Sprites::initSpriteDefs($this->wad) when Sprites is ported.
        }
    }

    public function colormap(int $level): string
    {
        $level = max(0, min(32, $level));
        return substr($this->colormaps, $level * 256, 256);
    }

    private function initFlats(): void
    {
        $this->flatsFirst = $this->wad->getNumForName('F_START') + 1;
        $this->flatsLast = $this->wad->getNumForName('F_END') - 1;
        $count = $this->flatsLast - $this->flatsFirst + 1;
        $this->flattranslation = $count > 0 ? range(0, $count - 1) : [];
    }

    public function flatNumForName(string $name): int
    {
        $index = $this->wad->checkNumForName($name);
        return $index < 0 ? 0 : $index - $this->flatsFirst;
    }

    public function flatLump(int $flatnum): int
    {
        if ($flatnum < 0) {
            $flatnum = 0;
        }
        $count = $this->flatsLast - $this->flatsFirst + 1;
        if ($flatnum >= $count) {
            $flatnum = 0;
        }
        return $this->flatsFirst + $this->flattranslation[$flatnum];
    }

    private function initTextures(): void
    {
        $pnames = $this->wad->cacheLumpName('PNAMES');
        $numMapPatches = Bin::i32($pnames, 0);
        $patchLookup = [];
        for ($i = 0; $i < $numMapPatches; ++$i) {
            $patchLookup[] = $this->wad->checkNumForName(Bin::name8($pnames, 4 + $i * 8));
        }

        $maptex1 = $this->wad->cacheLumpName('TEXTURE1');
        $numTextures1 = Bin::i32($maptex1, 0);
        $maptex2 = '';
        $numTextures2 = 0;
        if ($this->wad->checkNumForName('TEXTURE2') >= 0) {
            $maptex2 = $this->wad->cacheLumpName('TEXTURE2');
            $numTextures2 = Bin::i32($maptex2, 0);
        }

        for ($i = 0; $i < $numTextures1 + $numTextures2; ++$i) {
            if ($i < $numTextures1) {
                $offset = Bin::i32($maptex1, 4 + $i * 4);
                $src = $maptex1;
            } else {
                $offset = Bin::i32($maptex2, 4 + ($i - $numTextures1) * 4);
                $src = $maptex2;
            }
            $name = Bin::name8($src, $offset);
            $width = Bin::i16($src, $offset + 12);
            $height = Bin::i16($src, $offset + 14);
            $patchCount = Bin::i16($src, $offset + 20);
            $texture = new Texture($name, $width, $height);
            $patchOffset = $offset + 22;
            for ($p = 0; $p < $patchCount; ++$p) {
                $originx = Bin::i16($src, $patchOffset);
                $originy = Bin::i16($src, $patchOffset + 2);
                $patchIndex = Bin::i16($src, $patchOffset + 4);
                $patchOffset += 10;
                $lump = ($patchIndex >= 0 && $patchIndex < count($patchLookup))
                    ? $patchLookup[$patchIndex]
                    : -1;
                $texture->patches[] = new TexPatch($originx, $originy, $lump);
            }
            $maskWidth = 1;
            while ($maskWidth * 2 <= $width) {
                $maskWidth *= 2;
            }
            $texture->widthmask = $maskWidth - 1;
            $texture->colLump = array_fill(0, $width, -1);
            $texture->colOfs = array_fill(0, $width, 0);
            $this->texIndex[$name] = count($this->textures);
            $this->textures[] = $texture;
        }

        $count = count($this->textures);
        $this->texturetranslation = $count > 0 ? range(0, $count - 1) : [];
        foreach ($this->textures as $texture) {
            $this->generateLookup($texture);
        }
    }

    private function generateLookup(Texture $texture): void
    {
        $patchCount = array_fill(0, $texture->width, 0);
        foreach ($texture->patches as $mapPatch) {
            if ($mapPatch->patch < 0) {
                continue;
            }
            $patchData = $this->wad->cacheLumpNum($mapPatch->patch);
            $patchWidth = Bin::i16($patchData, 0);
            $x1 = $mapPatch->originx;
            $x2 = min($x1 + $patchWidth, $texture->width);
            $x = max($x1, 0);
            while ($x < $x2) {
                ++$patchCount[$x];
                $texture->colLump[$x] = $mapPatch->patch;
                $texture->colOfs[$x] = Bin::u32(
                    $patchData,
                    8 + ($x - $mapPatch->originx) * 4
                );
                ++$x;
            }
        }
        for ($x = 0; $x < $texture->width; ++$x) {
            if ($patchCount[$x] > 1) {
                $texture->colLump[$x] = -1;
            }
        }
    }

    private function generateComposite(Texture $texture): void
    {
        if ($texture->composite !== null) {
            return;
        }
        $buffer = array_fill(0, $texture->width * $texture->height, 0);
        foreach ($texture->patches as $mapPatch) {
            if ($mapPatch->patch < 0) {
                continue;
            }
            $patchData = $this->wad->cacheLumpNum($mapPatch->patch);
            $patchWidth = Bin::i16($patchData, 0);
            $x1 = $mapPatch->originx;
            $x2 = min($x1 + $patchWidth, $texture->width);
            $x = max($x1, 0);
            while ($x < $x2) {
                $columnOffset = Bin::u32(
                    $patchData,
                    8 + ($x - $mapPatch->originx) * 4
                );
                $this->drawColumnInCache(
                    $patchData,
                    $columnOffset,
                    $buffer,
                    $x,
                    $mapPatch->originy,
                    $texture
                );
                ++$x;
            }
        }
        $texture->composite = $buffer;
        for ($x = 0; $x < $texture->width; ++$x) {
            if ($texture->colLump[$x] < 0) {
                $texture->colOfs[$x] = $x * $texture->height;
            }
        }
    }

    /**
     * @param array<int, int> $cache
     */
    private function drawColumnInCache(
        string $patch,
        int $column,
        array &$cache,
        int $x,
        int $originy,
        Texture $texture,
    ): void {
        $patchLength = strlen($patch);
        while ($column < $patchLength) {
            $topDelta = ord($patch[$column]);
            if ($topDelta === 0xFF) {
                break;
            }
            $length = ord($patch[$column + 1]);
            $source = $column + 3;
            $position = $originy + $topDelta;
            $count = $length;
            if ($position < 0) {
                $count += $position;
                $source -= $position;
                $position = 0;
            }
            if ($position + $count > $texture->height) {
                $count = $texture->height - $position;
            }
            $dest = $x * $texture->height + $position;
            for ($i = 0; $i < $count; ++$i) {
                $cache[$dest + $i] = ord($patch[$source + $i]);
            }
            $column += $length + 4;
        }
    }

    public function textureNumForName(string $name): int
    {
        $key = substr(rtrim(strtoupper($name)), 0, 8);
        if ($key === '-' || $key === '') {
            return 0;
        }
        return $this->texIndex[$key] ?? 0;
    }

    public function textureHeight(int $texnum): int
    {
        return $this->textures[$texnum]->height * Defs::FRACUNIT;
    }

    public function textureWidth(int $texnum): int
    {
        return $this->textures[$texnum]->width;
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    public function columnPosts(int $texnum, int $col): array
    {
        if ($texnum <= 0 || $texnum >= count($this->textures)) {
            return [];
        }
        $texture = $this->textures[$texnum];
        $col &= $texture->widthmask;
        $lump = $texture->colLump[$col];
        $posts = [];
        if ($lump >= 0) {
            $patch = $this->wad->cacheLumpNum($lump);
            $column = $texture->colOfs[$col];
            $patchLength = strlen($patch);
            while ($column < $patchLength) {
                $topDelta = ord($patch[$column]);
                if ($topDelta === 0xFF) {
                    break;
                }
                $length = ord($patch[$column + 1]);
                $posts[] = [$topDelta, substr($patch, $column + 3, $length)];
                $column += $length + 4;
            }
            return $posts;
        }
        $this->generateComposite($texture);
        $offset = $texture->colOfs[$col];
        $columnBytes = self::bytesFromArray($texture->composite ?? [], $offset, $texture->height);
        if ($columnBytes !== '') {
            $posts[] = [0, $columnBytes];
        }
        return $posts;
    }

    public function getColumn(int $texnum, int $col): string
    {
        $texture = $this->textures[$texnum];
        $col &= $texture->widthmask;
        $lump = $texture->colLump[$col];
        if ($lump >= 0) {
            return $this->columnToSource(
                $this->wad->cacheLumpNum($lump),
                $texture->colOfs[$col],
                $texture->height
            );
        }
        $this->generateComposite($texture);
        $columnBytes = self::bytesFromArray(
            $texture->composite ?? [],
            $texture->colOfs[$col],
            $texture->height
        );
        return $this->repeatColumn($columnBytes);
    }

    private function columnToSource(string $patch, int $column, int $height): string
    {
        $buffer = array_fill(0, 128, 0);
        $patchLength = strlen($patch);
        while ($column < $patchLength) {
            $topDelta = ord($patch[$column]);
            if ($topDelta === 0xFF) {
                break;
            }
            $length = ord($patch[$column + 1]);
            $source = $column + 3;
            for ($i = 0; $i < $length; ++$i) {
                $y = $topDelta + $i;
                if ($y >= 0 && $y < 128) {
                    $buffer[$y] = ord($patch[$source + $i]);
                }
            }
            $column += $length + 4;
        }
        return self::bytesFromArray($buffer);
    }

    private function repeatColumn(string $columnBytes): string
    {
        if ($columnBytes === '') {
            return str_repeat("\0", 128);
        }
        $length = strlen($columnBytes);
        $buffer = '';
        for ($i = 0; $i < 128; ++$i) {
            $buffer .= $columnBytes[$i % $length];
        }
        return $buffer;
    }

    public function flatPixels(int $flatnum): string
    {
        $data = $this->wad->cacheLumpNum($this->flatLump($flatnum));
        if (strlen($data) >= 4096) {
            return substr($data, 0, 4096);
        }
        return $data . str_repeat("\0", 4096 - strlen($data));
    }

    /**
     * @param array<int, int> $bytes
     */
    private static function bytesFromArray(array $bytes, int $offset = 0, ?int $length = null): string
    {
        $slice = array_slice($bytes, $offset, $length);
        if ($slice === []) {
            return '';
        }
        return pack('C*', ...$slice);
    }
}
