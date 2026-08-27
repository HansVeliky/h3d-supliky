<?php
declare(strict_types=1);

if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * Ready-made SMTP servers for the mail settings.
 *
 * Picking a provider fills in the host, the port and the encryption, which
 * are the three things people get wrong and which produce the least helpful
 * error message in the world ("connection timed out"). The credentials are
 * never part of a preset - those belong to the account, not to the server.
 *
 * The list is editable: the built-in entries below are only the starting
 * point, and the panel can add its own or remove the ones it will never
 * use. As soon as anything is edited the whole list is written to the
 * settings, so a removed entry stays removed even after an update adds new
 * built-ins.
 *
 * Ports and encryption follow each provider's own documentation. Where a
 * provider needs something beyond a password - an application password, an
 * API key as the user name, a region in the host - it is said in the note
 * rather than left to be discovered by trial.
 */
final class SmtpPresets
{
    private const KEY = 'smtp_presets';

    /**
     * @return list<array{id:string,label:string,host:string,port:int,security:string,note:string}>
     */
    public const BUILT_IN = [
        // Czech and Slovak mailboxes
        ['id' => 'seznam',    'label' => 'Seznam.cz',            'host' => 'smtp.seznam.cz',        'port' => 465, 'security' => 'ssl',
         'note' => 'Přihlašovací jméno je celá adresa. V Nastavení schránky musí být povolený SMTP.'],
        ['id' => 'centrum',   'label' => 'Centrum.cz / Volny.cz','host' => 'smtp.centrum.cz',       'port' => 465, 'security' => 'ssl',
         'note' => 'Přihlašovací jméno je celá adresa.'],
        ['id' => 'forpsi',    'label' => 'Forpsi',               'host' => 'smtp.forpsi.com',       'port' => 465, 'security' => 'ssl',
         'note' => 'Hosting Forpsi. Odesílat lze jen z adresy, která na hostingu existuje.'],
        ['id' => 'wedos',     'label' => 'Wedos (WebMail)',      'host' => 'smtp.wedos.net',        'port' => 465, 'security' => 'ssl',
         'note' => 'Ověř si přesný název serveru v administraci Wedosu - u některých tarifů se liší.'],

        // The big mailbox providers
        ['id' => 'gmail',     'label' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com',    'port' => 587, 'security' => 'tls',
         'note' => 'Nefunguje s běžným heslem. Zapni dvoufázové ověření a vytvoř heslo aplikace.'],
        ['id' => 'm365',      'label' => 'Microsoft 365',        'host' => 'smtp.office365.com',    'port' => 587, 'security' => 'tls',
         'note' => 'Účet musí mít povolené SMTP AUTH; Microsoft ho novým tenantům vypíná.'],
        ['id' => 'outlook',   'label' => 'Outlook.com / Hotmail','host' => 'smtp-mail.outlook.com', 'port' => 587, 'security' => 'tls',
         'note' => 'Osobní účty vyžadují heslo aplikace.'],
        ['id' => 'yahoo',     'label' => 'Yahoo Mail',           'host' => 'smtp.mail.yahoo.com',   'port' => 465, 'security' => 'ssl',
         'note' => 'Vyžaduje heslo aplikace.'],
        ['id' => 'icloud',    'label' => 'iCloud Mail',          'host' => 'smtp.mail.me.com',      'port' => 587, 'security' => 'tls',
         'note' => 'Vyžaduje heslo aplikace z Apple ID.'],
        ['id' => 'zoho-eu',   'label' => 'Zoho Mail (EU)',       'host' => 'smtp.zoho.eu',          'port' => 465, 'security' => 'ssl',
         'note' => 'Pro účty v EU. Mimo EU použij smtp.zoho.com.'],
        ['id' => 'fastmail',  'label' => 'Fastmail',             'host' => 'smtp.fastmail.com',     'port' => 465, 'security' => 'ssl',
         'note' => 'Vyžaduje heslo aplikace.'],
        ['id' => 'gmx',       'label' => 'GMX',                  'host' => 'mail.gmx.com',          'port' => 587, 'security' => 'tls',
         'note' => 'V nastavení schránky musí být povolený přístup přes SMTP.'],
        ['id' => 'webde',     'label' => 'WEB.DE',               'host' => 'smtp.web.de',           'port' => 587, 'security' => 'tls',
         'note' => 'V nastavení schránky musí být povolený přístup přes SMTP.'],
        ['id' => 'ovh',       'label' => 'OVH',                  'host' => 'ssl0.ovh.net',          'port' => 465, 'security' => 'ssl',
         'note' => 'Hosting OVH. Přihlašovací jméno je celá adresa.'],
        ['id' => 'proton',    'label' => 'Proton Mail (Bridge)', 'host' => '127.0.0.1',             'port' => 1025, 'security' => 'none',
         'note' => 'Jen přes Proton Mail Bridge běžící na stejném stroji. Přímé SMTP Proton nenabízí.'],

        // Services made for sending from an application. On a website these
        // behave far better than a mailbox: they are built for it, and they
        // do not lock the account when it suddenly sends a hundred messages.
        ['id' => 'brevo',     'label' => 'Brevo (Sendinblue)',   'host' => 'smtp-relay.brevo.com',  'port' => 587, 'security' => 'tls',
         'note' => 'Uživatel je přihlašovací e-mail, heslo je SMTP klíč z účtu (ne heslo do Brevo).'],
        ['id' => 'sendgrid',  'label' => 'SendGrid',             'host' => 'smtp.sendgrid.net',     'port' => 587, 'security' => 'tls',
         'note' => 'Uživatelské jméno je doslova "apikey", heslo je vygenerovaný API klíč.'],
        ['id' => 'mailgun',   'label' => 'Mailgun',              'host' => 'smtp.mailgun.org',      'port' => 587, 'security' => 'tls',
         'note' => 'Pro evropskou oblast použij smtp.eu.mailgun.org.'],
        ['id' => 'postmark',  'label' => 'Postmark',             'host' => 'smtp.postmarkapp.com',  'port' => 587, 'security' => 'tls',
         'note' => 'Jméno i heslo je stejný Server API token.'],
        ['id' => 'mailjet',   'label' => 'Mailjet',              'host' => 'in-v3.mailjet.com',     'port' => 587, 'security' => 'tls',
         'note' => 'Jméno je API key, heslo je Secret key.'],
        ['id' => 'ses',       'label' => 'Amazon SES',           'host' => 'email-smtp.eu-central-1.amazonaws.com', 'port' => 587, 'security' => 'tls',
         'note' => 'V názvu serveru vyměň oblast za svou. Přihlašovací údaje jsou zvláštní SMTP credentials, ne klíče k IAM.'],
        ['id' => 'smtp2go',   'label' => 'SMTP2GO',              'host' => 'mail.smtp2go.com',      'port' => 2525, 'security' => 'tls',
         'note' => 'Port 2525 projde i tam, kde poskytovatel blokuje 587.'],
        ['id' => 'resend',    'label' => 'Resend',               'host' => 'smtp.resend.com',       'port' => 465, 'security' => 'ssl',
         'note' => 'Uživatel je "resend", heslo je API klíč.'],
        ['id' => 'mailersend','label' => 'MailerSend',           'host' => 'smtp.mailersend.net',   'port' => 587, 'security' => 'tls',
         'note' => 'Údaje se generují u ověřené domény.'],
        ['id' => 'elastic',   'label' => 'Elastic Email',        'host' => 'smtp.elasticemail.com', 'port' => 2525, 'security' => 'tls',
         'note' => 'Heslo je API klíč.'],
        ['id' => 'sparkpost', 'label' => 'SparkPost',            'host' => 'smtp.sparkpostmail.com','port' => 587, 'security' => 'tls',
         'note' => 'Uživatelské jméno je "SMTP_Injection", heslo je API klíč.'],
        ['id' => 'mailtrap',  'label' => 'Mailtrap (testování)', 'host' => 'sandbox.smtp.mailtrap.io', 'port' => 2525, 'security' => 'tls',
         'note' => 'Nic neodejde ven - všechno skončí ve schránce Mailtrapu. Ideální na zkoušení.'],
        ['id' => 'local',     'label' => 'Místní server (localhost)', 'host' => '127.0.0.1',        'port' => 25,  'security' => 'none',
         'note' => 'Postfix, Exim nebo jiný server na stejném stroji.'],
    ];

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $stored = trim((string) Settings::get(self::KEY));
        if ($stored === '') {
            return self::BUILT_IN;
        }
        $rows = json_decode($stored, true);
        if (!is_array($rows)) {
            // Unreadable settings must not take the mail page down with them.
            return self::BUILT_IN;
        }
        $out = [];
        foreach ($rows as $r) {
            $clean = self::clean(is_array($r) ? $r : []);
            if ($clean !== null) { $out[] = $clean; }
        }
        return $out ?: self::BUILT_IN;
    }

    /** One row, checked. Null when it could never work. */
    public static function clean(array $r): ?array
    {
        $host = trim((string) ($r['host'] ?? ''));
        $label = trim((string) ($r['label'] ?? '')) ?: $host;
        if ($host === '' || $label === '') { return null; }
        $port = (int) ($r['port'] ?? 587);
        if ($port < 1 || $port > 65535) { $port = 587; }
        $security = (string) ($r['security'] ?? 'tls');
        if (!in_array($security, ['tls', 'ssl', 'none'], true)) { $security = 'tls'; }
        $id = trim((string) ($r['id'] ?? ''));
        if ($id === '' || !preg_match('/^[a-z0-9._-]{1,40}$/i', $id)) {
            $id = substr(preg_replace('/[^a-z0-9]+/i', '-', strtolower($label)) ?? 'server', 0, 40);
            if ($id === '') { $id = 'server-' . substr(sha1($host), 0, 6); }
        }
        return [
            'id'       => $id,
            'label'    => mb_substr($label, 0, 60),
            'host'     => mb_substr($host, 0, 120),
            'port'     => $port,
            'security' => $security,
            'note'     => mb_substr(trim((string) ($r['note'] ?? '')), 0, 200),
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    public static function save(array $rows): void
    {
        $out = [];
        foreach ($rows as $r) {
            $clean = self::clean(is_array($r) ? $r : []);
            if ($clean === null) { continue; }
            // Two entries with the same id would fight over the picker.
            foreach ($out as $existing) {
                if ($existing['id'] === $clean['id']) { $clean['id'] .= '-' . substr(sha1($clean['host']), 0, 4); break; }
            }
            $out[] = $clean;
        }
        Settings::set([self::KEY => json_encode(array_values($out), JSON_UNESCAPED_UNICODE)]);
    }

    public static function add(array $row): bool
    {
        $clean = self::clean($row);
        if ($clean === null) { return false; }
        $rows = self::all();
        $rows[] = $clean;
        self::save($rows);
        return true;
    }

    public static function remove(string $id): void
    {
        $rows = array_values(array_filter(self::all(), static fn (array $r): bool => $r['id'] !== $id));
        // An empty list would silently come back as the built-ins, so it is
        // stored as an explicit empty array instead.
        if (!$rows) {
            Settings::set([self::KEY => '[]']);
            return;
        }
        self::save($rows);
    }

    /** Back to the list this version ships with. */
    public static function reset(): void
    {
        Settings::set([self::KEY => '']);
    }
}
