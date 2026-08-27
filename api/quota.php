<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = Auth::user();
$featureUser = ($user && (!empty($user['verified_at']) || Auth::isPrivileged($user))) ? $user : null;
// Read-only from here on, so the session lock can go - the studio polls
// this and must not make other requests wait on it.
@session_write_close();
$q = Quota::check($featureUser);

echo json_encode([
    'mode'       => $q['mode'],
    'cost'       => Cred::fmt($q['cost']),
    'retryAfter' => $q['retry_after'],
    'cooldown'   => $q['cooldown'],
    'balance'    => Cred::fmt($q['balance']),
    'limit'        => $q['limit'],
    'noFree'       => !empty($q['free_paid']),
    'windowReset'  => (int) ($q['window_reset'] ?? 0),
    'freeLeft'     => $q['free_left'],
    'confirmSpend' => Quota::confirmSpend($featureUser),
    'signedIn'   => $featureUser !== null,
    'authenticated' => $user !== null,
    'verified'   => (bool) ($user && !empty($user['verified_at'])),
    'unlimited'  => !empty($q['unlimited']),
], JSON_UNESCAPED_UNICODE);
