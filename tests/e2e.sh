#!/bin/bash
# End-to-end tests for air-ds-webapp.
#
# Requirements: a running MySQL/MariaDB reachable as root via socket,
# PHP 8+ with pdo_mysql, curl. Run from the project root:
#
#   bash tests/e2e.sh
#
# The script resets the air_ds database, starts a temporary PHP dev
# server on port 8000, and exercises the full booking lifecycle.
set -u
cd "$(dirname "$0")/.." 

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  ✔ $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  ✘ $1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected [$3], got [$2])"; fi; }
grepok(){ if echo "$2" | grep -q "$3"; then ok "$1"; else bad "$1 (missing: $3)"; fi; }

echo "── Preparing database ──"
mysqladmin -u root ping >/dev/null 2>&1 || { echo "MariaDB is not running"; exit 1; }

mysql -u root < sql/schema.sql
mysql -u root air_ds < sql/seed.sql
mysql -u root -e "CREATE USER IF NOT EXISTS 'airds'@'127.0.0.1' IDENTIFIED BY 'airds'; GRANT ALL ON air_ds.* TO 'airds'@'127.0.0.1'; FLUSH PRIVILEGES;"
echo "  DB loaded: $(mysql -u root air_ds -N -e 'SELECT COUNT(*) FROM airports') airports, $(mysql -u root air_ds -N -e 'SELECT COUNT(*) FROM reservations') reservations"

DB_USER=airds DB_PASS=airds php -S 127.0.0.1:8000 -t public >/tmp/php-server.log 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null' EXIT
sleep 2
B=http://127.0.0.1:8000
J=/tmp/cookies.txt; rm -f $J
FUT=$(date -d "+60 days" +%Y-%m-%d)

echo "── 1. Public pages ──"
check "GET /home.php → 200"        "$(curl -s -o /tmp/home.html -w '%{http_code}' $B/home.php)" 200
grepok "home has hero + search"    "$(cat /tmp/home.html)" 'hero-eyebrow'
grepok "home loads new stylesheet" "$(cat /tmp/home.html)" 'assets/css/style.css'
check "GET / (index) → home"       "$(curl -s -o /dev/null -w '%{redirect_url}' $B/)" "$B/home.php"
check "GET /login.php → 200"       "$(curl -s -o /tmp/login.html -w '%{http_code}' $B/login.php)" 200
grepok "login has both panels"     "$(cat /tmp/login.html)" 'sign-up-container'

echo "── 2. Auth guards ──"
check "book.php w/o login → login.php"     "$(curl -s -o /dev/null -w '%{redirect_url}' $B/book.php)"     "$B/login.php?redirect=book.php"
check "my_trips.php w/o login → login.php" "$(curl -s -o /dev/null -w '%{redirect_url}' $B/my_trips.php)" "$B/login.php?redirect=my_trips.php"

echo "── 3. Registration validation ──"
R=$(curl -s -c $J -d "register=1&first_name=Test123&last_name=User&username=99999&email=bad&password=short" $B/login.php)
grepok "rejects numeric name"      "$R" 'μόνο γράμματα'
grepok "rejects weak password"     "$R" '8–64 χαρακτήρες'
grepok "rejects bad email"         "$R" 'έγκυρο email'
grepok "rejects numeric username"  "$R" 'τουλάχιστον ένα γράμμα'

echo "── 4. Successful registration ──"
rm -f $J
LOC=$(curl -s -c $J -o /dev/null -w '%{redirect_url}' -d "register=1&first_name=Νίκος&last_name=Τεστ&username=nikostest&email=nikos@test.gr&password=Secret123" $B/login.php)
check "register → redirect home" "$LOC" "$B/home.php"
N=$(mysql -u root air_ds -N -e "SELECT COUNT(*) FROM users WHERE username='nikostest'")
check "user row created" "$N" 1
H=$(mysql -u root air_ds -N -e "SELECT password FROM users WHERE username='nikostest'")
grepok "password stored as bcrypt" "$H" '^\$2y\$'

echo "── 5. Duplicate registration blocked ──"
R=$(curl -s -d "register=1&first_name=Νίκος&last_name=Τεστ&username=nikostest&email=other@test.gr&password=Secret123" $B/login.php)
grepok "duplicate username rejected" "$R" 'υπάρχει ήδη'

echo "── 6. Login with seeded demo user ──"
rm -f $J
LOC=$(curl -s -c $J -o /dev/null -w '%{redirect_url}' -d "login=1&username=demouser&password=Demo1234" $B/login.php)
check "demouser login → home" "$LOC" "$B/home.php"
R=$(curl -s -d "login=1&username=demouser&password=WrongPass1" $B/login.php)
grepok "wrong password rejected" "$R" 'Λάθος username'

echo "── 7. My Trips shows seeded reservations ──"
R=$(curl -s -b $J $B/my_trips.php)
grepok "boarding pass rendered" "$R" 'boarding-pass'
grepok "seeded seats 4A shown"  "$R" '>4A<'
grepok "ATH code shown"         "$R" '>ATH<'
grepok "cancel button present"  "$R" 'cancel.php'

echo "── 8. Flight search ──"
R=$(curl -s -b $J -d "departure=1&arrival=1&flight_date=$FUT&passengers=2" $B/home.php)
grepok "same airports rejected" "$R" 'διαφορετικά'
R=$(curl -s -b $J -d "departure=1&arrival=2&flight_date=2020-01-01&passengers=2" $B/home.php)
grepok "past date rejected" "$R" 'παρελθοντική'
LOC=$(curl -s -b $J -c $J -o /dev/null -w '%{redirect_url}' -d "departure=1&arrival=2&flight_date=$FUT&passengers=2" $B/home.php)
check "valid search → book.php" "$LOC" "$B/book.php"

echo "── 9. Booking page ──"
R=$(curl -s -b $J $B/book.php)
grepok "flight summary ATH→CDG" "$R" '>CDG<'
grepok "BookingData injected"   "$R" 'window.BookingData'
grepok "seat 4A is unavailable (seeded, same date/route)" "$R" '"4A"'
grepok "seat map JSON loaded"   "$R" 'seatsData'
grepok "distance computed"      "$R" 'dist:'

echo "── 10. Confirm: seat conflict is blocked ──"
LOC=$(curl -s -b $J -c $J -o /dev/null -w '%{redirect_url}' -d "departure=1&arrival=2&flight_date=$FUT&passengers=2&seats=4A,4B" $B/confirm.php)
check "conflicting seats → back to home" "$LOC" "$B/home.php"
N=$(mysql -u root air_ds -N -e "SELECT COUNT(*) FROM reservations")
check "no new row inserted on conflict" "$N" 2

echo "── 11. Confirm: successful booking ──"
LOC=$(curl -s -b $J -c $J -o /dev/null -w '%{redirect_url}' -d "departure=1&arrival=2&flight_date=$FUT&passengers=2&seats=5A,5B" $B/confirm.php)
check "valid booking → my_trips" "$LOC" "$B/my_trips.php"
ROW=$(mysql -u root air_ds -N -e "SELECT seats, passengers_count, total_cost FROM reservations ORDER BY id DESC LIMIT 1")
grepok "row stored with seats 5A,5B" "$ROW" '5A,5B'
# Independent cost check: 2*(dist/10 + 350) + 20 (two seats in rows 2–10)
EXPECT=$(php -r '
  $R=6371; $la1=37.937225;$lo1=23.945238;$la2=49.009724;$lo2=2.547778;
  $dLat=deg2rad($la2-$la1);$dLon=deg2rad($lo2-$lo1);
  $a=sin($dLat/2)**2+cos(deg2rad($la1))*cos(deg2rad($la2))*sin($dLon/2)**2;
  $d=$R*2*asin(min(1,sqrt($a)));
  printf("%.2f", 2*($d/10+350)+20);
')
GOT=$(echo "$ROW" | awk '{print $3}')
check "server-side price matches independent calc (${EXPECT}€)" "$GOT" "$EXPECT"
R=$(curl -s -b $J $B/my_trips.php)
grepok "success flash shown" "$R" 'ολοκληρώθηκε επιτυχώς'
grepok "new booking listed"  "$R" '>5A<'

echo "── 12. Cancellation rules ──"
NEWID=$(mysql -u root air_ds -N -e "SELECT id FROM reservations ORDER BY id DESC LIMIT 1")
SOONID=$(mysql -u root air_ds -N -e "SELECT id FROM reservations WHERE DATEDIFF(flight_date, CURDATE()) < 30 LIMIT 1")
LOC=$(curl -s -b $J -o /dev/null -w '%{redirect_url}' -d "reservation_id=$SOONID" $B/cancel.php)
R=$(curl -s -b $J $B/my_trips.php)
grepok "cancel <30 days refused" "$R" '30+'
N=$(mysql -u root air_ds -N -e "SELECT COUNT(*) FROM reservations WHERE id=$SOONID")
check "near-term reservation not deleted" "$N" 1
curl -s -b $J -o /dev/null -d "reservation_id=$NEWID" $B/cancel.php
N=$(mysql -u root air_ds -N -e "SELECT COUNT(*) FROM reservations WHERE id=$NEWID")
check "60-days-out reservation cancelled" "$N" 0
R=$(curl -s -b $J $B/my_trips.php)
grepok "cancel flash shown" "$R" 'ακυρώθηκε επιτυχώς'

echo "── 13. Ownership guard on cancel ──"
OTHERID=$(mysql -u root air_ds -N -e "SELECT id FROM reservations LIMIT 1")
rm -f /tmp/j2; curl -s -c /tmp/j2 -o /dev/null -d "login=1&username=nikostest&password=Secret123" $B/login.php
curl -s -b /tmp/j2 -o /dev/null -d "reservation_id=$OTHERID" $B/cancel.php
N=$(mysql -u root air_ds -N -e "SELECT COUNT(*) FROM reservations WHERE id=$OTHERID")
check "other user's reservation untouched" "$N" 1

echo "── 14. Logout ──"
LOC=$(curl -s -b $J -c $J -o /dev/null -w '%{redirect_url}' $B/logout.php)
check "logout → home" "$LOC" "$B/home.php"
LOC=$(curl -s -b $J -o /dev/null -w '%{redirect_url}' $B/my_trips.php)
check "session actually destroyed" "$LOC" "$B/login.php?redirect=my_trips.php"

echo "── 15. Open-redirect guard ──"
R=$(curl -s -o /dev/null -w '%{redirect_url}' -d "login=1&username=demouser&password=Demo1234" "$B/login.php?redirect=https://evil.example")
check "external redirect target ignored" "$R" "$B/home.php"

echo "── 16. PHP error scan ──"
ERRS=$(grep -ciE "PHP (Warning|Fatal|Deprecated|Notice)" /tmp/php-server.log || true)
check "no PHP warnings/errors in server log" "$ERRS" 0

echo
echo "════════ RESULT: $PASS passed, $FAIL failed ════════"
[ $FAIL -eq 0 ]
