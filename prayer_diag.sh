#!/bin/bash
# Prayer Times App Diagnostic Script
# Saves output to ~/Desktop/prayer_diag_output.txt

OUT=~/Desktop/prayer_diag_output.txt
echo "=== Prayer Times App Diagnostic ===" > "$OUT"
echo "Run at: $(date)" >> "$OUT"
echo "" >> "$OUT"

# 1. User crontab
echo "====== 1. USER CRONTAB (crontab -l) ======" >> "$OUT"
crontab -l 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 2. System cron directories
echo "====== 2. /etc/cron* contents ======" >> "$OUT"
for d in /etc/cron.d /etc/cron.daily /etc/cron.hourly /etc/cron.weekly /etc/crontab; do
  if [ -e "$d" ]; then
    echo "--- $d ---" >> "$OUT"
    if [ -d "$d" ]; then
      ls -la "$d" >> "$OUT"
    else
      cat "$d" >> "$OUT"
    fi
  else
    echo "$d: does not exist" >> "$OUT"
  fi
done
echo "" >> "$OUT"

# 3. /var/cron
echo "====== 3. /var/cron/ ======" >> "$OUT"
ls -la /var/cron/ 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 4. User LaunchAgents
echo "====== 4. ~/Library/LaunchAgents/ ======" >> "$OUT"
ls -la ~/Library/LaunchAgents/ 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 5. System LaunchAgents
echo "====== 5. /Library/LaunchAgents/ ======" >> "$OUT"
ls -la /Library/LaunchAgents/ 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 6. System LaunchDaemons
echo "====== 6. /Library/LaunchDaemons/ ======" >> "$OUT"
ls -la /Library/LaunchDaemons/ 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 7. grep for prayer/salat/athan/php in LaunchAgents
echo "====== 7. grep prayer|salat|athan|php in LaunchAgents ======" >> "$OUT"
grep -ril "prayer\|salat\|athan\|php" \
  ~/Library/LaunchAgents/ \
  /Library/LaunchAgents/ \
  /Library/LaunchDaemons/ 2>/dev/null >> "$OUT"
echo "(no matches = nothing found)" >> "$OUT"
echo "" >> "$OUT"

# 8. Full contents of any matching plists
echo "====== 8. Contents of matching plist files ======" >> "$OUT"
grep -ril "prayer\|salat\|athan\|php" \
  ~/Library/LaunchAgents/ \
  /Library/LaunchAgents/ \
  /Library/LaunchDaemons/ 2>/dev/null | while read f; do
    echo "--- FILE: $f ---" >> "$OUT"
    cat "$f" >> "$OUT"
    echo "" >> "$OUT"
done
echo "" >> "$OUT"

# 9. launchctl list filtered for prayer/php
echo "====== 9. launchctl list (prayer/php/salat/athan related) ======" >> "$OUT"
launchctl list 2>&1 | grep -i "prayer\|php\|salat\|athan" >> "$OUT"
echo "(no matches = nothing found)" >> "$OUT"
echo "" >> "$OUT"

# 10. Full launchctl list (user domain)
echo "====== 10. Full launchctl list ======" >> "$OUT"
launchctl list 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 11. Check for any PHP executables
echo "====== 11. PHP locations ======" >> "$OUT"
which php 2>&1 >> "$OUT"
php --version 2>&1 >> "$OUT"
echo "" >> "$OUT"

# 12. Look for prayer PHP scripts in common locations
echo "====== 12. Find prayer/salat/athan PHP scripts ======" >> "$OUT"
find ~/Sites ~/Documents ~/Desktop /var/www /srv 2>/dev/null \
  -name "*.php" \( -iname "*prayer*" -o -iname "*salat*" -o -iname "*athan*" -o -iname "*adhan*" \) \
  -print 2>/dev/null >> "$OUT"
echo "(no matches = nothing found)" >> "$OUT"
echo "" >> "$OUT"

echo "=== DONE ===" >> "$OUT"
echo ""
echo "✅ Diagnostic complete. Output saved to: $OUT"
echo "   Open it with: open $OUT"
