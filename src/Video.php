<?php
/**
 * DOOM generic portado de Harbour para PHP CLI com SDL3.
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

use FFI;
use Throwable;

/** SDL3 video + audio (doomgeneric / i_video) via PHP FFI. */
final class Video
{
    public const SCALE_MIN = 1;
    public const SCALE_MAX = 6;
    private const SDL_INIT_AUDIO = 0x00000010;
    private const SDL_INIT_VIDEO = 0x00000020;
    private const SDL_INIT_EVENTS = 0x00004000;
    private const SDL_WINDOWPOS_CENTERED = 0x2FFF0000;
    private const SDL_WINDOW_RESIZABLE = 0x20;
    private const SDL_SCALEMODE_NEAREST = 0;
    private const SDL_AUDIO_DEVICE_DEFAULT_PLAYBACK = 0xFFFFFFFF;
    /** SDL_PIXELFORMAT_ARGB8888 — packed 32-bit, works on Windows D3D (RGB24 looked palettized). */
    private const SDL_PIXELFORMAT_ARGB8888 = 0x16362004;
    private const SDL_TEXTUREACCESS_STREAMING = 1;
    private const SDL_QUIT = 0x100;
    private const SDL_KEYDOWN = 0x300;
    private const SDL_KEYUP = 0x301;
    private const SDL_MOUSEMOTION = 0x400;
    private const SDL_MOUSEBUTTONDOWN = 0x401;
    private const SDL_MOUSEBUTTONUP = 0x402;
    private const SDL_BUTTON_LEFT = 1;
    private const AUDIO_S16LE = 0x8010;

    /** @var array<int,int> 320x200 PLAYPAL indices */
    public array $fb = [];
    public bool $fullscreen = false;
    public int $scale = 2;
    public bool $showFps = false;
    public bool $crt = false;
    public string $windowTitle = 'DOOM';

    private ?FFI $sdl = null;
    private mixed $window = null;
    private mixed $renderer = null;
    private mixed $texture = null;
    private mixed $audio = null;
    /** @var array<int,array{0:int,1:int,2:int}> */
    private array $palette = [];
    public int $fpsValue = 0;
    public int $fpsAvg = 0;
    private int $fpsFrames = 0;
    private int $fpsTotal = 0;
    private int $fpsStamp = 0;
    private int $fpsStart = 0;
    /** @var list<int> remaining mixed s16 samples */
    private array $mix = [];
    private bool $mouseGrab = false;

    public function init(bool $fullscreen = false, string $title = 'DOOM'): void
    {
        if (!extension_loaded('FFI')) {
            throw new \RuntimeException('PHP FFI is required. Enable extension=ffi and ffi.enable=true');
        }
        $this->fb = array_fill(0, Defs::SCREENWIDTH * Defs::SCREENHEIGHT, 0);
        $this->palette = array_fill(0, 256, [0, 0, 0]);
        $this->fullscreen = $fullscreen;
        $this->windowTitle = $title;
        $this->sdl = FFI::cdef(self::cdef(), self::libraryPath());
        $flags = self::SDL_INIT_VIDEO | self::SDL_INIT_EVENTS | self::SDL_INIT_AUDIO;
        if (!$this->sdl->SDL_Init($flags)) {
            throw new \RuntimeException('SDL_Init: ' . $this->error());
        }
        $this->applyMode();
        $this->sdl->SDL_HideCursor();
        $this->openAudio();
        $this->fpsStamp = $this->ticksMs();
        $this->fpsStart = $this->fpsStamp;
    }

    public function ticksMs(): int
    {
        return $this->sdl !== null ? (int) $this->sdl->SDL_GetTicks() : (int) (microtime(true) * 1000);
    }

    public function setRelativeMouse(bool $on): void
    {
        if ($on === $this->mouseGrab || $this->sdl === null) {
            return;
        }
        $this->mouseGrab = $on;
        if ($this->window !== null) {
            $this->sdl->SDL_SetWindowRelativeMouseMode($this->window, $on);
        }
        if ($on) {
            $this->sdl->SDL_HideCursor();
        } else {
            $this->sdl->SDL_ShowCursor();
        }
    }

    public function toggleFullscreen(): void
    {
        $this->fullscreen = !$this->fullscreen;
        $this->applyMode();
    }

    public function changeScale(int $delta): bool
    {
        if ($this->fullscreen) {
            return false;
        }
        $new = max(self::SCALE_MIN, min(self::SCALE_MAX, $this->scale + $delta));
        if ($new === $this->scale) {
            return false;
        }
        $this->scale = $new;
        $this->applyMode();
        return true;
    }

    public function setPalette(string $playpal, int $gamma = 0): void
    {
        $this->setPaletteRaw(substr($playpal, 0, 768));
    }

    public function setPaletteRaw(string $rgb768): void
    {
        for ($i = 0; $i < 256; ++$i) {
            $this->palette[$i] = [ord($rgb768[$i * 3]), ord($rgb768[$i * 3 + 1]), ord($rgb768[$i * 3 + 2])];
        }
    }

    public function playSfx(string $pcm, int $volume = 8): void
    {
        if ($pcm === '' || $this->sdl === null || $this->audio === null) {
            return;
        }
        $gain = max(0, min(15, $volume)) / 15.0;
        $n = intdiv(strlen($pcm), 2);
        $need = count($this->mix);
        if ($need < $n) {
            $this->mix = array_pad($this->mix, $n, 0);
        }
        for ($i = 0; $i < $n; ++$i) {
            $s = unpack('v', substr($pcm, $i * 2, 2))[1];
            if ($s >= 0x8000) {
                $s -= 0x10000;
            }
            $mixed = $this->mix[$i] + (int) ($s * $gain);
            $this->mix[$i] = max(-32768, min(32767, $mixed));
        }
    }

    public function present(): void
    {
        if ($this->sdl === null || $this->renderer === null || $this->texture === null) {
            return;
        }
        $this->pumpAudio();
        $n = Defs::SCREENWIDTH * Defs::SCREENHEIGHT;
        $pixels = $this->sdl->new('uint8_t[' . ($n * 4) . ']');
        for ($i = 0; $i < $n; ++$i) {
            $c = $this->palette[$this->fb[$i] & 0xFF];
            if ($this->crt) {
                $row = intdiv($i, Defs::SCREENWIDTH);
                $scan = ($row & 1) ? 180 : 256;
                $c = [
                    min(255, intdiv($c[0] * $scan, 256)),
                    min(255, intdiv($c[1] * $scan, 256)),
                    min(255, intdiv($c[2] * $scan, 256)),
                ];
            }
            $o = $i * 4;
            $pixels[$o] = $c[2];
            $pixels[$o + 1] = $c[1];
            $pixels[$o + 2] = $c[0];
            $pixels[$o + 3] = 255;
        }
        $this->sdl->SDL_UpdateTexture($this->texture, null, $pixels, Defs::SCREENWIDTH * 4);
        $this->sdl->SDL_RenderClear($this->renderer);
        $this->sdl->SDL_RenderTexture($this->renderer, $this->texture, null, null);
        $this->sdl->SDL_RenderPresent($this->renderer);
        ++$this->fpsFrames;
        $now = $this->ticksMs();
        if ($now - $this->fpsStamp >= 1000) {
            $this->fpsValue = $this->fpsFrames;
            $this->fpsTotal += $this->fpsFrames;
            $span = $now - $this->fpsStart;
            if ($span > 0) {
                $this->fpsAvg = intdiv($this->fpsTotal * 1000, $span);
            }
            $this->fpsFrames = 0;
            $this->fpsStamp = $now;
        }
        if ($this->showFps) {
            $this->sdl->SDL_SetWindowTitle(
                $this->window,
                $this->windowTitle . ' - ' . $this->fpsValue . ' FPS  ' . $this->fpsAvg . ' AVG'
            );
        }
    }

    /** @return list<array{type:string,key?:int,sym?:int,repeat?:bool,mod?:int,text?:string,dx?:int,dy?:int,button?:int}> */
    public function pollEvents(): array
    {
        $out = [];
        if ($this->sdl === null) {
            return $out;
        }
        $event = $this->sdl->new('SDL_Event');
        while ($this->sdl->SDL_PollEvent(FFI::addr($event))) {
            $type = $event->type;
            if ($type === self::SDL_QUIT) {
                $out[] = ['type' => 'quit'];
                continue;
            }
            if ($type === self::SDL_MOUSEMOTION) {
                $out[] = [
                    'type' => 'mousemotion',
                    'dx' => (int) $event->motion->xrel,
                    'dy' => (int) $event->motion->yrel,
                ];
                continue;
            }
            if ($type === self::SDL_MOUSEBUTTONDOWN || $type === self::SDL_MOUSEBUTTONUP) {
                $out[] = [
                    'type' => $type === self::SDL_MOUSEBUTTONDOWN ? 'mousedown' : 'mouseup',
                    'button' => (int) $event->button->button,
                ];
                continue;
            }
            if ($type !== self::SDL_KEYDOWN && $type !== self::SDL_KEYUP) {
                continue;
            }
            $sym = (int) $event->key->key;
            $scan = (int) $event->key->scancode;
            // Physical -/=/+ even on ABNT2 (SDL may send SDLK_PLUS=43, not EQUALS).
            if ($scan === 45 || $scan === 86) {
                $sym = $scan === 86 ? Keys::KP_MINUS : Keys::MINUS;
            } elseif ($scan === 46 || $scan === 87) {
                $sym = $scan === 87 ? Keys::KP_PLUS : Keys::EQUALS;
            } elseif ($sym === Keys::PLUS) {
                $sym = Keys::EQUALS;
            }
            $repeat = (int) $event->key->repeat !== 0;
            if ($repeat && $type === self::SDL_KEYDOWN) {
                continue;
            }
            $text = '';
            if ($sym >= 32 && $sym < 127) {
                $text = chr($sym);
            }
            $out[] = [
                'type' => $type === self::SDL_KEYDOWN ? 'keydown' : 'keyup',
                'key' => $sym,
                'sym' => $sym,
                'repeat' => $repeat,
                'mod' => (int) $event->key->mod,
                'text' => $text,
            ];
        }
        return $out;
    }

    public function shutdown(): void
    {
        if ($this->sdl === null) {
            return;
        }
        if ($this->audio !== null) {
            $this->sdl->SDL_DestroyAudioStream($this->audio);
            $this->audio = null;
        }
        if ($this->texture !== null) {
            $this->sdl->SDL_DestroyTexture($this->texture);
            $this->texture = null;
        }
        if ($this->renderer !== null) {
            $this->sdl->SDL_DestroyRenderer($this->renderer);
            $this->renderer = null;
        }
        if ($this->window !== null) {
            $this->sdl->SDL_DestroyWindow($this->window);
            $this->window = null;
        }
        $this->sdl->SDL_Quit();
        $this->sdl = null;
    }

    private function applyMode(): void
    {
        if ($this->sdl === null) {
            return;
        }
        $w = Defs::SCREENWIDTH * $this->scale;
        $h = Defs::SCREENHEIGHT * $this->scale;
        if ($this->window === null) {
            $this->window = $this->sdl->SDL_CreateWindow(
                $this->windowTitle,
                $w,
                $h,
                self::SDL_WINDOW_RESIZABLE
            );
            if ($this->window === null) {
                throw new \RuntimeException('SDL_CreateWindow: ' . $this->error());
            }
            $this->renderer = $this->sdl->SDL_CreateRenderer($this->window, null);
            if ($this->renderer === null) {
                throw new \RuntimeException('SDL_CreateRenderer: ' . $this->error());
            }
            $this->sdl->SDL_SetRenderVSync($this->renderer, 1);
            $this->texture = $this->sdl->SDL_CreateTexture(
                $this->renderer,
                self::SDL_PIXELFORMAT_ARGB8888,
                self::SDL_TEXTUREACCESS_STREAMING,
                Defs::SCREENWIDTH,
                Defs::SCREENHEIGHT
            );
            if ($this->texture === null) {
                throw new \RuntimeException('SDL_CreateTexture: ' . $this->error());
            }
            $this->sdl->SDL_SetTextureScaleMode($this->texture, self::SDL_SCALEMODE_NEAREST);
        }
        $this->sdl->SDL_SetWindowFullscreen($this->window, $this->fullscreen);
        if (!$this->fullscreen) {
            $this->sdl->SDL_SetWindowSize($this->window, $w, $h);
            $this->sdl->SDL_SetWindowPosition(
                $this->window,
                self::SDL_WINDOWPOS_CENTERED,
                self::SDL_WINDOWPOS_CENTERED
            );
        }
        $this->sdl->SDL_SetWindowTitle($this->window, $this->windowTitle);
    }

    private function openAudio(): void
    {
        if ($this->sdl === null) {
            return;
        }
        try {
            $want = $this->sdl->new('SDL_AudioSpec');
            $want->format = self::AUDIO_S16LE;
            $want->channels = 1;
            $want->freq = 11025;
            $stream = $this->sdl->SDL_OpenAudioDeviceStream(
                self::SDL_AUDIO_DEVICE_DEFAULT_PLAYBACK,
                FFI::addr($want),
                null,
                null
            );
            if ($stream !== null) {
                $this->audio = $stream;
                $this->sdl->SDL_ResumeAudioStreamDevice($stream);
            }
        } catch (Throwable) {
            $this->audio = null;
        }
    }

    private function pumpAudio(): void
    {
        if ($this->sdl === null || $this->audio === null || $this->mix === []) {
            return;
        }
        $queued = (int) $this->sdl->SDL_GetAudioStreamQueued($this->audio);
        // 16-bit mono at 11025 Hz. Three device periods (~139 ms). A 1 s
        // backlog is what made shots and doors land late next to Harbour.
        $limit = 512 * 2 * 3;
        if ($queued >= $limit) {
            return;
        }
        $n = min(count($this->mix), intdiv($limit - $queued, 2));
        if ($n < 1) {
            return;
        }
        $chunk = array_splice($this->mix, 0, $n);
        $pcm = '';
        foreach ($chunk as $s) {
            $pcm .= pack('v', $s & 0xFFFF);
        }
        $len = strlen($pcm);
        $buf = $this->sdl->new('uint8_t[' . $len . ']');
        FFI::memcpy($buf, $pcm, $len);
        $this->sdl->SDL_PutAudioStreamData($this->audio, $buf, $len);
    }

    private function error(): string
    {
        try {
            return (string) $this->sdl->SDL_GetError();
        } catch (Throwable) {
            return 'unknown SDL error';
        }
    }

    private static function libraryPath(): string
    {
        $env = getenv('SDL3_PATH');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }
        $root = dirname(__DIR__);
        $bits = PHP_INT_SIZE === 8 ? 'x64' : 'x86';
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'sdl3' . DIRECTORY_SEPARATOR . 'SDL3-3.4.18-win32-' . $bits . DIRECTORY_SEPARATOR . 'SDL3.dll',
            $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SDL3.dll',
            $root . DIRECTORY_SEPARATOR . 'SDL3.dll',
            $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'libSDL3.so',
            $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'libSDL3.so.0',
            $root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'libSDL3.dylib',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return match (PHP_OS_FAMILY) {
            'Windows' => 'SDL3.dll',
            'Darwin' => 'libSDL3.dylib',
            default => 'libSDL3.so.0',
        };
    }

    private static function cdef(): string
    {
        return <<<'C'
typedef unsigned char Uint8;
typedef unsigned short Uint16;
typedef unsigned int Uint32;
typedef int Sint32;
typedef uint64_t Uint64;

bool SDL_Init(Uint32 flags);
void SDL_Quit(void);
const char *SDL_GetError(void);
Uint64 SDL_GetTicks(void);
void SDL_Delay(Uint32 ms);
typedef struct SDL_Window SDL_Window;
typedef struct SDL_Renderer SDL_Renderer;
typedef struct SDL_Texture SDL_Texture;

bool SDL_ShowCursor(void);
bool SDL_HideCursor(void);
bool SDL_SetWindowRelativeMouseMode(SDL_Window *window, bool enabled);

SDL_Window *SDL_CreateWindow(const char *title, int w, int h, Uint64 flags);
void SDL_DestroyWindow(SDL_Window *window);
bool SDL_SetWindowFullscreen(SDL_Window *window, bool fullscreen);
bool SDL_SetWindowSize(SDL_Window *window, int w, int h);
bool SDL_SetWindowPosition(SDL_Window *window, int x, int y);
bool SDL_SetWindowTitle(SDL_Window *window, const char *title);

SDL_Renderer *SDL_CreateRenderer(SDL_Window *window, const char *name);
void SDL_DestroyRenderer(SDL_Renderer *renderer);
bool SDL_SetRenderVSync(SDL_Renderer *renderer, int vsync);
bool SDL_RenderClear(SDL_Renderer *renderer);
bool SDL_RenderTexture(SDL_Renderer *renderer, SDL_Texture *texture, const void *srcrect, const void *dstrect);
bool SDL_RenderPresent(SDL_Renderer *renderer);

SDL_Texture *SDL_CreateTexture(SDL_Renderer *renderer, Uint32 format, int access, int w, int h);
void SDL_DestroyTexture(SDL_Texture *texture);
bool SDL_SetTextureScaleMode(SDL_Texture *texture, int scaleMode);
bool SDL_UpdateTexture(SDL_Texture *texture, const void *rect, const void *pixels, int pitch);

typedef struct SDL_KeyboardEvent {
    Uint32 type;
    Uint32 reserved;
    Uint64 timestamp;
    Uint32 windowID;
    Uint32 which;
    int scancode;
    Uint32 key;
    Uint16 mod;
    Uint16 raw;
    bool down;
    bool repeat;
} SDL_KeyboardEvent;

typedef struct SDL_MouseMotionEvent {
    Uint32 type;
    Uint32 reserved;
    Uint64 timestamp;
    Uint32 windowID;
    Uint32 which;
    Uint32 state;
    float x;
    float y;
    float xrel;
    float yrel;
} SDL_MouseMotionEvent;

typedef struct SDL_MouseButtonEvent {
    Uint32 type;
    Uint32 reserved;
    Uint64 timestamp;
    Uint32 windowID;
    Uint32 which;
    Uint8 button;
    bool down;
    Uint8 clicks;
    Uint8 padding;
    float x;
    float y;
} SDL_MouseButtonEvent;

typedef union SDL_Event {
    Uint32 type;
    SDL_KeyboardEvent key;
    SDL_MouseMotionEvent motion;
    SDL_MouseButtonEvent button;
    Uint8 padding[128];
} SDL_Event;

bool SDL_PollEvent(SDL_Event *event);

typedef struct SDL_AudioSpec {
    int format;
    int channels;
    int freq;
} SDL_AudioSpec;

typedef struct SDL_AudioStream SDL_AudioStream;
SDL_AudioStream *SDL_OpenAudioDeviceStream(Uint32 devid, const SDL_AudioSpec *spec, void *callback, void *userdata);
bool SDL_ResumeAudioStreamDevice(SDL_AudioStream *stream);
bool SDL_PutAudioStreamData(SDL_AudioStream *stream, const void *buf, int len);
int SDL_GetAudioStreamQueued(SDL_AudioStream *stream);
void SDL_DestroyAudioStream(SDL_AudioStream *stream);
C;
    }
}
