# ═══════════════════════════════════════════════════════════════
#  TopZone — One-click launcher for PowerShell (Windows)
#  Klik kanan file ini → Run with PowerShell
# ═══════════════════════════════════════════════════════════════
[Console]::OutputEncoding = [Text.UTF8Encoding]::new()
$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

Clear-Host
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║          TopZone Universal Launcher v3.0             ║" -ForegroundColor Cyan
Write-Host "  ║                                                      ║" -ForegroundColor Cyan
Write-Host "  ║   Setup otomatis untuk pemula. Tunggu sebentar...    ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

function Show-Step($n, $total, $msg) {
    Write-Host "[$n/$total] " -NoNewline -ForegroundColor Yellow
    Write-Host $msg
}

function Show-Success($msg) {
    Write-Host "    ✓ " -NoNewline -ForegroundColor Green
    Write-Host $msg
}

function Show-Error($msg) {
    Write-Host ""
    Write-Host "    ❌ $msg" -ForegroundColor Red
    Write-Host ""
}

# ─────────────────────────────────────────────────────────
#  STEP 1: Node.js
# ─────────────────────────────────────────────────────────
Show-Step 1 4 "Cek Node.js..."
$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    Show-Error "Node.js belum terinstall!"
    Write-Host "  Solusi:" -ForegroundColor Yellow
    Write-Host "  1. Buka https://nodejs.org/en/download"
    Write-Host "  2. Download versi LTS"
    Write-Host "  3. Install (Next-Next-Finish)"
    Write-Host "  4. Jalankan lagi file ini"
    Write-Host ""
    Read-Host "Tekan ENTER untuk keluar"
    exit 1
}
$nodeVer = (& node -v)
$nodeMajor = [int]($nodeVer -replace 'v', '' -split '\.')[0]
if ($nodeMajor -lt 16) {
    Show-Error "Node.js $nodeVer terlalu lama. Butuh >= v16. Update di nodejs.org"
    Read-Host "Tekan ENTER untuk keluar"
    exit 1
}
Show-Success "Node $nodeVer"

# ─────────────────────────────────────────────────────────
#  STEP 2: npm install
# ─────────────────────────────────────────────────────────
Show-Step 2 4 "Cek dependency..."
if (-not (Test-Path "node_modules")) {
    Write-Host "    Menginstall (sekali saja, tunggu 1-2 menit)..." -ForegroundColor Yellow
    npm install --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) {
        Show-Error "npm install gagal. Cek koneksi internet."
        Read-Host "Tekan ENTER untuk keluar"
        exit 1
    }
    Show-Success "Dependency terinstall."
}
else {
    Show-Success "Dependency sudah ada."
}

# ─────────────────────────────────────────────────────────
#  STEP 3: Konfigurasi
# ─────────────────────────────────────────────────────────
Show-Step 3 4 "Cek konfigurasi..."
if (-not (Test-Path ".env")) {
    Show-Success ".env belum ada — wizard akan muncul otomatis."
}
else {
    Show-Success ".env sudah ada."
}

# ─────────────────────────────────────────────────────────
#  STEP 4: Mode pilih
# ─────────────────────────────────────────────────────────
Show-Step 4 4 "Mempersiapkan panel..."
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║   Pilih cara menjalankan TopZone:                    ║" -ForegroundColor Cyan
Write-Host "  ║                                                      ║" -ForegroundColor Cyan
Write-Host "  ║     1. GUI (panel di browser)  ← rekomendasi pemula  ║" -ForegroundColor Cyan
Write-Host "  ║     2. CLI (jalan di terminal ini)                   ║" -ForegroundColor Cyan
Write-Host "  ║     3. Doctor (diagnosa lingkungan)                  ║" -ForegroundColor Cyan
Write-Host "  ║     4. Update (tarik versi terbaru dari GitHub)      ║" -ForegroundColor Cyan
Write-Host "  ║                                                      ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$choice = Read-Host "Pilih (1-4) [default: 1]"
if (-not $choice) { $choice = "1" }

Write-Host ""
switch ($choice) {
    "2" { node server.js }
    "3" { node server.js --doctor; Read-Host "Tekan ENTER untuk tutup" }
    "4" { node server.js --update; Read-Host "Tekan ENTER untuk tutup" }
    default { node gui.js }
}

Write-Host ""
Read-Host "Panel berhenti. Tekan ENTER untuk keluar"
