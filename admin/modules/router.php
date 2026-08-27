<?php
declare(strict_types=1);
if (!defined('H3D_APP')) exit;
$tab = $_GET['tab'] ?? 'overview';
// Security: whitelist tabs
$allTabs = [];
foreach ($GLOBALS['GROUPS'] as $g) $allTabs = array_merge($allTabs, $g['tabs']);
if (!in_array($tab, $allTabs, true)) $tab = 'overview';
// Route to module file
$moduleFile = __DIR__ . "/{$tab}.php";
if (file_exists($moduleFile)) {
  require $moduleFile;
} else {
  echo "Modul $tab zatím migrován - původní kód v index.php";
}
