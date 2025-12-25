[![islamicapi](/assets/images/main-logo.png)](/)

[Home](/) [Documentation](#)

### API Reference

- [Quick Start](/doc/)
- [Prayer Time](/doc/prayer-time/)
- [Fasting](/doc/fasting/)
- [Zakat Nisab](/doc/zakat-nisab/)
- [Asma ul Husna](/doc/asma-ul-husna/)

[Docs](/doc/) / Prayer Time

# Prayer Time API

This API provides endpoints to retrieve prayer times, Qibla direction, and Prohibited times for prayer.

### Request:

GET `https://islamicapi.com/api/v1/prayer-time/?lat={latitude}&lon={longitude}&method={method}&school={school}&api_key={YOUR_API_KEY}`

#### Query Parameters

## `lat` (string) *

Latitude coordinates of the user's location.

Example: `51.5194682`

## `lon` (string) *

Longitude coordinates of the user's location.

Example: `-0.1360365`

## `method` (integer)

Method for calculating prayer times based on geographical location and algorithms.

Example: `3`

Allowed values:  
1 - University of Islamic Sciences, Karachi  
2 - Islamic Society of North America  
3 - Muslim World League  
4 - Umm Al-Qura University, Makkah  
5 - Egyptian General Authority of Survey  
7 - Institute of Geophysics, Tehran  
8 - Gulf Region  
9 - Kuwait  
10 - Qatar  
11 - MUIS, Singapore  
12 - UOIF, France  
13 - Diyanet, Turkey  
14 - Russia  
15 - Moonsighting Committee Worldwide  
16 - Dubai (experimental)  
17 - JAKIM, Malaysia  
18 - Tunisia  
19 - Algeria  
20 - KEMENAG, Indonesia  
21 - Morocco  
22 - Lisbon, Portugal  
23 - Jordan  
0 - Jafari / Shia Ithna-Ashari

## `school` (integer)

School of thought for Asr prayer time.

Default: `1` (Shafi)

Allowed: `1` - Shafi, `2` - Hanafi

## `calender` (enum)

Calendar Calculation Method.

Default: `UAQ`

Allowed: `HJCoSA`, `UAQ`, `DIYANET`, `MATHEMATICAL`

JSON Response (success) Copy

```
{
                                        
    "code": 200,
    "status": "success",
    "data": {
        "times": {
            "Fajr": "03:48",
            "Sunrise": "05:17",
            "Dhuhr": "12:02",
            "Asr": "15:24",
            "Sunset": "18:48",
            "Maghrib": "18:48",
            "Isha": "20:18",
            "Imsak": "03:38",
            "Midnight": "00:02",
            "Firstthird": "22:17",
            "Lastthird": "01:47"
        },
        "date": {
            "readable": "28 May 2025",
            "timestamp": "1748390400",
            "hijri": {
                "date": "1446-12-01",
                "format": "YYYY-MM-DD",
                "day": "1",
                "weekday": {
                    "en": "Wednesday",
                    "ar": "الأربعاء"
                },
                "month": {
                    "number": 12,
                    "en": "Dhu al-Hijjah",
                    "ar": "ذُو ٱلْحِجَّة",
                    "days": 29
                },
                "year": "1446",
                "designation": {
                    "abbreviated": "AH",
                    "expanded": "Anno Hegirae"
                },
                "holidays": [],
                "adjustedHolidays": [],
                "method": "HJCOSA"
            },
            "gregorian": {
                "date": "2025-05-28",
                "format": "YYYY-MM-DD",
                "day": "28",
                "weekday": {
                    "en": "Wednesday"
                },
                "month": {
                    "number": 5,
                    "en": "May"
                },
                "year": "2025",
                "designation": {
                    "abbreviated": "AD",
                    "expanded": "Anno Domini"
                }
            }
        },
        "qibla": {
            "direction": {
                "degrees": 276.41,
                "from": "North",
                "clockwise": true
            },
            "distance": {
                "value": 4996.53,
                "unit": "km"
            }
        },
        "prohibited_times": {
            "sunrise": {
                "start": "05:15",
                "end": "05:30"
            },
            "noon": {
                "start": "11:57",
                "end": "12:07"
            },
            "sunset": {
                "start": "18:48",
                "end": "18:53"
            }
        },
        "timezone": {
            "name": "Asia/Manila",
            "utc_offset": "+08:00",
            "abbreviation": "PST"
        }
    }

}
```

JSON Response (error) Copy

```
{
                                        
    "code": 502,
    "status": "error",
    "message": "Unable to fetch prayer times"

}
```
