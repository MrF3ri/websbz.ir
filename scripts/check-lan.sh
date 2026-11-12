#!/usr/bin/env bash
set -u

APP_PORT="${APP_PORT:-8000}"
VITE_PORT="${VITE_PORT:-5173}"

if command -v ip >/dev/null 2>&1; then
    LAN_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for (i=1; i<=NF; i++) if ($i == "src") {print $(i+1); exit}}')"
fi
if [[ -z "${LAN_IP:-}" ]] && command -v hostname >/dev/null 2>&1; then
    LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
fi

if [[ -z "${LAN_IP:-}" ]]; then
    echo "FAIL: Could not detect LAN IPv4. Set LAN_IP manually."
    exit 1
fi

echo "Detected LAN IP: $LAN_IP"
echo

if command -v ss >/dev/null 2>&1; then
    echo "Listening sockets:"
    ss -lntp 2>/dev/null | awk -v a=":$APP_PORT" -v v=":$VITE_PORT" 'NR==1 || index($0,a) || index($0,v)'
    echo
else
    echo "NOTE: ss is not installed; skipping listener inspection."
fi

failed=0
for entry in "Laravel|http://127.0.0.1:$APP_PORT/" "Vite HMR client|http://127.0.0.1:$VITE_PORT/@vite/client" "Laravel via LAN IP|http://$LAN_IP:$APP_PORT/" "Vite via LAN IP|http://$LAN_IP:$VITE_PORT/@vite/client"; do
    name="${entry%%|*}"
    url="${entry#*|}"
    code="$(curl --connect-timeout 3 --max-time 6 -sS -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || true)"
    if [[ "$code" =~ ^[0-9]{3}$ ]] && [[ "$code" != "000" ]]; then
        printf 'PASS  %-22s HTTP %s\n' "$name" "$code"
    else
        printf 'FAIL  %-22s no HTTP response (%s)\n' "$name" "$url"
        failed=1
    fi
done

echo
if [[ "$failed" -eq 0 ]]; then
    echo "Local checks passed. Now open http://$LAN_IP:$APP_PORT on your phone."
    echo "If the phone cannot connect, check Wi-Fi client isolation and the computer firewall."
else
    echo "One or more local checks failed. Start the app with: npm run dev:lan"
fi

exit "$failed"
