# Terminal Output Screenshot

This file contains a sample output of the `prayers-cli prayers:times` command.

To update this file, run the screenshot test:

```sh
vendor/bin/pest --filter=Terminal Output Screenshot
```

The output will be saved to `terminal_output.txt`.

---

```
# Example output (will be updated by test)
Fajr      : 04:15 (Next)
Sunrise   : 05:35
Dhuhr     : 11:45
Asr       : 15:10
Maghrib   : 18:20
Isha      : 19:40
Time remaining: 1 hour and 25 minutes

Next prayer: Fajr in 1 hour and 25 minutes
```
