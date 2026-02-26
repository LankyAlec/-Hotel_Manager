<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function go_back(int $servizio_id, string $ok = '', string $err = ''): void {
  if ($ok)  $_SESSION['flash_ok']  = $ok;
  if ($err) $_SESSION['flash_err'] = $err;
  header("Location: servizio_prezzi.php?id=" . (int)$servizio_id);
  exit;
}

function parse_price(string $label, $value): float {
  $v = trim((string)$value);

  if ($v === '') {
    return 0.0;
  }

  if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
    throw new Exception("Valore non valido per $label.");
  }

  return (float)$v;
}


function date_or_null($s): ?string {
  $s = trim((string)$s);
  if ($s === '') return null;

  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

  if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }
  return null;
}

$azione = $_POST['azione'] ?? '';
$servizio_id = (int)($_POST['servizio_id'] ?? 0);
$tariffa_id  = (int)($_POST['tariffa_id'] ?? 0);

if ($servizio_id <= 0) {
  go_back(0, '', "Servizio non valido.");
}

$sqlSrv = "SELECT id, parent_id FROM servizi WHERE id=" . (int)$servizio_id . " LIMIT 1";
$resSrv = mysqli_query($mysqli, $sqlSrv);
if (!$resSrv) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
$srv = mysqli_fetch_assoc($resSrv);
if (!$srv) go_back($servizio_id, '', "Servizio non trovato.");

if (!empty($srv['parent_id'])) {
  go_back((int)$srv['parent_id'], '', "Questo servizio è un componente: le tariffe si gestiscono sul genitore.");
}

if ($azione === 'delete') {
  if ($tariffa_id <= 0) go_back($servizio_id, '', "Tariffa non valida.");

  $sqlDel = "DELETE FROM servizi_tariffe WHERE id=" . (int)$tariffa_id . " AND servizio_id=" . (int)$servizio_id;
  if (!mysqli_query($mysqli, $sqlDel)) {
    go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
  }

  go_back($servizio_id, "Tariffa eliminata.");
}

if ($azione !== 'insert' && $azione !== 'update') {
  go_back($servizio_id, '', "Azione non valida.");
}

$dal = date_or_null($_POST['dal'] ?? '');
$al  = date_or_null($_POST['al'] ?? '');

if (!$dal) go_back($servizio_id, '', "Data 'Dal' non valida.");
if (trim((string)($_POST['al'] ?? '')) !== '' && !$al) go_back($servizio_id, '', "Data 'Al' non valida.");

if ($al !== null && $al < $dal) {
  go_back($servizio_id, '', "La data 'Al' non può essere prima di 'Dal'.");
}

try {
  $prezzo_slot  = parse_price('Prezzo slot',  $_POST['prezzo_slot']  ?? '');
  $prezzo_extra = parse_price('Prezzo extra', $_POST['prezzo_extra'] ?? '');
} catch (Exception $e) {
  go_back($servizio_id, '', $e->getMessage());
}

$note = trim($_POST['note'] ?? '');
$attiva = !empty($_POST['attiva']) ? 1 : 0;
$endNew = $al ?? '9999-12-31';

$dalEsc = mysqli_real_escape_string($mysqli, $dal);
$endNewEsc = mysqli_real_escape_string($mysqli, $endNew);
$sqlOverlap = "
  SELECT id, dal, al
  FROM servizi_tariffe
  WHERE servizio_id=" . (int)$servizio_id . "
    AND id <> " . (int)$tariffa_id . "
    AND dal <= '{$endNewEsc}'
    AND COALESCE(al, '9999-12-31') >= '{$dalEsc}'
  LIMIT 1
";
$resOverlap = mysqli_query($mysqli, $sqlOverlap);
if (!$resOverlap) go_back($servizio_id, '', "Errore DB: " . $mysqli->error);
$over = mysqli_fetch_assoc($resOverlap);
if ($over) {
  $msg = "Periodo sovrapposto a una tariffa esistente (" . $over['dal'] . " → " . ($over['al'] ?: "senza scadenza") . ").";
  go_back($servizio_id, '', $msg);
}

$dalSql = "'" . mysqli_real_escape_string($mysqli, $dal) . "'";
$alSql = ($al === null) ? 'NULL' : ("'" . mysqli_real_escape_string($mysqli, $al) . "'");
$slotSql = (float)$prezzo_slot;
$extraSql = (float)$prezzo_extra;
$noteSql = "'" . mysqli_real_escape_string($mysqli, $note) . "'";

if ($azione === 'update') {
  if ($tariffa_id <= 0) go_back($servizio_id, '', "Tariffa non valida.");

  $sql = "UPDATE servizi_tariffe
          SET dal={$dalSql}, al={$alSql}, prezzo_slot={$slotSql}, prezzo_extra={$extraSql}, note={$noteSql}, attiva=" . (int)$attiva . "
          WHERE id=" . (int)$tariffa_id . " AND servizio_id=" . (int)$servizio_id;

  if (!mysqli_query($mysqli, $sql)) go_back($servizio_id, '', "Errore salvataggio: " . $mysqli->error);
  go_back($servizio_id, "Tariffa aggiornata ✅");

} else {
  $sql = "INSERT INTO servizi_tariffe (servizio_id, dal, al, prezzo_slot, prezzo_extra, note, attiva)
          VALUES (" . (int)$servizio_id . ", {$dalSql}, {$alSql}, {$slotSql}, {$extraSql}, {$noteSql}, " . (int)$attiva . ")";

  if (!mysqli_query($mysqli, $sql)) go_back($servizio_id, '', "Errore salvataggio: " . $mysqli->error);
  go_back($servizio_id, "Tariffa salvata ✅");
}
