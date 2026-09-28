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

final class VerticalDoor { public bool $dead=false; public function __construct(public Sector $sector,public int $type,public int $direction,public int $topheight,public int $speed,public int $topwait,public int $topcountdown=0){} }
final class Plat { public bool $dead=false; public function __construct(public Sector $sector,public int $type,public int $status,public int $speed,public int $low,public int $high,public int $wait,public int $count=0){} }
final class FloorMove { public bool $dead=false; public function __construct(public Sector $sector,public int $direction,public int $dest,public int $speed){} }
final class CeilingMove { public bool $dead=false; public function __construct(public Sector $sector,public int $direction,public int $dest,public int $speed){} }
final class Button { public function __construct(public Line $line,public string $where,public int $texture,public int $timer){} }

final class Specials
{
    public array $thinkers=[]; public array $buttons=[]; public bool $exitRequested=false; public bool $secretExit=false; public array $switchMap=[];
    private const SWITCH_PAIRS=[
        ['SW1BRCOM','SW2BRCOM'],['SW1BRN1','SW2BRN1'],['SW1BRN2','SW2BRN2'],['SW1BRNGN','SW2BRNGN'],['SW1BROWN','SW2BROWN'],
        ['SW1COMM','SW2COMM'],['SW1COMP','SW2COMP'],['SW1DIRT','SW2DIRT'],['SW1EXIT','SW2EXIT'],['SW1GRAY','SW2GRAY'],
        ['SW1GRAY1','SW2GRAY1'],['SW1METAL','SW2METAL'],['SW1PIPE','SW2PIPE'],['SW1SLAD','SW2SLAD'],['SW1STARG','SW2STARG'],
        ['SW1STON1','SW2STON1'],['SW1STON2','SW2STON2'],['SW1STONE','SW2STONE'],['SW1STRTN','SW2STRTN'],['SW1BLUE','SW2BLUE'],
        ['SW1CMT','SW2CMT'],['SW1GARG','SW2GARG'],['SW1GSTON','SW2GSTON'],['SW1HOT','SW2HOT'],['SW1LION','SW2LION'],
        ['SW1SATYR','SW2SATYR'],['SW1SKIN','SW2SKIN'],['SW1VINE','SW2VINE'],['SW1WOOD','SW2WOOD'],['SW1PANEL','SW2PANEL'],
        ['SW1ROCK','SW2ROCK'],['SW1MET2','SW2MET2'],['SW1WDMET','SW2WDMET'],['SW1BRIK','SW2BRIK'],['SW1MOD1','SW2MOD1'],
        ['SW1ZIM','SW2ZIM'],['SW1STON6','SW2STON6'],['SW1TEK','SW2TEK'],['SW1MARB','SW2MARB'],['SW1SKULL','SW2SKULL'],
    ];
    public function __construct(public World $world,public object $res,public object $sound)
    {
        foreach(self::SWITCH_PAIRS as[$a,$b]){$ia=$res->textureNumForName($a);$ib=$res->textureNumForName($b);if($ia||$ib){$this->switchMap[$ia]=$ib;$this->switchMap[$ib]=$ia;}}
    }
    /** T_MovePlane + P_ChangeSector (p_floor.prg): move the plane then carry things with it. */
    private function movePlane(Sector $s, int $speed, int $dest, int $plane, int $dir, bool $crush = false): int
    {
        $prop = $plane ? 'ceilingheight' : 'floorheight';
        $last = $s->$prop;
        $past = false;
        if ($dir === -1) {
            if ($last - $speed < $dest) {
                $s->$prop = $dest;
                $past = true;
            } else {
                $s->$prop -= $speed;
            }
        } elseif ($last + $speed > $dest) {
            $s->$prop = $dest;
            $past = true;
        } else {
            $s->$prop += $speed;
        }
        $nofit = Collision::changeSector($this->world, $s, $crush);
        if ($nofit) {
            if (!$crush || $past) {
                $s->$prop = $last;
                Collision::changeSector($this->world, $s, $crush);
            }
            return $past ? Defs::RESULT_PASTDEST : Defs::RESULT_CRUSHED;
        }
        return $past ? Defs::RESULT_PASTDEST : Defs::RESULT_OK;
    }
    public static function surroundingSectors(Sector $s):array
    {
        $out=[];foreach($s->lines as$ln){$o=$ln->frontsector===$s?$ln->backsector:$ln->frontsector;if($o&&$o!==$s&&!in_array($o,$out,true))$out[]=$o;}return$out;
    }
    public static function lowestCeiling(Sector $s):int{$h=0x7fffffff;foreach(self::surroundingSectors($s)as$o)$h=min($h,$o->ceilingheight);return$h===0x7fffffff?$s->ceilingheight:$h;}
    public static function lowestFloor(Sector $s):int{$h=$s->floorheight;foreach(self::surroundingSectors($s)as$o)$h=min($h,$o->floorheight);return$h;}
    public static function highestFloor(Sector $s):int{$h=-500*Defs::FRACUNIT;foreach(self::surroundingSectors($s)as$o)$h=max($h,$o->floorheight);return$h;}
    public static function nextHighestFloor(Sector $s,int $cur):int{$h=0x7fffffff;foreach(self::surroundingSectors($s)as$o)if($o->floorheight>$cur)$h=min($h,$o->floorheight);return$h===0x7fffffff?$cur:$h;}
    public function sectorsFromTag(int $tag):array{return$tag?array_values(array_filter($this->world->sectors,fn($s)=>$s->tag===$tag)):[];}

    public function tick():void
    {
        $alive=[];foreach($this->thinkers as$t){if($t->dead)continue;if($t instanceof VerticalDoor)$this->tickDoor($t);elseif($t instanceof Plat)$this->tickPlat($t);elseif($t instanceof FloorMove)$this->tickFloor($t);elseif($t instanceof CeilingMove)$this->tickCeiling($t);if(!$t->dead)$alive[]=$t;}$this->thinkers=$alive;
        foreach($this->buttons as$i=>$b)if(--$b->timer<=0){$side=$b->line->sides[0];if($side){$prop=$b->where.'texture';$side->$prop=$b->texture;}unset($this->buttons[$i]);}$this->buttons=array_values($this->buttons);
    }
    private function tickDoor(VerticalDoor $d):void
    {
        if($d->direction===0){if(--$d->topcountdown<=0){if(in_array($d->type,[Defs::VLD_NORMAL,Defs::VLD_BLAZERAISE],true)){$d->direction=-1;$this->sound->play($d->type===Defs::VLD_NORMAL?'dorcls':'bdcls');}elseif($d->type===Defs::VLD_CLOSE30){$d->direction=1;$this->sound->play('doropn');}}return;}
        $dest=$d->direction===1?$d->topheight:$d->sector->floorheight;if($this->movePlane($d->sector,$d->speed,$dest,1,$d->direction)!==Defs::RESULT_PASTDEST)return;
        if($d->direction===1){if(in_array($d->type,[Defs::VLD_NORMAL,Defs::VLD_BLAZERAISE],true)){$d->direction=0;$d->topcountdown=$d->topwait;}else{$d->sector->specialdata=null;$d->dead=true;}}
        elseif($d->type===Defs::VLD_CLOSE30){$d->direction=0;$d->topcountdown=Defs::TICRATE*30;}else{$d->sector->specialdata=null;$d->dead=true;}
    }
    private function tickPlat(Plat $p):void
    {
        if($p->status===Defs::PLAT_WAITING){if(--$p->count<=0){$p->status=$p->sector->floorheight<=$p->low?Defs::PLAT_UP:Defs::PLAT_DOWN;$this->sound->play('pstart');}return;}
        $up=$p->status===Defs::PLAT_UP;if($this->movePlane($p->sector,$p->speed,$up?$p->high:$p->low,0,$up?1:-1)!==Defs::RESULT_PASTDEST)return;
        if(!$up){$p->status=Defs::PLAT_WAITING;$p->count=$p->wait;$this->sound->play('pstop');}else{$p->sector->specialdata=null;$p->dead=true;$this->sound->play('pstop');}
    }
    private function tickFloor(FloorMove $f):void{if($this->movePlane($f->sector,$f->speed,$f->dest,0,$f->direction)===Defs::RESULT_PASTDEST){$f->sector->specialdata=null;$f->dead=true;}}
    private function tickCeiling(CeilingMove $c):void{if($this->movePlane($c->sector,$c->speed,$c->dest,1,$c->direction)===Defs::RESULT_PASTDEST){$c->sector->specialdata=null;$c->dead=true;}}
    private function spawnDoor(Sector $s,int $type,bool $reverse=false):bool
    {
        if($s->specialdata){if($s->specialdata instanceof VerticalDoor&&in_array($type,[Defs::VLD_NORMAL,Defs::VLD_BLAZERAISE],true)){$s->specialdata->direction=$s->specialdata->direction===-1?1:-1;return true;}return false;}
        $close=in_array($type,[Defs::VLD_CLOSE,Defs::VLD_BLAZECLOSE,Defs::VLD_CLOSE30],true);$d=new VerticalDoor($s,$type,$reverse||$close?-1:1,self::lowestCeiling($s)-4*Defs::FRACUNIT,Defs::VDOORSPEED*($type>=Defs::VLD_BLAZERAISE?4:1),Defs::VDOORWAIT);
        if($type===Defs::VLD_CLOSE30)$d->topheight=$s->ceilingheight;$s->specialdata=$d;$this->thinkers[]=$d;
        $this->sound->play($d->direction===1?($type<Defs::VLD_BLAZERAISE?'doropn':'bdopn'):($type<Defs::VLD_BLAZERAISE?'dorcls':'bdcls'));return true;
    }
    public function doDoor(Line $line,int $type,bool $reverse=false):bool{$ok=false;foreach($this->sectorsFromTag($line->tag)as$s)if($this->spawnDoor($s,$type,$reverse))$ok=true;return$ok;}
    public function verticalDoor(Line $line,Mobj $thing):void
    {
        $p=$thing->player;$sp=$line->special;$locks=[[26,32,Defs::IT_BLUECARD,Defs::IT_BLUESKULL,'blue'],[27,34,Defs::IT_YELLOWCARD,Defs::IT_YELLOWSKULL,'yellow'],[28,33,Defs::IT_REDCARD,Defs::IT_REDSKULL,'red']];
        foreach($locks as[$a,$b,$card,$skull,$name])if(in_array($sp,[$a,$b],true)&&$p&&!($p->cards[$card]||$p->cards[$skull])){$p->message="You need a $name key to open this door";$this->sound->play('oof');return;}
        $s=$line->sides[1]?->sector;if(!$s)return;
        if(in_array($sp,[1,26,27,28],true))$type=Defs::VLD_NORMAL;elseif(in_array($sp,[31,32,33,34],true)){$type=Defs::VLD_OPEN;$line->special=0;}elseif($sp===117)$type=Defs::VLD_BLAZERAISE;elseif($sp===118){$type=Defs::VLD_OPEN;$line->special=0;}else$type=Defs::VLD_NORMAL;
        $this->spawnDoor($s,$type);
    }
    public function doPlatDwus(Line $line,bool $blaze=false):bool
    {
        $ok=false;foreach($this->sectorsFromTag($line->tag)as$s){if($s->specialdata)continue;$p=new Plat($s,$blaze?Defs::PLAT_BLAZEDWUS:Defs::PLAT_DWUS,Defs::PLAT_DOWN,Defs::PLATSPEED*($blaze?8:1),self::lowestFloor($s),$s->floorheight,Defs::PLATWAIT*Defs::TICRATE);if($p->low===$p->high)$p->low=$p->high-8*Defs::FRACUNIT;$s->specialdata=$p;$this->thinkers[]=$p;$this->sound->play('pstart');$ok=true;}return$ok;
    }
    public function doFloor(Line $line,callable $dest,int $dir):bool{$ok=false;foreach($this->sectorsFromTag($line->tag)as$s){if($s->specialdata)continue;$f=new FloorMove($s,$dir,$dest($s),Defs::FLOORSPEED);$s->specialdata=$f;$this->thinkers[]=$f;$ok=true;}return$ok;}
    public function doCeiling(Line $line,callable $dest,int $dir=-1,?int $speed=null):bool{$ok=false;foreach($this->sectorsFromTag($line->tag)as$s){if($s->specialdata)continue;$c=new CeilingMove($s,$dir,$dest($s),$speed??Defs::CEILSPEED);$s->specialdata=$c;$this->thinkers[]=$c;$ok=true;}return$ok;}
    public function doStairs(Line $line,int $step,int $speed):bool
    {
        $ok=false;foreach($this->sectorsFromTag($line->tag)as$s){if($s->specialdata)continue;$height=$s->floorheight+$step;$f=new FloorMove($s,1,$height,$speed);$s->specialdata=$f;$this->thinkers[]=$f;$ok=true;$texture=$s->floorpic;$cur=$s;
            while(true){$next=null;foreach($cur->lines as$ln){if(!($ln->flags&Defs::ML_TWOSIDED))continue;$o=$ln->frontsector===$cur?$ln->backsector:$ln->frontsector;if($o&&$o!==$cur&&$o->floorpic===$texture&&!$o->specialdata){$next=$o;break;}}if(!$next)break;$height+=$step;$f=new FloorMove($next,1,$height,$speed);$next->specialdata=$f;$this->thinkers[]=$f;$cur=$next;}}return$ok;
    }
    public function changeSwitch(Line $line,int $again):void
    {
        $side=$line->sides[0];if(!$side)return;if(!$again)$line->special=0;$sound=$line->special===11?'swtchx':'swtchn';
        foreach(['top','mid','bottom']as$where){$prop=$where.'texture';$tex=$side->$prop;if(array_key_exists($tex,$this->switchMap)){if($again)$this->buttons[]=new Button($line,$where,$tex,Defs::BUTTONTIME);$side->$prop=$this->switchMap[$tex];$this->sound->play($sound);return;}}$this->sound->play($sound);
    }
    public function useSpecial(Line $line,Mobj $thing,int $side):void
    {
        if($side!==0)return;$sp=$line->special;if(in_array($sp,[1,26,27,28,31,32,33,34,117,118],true)){$this->verticalDoor($line,$thing);return;}
        if($sp===11||$sp===51){$this->changeSwitch($line,0);$this->exitRequested=true;if($sp===51)$this->secretExit=true;return;}
        $once=[29=>fn()=>$this->doDoor($line,Defs::VLD_NORMAL),50=>fn()=>$this->doDoor($line,Defs::VLD_CLOSE),103=>fn()=>$this->doDoor($line,Defs::VLD_OPEN),111=>fn()=>$this->doDoor($line,Defs::VLD_BLAZERAISE),112=>fn()=>$this->doDoor($line,Defs::VLD_BLAZEOPEN),113=>fn()=>$this->doDoor($line,Defs::VLD_BLAZECLOSE),21=>fn()=>$this->doPlatDwus($line),122=>fn()=>$this->doPlatDwus($line,true),
            18=>fn()=>$this->doFloor($line,fn($s)=>self::nextHighestFloor($s,$s->floorheight),1),23=>fn()=>$this->doFloor($line,[self::class,'lowestFloor'],-1),71=>fn()=>$this->doFloor($line,[self::class,'highestFloor'],-1),101=>fn()=>$this->doFloor($line,fn($s)=>self::nextHighestFloor($s,$s->floorheight),1),102=>fn()=>$this->doFloor($line,fn($s)=>$s->floorheight-8*Defs::FRACUNIT,-1),7=>fn()=>$this->doStairs($line,8*Defs::FRACUNIT,intdiv(Defs::FLOORSPEED,4)),127=>fn()=>$this->doStairs($line,16*Defs::FRACUNIT,Defs::FLOORSPEED*4),41=>fn()=>$this->doCeiling($line,fn($s)=>$s->floorheight),49=>fn()=>$this->doCeiling($line,fn($s)=>$s->floorheight+8*Defs::FRACUNIT),14=>fn()=>$this->doPlatDwus($line),15=>fn()=>$this->doPlatDwus($line),20=>fn()=>$this->doPlatDwus($line)];
        $repeat=[42=>fn()=>$this->doDoor($line,Defs::VLD_CLOSE),61=>fn()=>$this->doDoor($line,Defs::VLD_OPEN),63=>fn()=>$this->doDoor($line,Defs::VLD_NORMAL),62=>fn()=>$this->doPlatDwus($line),114=>fn()=>$this->doDoor($line,Defs::VLD_BLAZERAISE),115=>fn()=>$this->doDoor($line,Defs::VLD_BLAZEOPEN),116=>fn()=>$this->doDoor($line,Defs::VLD_BLAZECLOSE),120=>fn()=>$this->doPlatDwus($line,true),45=>fn()=>$this->doFloor($line,fn($s)=>$s->floorheight-8*Defs::FRACUNIT,-1),60=>fn()=>$this->doFloor($line,[self::class,'lowestFloor'],-1),64=>fn()=>$this->doFloor($line,fn($s)=>self::nextHighestFloor($s,$s->floorheight),1),70=>fn()=>$this->doFloor($line,[self::class,'highestFloor'],-1),43=>fn()=>$this->doCeiling($line,fn($s)=>$s->floorheight)];
        if(isset($once[$sp])){if($once[$sp]())$this->changeSwitch($line,0);}elseif(isset($repeat[$sp])&&$repeat[$sp]())$this->changeSwitch($line,1);
    }
    public function crossSpecial(Line $line,int $side,Mobj $thing):void
    {
        $sp=$line->special;$clear=true;
        if($sp===2)$this->doDoor($line,Defs::VLD_OPEN);elseif($sp===3)$this->doDoor($line,Defs::VLD_CLOSE);elseif($sp===4)$this->doDoor($line,Defs::VLD_NORMAL);
        elseif($sp===5)$this->doFloor($line,fn($s)=>self::nextHighestFloor($s,$s->floorheight),1);elseif($sp===10)$this->doPlatDwus($line);elseif($sp===16)$this->doDoor($line,Defs::VLD_CLOSE30,true);
        elseif($sp===19)$this->doFloor($line,fn($s)=>$s->floorheight-8*Defs::FRACUNIT,-1);elseif($sp===36)$this->doFloor($line,[self::class,'highestFloor'],-1);elseif($sp===38)$this->doFloor($line,[self::class,'lowestFloor'],-1);
        elseif($sp===39){$this->teleport($line,$side,$thing);$clear=false;}
        elseif($sp===52){$this->exitRequested=true;$clear=false;}elseif($sp===88){$this->doPlatDwus($line);$clear=false;}elseif($sp===86){$this->doDoor($line,Defs::VLD_OPEN);$clear=false;}elseif($sp===90){$this->doDoor($line,Defs::VLD_NORMAL);$clear=false;}
        elseif($sp===105){$this->doDoor($line,Defs::VLD_BLAZERAISE);$clear=false;}elseif($sp===106){$this->doDoor($line,Defs::VLD_BLAZEOPEN);$clear=false;}elseif($sp===107){$this->doDoor($line,Defs::VLD_BLAZECLOSE);$clear=false;}
        elseif($sp===120){$this->doPlatDwus($line,true);$clear=false;}elseif($sp===121)$this->doPlatDwus($line,true);elseif($sp===124){$this->exitRequested=$this->secretExit=true;$clear=false;}else$clear=false;
        if($clear)$line->special=0;
    }

    /** EV_Teleport (p_telept.prg): walk special 39 onto MT_TELEPORTMAN (thing 14). */
    private function teleport(Line $line, int $side, Mobj $thing): void
    {
        if ($side === 1 || ($thing->flags & Defs::MF_MISSILE) !== 0) {
            return;
        }
        $tag = $line->tag;
        foreach ($this->world->sectors as $i => $sector) {
            if ($sector->tag !== $tag) {
                continue;
            }
            foreach ($this->world->mobjs as $dest) {
                if ($dest->type !== 14) {
                    continue;
                }
                $destSector = Collision::pointInSubsector($this->world, $dest->x, $dest->y)->sector;
                if ($destSector !== $sector && $destSector->iSector !== $i) {
                    continue;
                }
                $thing->momx = $thing->momy = $thing->momz = 0;
                $thing->x = $dest->x;
                $thing->y = $dest->y;
                $ss = Collision::pointInSubsector($this->world, $thing->x, $thing->y);
                $thing->floorz = $ss->sector->floorheight;
                $thing->ceilingz = $ss->sector->ceilingheight;
                $thing->z = $thing->floorz;
                $thing->angle = $dest->angle;
                if ($thing->player !== null) {
                    $thing->player->viewz = $thing->z + $thing->player->viewheight;
                    $thing->reactiontime = 18;
                }
                $this->sound->play('telept');
                return;
            }
        }
    }
}
