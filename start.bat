@echo off
REM ════════════════════════════════════════════════════════════
REM  TopZone — One-click launcher for Windows
REM  Klik dua kali file ini untuk mulai panel kontrol.
REM ════════════════════════════════════════════════════════════
setlocal enabledelayedexpansion
chcp 65001 >nul 2>&1
title TopZone Launcher
cd /d "%~dp0"

cls
echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║          TopZone Universal Launcher v3.0             ║
echo  ║                                                      ║
echo  ║   Setup otomatis untuk pemula. Tunggu sebentar...    ║
echo  ╚══════════════════════════════════════════════════════╝
echo.

REM ───────────────────────────────────────────────────
REM  STEP 1: Cek Node.js — kalau belum, tawarkan auto-install
REM ───────────────────────────────────────────────────
echo [1/4] Cek Node.js...
where node >nul 2>&1
if errorlevel 1 (
    echo.
    echo  ❌ Node.js belum terinstall di komputer ini!
    echo.
    echo  Pilih cara install:
    echo    [1] Jalankan installer otomatis (install.bat) ← RECOMMENDED
    echo    [2] Install manual: buka https://nodejs.org/en/download
    echo    [3] Batal
    echo.
    set /p NODE_CHOICE="  Pilihan (1-3) [default: 1]: "
    if "!NODE_CHOICE!"=="" set NODE_CHOICE=1
    if "!NODE_CHOICE!"=="1" (
        echo  Menjalankan install.bat...
        call "%~dp0install.bat"
        echo  Selesai install. Tutup window ini dan buka lagi start.bat.
        pause
        exit /b 0
    )
    if "!NODE_CHOICE!"=="2" (
        start "" "https://nodejs.org/en/download"
        echo  Browser dibuka. Download LTS, install, lalu jalankan start.bat lagi.
        pause
        exit /b 0
    )
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node -v') do set NODEVER=%%i
echo     OK — Node !NODEVER!

REM Cek minimal v16
for /f "tokens=1 delims=." %%a in ("!NODEVER:v=!") do set NODE_MAJOR=%%a
if !NODE_MAJOR! LSS 16 (
    echo.
    echo  ⚠️  Node.js !NODEVER! terlalu lama. Butuh minimal v16.
    echo     Download versi terbaru di nodejs.org
    echo.
    pause
    exit /b 1
)

REM ───────────────────────────────────────────────────
REM  STEP 2: Cek node_modules
REM ───────────────────────────────────────────────────
echo [2/4] Cek dependency...
if not exist "node_modules" (
    echo     Menginstall dependency ^(sekali saja, tunggu 1-2 menit^)...
    call npm install --no-audit --no-fund
    if errorlevel 1 (
        echo.
        echo  ❌ npm install gagal. Pastikan koneksi internet stabil.
        echo.
        pause
        exit /b 1
    )
    echo     OK — dependency terinstall.
) else (
    echo     OK — dependency sudah ada.
)

REM ───────────────────────────────────────────────────
REM  STEP 3: Buat .env kalau belum ada (jangan overwrite!)
REM ───────────────────────────────────────────────────
echo [3/4] Cek konfigurasi...
if not exist ".env" (
    echo     File .env belum ada — wizard akan jalan otomatis.
)

REM ───────────────────────────────────────────────────
REM  STEP 4: Pilih mode launch
REM ───────────────────────────────────────────────────
echo [4/4] Mempersiapkan panel...
echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║   Pilih cara menjalankan TopZone:                    ║
echo  ║                                                      ║
echo  ║     1. GUI ^(panel di browser^)  ← rekomendasi pemula ║
echo  ║     2. CLI ^(jalan di terminal ini^)                  ║
echo  ║     3. Doctor ^(diagnosa lingkungan^)                 ║
echo  ║     4. Update ^(tarik versi terbaru dari GitHub^)     ║
echo  ║                                                      ║
echo  ╚══════════════════════════════════════════════════════╝
echo.
set /p CHOICE="Pilih (1-4) [default: 1]: "
if "!CHOICE!"=="" set CHOICE=1

echo.
if "!CHOICE!"=="2" (
    node server.js
) else if "!CHOICE!"=="3" (
    node server.js --doctor
    pause
) else if "!CHOICE!"=="4" (
    node server.js --update
    pause
) else (
    REM Default: GUI
    node gui.js
)

echo.
echo  Panel berhenti. Tekan tombol apa saja untuk tutup window ini.
pause >nul
endlocal
