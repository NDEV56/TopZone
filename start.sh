#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════
#  TopZone — One-click launcher for macOS / Linux
#
#  Cara pakai:
#    chmod +x start.sh
#    ./start.sh
# ════════════════════════════════════════════════════════════

set -e
cd "$(dirname "$0")"

# Warna ANSI
RESET="\033[0m"; BOLD="\033[1m"; CYAN="\033[36m"; GREEN="\033[32m"
RED="\033[31m"; YELLOW="\033[33m"; DIM="\033[2m"

clear
echo
echo -e "${CYAN}${BOLD}  ╔══════════════════════════════════════════════════════╗${RESET}"
echo -e "${CYAN}${BOLD}  ║          TopZone Universal Launcher v3.0             ║${RESET}"
echo -e "${CYAN}${BOLD}  ║                                                      ║${RESET}"
echo -e "${CYAN}${BOLD}  ║   Setup otomatis untuk pemula. Tunggu sebentar...    ║${RESET}"
echo -e "${CYAN}${BOLD}  ╚══════════════════════════════════════════════════════╝${RESET}"
echo

step() { echo -e "${YELLOW}[$1/$2]${RESET} $3"; }
ok()   { echo -e "    ${GREEN}✓${RESET} $1"; }
err()  { echo; echo -e "    ${RED}❌ $1${RESET}"; echo; }

# ─── 1. Cek Node.js ─────────────────────────────────────
step 1 4 "Cek Node.js..."
if ! command -v node >/dev/null 2>&1; then
    err "Node.js belum terinstall!"
    echo -e "${YELLOW}  Cara install:${RESET}"
    if [ "$(uname)" = "Darwin" ]; then
        echo "    brew install node      (kalau punya Homebrew)"
        echo "    atau download dari https://nodejs.org/en/download"
    else
        echo "    # Ubuntu/Debian:"
        echo "    curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -"
        echo "    sudo apt install -y nodejs"
        echo
        echo "    # atau download manual: https://nodejs.org/en/download"
    fi
    echo
    read -rp "Tekan ENTER untuk keluar..."
    exit 1
fi

NODE_VER=$(node -v)
NODE_MAJOR=$(echo "$NODE_VER" | sed 's/v//' | cut -d. -f1)
if [ "$NODE_MAJOR" -lt 16 ]; then
    err "Node.js $NODE_VER terlalu lama. Butuh >= v16."
    echo -e "${DIM}  Update di https://nodejs.org${RESET}"
    read -rp "Tekan ENTER untuk keluar..."
    exit 1
fi
ok "Node $NODE_VER"

# ─── 2. npm install ─────────────────────────────────────
step 2 4 "Cek dependency..."
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}    Menginstall (sekali saja, tunggu 1-2 menit)...${RESET}"
    if ! npm install --no-audit --no-fund; then
        err "npm install gagal. Cek koneksi internet."
        read -rp "Tekan ENTER untuk keluar..."
        exit 1
    fi
    ok "Dependency terinstall."
else
    ok "Dependency sudah ada."
fi

# ─── 3. Konfigurasi ────────────────────────────────────
step 3 4 "Cek konfigurasi..."
if [ ! -f ".env" ]; then
    ok ".env belum ada — wizard akan muncul otomatis."
else
    ok ".env sudah ada."
fi

# ─── 4. Pilih mode ─────────────────────────────────────
step 4 4 "Mempersiapkan panel..."
echo
echo -e "${CYAN}  ╔══════════════════════════════════════════════════════╗${RESET}"
echo -e "${CYAN}  ║   Pilih cara menjalankan TopZone:                    ║${RESET}"
echo -e "${CYAN}  ║                                                      ║${RESET}"
echo -e "${CYAN}  ║     1. GUI (panel di browser)  ← rekomendasi pemula  ║${RESET}"
echo -e "${CYAN}  ║     2. CLI (jalan di terminal ini)                   ║${RESET}"
echo -e "${CYAN}  ║     3. Doctor (diagnosa lingkungan)                  ║${RESET}"
echo -e "${CYAN}  ║     4. Update (tarik versi terbaru dari GitHub)      ║${RESET}"
echo -e "${CYAN}  ║                                                      ║${RESET}"
echo -e "${CYAN}  ╚══════════════════════════════════════════════════════╝${RESET}"
echo

read -rp "Pilih (1-4) [default: 1]: " CHOICE
CHOICE=${CHOICE:-1}
echo

case "$CHOICE" in
    2) node server.js ;;
    3) node server.js --doctor; read -rp "Tekan ENTER untuk tutup..." ;;
    4) node server.js --update; read -rp "Tekan ENTER untuk tutup..." ;;
    *) node gui.js ;;
esac

echo
echo -e "${DIM}Panel berhenti.${RESET}"
