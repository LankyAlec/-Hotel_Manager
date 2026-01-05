<?php
declare(strict_types=1);

// --- AJAX JSON guard: no HTML, no warnings ---
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

while (ob_get_level() > 0) { @ob_end_clean(); }

function json_out(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
  json_out([
    'ok' => false,
    'php_warning' => true,
    'message' => $message,
    'file' => basename((string)$file),
    'line' => (int)$line,
  ], 500);
});

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    json_out([
      'ok' => false,
      'fatal' => true,
      'message' => $e['message'] ?? 'Fatal error',
      'file' => basename((string)($e['file'] ?? '')),
      'line' => (int)($e['line'] ?? 0),
    ], 500);
  }
});

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!function_exists('require_root')) { function require_root(){} }
require_root();

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
  json_out(['ok' => false, 'msg' => 'Bad request'], 400);
}

$id     = (int)($_POST['id'] ?? 0);
$attivo = (int)($_POST['attivo'] ?? -1);

if ($id <= 0 || !in_array($attivo, [0,1], true)) {
  json_out(['ok' => false, 'msg' => 'Parametri non validi'], 422);
}

$stmt = $mysqli->prepare("UPDATE edifici SET attivo=? WHERE id=?");
if (!$stmt) {
  json_out(['ok' => false, 'msg' => 'Errore DB: ' . $mysqli->error], 500);
}
$stmt->bind_param("ii", $attivo, $id);

if (!$stmt->execute()) {
  json_out(['ok' => false, 'msg' => 'Errore DB: ' . $stmt->error], 500);
}

json_out(['ok' => true]);
