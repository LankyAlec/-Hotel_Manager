<?php
/* ===========================
   FILE: pulizie_task_move_ajax.php
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

$id = (int)($_POST['id'] ?? 0);
$to = trim((string)($_POST['stato'] ?? ''));

$allowed = ['DA_FARE','IN_CORSO','COMPLETATA'];
if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID non valido'], 422);
if (!in_array($to, $allowed, true)) json_out(['ok'=>false,'msg'=>'Stato non valido'], 422);

// stato corrente
$st0 = $mysqli->prepare("SELECT stato FROM pulizie_task WHERE id = ?");
if (!$st0) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);
$st0->bind_param("i", $id);
$st0->execute();
$res0 = $st0->get_result();
$row0 = $res0 ? $res0->fetch_assoc() : null;
$st0->close();

if (!$row0) json_out(['ok'=>false,'msg'=>'Task non trovato'], 404);

$from = (string)$row0['stato'];
if ($from === 'ANNULLATA') {
  json_out(['ok'=>false,'msg'=>'Task annullata: ripristino solo da modifica'], 422);
}
if ($to === $from) json_out(['ok'=>true,'msg'=>'Nessuna modifica']);

// regole transizione
$rules = [
  'DA_FARE'    => ['IN_CORSO'],
  'IN_CORSO'   => ['DA_FARE','COMPLETATA'],
  'COMPLETATA' => ['IN_CORSO'],
];
if (!in_array($to, $rules[$from] ?? [], true)) {
  json_out(['ok'=>false,'msg'=>"Transizione non consentita ($from → $to)"], 422);
}

$uid = (int)($_SESSION['uid'] ?? $_SESSION['user_id'] ?? 0);
$uid = $uid > 0 ? $uid : 0;

if ($to === 'IN_CORSO') {
  // da DA_FARE o da COMPLETATA -> IN_CORSO (riapertura)
  $sql = "UPDATE pulizie_task
          SET stato='IN_CORSO',
              started_at=COALESCE(started_at, NOW()),
              started_by=COALESCE(started_by, NULLIF(?,0)),
              completed_at=NULL,
              completed_by=NULL
          WHERE id=?";
  $st = $mysqli->prepare($sql);
  if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);
  $st->bind_param("ii", $uid, $id);
  $ok = $st->execute();
  $st->close();
  if (!$ok) json_out(['ok'=>false,'msg'=>'Errore update'], 500);
  json_out(['ok'=>true,'msg'=>'Stato aggiornato']);
}

if ($to === 'COMPLETATA') {
  $sql = "UPDATE pulizie_task
          SET stato='COMPLETATA',
              started_at=COALESCE(started_at, NOW()),
              started_by=COALESCE(started_by, NULLIF(?,0)),
              completed_at=NOW(),
              completed_by=NULLIF(?,0)
          WHERE id=?";
  $st = $mysqli->prepare($sql);
  if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);
  $st->bind_param("iii", $uid, $uid, $id);
  $ok = $st->execute();
  $st->close();
  if (!$ok) json_out(['ok'=>false,'msg'=>'Errore update'], 500);
  json_out(['ok'=>true,'msg'=>'Stato aggiornato']);
}

// to DA_FARE (solo da IN_CORSO): reset inizio/fine
$sql = "UPDATE pulizie_task
        SET stato='DA_FARE',
            started_at=NULL,
            started_by=NULL,
            completed_at=NULL,
            completed_by=NULL
        WHERE id=?";
$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);
$st->bind_param("i", $id);
$ok = $st->execute();
$st->close();

if (!$ok) json_out(['ok'=>false,'msg'=>'Errore update'], 500);

json_out(['ok'=>true,'msg'=>'Stato aggiornato']);
