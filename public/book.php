<?php
session_start();
require __DIR__ . '/../config/db.php';

// Require an authenticated user.
if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect=book.php', true, 302);
    exit;
}

// Require a valid search from home.php.
$search = $_SESSION['search'] ?? [];
$departure   = $search['departure']   ?? '';
$arrival     = $search['arrival']     ?? '';
$flightDate  = $search['flight_date'] ?? '';
$passengers  = (int) ($search['passengers'] ?? 0);

if (!$departure || !$arrival || !$flightDate || $passengers < 1) {
    header('Location: home.php', true, 302);
    exit;
}

// Load both airports in one query.
$stmt = $pdo->prepare(
  'SELECT id, name, code, latitude, longitude, tax
   FROM airports
   WHERE id IN (?, ?)'
);
$stmt->execute([$departure, $arrival]);
$rows = $stmt->fetchAll();
if (count($rows) !== 2) {
    http_response_code(400);
    exit('Μη έγκυρα αεροδρόμια.');
}
$airports = array_column($rows, null, 'id');
$dep = $airports[$departure];
$arr = $airports[$arrival];

/**
 * Great-circle distance between two coordinates (Haversine), in km.
 */
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * asin(min(1, sqrt($a)));
}
$distance = haversine(
    (float) $dep['latitude'], (float) $dep['longitude'],
    (float) $arr['latitude'], (float) $arr['longitude']
);

// Seats already taken on this route/date (stored as comma-separated lists).
$stmt = $pdo->prepare(
  'SELECT seats FROM reservations
   WHERE departure_airport_id = ? AND arrival_airport_id = ? AND flight_date = ?'
);
$stmt->execute([$departure, $arrival, $flightDate]);
$used = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $list) {
    foreach (explode(',', $list) as $id) {
        if (($id = trim($id)) !== '') {
            $used[] = $id;
        }
    }
}
$unavailable = array_values(array_unique($used));

// First passenger defaults to the logged-in user.
$stmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Seat-map coordinates (normalised x/y over the aircraft image).
$seatMapJson = file_get_contents(__DIR__ . '/assets/data/seatmap.json');

$pageTitle = 'Air DS — Κράτηση πτήσης';
require __DIR__ . '/../includes/header.php';
?>
<body>
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <main class="container">
    <h1>Κράτηση πτήσης</h1>

    <!-- Boarding-pass style flight summary -->
    <section class="boarding-pass fade-in-up">
      <div class="bp-main">
        <div class="bp-route">
          <div class="bp-airport">
            <span class="code"><?= htmlspecialchars($dep['code']) ?></span>
            <span class="bp-city"><?= htmlspecialchars($dep['name']) ?></span>
          </div>
          <span class="bp-plane" aria-hidden="true">✈</span>
          <div class="bp-airport">
            <span class="code"><?= htmlspecialchars($arr['code']) ?></span>
            <span class="bp-city"><?= htmlspecialchars($arr['name']) ?></span>
          </div>
        </div>
      </div>
      <div class="bp-stub">
        <p><span class="bp-label">Ημερομηνία</span><span class="mono"><?= htmlspecialchars($flightDate) ?></span></p>
        <p><span class="bp-label">Επιβάτες</span><span class="mono"><?= $passengers ?></span></p>
        <p><span class="bp-label">Απόσταση</span><span class="mono"><?= round($distance) ?> km</span></p>
      </div>
    </section>

    <!-- Passenger names -->
    <form id="passengers-form" class="card pass-form fade-in-up">
      <h2 class="card-title">Στοιχεία επιβατών</h2>
      <?php for ($i = 1; $i <= $passengers; $i++): ?>
        <fieldset>
          <legend>Επιβάτης <?= $i ?></legend>
          <div class="form-grid">
            <label>
              Όνομα
              <input type="text" name="p_first[]" required minlength="3" maxlength="20"
                     pattern="[A-Za-zΑ-Ωα-ωΆάΈέΉήΊίΌόΎύΏώ]{3,20}"
                     <?= $i === 1
                       ? 'readonly value="' . htmlspecialchars($user['first_name']) . '"'
                       : '' ?>>
            </label>
            <label>
              Επώνυμο
              <input type="text" name="p_last[]" required minlength="3" maxlength="20"
                     pattern="[A-Za-zΑ-Ωα-ωΆάΈέΉήΊίΌόΎύΏώ]{3,20}"
                     <?= $i === 1
                       ? 'readonly value="' . htmlspecialchars($user['last_name']) . '"'
                       : '' ?>>
            </label>
          </div>
        </fieldset>
      <?php endfor; ?>
      <button type="button" id="to-seats" class="btn btn-primary">Συνέχεια στις θέσεις</button>
    </form>

    <!-- Seat map (revealed after passenger details) -->
    <section class="card seat-map fade-in-up" id="seat-map">
      <h2 class="card-title">Επιλογή θέσεων</h2>
      <div class="seat-legend">
        <span><i class="dot dot-free"></i> Διαθέσιμη</span>
        <span><i class="dot dot-selected"></i> Επιλεγμένη</span>
        <span><i class="dot dot-taken"></i> Κατειλημμένη</span>
      </div>
      <div class="image-container" id="seat-container">
        <img src="assets/images/A320Neo.png" alt="Κάτοψη καθισμάτων A320neo">
      </div>
      <p id="selected" class="selected-seats">Επιλεγμένες θέσεις: <span class="mono">—</span></p>
      <button id="to-summary" class="btn btn-primary" disabled>Σύνοψη</button>
    </section>

    <!-- Booking summary -->
    <section id="summary" class="card summary-card" style="display:none;">
      <h2 class="card-title">Σύνοψη κράτησης</h2>
      <div id="details"></div>
      <button id="confirm" class="btn btn-primary">Οριστική κράτηση</button>
    </section>

    <!-- Hidden form for the final submission -->
    <form id="confirm-form" action="confirm.php" method="post" style="display:none;">
      <input type="hidden" name="departure"   value="<?= (int) $departure ?>">
      <input type="hidden" name="arrival"     value="<?= (int) $arrival ?>">
      <input type="hidden" name="flight_date" value="<?= htmlspecialchars($flightDate) ?>">
      <input type="hidden" name="passengers"  value="<?= $passengers ?>">
      <input type="hidden" name="seats"       id="seats-input" value="">
    </form>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
    // Flight data handed over to assets/js/main.js.
    window.BookingData = {
      seatsData:   <?= json_encode(json_decode($seatMapJson), JSON_UNESCAPED_SLASHES) ?>,
      unavailable: <?= json_encode($unavailable, JSON_UNESCAPED_SLASHES) ?>,
      numSeats:    <?= $passengers ?>,
      depTax:      <?= (float) $dep['tax'] ?>,
      arrTax:      <?= (float) $arr['tax'] ?>,
      dist:        <?= round($distance, 2) ?>
    };
  </script>
  <script src="assets/js/main.js"></script>
</body>
</html>
