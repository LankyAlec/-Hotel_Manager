<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

@ini_set('display_errors', '0');
@error_reporting(E_ALL);

/* ========= JSON helpers ========= */
function json_out(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ========= ALWAYS return JSON on fatal ========= */
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    json_out([
      'ok' => false,
      'fatal' => true,
      'msg' => $e['message'] ?? 'Fatal error',
      'file' => $e['file'] ?? '',
      'line' => $e['line'] ?? 0,
    ], 500);
  }
});

function post_int(string $k): int { return (int)($_POST[$k] ?? 0); }
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }

$id = post_int('id');
if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID non valido'], 422);

$titolo   = post_str('titolo');
$descr    = post_str('descrizione');
$priorita = post_str('priorita');

$edificio_id = post_int('edificio_id'); // 0 = NULL
$piano_id    = post_int('piano_id');    // 0 = NULL
$camera_id   = post_int('camera_id');   // 0 = NULL

// Accetta sia assegnato_a che assegnato (fallback)
$assegnato_a = post_int('assegnato_a'); // 0 = NULL
if ($assegnato_a === 0 && isset($_POST['assegnato'])) {
  $assegnato_a = (int)$_POST['assegnato'];
}

if ($titolo === '') json_out(['ok'=>false,'msg'=>'Titolo obbligatorio'], 422);
if ($priorita === '') $priorita = 'MEDIA';

// gestione eventuale annulla / ripristina da edit
$edit_stato = post_str('edit_stato');
$allowed = ['','ANNULLATO','APERTO','IN_CORSO','RISOLTO'];
if (!in_array($edit_stato, $allowed, true)) $edit_stato = '';

/* ========= UPDATE campi base (compreso assegnato_a) ========= */
$sql = "
UPDATE ticket_manutenzione
SET
  edificio_id = NULLIF(?,0),
  piano_id    = NULLIF(?,0),
  camera_id   = NULLIF(?,0),
  titolo      = ?,
  descrizione = ?,
  priorita    = ?,
  assegnato_a = NULLIF(?,0)
WHERE id = ?
";

$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare edit)'], 500);

$descr_db = ($descr !== '') ? $descr : null;

$st->bind_param(
  "iiisssii",
  $edificio_id,
  $piano_id,
  $camera_id,
  $titolo,
  $descr_db,
  $priorita,
  $assegnato_a,
  $id
);

if (!$st->execute()) {
  $err = $st->error ?: 'Errore update';
  $st->close();
  json_out(['ok'=>false,'msg'=>$err], 500);
}
$st->close();

/* ========= UPDATE stato/date (fix completo placeholders) ========= */
if ($edit_stato !== '') {
  $uid = (int)($_SESSION['utente_id'] ?? $_SESSION['utente_id'] ?? 0);
  $uid = $uid > 0 ? $uid : 0;

  // closed_at e chiuso_da SOLO su RISOLTO
  $closed_at = null;          // NULL per tutti i casi, tranne RISOLTO
  $chiuso_da = 0;             // 0 => NULLIF(?,0) => NULL

  if ($edit_stato === 'RISOLTO' || $edit_stato === 'ANNULLATO') {
    $closed_at = date('Y-m-d H:i:s');
    $chiuso_da = $uid;
  }

  // aperto_da solo quando si imposta APERTO
  $aperto_da = ($edit_stato === 'APERTO') ? $uid : 0;

  $sql2 = "
  UPDATE ticket_manutenzione
  SET
    stato     = ?,
    opened_at = COALESCE(opened_at, NOW()),
    closed_at = ?,
    chiuso_da = NULLIF(?,0),
    aperto_da = CASE WHEN ? = 'APERTO' THEN NULLIF(?,0) ELSE aperto_da END
  WHERE id = ?
  ";
  $st2 = $mysqli->prepare($sql2);
  if (!$st2) json_out(['ok'=>false,'msg'=>'Errore DB (prepare stato)'], 500);

  $st2->bind_param("ssisii", $edit_stato, $closed_at, $chiuso_da, $edit_stato, $aperto_da, $id);

  if (!$st2->execute()) {
    $err2 = $st2->error ?: 'Errore update stato';
    $st2->close();
    json_out(['ok'=>false,'msg'=>$err2], 500);
  }
  $st2->close();
}

json_out([
  'ok'=>true,
  'msg'=>'Ticket aggiornato',
  // utile per capire subito se il POST sta arrivando:
  'debug' => [
    'assegnato_a' => $assegnato_a,
    'edit_stato'  => $edit_stato,
  ]
]);
