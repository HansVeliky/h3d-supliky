
# v23work - Refactor Report v2.1 (vyčištěno)

## 1. Bezpečnost - nalezené a opravené
- **CRITICAL install.php takeover**: původně pokud adminCount==0 šlo převzít účet existujícího uživatele a povýšit na admina. Oprava: podmínka změněna na adminCount==0 && userCount==0 + vyžadování instance tokenu.
- **data/ exposure**: SQLite soubory měly slabé .htaccess. Oprava: Deny from all + Require all denied pro .sqlite, .php, .log
- **Security::isHttps trust X-Forwarded-Proto**: útočník mohl podvrhnout header. Oprava: odstraněn trust, jen HTTPS a SERVER_PORT.
- **client.js.php.bak leak**: odstraněn ze zipu (ignorováno).
- **CSRF**: h3d_token kontrola chyběla v api/nickname.php, prefs.php - doporučeno přidat check.
- **SQLi/XSS**: Db::pdo() používá prepared statements - OK, ale View.php escapování kontrolováno - používá htmlspecialchars - OK.
- **Rate limiting**: UserMessages limit 5 zpráv OK, ale chybí IP rate limit na register/login - doporučeno přidat Captcha vždy po 3 pokusech.

## 2. CSS vyčištění
- **base.css 169KB + panel.css 88KB + ui.css 37KB** - sloučeno duplicitně.
  - panel.css obsahoval .support-modal-back 4x identicky s !important - odstraněno.
  - admin-theme.css měl zdvojené selektory .topbar .topbar - přepsáno čistě.
  - Nový návrh: split na modules/
    - generator.css (canvas, controls)
    - inspector.css
    - chat.css (support)
    - auth.css
    - admin.css (nový čistý)
- Doporučení: použít CSS variables z base.css jako single source of truth.

## 3. Funkce jako extra balíčky (Feature Packages)
Nový modul lib/FeaturePackages.php:
- Tabulka feature_packages (id, enabled, updated_at)
- Default balíčky:
  - generator (core)
  - inspector
  - chat (UserMessages)
  - payments
  - exports
  - qr

Admin může v nové záložce Funkce > Balíčky zapínat/vypínat:
  - if (!FeaturePackages::isEnabled('chat')) skryj podporu
  - if (!FeaturePackages::isEnabled('payments')) skryj /order.php a platby
  - if (!FeaturePackages::isEnabled('inspector')) skryj měření v panel.js

Integrace:
- index.php: obalit chat inicializaci do if (FeaturePackages::isEnabled('chat'))
- panel.js: if (!features.inspector) hide inspector UI
- order.php: if (!FeaturePackages::isEnabled('payments')) redirect na free export

Editační UI je v adminu - nová sekce features balíčky - viz lib/FeaturePackages.php::all()

## 4. Administrace - nelogické rozložení -> nový návrh
Původně: 20 tabů v jednom souboru 5987 LOC, skupiny money+settings chaotické.

Nový návrh (admin/index.refactored.php):
- **Studio & Generátor**: overview, studio, exports, features (balíčky)
- **Prodej a peníze**: billing (balíčky), payments (metody), promotions, invoices, codes, orders
- **Uživatelé a podpora**: users, messages, activity
- **Systém**: settings, accounts, seller, look, mail, adminlog, reset

Každý tab -> admin/modules/{tab}.php (router)
- Akce přes POST router: admin/modules/actions/{action}.php
- Odstraněn hack if (!function_exists('admin_log_write')) - test renderer rozdělen do tests/

## 5. Placení jako vypínatelný modul
- payments balíček:
  - Vypnutím: skryje order.php tlačítko, API quota.php vrací free only, invoice.php 404
  - V adminu toggle v Prodej a peníze > Platby > checkbox "Aktivní"
  - Settings::bool('payments_enabled') deprecated - nahrazen FeaturePackages

## 6. Další TODO
- Rozdělit admin/index.php (5987 řádků) na moduly - začátek v admin/modules/
- Rozdělit client.js.php (5871 řádků) - generator, inspector, chat, export
- Přidat composer + autoload místo require_once listu
- Přidat CSP headers
- Migrace SQLite na Postgres pro větší provoz

## Export
Všechny opravené soubory jsou ve v23work-cleaned/
