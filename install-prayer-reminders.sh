#!/usr/bin/env bash
# install-prayer-reminders.sh — One-shot installer for the prayer launchd system
# Run this once from Terminal: bash ~/Projects/ws-prayers/prayers-0/install-prayer-reminders.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LAUNCH_AGENTS="$HOME/Library/LaunchAgents"
MASTER_LABEL="net.aldoy.prayer.daily-scheduler"
MASTER_PLIST="$LAUNCH_AGENTS/$MASTER_LABEL.plist"

echo "🕌 Prayer Reminder Installer"
echo "============================="

# 1. Make scripts executable
echo "→ Setting permissions..."
chmod +x "$SCRIPT_DIR/notify-prayer.sh"
chmod +x "$SCRIPT_DIR/schedule-prayers.sh"

# 2. Copy master plist (the daily 00:01 scheduler trigger)
echo "→ Installing master plist to $MASTER_PLIST ..."
mkdir -p "$LAUNCH_AGENTS"
cp "$SCRIPT_DIR/../../outputs/net.aldoy.prayer.daily-scheduler.plist" "$MASTER_PLIST" 2>/dev/null || \
cp "$(dirname "$SCRIPT_DIR")/../../net.aldoy.prayer.daily-scheduler.plist" "$MASTER_PLIST" 2>/dev/null || {
    # Write it inline if copy fails
    cat > "$MASTER_PLIST" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>net.aldoy.prayer.daily-scheduler</string>
    <key>ProgramArguments</key>
    <array>
        <string>/bin/bash</string>
        <string>/Users/aldoyh/Projects/ws-prayers/prayers-0/schedule-prayers.sh</string>
    </array>
    <key>StartCalendarInterval</key>
    <dict>
        <key>Hour</key>
        <integer>0</integer>
        <key>Minute</key>
        <integer>1</integer>
    </dict>
    <key>RunAtLoad</key>
    <false/>
    <key>StandardOutPath</key>
    <string>/tmp/net.aldoy.prayer.daily-scheduler.out</string>
    <key>StandardErrorPath</key>
    <string>/tmp/net.aldoy.prayer.daily-scheduler.err</string>
</dict>
</plist>
PLIST
}

# 3. Unload master if already loaded, then reload
echo "→ Loading master scheduler plist..."
launchctl unload "$MASTER_PLIST" 2>/dev/null || true
launchctl load "$MASTER_PLIST"
echo "  ✓ $MASTER_LABEL loaded"

# 4. Run schedule-prayers.sh right now to create today's prayer plists
echo "→ Running schedule-prayers.sh to fetch today's times..."
bash "$SCRIPT_DIR/schedule-prayers.sh"

# 5. Verify
echo ""
echo "→ Verifying loaded agents:"
launchctl list | grep aldoy || echo "  (none found — check launchctl manually)"

echo ""
echo "✅ Done! Prayer reminders are active."
echo "   • Daily reschedule: 00:01 AM every day"
echo "   • Logs: /tmp/schedule-prayers.log"
echo "   • Audio: $SCRIPT_DIR/storage/adhan/doha/"
