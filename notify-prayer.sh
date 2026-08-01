#!/usr/bin/env bash
# notify-prayer.sh — Play Adhan and send macOS notification for a prayer time
# Usage: notify-prayer.sh <prayer_name>
# Prayer names: Fajr, Dhuhr, Asr, Maghrib, Isha

set -euo pipefail

PRAYER="${1:-}"
if [[ -z "$PRAYER" ]]; then
    echo "Usage: $0 <Fajr|Dhuhr|Asr|Maghrib|Isha>" >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AUDIO_DIR="$SCRIPT_DIR/storage/adhan/doha"

# Arabic prayer name mapping
arabic_name() {
    case "$1" in
        Fajr)    echo "الفجر" ;;
        Dhuhr)   echo "الظهر" ;;
        Asr)     echo "العصر" ;;
        Maghrib) echo "المغرب" ;;
        Isha)    echo "العشاء" ;;
        *)       echo "$1" ;;
    esac
}

ARABIC="$(arabic_name "$PRAYER")"
SUBTITLE="حان وقت صلاة $ARABIC"

# macOS notification via osascript
osascript <<APPLESCRIPT
display notification "$SUBTITLE" with title "🕌 وقت الصلاة" subtitle "$PRAYER Prayer Time" sound name "Ping"
APPLESCRIPT

# Audio: prefer per-prayer MP3, fall back to system Ping
AUDIO_FILE="$AUDIO_DIR/$(echo "$PRAYER" | tr '[:upper:]' '[:lower:]').mp3"
if [[ -f "$AUDIO_FILE" ]]; then
    afplay "$AUDIO_FILE" &
else
    afplay /System/Library/Sounds/Ping.aiff &
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Adhan played for $PRAYER ($ARABIC)"
