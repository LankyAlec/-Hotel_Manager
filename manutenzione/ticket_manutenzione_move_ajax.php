<?php
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

$allowed = ['APERTO','IN_CORSO','RISOLTO','ANNULLATO'];
if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID non valido'], 422);
if (!in_array($to, $allowed, true)) json_out(['ok'=>false,'msg'=>'Stato non valido'], 422);

// stato corrente
$st0 = $mysqli->prepare("SELECT stato FROM ticket_manutenzione WHERE id = ?");
if (!$st0) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);
$st0->bind_param("i", $id);
$st0->execute();
$res0 = $st0->get_result();
$row0 = $res0 ? $res0->fetch_assoc() : null;
$st0->close();

if (!$row0) json_out(['ok'=>false,'msg'=>'Ticket non trovato'], 404);

$from = (string)$row0['stato'];

$rules = [
  'APERTO'   => ['IN_CORSO','ANNULLATO'],
  'IN_CORSO' => ['APERTO','RISOLTO','ANNULLATO'],
  'RISOLTO'  => ['IN_CORSO'],
  'ANNULLATO'=> [],
];


if ($to === $from) json_out(['ok'=>true,'msg'=>'Nessuna modifica']);

if ($from === 'ANNULLATO') {
  json_out(['ok'=>false,'msg'=>'Ticket annullato: ripristino solo da modifica'], 422);
}

if (!in_array($to, $rules[$from] ?? [], true)) {
  json_out(['ok'=>false,'msg'=>"Transizione non consentita ($from → $to)"], 422);
}

$uid = (int)($_SESSION['utente_id'] ?? $_SESSION['utente_id'] ?? 0);

if ($to === 'RISOLTO' || $to === 'ANNULLATO') {
  $chiuso_da = $uid > 0 ? $uid : 0;

  $sql = "UPDATE ticket_manutenzione
          SET stato=?, closed_at=NOW(), chiuso_da=NULLIF(?,0)
          WHERE id=?";
  $st = $mysqli->prepare($sql);
  if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);

  $st->bind_param("sii", $to, $chiuso_da, $id);
  $ok = $st->execute();
  $st->close();

  if (!$ok) json_out(['ok'=>false,'msg'=>'Errore update stato'], 500);
  json_out(['ok'=>true,'msg'=>'Stato aggiornato']);
}

// APERTO o IN_CORSO (riaperture / reset chiusura) + opened_at se mancante
$aperto_da = $uid > 0 ? $uid : 0;

$sql = "UPDATE ticket_manutenzione
        SET stato=?,
            closed_at=NULL,
            chiuso_da=NULL,
            opened_at=COALESCE(opened_at, NOW()),
            aperto_da=NULLIF(?,0)
        WHERE id=?";
$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB'], 500);

$st->bind_param("sii", $to, $aperto_da, $id);
$ok = $st->execute();
$st->close();

if (!$ok) json_out(['ok'=>false,'msg'=>'Errore update stato'], 500);

json_out(['ok'=>true,'msg'=>'Stato aggiornato']);
