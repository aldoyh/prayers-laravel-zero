# 🕌 Adhan Player - Quick Reference

## Overview
The Prayers CLI now includes a comprehensive Adhan player with support for multiple variants and audio players.

## Available Variants

| Variant | Description | Status |
|---------|-------------|--------|
| `doha` | Doha, Qatar - Complete 5 prayers | ✅ Available |
| `makkah` | Makkah Al-Mukarramah | ⚠️ Partial (add more files) |
| `madinah` | Madinah Al-Munawwarah | ⚠️ Partial (add more files) |
| `generic` | Simple notification tones | ✅ Available |

## Audio Players

| Player | Platform | Requirements | Notes |
|--------|----------|--------------|-------|
| `afplay` | macOS | Built-in | Default on macOS |
| `ffplay` | Cross-platform | Requires ffmpeg | More flexible |
| `auto` | Any | Detects available | Default mode |

## Quick Commands

### Play specific prayer directly:
```bash
# Play Fajr Adhan with Doha variant
php prayers-cli prayers:times --action=playadhan --prayer=Fajr

# Play Dhuhr with Makkah variant using afplay
php prayers-cli prayers:times --action=playadhan --prayer=Dhuhr --variant=makkah --player=afplay

# Play Asr with ffplay
php prayers-cli prayers:times --action=playadhan --prayer=Asr --player=ffplay
```

### Interactive mode (menu):
```bash
php prayers-cli prayers:times --action=playadhan
```

### Play all 5 prayers:
```bash
php prayers-cli prayers:times --action=playadhan --variant=doha
# Then select "All" in the interactive menu
```

## Adding More Adhan Variants

### Option 1: Use the download script
```bash
cd storage/adhan
./download-adhan.sh
```

### Option 2: Manual download
1. Create directory: `storage/adhan/<variant_name>/`
2. Add MP3/OGG files for each prayer:
   - `fajr.mp3`
   - `dhuhr.mp3`
   - `asr.mp3`
   - `maghrib.mp3`
   - `isha.mp3`
3. Update the `$variants` array in `PrayersCommand.php`

### Option 3: Convert existing files
```bash
# Convert OGG to MP3
ffmpeg -i input.ogg -y output.mp3

# Adjust volume
ffmpeg -i input.mp3 -af 'volume=1.5' output_loud.mp3

# Trim silence
ffmpeg -i input.mp3 -af silenceremove=1:0:-50dB output_trim.mp3
```

## File Structure
```
storage/adhan/
├── doha/              # Doha, Qatar variant
│   ├── fajr.mp3
│   ├── dhuhr.mp3
│   ├── asr.mp3
│   ├── maghrib.mp3
│   └── isha.mp3
├── makkah/            # Makkah variant (add files)
├── madinah/           # Madinah variant (add files)
├── generated/         # Generic tones
│   ├── fajr_alert.mp3
│   └── prayer_tone.mp3
├── .gitignore
└── download-adhan.sh
```

## Sources for Adhan Audio

### Internet Archive Collections:
- [Adhan Recordings from Doha, Qatar](https://archive.org/details/adhan.recordings.from.doha.qatar)
- [Adzan Around The World](https://archive.org/details/AdzanAroundTheWorld)
- [MP3 Collection Adzan](https://archive.org/details/Mp3CollectionAdzan)
- [Fajr Azan Collection](https://archive.org/details/FajrAzan)

### Other Sources:
- [IslamCan Adhan](https://www.islamcan.com/audio/adhan/index.shtml)
- [The Wahy Project](https://thewahyproject.com/2011/09/05/adhan-recordings/)

## Troubleshooting

### No sound playing
- Check if audio player is installed: `which afplay` or `which ffplay`
- Verify file exists: `ls -lh storage/adhan/doha/fajr.mp3`
- Try different player: `--player=ffplay` or `--player=afplay`

### File not found warnings
- The system will automatically fallback to Doha variant
- Download missing files using `./download-adhan.sh`

### Audio too loud/quiet
- Use ffmpeg to adjust: `ffmpeg -i input.mp3 -af 'volume=1.5' output.mp3`

## Future Enhancements
- [ ] Add more Makkah recordings (different reciters)
- [ ] Add Madinah Al-Masjid an-Nabawi recordings
- [ ] Support for custom user-provided Adhan files
- [ ] Volume control option
- [ ] Repeat/loop playback
- [ ] Schedule Adhan playback at prayer times
