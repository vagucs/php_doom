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
 * VisSprite projection and wall clip (r_things.prg).
 */
declare(strict_types=1);

namespace Doom;

final class SpriteFrame
{
    public int $rotate = -1;
    /** @var int[] */
    public array $lump;
    /** @var int[] */
    public array $flip;

    public function __construct()
    {
        $this->lump = array_fill(0, 8, -1);
        $this->flip = array_fill(0, 8, 0);
    }
}

final class Sprites
{
    public const BASEYCENTER = 100;
    public const WEAPONTOP = 32 * Defs::FRACUNIT;
    public const WEAPONBOTTOM = 128 * Defs::FRACUNIT;
    public const LOWERSPEED = 6 * Defs::FRACUNIT;
    public const RAISESPEED = 6 * Defs::FRACUNIT;

    private const MINZ = 4 * Defs::FRACUNIT;
    private const MAX_SPRITE_FRAMES = 29;

    /**
     * R_InitSpriteDefs: parse S_START..S_END, including mirrored POSSA2A8 lumps.
     *
     * @return array<string, SpriteFrame[]>
     */
    public static function initSpriteDefs(Wad $wad): array
    {
        $start = $wad->checkNumForName('S_START');
        $end = $wad->checkNumForName('S_END');
        if ($start < 0) {
            $start = $wad->checkNumForName('SS_START');
        }
        if ($end < 0) {
            $end = $wad->checkNumForName('SS_END');
        }
        if ($start >= 0 && $end > $start) {
            $first = $start + 1;
            $last = $end - 1;
        } else {
            $first = 0;
            $last = $wad->numLumps() - 1;
        }

        /** @var array<string,int[]> $buckets */
        $buckets = [];
        for ($lump = $first; $lump <= $last; ++$lump) {
            $name = $wad->lumpName($lump);
            if (strlen($name) < 6) {
                continue;
            }
            $base = substr($name, 0, 4);
            $buckets[$base] ??= [];
            $buckets[$base][] = $lump;
        }

        $result = [];
        foreach ($buckets as $spriteName => $lumps) {
            $frames = [];
            for ($i = 0; $i < self::MAX_SPRITE_FRAMES; ++$i) {
                $frames[] = new SpriteFrame();
            }
            $maxFrame = -1;
            foreach ($lumps as $lump) {
                $name = $wad->lumpName($lump);
                $frame = ord($name[4]) - ord('A');
                $rotation = ord($name[5]) - ord('0');
                if (self::installSpriteLump($frames, $lump, $frame, $rotation, false)) {
                    $maxFrame = max($maxFrame, $frame);
                }
                if (strlen($name) >= 8 && $name[6] >= 'A' && $name[6] <= ']') {
                    $frame2 = ord($name[6]) - ord('A');
                    $rotation2 = ord($name[7]) - ord('0');
                    if (self::installSpriteLump($frames, $lump, $frame2, $rotation2, true)) {
                        $maxFrame = max($maxFrame, $frame2);
                    }
                }
            }
            if ($maxFrame >= 0) {
                $result[$spriteName] = array_slice($frames, 0, $maxFrame + 1);
            }
        }
        return $result;
    }

    /**
     * @param SpriteFrame[] $frames
     */
    private static function installSpriteLump(
        array $frames,
        int $lump,
        int $frame,
        int $rotation,
        bool $flipped,
    ): bool {
        if ($frame < 0 || $frame >= self::MAX_SPRITE_FRAMES || $rotation < 0 || $rotation > 8) {
            return false;
        }
        $spriteFrame = $frames[$frame];
        if ($rotation === 0) {
            if ($spriteFrame->rotate === 1) {
                return true;
            }
            $spriteFrame->rotate = 0;
            for ($r = 0; $r < 8; ++$r) {
                $spriteFrame->lump[$r] = $lump;
                $spriteFrame->flip[$r] = $flipped ? 1 : 0;
            }
            return true;
        }
        if ($spriteFrame->rotate === 0) {
            return true;
        }
        $spriteFrame->rotate = 1;
        $index = $rotation - 1;
        if ($spriteFrame->lump[$index] < 0) {
            $spriteFrame->lump[$index] = $lump;
            $spriteFrame->flip[$index] = $flipped ? 1 : 0;
        }
        return true;
    }

    /** @return null|array{0:int,1:int} */
    public static function lookupSprite(
        Resources $resources,
        string $name,
        int $angleToThing,
        int $mobjAngle,
        int $frame,
    ): ?array {
        $base = strtoupper(substr($name, 0, 4));
        $frames = $resources->sprites[$base] ?? null;
        if (!$frames) {
            return null;
        }
        $frameIndex = $frame & 31;
        if ($frameIndex >= count($frames)) {
            return null;
        }
        $spriteFrame = $frames[$frameIndex];
        if ($spriteFrame->rotate !== 0) {
            $rotation = Compat::ushr(
                Compat::asU32($angleToThing - $mobjAngle + intdiv(Defs::ANG45, 2) * 9),
                29
            ) & 7;
            $lump = $spriteFrame->lump[$rotation];
            $flip = $spriteFrame->flip[$rotation];
        } else {
            $lump = $spriteFrame->lump[0];
            $flip = $spriteFrame->flip[0];
        }
        return $lump < 0 ? null : [$lump, $flip];
    }

    /** @param int[] $fb */
    public static function drawSprites(Renderer $renderer, World $world, array &$fb): void
    {
        $visible = [];
        foreach ($world->mobjs as $mobj) {
            if (($mobj->sprite ?? '') === '' || ($mobj->player ?? null) !== null) {
                continue;
            }
            $item = self::project($renderer, $mobj);
            if ($item !== null) {
                $visible[] = $item;
            }
        }
        usort($visible, static fn(array $a, array $b): int => $a['scale'] <=> $b['scale']);
        foreach ($visible as $sprite) {
            self::drawSprite($renderer, $fb, $sprite);
        }
    }

    /** @return null|array<string,mixed> */
    private static function project(Renderer $renderer, mixed $mobj): ?array
    {
        $trX = Compat::asI32($mobj->x - $renderer->viewx);
        $trY = Compat::asI32($mobj->y - $renderer->viewy);
        $gxt = Compat::fixedMul($trX, $renderer->viewcos);
        $gyt = -Compat::fixedMul($trY, $renderer->viewsin);
        $tz = $gxt - $gyt;
        if ($tz < self::MINZ) {
            return null;
        }
        $xscale = Compat::fixedDiv($renderer->projection, $tz);
        $gxt = -Compat::fixedMul($trX, $renderer->viewsin);
        $gyt = Compat::fixedMul($trY, $renderer->viewcos);
        $tx = -($gyt + $gxt);
        if (abs($tx) > $tz * 4) {
            return null;
        }
        $found = self::lookupSprite(
            $renderer->res,
            (string) $mobj->sprite,
            $renderer->pointToAngle($mobj->x, $mobj->y),
            $mobj->angle,
            $mobj->frame ?? 0
        );
        if ($found === null) {
            return null;
        }
        [$lump, $flip] = $found;
        $patch = $renderer->res->wad->cacheLumpNum($lump);
        [$width, , $left, $top] = VVideo::patchSize($patch);
        $tx -= $left * Defs::FRACUNIT;
        $x1 = ($renderer->centerxfrac + Compat::fixedMul($tx, $xscale)) >> Defs::FRACBITS;
        if ($x1 > $renderer->viewwidth) {
            return null;
        }
        $tx += $width * Defs::FRACUNIT;
        $x2 = (($renderer->centerxfrac + Compat::fixedMul($tx, $xscale)) >> Defs::FRACBITS) - 1;
        if ($x2 < 0) {
            return null;
        }
        $iscale = $xscale !== 0 ? Compat::fixedDiv(Defs::FRACUNIT, $xscale) : Defs::FRACUNIT;
        $xiscale = $flip !== 0 ? -$iscale : $iscale;
        $startfrac = $flip !== 0 ? ($width << Defs::FRACBITS) - 1 : 0;
        $visibleX1 = max($x1, 0);
        $visibleX2 = min($x2, $renderer->viewwidth - 1);
        if ($visibleX1 > $x1) {
            $startfrac += $xiscale * ($visibleX1 - $x1);
        }
        return [
            'mo' => $mobj,
            'patch' => $patch,
            'w' => $width,
            'scale' => $xscale << $renderer->detailshift,
            'gx' => $mobj->x,
            'gy' => $mobj->y,
            'gz' => $mobj->z,
            'gzt' => $mobj->z + $top * Defs::FRACUNIT,
            'texturemid' => $mobj->z + $top * Defs::FRACUNIT - $renderer->viewz,
            'x1' => $visibleX1,
            'x2' => $visibleX2,
            'xiscale' => $xiscale,
            'startfrac' => $startfrac,
        ];
    }

    private static function pointOnSegSide(int $x, int $y, mixed $line): int
    {
        $lx = $line->v1->x;
        $ly = $line->v1->y;
        $ldx = $line->v2->x - $lx;
        $ldy = $line->v2->y - $ly;
        if ($ldx === 0) {
            if ($x <= $lx) {
                return $ldy > 0 ? 1 : 0;
            }
            return $ldy < 0 ? 1 : 0;
        }
        if ($ldy === 0) {
            if ($y <= $ly) {
                return $ldx < 0 ? 1 : 0;
            }
            return $ldx > 0 ? 1 : 0;
        }
        $dx = $x - $lx;
        $dy = $y - $ly;
        $left = Compat::fixedMul($ldy >> Defs::FRACBITS, $dx);
        $right = Compat::fixedMul($dy, $ldx >> Defs::FRACBITS);
        return $right < $left ? 0 : 1;
    }

    /**
     * @param array<string,mixed> $sprite
     * @return array{0:int[],1:int[]}
     */
    private static function clipAgainstWalls(Renderer $renderer, array $sprite): array
    {
        $x1 = $sprite['x1'];
        $x2 = $sprite['x2'];
        $clipBottom = array_fill(0, Defs::SCREENWIDTH, -2);
        $clipTop = array_fill(0, Defs::SCREENWIDTH, -2);
        for ($d = count($renderer->drawsegs) - 1; $d >= 0; --$d) {
            $drawseg = $renderer->drawsegs[$d];
            if ($drawseg->x1 > $x2 || $drawseg->x2 < $x1) {
                continue;
            }
            if ($drawseg->silhouette === 0 && empty($drawseg->maskedtexturecol)) {
                continue;
            }
            $range1 = max($drawseg->x1, $x1);
            $range2 = min($drawseg->x2, $x2);
            $scale = max($drawseg->scale1, $drawseg->scale2);
            $lowScale = min($drawseg->scale1, $drawseg->scale2);
            $inFront = $scale < $sprite['scale']
                || (
                    $lowScale < $sprite['scale']
                    && $drawseg->curline !== null
                    && self::pointOnSegSide($sprite['gx'], $sprite['gy'], $drawseg->curline) === 0
                );
            if ($inFront) {
                if (!empty($drawseg->maskedtexturecol)) {
                    $renderer->renderMaskedSegRange($drawseg, $range1, $range2);
                }
                continue;
            }
            $silhouette = $drawseg->silhouette;
            if ($sprite['gz'] >= $drawseg->bsilheight) {
                $silhouette &= ~Defs::SIL_BOTTOM;
            }
            if ($sprite['gzt'] <= $drawseg->tsilheight) {
                $silhouette &= ~Defs::SIL_TOP;
            }
            for ($x = $range1; $x <= $range2; ++$x) {
                $i = $x - $drawseg->x1;
                if ($i < 0 || $i >= count($drawseg->sprtopclip)) {
                    continue;
                }
                if (($silhouette & Defs::SIL_BOTTOM) !== 0 && $clipBottom[$x] === -2) {
                    $clipBottom[$x] = $drawseg->sprbottomclip[$i];
                }
                if (($silhouette & Defs::SIL_TOP) !== 0 && $clipTop[$x] === -2) {
                    $clipTop[$x] = $drawseg->sprtopclip[$i];
                }
            }
        }
        for ($x = $x1; $x <= $x2; ++$x) {
            if ($clipBottom[$x] === -2) {
                $clipBottom[$x] = $renderer->viewheight;
            }
            if ($clipTop[$x] === -2) {
                $clipTop[$x] = -1;
            }
        }
        return [$clipTop, $clipBottom];
    }

    /** @param int[] $fb */
    public static function drawPsprite(
        Renderer $renderer,
        array &$fb,
        string $patch,
        int $sx,
        int $sy,
    ): void {
        [$width, , $left, $top] = VVideo::patchSize($patch);
        $tx = $sx - 160 * Defs::FRACUNIT;
        $tx -= $left * Defs::FRACUNIT;
        $x1 = ($renderer->centerxfrac + Compat::fixedMul($tx, $renderer->pspritescale))
            >> Defs::FRACBITS;
        if ($x1 > $renderer->viewwidth) {
            return;
        }
        $tx += $width * Defs::FRACUNIT;
        $x2 = (($renderer->centerxfrac + Compat::fixedMul($tx, $renderer->pspritescale))
            >> Defs::FRACBITS) - 1;
        if ($x2 < 0) {
            return;
        }
        $visibleX1 = max($x1, 0);
        $visibleX2 = min($x2, $renderer->viewwidth - 1);
        $startfrac = $visibleX1 > $x1 ? $renderer->pspriteiscale * ($visibleX1 - $x1) : 0;
        $texturemid = self::BASEYCENTER * Defs::FRACUNIT + intdiv(Defs::FRACUNIT, 2)
            - ($sy - $top * Defs::FRACUNIT);

        self::drawSprite(
            $renderer,
            $fb,
            [
                'patch' => $patch,
                'w' => $width,
                // LOW detail keeps weapon height; DrawColumnLow doubles horizontal pixels.
                'scale' => $renderer->pspritescale << $renderer->detailshift,
                'texturemid' => $texturemid,
                'x1' => $visibleX1,
                'x2' => $visibleX2,
                'xiscale' => $renderer->pspriteiscale,
                'startfrac' => $startfrac,
            ],
            false
        );
    }

    /** @return array{0:int,1:int} */
    public static function weaponPspriteXY(Player $player, int $leveltime): array
    {
        $state = $player->pspriteState;
        if ($state === 'up' || $state === 'down') {
            return [Defs::FRACUNIT, $player->pspriteSy];
        }
        if ($state === 'atk') {
            return [Defs::FRACUNIT, $player->pspriteSy ?: self::WEAPONTOP];
        }
        $bob = $player->bob;
        $angle = (128 * $leveltime) & Defs::FINEMASK;
        $sx = Defs::FRACUNIT + Compat::fixedMul(
            $bob,
            Tables::$finesine[($angle + intdiv(Defs::FINEANGLES, 4)) % count(Tables::$finesine)]
        );
        $angle &= intdiv(Defs::FINEANGLES, 2) - 1;
        $sy = self::WEAPONTOP + Compat::fixedMul($bob, Tables::$finesine[$angle]);
        $player->pspriteSy = $sy;
        return [$sx, $sy];
    }

    /**
     * @param int[] $fb
     * @param array<string,mixed> $sprite
     */
    private static function drawSprite(
        Renderer $renderer,
        array &$fb,
        array $sprite,
        bool $clipWalls = true,
    ): void {
        $patch = $sprite['patch'];
        $patchWidth = $sprite['w'];
        $iscale = $sprite['xiscale'];
        $spryscale = $sprite['scale'];
        // R_DrawVisSprite: dc_iscale = abs(xiscale) >> detailshift.
        $yIscale = abs($iscale) >> $renderer->detailshift;
        $yIscale = max(1, $yIscale);
        $sprTopScreen = $renderer->centeryfrac
            - Compat::fixedMul($sprite['texturemid'], $spryscale);
        if ($clipWalls) {
            [$clipTop, $clipBottom] = self::clipAgainstWalls($renderer, $sprite);
        } else {
            $clipTop = array_fill(0, Defs::SCREENWIDTH, -1);
            $clipBottom = array_fill(0, Defs::SCREENWIDTH, $renderer->viewheight);
        }
        $columnOffsets = [];
        for ($column = 0; $column < max(1, $patchWidth); ++$column) {
            $columnOffsets[$column] = Bin::u32($patch, 8 + $column * 4);
        }
        $colormap = $renderer->res->colormap(0);
        $patchLength = strlen($patch);
        $frac = $sprite['startfrac'];
        for ($x = $sprite['x1']; $x <= $sprite['x2']; ++$x) {
            $columnNumber = $frac >> Defs::FRACBITS;
            if ($columnNumber >= 0 && $columnNumber < $patchWidth) {
                $column = $columnOffsets[$columnNumber];
                while ($column < $patchLength) {
                    $topDelta = ord($patch[$column]);
                    if ($topDelta === 0xFF) {
                        break;
                    }
                    $length = ord($patch[$column + 1]);
                    $source = $column + 3;
                    $topScreen = $sprTopScreen + $spryscale * $topDelta;
                    $bottomScreen = $topScreen + $spryscale * $length;
                    $yl = ($topScreen + Defs::FRACUNIT - 1) >> Defs::FRACBITS;
                    $yh = ($bottomScreen - 1) >> Defs::FRACBITS;
                    $yl = max($yl, $clipTop[$x] + 1, 0);
                    $yh = min($yh, $clipBottom[$x] - 1, $renderer->viewheight - 1);
                    if ($yl <= $yh) {
                        $texfrac = Compat::fixedMul(
                            ($yl << Defs::FRACBITS) - $topScreen,
                            $yIscale
                        );
                        $texfrac = max(0, $texfrac);
                        for ($y = $yl; $y <= $yh; ++$y) {
                            $index = $texfrac >> Defs::FRACBITS;
                            if ($index >= 0 && $index < $length) {
                                $pixel = ord($patch[$source + $index]);
                                $value = $pixel < strlen($colormap) ? ord($colormap[$pixel]) : $pixel;
                                if ($renderer->detailshift !== 0) {
                                    $xx = $x << 1;
                                    $offset = $renderer->ylookup[$y] + $renderer->columnofs[$xx];
                                    $fb[$offset] = $value;
                                    $fb[$offset + 1] = $value;
                                } else {
                                    $fb[$renderer->ylookup[$y] + $renderer->columnofs[$x]] = $value;
                                }
                            }
                            $texfrac += $yIscale;
                        }
                    }
                    $column += $length + 4;
                }
            }
            $frac += $iscale;
        }
    }
}
