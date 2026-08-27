<?php
declare(strict_types=1);
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * Feature Packages - toggleable modules editable in admin
 * generator = core 3D generator
 * inspector = model inspector / measurements
 * chat = UserMessages (support)
 * payments = Payments gateways
 * exports = export engine (STL/3MF)
 * qr = QR generator
 */
final class FeaturePackages
{
    public const PACKAGES = [
        'generator' => ['label'=>'Generátor', 'desc'=>'Hlavní generátor organizéru (core). Vypnutím zablokuje export.','core'=>true,'default'=>1],
        'inspector' => ['label'=>'Inspektor', 'desc'=>'Měření, rozměry, náhled vrstev.','core'=>false,'default'=>1],
        'chat'      => ['label'=>'Zprávy / Chat', 'desc'=>'Podpora - konverzace uživatel-admin.','core'=>false,'default'=>1],
        'payments'  => ['label'=>'Platby', 'desc'=>'Celý platební modul (možnosti platby, objednávky). Vypnutím skryje platby.','core'=>false,'default'=>1],
        'exports'   => ['label'=>'Exporty', 'desc'=>'STL/3MF export a kreditní logika.','core'=>true,'default'=>1],
        'qr'        => ['label'=>'QR Platba', 'desc'=>'Generování QR kódů pro platby.','core'=>false,'default'=>1],
    ];

    public static function ensureTable(): void
    {
        Db::pdo()->exec("CREATE TABLE IF NOT EXISTS feature_packages (
            id TEXT PRIMARY KEY,
            enabled INTEGER NOT NULL DEFAULT 1,
            updated_at INTEGER NOT NULL
        )");
        $now = time();
        foreach (self::PACKAGES as $id=>$meta) {
            $st = Db::pdo()->prepare("INSERT OR IGNORE INTO feature_packages (id, enabled, updated_at) VALUES (?,?,?)");
            $st->execute([$id, $meta['default'], $now]);
        }
    }

    public static function all(): array
    {
        self::ensureTable();
        $rows = Db::pdo()->query("SELECT * FROM feature_packages")->fetchAll(PDO::FETCH_KEY_PAIR);
        $out=[];
        foreach (self::PACKAGES as $id=>$meta) {
            $out[$id] = [
                'id'=>$id,
                'label'=>$meta['label'],
                'desc'=>$meta['desc'],
                'core'=>$meta['core'],
                'enabled'=> isset($rows[$id]) ? (int)$rows[$id] : $meta['default']
            ];
        }
        return $out;
    }

    public static function isEnabled(string $id): bool
    {
        self::ensureTable();
        if (!isset(self::PACKAGES[$id])) return false;
        $st = Db::pdo()->prepare("SELECT enabled FROM feature_packages WHERE id=?");
        $st->execute([$id]);
        $v = $st->fetchColumn();
        if ($v===false) return (bool)self::PACKAGES[$id]['default'];
        return (bool)$v;
    }

    public static function setEnabled(string $id, bool $enabled): void
    {
        if (!isset(self::PACKAGES[$id])) throw new InvalidArgumentException("Unknown package $id");
        self::ensureTable();
        $st = Db::pdo()->prepare("INSERT INTO feature_packages (id, enabled, updated_at) VALUES (?,?,?)
            ON CONFLICT(id) DO UPDATE SET enabled=excluded.enabled, updated_at=excluded.updated_at");
        $st->execute([$id, $enabled?1:0, time()]);
    }
}
