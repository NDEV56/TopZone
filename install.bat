@echo off
REM ════════════════════════════════════════════════════════════════
REM  TopZone Installer — Auto-Setup untuk Pemula
REM  Klik dua kali file ini untuk install dependencies otomatis.
REM ════════════════════════════════════════════════════════════════
setlocal enabledelayedexpansion
chcp 65001 >nul 2>&1
title TopZone Installer
cd /d "%~dp0"

cls
echo.
echo  ╔══════════════════════════════════════════════════════════════╗
echo  ║         TopZone — Penginstal Otomatis Tools/Dependency       ║
echo  ║                                                              ║
echo  ║   Skrip ini akan deteksi tool yang kurang & bantu install   ║
echo  ╚══════════════════════════════════════════════════════════════╝
echo.

REM Pastikan PowerShell tersedia (Windows 7+ pasti ada)
where powershell >nul 2>&1
if errorlevel 1 (
    echo  ❌ PowerShell tidak ditemukan!
    echo     Windows 7/8/10/11 seharusnya sudah punya PowerShell bawaan.
    echo     Hubungi admin komputer kamu.
    pause
    exit /b 1
)

REM Jalankan installer PowerShell (lebih powerful daripada batch murni)
echo  Membuka installer PowerShell...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"

echo.
echo  Installer selesai.
pause
