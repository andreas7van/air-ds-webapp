<?php
require __DIR__ . '/../config/db.php';
session_start();

$isLoggedIn = isset($_SESSION['user_id']);

// Server-side validation of the search form.
$errors = [];
$old = [
  'departure'   => '',
  'arrival'     => '',
  'flight_date' => '',
  'passengers'  => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $old['departure']   = $_POST['departure']   ?? '';
  $old['arrival']     = $_POST['arrival']     ?? '';
  $old['flight_date'] = $_POST['flight_date'] ?? '';
  $old['passengers']  = $_POST['passengers']  ?? '1';

  if ($old['departure'] && $old['arrival'] && $old['departure'] === $old['arrival']) {
    $errors[] = 'Το αεροδρόμιο αναχώρησης και άφιξης πρέπει να είναι διαφορετικά.';
  }
  if ($old['flight_date'] && $old['flight_date'] < date('Y-m-d')) {
    $errors[] = 'Η ημερομηνία πτήσης δεν μπορεί να είναι παρελθοντική.';
  }
  if (!filter_var($old['passengers'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 10],
      ])) {
    $errors[] = 'Το πλήθος επιβατών πρέπει να είναι από 1 έως 10.';
  }

  // Valid search: store it in the session and continue to booking
  // (or to login first, if the visitor is not authenticated yet).
  if (empty($errors)) {
    $_SESSION['search'] = $old;
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $target = $isLoggedIn ? "{$base}/book.php"
                          : "{$base}/login.php?redirect=book.php";
    header("Location: {$target}", true, 302);
    exit;
  }
}

// Airports for the two <select> menus.
$airports = $pdo->query('SELECT id, name, code FROM airports ORDER BY name')
                ->fetchAll();

$pageTitle = 'Air DS — Αναζήτηση πτήσης';
require __DIR__ . '/../includes/header.php';
?>
<body>
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <header class="hero">
    <div class="hero-inner">
      <p class="hero-eyebrow">Air DS · Κρατήσεις πτήσεων</p>
      <h1>Πτήση σε τρία βήματα.<br>Αναζήτηση, θέσεις, κράτηση.</h1>
      <div class="hero-route" aria-hidden="true">
        <span class="code">ATH</span>
        <svg class="route-arc" viewBox="0 0 320 60" fill="none">
          <path d="M8 52 Q160 -30 312 52" stroke="currentColor" stroke-width="2"
                stroke-dasharray="6 7" stroke-linecap="round"/>
          <circle cx="8" cy="52" r="4" fill="currentColor"/>
          <circle cx="312" cy="52" r="4" fill="currentColor"/>
        </svg>
        <span class="code">CDG</span>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="card search-card fade-in-up">
      <h2 class="card-title">Κάντε την κράτησή σας</h2>

      <div id="airport-error" class="errors" style="<?= $errors ? '' : 'display:none;' ?>">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <form action="" method="post" id="flight-form"
            data-logged-in="<?= $isLoggedIn ? '1' : '0' ?>">

        <div class="form-grid">
          <div class="form-group">
            <label for="departure">Αεροδρόμιο αναχώρησης</label>
            <select name="departure" id="departure" required>
              <option value="">— Επιλέξτε —</option>
              <?php foreach ($airports as $a): ?>
                <option value="<?= $a['id'] ?>"
                  <?= $old['departure'] == $a['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars("{$a['name']} ({$a['code']})") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="arrival">Αεροδρόμιο άφιξης</label>
            <select name="arrival" id="arrival" required>
              <option value="">— Επιλέξτε —</option>
              <?php foreach ($airports as $a): ?>
                <option value="<?= $a['id'] ?>"
                  <?= $old['arrival'] == $a['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars("{$a['name']} ({$a['code']})") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="flight_date">Ημερομηνία πτήσης</label>
            <input type="date" name="flight_date" id="flight_date" required
                   min="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($old['flight_date']) ?>">
          </div>

          <div class="form-group">
            <label for="passengers">Πλήθος επιβατών</label>
            <input type="number" name="passengers" id="passengers" required
                   min="1" max="10"
                   value="<?= htmlspecialchars($old['passengers']) ?>">
          </div>
        </div>

        <button type="submit" id="submit-btn" class="btn btn-primary" disabled>
          Αναζήτηση &amp; κράτηση
        </button>
        <?php if (!$isLoggedIn): ?>
          <p class="form-hint">Χρειάζεται <a href="login.php">σύνδεση</a> για να ολοκληρώσετε κράτηση.</p>
        <?php endif; ?>
      </form>
    </section>
  </main>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script src="assets/js/main.js"></script>
</body>
</html>
