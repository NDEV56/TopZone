# ═══════════════════════════════════════════════════════════════════════
#  TopZone Installer (PowerShell) — Setup Otomatis untuk Pemula
#  Deteksi tool yang kurang → pilih AUTO INSTALL atau MANUAL DOWNLOAD
# ═══════════════════════════════════════════════════════════════════════
[Console]::OutputEncoding = [Text.UTF8Encoding]::new()
$ErrorActionPreference = 'Continue'
Set-Location -Path $PSScriptRoot

# ─────────────────────────────────────────────────────────────────────────
#  Helper functions
# ─────────────────────────────────────────────────────────────────────────
function Write-Header($text) {
    Write-Host ""
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "  $text" -ForegroundColor Cyan
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
}

function Write-OK($text)    { Write-Host "  ✓ $text" -ForegroundColor Green }
function Write-Miss($text)  { Write-Host "  ✗ $text" -ForegroundColor Red }
function Write-Info($text)  { Write-Host "  ℹ $text" -ForegroundColor Yellow }
function Write-Step($text)  { Write-Host "  → $text" -ForegroundColor Blue }

function Test-Command($cmd) {
    return $null -ne (Get-Command $cmd -ErrorAction SilentlyContinue)
}

function Test-WingetAvailable {
    return Test-Command 'winget'
}

# ─────────────────────────────────────────────────────────────────────────
#  Catalog Tools — daftar lengkap tool yang TopZone butuhkan
# ─────────────────────────────────────────────────────────────────────────
$Tools = @(
    @{
        Name        = 'Node.js (LTS)'
        Check       = 'node'
        Required    = $true
        Description = 'Untuk menjalankan server.js & gui.js (panel kontrol)'
        WingetId    = 'OpenJS.NodeJS.LTS'
        DownloadUrl = 'https://nodejs.org/en/download'
        DirectUrl   = 'https://nodejs.org/dist/v20.18.0/node-v20.18.0-x64.msi'
        AutoCapable = $true
    },
    @{
        Name        = 'XAMPP (Apache + MySQL + PHP)'
        Check       = 'php'
        Required    = $true
        Description = 'Web server + database untuk menjalankan website PHP'
        WingetId    = 'ApacheFriends.Xampp.8.2'
        DownloadUrl = 'https://www.apachefriends.org/download.html'
        DirectUrl   = 'https://www.apachefriends.org/xampp-files/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe'
        AutoCapable = $true
        Note        = 'XAMPP butuh konfirmasi UAC (klik Yes saat dimintai izin admin)'
    },
    @{
        Name        = 'Git'
        Check       = 'git'
        Required    = $false
        Description = 'Untuk auto-update dari GitHub (opsional tapi disarankan)'
        WingetId    = 'Git.Git'
        DownloadUrl = 'https://git-scm.com/download/win'
        DirectUrl   = 'https://github.com/git-for-windows/git/releases/download/v2.46.2.windows.1/Git-2.46.2-64-bit.exe'
        AutoCapable = $true
    },
    @{
        Name        = 'ngrok (Tunnel)'
        Check       = 'ngrok'
        Required    = $false
        Description = 'Untuk membuat website lokal bisa diakses dari internet'
        WingetId    = 'Ngrok.Ngrok'
        DownloadUrl = 'https://ngrok.com/download'
        DirectUrl   = 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip'
        AutoCapable = $true
        Note        = 'Opsional — TopZone GUI sudah bisa install ngrok via npm (@ngrok/ngrok)'
    },
    @{
        Name        = 'Cloudflared (Tunnel Alternatif)'
        Check       = 'cloudflared'
        Required    = $false
        Description = 'Tunnel alternatif gratis (tanpa daftar)'
        WingetId    = 'Cloudflare.cloudflared'
        DownloadUrl = 'https://github.com/cloudflare/cloudflared/releases'
        DirectUrl   = 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
        AutoCapable = $true
    }
)

# ─────────────────────────────────────────────────────────────────────────
#  Banner
# ─────────────────────────────────────────────────────────────────────────
Clear-Host
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║          TopZone — Penginstal Otomatis Tools                 ║" -ForegroundColor Cyan
Write-Host "  ║                                                              ║" -ForegroundColor Cyan
Write-Host "  ║   Skrip ini akan cek tool yang kurang & bantu install        ║" -ForegroundColor Cyan
Write-Host "  ║   secara otomatis atau berikan link download manual.         ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan

# ─────────────────────────────────────────────────────────────────────────
#  Step 1: Detect what's installed
# ─────────────────────────────────────────────────────────────────────────
Write-Header "Langkah 1/3: Mengecek tool yang sudah terinstall"
$wingetOk = Test-WingetAvailable
if ($wingetOk) {
    Write-OK "winget tersedia (Windows 10+ — bisa AUTO install)"
} else {
    Write-Miss "winget TIDAK tersedia — install otomatis tidak mungkin"
    Write-Info "Update Windows ke versi terbaru, atau install App Installer dari Microsoft Store"
    Write-Info "Alternatif: gunakan opsi MANUAL DOWNLOAD"
}

$missing = @()
$installed = @()
foreach ($t in $Tools) {
    if (Test-Command $t.Check) {
        $ver = (& $t.Check --version 2>&1) -join ' ' -replace '\s+', ' '
        if ($ver.Length -gt 60) { $ver = $ver.Substring(0, 60) + '...' }
        Write-OK "$($t.Name) — $ver"
        $installed += $t
    } else {
        if ($t.Required) {
            Write-Miss "$($t.Name) — BELUM TERINSTALL (wajib)"
        } else {
            Write-Info "$($t.Name) — belum terinstall (opsional)"
        }
        $missing += $t
    }
}

if ($missing.Count -eq 0) {
    Write-Host ""
    Write-Host "  🎉 Semua tool sudah terinstall! TopZone siap dijalankan." -ForegroundColor Green
    Write-Host ""
    Write-Host "  Jalankan: " -NoNewline
    Write-Host "start.bat" -ForegroundColor Yellow
    Write-Host ""
    exit 0
}

# ─────────────────────────────────────────────────────────────────────────
#  Step 2: Pilihan install global
# ─────────────────────────────────────────────────────────────────────────
Write-Header "Langkah 2/3: Pilih cara install"
Write-Host "  $($missing.Count) tool perlu diinstall. Pilih cara:"
Write-Host ""
Write-Host "    [1] AUTO INSTALL semua via winget (paling cepat, butuh izin admin)" -ForegroundColor Green
Write-Host "    [2] DOWNLOAD installer ke folder Downloads (kamu klik install manual)" -ForegroundColor Yellow
Write-Host "    [3] BUKA LINK DOWNLOAD di browser (paling aman, install sendiri)" -ForegroundColor Cyan
Write-Host "    [4] PILIH PER TOOL (control penuh per item)" -ForegroundColor Magenta
Write-Host "    [5] BATAL" -ForegroundColor Gray
Write-Host ""

$choice = Read-Host "  Pilihan (1-5)"
if ([string]::IsNullOrWhiteSpace($choice)) { $choice = '4' }

# ─────────────────────────────────────────────────────────────────────────
#  Step 3: Execute install
# ─────────────────────────────────────────────────────────────────────────
$DownloadDir = [Environment]::GetFolderPath('UserProfile') + '\Downloads\TopZone-Setup'
function Ensure-DownloadDir {
    if (-not (Test-Path $DownloadDir)) { New-Item -ItemType Directory -Path $DownloadDir -Force | Out-Null }
}

function Install-Winget($tool) {
    if (-not (Test-WingetAvailable)) {
        Write-Miss "winget tidak tersedia. Pakai opsi DOWNLOAD atau MANUAL."
        return $false
    }
    Write-Step "winget install $($tool.WingetId) ..."
    try {
        $proc = Start-Process winget -ArgumentList @('install','--id', $tool.WingetId,
            '--silent','--accept-package-agreements','--accept-source-agreements',
            '--scope','user','-e') -Wait -PassThru -NoNewWindow
        if ($proc.ExitCode -eq 0) {
            Write-OK "$($tool.Name) terinstall via winget"
            return $true
        } else {
            Write-Miss "winget exit code $($proc.ExitCode) — coba opsi DOWNLOAD"
            return $false
        }
    } catch {
        Write-Miss "Error winget: $_"
        return $false
    }
}

function Download-Installer($tool) {
    if (-not $tool.DirectUrl) {
        Write-Info "Tidak ada direct URL — buka link manual"
        Start-Process $tool.DownloadUrl
        return $true
    }
    Ensure-DownloadDir
    $fileName = Split-Path $tool.DirectUrl -Leaf
    $dest = Join-Path $DownloadDir $fileName
    Write-Step "Download $($tool.Name) → $dest ..."
    try {
        $oldPref = $ProgressPreference
        $ProgressPreference = 'SilentlyContinue'
        Invoke-WebRequest -Uri $tool.DirectUrl -OutFile $dest -UseBasicParsing
        $ProgressPreference = $oldPref
        $size = (Get-Item $dest).Length / 1MB
        Write-OK "Download selesai ($([Math]::Round($size,1)) MB)"
        Write-Info "Lokasi: $dest"
        $openIt = Read-Host "  Buka installer sekarang? (y/n)"
        if ($openIt -match '^y') {
            Write-Step "Membuka installer..."
            Start-Process $dest
            Write-Info "Ikuti wizard di window installer. Selesai → kembali ke skrip ini."
        }
        return $true
    } catch {
        Write-Miss "Gagal download: $_"
        Write-Info "Coba opsi MANUAL atau download sendiri dari: $($tool.DownloadUrl)"
        return $false
    }
}

function Open-ManualLink($tool) {
    Write-Step "Buka $($tool.DownloadUrl) di browser ..."
    Start-Process $tool.DownloadUrl
    Write-OK "Browser dibuka. Download installer, lalu jalankan."
}

Write-Header "Langkah 3/3: Eksekusi"

switch ($choice) {
    '1' {
        foreach ($t in $missing) {
            Write-Host ""
            Write-Step "Install: $($t.Name)"
            if ($t.Note) { Write-Info $t.Note }
            Install-Winget $t | Out-Null
        }
    }
    '2' {
        foreach ($t in $missing) {
            Write-Host ""
            Write-Step "Download: $($t.Name)"
            if ($t.Note) { Write-Info $t.Note }
            Download-Installer $t | Out-Null
        }
        Write-Host ""
        Write-Info "Semua installer ada di: $DownloadDir"
        Write-Info "Klik dua kali tiap installer untuk install."
    }
    '3' {
        foreach ($t in $missing) {
            Write-Host ""
            Write-Step "Buka link: $($t.Name)"
            Open-ManualLink $t
        }
    }
    '4' {
        foreach ($t in $missing) {
            Write-Host ""
            Write-Host "  📦 $($t.Name)" -ForegroundColor Cyan
            Write-Host "     $($t.Description)" -ForegroundColor Gray
            if ($t.Note) { Write-Host "     ⚠ $($t.Note)" -ForegroundColor Yellow }
            Write-Host ""
            Write-Host "     [1] AUTO install (winget)" -ForegroundColor Green
            Write-Host "     [2] DOWNLOAD ke Downloads folder" -ForegroundColor Yellow
            Write-Host "     [3] BUKA LINK manual di browser" -ForegroundColor Cyan
            Write-Host "     [4] Skip" -ForegroundColor Gray
            $perChoice = Read-Host "  Pilihan untuk $($t.Name) (1-4)"
            switch ($perChoice) {
                '1' { Install-Winget $t | Out-Null }
                '2' { Download-Installer $t | Out-Null }
                '3' { Open-ManualLink $t }
                default { Write-Info "Skipped $($t.Name)" }
            }
        }
    }
    '5' {
        Write-Info "Dibatalkan."
        exit 0
    }
    default {
        Write-Miss "Pilihan tidak valid."
        exit 1
    }
}

# ─────────────────────────────────────────────────────────────────────────
#  Final Recap
# ─────────────────────────────────────────────────────────────────────────
Write-Header "Ringkasan Akhir"
$finalMissing = @()
foreach ($t in $Tools) {
    if (Test-Command $t.Check) {
        Write-OK "$($t.Name)"
    } else {
        if ($t.Required) {
            Write-Miss "$($t.Name) (WAJIB — belum terinstall)"
        } else {
            Write-Info "$($t.Name) (opsional — belum terinstall)"
        }
        if ($t.Required) { $finalMissing += $t }
    }
}

Write-Host ""
if ($finalMissing.Count -eq 0) {
    Write-Host "  🎉 Semua tool WAJIB sudah terinstall!" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Langkah selanjutnya:" -ForegroundColor Cyan
    Write-Host "    1. Tutup terminal ini" -ForegroundColor Gray
    Write-Host "    2. Klik dua kali file: " -NoNewline
    Write-Host "start.bat" -ForegroundColor Yellow
    Write-Host "    3. Ikuti panduan setup wizard di browser" -ForegroundColor Gray
} else {
    Write-Host "  ⚠ Masih ada tool WAJIB yang belum terinstall:" -ForegroundColor Yellow
    foreach ($t in $finalMissing) {
        Write-Host "    - $($t.Name): $($t.DownloadUrl)" -ForegroundColor Gray
    }
    Write-Host ""
    Write-Host "  💡 Tips: kalau winget gagal, pilih opsi [2] DOWNLOAD" -ForegroundColor Cyan
    Write-Host "          installer ke Downloads, lalu install manual." -ForegroundColor Cyan
}

Write-Host ""
Write-Host "  📖 Bantuan lengkap: lihat INSTALL.md atau PANDUAN_HOSTING.md" -ForegroundColor DarkGray
Write-Host ""

# Catat log
$logFile = Join-Path $PSScriptRoot 'logs\install-log.txt'
$logDir = Split-Path $logFile -Parent
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
"[$timestamp] install.ps1 selesai. Choice=$choice. Missing-after=$($finalMissing.Count)" |
    Out-File -FilePath $logFile -Append -Encoding utf8
