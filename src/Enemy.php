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

final class Enemy
{
    private const DI_EAST=0,DI_NE=1,DI_NORTH=2,DI_NW=3,DI_WEST=4,DI_SW=5,DI_SOUTH=6,DI_SE=7,DI_NODIR=8;
    private const XSPEED=[Defs::FRACUNIT,47000,0,-47000,-Defs::FRACUNIT,-47000,0,47000];
    private const YSPEED=[0,47000,Defs::FRACUNIT,47000,0,-47000,-Defs::FRACUNIT,-47000];
    private const OPPOSITE=[4,5,6,7,0,1,2,3,8];
    private const DIAGS=[self::DI_NW,self::DI_NE,self::DI_SW,self::DI_SE];
    private static int $seed=1;

    private static function profiles():array{return[
        3004=>[8,'hitscan','posit1','podth1','pistol',7,5,4],9=>[8,'shotgun','posit2','podth2','shotgn',7,5,3],
        3001=>[8,'imp','bgsit1','bgdth1','claw',7,6,3],3002=>[10,'melee','sgtsit','sgtdth','sgtatk',7,6,2],
        58=>[10,'melee','sgtsit','sgtdth','sgtatk',7,6,2],3003=>[8,'baron','brssit','brsdth','claw',7,7,3],
        3005=>[8,'caco','cacsit','cacdth','claw',5,6,3],3006=>[8,'melee','sklatk','firxpl','sklatk',5,6,3],
        16=>[16,'hitscan','cybsit','cybdth','pistol',5,9,3],7=>[12,'shotgun','spisit','spidth','shotgn',5,10,3],
        68=>[12,'hitscan','bspsit','bspdth','plasma',5,7,3],69=>[8,'baron','kntsit','kntdth','claw',7,7,3],
        84=>[8,'hitscan','posit1','podth1','pistol',7,5,3],2035=>[0,'none',null,'barexp',null,0,5,4],
    ];}
    private static function random():int{self::$seed=(self::$seed*1103515245+12345)&0x7fffffff;return(self::$seed>>16)&255;}
    private static function walkTics(Mobj $mo):int{return self::profiles()[$mo->type][7]??3;}

    public static function tickEnemies(World $world,object $game):void
    {
        $p=$game->player;if(!$p||!$p->mo)return;
        foreach(array_values($world->mobjs)as$mo){if($mo===$p->mo)continue;if($mo->flags&Defs::MF_MISSILE){self::tickMissile($world,$mo,$game);continue;}
            if(!$mo->info||$mo->info[0]!=='enemy')continue;if($mo->health<=0){self::tickDead($world,$mo);continue;}$prof=self::profiles()[$mo->type]??null;if($prof&&$prof[1]==='none')continue;
            if($mo->aiState==='attack')self::tickAttack($world,$mo,$game);elseif($mo->aiState==='chase'){if($mo->chaseTics>0)--$mo->chaseTics;else{self::chase($world,$mo,$p->mo,$game);if($mo->aiState==='chase')$mo->chaseTics=max(0,self::walkTics($mo)-1);}}
            else{self::tickStand($mo);self::look($world,$mo,$p->mo,$game);}
        }
    }
    private static function tickStand(Mobj $mo):void{if($mo->tics>0){--$mo->tics;return;}$mo->frame=($mo->frame+1)%($mo->type===3005?1:2);$mo->tics=10;}
    public static function killMonster(Mobj $mo,object $game,?Mobj $source):void
    {
        $mo->health=0;$mo->flags&=~(Defs::MF_SOLID|Defs::MF_SHOOTABLE);$mo->flags|=Defs::MF_CORPSE;$mo->target=null;$mo->aiState='die';$prof=self::profiles()[$mo->type]??null;
        if($prof){[, $atk,, $sfx,, $d0]=$prof;$mo->frame=$d0;$mo->tics=5;if($atk==='none'){$mo->sprite='BEXP';$mo->frame=0;}if($sfx)$game->startSound($sfx);if($mo->type===2035)self::explodeBarrel($game->world,$mo,$game);}
        else{$mo->frame=7;$mo->tics=5;$game->startSound('podth1');}if($source?->player)++$source->player->killcount;
    }
    private static function tickDead(World $world,Mobj $mo):void
    {
        if($mo->aiState!=='die')return;if($mo->tics>0){--$mo->tics;return;}$prof=self::profiles()[$mo->type]??null;[$d0,$dn]=$prof?[$prof[5],$prof[6]]:[7,5];if($mo->sprite==='BEXP')[$d0,$dn]=[0,5];
        if($mo->frame+1<$d0+$dn){++$mo->frame;$mo->tics=5;return;}
        $mo->aiState='dead';
        // Vanilla BEXP ends in S_NULL — remove the barrel or the last blast frame stays on screen.
        if($mo->sprite==='BEXP'){
            $i=array_search($mo,$world->mobjs,true);
            if($i!==false)array_splice($world->mobjs,$i,1);
        }
    }
    public static function noiseAlert(World $world,?Mobj $emitter,?object $game=null):void
    {
        if(!$emitter)return;$sec=Collision::pointInSubsector($world,$emitter->x,$emitter->y)->sector;++$world->validcount;self::recursiveSound($world,$sec,0,$emitter);
    }
    private static function recursiveSound(World $world,Sector $sec,int $blocks,Mobj $target):void
    {
        if($sec->validcount===$world->validcount&&$sec->soundtraversed<=$blocks+1)return;$sec->validcount=$world->validcount;$sec->soundtraversed=$blocks+1;$sec->soundtarget=$target;
        foreach($sec->lines as$ln){if(!($ln->flags&Defs::ML_TWOSIDED))continue;[$top,$bottom]=Collision::lineOpening($ln);if($top-$bottom<=0)continue;$other=$ln->frontsector===$sec?$ln->backsector:$ln->frontsector;if(!$other)continue;
            if($ln->flags&Defs::ML_SOUNDBLOCK){if($blocks===0)self::recursiveSound($world,$other,1,$target);}else self::recursiveSound($world,$other,$blocks,$target);}
    }
    private static function look(World $world,Mobj $mo,Mobj $player,object $game):void
    {
        if(!($player->flags&Defs::MF_SHOOTABLE))return;$see=false;$sec=Collision::pointInSubsector($world,$mo->x,$mo->y)->sector;$target=$sec->soundtarget;
        if($target&&($target->flags&Defs::MF_SHOOTABLE)){$mo->target=$target;$see=($mo->flags&Defs::MF_AMBUSH)?Collision::checkSight($world,$mo,$target):true;}
        if(!$see&&!self::lookForPlayer($world,$mo,$player))return;if(!$mo->target)$mo->target=$player;$mo->aiState='chase';$mo->movedir=self::DI_NODIR;$mo->movecount=0;$prof=self::profiles()[$mo->type]??null;if($prof&&$prof[2])$game->startSound($prof[2]);$mo->frame=0;
    }
    private static function lookForPlayer(World $world,Mobj $mo,Mobj $p):bool
    {
        if($p->health<=0||!($p->flags&Defs::MF_SHOOTABLE)||!Collision::checkSight($world,$mo,$p))return false;
        $angle=Compat::asU32(Collision::angleTo($mo->x,$mo->y,$p->x,$p->y)-$mo->angle);
        if($angle>Defs::ANG90&&$angle<Defs::ANG270&&Collision::approxDistance($p->x-$mo->x,$p->y-$mo->y)>Defs::MELEERANGE)return false;$mo->target=$p;return true;
    }
    private static function chase(World $world,Mobj $mo,Mobj $player,object $game):void
    {
        if($mo->reactiontime)--$mo->reactiontime;$target=$mo->target??$player;if(!$target||$target->health<=0||!($target->flags&Defs::MF_SHOOTABLE)){$mo->target=null;$mo->aiState='look';$mo->frame=0;$mo->tics=10;return;}$mo->target=$target;
        if($mo->justAttacked){$mo->justAttacked=false;self::newChaseDir($world,$mo,$game);return;}if($mo->movedir===self::DI_NODIR)self::newChaseDir($world,$mo,$game);self::faceMoveDir($mo);
        $dist=Collision::approxDistance($target->x-$mo->x,$target->y-$mo->y);$prof=self::profiles()[$mo->type]??[8,'hitscan',null,null,null,7,5,3];[$speed,$attack,,,$sfx]=$prof;if($attack==='none')return;
        $melee=in_array($attack,['melee','imp','baron','caco'],true);$missile=in_array($attack,['hitscan','shotgun','imp','baron','caco'],true);
        if($melee&&$dist<Defs::MELEERANGE-20*Defs::FRACUNIT+$target->radius&&Collision::checkSight($world,$mo,$target)){if($sfx)$game->startSound($sfx);self::startAttack($mo,'melee');return;}
        if($missile&&$mo->movecount===0&&self::missileOk($world,$mo,$target,$dist,$melee)){self::startAttack($mo,$attack);$mo->justAttacked=true;return;}
        if(--$mo->movecount<0||!self::move($world,$mo,$speed,$game))self::newChaseDir($world,$mo,$game);$mo->frame=($mo->frame+1)%4;
    }
    private static function missileOk(World $w,Mobj $mo,Mobj $target,int $dist,bool $melee):bool
    {
        if(!Collision::checkSight($w,$mo,$target)||$mo->reactiontime)return false;$d=$dist-64*Defs::FRACUNIT;if(!$melee)$d-=128*Defs::FRACUNIT;$d>>=16;if($d<0)return true;return self::random()>=min(200,$d);
    }
    private static function startAttack(Mobj $mo,string $kind):void{$mo->aiState='attack';$mo->tics=26;$mo->frame=4;$mo->attackKind=$kind;$mo->didFire=false;}
    private static function tickAttack(World $world,Mobj $mo,object $game):void
    {
        $t=$mo->target;if(!$t||$t->health<=0){$mo->aiState='look';$mo->frame=0;$mo->tics=10;return;}self::faceTarget($mo,$t);
        if(!$mo->didFire&&$mo->tics<=16){self::doAttack($world,$mo,$game);$mo->didFire=true;$mo->frame=5;}if(--$mo->tics<=0){$mo->aiState='chase';$mo->frame=0;$mo->movecount=15+(self::random()&15);}
    }
    private static function doAttack(World $world,Mobj $mo,object $game):void
    {
        $kind=$mo->attackKind;$saved=$mo->angle;self::faceTarget($mo,$mo->target);
        if($kind==='melee'){if(Collision::approxDistance($mo->target->x-$mo->x,$mo->target->y-$mo->y)<Defs::MELEERANGE+$mo->radius)$game->damageMobj($mo->target,$mo,(self::random()%8+1)*3);}
        elseif($kind==='shotgun'){$game->startSound('shotgn');for($i=0;$i<3;++$i){$mo->angle=Compat::asU32($saved+((self::random()-self::random())<<20));Collision::lineAttack($world,$mo,(self::random()%5+1)*3,$game,Defs::MISSILERANGE);}$mo->angle=$saved;}
        elseif(in_array($kind,['imp','baron','caco'],true)){if(Collision::approxDistance($mo->target->x-$mo->x,$mo->target->y-$mo->y)<Defs::MELEERANGE+$mo->radius){$game->startSound('claw');$game->damageMobj($mo->target,$mo,(self::random()%8+1)*3);}else{$game->startSound('firsht');self::spawnMissile($world,$mo,$mo->target,$kind==='baron'?'BAL2':'BAL1',($kind==='baron'?15:10)*Defs::FRACUNIT,$kind==='baron'?8:3);}}
        else{$game->startSound('pistol');$mo->angle=Compat::asU32($saved+((self::random()-self::random())<<20));Collision::lineAttack($world,$mo,(self::random()%5+1)*3,$game,Defs::MISSILERANGE);$mo->angle=$saved;}
    }
    private static function faceTarget(Mobj $mo,Mobj $t):void{$mo->angle=Collision::angleTo($mo->x,$mo->y,$t->x,$t->y);}
    private static function faceMoveDir(Mobj $mo):void
    {
        if($mo->movedir<0||$mo->movedir>=8)return;$want=Compat::asU32($mo->movedir*Defs::ANG45);$delta=Compat::asU32($want-$mo->angle);if(!$delta)return;$step=intdiv(Defs::ANG45,2);
        $mo->angle=$delta<0x80000000?Compat::asU32($mo->angle+min($step,$delta)):Compat::asU32($mo->angle-min($step,0x100000000-$delta));
    }
    private static function move(World $world,Mobj $mo,int $speed,object $game):bool
    {
        if($mo->movedir<0||$mo->movedir>=8)return false;$nx=$mo->x+$speed*self::XSPEED[$mo->movedir];$ny=$mo->y+$speed*self::YSPEED[$mo->movedir];$chk=Collision::checkPosition($world,$mo,$nx,$ny);
        if(!Collision::tryMove($world,$mo,$nx,$ny,$game)){foreach($chk->spechit as$ln)if($ln->special)$game->useSpecial($ln,$mo,0);return false;}$mo->z=$mo->floorz;return true;
    }
    private static function newChaseDir(World $world,Mobj $mo,object $game):void
    {
        $t=$mo->target;if(!$t)return;$old=$mo->movedir;$turn=self::OPPOSITE[$old]??self::DI_NODIR;$dx=$t->x-$mo->x;$dy=$t->y-$mo->y;
        $d2=$dx>10*Defs::FRACUNIT?self::DI_EAST:($dx<-10*Defs::FRACUNIT?self::DI_WEST:self::DI_NODIR);$d3=$dy<-10*Defs::FRACUNIT?self::DI_SOUTH:($dy>10*Defs::FRACUNIT?self::DI_NORTH:self::DI_NODIR);$speed=self::profiles()[$mo->type][0]??8;
        if($d2!==self::DI_NODIR&&$d3!==self::DI_NODIR){$mo->movedir=self::DIAGS[($dy<0?2:0)+($dx>0?1:0)];if($mo->movedir!==$turn&&self::move($world,$mo,$speed,$game)){$mo->movecount=self::random()&15;return;}}
        if(self::random()>200||abs($dy)>abs($dx))[$d2,$d3]=[$d3,$d2];if($d2===$turn)$d2=self::DI_NODIR;if($d3===$turn)$d3=self::DI_NODIR;
        foreach([$d2,$d3,$old]as$d)if($d!==self::DI_NODIR){$mo->movedir=$d;if(self::move($world,$mo,$speed,$game)){$mo->movecount=self::random()&15;return;}}
        $dirs=(self::random()&1)?range(0,7):range(7,0);foreach($dirs as$d)if($d!==$turn){$mo->movedir=$d;if(self::move($world,$mo,$speed,$game)){$mo->movecount=self::random()&15;return;}}
        if($turn!==self::DI_NODIR){$mo->movedir=$turn;if(self::move($world,$mo,$speed,$game)){$mo->movecount=self::random()&15;return;}}$mo->movedir=self::DI_NODIR;$mo->movecount=self::random()&15;
    }
    private static function spawnMissile(World $world,Mobj $src,Mobj $dest,string $sprite,int $speed,int $damage):void
    {
        $a=Collision::angleTo($src->x,$src->y,$dest->x,$dest->y);$dist=Collision::approxDistance($dest->x-$src->x,$dest->y-$src->y);$steps=max(1,$speed?intdiv($dist,$speed):1);
        $mo=new Mobj(x:$src->x+Compat::fixedMul(12*Defs::FRACUNIT,Tables::fineCos($a)),y:$src->y+Compat::fixedMul(12*Defs::FRACUNIT,Tables::fineSin($a)),z:$src->z+32*Defs::FRACUNIT,angle:$a,
            momx:Compat::fixedMul($speed,Tables::fineCos($a)),momy:Compat::fixedMul($speed,Tables::fineSin($a)),momz:(int)(($dest->z-$src->z)/$steps),radius:6*Defs::FRACUNIT,height:8*Defs::FRACUNIT,
            floorz:$src->floorz,ceilingz:$src->ceilingz,flags:Defs::MF_MISSILE|Defs::MF_DROPOFF|Defs::MF_NOGRAVITY,health:1000,type:0,sprite:$sprite,info:['missile',null],damage:$damage,aiState:'missile',target:$src);
        $mo->x+=$mo->momx>>1;$mo->y+=$mo->momy>>1;$mo->z+=$mo->momz>>1;$world->mobjs[]=$mo;
    }
    private static function tickMissile(World $world,Mobj $mo,object $game):void
    {
        $nx=$mo->x+$mo->momx;$ny=$mo->y+$mo->momy;$chk=Collision::checkPosition($world,$mo,$nx,$ny);if($chk->blocked||!Collision::tryMove($world,$mo,$nx,$ny,$game)){self::explodeMissile($world,$mo,$game,$chk->hitThing===$mo?null:$chk->hitThing);return;}
        $mo->z+=$mo->momz;if($mo->z<=$mo->floorz||$mo->z+$mo->height>$mo->ceilingz){self::explodeMissile($world,$mo,$game,null);return;}$mo->frame=($mo->frame+1)&1;
    }
    private static function explodeMissile(World $world,Mobj $mo,object $game,?Mobj $hit):void
    {
        if($hit)$game->damageMobj($hit,$mo->target??$mo,$mo->damage*(self::random()%8+1),$mo);$game->startSound('firxpl');$i=array_search($mo,$world->mobjs,true);if($i!==false)array_splice($world->mobjs,$i,1);
    }
    private static function explodeBarrel(World $world,Mobj $barrel,object $game):void
    {
        foreach(array_values($world->mobjs)as$o){if($o===$barrel||!($o->flags&Defs::MF_SHOOTABLE))continue;$d=max(0,Collision::approxDistance($o->x-$barrel->x,$o->y-$barrel->y)-$o->radius);if($d<128*Defs::FRACUNIT)$game->damageMobj($o,$barrel,(128*Defs::FRACUNIT-$d)>>16);}
    }
}
