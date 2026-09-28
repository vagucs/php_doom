@echo off
setlocal
cd /d "%~dp0"

set "PHP_DIR=C:\php-8.2.34-Win32-vs16-x64"
set "PHP=%PHP_DIR%\php.exe"

if not exist "%PHP%" (
    echo PHP nao encontrado: %PHP%
    exit /b 1
)

if not exist "lib\SDL2.dll" if not defined SDL2_PATH (
    echo Aviso: coloque SDL2.dll em lib\  ^(64-bit, https://github.com/libsdl-org/SDL/releases^)
)

set "IWAD_ARGS=%*"
echo %* | findstr /i /c:"-iwad" >nul
if errorlevel 1 (
    if exist "DOOM1.WAD" set "IWAD_ARGS=-iwad DOOM1.WAD %*"
    if exist "..\DOOM1.WAD" set "IWAD_ARGS=-iwad ..\DOOM1.WAD %*"
)

"%PHP%" -d extension_dir="%PHP_DIR%\ext" -d extension=ffi -d ffi.enable=true doom.php %IWAD_ARGS%
exit /b %ERRORLEVEL%
