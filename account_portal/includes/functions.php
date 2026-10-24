<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- output / navigation ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): void { header('Location: ' . BASE_URL . $path); exit; }
function peso($n): string { return '₱ ' . number_format((float)$n, 2); }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

/* ---------- auth ---------- */
function require_login(): void {
    if (empty($_SESSION['user_id'])) redirect('/index.php');
}
function current_user_id(): int { return (int)($_SESSION['user_id'] ?? 0); }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(400);
            die('Invalid or expired form token. Go back and try again.');
        }
    }
}

/* ---------- encryption for game-account passwords (seller must read them back) ---------- */
function encrypt_text(string $plain): string {
    $key = hash('sha256', APP_KEY, true);
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}
function decrypt_text(string $blob): string {
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) return '';
    $key = hash('sha256', APP_KEY, true);
    $out = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return $out === false ? '' : $out;
}

/* ---------- game layouts ---------- */
const RANK_TIERS = ['Mythic', 'Legend', 'Epic'];

function game_meta_all(): array {
    return [
        'Genshin Impact' => [
            'group' => 'hoyo', 'level_label' => 'Adventure Rank', 'level_max' => 60,
            'items_label' => '5★ Characters Available', 'has_rank' => false,
        ],
        'Zenless Zone Zero' => [
            'group' => 'hoyo', 'level_label' => 'Interknot Level', 'level_max' => 60,
            'items_label' => '5★ Characters Available', 'has_rank' => false,
        ],
        'Mobile Legends' => [
            'group' => 'ml', 'level_label' => 'Account Level', 'level_max' => 300,
            'items_label' => 'Valuable Skins Available', 'has_rank' => true,
        ],
    ];
}
function game_meta(string $name): ?array { return game_meta_all()[$name] ?? null; }

/* ---------- small HTML helper: one-button POST form ---------- */
function post_button(string $action, int $id, string $label, string $class = 'btn btn-sm', string $confirm = ''): string {
    $c = $confirm ? ' data-confirm="' . e($confirm) . '"' : '';
    return '<form method="post" class="inline"' . $c . '>' . csrf_field()
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<input type="hidden" name="account_id" value="' . $id . '">'
         . '<button class="' . e($class) . '">' . e($label) . '</button></form>';
}
