<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

/*
 * Live nickname availability for the register form and the account page.
 * Nicknames are login identifiers, so whether one is taken is not a secret -
 * still, the endpoint answers only yes/no and never which account owns it.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$nick = trim((string) ($_GET['nick'] ?? ''));

$valid = (bool) preg_match('/^[A-Za-z0-9_.-]{3,20}$/', $nick)
      && !filter_var($nick, FILTER_VALIDATE_EMAIL);

$available = false;
if ($valid) {
    $st = Db::pdo()->prepare(
        "SELECT id FROM users WHERE nickname <> '' AND LOWER(nickname) = LOWER(?)"
    );
    $st->execute([$nick]);
    $owner = $st->fetchColumn();

    // Your own current nickname counts as available, so the account form
    // does not shout "taken" at the value that is already yours.
    $me        = Auth::user();
    @session_write_close();
    $available = !$owner || ($me && (int) $owner === (int) $me['id']);
}

echo json_encode(['ok' => true, 'valid' => $valid, 'available' => $available]);
