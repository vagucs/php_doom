The game loads SDL3 from sdl3/SDL3-3.4.18-win32-x64/SDL3.dll
(32-bit PHP uses the win32-x86 copy). Headers live in sdl3/include/.

SDL3_PATH can point at another SDL3.dll.

PHP needs:
  extension=ffi
  ffi.enable=true
