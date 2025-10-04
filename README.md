
<p align="center">
  <img src="https://img.icons8.com/ios-filled/100/000000/mosque.png" height="90" alt="Mosque Icon"/>
</p>

# 🕌 Prayers CLI

**Prayers CLI** is a modern, fast, and flexible command-line tool to fetch and display daily Islamic prayer times, calendars, and related information for any city and country, powered by the Aladhan API.

![Banner Image](prayers-cli-banner.png)

---

<div align="center">
  <h2>🕌 Daily Prayer Timings</h2>
  <p>Automatically updated every day after midnight.</p>
  <div align="center" style="background: #f8fafc; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 1.5em 1em; max-width: 420px; margin: 1.5em auto;">
  
<!-- PRAYER-TIMINGS-START -->
```text
Fajr      : 04:15 (Next)
Sunrise   : 05:35
Dhuhr     : 11:45
Asr       : 15:10
Maghrib   : 18:20
Isha      : 19:40
Time remaining: 1 hour and 25 minutes

Next prayer: Fajr in 1 hour and 25 minutes
```
<!-- PRAYER-TIMINGS-END -->
  </div>
</div>

---

## Features

- 🌍 Fetches accurate prayer times for any city and country
- 📅 Supports daily timings, monthly calendars, and Hijri calendars
- 🕰️ Shows current date, time, and timestamp in any timezone
- 🧮 Lists available calculation methods
- ⚡ Fast, reliable, and easy to use
- 📝 Can be automated to update README or other files

## Usage

Run the CLI with various options:

```sh
php prayers-cli prayers:times [--city=CityName] [--country=CountryName] [--date=DD-MM-YYYY] [--action=timings|calendar|hijricalendar|currentdate|currenttime|currenttimestamp|methods] [--method=ID] [--timezone=Zone] [--next]
```

### Examples

- Show today’s prayer times for Manama, Bahrain:
  ```sh
  php prayers-cli prayers:times
  ```
- Show prayer times for a specific date and city:
  ```sh
  php prayers-cli prayers:times --city=Cairo --country=Egypt --date=05-10-2025
  ```
- Show the full monthly calendar:
  ```sh
  php prayers-cli prayers:times --action=calendar --month=10 --year=2025
  ```
- List available calculation methods:
  ```sh
  php prayers-cli prayers:times --action=methods
  ```

## How it works

This CLI fetches data from the [Aladhan API](https://aladhan.com/prayer-times-api) and displays it in a user-friendly format. It supports caching, error handling, and can be integrated into automation workflows (see `.github/workflows/`).

## License

MIT. See [LICENSE](LICENSE).
