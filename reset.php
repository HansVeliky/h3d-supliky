<?php
declare(strict_types=1);

/**
 * Reset from the terminal.
 *
 * The panel is the normal way in; this exists for the case where you cannot
 * reach it - a broken settings save, a lost admin password, or a database in
 * a state the app refuses to open properly.
 *
 *   php reset.php --list
 *   php reset.php --scope=activity,commerce
 *   php reset.php --everything
 *
 * CLI only, so it cannot be triggered over HTTP whatever the server config.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/lib/bootstrap.php';

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

$backupNote = "Zálohu si udělej zkopírováním souboru:\n  cp '" . Db::path() . "' backup.sqlite\n";

if (isset($args['list']) || !$args) {
    echo "\nDatabáze: " . Db::path() . "\n\n";
    echo "Obsah:\n";
    foreach (Reset::counts() as $table => $n) {
        printf("  %-18s %d\n", $table, $n);
    }
    echo "\nRozsahy pro --scope:\n";
    foreach (Reset::SCOPES as $key => [$label, $desc]) {
        printf("  %-10s %s\n", $key, $label);
        printf("  %-10s %s\n", '', $desc);
    }
    echo "\n  --everything   smaže celou databázi i token instance\n\n";
    echo $backupNote . "\n";
    exit(0);
}

/** Refuses to go ahead unless the answer is exactly what was asked for. */
function confirm(string $question): bool
{
    echo $question . "\nNapiš RESET a potvrď: ";
    $line = trim((string) fgets(STDIN));
    return $line === 'RESET';
}

if (isset($args['everything'])) {
    echo "\nTOHLE SMAŽE CELOU DATABÁZI.\n" . $backupNote . "\n";
    if (!confirm('Opravdu smazat vše?')) {
        echo "Zrušeno, nic se nestalo.\n";
        exit(1);
    }
    Reset::wipeEverything();
    echo "Hotovo. Otevři register.php a první registrace vytvoří správce.\n";
    exit(0);
}

if (!isset($args['scope']) || !is_string($args['scope'])) {
    echo "Chybí --scope=... nebo --everything. Spusť s --list.\n";
    exit(1);
}

$scopes = array_values(array_intersect(
    array_map('trim', explode(',', $args['scope'])),
    array_keys(Reset::SCOPES)
));

if (!$scopes) {
    echo "Žádný známý rozsah. Spusť s --list.\n";
    exit(1);
}

echo "\nSmaže se:\n";
foreach ($scopes as $s) {
    echo '  - ' . Reset::SCOPES[$s][0] . ': ' . Reset::SCOPES[$s][1] . "\n";
}
echo "\n" . $backupNote . "\n";

if (!confirm('Pokračovat?')) {
    echo "Zrušeno, nic se nestalo.\n";
    exit(1);
}

// The first admin is kept so there is still a way into the panel afterwards.
$keep = (int) (Db::pdo()->query('SELECT id FROM users WHERE is_admin = 1 ORDER BY id LIMIT 1')
    ->fetchColumn() ?: 0);

foreach (Reset::run($scopes, $keep) as $scope => $result) {
    echo '  ' . Reset::SCOPES[$scope][0] . ': ' . $result . "\n";
}

echo "Hotovo.\n";
