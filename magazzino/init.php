<?php
declare(strict_types=1);

// Bootstrap magazzino pages with the main app stack.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

/**
 * Safe int casting with minimum bound.
 */
function qint($value, int $min = 0): int {
    return max($min, (int)$value);
}

/**
 * Flash helpers (scoped to the magazzino module).
 */
function flash_set(string $type, string $msg): void {
    $_SESSION['flash_magazzino'] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): ?array {
    if (!isset($_SESSION['flash_magazzino'])) {
        return null;
    }
    $f = $_SESSION['flash_magazzino'];
    unset($_SESSION['flash_magazzino']);
    return $f;
}

/**
 * Redirect helper that keeps the magazzino base path.
 */
function mag_redirect(string $path): void {
    $clean = ltrim($path, '/');
    header('Location: ' . BASE_URL . '/magazzino/' . $clean);
    exit;
}

/**
 * DB handle shortcut (MySQLi)
 */
$mysqli = $mysqli ?? $conn ?? null;
if (!$mysqli instanceof mysqli) {
    http_response_code(500);
    echo 'Errore DB: connessione non disponibile.';
    exit;
}
