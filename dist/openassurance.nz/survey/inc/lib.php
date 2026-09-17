<?php
/**
 * Shared code for the survey. No sessions and no cookies are used anywhere:
 * the site promises its visitors that it sets none.
 */

require_once __DIR__ . '/questions.php';
require_once __DIR__ . '/shell.php';

const OA_MIN_SECONDS = 4;        // a person cannot complete the form faster than this
const OA_MAX_SECONDS = 14400;    // a form left open longer than four hours is stale
const OA_CONTACT_MAX = 200;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Configuration lives outside the web root, so that database credentials are
 * never in the repository and can never be served by the web server.
 */
function oa_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $path = oa_config_path();
    $config = [];
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }
    return $config;
}

function oa_config_path(): string
{
    $path = getenv('OA_SURVEY_CONFIG');
    if ($path) {
        return (string) $path;
    }
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') : '';
    return dirname($root) . '/openassurance-private/survey-config.php';
}

function oa_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $c = oa_config();
    if (!$c) {
        return $pdo = null;
    }
    $dsn = $c['dsn'] ?? sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['db_host'] ?? 'localhost', $c['db_name'] ?? '');
    try {
        $pdo = new PDO($dsn, $c['db_user'] ?? null, $c['db_pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('OpenAssurance survey: database connection failed');
        // Kept for the admin setup check only. It is never shown to a visitor.
        $GLOBALS['oa_db_error'] = $e->getMessage();
        $pdo = null;
    }
    return $pdo;
}

/**
 * A step-by-step setup check for the admin area. Each entry is
 * [label, passed, detail]. Passwords are never included: only whether a
 * value has been filled in.
 */
function oa_diagnose(): array
{
    $checks = [];
    $path = oa_config_path();
    $found = @is_file($path);
    $detail = 'Looked for ' . $path;
    $basedir = (string) ini_get('open_basedir');
    if (!$found && $basedir !== '') {
        $detail .= '. PHP on this host may only read inside: ' . $basedir;
    }
    $checks[] = ['Configuration file found', $found, $detail];
    if (!$found) {
        return $checks;
    }
    $checks[] = ['Configuration file readable', is_readable($path), 'The web server must be able to read it; permissions 644 are normal.'];

    $c = oa_config();
    $checks[] = ['Configuration file returns its settings', (bool) $c, 'The file must begin with <?php and end by returning the array, exactly as the sample does.'];
    if (!$c) {
        return $checks;
    }
    if (empty($c['dsn'])) {
        $unfilled = [];
        foreach (['db_name', 'db_user', 'db_pass'] as $key) {
            $value = (string) ($c[$key] ?? '');
            if ($value === '' || stripos($value, 'REPLACE') !== false) {
                $unfilled[] = $key;
            }
        }
        $checks[] = ['Database settings filled in', !$unfilled, $unfilled ? 'Still to fill in: ' . implode(', ', $unfilled) : 'db_name, db_user, and db_pass all have values.'];
        if ($unfilled) {
            return $checks;
        }
        $drivers = class_exists('PDO') ? PDO::getAvailableDrivers() : [];
        $checks[] = ['PHP can talk to MySQL', in_array('mysql', $drivers, true), 'Drivers available: ' . ($drivers ? implode(', ', $drivers) : 'none') . '. If mysql is missing, enable pdo_mysql under Select PHP Version in cPanel.'];
        if (!in_array('mysql', $drivers, true)) {
            return $checks;
        }
    }

    $db = oa_db();
    $error = (string) ($GLOBALS['oa_db_error'] ?? '');
    $hint = '';
    if (stripos($error, 'Access denied') !== false) {
        $hint = ' Check the user name and password, and that the user has been added to the database under MySQL Databases.';
    } elseif (stripos($error, 'Unknown database') !== false) {
        $hint = ' Check db_name, including the cPanel account prefix, for example account_name.';
    }
    $checks[] = ['Connected to the database', $db !== null, $db !== null ? 'Connection succeeded.' : 'The server said: ' . $error . $hint];
    if ($db === null) {
        return $checks;
    }
    try {
        $db->query('SELECT 1 FROM survey_responses LIMIT 1');
        $checks[] = ['Table survey_responses exists', true, 'The survey is ready to take responses.'];
    } catch (Throwable $e) {
        $checks[] = ['Table survey_responses exists', false, 'Run survey-setup/schema.sql in phpMyAdmin against this database.'];
    }
    return $checks;
}

/** A secret for signing form tokens, derived from configuration so nothing extra has to be invented. */
function oa_secret(): string
{
    $c = oa_config();
    if (!empty($c['secret'])) {
        return (string) $c['secret'];
    }
    return hash('sha256', ($c['db_pass'] ?? '') . '|' . ($c['db_name'] ?? '') . '|' . ($c['dsn'] ?? '') . '|openassurance-survey');
}

/**
 * Contact details are only asked for when the operator has said who holds
 * them. Collecting personal information without saying so would break the
 * project's own privacy principles.
 */
function oa_contact_enabled(): bool
{
    $c = oa_config();
    $notice = trim((string) ($c['holder_notice'] ?? ''));
    return !empty($c['collect_contact']) && $notice !== '' && stripos($notice, 'REPLACE') === false;
}

function oa_token(string $purpose = 'form'): array
{
    $t = time();
    return [$t, hash_hmac('sha256', $purpose . '|' . $t, oa_secret())];
}

function oa_token_ok($t, $sig, string $purpose = 'form', int $min = OA_MIN_SECONDS): bool
{
    if (!is_string($t) || !ctype_digit($t) || !is_string($sig)) {
        return false;
    }
    $age = time() - (int) $t;
    if ($age < $min || $age > OA_MAX_SECONDS) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $purpose . '|' . $t, oa_secret()), $sig);
}

function oa_clean_text($value, int $max): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = preg_replace('/[^\P{C}\n\t]+/u', '', $value) ?? '';
    $value = trim(str_replace("\r\n", "\n", $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

/**
 * Validate a submission against the question set. Anything that is not an
 * allowed code is dropped, so nothing unexpected can reach the database.
 * Returns [answers, comment, errors].
 */
function oa_validate(array $post): array
{
    $answers = [];
    $comment = '';
    $errors = [];
    foreach (survey_questions() as $id => $q) {
        $raw = $post[$id] ?? null;
        if ($q['type'] === 'single') {
            if (is_string($raw) && array_key_exists($raw, $q['options'])) {
                $answers[$id] = $raw;
            } elseif (!empty($q['required'])) {
                $errors[] = 'Please answer: ' . $q['label'];
            }
        } elseif ($q['type'] === 'multi') {
            if (is_array($raw)) {
                $chosen = [];
                foreach ($raw as $code) {
                    if (is_string($code) && array_key_exists($code, $q['options']) && !in_array($code, $chosen, true)) {
                        $chosen[] = $code;
                    }
                }
                if ($chosen) {
                    $answers[$id] = $chosen;
                }
            }
        } elseif ($q['type'] === 'text') {
            $comment = oa_clean_text($raw, (int) ($q['max'] ?? 1500));
        }
    }
    return [$answers, $comment, $errors];
}

/** A short code the respondent can use later to delete their own response. */
function oa_new_removal_code(): string
{
    $raw = strtoupper(bin2hex(random_bytes(6)));
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
}

function oa_removal_hash(string $code): string
{
    return hash('sha256', preg_replace('/[^A-F0-9]/', '', strtoupper($code)));
}

/** A ceiling on submissions per hour, in place of storing visitors' addresses. */
function oa_throttled(PDO $db): bool
{
    $limit = (int) (oa_config()['max_per_hour'] ?? 60);
    $stmt = $db->prepare('SELECT COUNT(*) AS n FROM survey_responses WHERE created_at >= :since');
    $stmt->execute([':since' => gmdate('Y-m-d H:i:s', time() - 3600)]);
    return (int) $stmt->fetch()['n'] >= $limit;
}

/**
 * The admin area is protected by the web server, through cPanel Directory
 * Privacy. REMOTE_USER is set by the server only after it has checked a
 * password. PHP_AUTH_USER is deliberately NOT trusted: PHP fills it in from
 * any Authorization header a visitor cares to send, checked or not.
 */
function oa_admin_user(): ?string
{
    if (PHP_SAPI === 'cli-server' && getenv('OA_SURVEY_TEST_ADMIN')) {
        return 'local-test';
    }
    foreach (['REMOTE_USER', 'REDIRECT_REMOTE_USER'] as $key) {
        if (!empty($_SERVER[$key])) {
            $user = (string) $_SERVER[$key];
            $allowed = oa_config()['admin_users'] ?? null;
            if (is_array($allowed) && $allowed && !in_array($user, $allowed, true)) {
                return null;
            }
            return $user;
        }
    }
    return null;
}

function oa_require_admin(): string
{
    $user = oa_admin_user();
    if ($user === null) {
        http_response_code(403);
        oa_page_open('Admin area not available');
        echo '<section><div class="wrap"><h1>Admin area not available</h1>';
        echo '<p>This area only works once the folder is password-protected by the web server. ';
        echo 'Turn on Directory Privacy for <code>/survey/admin</code> in cPanel, as DEPLOY.md describes.</p>';
        echo '</div></section>';
        oa_page_close();
        exit;
    }
    return $user;
}

function oa_no_store(): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}

function oa_answer_label(string $id, $value): string
{
    $q = survey_questions()[$id] ?? null;
    if (!$q) {
        return '';
    }
    $values = is_array($value) ? $value : [$value];
    $labels = [];
    foreach ($values as $v) {
        $labels[] = $q['options'][$v] ?? (string) $v;
    }
    return implode('; ', $labels);
}
