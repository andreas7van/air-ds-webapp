<?php
session_start();
require __DIR__ . '/../config/db.php';

// Never cache this endpoint.
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
  header('Location: login.php?redirect=home.php', true, 302);
  exit;
}

// Read and coerce the submitted fields.
$u     = (int) $_SESSION['user_id'];
$d     = (int) ($_POST['departure']   ?? 0);
$a     = (int) ($_POST['arrival']     ?? 0);
$fd    =        $_POST['flight_date'] ?? '';
$p     = (int) ($_POST['passengers']  ?? 1);
$seats = trim($_POST['seats']         ?? '');

$seatList = array_values(array_filter(array_map('trim', explode(',', $seats))));

// Basic sanity checks.
if (!$d || !$a || $d === $a || !$fd || !$seatList
    || $p < 1 || $p > 10 || count($seatList) !== $p
    || $fd < date('Y-m-d')) {
  $_SESSION['error'] = 'Σφάλμα στα στοιχεία κράτησης.';
  header('Location: home.php', true, 302);
  exit;
}

// Re-check seat availability server-side to prevent double booking.
$stmt = $pdo->prepare(
  'SELECT seats FROM reservations
   WHERE departure_airport_id = ? AND arrival_airport_id = ? AND flight_date = ?'
);
$stmt->execute([$d, $a, $fd]);
$taken = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $list) {
  foreach (explode(',', $list) as $id) {
    if (($id = trim($id)) !== '') {
      $taken[$id] = true;
    }
  }
}
$conflicts = array_filter($seatList, fn ($s) => isset($taken[$s]));
if ($conflicts) {
  $_SESSION['error'] = 'Οι θέσεις ' . implode(', ', $conflicts)
                     . ' μόλις κρατήθηκαν από άλλον χρήστη. Δοκιμάστε ξανά.';
  header('Location: home.php', true, 302);
  exit;
}

// Recompute the total cost server-side (never trust client-side math).
$stmt = $pdo->prepare('SELECT latitude, longitude, tax FROM airports WHERE id = ?');
$stmt->execute([$d]);
$dep = $stmt->fetch();
$stmt->execute([$a]);
$arr = $stmt->fetch();
if (!$dep || !$arr) {
  $_SESSION['error'] = 'Μη έγκυρα αεροδρόμια.';
  header('Location: home.php', true, 302);
  exit;
}

/** Great-circle distance in km (Haversine). */
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
  $R = 6371;
  $dLat = deg2rad($lat2 - $lat1);
  $dLon = deg2rad($lon2 - $lon1);
  $x = sin($dLat / 2) ** 2
     + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
  return $R * 2 * asin(min(1, sqrt($x)));
}

$distance   = haversine((float) $dep['latitude'], (float) $dep['longitude'],
                        (float) $arr['latitude'], (float) $arr['longitude']);
$flightCost = $distance / 10;                 // 1€ per 10 km, per passenger
$taxTotal   = $dep['tax'] + $arr['tax'];      // per passenger

// Premium rows: 1, 11, 12 → +20€, rows 2–10 → +10€.
$seatsCost = array_sum(array_map(function (string $id): int {
  $row = (int) $id;
  if (in_array($row, [1, 11, 12], true)) {
    return 20;
  }
  return ($row >= 2 && $row <= 10) ? 10 : 0;
}, $seatList));

$totalCost = $p * ($flightCost + $taxTotal) + $seatsCost;

// Persist the reservation.
$stmt = $pdo->prepare(
  'INSERT INTO reservations
     (user_id, departure_airport_id, arrival_airport_id, flight_date,
      passengers_count, seats, total_cost)
   VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$u, $d, $a, $fd, $p, implode(',', $seatList), $totalCost]);

$_SESSION['message'] = 'Η κράτηση ολοκληρώθηκε επιτυχώς.';
header('Location: my_trips.php', true, 302);
exit;
