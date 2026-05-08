@echo off
:: ════════════════════════════════════════════════════════════════════
::  TOPZONE — Quick Start (Windows)
:: ════════════════════════════════════════════════════════════════════
::  Otomatis cek dependency + install + jalankan server
:: ════════════════════════════════════════════════════════════════════

setlocal enabledelayedexpansion
title TOPZONE Launcher
color 0B

echo.
echo  ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗
echo     ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝
echo     ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗
echo     ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗
echo     ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝
echo                Quick Start Launcher (Windows)
echo.

:: ─── Cek Node.js ─────────────────────────────────────────────────
where node >nul 2>nul
if errorlevel 1 (
    echo [ERROR] Node.js belum terinstall!
    echo         Download dari: https://nodejs.org/
    pause
    exit /b 1
)
echo [OK] Node.js terdeteksi

:: ─── Cek node_modules ────────────────────────────────────────────
if not exist "node_modules\" (
    echo [INFO] Dependencies belum terinstall, jalankan npm install...
    call npm install
    if errorlevel 1 (
        echo [ERROR] npm install gagal!
        pause
        exit /b 1
    )
)
echo [OK] Dependencies siap

:: ─── Cek .env ────────────────────────────────────────────────────
if not exist ".env" (
    if exist ".env.example" (
        echo [INFO] File .env belum ada, copy dari .env.example...
        copy /y ".env.example" ".env" >nul
        echo [WARN] EDIT .env DULU untuk isi NGROK_AUTHTOKEN sebelum lanjut!
        echo        Daftar gratis: https://dashboard.ngrok.com/get-started/your-authtoken
        notepad .env
    )
)

:: ─── Jalankan server ─────────────────────────────────────────────
echo.
echo [GO] Menjalankan server...
echo.
node server.js

pause
