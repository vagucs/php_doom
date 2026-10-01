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
 * Software renderer: r_main + r_bsp + r_segs + r_plane + r_draw.
 */
declare(strict_types=1);

namespace Doom;

final class RenderClipRange
{
    public function __construct(public int $first = 0, public int $last = 0)
    {
    }
}

final class Visplane
{
    /** @var int[] */
    public array $top = [];
    /** @var int[] */
    public array $bottom = [];

    public function __construct(
        public int $height = 0,
        public int $picnum = 0,
        public int $lightlevel = 0,
        public int $minx = 0,
        public int $maxx = 0,
    ) {
    }
}

final class DrawSeg
{
    public int $x1 = 0;
    public int $x2 = 0;
    public int $scale1 = 0;
    public int $scale2 = 0;
    public int $silhouette = 0;
    /** @var int[] */
    public array $sprtopclip = [];
    /** @var int[] */
    public array $sprbottomclip = [];
    public int $bsilheight = 0;
    public int $tsilheight = 0;
    public mixed $curline = null;
    public int $scalestep = 0;
    /** @var null|int[] */
    public ?array $maskedtexturecol = null;
}

final class Renderer
{
    private const HEIGHTBITS = 12;
    private const HEIGHTUNIT = 1 << self::HEIGHTBITS;
    private const ANGLETOSKYSHIFT = 22;
    private const SHRT_MAX = 0x7FFF;
    private const INT_MAX = 0x7FFFFFFF;
    private const INT_MIN = -0x7FFFFFFF;

    public mixed $res;
    public int $viewwidth = 0;
    public int $viewheight = 0;
    public int $centerx = 0;
    public int $centery = 0;
    public int $centerxfrac = 0;
    public int $centeryfrac = 0;
    public int $projection = 0;
    public int $detailshift = 0;
    /** @var int[] */
    public array $viewangletox = [];
    /** @var int[] */
    public array $xtoviewangle = [];
    public int $clipangle = 0;
    /** @var int[] */
    public array $yslope = [];
    /** @var int[] */
    public array $distscale = [];
    /** @var array<int,array<int,int>> */
    public array $scalelight = [];
    /** @var array<int,array<int,int>> */
    public array $zlight = [];
    /** @var int[] */
    public array $walllights = [];
    /** @var int[] */
    public array $ylookup = [];
    /** @var int[] */
    public array $columnofs = [];
    public int $viewwindowx = 0;
    public int $viewwindowy = 0;
    public int $scaledviewwidth = 0;
    public int $screenblocks = 10;
    public int $pspritescale;
    public int $pspriteiscale;
    /** @var int[] */
    public array $ceilingclip = [];
    /** @var int[] */
    public array $floorclip = [];
    /** @var RenderClipRange[] */
    private array $solidsegs = [];
    private int $newend = 0;
    /** @var Visplane[] */
    private array $visplanes = [];
    private ?Visplane $floorplane = null;
    private ?Visplane $ceilingplane = null;
    public int $viewx = 0;
    public int $viewy = 0;
    public int $viewz = 0;
    public int $viewangle = 0;
    public int $viewcos = 0;
    public int $viewsin = 0;
    public int $extralight = 0;
    public string $fixedcolormap = '';
    private mixed $curline = null;
    private mixed $frontsector = null;
    private mixed $backsector = null;
    private int $rw_x = 0;
    private int $rw_stopx = 0;
    private int $rw_start = 0;
    private int $rw_centerangle = 0;
    private int $rw_offset = 0;
    private int $rw_distance = 0;
    private int $rw_scale = 0;
    private int $rw_scalestep = 0;
    private int $rw_midtexturemid = 0;
    private int $rw_toptexturemid = 0;
    private int $rw_bottomtexturemid = 0;
    private int $rw_normalangle = 0;
    private int $rw_angle1 = 0;
    private bool $segtextured = false;
    private bool $markfloor = false;
    private bool $markceiling = false;
    private bool $maskedtexture = false;
    /** @var null|int[] */
    private ?array $maskedtexturecol = null;
    private int $midtexture = 0;
    private int $toptexture = 0;
    private int $bottomtexture = 0;
    private int $pixhigh = 0;
    private int $pixlow = 0;
    private int $pixhighstep = 0;
    private int $pixlowstep = 0;
    private int $topfrac = 0;
    private int $topstep = 0;
    private int $bottomfrac = 0;
    private int $bottomstep = 0;
    private int $worldtop = 0;
    private int $worldbottom = 0;
    private int $worldhigh = 0;
    private int $worldlow = 0;
    public int $dc_x = 0;
    public int $dc_yl = 0;
    public int $dc_yh = 0;
    public int $dc_iscale = 0;
    public int $dc_texturemid = 0;
    public string $dc_source;
    public string $dc_colormap;
    /** @var int[] */
    public array $fb = [];
    /** @var DrawSeg[] */
    public array $drawsegs = [];
    private int $basexscale = 0;
    private int $baseyscale = 0;

    public function __construct(mixed $resources)
    {
        $this->res = $resources;
        $this->viewwidth = Defs::SCREENWIDTH;
        $this->viewheight = Defs::SCREENHEIGHT - Defs::SBARHEIGHT;
        $this->centerx = intdiv($this->viewwidth, 2);
        $this->centery = intdiv($this->viewheight, 2);
        $this->centerxfrac = $this->centerx << Defs::FRACBITS;
        $this->centeryfrac = $this->centery << Defs::FRACBITS;
        $this->projection = $this->centerxfrac;
        $this->viewangletox = array_fill(0, intdiv(Defs::FINEANGLES, 2), 0);
        $this->xtoviewangle = array_fill(0, Defs::SCREENWIDTH + 1, 0);
        $this->yslope = array_fill(0, Defs::SCREENHEIGHT, 0);
        $this->distscale = array_fill(0, Defs::SCREENWIDTH, 0);
        for ($i = 0; $i < Defs::LIGHTLEVELS; ++$i) {
            $this->scalelight[$i] = array_fill(0, Defs::MAXLIGHTSCALE, 0);
            $this->zlight[$i] = array_fill(0, Defs::MAXLIGHTZ, 0);
        }
        $this->walllights = array_fill(0, Defs::MAXLIGHTSCALE, 0);
        for ($i = 0; $i < Defs::SCREENHEIGHT; ++$i) {
            $this->ylookup[$i] = $i * Defs::SCREENWIDTH;
        }
        $this->columnofs = range(0, Defs::SCREENWIDTH - 1);
        $this->scaledviewwidth = Defs::SCREENWIDTH;
        $this->pspritescale = Defs::FRACUNIT;
        $this->pspriteiscale = Defs::FRACUNIT;
        $this->ceilingclip = array_fill(0, Defs::SCREENWIDTH, 0);
        $this->floorclip = array_fill(0, Defs::SCREENWIDTH, 0);
        for ($i = 0; $i < 64; ++$i) {
            $this->solidsegs[] = new RenderClipRange();
        }
        $this->dc_source = str_repeat("\0", 128);
        $this->dc_colormap = implode('', array_map('chr', range(0, 255)));
        $this->fb = array_fill(0, Defs::SCREENWIDTH * Defs::SCREENHEIGHT, 0);
        $this->initMapping();
        $this->initLights();
        $this->initSlopes();
    }

    public function setViewSize(int $blocks, int $detail): void
    {
        $blocks = max(3, min(11, $blocks));
        $this->detailshift = $detail !== 0 ? 1 : 0;
        $this->screenblocks = $blocks;
        if ($blocks === 11) {
            $scaled = Defs::SCREENWIDTH;
            $viewheight = Defs::SCREENHEIGHT;
        } else {
            $scaled = $blocks * 32;
            $viewheight = (intdiv($blocks * 168, 10)) & ~7;
        }
        $this->scaledviewwidth = $scaled;
        $this->viewwidth = $scaled >> $this->detailshift;
        $this->viewheight = $viewheight;
        $this->centerx = intdiv($this->viewwidth, 2);
        $this->centery = intdiv($this->viewheight, 2);
        $this->centerxfrac = $this->centerx << Defs::FRACBITS;
        $this->centeryfrac = $this->centery << Defs::FRACBITS;
        $this->projection = $this->centerxfrac;
        $this->viewwindowx = (Defs::SCREENWIDTH - $scaled) >> 1;
        $this->viewwindowy = $scaled === Defs::SCREENWIDTH
            ? 0 : (Defs::SCREENHEIGHT - Defs::SBARHEIGHT - $viewheight) >> 1;
        $this->ylookup = [];
        for ($i = 0; $i < Defs::SCREENHEIGHT; ++$i) {
            $this->ylookup[$i] = ($i + $this->viewwindowy) * Defs::SCREENWIDTH;
        }
        $this->columnofs = [];
        for ($i = 0; $i < Defs::SCREENWIDTH; ++$i) {
            $this->columnofs[$i] = $this->viewwindowx + $i;
        }
        $this->ceilingclip = array_fill(0, max(1, $this->viewwidth), 0);
        $this->floorclip = array_fill(0, max(1, $this->viewwidth), 0);
        $this->pspritescale = intdiv(Defs::FRACUNIT * $this->viewwidth, Defs::SCREENWIDTH);
        $this->pspriteiscale = intdiv(Defs::FRACUNIT * Defs::SCREENWIDTH, max(1, $this->viewwidth));
        $this->initMapping();
        $this->initSlopes();
        $this->initLights();
    }

    private function initMapping(): void
    {
        $half = intdiv(Defs::FINEANGLES, 2);
        $focal = Compat::fixedDiv(
            $this->centerxfrac,
            Tables::$finetangent[intdiv(Defs::FINEANGLES, 4) + intdiv(Defs::FIELDOFVIEW, 2)]
        );
        for ($i = 0; $i < $half; ++$i) {
            $ft = Tables::$finetangent[$i];
            if ($ft > Defs::FRACUNIT * 2) {
                $t = -1;
            } elseif ($ft < -Defs::FRACUNIT * 2) {
                $t = $this->viewwidth + 1;
            } else {
                $t = Compat::asI32(
                    $this->centerxfrac - Compat::fixedMul($ft, $focal) + Defs::FRACUNIT - 1
                ) >> Defs::FRACBITS;
                $t = max(-1, min($this->viewwidth + 1, $t));
            }
            $this->viewangletox[$i] = $t;
        }
        for ($x = 0; $x <= $this->viewwidth; ++$x) {
            $i = 0;
            while ($i < $half && $this->viewangletox[$i] > $x) {
                ++$i;
            }
            $this->xtoviewangle[$x] = Compat::asU32(($i << Defs::ANGLETOFINESHIFT) - Defs::ANG90);
        }
        for ($i = 0; $i < $half; ++$i) {
            if ($this->viewangletox[$i] === -1) {
                $this->viewangletox[$i] = 0;
            } elseif ($this->viewangletox[$i] === $this->viewwidth + 1) {
                $this->viewangletox[$i] = $this->viewwidth;
            }
        }
        $this->clipangle = $this->xtoviewangle[0];
    }

    private function initLights(): void
    {
        for ($i = 0; $i < Defs::LIGHTLEVELS; ++$i) {
            $startmap = intdiv(
                ((Defs::LIGHTLEVELS - 1 - $i) * 2) * Defs::NUMCOLORMAPS,
                Defs::LIGHTLEVELS
            );
            for ($j = 0; $j < Defs::MAXLIGHTZ; ++$j) {
                $scale = Compat::fixedDiv(
                    intdiv(Defs::SCREENWIDTH, 2) * Defs::FRACUNIT,
                    ($j + 1) << Defs::LIGHTZSHIFT
                ) >> Defs::LIGHTSCALESHIFT;
                $this->zlight[$i][$j] = max(0, min(Defs::NUMCOLORMAPS - 1, $startmap - intdiv($scale, 2)));
            }
            $vw = max(1, $this->viewwidth << $this->detailshift);
            for ($j = 0; $j < Defs::MAXLIGHTSCALE; ++$j) {
                $level = $startmap - (int) ($j * Defs::SCREENWIDTH / $vw / 2);
                $this->scalelight[$i][$j] = max(0, min(Defs::NUMCOLORMAPS - 1, $level));
            }
        }
    }

    private function initSlopes(): void
    {
        for ($i = 0; $i < $this->viewheight; ++$i) {
            $dy = abs((($i - $this->centery) << Defs::FRACBITS) + intdiv(Defs::FRACUNIT, 2));
            $this->yslope[$i] = Compat::fixedDiv(
                (intdiv($this->viewwidth << $this->detailshift, 2)) * Defs::FRACUNIT,
                max(1, $dy)
            );
        }
        for ($i = 0; $i < $this->viewwidth; ++$i) {
            $cosadj = Compat::absFixed(Tables::fineCos($this->xtoviewangle[$i]));
            $this->distscale[$i] = Compat::fixedDiv(Defs::FRACUNIT, max(1, $cosadj));
        }
    }

    public function pointToAngle(int $x, int $y): int
    {
        $x = Compat::asI32($x - $this->viewx);
        $y = Compat::asI32($y - $this->viewy);
        if ($x === 0 && $y === 0) {
            return 0;
        }
        if ($x >= 0) {
            if ($y >= 0) {
                return $x > $y
                    ? Tables::$tantoangle[Tables::slopeDiv($y, $x)]
                    : Compat::asU32(Defs::ANG90 - 1 - Tables::$tantoangle[Tables::slopeDiv($x, $y)]);
            }
            $y = -$y;
            return $x > $y
                ? Compat::asU32(-Tables::$tantoangle[Tables::slopeDiv($y, $x)])
                : Compat::asU32(0xC0000000 + Tables::$tantoangle[Tables::slopeDiv($x, $y)]);
        }
        $x = -$x;
        if ($y >= 0) {
            return $x > $y
                ? Compat::asU32(Defs::ANG180 - 1 - Tables::$tantoangle[Tables::slopeDiv($y, $x)])
                : Compat::asU32(Defs::ANG90 + Tables::$tantoangle[Tables::slopeDiv($x, $y)]);
        }
        $y = -$y;
        return $x > $y
            ? Compat::asU32(Defs::ANG180 + Tables::$tantoangle[Tables::slopeDiv($y, $x)])
            : Compat::asU32(0xC0000000 - 1 - Tables::$tantoangle[Tables::slopeDiv($x, $y)]);
    }

    public function pointOnSide(int $x, int $y, mixed $node): int
    {
        $dx = Compat::asI32($x - $node->x);
        $dy = Compat::asI32($y - $node->y);
        $left = Compat::asI32($node->dy >> 16) * $dx;
        $right = $dy * Compat::asI32($node->dx >> 16);
        return $right >= $left ? 1 : 0;
    }

    public function scaleFromGlobalAngle(int $visangle): int
    {
        $anglea = Compat::asU32(Defs::ANG90 + Compat::asU32($visangle - $this->viewangle));
        $angleb = Compat::asU32(Defs::ANG90 + Compat::asU32($visangle - $this->rw_normalangle));
        $sinea = Tables::$finesine[($anglea >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK];
        $sineb = Tables::$finesine[($angleb >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK];
        $num = Compat::fixedMul($this->projection, $sineb) << $this->detailshift;
        $den = Compat::fixedMul($this->rw_distance, $sinea);
        if ($den !== 0 && $den > ($num >> 16)) {
            return max(256, min(64 * Defs::FRACUNIT, Compat::fixedDiv($num, $den)));
        }
        return 64 * Defs::FRACUNIT;
    }

    public function setupFrame(int $x, int $y, int $z, int $angle, int $extraLight = 0, int $fixedcolormap = 0): void
    {
        $this->viewx = $x;
        $this->viewy = $y;
        $this->viewz = $z;
        $this->viewangle = Compat::asU32($angle);
        $this->viewsin = Tables::fineSin($this->viewangle);
        $this->viewcos = Tables::fineCos($this->viewangle);
        $this->extralight = $extraLight;
        $this->fixedcolormap = $fixedcolormap ? $this->res->colormap($fixedcolormap) : '';
        $ang = Compat::ushr(Compat::asU32($this->viewangle - Defs::ANG90), Defs::ANGLETOFINESHIFT)
            & Defs::FINEMASK;
        $this->basexscale = Compat::fixedDiv(
            Tables::$finesine[($ang + intdiv(Defs::FINEANGLES, 4)) & Defs::FINEMASK],
            $this->centerxfrac ?: 1
        );
        $this->baseyscale = -Compat::fixedDiv(Tables::$finesine[$ang], $this->centerxfrac ?: 1);
    }

    /** @param int[] $fb */
    public function render(mixed $world, array &$fb): void
    {
        $this->fb =& $fb;
        $this->visplanes = [];
        $this->drawsegs = [];
        $this->clearClip();
        if (!empty($world->nodes)) {
            $this->renderBspNode($world, $world->numnodes - 1);
        } else {
            $this->subsector($world, 0);
        }
        $this->drawPlanes();
    }

    private function clearClip(): void
    {
        $this->solidsegs[0]->first = -0x7FFFFFFF;
        $this->solidsegs[0]->last = -1;
        $this->solidsegs[1]->first = $this->viewwidth;
        $this->solidsegs[1]->last = 0x7FFFFFFF;
        $this->newend = 2;
        for ($i = 0; $i < $this->viewwidth; ++$i) {
            $this->floorclip[$i] = $this->viewheight;
            $this->ceilingclip[$i] = -1;
        }
    }

    private function findPlane(int $height, int $picnum, int $lightlevel): Visplane
    {
        if ($picnum === $this->res->skyflatnum) {
            $height = 0;
            $lightlevel = 0;
        }
        foreach ($this->visplanes as $plane) {
            if ($plane->height === $height && $plane->picnum === $picnum && $plane->lightlevel === $lightlevel) {
                return $plane;
            }
        }
        $plane = new Visplane($height, $picnum, $lightlevel, $this->viewwidth, -1);
        $plane->top = array_fill(0, Defs::SCREENWIDTH, 0xFF);
        $plane->bottom = array_fill(0, Defs::SCREENWIDTH, 0);
        $this->visplanes[] = $plane;
        return $plane;
    }

    private function checkPlane(?Visplane $plane, int $start, int $stop): Visplane
    {
        if ($plane === null) {
            return $this->findPlane(0, 0, 0);
        }
        if ($start < $plane->minx) {
            $intrl = $plane->minx;
            $unionl = $start;
        } else {
            $unionl = $plane->minx;
            $intrl = $start;
        }
        if ($stop > $plane->maxx) {
            $intrh = $plane->maxx;
            $unionh = $stop;
        } else {
            $unionh = $plane->maxx;
            $intrh = $stop;
        }
        $x = $intrl;
        while ($x <= $intrh) {
            if ($x >= 0 && $x < Defs::SCREENWIDTH && $plane->top[$x] !== 0xFF) {
                break;
            }
            ++$x;
        }
        if ($x > $intrh) {
            $plane->minx = $unionl;
            $plane->maxx = $unionh;
            return $plane;
        }
        $copy = new Visplane($plane->height, $plane->picnum, $plane->lightlevel, $start, $stop);
        $copy->top = array_fill(0, Defs::SCREENWIDTH, 0xFF);
        $copy->bottom = array_fill(0, Defs::SCREENWIDTH, 0);
        $this->visplanes[] = $copy;
        return $copy;
    }

    private function renderBspNode(mixed $world, int $bspnum): void
    {
        if (($bspnum & Defs::NF_SUBSECTOR) !== 0 || $bspnum < 0) {
            $this->subsector($world, $bspnum === -1 ? 0 : ($bspnum & ~Defs::NF_SUBSECTOR));
            return;
        }
        $node = $world->nodes[$bspnum];
        $side = $this->pointOnSide($this->viewx, $this->viewy, $node);
        $this->renderBspNode($world, $node->children[$side]);
        $this->renderBspNode($world, $node->children[$side ^ 1]);
    }

    private function subsector(mixed $world, int $num): void
    {
        $sub = $world->subsectors[$num];
        $this->frontsector = $sub->sector;
        $light = max(0, min(Defs::LIGHTLEVELS - 1, ($this->frontsector->lightlevel >> 4) + $this->extralight));
        $this->walllights = $this->scalelight[$light];
        $this->floorplane = $this->findPlane(
            $this->frontsector->floorheight,
            $this->frontsector->floorpic,
            $this->frontsector->lightlevel
        );
        $this->ceilingplane = $this->findPlane(
            $this->frontsector->ceilingheight,
            $this->frontsector->ceilingpic,
            $this->frontsector->lightlevel
        );
        $line = $sub->firstline;
        for ($i = 0; $i < $sub->numlines; ++$i, ++$line) {
            $this->addLine($world->segs[$line]);
        }
    }

    private function addLine(mixed $line): void
    {
        $this->curline = $line;
        $angle1 = $this->pointToAngle($line->v1->x, $line->v1->y);
        $angle2 = $this->pointToAngle($line->v2->x, $line->v2->y);
        $span = Compat::asU32($angle1 - $angle2);
        if ($span >= Defs::ANG180) {
            return;
        }
        $this->rw_angle1 = $angle1;
        $angle1 = Compat::asU32($angle1 - $this->viewangle);
        $angle2 = Compat::asU32($angle2 - $this->viewangle);
        $tspan = Compat::asU32($angle1 + $this->clipangle);
        if ($tspan > Compat::asU32(2 * $this->clipangle)) {
            $tspan = Compat::asU32($tspan - 2 * $this->clipangle);
            if ($tspan >= $span) {
                return;
            }
            $angle1 = $this->clipangle;
        }
        $tspan = Compat::asU32($this->clipangle - $angle2);
        if ($tspan > Compat::asU32(2 * $this->clipangle)) {
            $tspan = Compat::asU32($tspan - 2 * $this->clipangle);
            if ($tspan >= $span) {
                return;
            }
            $angle2 = Compat::asU32(-$this->clipangle);
        }
        $mask = intdiv(Defs::FINEANGLES, 2) - 1;
        $x1 = $this->viewangletox[
            Compat::ushr(Compat::asU32($angle1 + Defs::ANG90), Defs::ANGLETOFINESHIFT) & $mask
        ];
        $x2 = $this->viewangletox[
            Compat::ushr(Compat::asU32($angle2 + Defs::ANG90), Defs::ANGLETOFINESHIFT) & $mask
        ];
        if ($x1 === $x2) {
            return;
        }
        $this->backsector = $line->backsector;
        if (
            $this->backsector === null
            || $this->backsector->ceilingheight <= $this->frontsector->floorheight
            || $this->backsector->floorheight >= $this->frontsector->ceilingheight
        ) {
            $this->clipSolid($x1, $x2 - 1);
        } else {
            $this->clipPass($x1, $x2 - 1);
        }
    }

    private function clipSolid(int $first, int $last): void
    {
        if ($first > $last) {
            return;
        }
        $start = 0;
        while ($start < $this->newend && $this->solidsegs[$start]->last < $first - 1) {
            ++$start;
        }
        if ($start >= $this->newend) {
            $this->storeWallRange($first, $last);
            return;
        }
        if ($first < $this->solidsegs[$start]->first) {
            if ($last < $this->solidsegs[$start]->first - 1) {
                $this->storeWallRange($first, $last);
                array_splice($this->solidsegs, $start, 0, [new RenderClipRange($first, $last)]);
                ++$this->newend;
                return;
            }
            $this->storeWallRange($first, $this->solidsegs[$start]->first - 1);
            $this->solidsegs[$start]->first = $first;
        }
        if ($last <= $this->solidsegs[$start]->last) {
            return;
        }
        $next = $start;
        while ($next + 1 < $this->newend && $last >= $this->solidsegs[$next + 1]->first - 1) {
            $this->storeWallRange($this->solidsegs[$next]->last + 1, $this->solidsegs[$next + 1]->first - 1);
            ++$next;
            if ($last <= $this->solidsegs[$next]->last) {
                $this->solidsegs[$start]->last = $this->solidsegs[$next]->last;
                $this->crunchSolid($start, $next);
                return;
            }
        }
        $this->storeWallRange($this->solidsegs[$next]->last + 1, $last);
        $this->solidsegs[$start]->last = $last;
        $this->crunchSolid($start, $next);
    }

    private function crunchSolid(int $start, int $next): void
    {
        if ($next === $start) {
            return;
        }
        array_splice($this->solidsegs, $start + 1, $next - $start);
        $this->newend -= $next - $start;
        while (count($this->solidsegs) < 64) {
            $this->solidsegs[] = new RenderClipRange();
        }
    }

    private function clipPass(int $first, int $last): void
    {
        if ($first > $last) {
            return;
        }
        $start = 0;
        while ($start < $this->newend && $this->solidsegs[$start]->last < $first - 1) {
            ++$start;
        }
        if ($start >= $this->newend) {
            $this->storeWallRange($first, $last);
            return;
        }
        if ($first < $this->solidsegs[$start]->first) {
            if ($last < $this->solidsegs[$start]->first - 1) {
                $this->storeWallRange($first, $last);
                return;
            }
            $this->storeWallRange($first, $this->solidsegs[$start]->first - 1);
        }
        if ($last <= $this->solidsegs[$start]->last) {
            return;
        }
        $next = $start;
        while ($next + 1 < $this->newend && $last >= $this->solidsegs[$next + 1]->first - 1) {
            $this->storeWallRange($this->solidsegs[$next]->last + 1, $this->solidsegs[$next + 1]->first - 1);
            ++$next;
            if ($last <= $this->solidsegs[$next]->last) {
                return;
            }
        }
        $this->storeWallRange($this->solidsegs[$next]->last + 1, $last);
    }

    private function storeWallRange(int $start, int $stop): void
    {
        if ($start > $stop) {
            return;
        }
        $line = $this->curline;
        $linedef = $line->linedef;
        $sidedef = $line->sidedef;
        $linedef->flags |= Defs::ML_MAPPED;
        $this->rw_normalangle = Compat::asU32($line->angle + Defs::ANG90);
        $offsetangle = Compat::asU32($this->rw_normalangle - $this->rw_angle1);
        if ($offsetangle > Defs::ANG180) {
            $offsetangle = Compat::asU32(-$offsetangle);
        }
        $offsetangle = min($offsetangle, Defs::ANG90);
        $distangle = Compat::asU32(Defs::ANG90 - $offsetangle);
        $hyp = $this->pointToDist($line->v1->x, $line->v1->y);
        $this->rw_distance = Compat::fixedMul(
            $hyp,
            Tables::$finesine[($distangle >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK]
        );
        $this->rw_x = $start;
        $this->rw_start = $start;
        $this->rw_stopx = $stop + 1;
        $this->rw_scale = $this->scaleFromGlobalAngle(Compat::asU32($this->viewangle + $this->xtoviewangle[$start]));
        $this->rw_scalestep = $stop > $start
            ? self::floorDiv(
                $this->scaleFromGlobalAngle(Compat::asU32($this->viewangle + $this->xtoviewangle[$stop]))
                    - $this->rw_scale,
                $stop - $start
            ) : 0;
        $this->worldtop = $this->frontsector->ceilingheight - $this->viewz;
        $this->worldbottom = $this->frontsector->floorheight - $this->viewz;
        $this->midtexture = $this->toptexture = $this->bottomtexture = 0;
        $this->maskedtexture = false;
        $this->maskedtexturecol = null;
        $this->segtextured = false;
        if ($this->backsector === null) {
            $this->midtexture = $sidedef->midtexture;
            $this->markfloor = $this->markceiling = true;
            $this->rw_midtexturemid = (($linedef->flags & Defs::ML_DONTPEGBOTTOM) !== 0)
                ? $this->frontsector->floorheight + $this->res->textureHeight($this->midtexture) - $this->viewz
                : $this->worldtop;
            $this->rw_midtexturemid += $sidedef->rowoffset;
        } else {
            $this->worldhigh = $this->backsector->ceilingheight - $this->viewz;
            $this->worldlow = $this->backsector->floorheight - $this->viewz;
            if (
                $this->frontsector->ceilingpic === $this->res->skyflatnum
                && $this->backsector->ceilingpic === $this->res->skyflatnum
            ) {
                $this->worldtop = $this->worldhigh;
            }
            $this->markfloor = $this->worldlow !== $this->worldbottom
                || $this->backsector->floorpic !== $this->frontsector->floorpic
                || $this->backsector->lightlevel !== $this->frontsector->lightlevel;
            $this->markceiling = $this->worldhigh !== $this->worldtop
                || $this->backsector->ceilingpic !== $this->frontsector->ceilingpic
                || $this->backsector->lightlevel !== $this->frontsector->lightlevel;
            if (
                $this->backsector->ceilingheight <= $this->frontsector->floorheight
                || $this->backsector->floorheight >= $this->frontsector->ceilingheight
            ) {
                $this->markfloor = $this->markceiling = true;
            }
            if ($this->worldhigh < $this->worldtop) {
                $this->toptexture = $sidedef->toptexture;
                $this->rw_toptexturemid = (($linedef->flags & Defs::ML_DONTPEGTOP) !== 0)
                    ? $this->worldtop
                    : $this->backsector->ceilingheight + $this->res->textureHeight($this->toptexture) - $this->viewz;
            }
            if ($this->worldlow > $this->worldbottom) {
                $this->bottomtexture = $sidedef->bottomtexture;
                $this->rw_bottomtexturemid = (($linedef->flags & Defs::ML_DONTPEGBOTTOM) !== 0)
                    ? $this->worldtop : $this->worldlow;
            }
            $this->rw_toptexturemid += $sidedef->rowoffset;
            $this->rw_bottomtexturemid += $sidedef->rowoffset;
            if ($sidedef->midtexture !== 0) {
                $this->maskedtexture = true;
                $this->maskedtexturecol = array_fill(0, $stop - $start + 1, self::SHRT_MAX);
            }
        }
        $this->segtextured = $this->midtexture !== 0 || $this->toptexture !== 0
            || $this->bottomtexture !== 0 || $this->maskedtexture;
        if ($this->segtextured) {
            $offsetangle = Compat::asU32($this->rw_normalangle - $this->rw_angle1);
            if ($offsetangle > Defs::ANG180) {
                $offsetangle = Compat::asU32(-$offsetangle);
            }
            $this->rw_offset = Compat::fixedMul(
                $hyp,
                Tables::$finesine[($offsetangle >> Defs::ANGLETOFINESHIFT) & Defs::FINEMASK]
            );
            if (Compat::asU32($this->rw_normalangle - $this->rw_angle1) < Defs::ANG180) {
                $this->rw_offset = -$this->rw_offset;
            }
            $this->rw_offset += $sidedef->textureoffset + $line->offset;
            $this->rw_centerangle = Compat::asU32(Defs::ANG90 + $this->viewangle - $this->rw_normalangle);
        }
        if ($this->frontsector->floorheight >= $this->viewz) {
            $this->markfloor = false;
        }
        if (
            $this->frontsector->ceilingheight <= $this->viewz
            && $this->frontsector->ceilingpic !== $this->res->skyflatnum
        ) {
            $this->markceiling = false;
        }
        if ($this->markceiling) {
            $this->ceilingplane = $this->checkPlane($this->ceilingplane, $start, $stop);
        }
        if ($this->markfloor) {
            $this->floorplane = $this->checkPlane($this->floorplane, $start, $stop);
        }
        $this->worldtop >>= 4;
        $this->worldbottom >>= 4;
        $this->topstep = -Compat::fixedMul($this->rw_scalestep, $this->worldtop);
        $this->topfrac = ($this->centeryfrac >> 4) - Compat::fixedMul($this->worldtop, $this->rw_scale);
        $this->bottomstep = -Compat::fixedMul($this->rw_scalestep, $this->worldbottom);
        $this->bottomfrac = ($this->centeryfrac >> 4) - Compat::fixedMul($this->worldbottom, $this->rw_scale);
        if ($this->backsector !== null) {
            $this->worldhigh >>= 4;
            $this->worldlow >>= 4;
            if ($this->worldhigh < $this->worldtop) {
                $this->pixhigh = ($this->centeryfrac >> 4) - Compat::fixedMul($this->worldhigh, $this->rw_scale);
                $this->pixhighstep = -Compat::fixedMul($this->rw_scalestep, $this->worldhigh);
            }
            if ($this->worldlow > $this->worldbottom) {
                $this->pixlow = ($this->centeryfrac >> 4) - Compat::fixedMul($this->worldlow, $this->rw_scale);
                $this->pixlowstep = -Compat::fixedMul($this->rw_scalestep, $this->worldlow);
            }
        }
        $scale1 = $this->rw_scale;
        $this->renderSegLoop();
        $this->pushDrawseg($start, $stop, $scale1);
    }

    private function pushDrawseg(int $start, int $stop, int $scale1): void
    {
        $ds = new DrawSeg();
        $ds->x1 = $start;
        $ds->x2 = $stop;
        $ds->scale1 = $scale1;
        $ds->scale2 = $scale1 + $this->rw_scalestep * max(0, $stop - $start);
        $ds->curline = $this->curline;
        $ds->scalestep = $this->rw_scalestep;
        $ds->maskedtexturecol = $this->maskedtexturecol;
        if ($this->backsector === null) {
            $ds->silhouette = Defs::SIL_BOTH;
            $ds->bsilheight = self::INT_MAX;
            $ds->tsilheight = self::INT_MIN;
            $width = $stop - $start + 1;
            $ds->sprtopclip = array_fill(0, $width, $this->viewheight);
            $ds->sprbottomclip = array_fill(0, $width, -1);
        } else {
            $ds->silhouette = Defs::SIL_NONE;
            if ($this->frontsector->floorheight > $this->backsector->floorheight) {
                $ds->silhouette = Defs::SIL_BOTTOM;
                $ds->bsilheight = $this->frontsector->floorheight;
            } elseif ($this->backsector->floorheight > $this->viewz) {
                $ds->silhouette = Defs::SIL_BOTTOM;
                $ds->bsilheight = self::INT_MAX;
            }
            if ($this->frontsector->ceilingheight < $this->backsector->ceilingheight) {
                $ds->silhouette |= Defs::SIL_TOP;
                $ds->tsilheight = $this->frontsector->ceilingheight;
            } elseif ($this->backsector->ceilingheight < $this->viewz) {
                $ds->silhouette |= Defs::SIL_TOP;
                $ds->tsilheight = self::INT_MIN;
            }
            if ($this->backsector->ceilingheight <= $this->frontsector->floorheight) {
                $ds->silhouette |= Defs::SIL_BOTTOM;
                $ds->bsilheight = self::INT_MAX;
            }
            if ($this->backsector->floorheight >= $this->frontsector->ceilingheight) {
                $ds->silhouette |= Defs::SIL_TOP;
                $ds->tsilheight = self::INT_MIN;
            }
            $ds->sprtopclip = array_slice($this->ceilingclip, $start, $stop - $start + 1);
            $ds->sprbottomclip = array_slice($this->floorclip, $start, $stop - $start + 1);
            if ($this->maskedtexture) {
                if (($ds->silhouette & Defs::SIL_TOP) === 0) {
                    $ds->silhouette |= Defs::SIL_TOP;
                    $ds->tsilheight = self::INT_MIN;
                }
                if (($ds->silhouette & Defs::SIL_BOTTOM) === 0) {
                    $ds->silhouette |= Defs::SIL_BOTTOM;
                    $ds->bsilheight = self::INT_MAX;
                }
            }
        }
        $this->drawsegs[] = $ds;
    }

    private function pointToDist(int $x, int $y): int
    {
        $dx = Compat::absFixed($x - $this->viewx);
        $dy = Compat::absFixed($y - $this->viewy);
        if ($dy > $dx) {
            [$dx, $dy] = [$dy, $dx];
        }
        if ($dx === 0) {
            return 0;
        }
        $frac = Compat::fixedDiv($dy, $dx);
        $ang = (Tables::$tantoangle[min($frac >> Defs::DBITS, 2048)] + Defs::ANG90)
            >> Defs::ANGLETOFINESHIFT;
        return Compat::fixedDiv($dx, Tables::$finesine[$ang & Defs::FINEMASK]);
    }

    private function renderSegLoop(): void
    {
        $texturecolumn = 0;
        while ($this->rw_x < $this->rw_stopx) {
            $yl = Compat::shar($this->topfrac + self::HEIGHTUNIT - 1, self::HEIGHTBITS);
            $yl = max($yl, $this->ceilingclip[$this->rw_x] + 1);
            if ($this->markceiling && $this->ceilingplane !== null) {
                $top = $this->ceilingclip[$this->rw_x] + 1;
                $bottom = min($yl - 1, $this->floorclip[$this->rw_x] - 1);
                if ($top <= $bottom) {
                    $this->ceilingplane->top[$this->rw_x] = $top;
                    $this->ceilingplane->bottom[$this->rw_x] = $bottom;
                }
            }
            $yh = min(
                Compat::shar($this->bottomfrac, self::HEIGHTBITS),
                $this->floorclip[$this->rw_x] - 1
            );
            if ($this->markfloor && $this->floorplane !== null) {
                $top = max($yh + 1, $this->ceilingclip[$this->rw_x] + 1);
                $bottom = $this->floorclip[$this->rw_x] - 1;
                if ($top <= $bottom) {
                    $this->floorplane->top[$this->rw_x] = $top;
                    $this->floorplane->bottom[$this->rw_x] = $bottom;
                }
            }
            if ($this->segtextured) {
                $angle = Compat::ushr(
                    Compat::asU32($this->rw_centerangle + $this->xtoviewangle[$this->rw_x]),
                    Defs::ANGLETOFINESHIFT
                );
                $tan = Tables::$finetangent[$angle & (intdiv(Defs::FINEANGLES, 2) - 1)];
                $texturecolumn = Compat::shar(
                    $this->rw_offset - Compat::fixedMul($tan, $this->rw_distance),
                    Defs::FRACBITS
                );
                $index = min(Defs::MAXLIGHTSCALE - 1, Compat::ushr($this->rw_scale, Defs::LIGHTSCALESHIFT));
                $this->dc_colormap = $this->fixedcolormap !== '' ? $this->fixedcolormap : $this->res->colormap($this->walllights[$index]);
                $this->dc_x = $this->rw_x;
                $this->dc_iscale = $this->rw_scale !== 0 ? intdiv(0xFFFFFFFF, $this->rw_scale) : 0;
            }
            if ($this->midtexture !== 0) {
                $this->dc_yl = $yl;
                $this->dc_yh = $yh;
                $this->dc_texturemid = $this->rw_midtexturemid;
                $this->dc_source = $this->res->getColumn($this->midtexture, $texturecolumn);
                $this->drawColumn();
                $this->ceilingclip[$this->rw_x] = $this->viewheight;
                $this->floorclip[$this->rw_x] = -1;
            } else {
                if ($this->toptexture !== 0) {
                    $mid = min(
                        Compat::shar($this->pixhigh, self::HEIGHTBITS),
                        $this->floorclip[$this->rw_x] - 1
                    );
                    $this->pixhigh += $this->pixhighstep;
                    if ($mid >= $yl) {
                        $this->dc_yl = $yl;
                        $this->dc_yh = $mid;
                        $this->dc_texturemid = $this->rw_toptexturemid;
                        $this->dc_source = $this->res->getColumn($this->toptexture, $texturecolumn);
                        $this->drawColumn();
                        $this->ceilingclip[$this->rw_x] = $mid;
                    } else {
                        $this->ceilingclip[$this->rw_x] = $yl - 1;
                    }
                } elseif ($this->markceiling) {
                    $this->ceilingclip[$this->rw_x] = $yl - 1;
                }
                if ($this->bottomtexture !== 0) {
                    $mid = max(
                        Compat::shar($this->pixlow + self::HEIGHTUNIT - 1, self::HEIGHTBITS),
                        $this->ceilingclip[$this->rw_x] + 1
                    );
                    $this->pixlow += $this->pixlowstep;
                    if ($mid <= $yh) {
                        $this->dc_yl = $mid;
                        $this->dc_yh = $yh;
                        $this->dc_texturemid = $this->rw_bottomtexturemid;
                        $this->dc_source = $this->res->getColumn($this->bottomtexture, $texturecolumn);
                        $this->drawColumn();
                        $this->floorclip[$this->rw_x] = $mid;
                    } else {
                        $this->floorclip[$this->rw_x] = $yh + 1;
                    }
                } elseif ($this->markfloor) {
                    $this->floorclip[$this->rw_x] = $yh + 1;
                }
                if ($this->maskedtexture && $this->maskedtexturecol !== null) {
                    $this->maskedtexturecol[$this->rw_x - $this->rw_start] = $texturecolumn;
                }
            }
            $this->rw_scale += $this->rw_scalestep;
            $this->topfrac += $this->topstep;
            $this->bottomfrac += $this->bottomstep;
            ++$this->rw_x;
        }
    }

    public function drawColumn(): void
    {
        $count = $this->dc_yh - $this->dc_yl;
        if ($count < 0 || $this->dc_x < 0 || $this->dc_x >= $this->viewwidth) {
            return;
        }
        $yl = max(0, min(Defs::SCREENHEIGHT - 1, $this->dc_yl));
        if ($this->detailshift !== 0) {
            $x = $this->dc_x << 1;
            if ($x < 0 || $x + 1 >= Defs::SCREENWIDTH) {
                return;
            }
            $dest = $this->ylookup[$yl] + $this->columnofs[$x];
            $dest2 = $dest + 1;
        } else {
            $dest = $this->ylookup[$yl] + $this->columnofs[$this->dc_x];
            $dest2 = null;
        }
        $fracstep = $this->dc_iscale;
        $frac = $this->dc_texturemid + ($this->dc_yl - $this->centery) * $fracstep;
        $slen = strlen($this->dc_source);
        $clen = strlen($this->dc_colormap);
        $limit = Defs::SCREENWIDTH * Defs::SCREENHEIGHT;
        while ($count >= 0 && $dest < $limit) {
            $idx = ($frac >> Defs::FRACBITS) & 127;
            if ($slen !== 0) {
                $pix = ord($this->dc_source[$idx % $slen]);
                $value = $pix < $clen ? ord($this->dc_colormap[$pix]) : $pix;
                $this->fb[$dest] = $value;
                if ($dest2 !== null && $dest2 < $limit) {
                    $this->fb[$dest2] = $value;
                }
            }
            $dest += Defs::SCREENWIDTH;
            if ($dest2 !== null) {
                $dest2 += Defs::SCREENWIDTH;
            }
            $frac = Compat::asI32($frac + $fracstep);
            --$count;
        }
    }

    public function drawMasked(): void
    {
        for ($i = count($this->drawsegs) - 1; $i >= 0; --$i) {
            $ds = $this->drawsegs[$i];
            if (!empty($ds->maskedtexturecol)) {
                $this->renderMaskedSegRange($ds, $ds->x1, $ds->x2);
            }
        }
    }

    public function renderMaskedSegRange(DrawSeg $ds, int $x1, int $x2): void
    {
        if (empty($ds->maskedtexturecol) || $ds->curline === null) {
            return;
        }
        $line = $ds->curline;
        $front = $line->frontsector;
        $back = $line->backsector;
        $sidedef = $line->sidedef;
        $texnum = $sidedef?->midtexture ?? 0;
        if ($texnum === 0 || $back === null || $front === null) {
            return;
        }
        $lightnum = ($front->lightlevel >> 4) + $this->extralight;
        if ($line->v1->y === $line->v2->y) {
            --$lightnum;
        } elseif ($line->v1->x === $line->v2->x) {
            ++$lightnum;
        }
        $walllights = $this->scalelight[max(0, min(Defs::LIGHTLEVELS - 1, $lightnum))];
        if (($line->linedef->flags & Defs::ML_DONTPEGBOTTOM) !== 0) {
            $texturemid = max($front->floorheight, $back->floorheight)
                + $this->res->textureHeight($texnum) - $this->viewz;
        } else {
            $texturemid = min($front->ceilingheight, $back->ceilingheight) - $this->viewz;
        }
        $texturemid += $sidedef->rowoffset;
        $spryscale = $ds->scale1 + ($x1 - $ds->x1) * $ds->scalestep;
        for ($x = $x1; $x <= $x2; ++$x, $spryscale += $ds->scalestep) {
            $i = $x - $ds->x1;
            if ($i < 0 || $i >= count($ds->maskedtexturecol)) {
                continue;
            }
            $tcol = $ds->maskedtexturecol[$i];
            if ($tcol === self::SHRT_MAX) {
                continue;
            }
            $index = $spryscale > 0 ? Compat::ushr($spryscale, Defs::LIGHTSCALESHIFT) : 0;
            $index = min(Defs::MAXLIGHTSCALE - 1, $index);
            $this->dc_colormap = $this->fixedcolormap !== '' ? $this->fixedcolormap : $this->res->colormap($walllights[$index]);
            $this->dc_x = $x;
            $this->dc_iscale = $spryscale !== 0 ? intdiv(0xFFFFFFFF, $spryscale) : 0;
            $this->dc_texturemid = $texturemid;
            $topscreen = $this->centeryfrac - Compat::fixedMul($texturemid, $spryscale);
            $mceil = $ds->sprtopclip[$i] ?? -1;
            $mfloor = $ds->sprbottomclip[$i] ?? $this->viewheight;
            $this->drawMaskedColumn(
                $this->res->columnPosts($texnum, $tcol),
                $topscreen,
                $spryscale,
                $mceil,
                $mfloor
            );
            $ds->maskedtexturecol[$i] = self::SHRT_MAX;
        }
    }

    /** @param array<int,array{0:int,1:string}> $posts */
    private function drawMaskedColumn(array $posts, int $sprtopscreen, int $spryscale, int $mceil, int $mfloor): void
    {
        $basemid = $this->dc_texturemid;
        foreach ($posts as [$topdelta, $pixels]) {
            $length = strlen($pixels);
            if ($length === 0) {
                continue;
            }
            $topscreen = $sprtopscreen + $spryscale * $topdelta;
            $bottomscreen = $topscreen + $spryscale * $length;
            $yl = ($topscreen + Defs::FRACUNIT - 1) >> Defs::FRACBITS;
            $yh = ($bottomscreen - 1) >> Defs::FRACBITS;
            $yh = min($yh, $mfloor - 1, $this->viewheight - 1);
            $yl = max($yl, $mceil + 1, 0);
            if ($yl <= $yh) {
                $this->dc_yl = $yl;
                $this->dc_yh = $yh;
                $this->dc_source = substr($pixels . str_repeat("\0", 128), 0, 128);
                $this->dc_texturemid = $basemid - ($topdelta << Defs::FRACBITS);
                $this->drawColumn();
            }
        }
        $this->dc_texturemid = $basemid;
    }

    private function drawPlanes(): void
    {
        foreach ($this->visplanes as $plane) {
            if ($plane->minx > $plane->maxx) {
                continue;
            }
            if ($plane->picnum === $this->res->skyflatnum) {
                $this->dc_iscale = intdiv(9 * Defs::FRACUNIT, 10);
                $this->dc_colormap = $this->res->colormap(0);
                $this->dc_texturemid = 100 * Defs::FRACUNIT;
                for ($x = $plane->minx; $x <= $plane->maxx; ++$x) {
                    $yl = $plane->top[$x];
                    $yh = $plane->bottom[$x];
                    if ($yl <= $yh && $yl < 0xFF) {
                        $ang = Compat::ushr(
                            Compat::asU32($this->viewangle + $this->xtoviewangle[$x]),
                            self::ANGLETOSKYSHIFT
                        );
                        $this->dc_x = $x;
                        $this->dc_yl = $yl;
                        $this->dc_yh = $yh;
                        $this->dc_source = $this->res->getColumn($this->res->skytexture, $ang);
                        $this->drawColumn();
                    }
                }
                continue;
            }
            $light = max(
                0,
                min(Defs::LIGHTLEVELS - 1, ($plane->lightlevel >> 4) + $this->extralight)
            );
            $planezlight = $this->zlight[$light];
            $flat = $this->res->flatPixels($plane->picnum);
            $planeheight = Compat::absFixed($plane->height - $this->viewz);
            if ($planeheight === 0) {
                continue;
            }
            $cachedY = -1;
            $distance = 0;
            for ($x = max(0, $plane->minx); $x < min($this->viewwidth, $plane->maxx + 1); ++$x) {
                $t1 = $plane->top[$x];
                $b1 = $plane->bottom[$x];
                if ($t1 > $b1 || $t1 === 0xFF) {
                    continue;
                }
                for ($y = $t1; $y <= min($b1, $this->viewheight - 1); ++$y) {
                    if ($y !== $cachedY) {
                        $cachedY = $y;
                        $distance = Compat::fixedMul($planeheight, $this->yslope[$y]);
                    }
                    $length = Compat::fixedMul($distance, $this->distscale[$x]);
                    $ang = Compat::ushr(
                        Compat::asU32($this->viewangle + $this->xtoviewangle[$x]),
                        Defs::ANGLETOFINESHIFT
                    ) & Defs::FINEMASK;
                    $xfrac = $this->viewx + Compat::fixedMul(
                        Tables::$finesine[($ang + intdiv(Defs::FINEANGLES, 4)) & Defs::FINEMASK],
                        $length
                    );
                    $yfrac = -$this->viewy - Compat::fixedMul(Tables::$finesine[$ang], $length);
                    $index = min(Defs::MAXLIGHTZ - 1, Compat::ushr($distance, Defs::LIGHTZSHIFT));
                    $cm = $this->fixedcolormap !== '' ? $this->fixedcolormap : $this->res->colormap($planezlight[$index]);
                    $spot = (($xfrac >> 16) & 63) | (($yfrac >> 10) & 0x0FC0);
                    $source = $spot < strlen($flat) ? ord($flat[$spot]) : 0;
                    $pix = ord($cm[$source]);
                    if ($this->detailshift !== 0) {
                        $xx = $x << 1;
                        $off = $this->ylookup[$y] + $this->columnofs[$xx];
                        $this->fb[$off] = $pix;
                        $this->fb[$off + 1] = $pix;
                    } else {
                        $this->fb[$this->ylookup[$y] + $this->columnofs[$x]] = $pix;
                    }
                }
            }
        }
    }

    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        if (($a % $b) !== 0 && (($a < 0) !== ($b < 0))) {
            --$q;
        }
        return $q;
    }
}
