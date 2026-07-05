<?php
header('Cache-Control: no-store, no-cache, must-revalidate');

require __DIR__ . '/../config/db.php';
session_start();

// Optional post-login destination, restricted to known local pages
// so the parameter can't be abused for open redirects.
$allowedRedirects = ['home.php', 'book.php', 'my_trips.php'];
$redirect = $_GET['redirect'] ?? 'home.php';
if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = 'home.php';
}

// Already logged in → go straight to the destination.
if (isset($_SESSION['user_id'])) {
    header("Location: {$redirect}", true, 302);
    exit;
}

$errors = [];
$showRegister = false;

// Registration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $showRegister = true;
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name']  ?? '');
    $un = trim($_POST['username']   ?? '');
    $em = trim($_POST['email']      ?? '');
    $pw = $_POST['password']        ?? '';

    if (!preg_match('/^[\p{L}]+$/u', $fn)) {
        $errors[] = 'Το όνομα πρέπει να περιέχει μόνο γράμματα.';
    }
    if (!preg_match('/^[\p{L}]+$/u', $ln)) {
        $errors[] = 'Το επώνυμο πρέπει να περιέχει μόνο γράμματα.';
    }
    if (strlen($pw) < 8 || strlen($pw) > 64 || !preg_match('/\d/', $pw)) {
        $errors[] = 'Ο κωδικός πρέπει να έχει 8–64 χαρακτήρες και τουλάχιστον έναν αριθμό.';
    }
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Δώστε έγκυρο email.';
    }
    if (ctype_digit($un)) {
        $errors[] = 'Το username πρέπει να περιέχει τουλάχιστον ένα γράμμα.';
    }

    // Uniqueness checks.
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$un]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Το username υπάρχει ήδη.';
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$em]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Το email υπάρχει ήδη.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, username, email, password)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fn, $ln, $un, $em, $hash]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        header("Location: {$redirect}", true, 302);
        exit;
    }
}

// Login.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $un = trim($_POST['username'] ?? '');
    $pw = $_POST['password']      ?? '';

    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->execute([$un]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($pw, $row['password'])) {
        $errors[] = 'Λάθος username ή κωδικός.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        header("Location: {$redirect}", true, 302);
        exit;
    }
}

$pageTitle = 'Air DS — Σύνδεση / Εγγραφή';
require __DIR__ . '/../includes/header.php';
?>
<body class="auth-page">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="auth-stage">
    <div class="auth-wrapper <?= $showRegister ? 'right-panel-active' : '' ?>" id="auth-container">

      <!-- Register -->
      <div class="form-container sign-up-container">
        <form method="post" action="login.php<?= $redirect !== 'home.php' ? '?redirect=' . urlencode($redirect) : '' ?>">
          <input type="hidden" name="register" value="1">
          <h2>Εγγραφή</h2>
          <span>Δημιουργήστε λογαριασμό Air DS</span>
          <input type="text"     placeholder="Όνομα"    name="first_name" required>
          <input type="text"     placeholder="Επώνυμο"  name="last_name"  required>
          <input type="text"     placeholder="Username" name="username"   required>
          <input type="email"    placeholder="Email"    name="email"      required>
          <input type="password" placeholder="Κωδικός (8+ χαρακτήρες, 1 αριθμός)"
                 name="password" required minlength="8" maxlength="64">
          <button class="btn btn-primary">Εγγραφή</button>
        </form>
      </div>

      <!-- Login -->
      <div class="form-container sign-in-container">
        <form method="post" action="login.php<?= $redirect !== 'home.php' ? '?redirect=' . urlencode($redirect) : '' ?>">
          <input type="hidden" name="login" value="1">
          <h2>Σύνδεση</h2>
          <span>Συνδεθείτε στον λογαριασμό σας</span>
          <input type="text"     placeholder="Username" name="username" required>
          <input type="password" placeholder="Κωδικός"  name="password" required>
          <button class="btn btn-primary">Σύνδεση</button>
        </form>
      </div>

      <!-- Sliding overlay -->
      <div class="overlay-container">
        <div class="overlay">
          <div class="overlay-panel overlay-left">
            <h2>Καλώς όρισες πίσω!</h2>
            <p>Συνδέσου για να συνεχίσεις την κράτησή σου.</p>
            <button class="btn ghost" id="signIn">Σύνδεση</button>
          </div>
          <div class="overlay-panel overlay-right">
            <h2>Καλωσήρθες στην Air DS</h2>
            <p>Δημιούργησε λογαριασμό σε ένα λεπτό.</p>
            <button class="btn ghost" id="signUp">Εγγραφή</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div id="error-modal">
      <div class="modal-content">
        <h3>Παρακαλώ διορθώστε τα παρακάτω:</h3>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
        <button id="error-ok" class="btn btn-primary">ΟΚ</button>
      </div>
    </div>
  <?php endif; ?>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script src="assets/js/main.js"></script>
</body>
</html>
