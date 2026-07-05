<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require __DIR__ . '/../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php?redirect=my_trips.php', true, 302);
    exit;
}
$userId = (int) $_SESSION['user_id'];

// Cancellation only via POST with a valid reservation id.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resId = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    if (!$resId) {
        $_SESSION['error'] = 'Μη έγκυρο αναγνωριστικό κράτησης.';
        header('Location: my_trips.php', true, 302);
        exit;
    }

    // The reservation must belong to the logged-in user.
    $stmt = $pdo->prepare(
        'SELECT flight_date FROM reservations WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$resId, $userId]);
    $flightDate = $stmt->fetchColumn();

    if (!$flightDate) {
        $_SESSION['error'] = 'Η κράτηση δεν βρέθηκε ή δεν σας ανήκει.';
        header('Location: my_trips.php', true, 302);
        exit;
    }

    // Business rule: cancellation allowed only 30+ days before departure.
    $now  = new DateTime('today');
    $fd   = new DateTime($flightDate);
    $diff = $now->diff($fd);
    if ($diff->invert || $diff->days < 30) {
        $_SESSION['error'] = 'Ακύρωση επιτρέπεται μόνο 30+ ημέρες πριν την πτήση.';
        header('Location: my_trips.php', true, 302);
        exit;
    }

    try {
        $del = $pdo->prepare('DELETE FROM reservations WHERE id = ? AND user_id = ?');
        $del->execute([$resId, $userId]);
        $_SESSION['message'] = 'Η κράτησή σας ακυρώθηκε επιτυχώς.';
    } catch (Exception $e) {
        error_log('Cancel failed: ' . $e->getMessage());
        $_SESSION['error'] = 'Παρουσιάστηκε σφάλμα κατά την ακύρωση. Δοκιμάστε ξανά.';
    }
}

header('Location: my_trips.php', true, 302);
exit;
