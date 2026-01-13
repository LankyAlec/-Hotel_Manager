<?php
declare(strict_types=1);

/* ======================
 * EXPORT CSV (compatibile PHP 8 / Excel)
 * - Allineato alla logica "giacenza da movimenti" usata in magazzino.php
 * - Supporta stessi filtri (q, magazzino_id, categoria_id, expiring, days, hide_zero)
 * ====================== */

ini_set('display_errors', '0');
error_reporting(0);
while (ob_get_level() > 0) { @ob_end_clean(); }

require __DIR__ . '/init.php';

if (!function_exists('esc')) {
  function esc($conn, string $s): string {
    return mysqli_real_escape_string($conn, $s);
  }
}

/* ======================
 * INPUT
 * ====================== */
$q            = trim((string)($_GET['q'] ?? ''));
$magazzino_id = (int)($_GET['magazzino_id'] ?? 0);
$categoria_id = (int)($_GET['categoria_id'] ?? 0);
$expiring     = (int)($_GET['expiring'] ?? 0);
$days         = max(0, (int)($_GET['days'] ?? 30));

// default: nascondi giacenza 0
$hz_present   = isset($_GET['hz']);
$hide_zero    = $hz_present ? (int)($_GET['hide_zero'] ?? 0) : 1;

/* ======================
 * WHERE (prodotti)
 * ====================== */
$where = ["p.attivo = 1"];
$stockExpr = "COALESCE((SELECT SUM(CASE WHEN mv.tipo='CARICO' THEN mv.quantita ELSE -mv.quantita END) FROM movimenti mv WHERE mv.prodotto_id = p.id),0)";

if ($q !== '') {
  $qq = esc($conn, $q);
  $where[] = "(
    p.nome LIKE '%$qq%'
    OR p.descrizione LIKE '%$qq%'
    OR p.unita LIKE '%$qq%'
    OR EXISTS (
      SELECT 1 FROM lotti lx
      WHERE lx.prodotto_id = p.id
        AND (lx.scaffale LIKE '%$qq%' OR lx.ripiano LIKE '%$qq%')
    )
  )";
}

if ($magazzino_id > 0) $where[] = "p.magazzino_id = $magazzino_id";
if ($categoria_id > 0) $where[] = "p.categoria_id = $categoria_id";
if ($hide_zero === 1)  $where[] = "$stockExpr <> 0";

if ($expiring === 1) {
  $where[] = "EXISTS (
    SELECT 1 FROM lotti l2
    WHERE l2.prodotto_id = p.id
      AND l2.data_scadenza IS NOT NULL
      AND l2.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
  )";
}

$w = 'WHERE ' . implode(' AND ', $where);

/* ======================
 * QUERY: 1 riga per lotto (o riga vuota se nessun lotto)
 * ====================== */
$sql = "
SELECT
  p.nome          AS prodotto,
  p.descrizione   AS descrizione,
  c.tipo          AS categoria_tipo,
  c.nome          AS categoria_nome,
  m.nome          AS magazzino,
  p.unita         AS unita,

  l.id            AS lotto_id,
  l.scaffale      AS scaffale,
  l.ripiano       AS ripiano,
  l.data_scadenza AS scadenza,

  COALESCE(SUM(
    CASE
      WHEN mv.tipo='CARICO'  THEN mv.quantita
      WHEN mv.tipo='SCARICO' THEN -mv.quantita
      ELSE 0
    END
  ),0) AS giacenza,

  (
    SELECT mv2.prezzo
    FROM movimenti mv2
    WHERE mv2.lotto_id = l.id
      AND mv2.tipo='CARICO'
      AND mv2.prezzo IS NOT NULL
    ORDER BY mv2.ts DESC, mv2.id DESC
    LIMIT 1
  ) AS ultimo_prezzo

FROM prodotti p
JOIN magazzini m ON m.id = p.magazzino_id
LEFT JOIN categorie c ON c.id = p.categoria_id
LEFT JOIN lotti l ON l.prodotto_id = p.id
LEFT JOIN movimenti mv ON mv.lotto_id = l.id

$w
GROUP BY p.id, l.id
" . ($hide_zero === 1 ? "HAVING (l.id IS NULL OR giacenza <> 0)" : "") . "
ORDER BY p.nome ASC, (l.data_scadenza IS NULL) ASC, l.data_scadenza ASC, l.id ASC
";

$res = mysqli_query($conn, $sql);
if (!$res) {
  http_response_code(500);
  exit;
}

/* ======================
 * HEADERS CSV
 * ====================== */
$filename = 'magazzino_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM Excel

fputcsv($out, [
  'Prodotto',
  'Descrizione',
  'CategoriaTipo',
  'CategoriaNome',
  'Magazzino',
  'Giacenza',
  'Unita',
  'Prezzo',
  'Scaffale',
  'Ripiano',
  'Scadenza'
], ';');

while ($r = mysqli_fetch_assoc($res)) {

  $qta = ($r['lotto_id'] === null) ? '' : (int)($r['giacenza'] ?? 0);

  $prezzo = '';
  if ($r['ultimo_prezzo'] !== null && $r['ultimo_prezzo'] !== '' && (float)$r['ultimo_prezzo'] > 0) {
    $prezzo = number_format((float)$r['ultimo_prezzo'], 2, ',', '.');
  }

  $scad = '';
  if ($r['scadenza'] !== null && $r['scadenza'] !== '') {
    $ts = strtotime((string)$r['scadenza']);
    if ($ts) $scad = date('d/m/Y', $ts);
  }

  fputcsv($out, [
    $r['prodotto'] ?? '',
    $r['descrizione'] ?? '',
    $r['categoria_tipo'] ?? '',
    $r['categoria_nome'] ?? '',
    $r['magazzino'] ?? '',
    $qta,
    $r['unita'] ?? '',
    $prezzo,
    $r['scaffale'] ?? '',
    $r['ripiano'] ?? '',
    $scad,
  ], ';');
}

fclose($out);
exit;
