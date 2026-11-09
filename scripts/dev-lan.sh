#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v php >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    echo "Error: both php and npm must be installed and available in PATH." >&2
    exit 1
fi

if command -v ip >/dev/null 2>&1; then
    LAN_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1; i<=NF; i++) if ($i == "src") {print $(i+1); exit}}')"
fi

if [[ -z "${LAN_IP:-}" ]] && command -v hostname >/dev/null 2>&1; then
    LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
fi

if [[ -z "${LAN_IP:-}" ]]; then
    echo "Could not detect the LAN IPv4 address. Set LAN_IP manually, e.g. LAN_IP=192.168.1.20 npm run dev:lan" >&2
    exit 1
fi

APP_PORT="${APP_PORT:-8000}"
VITE_PORT="${VITE_PORT:-5173}"
export APP_URL="http://${LAN_IP}:${APP_PORT}"
export VITE_DEV_SERVER_HOST="0.0.0.0"
export VITE_DEV_SERVER_ORIGIN="http://${LAN_IP}:${VITE_PORT}"
export VITE_HMR_HOST="${LAN_IP}"

echo
echo "LAN development servers"
echo "  App:  http://${LAN_IP}:${APP_PORT}"
echo "  Vite: http://${LAN_IP}:${VITE_PORT}"
echo
echo "Keep this terminal open. Press Ctrl+C to stop both servers."
echo "Make sure your phone and computer are on the same Wi-Fi network."
echo

pids=()
cleanup() {
    trap - EXIT INT TERM
    for pid in "${pids[@]:-}"; do
        kill "$pid" 2>/dev/null || true
    done
    wait 2>/dev/null || true
}
trap cleanup EXIT INT TERM

php artisan serve --host=0.0.0.0 --port="$APP_PORT" &
pids+=("$!")

npm run dev -- --host 0.0.0.0 --port "$VITE_PORT" &
pids+=("$!")

wait
