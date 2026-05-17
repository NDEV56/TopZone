#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
#  TopZone Installer — Linux / macOS
#  Detect missing tools → pilih AUTO INSTALL atau MANUAL DOWNLOAD
# ════════════════════════════════════════════════════════════════
set -e
cd "$(dirname "$0")"

RESET="\033[0m"; BOLD="\033[1m"; CYAN="\033[36m"; GREEN="\033[32m"
RED="\033[31m"; YELLOW="\033[33m"; BLUE="\033[34m"; DIM="\033[2m"

ok()   { echo -e "  ${GREEN}✓${RESET} $1"; }
miss() { echo -e "  ${RED}✗${RESET} $1"; }
info() { echo -e "  ${YELLOW}ℹ${RESET} $1"; }
step() { echo -e "  ${BLUE}→${RESET} $1"; }

header() {
    echo
    echo -e "${CYAN}═══════════════════════════════════════════════════${RESET}"
    echo -e "${CYAN}  $1${RESET}"
    echo -e "${CYAN}═══════════════════════════════════════════════════${RESET}"
}

# Detect OS
OS=$(uname -s)
case "$OS" in
    Linux*)  IS_LINUX=1; PKG=""; if command -v apt >/dev/null;then PKG=apt;
              elif command -v dnf >/dev/null;then PKG=dnf; elif command -v pacman >/dev/null;then PKG=pacman; fi ;;
    Darwin*) IS_MAC=1; PKG=brew ;;
    *)       echo "OS tidak didukung: $OS"; exit 1 ;;
esac

clear
echo
echo -e "${CYAN}${BOLD}  ╔═══════════════════════════════════════════════════════════════╗${RESET}"
echo -e "${CYAN}${BOLD}  ║          TopZone Installer — $OS                              ║${RESET}"
echo -e "${CYAN}${BOLD}  ╚═══════════════════════════════════════════════════════════════╝${RESET}"

header "Langkah 1/2: Cek tool yang sudah terinstall"

declare -a MISSING_NAMES
declare -a MISSING_CMDS
declare -a MISSING_PKGS
declare -a MISSING_URLS

check_tool() {
    local name="$1" cmd="$2" pkg="$3" url="$4" required="$5"
    if command -v "$cmd" >/dev/null 2>&1; then
        local ver; ver=$("$cmd" --version 2>&1 | head -1)
        ok "$name — $ver"
    else
        if [ "$required" = "1" ]; then
            miss "$name — BELUM (wajib)"
        else
            info "$name — belum terinstall (opsional)"
        fi
        MISSING_NAMES+=("$name")
        MISSING_CMDS+=("$cmd")
        MISSING_PKGS+=("$pkg")
        MISSING_URLS+=("$url")
    fi
}

# Tool checks
check_tool "Node.js"     "node"  "nodejs npm"           "https://nodejs.org/en/download" "1"
check_tool "PHP 8.1+"    "php"   "php"                  "https://www.php.net/downloads"  "1"
check_tool "MySQL/MariaDB" "mysql" "mariadb-server"     "https://mariadb.org/download/"  "1"
check_tool "Apache"      "apache2" "apache2"            "https://httpd.apache.org/download.cgi" "0"
check_tool "Git"         "git"   "git"                  "https://git-scm.com/download"   "0"
check_tool "ngrok"       "ngrok" ""                     "https://ngrok.com/download"     "0"

if [ ${#MISSING_NAMES[@]} -eq 0 ]; then
    echo
    ok "Semua tool sudah terinstall! Jalankan: ./start.sh"
    exit 0
fi

header "Langkah 2/2: Install tool yang kurang"
echo "  ${#MISSING_NAMES[@]} tool perlu diinstall. Pilih cara:"
echo
echo -e "    ${GREEN}[1]${RESET} AUTO install via package manager ($PKG)"
echo -e "    ${YELLOW}[2]${RESET} BUKA LINK download di browser"
echo -e "    ${CYAN}[3]${RESET} PILIH per tool"
echo -e "    ${DIM}[4]${RESET} BATAL"
echo
read -rp "  Pilihan (1-4): " CHOICE
CHOICE=${CHOICE:-3}

install_pkg() {
    local pkg="$1"
    if [ -z "$pkg" ]; then
        info "Tool ini tidak ada di package manager. Skip."
        return
    fi
    step "Install: $pkg"
    case "$PKG" in
        apt)    sudo apt-get update && sudo apt-get install -y $pkg ;;
        dnf)    sudo dnf install -y $pkg ;;
        pacman) sudo pacman -S --noconfirm $pkg ;;
        brew)   brew install $pkg ;;
        *)      miss "Package manager tidak didukung. Install manual: $pkg" ;;
    esac
}

open_url() {
    local url="$1"
    if command -v xdg-open >/dev/null; then xdg-open "$url" &
    elif command -v open >/dev/null;     then open "$url" &
    else info "Buka manual di browser: $url"
    fi
}

case "$CHOICE" in
    1)
        for i in "${!MISSING_NAMES[@]}"; do
            echo; step "Install: ${MISSING_NAMES[$i]}"
            install_pkg "${MISSING_PKGS[$i]}"
        done
        ;;
    2)
        for i in "${!MISSING_URLS[@]}"; do
            echo; step "Buka link: ${MISSING_NAMES[$i]}"
            open_url "${MISSING_URLS[$i]}"
        done
        ;;
    3)
        for i in "${!MISSING_NAMES[@]}"; do
            echo
            echo "  📦 ${MISSING_NAMES[$i]}"
            echo -e "     ${GREEN}[1]${RESET} AUTO install (package manager)"
            echo -e "     ${YELLOW}[2]${RESET} BUKA LINK manual"
            echo -e "     ${DIM}[3]${RESET} Skip"
            read -rp "  Pilihan: " PC
            case "${PC:-3}" in
                1) install_pkg "${MISSING_PKGS[$i]}" ;;
                2) open_url    "${MISSING_URLS[$i]}" ;;
                *) info "Skipped ${MISSING_NAMES[$i]}" ;;
            esac
        done
        ;;
    *) info "Dibatalkan."; exit 0 ;;
esac

echo
echo -e "  ${GREEN}🎉 Installer selesai.${RESET}"
echo "  Selanjutnya: ./start.sh"
