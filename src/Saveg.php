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

final class Saveg
{
    private const MAGIC = 'DOOMPY01';
    private const PLAYER_FIELDS = [
        'playerstate', 'viewz', 'viewheight', 'deltaviewheight', 'bob', 'health',
        'armorpoints', 'armortype', 'ammo', 'maxammo', 'weaponowned', 'pendingweapon',
        'readyweapon', 'cards', 'cheats', 'message', 'messageTics', 'attackdown',
        'usedown', 'damagecount', 'bonuscount', 'extralight', 'refire', 'killcount',
        'itemcount', 'secretcount', 'didsecret', 'pspriteY', 'pspriteSy',
        'pspriteState', 'pspriteTics', 'pspriteStep', 'pspriteBody', 'pspriteFlash',
        'flashTics',
    ];
    private const MOBJ_FIELDS = [
        'x', 'y', 'z', 'angle', 'momx', 'momy', 'momz', 'radius', 'height',
        'floorz', 'ceilingz', 'flags', 'health', 'type', 'sprite', 'info', 'alive',
        'reactiontime', 'movedir', 'movecount', 'aiState', 'frame', 'tics',
        'chaseTics', 'justAttacked', 'damage', 'attackKind', 'didFire',
    ];

    public static function savePath(Game $game, int $slot): string
    {
        $directory = $game->iwadPath !== '' ? dirname((string) realpath($game->iwadPath)) : getcwd();
        return $directory . DIRECTORY_SEPARATOR . Defs::SAVEGAMENAME . $slot . '.dsg';
    }

    /** @return array{string,bool} */
    public static function readSlotDescription(Game $game, int $slot): array
    {
        $data = @file_get_contents(self::savePath($game, $slot), false, null, 0, Defs::SAVESTRINGSIZE + 8);
        if ($data === false || strlen($data) < Defs::SAVESTRINGSIZE + 8
            || substr($data, Defs::SAVESTRINGSIZE, 8) !== self::MAGIC) {
            return [Defs::LOADSAVEEMPTY, false];
        }
        $description = rtrim(strtok(substr($data, 0, Defs::SAVESTRINGSIZE), "\0") ?: '');
        return [$description !== '' ? $description : Defs::LOADSAVEEMPTY, true];
    }

    public static function writeSave(Game $game, int $slot, string $description): bool
    {
        if ($game->world === null || $game->player === null) {
            return false;
        }
        $json = json_encode(self::dumpState($game), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $description = substr($description, 0, Defs::SAVESTRINGSIZE);
        $blob = str_pad($description, Defs::SAVESTRINGSIZE, "\0") . self::MAGIC . $json;
        $path = self::savePath($game, $slot);
        $temporary = $path . '.tmp';
        if (@file_put_contents($temporary, $blob, LOCK_EX) === false) {
            return false;
        }
        if (PHP_OS_FAMILY === 'Windows' && is_file($path)) {
            @unlink($path);
        }
        return @rename($temporary, $path);
    }

    public static function readAndRestore(Game $game, int $slot): bool
    {
        try {
            $data = @file_get_contents(self::savePath($game, $slot));
            $offset = Defs::SAVESTRINGSIZE + 8;
            if ($data === false || strlen($data) < $offset
                || substr($data, Defs::SAVESTRINGSIZE, 8) !== self::MAGIC) {
                return false;
            }
            $state = json_decode(substr($data, $offset), true, flags: JSON_THROW_ON_ERROR);
            self::restoreState($game, $state);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    private static function dumpState(Game $game): array
    {
        $world = $game->world;
        $mobjs = array_values($world->mobjs);
        $mobjIndexes = [];
        foreach ($mobjs as $i => $mobj) {
            $mobjIndexes[spl_object_id($mobj)] = $i;
        }
        $dump = static function (object $object, array $fields): array {
            $result = [];
            foreach ($fields as $field) {
                if (property_exists($object, $field)) {
                    $result[$field] = $object->{$field};
                }
            }
            return $result;
        };
        $mobjRecords = [];
        foreach ($mobjs as $mobj) {
            $record = $dump($mobj, self::MOBJ_FIELDS);
            if (isset($record['info']) && is_array($record['info'])) {
                $record['info'] = array_values($record['info']);
            }
            $record['target'] = $mobj->target === null ? null : ($mobjIndexes[spl_object_id($mobj->target)] ?? null);
            $record['isPlayer'] = $mobj->player !== null;
            $mobjRecords[] = $record;
        }
        $thinkers = [];
        if ($game->specials !== null) {
            foreach ($game->specials->thinkers as $thinker) {
                if ($thinker->dead ?? false) continue;
                $sector = array_search($thinker->sector ?? null, $world->sectors, true);
                if ($sector === false) continue;
                if ($thinker instanceof VerticalDoor) {
                    $thinkers[] = self::record($thinker, 'door', $sector, ['type','direction','topheight','speed','topwait','topcountdown']);
                } elseif ($thinker instanceof Plat) {
                    $thinkers[] = self::record($thinker, 'plat', $sector, ['type','status','speed','low','high','wait','count']);
                } elseif ($thinker instanceof FloorMove) {
                    $thinkers[] = self::record($thinker, 'floor', $sector, ['direction','dest','speed']);
                }
            }
        }
        $buttons = [];
        foreach ($game->specials?->buttons ?? [] as $button) {
            $buttons[] = [
                'line' => $button->line->iLine ?? -1, 'where' => $button->where,
                'texture' => $button->texture, 'timer' => $button->timer,
            ];
        }
        return [
            'episode' => $game->episode, 'mapn' => $game->mapn, 'skill' => $game->skill,
            'leveltime' => $game->leveltime, 'player' => $dump($game->player, self::PLAYER_FIELDS),
            'sectors' => array_map(fn($s) => $dump($s, ['floorheight','ceilingheight','floorpic','ceilingpic','lightlevel','special']), $world->sectors),
            'sides' => array_map(fn($s) => $dump($s, ['textureoffset','rowoffset','toptexture','bottomtexture','midtexture']), $world->sides),
            'lines' => array_map(fn($l) => ['flags' => $l->flags, 'special' => $l->special], $world->lines),
            'mobjs' => $mobjRecords, 'thinkers' => $thinkers, 'buttons' => $buttons,
            'totalkills' => $game->totalkills, 'totalitems' => $game->totalitems,
            'totalsecret' => $game->totalsecret,
        ];
    }

    /** @return array<string,mixed> */
    private static function record(object $object, string $kind, int $sector, array $fields): array
    {
        $record = ['kind' => $kind, 'sector' => $sector];
        foreach ($fields as $field) $record[$field] = $object->{$field};
        return $record;
    }

    /** @param array<string,mixed> $state */
    private static function restoreState(Game $game, array $state): void
    {
        $game->episode = (int) $state['episode'];
        $game->mapn = (int) $state['mapn'];
        $game->skill = (int) $state['skill'];
        $game->leveltime = (int) ($state['leveltime'] ?? 0);
        $game->totalkills = (int) ($state['totalkills'] ?? 0);
        $game->totalitems = (int) ($state['totalitems'] ?? 0);
        $game->totalsecret = (int) ($state['totalsecret'] ?? 0);
        $game->world = new World();
        $game->world->setupLevel($game->wad, $game->res, $game->episode, $game->mapn);
        $game->specials = new Specials($game->world, $game->res, $game->sound);
        $world = $game->world;
        self::restoreList($world->sectors, $state['sectors'] ?? []);
        self::restoreList($world->sides, $state['sides'] ?? []);
        self::restoreList($world->lines, $state['lines'] ?? []);
        foreach ($world->sectors as $sector) $sector->specialdata = null;

        $thinkers = [];
        foreach ($state['thinkers'] ?? [] as $record) {
            $sector = $world->sectors[(int) $record['sector']] ?? null;
            if ($sector === null) continue;
            $thinker = match ($record['kind'] ?? '') {
                'door' => new VerticalDoor($sector, (int)$record['type'], (int)$record['direction'], (int)$record['topheight'], (int)$record['speed'], (int)$record['topwait'], (int)$record['topcountdown']),
                'plat' => new Plat($sector, (int)$record['type'], (int)$record['status'], (int)$record['speed'], (int)$record['low'], (int)$record['high'], (int)$record['wait'], (int)$record['count']),
                'floor' => new FloorMove($sector, (int)$record['direction'], (int)$record['dest'], (int)$record['speed']),
                default => null,
            };
            if ($thinker !== null) {
                $sector->specialdata = $thinker;
                $thinkers[] = $thinker;
            }
        }
        $game->specials->thinkers = $thinkers;
        $game->specials->buttons = [];
        foreach ($state['buttons'] ?? [] as $record) {
            $line = $world->lines[(int) $record['line']] ?? null;
            if ($line !== null) {
                $game->specials->buttons[] = new Button($line, $record['where'], (int)$record['texture'], (int)$record['timer']);
            }
        }

        $mobjs = [];
        foreach ($state['mobjs'] ?? [] as $record) {
            $mobj = new Mobj();
            foreach (self::MOBJ_FIELDS as $field) {
                if (array_key_exists($field, $record)) $mobj->{$field} = $record[$field];
            }
            if (isset($record['info']) && is_array($record['info'])) $mobj->info = $record['info'];
            $mobjs[] = $mobj;
        }
        foreach ($mobjs as $i => $mobj) {
            $target = $state['mobjs'][$i]['target'] ?? null;
            if (is_int($target) && isset($mobjs[$target])) $mobj->target = $mobjs[$target];
        }
        $game->player = null;
        foreach ($mobjs as $i => $mobj) {
            if ($state['mobjs'][$i]['isPlayer'] ?? false) {
                $player = new Player($mobj);
                foreach (self::PLAYER_FIELDS as $field) {
                    if (array_key_exists($field, $state['player'] ?? [])) $player->{$field} = $state['player'][$field];
                }
                $mobj->player = $player;
                $mobj->health = $player->health;
                $game->player = $player;
                break;
            }
        }
        if ($game->player === null) throw new \RuntimeException('save has no player');
        $world->mobjs = $mobjs;
        $game->gamestate = Defs::GS_LEVEL;
        $game->sound->playLevelMusic($game->episode, $game->mapn);
    }

    private static function restoreList(array $objects, array $records): void
    {
        foreach ($records as $i => $record) {
            if (!isset($objects[$i])) break;
            foreach ($record as $field => $value) $objects[$i]->{$field} = $value;
        }
    }
}
