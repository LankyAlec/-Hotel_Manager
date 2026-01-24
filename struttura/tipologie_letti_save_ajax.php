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

$id = (int)($_POST['id'] ?? 0);
$codice = trim((string)($_POST['codice'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

if ($codice === '' || mb_strlen($codice) > 75) {
  out(['ok' => false, 'msg' => 'Codice non valido'], 400);
}
if (mb_strlen($descrizione) > 255) {
  out(['ok' => false, 'msg' => 'Descrizione troppo lunga'], 400);
}
if (mb_strlen($note) > 2000) {
  out(['ok' => false, 'msg' => 'Note troppo lunghe'], 400);
}

if ($isTariffe) {
  $dataDa = trim((string)($_POST['data_da'] ?? ''));
  $dataA = trim((string)($_POST['data_a'] ?? ''));
  $valuta = strtoupper(trim((string)($_POST['valuta'] ?? '')));
  $prezzoSp = (string)($_POST['prezzo_solo_pernottamento'] ?? '');
  $prezzoBb = (string)($_POST['prezzo_BB'] ?? '');
  $prezzoHb = (string)($_POST['prezzo_HB'] ?? '');
  $prezzoFb = (string)($_POST['prezzo_FB'] ?? '');

  if ($dataDa === '') out(['ok' => false, 'msg' => 'Data da obbligatoria'], 400);
  if ($valuta === '' || mb_strlen($valuta) > 3) out(['ok' => false, 'msg' => 'Valuta non valida'], 400);

  foreach ([
    'Prezzo solo pernottamento' => $prezzoSp,
    'Prezzo BB' => $prezzoBb,
    'Prezzo HB' => $prezzoHb,
    'Prezzo FB' => $prezzoFb,
  ] as $label => $value) {
    if ($value === '' || !is_numeric($value)) {
      out(['ok' => false, 'msg' => $label . ' non valido'], 400);
    }
  }

  if ($id > 0) {
    $sql = "UPDATE {$table}
            SET codice = ?, descrizione = ?, data_da = ?, data_a = ?,
                prezzo_solo_pernottamento = ?, prezzo_BB = ?, prezzo_HB = ?, prezzo_FB = ?,
                valuta = ?, note = ?
            WHERE id = ?
            LIMIT 1";
    $st = $mysqli->prepare($sql);
    if (!$st) out(['ok' => false, 'msg' => 'Errore DB (prepare tipologia).'], 500);
    $st->bind_param(
      "ssssddddssi",
      $codice,
      $descrizione,
      $dataDa,
      $dataA,
      $prezzoSp,
      $prezzoBb,
      $prezzoHb,
      $prezzoFb,
      $valuta,
      $note,
      $id
    );
  } else {
    $sql = "INSERT INTO {$table}
            (codice, descrizione, data_da, data_a, prezzo_solo_pernottamento, prezzo_BB, prezzo_HB, prezzo_FB, valuta, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $mysqli->prepare($sql);
    if (!$st) out(['ok' => false, 'msg' => 'Errore DB (prepare tipologia).'], 500);
    $st->bind_param(
      "ssssddddss",
      $codice,
      $descrizione,
      $dataDa,
      $dataA,
      $prezzoSp,
      $prezzoBb,
      $prezzoHb,
      $prezzoFb,
      $valuta,
      $note
    );
  }
} else {
  $fields = ['codice' => $codice];
  if (column_exists($mysqli, $table, 'descrizione')) $fields['descrizione'] = $descrizione;
  if (column_exists($mysqli, $table, 'note')) $fields['note'] = $note;

  $cols = array_keys($fields);
  $placeholders = implode(', ', array_fill(0, count($cols), '?'));
  $types = str_repeat('s', count($cols));
  $values = array_values($fields);

  if ($id > 0) {
    $setParts = implode(', ', array_map(fn($c) => "{$c} = ?", $cols));
    $sql = "UPDATE {$table} SET {$setParts} WHERE id = ? LIMIT 1";
    $st = $mysqli->prepare($sql);
    if (!$st) out(['ok' => false, 'msg' => 'Errore DB (prepare tipologia).'], 500);
    $types .= 'i';
    $values[] = $id;
    $st->bind_param($types, ...$values);
  } else {
    $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES ({$placeholders})";
    $st = $mysqli->prepare($sql);
    if (!$st) out(['ok' => false, 'msg' => 'Errore DB (prepare tipologia).'], 500);
    $st->bind_param($types, ...$values);
  }
}

if (!$st->execute()) {
  $st->close();
  out(['ok' => false, 'msg' => 'Errore DB (execute).'], 500);
}

$newId = $id > 0 ? $id : $mysqli->insert_id;
$st->close();

out(['ok' => true, 'msg' => 'Salvato', 'id' => $newId]);
