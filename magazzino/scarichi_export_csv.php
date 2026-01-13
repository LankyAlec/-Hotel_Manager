<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

ini_set('display_errors','0');
error_reporting(0);
while (ob_get_level() > 0) @ob_end_clean();


$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$dest = trim((string)($_GET['dest'] ?? ''));

$where = ["mv.tipo='SCARICO'"];
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $where[] = "mv.ts >= '".esc($conn,$from)." 00:00:00'";
if ($to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $where[] = "mv.ts <= '".esc($conn,$to)." 23:59:59'";
if ($dest !== '') $where[] = "mv.destinazione = '".esc($conn,$dest)."'";

$w = "WHERE ".implode(" AND ",$where);

$sql = "
SELECT mv.ts, p.nome AS prodotto, mv.quantita, mv.destinazione, mv.note,
       CONCAT(u.nome,' ',u.cognome) AS operatore_nome, mv.operatore_id
FROM movimenti mv
JOIN prodotti p ON p.id = mv.prodotto_id
LEFT JOIN utenti u ON u.id = mv.operatore_id
$w
ORDER BY mv.ts DESC, mv.id DESC
";

$res = mysqli_query($conn,$sql);
if (!$res) {
  // fallback senza utenti
  $sql = "
  SELECT mv.ts, p.nome AS prodotto, mv.quantita, mv.destinazione, mv.note, mv.operatore_id
  FROM movimenti mv
  JOIN prodotti p ON p.id = mv.prodotto_id
  $w
  ORDER BY mv.ts DESC, mv.id DESC
  ";
  $res = mysqli_query($conn,$sql);
  if (!$res) { http_response_code(500); exit; }
}

$filename = 'scarichi_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output','w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Data', 'Prodotto', 'Quantita', 'Destinazione', 'Operatore', 'Note'], ';');

while ($r = mysqli_fetch_assoc($res)) {
  $opNome = trim((string)($r['operatore_nome'] ?? ''));
  $opId   = (int)($r['operatore_id'] ?? 0);
  $opLbl  = $opNome !== '' ? $opNome : ($opId > 0 ? ('ID '.$opId) : '');

  fputcsv($out, [
    date('d/m/Y H:i', strtotime((string)$r['ts'])),
    $r['prodotto'] ?? '',
    (int)($r['quantita'] ?? 0),
    $r['destinazione'] ?? '',
    $opLbl,
    $r['note'] ?? '',
  ], ';');
}

fclose($out);
exit;
