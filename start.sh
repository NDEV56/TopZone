#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════
#  TOPZONE — Quick Start (Linux / macOS)
# ════════════════════════════════════════════════════════════════════
#  Pakai: chmod +x start.sh && ./start.sh
# ════════════════════════════════════════════════════════════════════

set -e

# Colors
RED='\033[0;31m'
GRN='\033[0;32m'
YLW='\033[0;33m'
CYN='\033[0;36m'
NC='\033[0m' # no color

cat <<'BANNER'

 ████████╗ ██████╗ ██████╗ ███████╗ ██████╗ ███╗  ██╗███████╗
    ██╔══╝██╔═══██╗██╔══██╗╚════██║██╔═══██╗████╗ ██║██╔════╝
    ██║   ██║   ██║██████╔╝    ██╔╝██║   ██║██╔██╗██║█████╗
    ██║   ╚██████╔╝██║        ██║  ╚██████╔╝██║ ╚███║███████╗
    ╚═╝    ╚═════╝ ╚═╝        ╚═╝   ╚═════╝ ╚═╝  ╚══╝╚══════╝
                Quick Start Launcher

BANNER

# ─── Cek Node.js ─────────────────────────────────────────────────
if ! command -v node >/dev/null 2>&1; then
    echo -e "${RED}[ERROR]${NC} Node.js belum terinstall!"
    echo "        Install dari: https://nodejs.org/"
    echo "        Atau via package manager:"
    echo "          macOS  : brew install node"
    echo "          Ubuntu : sudo apt install nodejs npm"
    exit 1
fi
echo -e "${GRN}[OK]${NC} Node.js $(node -v) terdeteksi"

# ─── Cek node_modules ────────────────────────────────────────────
if [ ! -d "node_modules" ]; then
    echo -e "${CYN}[INFO]${NC} Install dependencies..."
    npm install
fi
echo -e "${GRN}[OK]${NC} Dependencies siap"

# ─── Cek .env ────────────────────────────────────────────────────
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo -e "${CYN}[INFO]${NC} File .env belum ada, copy dari .env.example..."
        cp .env.example .env
        echo -e "${YLW}[WARN]${NC} EDIT .env dulu untuk isi NGROK_AUTHTOKEN!"
        echo "        Daftar gratis: https://dashboard.ngrok.com/get-started/your-authtoken"
        ${EDITOR:-nano} .env
    fi
fi

# ─── Jalankan server ─────────────────────────────────────────────
echo
echo -e "${GRN}[GO]${NC} Menjalankan server..."
echo

exec node server.js
