<?php
/* ===========================
   FILE: task_pulizie_create_ajax.php
   =========================== */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

@ini_set('display_errors', '0');
@error_reporting(E_ALL);

function json_out(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    json_out(['ok'=>false,'msg'=>$e['message'] ?? 'Fatal error'], 500);
  }
});

function post_int(string $k): int { return (int)($_POST[$k] ?? 0); }
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }

$camera_id = post_int('camera_id');
$dataIn    = post_str('data');
// La data è opzionale: se non valida, usa oggi.
$data      = date('Y-m-d');
$tipo      = post_str('tipo');
$assegnata = post_int('assegnata_a');
$checklist = ''; // non usato
$note      = post_str('note');

if ($camera_id <= 0) json_out(['ok'=>false,'msg'=>'Camera obbligatoria'], 422);
if ($tipo === '') $tipo = 'STANDARD';

$allowedTipo = ['STANDARD','EXTRA','CAMBIO_BIANCHERIA','CHECKOUT'];
if (!in_array($tipo, $allowedTipo, true)) $tipo = 'STANDARD';

if ($dataIn !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $dataIn);
  if ($d && $d->format('Y-m-d') === $dataIn) {
    $data = $dataIn;
  }
}

$uid = (int)($_SESSION['utente_id'] ?? $_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0);
$created_by = $uid > 0 ? $uid : 0;

$sql = "INSERT INTO pulizie_task
        (camera_id, data, tipo, stato, assegnata_a, checklist_json, note, created_by)
        VALUES (?, ?, ?, 'DA_FARE', NULLIF(?,0), NULL, NULLIF(?,''), NULLIF(?,0))";

$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare create)'], 500);

$st->bind_param(
  "issisi",
  $camera_id,
  $data,
  $tipo,
  $assegnata,
  $note,
  $created_by
);

if (!$st->execute()) {
  $err = $st->error ?: 'Errore insert';
  $st->close();
  json_out(['ok'=>false,'msg'=>$err], 500);
}
$st->close();

json_out(['ok'=>true,'msg'=>'Pulizia creata']);
