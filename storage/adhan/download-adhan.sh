#!/bin/bash

# Adhan Audio Downloader
# Downloads various Adhan variants for Prayers CLI

set -e

ADHAN_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "🕌 Adhan Audio Downloader"
echo "========================"
echo ""

# Create directories
mkdir -p "$ADHAN_DIR/makkah" "$ADHAN_DIR/madinah" "$ADHAN_DIR/generated"

download_file() {
    local url="$1"
    local output="$2"
    
    if [ -f "$output" ] && [ -s "$output" ]; then
        echo "✓ Already exists: $(basename $output)"
        return
    fi
    
    echo "⬇️  Downloading: $(basename $output)"
    curl -s -L -o "$output" "$url"
    
    # Check if file is valid (not an error page)
    if [ ! -s "$output" ] || file "$output" | grep -q "HTML\|XML"; then
        echo "⚠️  Failed to download: $(basename $output)"
        rm -f "$output"
        return 1
    fi
    
    echo "✓ Downloaded: $(basename $output) ($(du -h "$output" | cut -f1))"
}

echo "Downloading Makkah variant..."
echo "------------------------------"
# These are common archive.org links that may work
download_file "https://archive.org/download/AdzanAroundTheWorld/Adzan-SubuhMakkah1.mp3" "$ADHAN_DIR/makkah/fajr.mp3"
download_file "https://archive.org/download/AdzanAroundTheWorld/adzan-makkah1.mp3" "$ADHAN_DIR/makkah/dhuhr.mp3"
download_file "https://archive.org/download/AdzanAroundTheWorld/adzan-makkah2.mp3" "$ADHAN_DIR/makkah/asr.mp3"
download_file "https://archive.org/download/AdzanAroundTheWorld/Adzan%20from%20Makkah.mp3" "$ADHAN_DIR/makkah/maghrib.mp3"
download_file "https://archive.org/download/AdzanAroundTheWorld/AdzanMakkah.flac" "$ADHAN_DIR/makkah/isha.flac"

echo ""
echo "Downloading Madinah variant..."
echo "------------------------------"
download_file "https://archive.org/download/AdzanAroundTheWorld/Adzan-Madinah.ogg" "$ADHAN_DIR/madinah/fajr.ogg"
download_file "https://archive.org/download/AdzanAroundTheWorld/AdzanMadinah.ogg" "$ADHAN_DIR/madinah/dhuhr.ogg"
download_file "https://archive.org/download/AdzanAroundTheWorld/Adzan-Madinah2.ogg" "$ADHAN_DIR/madinah/asr.ogg"
download_file "https://archive.org/download/Mp3CollectionAdzan/Adzan%20Madinah.mp3" "$ADHAN_DIR/madinah/maghrib.mp3"
download_file "https://archive.org/download/Mp3CollectionAdzan/adzan%20-%20madinah.mp3" "$ADHAN_DIR/madinah/isha.mp3"

echo ""
echo "Converting OGG files to MP3 (if ffmpeg is available)..."
echo "-------------------------------------------------------"
if command -v ffmpeg &> /dev/null; then
    for f in "$ADHAN_DIR"/*/; do
        for ogg in "$f"*.ogg; do
            [ -f "$ogg" ] || continue
            mp3="${ogg%.ogg}.mp3"
            if [ ! -f "$mp3" ]; then
                echo "Converting: $(basename $ogg)"
                ffmpeg -i "$ogg" -y "$mp3" 2>/dev/null
            fi
        done
    done
    echo "✓ Conversion complete"
else
    echo "⚠️  ffmpeg not found - skipping conversion"
fi

echo ""
echo "✅ Done! Your Adhan files are ready."
echo ""
echo "Files downloaded:"
find "$ADHAN_DIR" -type f \( -name "*.mp3" -o -name "*.ogg" \) -exec du -h {} \; | sort -hr | head -20
