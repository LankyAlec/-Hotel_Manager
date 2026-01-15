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

$tipo    = (string)($_POST['tipo'] ?? '');
$id      = (int)($_POST['id'] ?? 0);
$val     = (int)($_POST['val'] ?? -1);
$cascade = (string)($_POST['cascade'] ?? 'off_only'); // off_only | always

if (!in_array($tipo, ['edificio','piano','camera'], true) || $id <= 0 || !in_array($val, [0,1], true)) {
  json_out(['ok' => false, 'msg' => 'Parametri non validi'], 422);
}

$out = [
  'ok' => true,
  'tipo' => $tipo,
  'id' => $id,
  'val' => $val,
  'cascade' => $cascade,
  'counts' => ['piani' => 0, 'camere' => 0],
];

try {
  if ($tipo === 'edificio') {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM struttura_piani WHERE edificio_id=?");
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['piani'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM struttura_camere c JOIN piani p ON p.id=c.piano_id WHERE p.edificio_id=?");
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['camere'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

  } elseif ($tipo === 'piano') {
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM struttura_camere WHERE piano_id=?");
    if (!$stmt) throw new Exception($mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $out['counts']['camere'] = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
  }

  $azione = $val === 1 ? 'ATTIVARE' : 'DISATTIVARE';
  $hint = '';
  if ($cascade === 'always' || ($cascade === 'off_only' && $val === 0)) {
    if ($tipo === 'edificio') $hint = "Verranno aggiornati anche {$out['counts']['piani']} piani e {$out['counts']['camere']} camere.";
    if ($tipo === 'piano')    $hint = "Verranno aggiornate anche {$out['counts']['camere']} camere.";
  } else {
    $hint = "Nessuna cascata verrà applicata (solo questo elemento).";
  }

  $out['msg']  = "Confermi di {$azione} questo {$tipo}?";
  $out['hint'] = $hint;

  json_out($out);
} catch (Throwable $e) {
  json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
}
