<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $p, int $code = 200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

function table_exists(mysqli $db, string $table): bool {
  $safe = $db->real_escape_string($table);
  $res = $db->query("SHOW TABLES LIKE '{$safe}'");
  return $res && $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool {
  $safeTable = $db->real_escape_string($table);
  $safeColumn = $db->real_escape_string($column);
  $res = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  return $res && $res->num_rows > 0;
}

$table = null;
$isTariffe = false;
if (table_exists($mysqli, 'soggiorni_tipologie_letti')) {
  $table = 'soggiorni_tipologie_letti';
} elseif (table_exists($mysqli, 'soggiorni_tariffe')) {
  $table = 'soggiorni_tariffe';
  $isTariffe = true;
}

if (!$table) {
  out(['ok' => false, 'msg' => 'Tabella tipologie letti non trovata.'], 404);
}

if ($isTariffe) {
  $descrCol = column_exists($mysqli, $table, 'descrizione') ? 'descrizione' : 'NULL AS descrizione';
  $noteCol = column_exists($mysqli, $table, 'note') ? 'note' : 'NULL AS note';
  $dataDaCol = column_exists($mysqli, $table, 'data_da') ? 'data_da' : 'NULL AS data_da';
  $dataACol = column_exists($mysqli, $table, 'data_a') ? 'data_a' : 'NULL AS data_a';
  $prezzoSpCol = column_exists($mysqli, $table, 'prezzo_solo_pernottamento')
    ? 'prezzo_solo_pernottamento' : 'NULL AS prezzo_solo_pernottamento';
  $prezzoBbCol = column_exists($mysqli, $table, 'prezzo_BB') ? 'prezzo_BB' : 'NULL AS prezzo_BB';
  $prezzoHbCol = column_exists($mysqli, $table, 'prezzo_HB') ? 'prezzo_HB' : 'NULL AS prezzo_HB';
  $prezzoFbCol = column_exists($mysqli, $table, 'prezzo_FB') ? 'prezzo_FB' : 'NULL AS prezzo_FB';
  $valutaCol = column_exists($mysqli, $table, 'valuta') ? 'valuta' : 'NULL AS valuta';

  $sql = "SELECT id, codice, {$descrCol}, {$dataDaCol}, {$dataACol},
          {$prezzoSpCol}, {$prezzoBbCol}, {$prezzoHbCol}, {$prezzoFbCol}, {$valutaCol}, {$noteCol}
          FROM {$table}
          ORDER BY id ASC";
} else {
  $descrCol = column_exists($mysqli, $table, 'descrizione') ? 'descrizione' : 'NULL AS descrizione';
  $noteCol = column_exists($mysqli, $table, 'note') ? 'note' : 'NULL AS note';
  $sql = "SELECT id, codice, {$descrCol}, NULL AS data_da, NULL AS data_a,
          NULL AS prezzo_solo_pernottamento, NULL AS prezzo_BB, NULL AS prezzo_HB,
          NULL AS prezzo_FB, NULL AS valuta, {$noteCol}
          FROM {$table}
          ORDER BY id ASC";
}

$res = $mysqli->query($sql);
if (!$res) {
  out(['ok' => false, 'msg' => 'Errore DB (select tipologie letti).'], 500);
}

$rows = [];
while ($row = $res->fetch_assoc()) {
  $rows[] = [
    'id' => (int)$row['id'],
    'codice' => (string)$row['codice'],
    'descrizione' => (string)($row['descrizione'] ?? ''),
    'data_da' => $row['data_da'] ?? null,
    'data_a' => $row['data_a'] ?? null,
    'prezzo_solo_pernottamento' => $row['prezzo_solo_pernottamento'] ?? null,
    'prezzo_BB' => $row['prezzo_BB'] ?? null,
    'prezzo_HB' => $row['prezzo_HB'] ?? null,
    'prezzo_FB' => $row['prezzo_FB'] ?? null,
    'valuta' => (string)($row['valuta'] ?? ''),
    'note' => (string)($row['note'] ?? ''),
  ];
}

out(['ok' => true, 'rows' => $rows]);
