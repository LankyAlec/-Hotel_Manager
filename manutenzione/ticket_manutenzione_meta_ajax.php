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
    json_out([
      'ok' => false,
      'fatal' => true,
      'msg' => $e['message'] ?? 'Fatal error',
      'file' => $e['file'] ?? '',
      'line' => $e['line'] ?? 0,
    ], 500);
  }
});

function col_exists(mysqli $mysqli, string $table, string $col): bool {
  $t = $mysqli->real_escape_string($table);
  $c = $mysqli->real_escape_string($col);
  $sql = "SHOW COLUMNS FROM `$t` LIKE '$c'";
  $r = $mysqli->query($sql);
  return ($r && $r->num_rows > 0);
}

$type = (string)($_GET['type'] ?? '');

try {

  if ($type === 'edifici') {
    // Schema Hotel: struttura_edifici / struttura_piani / struttura_camere
    $table = 'struttura_edifici';
    $hasAttivo = col_exists($mysqli, $table, 'attivo');

    $sql = "SELECT id, nome FROM $table";
    if ($hasAttivo) $sql .= " WHERE attivo = 1";
    $sql .= " ORDER BY nome ASC";

    $res = $mysqli->query($sql);
    if (!$res) json_out(['ok'=>false,'msg'=>'Errore DB (edifici)'], 500);

    $items = [];
    while ($r = $res->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['nome']];
    }
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'piani') {
    $edificio_id = (int)($_GET['edificio_id'] ?? 0);
    if ($edificio_id <= 0) json_out(['ok'=>true,'items'=>[]]);

    $table = 'struttura_piani';
    $hasAttivo   = col_exists($mysqli, $table, 'attivo');
    $hasLivello  = col_exists($mysqli, $table, 'livello');

    $sql = "SELECT id, nome" . ($hasLivello ? ", livello" : "") . " FROM $table WHERE edificio_id = ?";
    if ($hasAttivo) $sql .= " AND attivo = 1";
    $sql .= $hasLivello ? " ORDER BY livello ASC, nome ASC" : " ORDER BY nome ASC";

    $st = $mysqli->prepare($sql);
    if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare piani)'], 500);
    $st->bind_param('i', $edificio_id);
    $st->execute();
    $res = $st->get_result();

    $items = [];
    while ($r = $res->fetch_assoc()) {
      $label = (string)$r['nome'];
      if ($hasLivello && isset($r['livello'])) $label .= " (Liv. " . (int)$r['livello'] . ")";
      $items[] = ['id'=>(int)$r['id'], 'label'=>$label];
    }
    $st->close();
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'camere') {
    $piano_id = (int)($_GET['piano_id'] ?? 0);
    if ($piano_id <= 0) json_out(['ok'=>true,'items'=>[]]);

    $table = 'struttura_camere';
    $hasAttiva = col_exists($mysqli, $table, 'attiva');

    $sql = "SELECT id, codice FROM $table WHERE piano_id = ?";
    if ($hasAttiva) $sql .= " AND attiva = 1";
    $sql .= " ORDER BY (codice REGEXP '^[0-9]+$') DESC, CAST(codice AS UNSIGNED), codice ASC";

    $st = $mysqli->prepare($sql);
    if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare camere)'], 500);
    $st->bind_param('i', $piano_id);
    $st->execute();
    $res = $st->get_result();

    $items = [];
    while ($r = $res->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['codice']];
    }
    $st->close();
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'assegnati') {
    $hasStatus = col_exists($mysqli, 'utenti', 'status');

    $sql = "SELECT id, cognome, nome FROM utenti";
    if ($hasStatus) $sql .= " WHERE status = 'attivo'";
    $sql .= " ORDER BY cognome ASC, nome ASC";

    $res = $mysqli->query($sql);
    if (!$res) json_out(['ok'=>false,'msg'=>'Errore DB (utenti)'], 500);

    $items = [];
    while ($r = $res->fetch_assoc()) {
      $label = trim((string)$r['cognome'] . ' ' . (string)$r['nome']);
      $items[] = ['id'=>(int)$r['id'], 'label'=>$label];
    }
    json_out(['ok'=>true,'items'=>$items]);
  }

  json_out(['ok'=>false,'msg'=>'type non valido'], 400);

} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
}
