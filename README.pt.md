# php_doom

![DOOM rodando em PHP CLI com SDL2](screenshot/doom.png)

**Vídeo:** [DOOM rodando em PHP](https://youtu.be/aCOHFTD1D8M)

DOOM generic portado de Harbour para **PHP 8.1+ CLI + SDL2** (FFI). Não é aplicação web.

Por **Wagner Nunes da Silva**

- vagucs@bol.com.br
- vagucs@vagucs.com.br
- vagucs@gmail.com
- [www.vagucs.com.br](https://www.vagucs.com.br)

Esta árvore é um port de **[harbour_doom](https://github.com/vagucs/harbour_doom)** (`doom_hb`): o mesmo motor Chocolate Doom / doomgeneric que primeiro foi de C para Harbour, depois para Python (`doom_python`), agora de Harbour para PHP.

English version: [README.md](README.md)

---

## O que é este projeto

O motor do Chocolate Doom / doomgeneric foi traduzido para **Harbour** (`.prg` / `.ch`) com uma camada fina de C para Allegro 4.2.2. Esse trabalho está em [github.com/vagucs/harbour_doom](https://github.com/vagucs/harbour_doom). Este diretório é o **mesmo material de estudo**, em PHP:

- Janela, teclas, PCM: **SDL2** via **FFI** do PHP (sem extensão PECL `sdl`, sem navegador, sem HTTP)
- Framebuffer: 320×200, 8 bits PLAYPAL, escalado na janela
- Tic do jogo: 35 Hz (`TICRATE`), igual ao vanilla
- Renderer: BSP, visplanes, `R_DrawColumn` / `R_DrawSpan`, ponto fixo 16.16 `fixedMul` / `fixedDiv`
- Mapa: VERTEXES, LINEDEFS, SIDEDEFS, SECTORS, SEGS, SSECTORS, NODES, THINGS, BLOCKMAP, REJECT
- Jogo: andar, portas, plataformas, interruptores, teleports, saída, itens, armas (incluindo motosserra subindo/cortando), barra de status, automap com Tab, som DS*, música MUS→MIDI, menu ESC (opções, load/save), totalização no intermission, wipe derretendo, bunny scroll, inimigos em look/chase/ataque

É necessário um IWAD legal (shareware `doom1.wad` ou comercial `doom.wad` / `doom2.wad` / etc.). Este repositório não distribui WAD comercial.

É um **port completo de jogabilidade** do motor Harbour para PHP (os mesmos sistemas da árvore Python acabada, mais teleports e cheats extras). A contagem de arquivos é condensada frente aos 100+ `.prg`; o comportamento segue as fontes Harbour.

O que não entra nesta árvore (o mesmo corte do `boot.prg` Harbour):

- Rede, música de CD, joystick
- Quantização de paleta `-colors` (experimento só no Harbour)
- Playback de demo, tabelas `info` vanilla completas

---

## Proposta educacional

Este projeto é, antes de tudo, um **material de estudo**. O DOOM (1993) é pequeno o bastante para ser lido de ponta a ponta e denso o bastante para ensinar engenharia de verdade: renderização por BSP, ponto fixo 16.16, laço por tics, sistema de arquivos WAD.

O port Harbour ensinou a ler C com os olhos de outra linguagem. O port Python tirou o pré-processador e os arrays 1-based. O port PHP dá o passo seguinte: o jogo ainda roda num interpretador, mas a fronteira nativa é **FFI para SDL2**, não pygame e não Allegro.

O que o port pretende ensinar:

- **Quatro linguagens, um motor.** C (`base_c/` no harbour_doom) → Harbour (`.prg`) → Python (`doom_python`) → PHP (`php_doom/src`). Os mesmos nomes (`P_Thrust`, `R_DrawColumn`, `A_Look`) para abrir as quatro versões lado a lado.
- **O que os ponteiros faziam.** PHP usa objetos e arrays; wrap-around, ângulos BAM e overflow 16.16 ficam explícitos (`Compat::asU32`, `shar`, `fixedMul`) porque inteiros em PHP não estouram em 32 bits. Produtos que passam de `int` não podem ir para `intdiv()`.
- **Onde um interpretador basta.** O jogo inteiro roda em PHP CLI. SDL2 só abre janela, lê teclado e enfileira PCM. FFI é a fronteira nativa, como o Allegro foi no Harbour.
- **CLI, não HTTP.** `PHP_SAPI` precisa ser `cli`. Não há canvas no navegador nem servidor web.
- **Modernização de legado.** Manter o comportamento idêntico, isolar a camada nativa, conferir contra o original.

Sugestão de roteiro:

1. Rode o jogo e leia `doom.php` e `src/Game.php` — boot, tic, input.
2. Compare `src/Compat.php` com o Harbour `xhb_compat.prg` / `m_fixed.prg` e o Python `doom/compat.py`.
3. Abra `src/Render.php` ao lado de `r_main.prg` / `r_bsp.prg` / `r_segs.prg` / `r_draw.prg`.
4. Siga uma porta a partir do **Espaço** até `src/Specials.php`.
5. Siga um tiro a partir do **Ctrl** em `src/Player.php` até `Collision::lineAttack` e `Enemy`.

---

## De C / Harbour / Python para PHP

Arrays em PHP são 0-based, como o C e o port Python. Arrays Harbour eram 1-based; esse deslocamento some aqui.

| DOOM em C | Harbour | Python | PHP |
|---|---|---|---|
| `struct` / `typedef struct` | `CLASS ... DATA` | `@dataclass` | `class` + propriedades tipadas |
| `thing->x` | `thing:x` | `thing.x` | `$thing->x` |
| `NULL` | `NIL` | `None` | `null` |
| `array[0]` | `array[1]` | `array[0]` | `$array[0]` |
| `&`, `\|`, `^` | `hb_qbitAnd/Or/Xor` | `&`, `\|`, `^` | `&`, `\|`, `^` |
| `x >> n` sem sinal | `UShr(x, n)` | `ushr(x, n)` | `Compat::ushr($x, $n)` |
| `x >> n` com sinal | `Shar(x, n)` | `shar(x, n)` | `Compat::shar($x, $n)` |
| estouro de 32 bits | `AsU32` / `AsInt32` | `as_u32` / `as_i32` | `Compat::asU32` / `asI32` |
| `fixed_t` 16.16 | `FixedMul` / `FixedDiv` | `fixed_mul` / `fixed_div` | `Compat::fixedMul` / `fixedDiv` |
| framebuffer `byte *` | string Harbour | `bytearray` + LUT numpy | `array<int>` de 64000 + SDL ARGB8888 |
| Allegro 4.2.2 | GTALLEG / llibg | pygame | SDL2 via FFI |
| `Z_Malloc` | GC | GC | GC |
| globais `PUBLIC` | `PUBLIC` / `MEMVAR` | campos em `Game` | campos públicos em `Game` |
| 100+ arquivos `.prg` | 1:1 com o C | `doom/*.py` condensado | `src/*.php` condensado |

### Lado a lado: `P_Thrust`

C (`base_c/p_user.c` no harbour_doom):

```c
void P_Thrust (player_t* player, angle_t angle, fixed_t move)
{
    angle >>= ANGLETOFINESHIFT;
    player->mo->momx += FixedMul(move,finecosine[angle]);
    player->mo->momy += FixedMul(move,finesine[angle]);
}
```

Harbour (`p_user.prg`):

```harbour
PROCEDURE P_Thrust( player, angle, move )
    angle := UShr( angle, ANGLETOFINESHIFT )
    player:mo:momx += FixedMul( move, finecosine[ angle + 1 ] )
    player:mo:momy += FixedMul( move, finesine[ angle + 1 ] )
RETURN
```

Python (`doom/player.py`):

```python
def thrust(mo, angle, move):
    mo.momx += fixed_mul(move, fine_cos(angle))
    mo.momy += fixed_mul(move, fine_sin(angle))
```

PHP (`src/Player.php`):

```php
public static function thrust(Mobj $mo, int $angle, int $move): void
{
    $mo->momx += Compat::fixedMul($move, Tables::fineCos($angle));
    $mo->momy += Compat::fixedMul($move, Tables::fineSin($angle));
}
```

`->` continua `->` (como no C), o shift sem sinal fica em `Tables::fineCos`, e o `+ 1` do índice Harbour desaparece.

---

## Tecnologia

| Camada | Este port | Harbour (`harbour_doom`) |
|---|---|---|
| Linguagem | PHP 8.1+ CLI (testado no 8.2.34) | Harbour / xHarbour |
| Janela, teclas, PCM | SDL2 (FFI) | Allegro 4.2.2 + GTALLEG |
| Blit da paleta / CRT | laço PHP → `SDL_UpdateTexture` ARGB8888 | C em `doomgeneric_allegro.prg` |
| MIDI (Windows) | winmm MCI (FFI) | Allegro MIDI / MCI |
| IWAD | os mesmos lumps | os mesmos |
| Build | nenhum (`php doom.php` ou `run.bat`) | `compile.bat` / `hbmk2` |

PHP necessário:

```
extension=ffi
ffi.enable=true
```

Opcional: `composer install` só para o autoload PSR-4. `src/autoload.php` funciona sem Composer.

SDL2: coloque **SDL2.dll** (64 bits) em `lib/` no Windows, ou defina `SDL2_PATH`. Veja `lib/README.txt`.

---

## Desempenho

Taxa típica de desenho no mesmo PC (320×200, janela, IWAD shareware). O jogo continua em 35 Hz (`TICRATE`); `-fps` mostra esse número.

| Port | FPS típico |
|---|---|
| Harbour (`doom_hb`) | ~12 |
| Python (`doom_python`) | ~8 |
| PHP (`php_doom`) | ~20 |
| Node (`node_doom`) | ~100 |

---

## Como rodar

PHP 8.1+, FFI ligado, SDL2 no path (ou `php_doom/lib/SDL2.dll` no Windows). Neste diretório:

```
php doom.php
php doom.php -iwad DOOM1.WAD
php doom.php -iwad ..\DOOM1.WAD -warp 1 1 -fps
php doom.php -iwad DOOM1.WAD -crt
```

Windows (PHP em zip sem `php.ini`, FFI desligado por padrão) — edite `run.bat` se o PHP não estiver em `C:\php-8.2.34-Win32-vs16-x64`:

```
run.bat
run.bat -fps
run.bat -iwad ..\DOOM1.WAD -warp 1 1 -crt
```

O `run.bat` liga o FFI com `-d` e, se você omitir `-iwad`, procura `DOOM1.WAD` aqui ou na pasta pai `doom_minimal`.

Linux: `sudo apt install php-cli php-ffi libsdl2-2.0-0`

macOS: `brew install php sdl2`

Sem `-iwad` procura um argumento `.wad`, depois `doom1.wad` / `DOOM1.WAD` / `doom.wad` / `doom2.wad` no diretório atual, em `DOOMWADDIR`, nesta pasta e na pasta pai `doom_minimal`.

---

## Teclas

Controles clássicos do DOOM (este port condensado; sem remap via `default.cfg`).

### Movimento e ações

| Tecla | Ação |
|---|---|
| Setas | Frente, trás, girar |
| **Shift** | Correr |
| **Alt** | Strafe (segurar) |
| **,** / **.** | Strafe esquerda / direita |
| **Ctrl** | Atirar (segurar repete; animação da arma + `A_ReFire`) |
| **Espaço** / **E** | Usar / abrir porta |
| **1** | Punho / motosserra (alterna) |
| **2**–**7** | Pistola, shotgun, chaingun, foguete, plasma, BFG |
| **Enter** | Começar a partir do título |
| **Tab** | Automap (liga/desliga). **+** / **-** zoom, **0** encaixa o mapa, **F** follow, **G** grade, **M** marca, **C** limpa marcas |
| **Esc** | Menu |
| **F2** | Salvar |
| **F3** | Carregar |
| **F11** | Liga/desliga FPS |
| **Alt+Enter** | Tela cheia |
| **+** / **-** | Screen Size (o mesmo de Options); zoom do automap enquanto ele está aberto. Escala da janela: arraste a janela ou Alt+Enter |

As opções **Screen Size** e **Graphic Detail** (HIGH/LOW) mudam a vista 3D (`R_SetViewSize`), não a escala da janela.

O movimento usa **somente as setas** (sem WASD), para as letras ficarem livres para os cheats.

### Cheats (só nostalgia)

Digite no teclado durante o jogo; não precisa de Enter:

| Código | Efeito |
|---|---|
| **IDDQD** | Modo Deus (_Degreelessness Mode_) |
| **IDKFA** | Todas as armas, munição, chaves e armadura |
| **IDFA** | Armas, munição e armadura (sem chaves) |
| **IDCLIP** / **IDSPISPOPD** | Sem colisão |
| **IDDT** | Cheat do automap (digite com o mapa aberto): todas as paredes, depois os things |

---

## Parâmetros de linha de comando

### IWAD

| Parâmetro | Descrição |
|---|---|
| `-iwad arquivo.wad` | IWAD a carregar (caminho ou só o nome) |
| `arquivo.wad` | Mesmo efeito, sem `-iwad` |

### Vídeo

| Parâmetro | Descrição |
|---|---|
| `-fullscreen` | Começa em janela de tela cheia |
| `-crt` | Visual de scanlines (família Harbour `-crt`). Fica nítido em escala 2× ou maior |
| `-fps` | Mostra os quadros por segundo no HUD (canto superior direito) e no título da janela. **F11** liga/desliga |

### Jogo

| Parâmetro | Descrição |
|---|---|
| `-warp e m` | Pula o título e começa no episódio `e` mapa `m` |
| `-skill n` | 0 baby … 4 nightmare (padrão 2, Hurt Me Plenty) |
| `-nomonsters` | Não spawna inimigos |

Flags só do Harbour **não** implementadas aqui: `-videoc`, `-scaling`, `-gfxmode`, `-colors`, `-nosound` / `-nosfx` / `-nomusic`, `-config`, rede/CD/joystick.

---

## Estrutura

```
doom.php             entrada: php doom.php
run.bat              atalho Windows (flags -d do FFI + IWAD padrão)
composer.json        PHP >=8.1, ext-ffi
src/                 pacote do motor (namespace Doom)
src/autoload.php     PSR-4 + aliases de classe (sem Composer)
lib/                 coloque SDL2.dll / libSDL2 aqui
screenshot/doom.png  screenshot do README
docs/                QR codes de doação
```

| Caminho | Vanilla / Harbour |
|---|---|
| `src/Compat.php` | `m_fixed`, `xhb_compat` |
| `src/Defs.php` | `doomdef`, `doomtype`, constantes `doomkeys` |
| `src/Bin.php` | leitores little-endian de WAD / mapa |
| `src/Keys.php` | `doomkeys` / keycodes SDL |
| `src/Wad.php` | `w_wad` |
| `src/Video.php` | `i_video`, `doomgeneric_allegro` (SDL2 + `-crt`) |
| `src/VVideo.php` | `v_video` |
| `src/Tables.php` | `tables` |
| `src/RData.php` | `r_data` |
| `src/Render.php` | `r_main` `r_bsp` `r_segs` `r_plane` `r_draw` |
| `src/World.php` | `p_setup` |
| `src/Collision.php` | `p_map` `p_maputl` `p_sight` (REJECT) |
| `src/Player.php` | `p_user` `p_pspr` |
| `src/Specials.php` | `p_spec` `p_doors` `p_plats` `p_floor` `p_switch` `p_telept` |
| `src/Mobj.php` | `info` `p_inter` |
| `src/Sprites.php` | `r_things` |
| `src/Enemy.php` | `p_enemy` (look / chase / ataque) |
| `src/Status.php` | `st_stuff` |
| `src/AmMap.php` | `am_map` |
| `src/Sound.php` | `i_sound`, CacheSFX, música MCI no Windows |
| `src/Mus2Mid.php` | `mus2mid.c` |
| `src/Menu.php` | `m_menu` |
| `src/Saveg.php` | `p_saveg` (nome de 24 bytes + JSON) |
| `src/WiStuff.php` | `wi_stuff` |
| `src/Wipe.php` | `f_wipe` melt |
| `src/Finale.php` | `f_finale` (texto + bunny scroll) |
| `src/Game.php` | `d_main` `g_game` `d_loop` `boot` |

---

## Linhagem

1. **id Software DOOM** (1993) — motor original
2. **Chocolate Doom / doomgeneric** — C portátil
3. **[harbour_doom](https://github.com/vagucs/harbour_doom)** — Harbour + Allegro 4.2.2 (`Doom_hb.exe`)
4. **doom_python** — Python + pygame, a partir desse port Harbour
5. **Esta árvore** — PHP 8 CLI + SDL2 FFI, a partir do mesmo Harbour (intenção de gameplay 100% dos `.prg`; quantidade de arquivos condensada)

---

## Doe

### Ethereum

`0x1b64038A2b1DB73ABd0068d8B9B0d1dC5a90C5F1`

![QR Code Ethereum](docs/qr-ethereum.png)

### PIX

Chave: `vagucs@bol.com.br`

![QR Code PIX](docs/qr-pix.png)
