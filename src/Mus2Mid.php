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

/** MUS lump -> Standard MIDI File (Chocolate Doom mus2mid.c). */
final class Mus2Mid
{
    public string $body = '';
    public int $queued = 0;
    public int $tracksize = 0;
    /** @var array<int, int> */
    public array $velocities;
    /** @var array<int, int> */
    public array $channelMap;

    public function __construct()
    {
        $this->velocities = array_fill(0, 16, 127);
        $this->channelMap = array_fill(0, 16, -1);
    }

    public function writeTime(int $time): void
    {
        $buffer = $time & 0x7F;
        $working = $time;
        while (($working >>= 7) !== 0) {
            $buffer <<= 8;
            $buffer |= ($working & 0x7F) | 0x80;
        }
        while (true) {
            $this->body .= chr($buffer & 0xFF);
            ++$this->tracksize;
            if (($buffer & 0x80) !== 0) {
                $buffer >>= 8;
            } else {
                $this->queued = 0;
                return;
            }
        }
    }

    public function write(string $data): void
    {
        $this->writeTime($this->queued);
        $this->body .= $data;
        $this->tracksize += strlen($data);
    }

    private function allocateChannel(): int
    {
        $result = max($this->channelMap) + 1;
        if ($result === 9) {
            ++$result;
        }
        return $result;
    }

    public function midiChannel(int $musChannel): int
    {
        if ($musChannel === 15) {
            return 9;
        }
        if ($this->channelMap[$musChannel] === -1) {
            $this->channelMap[$musChannel] = $this->allocateChannel();
            $channel = $this->channelMap[$musChannel];
            $this->write(pack('C*', 0xB0 | $channel, 0x7B, 0));
        }
        return $this->channelMap[$musChannel];
    }
}

function mus2mid(string $mus): ?string
{
    if (strlen($mus) >= 4 && substr($mus, 0, 4) === 'MThd') {
        return $mus;
    }
    if (strlen($mus) < 16 || substr($mus, 0, 4) !== "MUS\x1a") {
        return null;
    }

    $controllerMap = [
        0x00, 0x20, 0x01, 0x07, 0x0A, 0x0B, 0x5B, 0x5D,
        0x40, 0x43, 0x78, 0x7B, 0x7E, 0x7F, 0x79,
    ];
    $midiHeader = "MThd\x00\x00\x00\x06\x00\x00\x00\x01\x00\x46"
        . "MTrk\x00\x00\x00\x00";
    $position = Bin::u16($mus, 6);
    $length = strlen($mus);
    $output = new Mus2Mid();
    $hitScoreEnd = false;

    $readU8 = static function () use (&$position, $length, $mus): ?int {
        if ($position >= $length) {
            return null;
        }
        return ord($mus[$position++]);
    };

    while (!$hitScoreEnd) {
        while (!$hitScoreEnd) {
            $descriptor = $readU8();
            if ($descriptor === null) {
                return null;
            }
            $channel = $output->midiChannel($descriptor & 0x0F);
            $event = $descriptor & 0x70;

            switch ($event) {
                case 0x00:
                    $key = $readU8();
                    if ($key === null) {
                        return null;
                    }
                    $output->write(pack('C*', 0x80 | $channel, $key & 0x7F, 0));
                    break;

                case 0x10:
                    $key = $readU8();
                    if ($key === null) {
                        return null;
                    }
                    if (($key & 0x80) !== 0) {
                        $velocity = $readU8();
                        if ($velocity === null) {
                            return null;
                        }
                        $output->velocities[$channel] = $velocity & 0x7F;
                    }
                    $output->write(pack(
                        'C*',
                        0x90 | $channel,
                        $key & 0x7F,
                        $output->velocities[$channel]
                    ));
                    break;

                case 0x20:
                    $key = $readU8();
                    if ($key === null) {
                        break 2;
                    }
                    $wheel = $key * 64;
                    $output->write(pack(
                        'C*',
                        0xE0 | $channel,
                        $wheel & 0x7F,
                        ($wheel >> 7) & 0x7F
                    ));
                    break;

                case 0x30:
                    $controller = $readU8();
                    if ($controller === null || $controller < 10 || $controller > 14) {
                        return null;
                    }
                    $output->write(pack(
                        'C*',
                        0xB0 | $channel,
                        $controllerMap[$controller],
                        0
                    ));
                    break;

                case 0x40:
                    $controller = $readU8();
                    $value = $readU8();
                    if ($controller === null || $value === null) {
                        return null;
                    }
                    if ($controller === 0) {
                        $output->write(pack('C*', 0xC0 | $channel, $value & 0x7F));
                    } else {
                        if ($controller < 1 || $controller > 9) {
                            return null;
                        }
                        $working = ($value & 0x80) !== 0 ? 0x7F : $value;
                        $output->write(pack(
                            'C*',
                            0xB0 | $channel,
                            $controllerMap[$controller],
                            $working
                        ));
                    }
                    break;

                case 0x60:
                    $hitScoreEnd = true;
                    break;

                default:
                    return null;
            }
            if (($descriptor & 0x80) !== 0) {
                break;
            }
        }

        if (!$hitScoreEnd) {
            $timeDelay = 0;
            do {
                $working = $readU8();
                if ($working === null) {
                    return null;
                }
                $timeDelay = $timeDelay * 128 + ($working & 0x7F);
            } while (($working & 0x80) !== 0);
            $output->queued += $timeDelay;
        }
    }

    $output->writeTime($output->queued);
    $output->body .= "\xFF\x2F\x00";
    $output->tracksize += 3;
    $midiHeader[18] = chr(($output->tracksize >> 24) & 0xFF);
    $midiHeader[19] = chr(($output->tracksize >> 16) & 0xFF);
    $midiHeader[20] = chr(($output->tracksize >> 8) & 0xFF);
    $midiHeader[21] = chr($output->tracksize & 0xFF);
    return $midiHeader . $output->body;
}
