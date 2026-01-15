<?php
/* ===========================
   FILE: task_pulizie_edit_ajax.php
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

$id        = post_int('id');
$camera_id = post_int('camera_id');
$data      = post_str('data');
$tipo      = post_str('tipo');
$assegnata = post_int('assegnata_a');
$checklist = ''; // non usato
$note      = post_str('note');

if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID non valido'], 422);
if ($camera_id <= 0) json_out(['ok'=>false,'msg'=>'Camera obbligatoria'], 422);
if ($data === '') json_out(['ok'=>false,'msg'=>'Data obbligatoria'], 422);
if ($tipo === '') $tipo = 'STANDARD';

$allowedTipo = ['STANDARD','EXTRA','CAMBIO_BIANCHERIA','CHECKOUT'];
if (!in_array($tipo, $allowedTipo, true)) $tipo = 'STANDARD';

// annullamento/ripristino non gestiti (non serve annullamento)

$sql = "UPDATE pulizie_task
        SET camera_id=?,
            data=?,
            tipo=?,
            assegnata_a=NULLIF(?,0),
            checklist_json=NULL,
            note=NULLIF(?, '')
        WHERE id=?";

$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare edit)'], 500);

$st->bind_param("issisi", $camera_id, $data, $tipo, $assegnata, $note, $id);

if (!$st->execute()) {
  $err = $st->error ?: 'Errore update';
  $st->close();
  json_out(['ok'=>false,'msg'=>$err], 500);
}
$st->close();

json_out(['ok'=>true,'msg'=>'Pulizia aggiornata']);
