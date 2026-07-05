<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect=my_trips.php', true, 302);
    exit;
}
$userId = (int) $_SESSION['user_id'];

require __DIR__ . '/../config/db.php';

// Flash messages set by confirm.php / cancel.php.
$message = $_SESSION['message'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['message'], $_SESSION['error']);

// All reservations of the current user, newest flight first.
$stmt = $pdo->prepare(<<<'SQL'
  SELECT
    r.id, r.flight_date, r.passengers_count, r.total_cost, r.seats,
    da.name AS dep_name, da.code AS dep_code,
    aa.name AS arr_name, aa.code AS arr_code,
    da.tax  AS dep_tax,  aa.tax  AS arr_tax
  FROM reservations r
  JOIN airports da ON r.departure_airport_id = da.id
  JOIN airports aa ON r.arrival_airport_id   = aa.id
  WHERE r.user_id = ?
  ORDER BY r.flight_date DESC
SQL);
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

$pageTitle = 'Air DS — Οι κρατήσεις μου';
require __DIR__ . '/../includes/header.php';
?>
<body>
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <main class="container">
    <h1>Οι κρατήσεις μου</h1>

    <?php if ($message): ?>
      <div class="alert alert-success fade-in-up"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error fade-in-up"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($reservations)): ?>
      <div class="card empty-state fade-in-up">
        <p>Δεν έχετε κάνει ακόμη κράτηση.</p>
        <a class="btn btn-primary" href="home.php">Αναζήτηση πτήσης</a>
      </div>
    <?php else: ?>
      <?php foreach ($reservations as $r):
        $taxesPerPassenger = $r['dep_tax'] + $r['arr_tax'];
        $flight    = new DateTime($r['flight_date']);
        $diff      = (new DateTime('today'))->diff($flight);
        $canCancel = !$diff->invert && $diff->days >= 30;
        $seats     = array_filter(array_map('trim', explode(',', $r['seats'])));
      ?>
        <article class="boarding-pass ticket fade-in-up">
          <div class="bp-main">
            <div class="bp-route">
              <div class="bp-airport">
                <span class="code"><?= htmlspecialchars($r['dep_code']) ?></span>
                <span class="bp-city"><?= htmlspecialchars($r['dep_name']) ?></span>
              </div>
              <span class="bp-plane" aria-hidden="true">✈</span>
              <div class="bp-airport">
                <span class="code"><?= htmlspecialchars($r['arr_code']) ?></span>
                <span class="bp-city"><?= htmlspecialchars($r['arr_name']) ?></span>
              </div>
            </div>

            <div class="bp-meta">
              <p><span class="bp-label">Ημερομηνία</span><span class="mono"><?= $flight->format('d.m.Y') ?></span></p>
              <p><span class="bp-label">Επιβάτες</span><span class="mono"><?= (int) $r['passengers_count'] ?></span></p>
              <p><span class="bp-label">Θέσεις</span>
                <span class="seat-chips">
                  <?php foreach ($seats as $seat): ?>
                    <span class="chip mono"><?= htmlspecialchars($seat) ?></span>
                  <?php endforeach; ?>
                </span>
              </p>
              <p><span class="bp-label">Φόροι / επιβάτη</span><span class="mono"><?= number_format($taxesPerPassenger, 2) ?> €</span></p>
            </div>
          </div>

          <div class="bp-stub">
            <p class="bp-total">
              <span class="bp-label">Σύνολο</span>
              <span class="mono"><?= number_format($r['total_cost'], 2) ?> €</span>
            </p>
            <form method="post" action="cancel.php"
                  onsubmit="return confirm('Σίγουρα θέλετε να ακυρώσετε την κράτηση;');">
              <input type="hidden" name="reservation_id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-danger" <?= $canCancel ? '' : 'disabled' ?>>
                Ακύρωση
              </button>
            </form>
            <?php if (!$canCancel): ?>
              <p class="cannot-cancel">Ακύρωση μόνο 30+ ημέρες πριν</p>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script src="assets/js/main.js"></script>
</body>
</html>
