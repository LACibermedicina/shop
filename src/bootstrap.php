<?php
/**
 * Inicialização da aplicação PHP (frontend web).
 * Responsabilidades: variáveis de ambiente, sessão segura, conexão SQLite (WAL),
 * proteção CSRF e headers de segurança (TLS 1.3 é garantido pelo nginx/proxy).
 */
declare(strict_types=1);

// ---- Variáveis de ambiente (lê o .env da raiz; fallback para valores seguros) ----
$ENV = [];
if (is_file($envFile = dirname(__DIR__) . '/.env')) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $ENV[trim($k)] = trim($v, " \t\"'");
    }
}
function env(string $key, string $default = ''): string {
    global $ENV;
    return isset($ENV[$key]) && $ENV[$key] !== '' ? $ENV[$key] : (getenv($key) ?: $default);
}

// ---- Sessão endurecida ----
session_name('M3DSESSID');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    'path'     => '/',
]);
session_start();

// ---- Headers de segurança (defesa em profundidade; TLS 1.3 configurado no nginx) ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (($_SERVER['HTTPS'] ?? '') === 'on') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// ---- Conexão SQLite compartilhada com o microsserviço Python ----
const DB_PATH = __DIR__ . '/../data/m3d.sqlite';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // WAL: leitura concorrente entre o PHP (web) e o serviço Python (IA/PDF/webhooks)
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    }
    return $pdo;
}

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_check(): void {
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), (string)$sent)) {
        http_response_code(419);
        exit('CSRF token inválido — recarregue a página.');
    }
}

// ---- Storage privado (equivalente ao Supabase Storage com bucket privado) ----
function private_storage_path(string $relative): string {
    return __DIR__ . '/../data/private/' . basename($relative);
}

// ---- Auxiliares de view ----
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money(int $cents): string { return 'R$ ' . number_format($cents / 100, 2, ',', '.'); }
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

require __DIR__ . '/Auth.php';
require __DIR__ . '/IntegrationClient.php';
