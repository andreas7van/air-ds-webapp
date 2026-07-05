# Air DS — Flight Booking Web App

A flight search & booking demo application built with **vanilla PHP 8, MySQL/MariaDB, and plain JavaScript** — no frameworks. Users register, search for a flight between European airports, pick their seats on an interactive A320neo seat map, and manage their reservations.

> The UI is in Greek; the codebase and documentation are in English.

## Features

- **Flight search** with server- and client-side validation (distinct airports, future dates, 1–10 passengers)
- **Interactive seat map**: seat buttons positioned over an A320neo cabin image from normalized JSON coordinates, with live availability per route/date
- **Dynamic pricing**: Haversine great-circle distance (1€ / 10 km) + per-airport taxes + premium-row seat surcharges, recomputed server-side on submission
- **Double-booking protection**: seat availability is re-checked on the server before a reservation is inserted
- **Authentication**: registration & login with `password_hash`/`password_verify`, session regeneration, and an allowlisted post-login redirect
- **My Trips**: boarding-pass styled reservation cards with cancellation (allowed only 30+ days before departure)
- Responsive layout, keyboard-visible focus states, `prefers-reduced-motion` support

## Tech stack

| Layer     | Choice                                   |
|-----------|------------------------------------------|
| Backend   | PHP 8+ (PDO, prepared statements)        |
| Database  | MySQL / MariaDB                          |
| Frontend  | HTML5, CSS3 (custom design system), vanilla JS |
| Fonts     | Sora · Inter · IBM Plex Mono             |

## Getting started

**Requirements:** PHP 8.0+ with `pdo_mysql`, and MySQL or MariaDB.

```bash
# 1. Create the database and load sample data
mysql -u root < sql/schema.sql
mysql -u root air_ds < sql/seed.sql

# 2. Start the app (from the project root)
php -S localhost:8000 -t public

# 3. Open http://localhost:8000
```

Database credentials are read from environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) and default to a local root/no-password setup — see `config/db.php`.

**Demo account:** username `demouser`, password `Demo1234`.

## Project structure

```
air-ds-webapp/
├── config/
│   └── db.php              # PDO connection (env-based credentials)
├── includes/
│   ├── header.php          # Shared <head>
│   ├── navbar.php          # Navigation bar
│   └── footer.php          # Footer with contact & map
├── public/                 # Web root
│   ├── index.php           # → home.php
│   ├── home.php            # Flight search
│   ├── login.php           # Login / registration (sliding panel UI)
│   ├── book.php            # Passenger details + seat selection
│   ├── confirm.php         # Server-side validation, pricing & insert
│   ├── my_trips.php        # Reservation list & cancellation
│   ├── cancel.php          # Cancellation endpoint (POST)
│   ├── logout.php
│   └── assets/
│       ├── css/style.css   # Design system (boarding-pass aesthetic)
│       ├── js/main.js      # All client-side behaviour
│       ├── data/seatmap.json  # Normalized seat coordinates
│       └── images/         # Logo & A320neo cabin plan
└── sql/
    ├── schema.sql
    └── seed.sql
```

## Booking flow

1. **Search** (`home.php`) — pick airports, date, passengers; the search is stored in the session.
2. **Passengers & seats** (`book.php`) — enter passenger names, then select one seat per passenger on the cabin map. Taken seats for that route/date are disabled.
3. **Summary & confirm** (`confirm.php`) — the client shows a cost breakdown; the server independently revalidates inputs, re-checks seat availability, recomputes the price, and stores the reservation.
4. **My Trips** (`my_trips.php`) — review reservations and cancel (30+ days before the flight only).

## Possible improvements

- CSRF tokens on state-changing forms
- Wrapping the seat-conflict check + insert in a transaction with row locking
- A `seats` join table instead of a comma-separated column
- Rate limiting on login attempts

## License

MIT — see [LICENSE](LICENSE).

## Tests

An end-to-end suite (46 assertions) covers the full booking lifecycle — auth guards, validation, seat conflicts, pricing, and cancellation rules:

```bash
bash tests/e2e.sh   # requires local MySQL/MariaDB + PHP 8 + curl
```
