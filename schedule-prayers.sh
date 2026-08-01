#!/usr/bin/env bash
# schedule-prayers.sh — Fetch today's prayer times and install launchd plists
# Runs at 00:01 daily via net.aldoy.prayer.daily-scheduler.plist

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NOTIFY_SCRIPT="$SCRIPT_DIR/notify-prayer.sh"
LAUNCH_AGENTS="$HOME/Library/LaunchAgents"
LOG="/tmp/schedule-prayers.log"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"; }

log "=== schedule-prayers.sh starting ==="

# Fetch prayer times from AlAdhan API
API_URL="https://api.aladhan.com/v1/timingsByCity?city=Manama&country=BH&method=10"
log "Fetching: $API_URL"

RESPONSE="$(curl -fsSL --max-time 30 "$API_URL")"
if [[ -z "$RESPONSE" ]]; then
    log "ERROR: Empty API response"
    exit 1
fi

# Parse times with python3 (guaranteed on macOS)
parse_times() {
    python3 - <<'PYEOF'
import json, sys, os

data = json.loads(os.environ["RESPONSE"])
timings = data["data"]["timings"]

prayers = ["Fajr", "Dhuhr", "Asr", "Maghrib", "Isha"]
for p in prayers:
    t = timings.get(p, "")
    if t:
        # Strip timezone suffix like "(+03)" if present
        t_clean = t.split(" ")[0]
        hour, minute = t_clean.split(":")
        print(f"{p} {int(hour)} {int(minute)}")
PYEOF
}

TIMES="$(RESPONSE="$RESPONSE" parse_times)"
if [[ -z "$TIMES" ]]; then
    log "ERROR: Failed to parse prayer times"
    exit 1
fi

log "Parsed prayer times:"
echo "$TIMES" | while read -r line; do log "  $line"; done

# Generate and load one plist per prayer
echo "$TIMES" | while IFS=' ' read -r PRAYER HOUR MINUTE; do
    PRAYER_LOWER="$(echo "$PRAYER" | tr '[:upper:]' '[:lower:]')"
    LABEL="net.aldoy.prayer.$PRAYER_LOWER"
    PLIST="$LAUNCH_AGENTS/$LABEL.plist"

    log "Writing $PLIST ($PRAYER at $HOUR:$(printf '%02d' $MINUTE))"

    cat > "$PLIST" <<PLISTEOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$LABEL</string>
    <key>ProgramArguments</key>
    <array>
        <string>$NOTIFY_SCRIPT</string>
        <string>$PRAYER</string>
    </array>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>$HOUR</integer>
        <key>Minute</key>
        <integer>$MINUTE</integer>
    </dict>
    <key>RunAtLoad</key>
    <false/>
    <key>StandardOutPath</key>
    <string>/tmp/$LABEL.out</string>
    <key>StandardErrorPath</key>
    <string>/tmp/$LABEL.err</string>
</dict>
</plist>
PLISTEOF

    # Unload existing (ignore errors if not loaded)
    launchctl unload "$PLIST" 2>/dev/null || true
    # Load new plist
    launchctl load "$PLIST"
    log "  Loaded: $LABEL"
done

log "=== Done. Prayer plists installed and loaded ==="
log "Verify with: launchctl list | grep aldoy"
