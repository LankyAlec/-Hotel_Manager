<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

ini_set('display_errors','0');
error_reporting(0);
while (ob_get_level() > 0) { @ob_end_clean(); }

/* ===== helper: check table exists ===== */
function table_exists(mysqli $conn, string $name): bool {
  $name = mysqli_real_escape_string($conn, $name);
  $sql = "SELECT 1
          FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$name'
          LIMIT 1";
  $res = mysqli_query($conn, $sql);
  if (!$res) return false;
  return (bool)mysqli_fetch_row($res);
}

/* ===== input ===== */
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$dest = (int)($_GET['dest'] ?? 0);

/* ===== where ===== */
$where = ["mv.tipo='SCARICO'"];

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $where[] = "mv.ts >= '" . esc($conn, $from) . " 00:00:00'";
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $where[] = "mv.ts <= '" . esc($conn, $to) . " 23:59:59'";
}
if ($dest > 0) {
  $where[] = "mv.id_destinazione = $dest";
}

$w = "WHERE " . implode(" AND ", $where);

/* ===== operatore join (se esiste una tabella utenti) ===== */
$joinOperatore = "";
$selectOperatore = "'' AS operatore_nome";

if (table_exists($conn, 'magazzino_utenti')) {
  $joinOperatore   = "LEFT JOIN magazzino_utenti u ON u.id = mv.operatore_id";
  $selectOperatore = "CONCAT(COALESCE(u.nome,''),' ',COALESCE(u.cognome,'')) AS operatore_nome";
} elseif (table_exists($conn, 'utenti')) {
  $joinOperatore   = "LEFT JOIN utenti u ON u.id = mv.operatore_id";
  // adatta se nel tuo `utenti` i campi si chiamano diversamente (es: username)
  $selectOperatore = "CONCAT(COALESCE(u.nome,''),' ',COALESCE(u.cognome,'')) AS operatore_nome";
}

/* ===== query (NUOVA: passa dai lotti per ottenere magazzino) ===== */
$sql = "
SELECT
  mv.ts,
  p.nome AS prodotto,
  mv.quantita,
  mv.note,
  d.nome AS destinazione_nome,
  mv.operatore_id,
  $selectOperatore,

  mv.lotto_id,
  mz.nome AS magazzino_nome,
  l.scaffale,
  l.ripiano,
  l.data_scadenza

FROM magazzino_movimenti mv
JOIN magazzino_prodotti p ON p.id = mv.prodotto_id
JOIN magazzino_lotti l ON l.id = mv.lotto_id
LEFT JOIN magazzini mz ON mz.id = l.magazzino_id
LEFT JOIN magazzino_destinazioni d ON d.id = mv.id_destinazione
$joinOperatore
$w
ORDER BY mv.ts DESC, mv.id DESC
";

$res = mysqli_query($conn, $sql);
if (!$res) {
  http_response_code(500);
  exit;
}

/* ===== output CSV ===== */
$filename = 'scarichi_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM per Excel

fputcsv($out, [
  'Data',
  'Prodotto',
  'Quantita',
  'Magazzino',
  'Lotto ID',
  'Scaffale',
  'Ripiano',
  'Scadenza',
  'Destinazione',
  'Operatore',
  'Note'
], ';');

while ($r = mysqli_fetch_assoc($res)) {
  $opNome = trim((string)($r['operatore_nome'] ?? ''));
  $opId   = (int)($r['operatore_id'] ?? 0);
  $opLbl  = $opNome !== '' ? $opNome : ($opId > 0 ? ('ID '.$opId) : '');

  $ts = (string)($r['ts'] ?? '');
  $tsIt = $ts !== '' ? date('d/m/Y H:i', strtotime($ts)) : '';

  $scad = (string)($r['data_scadenza'] ?? '');
  $scadIt = $scad !== '' ? date('d/m/Y', strtotime($scad)) : '';

  fputcsv($out, [
    $tsIt,
    (string)($r['prodotto'] ?? ''),
    (int)($r['quantita'] ?? 0),
    (string)($r['magazzino_nome'] ?? ''),
    (int)($r['lotto_id'] ?? 0),
    (string)($r['scaffale'] ?? ''),
    (string)($r['ripiano'] ?? ''),
    $scadIt,
    (string)($r['destinazione_nome'] ?? ''),
    $opLbl,
    (string)($r['note'] ?? ''),
  ], ';');
}

fclose($out);
exit;
