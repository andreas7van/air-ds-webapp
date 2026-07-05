<?php
// includes/navbar.php — session_start() has already been called by the page.
$isLoggedIn = isset($_SESSION['user_id']);
?>
<nav class="navbar">
  <div class="logo">
    <a href="home.php">
      <img src="assets/images/logo.png" alt="Air DS logo" class="navbar__logo">
      <span>Air&nbsp;DS</span>
    </a>
  </div>

  <button class="hamburger" aria-label="Menu" aria-expanded="false">☰</button>

  <div class="nav-links">
    <a href="home.php">Αρχική</a>
    <a href="my_trips.php">Οι κρατήσεις μου</a>
    <?php if ($isLoggedIn): ?>
      <a href="logout.php" class="logout-btn">Αποσύνδεση</a>
    <?php else: ?>
      <a href="login.php" class="login-btn">Σύνδεση</a>
    <?php endif; ?>
  </div>
</nav>
