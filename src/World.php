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

final class Vertex
{
    public function __construct(public int $x = 0, public int $y = 0) {}
}

final class Sector
{
    public int $floorheight = 0;
    public int $ceilingheight = 0;
    public int $floorpic = 0;
    public int $ceilingpic = 0;
    public int $lightlevel = 0;
    public int $special = 0;
    public int $tag = 0;
    /** @var Line[] */
    public array $lines = [];
    public ?object $specialdata = null;
    public ?object $soundorg = null;
    public int $iSector = 0;
    public int $validcount = 0;
    public int $soundtraversed = 0;
    public ?object $soundtarget = null;
}

final class Side
{
    public int $textureoffset = 0;
    public int $rowoffset = 0;
    public int $toptexture = 0;
    public int $bottomtexture = 0;
    public int $midtexture = 0;
    public ?Sector $sector = null;
}

final class Line
{
    public ?Vertex $v1 = null;
    public ?Vertex $v2 = null;
    public int $dx = 0;
    public int $dy = 0;
    public int $flags = 0;
    public int $special = 0;
    public int $tag = 0;
    /** @var int[] */
    public array $sidenum = [-1, -1];
    /** @var int[] */
    public array $bbox = [0, 0, 0, 0];
    public int $slopetype = 0;
    public ?Sector $frontsector = null;
    public ?Sector $backsector = null;
    /** @var array{0:?Side,1:?Side} */
    public array $sides = [null, null];
    public int $iLine = 0;
    public int $validcount = 0;
}

final class Seg
{
    public ?Vertex $v1 = null;
    public ?Vertex $v2 = null;
    public int $offset = 0;
    public int $angle = 0;
    public ?Side $sidedef = null;
    public ?Line $linedef = null;
    public ?Sector $frontsector = null;
    public ?Sector $backsector = null;
}

final class Subsector
{
    public function __construct(
        public int $numlines = 0,
        public int $firstline = 0,
        public ?Sector $sector = null,
    ) {}
}

final class Node
{
    public int $x = 0;
    public int $y = 0;
    public int $dx = 0;
    public int $dy = 0;
    /** @var array<int,array<int,int>> */
    public array $bbox = [[0, 0, 0, 0], [0, 0, 0, 0]];
    /** @var int[] */
    public array $children = [0, 0];
}

final class MapThing
{
    public function __construct(
        public int $x = 0,
        public int $y = 0,
        public int $angle = 0,
        public int $type = 0,
        public int $options = 0,
    ) {}
}

final class World
{
    /** @var Vertex[] */ public array $vertexes = [];
    /** @var Sector[] */ public array $sectors = [];
    /** @var Side[] */ public array $sides = [];
    /** @var Line[] */ public array $lines = [];
    /** @var Seg[] */ public array $segs = [];
    /** @var Subsector[] */ public array $subsectors = [];
    /** @var Node[] */ public array $nodes = [];
    /** @var MapThing[] */ public array $things = [];
    public int $numnodes = 0;
    /** @var int[] */ public array $blockmap = [];
    public int $bmaporgx = 0;
    public int $bmaporgy = 0;
    public int $bmapwidth = 0;
    public int $bmapheight = 0;
    public string $blockmaplump = '';
    public int $validcount = 0;
    /** @var Mobj[] */ public array $mobjs = [];
    public string $rejectmatrix = '';

    public function setupLevel(object $wad, object $res, int $episode, int $mapn): void
    {
        $mapName = sprintf('MAP%02d', $mapn);
        $lumpName = $wad->checkNumForName($mapName) >= 0 ? $mapName : "E{$episode}M{$mapn}";
        $lump = $wad->getNumForName($lumpName);
        $this->loadVertexes($wad->cacheLumpNum($lump + 4));
        $this->loadSectors($wad->cacheLumpNum($lump + 8), $res);
        $this->loadSides($wad->cacheLumpNum($lump + 3), $res);
        $this->loadLines($wad->cacheLumpNum($lump + 2));
        $this->loadSegs($wad->cacheLumpNum($lump + 5));
        $this->loadSubsectors($wad->cacheLumpNum($lump + 6));
        $this->loadNodes($wad->cacheLumpNum($lump + 7));
        $this->loadThings($wad->cacheLumpNum($lump + 1));
        $this->loadBlockmap($wad->cacheLumpNum($lump + 10));
        $this->rejectmatrix = $wad->cacheLumpNum($lump + 9) ?: '';
        $this->numnodes = count($this->nodes);
        foreach ($this->subsectors as $ss) {
            $ss->sector = $this->segs[$ss->firstline]->frontsector;
        }
    }

    private static function name8(string $data, int $offset): string
    {
        $name = substr($data, $offset, 8);
        $zero = strpos($name, "\0");
        if ($zero !== false) {
            $name = substr($name, 0, $zero);
        }
        return strtoupper(rtrim($name, ' '));
    }

    private function loadVertexes(string $data): void
    {
        $this->vertexes = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPVERTEX_SIZE <= $n; $o += Defs::MAPVERTEX_SIZE) {
            $this->vertexes[] = new Vertex(
                Bin::i16($data, $o) * Defs::FRACUNIT,
                Bin::i16($data, $o + 2) * Defs::FRACUNIT,
            );
        }
    }

    private function loadSectors(string $data, object $res): void
    {
        $this->sectors = [];
        for ($o = 0, $i = 0, $n = strlen($data); $o + Defs::MAPSECTOR_SIZE <= $n; $o += Defs::MAPSECTOR_SIZE, ++$i) {
            $s = new Sector();
            $s->floorheight = Bin::i16($data, $o) * Defs::FRACUNIT;
            $s->ceilingheight = Bin::i16($data, $o + 2) * Defs::FRACUNIT;
            $s->floorpic = $res->flatNumForName(self::name8($data, $o + 4));
            $s->ceilingpic = $res->flatNumForName(self::name8($data, $o + 12));
            $s->lightlevel = Bin::i16($data, $o + 20);
            $s->special = Bin::i16($data, $o + 22);
            $s->tag = Bin::i16($data, $o + 24);
            $s->iSector = $i;
            $this->sectors[] = $s;
        }
    }

    private function loadSides(string $data, object $res): void
    {
        $this->sides = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPSIDEDEF_SIZE <= $n; $o += Defs::MAPSIDEDEF_SIZE) {
            $s = new Side();
            $s->textureoffset = Bin::i16($data, $o) * Defs::FRACUNIT;
            $s->rowoffset = Bin::i16($data, $o + 2) * Defs::FRACUNIT;
            $s->toptexture = $res->textureNumForName(self::name8($data, $o + 4));
            $s->bottomtexture = $res->textureNumForName(self::name8($data, $o + 12));
            $s->midtexture = $res->textureNumForName(self::name8($data, $o + 20));
            $sec = Bin::i16($data, $o + 28);
            $s->sector = $this->sectors[$sec] ?? $this->sectors[0];
            $this->sides[] = $s;
        }
    }

    private function loadLines(string $data): void
    {
        $this->lines = [];
        for ($o = 0, $i = 0, $n = strlen($data); $o + Defs::MAPLINEDEF_SIZE <= $n; $o += Defs::MAPLINEDEF_SIZE, ++$i) {
            $ln = new Line();
            $ln->v1 = $this->vertexes[Bin::i16($data, $o)];
            $ln->v2 = $this->vertexes[Bin::i16($data, $o + 2)];
            $ln->dx = $ln->v2->x - $ln->v1->x;
            $ln->dy = $ln->v2->y - $ln->v1->y;
            $ln->flags = Bin::i16($data, $o + 4);
            $ln->special = Bin::i16($data, $o + 6);
            $ln->tag = Bin::i16($data, $o + 8);
            $s0 = Bin::i16($data, $o + 10);
            $s1 = Bin::i16($data, $o + 12);
            $ln->sidenum = [$s0, $s1];
            $ln->sides = [$s0 >= 0 ? $this->sides[$s0] : null, $s1 >= 0 ? $this->sides[$s1] : null];
            $ln->frontsector = $ln->sides[0]?->sector;
            $ln->backsector = $ln->sides[1]?->sector;
            $ln->bbox[Defs::BOXLEFT] = min($ln->v1->x, $ln->v2->x);
            $ln->bbox[Defs::BOXRIGHT] = max($ln->v1->x, $ln->v2->x);
            $ln->bbox[Defs::BOXBOTTOM] = min($ln->v1->y, $ln->v2->y);
            $ln->bbox[Defs::BOXTOP] = max($ln->v1->y, $ln->v2->y);
            $ln->iLine = $i;
            if ($ln->frontsector !== null) {
                $ln->frontsector->lines[] = $ln;
            }
            if ($ln->backsector !== null && $ln->backsector !== $ln->frontsector) {
                $ln->backsector->lines[] = $ln;
            }
            $this->lines[] = $ln;
        }
    }

    private function loadSegs(string $data): void
    {
        $this->segs = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPSEG_SIZE <= $n; $o += Defs::MAPSEG_SIZE) {
            $s = new Seg();
            $s->v1 = $this->vertexes[Bin::i16($data, $o)];
            $s->v2 = $this->vertexes[Bin::i16($data, $o + 2)];
            $s->angle = Compat::asU32(Bin::i16($data, $o + 4) << 16);
            $s->linedef = $this->lines[Bin::i16($data, $o + 6)];
            $side = Bin::i16($data, $o + 8);
            $s->offset = Bin::i16($data, $o + 10) * Defs::FRACUNIT;
            $s->sidedef = $s->linedef->sides[$side] ?? $s->linedef->sides[0];
            $s->frontsector = $s->sidedef?->sector;
            $s->backsector = ($s->linedef->flags & Defs::ML_TWOSIDED) ? ($s->linedef->sides[$side ^ 1]?->sector) : null;
            $this->segs[] = $s;
        }
    }

    private function loadSubsectors(string $data): void
    {
        $this->subsectors = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPSUBSECTOR_SIZE <= $n; $o += Defs::MAPSUBSECTOR_SIZE) {
            $this->subsectors[] = new Subsector(Bin::u16($data, $o), Bin::u16($data, $o + 2));
        }
    }

    private function loadNodes(string $data): void
    {
        $this->nodes = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPNODE_SIZE <= $n; $o += Defs::MAPNODE_SIZE) {
            $nd = new Node();
            $nd->x = Bin::i16($data, $o) * Defs::FRACUNIT;
            $nd->y = Bin::i16($data, $o + 2) * Defs::FRACUNIT;
            $nd->dx = Bin::i16($data, $o + 4) * Defs::FRACUNIT;
            $nd->dy = Bin::i16($data, $o + 6) * Defs::FRACUNIT;
            $p = $o + 8;
            $nd->bbox = [];
            for ($child = 0; $child < 2; ++$child, $p += 8) {
                $nd->bbox[] = [
                    Bin::i16($data, $p) * Defs::FRACUNIT,
                    Bin::i16($data, $p + 2) * Defs::FRACUNIT,
                    Bin::i16($data, $p + 4) * Defs::FRACUNIT,
                    Bin::i16($data, $p + 6) * Defs::FRACUNIT,
                ];
            }
            $nd->children = [Bin::u16($data, $p), Bin::u16($data, $p + 2)];
            $this->nodes[] = $nd;
        }
    }

    private function loadThings(string $data): void
    {
        $this->things = [];
        for ($o = 0, $n = strlen($data); $o + Defs::MAPTHING_SIZE <= $n; $o += Defs::MAPTHING_SIZE) {
            $this->things[] = new MapThing(
                Bin::i16($data, $o),
                Bin::i16($data, $o + 2),
                Bin::i16($data, $o + 4),
                Bin::i16($data, $o + 6),
                Bin::i16($data, $o + 8),
            );
        }
    }

    private function loadBlockmap(string $data): void
    {
        $this->blockmaplump = $data;
        if (strlen($data) < 8) {
            return;
        }
        $this->bmaporgx = Bin::i16($data, 0) * Defs::FRACUNIT;
        $this->bmaporgy = Bin::i16($data, 2) * Defs::FRACUNIT;
        $this->bmapwidth = Bin::i16($data, 4);
        $this->bmapheight = Bin::i16($data, 6);
    }

    public function playerStart(): ?MapThing
    {
        foreach ($this->things as $thing) {
            if ($thing->type === 1) {
                return $thing;
            }
        }
        return $this->things[0] ?? null;
    }
}
