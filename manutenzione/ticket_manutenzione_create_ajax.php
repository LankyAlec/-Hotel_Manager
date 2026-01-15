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

/* ========= helpers ========= */
function post_int(string $k): int { return (int)($_POST[$k] ?? 0); }
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }

/* ========= input ========= */
$edificio_id = post_int('edificio_id'); // 0 = NULL
$piano_id    = post_int('piano_id');    // 0 = NULL
$camera_id   = post_int('camera_id');   // 0 = NULL

$titolo      = post_str('titolo');
$descr       = post_str('descrizione');
$priorita    = post_str('priorita');
$assegnato_a = post_int('assegnato_a'); // 0 = NULL

if ($titolo === '') json_out(['ok'=>false,'msg'=>'Titolo obbligatorio'], 422);
if ($priorita === '') $priorita = 'MEDIA';

/* ========= utente loggato ========= */
$uid = (int)($_SESSION['utente_id'] ?? $_SESSION['utente_id'] ?? 0);
if ($uid <= 0) $uid = 0; // 0 = NULLIF -> NULL

$aperto_da = $uid;

/* ========= insert ========= */
$sql = "INSERT INTO ticket_manutenzione
        (edificio_id, piano_id, camera_id,
         titolo, descrizione, priorita, stato,
         assegnato_a, aperto_da, opened_at)
        VALUES
        (NULLIF(?,0), NULLIF(?,0), NULLIF(?,0),
         ?, ?, ?, 'APERTO',
         NULLIF(?,0), NULLIF(?,0), NOW())";

$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare create)'], 500);

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
  $aperto_da
);

$ok = $st->execute();
if (!$ok) {
  $err = $st->error ?: 'Errore insert';
  $st->close();
  json_out(['ok'=>false,'msg'=>$err], 500);
}

$newId = (int)$st->insert_id;
$st->close();

json_out(['ok'=>true,'msg'=>'Ticket creato', 'id'=>$newId]);
