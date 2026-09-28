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

/** Constants from doomdef/doomdata/tables/m_fixed (0-based, like C). */
final class Defs
{
    public const FRACBITS = 16;
    public const FRACUNIT = 65536;
    public const SCREENWIDTH = 320;
    public const SCREENHEIGHT = 200;
    public const SBARHEIGHT = 32;
    public const FINEANGLES = 8192;
    public const FINEMASK = self::FINEANGLES - 1;
    public const ANGLETOFINESHIFT = 19;
    public const ANG45 = 0x20000000;
    public const ANG90 = 0x40000000;
    public const ANG180 = 0x80000000;
    public const ANG270 = 0xC0000000;
    public const ANG_MAX = 0xFFFFFFFF;
    public const SLOPERANGE = 2048;
    public const SLOPEBITS = 11;
    public const DBITS = self::FRACBITS - self::SLOPEBITS;
    public const FIELDOFVIEW = 2048;
    public const ML_BLOCKING = 1;
    public const ML_BLOCKMONSTERS = 2;
    public const ML_TWOSIDED = 4;
    public const ML_DONTPEGTOP = 8;
    public const ML_DONTPEGBOTTOM = 16;
    public const ML_SECRET = 32;
    public const ML_SOUNDBLOCK = 64;
    public const ML_DONTDRAW = 128;
    public const ML_MAPPED = 256;
    public const SIL_NONE = 0;
    public const SIL_TOP = 1;
    public const SIL_BOTTOM = 2;
    public const SIL_BOTH = 3;
    public const LIGHTLEVELS = 16;
    public const LIGHTZSHIFT = 20;
    public const MAXLIGHTZ = 128;
    public const LIGHTSCALESHIFT = 12;
    public const MAXLIGHTSCALE = 48;
    public const NUMCOLORMAPS = 32;
    public const MAXDRAWSEGS = 256;
    public const PU_STATIC = 1;
    public const PU_LEVEL = 5;
    public const PU_CACHE = 8;
    public const MAPVERTEX_SIZE = 4;
    public const MAPSEG_SIZE = 12;
    public const MAPSUBSECTOR_SIZE = 4;
    public const MAPSECTOR_SIZE = 26;
    public const MAPNODE_SIZE = 28;
    public const MAPTHING_SIZE = 10;
    public const MAPLINEDEF_SIZE = 14;
    public const MAPSIDEDEF_SIZE = 30;
    public const PLAYER_RADIUS = 16 * self::FRACUNIT;
    public const PLAYER_HEIGHT = 56 * self::FRACUNIT;
    public const VIEWHEIGHT = 41 * self::FRACUNIT;
    public const MAXMOVE = 30 * self::FRACUNIT;
    public const USERANGE = 64 * self::FRACUNIT;
    public const MELEERANGE = 64 * self::FRACUNIT;
    public const MISSILERANGE = 32 * 64 * self::FRACUNIT;
    public const MAXSTEP = 24 * self::FRACUNIT;
    public const MAXDROP = 24 * self::FRACUNIT;
    public const GRAVITY = self::FRACUNIT;
    public const STOPSPEED = 0x1000;
    public const FRICTION = 0xE800;
    public const MAXBOB = 0x100000;
    public const VDOORSPEED = 2 * self::FRACUNIT;
    public const VDOORWAIT = 150;
    public const PLATSPEED = self::FRACUNIT;
    public const PLATWAIT = 3;
    public const FLOORSPEED = self::FRACUNIT;
    public const CEILSPEED = self::FRACUNIT;
    public const BUTTONTIME = 35;
    public const BOXLEFT = 0;
    public const BOXRIGHT = 1;
    public const BOXBOTTOM = 2;
    public const BOXTOP = 3;
    public const NF_SUBSECTOR = 0x8000;
    public const MF_SPECIAL = 1;
    public const MF_SOLID = 2;
    public const MF_SHOOTABLE = 4;
    public const MF_NOSECTOR = 8;
    public const MF_NOBLOCKMAP = 16;
    public const MF_AMBUSH = 32;
    public const MTF_AMBUSH = 8;
    public const MF_DROPOFF = 0x400;
    public const MF_PICKUP = 0x800;
    public const MF_NOCLIP = 0x1000;
    public const MF_FLOAT = 0x4000;
    public const MF_NOGRAVITY = 0x200;
    public const MF_MISSILE = 0x10000;
    public const MF_CORPSE = 0x40000;
    public const MF_COUNTKILL = 0x400000;
    public const MF_COUNTITEM = 0x800000;
    public const CF_NOCLIP = 1;
    public const CF_GODMODE = 2;
    public const BT_ATTACK = 1;
    public const BT_USE = 2;
    public const BT_CHANGE = 4;
    public const BT_WEAPONMASK = 8 + 16 + 32;
    public const BT_WEAPONSHIFT = 3;
    public const PST_LIVE = 0;
    public const PST_DEAD = 1;
    public const PST_REBORN = 2;
    public const GS_TITLE = 0;
    public const GS_LEVEL = 1;
    public const GS_INTERMISSION = 2;
    public const GS_FINALE = 3;
    public const SK_BABY = 0;
    public const SK_EASY = 1;
    public const SK_MEDIUM = 2;
    public const SK_HARD = 3;
    public const SK_NIGHTMARE = 4;
    public const IT_BLUECARD = 0;
    public const IT_YELLOWCARD = 1;
    public const IT_REDCARD = 2;
    public const IT_BLUESKULL = 3;
    public const IT_YELLOWSKULL = 4;
    public const IT_REDSKULL = 5;
    public const AM_CLIP = 0;
    public const AM_SHELL = 1;
    public const AM_CELL = 2;
    public const AM_MISL = 3;
    public const WP_FIST = 0;
    public const WP_PISTOL = 1;
    public const WP_SHOTGUN = 2;
    public const WP_CHAINGUN = 3;
    public const WP_MISSILE = 4;
    public const WP_PLASMA = 5;
    public const WP_BFG = 6;
    public const WP_CHAINSAW = 7;
    public const WP_SUPERSHOTGUN = 8;
    public const WP_NOCHANGE = 10;
    public const MAXHEALTH = 100;
    public const MAXARMOR = 200;
    public const RESULT_OK = 0;
    public const RESULT_CRUSHED = 1;
    public const RESULT_PASTDEST = 2;
    public const VLD_NORMAL = 0;
    public const VLD_CLOSE30 = 1;
    public const VLD_CLOSE = 2;
    public const VLD_OPEN = 3;
    public const VLD_RAISEIN5 = 4;
    public const VLD_BLAZERAISE = 5;
    public const VLD_BLAZEOPEN = 6;
    public const VLD_BLAZECLOSE = 7;
    public const PLAT_DOWN = 0;
    public const PLAT_UP = 1;
    public const PLAT_WAITING = 2;
    public const PLAT_DWUS = 0;
    public const PLAT_PERPETUAL = 1;
    public const PLAT_BLAZEDWUS = 2;
    public const TICRATE = 35;
    public const HU_FONTSTART = 33;
    public const HU_FONTEND = 95;
    public const HU_FONTSIZE = self::HU_FONTEND - self::HU_FONTSTART + 1;
    public const SAVESTRINGSIZE = 24;
    public const SAVEGAMENAME = 'doomsav';
    public const LOADSAVEEMPTY = 'empty slot';
}
