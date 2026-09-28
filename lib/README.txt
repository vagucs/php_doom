Put SDL2 here so PHP FFI can load it without a system install:

  Windows:  SDL2.dll
            https://github.com/libsdl-org/SDL/releases  (SDL2-devel-*-VC.zip, x64)

  Linux:    libSDL2.so.0  (or: sudo apt install libsdl2-2.0-0)
  macOS:    libSDL2.dylib (or: brew install sdl2)

You can also set SDL2_PATH to the full path of the library.

PHP needs:
  extension=ffi
  ffi.enable=true
