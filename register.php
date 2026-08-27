<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// Registration now lives on the unified auth page as a tab, so the old
// standalone route just forwards there (keeping any ?next target).
if (Auth::user()) { header('Location: index.php'); exit; }

$next = (string) ($_GET['next'] ?? '');
$q = 'tab=register' . ($next !== '' ? '&next=' . rawurlencode($next) : '');
header('Location: login.php?' . $q);
exit;
