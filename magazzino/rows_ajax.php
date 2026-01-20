<?php
declare(strict_types=1);

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
      'message' => $e['message'] ?? 'Fatal error',
      'file' => $e['file'] ?? '',
      'line' => $e['line'] ?? 0,
    ], 500);
  }
});

require __DIR__ . '/init.php';

/* fallback esc() */
if (!function_exists('esc')) {
  function esc($conn, string $s): string {
    return mysqli_real_escape_string($conn, $s);
  }
}

/* money/date helpers */
function money($v): string { return number_format((float)$v, 2, ',', '.'); }
function date_it(?string $ymd): string {
  $ymd = trim((string)$ymd);
  if ($ymd === '') return '—';
  $ts = strtotime($ymd);
  if (!$ts) return '—';
  return date('d/m/Y', $ts);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  json_out(['ok' => false, 'message' => 'Connessione DB non disponibile.'], 500);
}

/* ========= INPUT ========= */
$q            = trim((string)($_GET['q'] ?? ''));
$magazzino_id = (int)($_GET['magazzino_id'] ?? 0);
$categoria_id = (int)($_GET['categoria_id'] ?? 0);
$expiring     = (int)($_GET['expiring'] ?? 0);
$days         = max(0, (int)($_GET['days'] ?? 30));

$hz_present  = isset($_GET['hz']);
$hide_zero    = $hz_present ? (int)($_GET['hide_zero'] ?? 0) : 1;

$per_page = (int)($_GET['per_page'] ?? 25);
$per_page = in_array($per_page, [10,25,50,100], true) ? $per_page : 25;

$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

/* ========= WHERE ========= */
$where = ["p.attivo=1"];
// Giacenza prodotto: somma movimenti su tutti i lotti (o solo sul magazzino selezionato)
$stockExpr = "COALESCE((
  SELECT SUM(CASE WHEN mv.tipo='CARICO' THEN mv.quantita ELSE -mv.quantita END)
  FROM magazzino_movimenti mv
  JOIN magazzino_lotti l0 ON l0.id = mv.lotto_id
  WHERE mv.prodotto_id = p.id" . ($magazzino_id > 0 ? " AND l0.magazzino_id = " . (int)$magazzino_id : "") . "
),0)";

if ($q !== '') {
  $qq = esc($conn, $q);
  $where[] = "(
    p.nome LIKE '%$qq%'
    OR p.descrizione LIKE '%$qq%'
    OR p.unita LIKE '%$qq%'
    OR EXISTS (
      SELECT 1
      FROM magazzino_lotti lx
      WHERE lx.prodotto_id = p.id
        AND (lx.scaffale LIKE '%$qq%' OR lx.ripiano LIKE '%$qq%')
    )
  )";
}

if ($magazzino_id > 0) {
  $where[] = "EXISTS (SELECT 1 FROM magazzino_lotti lmx WHERE lmx.prodotto_id=p.id AND lmx.magazzino_id=".(int)$magazzino_id.")";
}
if ($categoria_id > 0) $where[] = "p.categoria_id=".(int)$categoria_id;
if ($hide_zero === 1) $where[] = "$stockExpr <> 0";

if ($expiring === 1) {
  $where[] = "EXISTS (
    SELECT 1 FROM magazzino_lotti l2
    WHERE l2.prodotto_id = p.id
      AND l2.data_scadenza IS NOT NULL
      AND l2.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
  )";
}

$w = 'WHERE ' . implode(' AND ', $where);

/* ========= COUNT ========= */
$sqlCount = "
SELECT COUNT(DISTINCT p.id) AS n
FROM magazzino_prodotti p
LEFT JOIN magazzino_categorie c ON c.id = p.categoria_id
$w
";
$res = mysqli_query($conn, $sqlCount);
if (!$res) {
  json_out(['ok'=>false,'stage'=>'COUNT','error'=>mysqli_error($conn),'sql'=>$sqlCount], 500);
}
$r = mysqli_fetch_assoc($res);
$tot = (int)($r['n'] ?? 0);

$total_pages = max(1, (int)ceil($tot / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

/* ========= DATA (prodotti paginati) ========= */
$sql = "
SELECT
  p.*,
  (
    SELECT GROUP_CONCAT(DISTINCT mz.nome ORDER BY mz.nome SEPARATOR ', ')
    FROM magazzino_lotti ll
    JOIN magazzini mz ON mz.id = ll.magazzino_id
    WHERE ll.prodotto_id = p.id" . ($magazzino_id > 0 ? " AND ll.magazzino_id = " . (int)$magazzino_id : "") . "
  ) AS magazzini_label,
  c.nome AS categoria_nome,
  c.tipo AS categoria_tipo
FROM magazzino_prodotti p
LEFT JOIN magazzino_categorie c ON c.id = p.categoria_id
$w
ORDER BY p.nome ASC
LIMIT $per_page OFFSET $offset
";
$res = mysqli_query($conn, $sql);
if (!$res) {
  json_out(['ok'=>false,'stage'=>'DATA','error'=>mysqli_error($conn),'sql'=>$sql], 500);
}

$rows = [];
while ($rr = mysqli_fetch_assoc($res)) $rows[] = $rr;

/* ========= LOTTI per pagina ========= */
$ids = array_map(fn($x) => (int)$x['id'], $rows);
$lotsByProd = [];

if ($ids) {
  $idList = implode(',', $ids);

  $sqlLots = "
    SELECT
      l.id,
      l.prodotto_id,
      l.magazzino_id,
      mz.nome AS magazzino_nome,
      l.scaffale,
      l.ripiano,
      l.data_scadenza,
      COALESCE((
        SELECT SUM(CASE WHEN mv.tipo='CARICO' THEN mv.quantita ELSE -mv.quantita END)
        FROM magazzino_movimenti mv
        WHERE mv.lotto_id = l.id
      ),0) AS giacenza,
      (
        SELECT mv2.prezzo
        FROM magazzino_movimenti mv2
        WHERE mv2.lotto_id = l.id
          AND mv2.tipo='CARICO'
          AND mv2.prezzo IS NOT NULL
        ORDER BY mv2.ts DESC, mv2.id DESC
        LIMIT 1
      ) AS ultimo_prezzo
    FROM magazzino_lotti l
    JOIN magazzini mz ON mz.id = l.magazzino_id
    WHERE l.prodotto_id IN ($idList)
      " . ($magazzino_id > 0 ? " AND l.magazzino_id = " . (int)$magazzino_id : "") . "
      " . ($hide_zero === 1 ? " AND COALESCE((SELECT SUM(CASE WHEN mvx.tipo='CARICO' THEN mvx.quantita ELSE -mvx.quantita END) FROM magazzino_movimenti mvx WHERE mvx.lotto_id = l.id),0) <> 0" : "") . "
    ORDER BY
      l.prodotto_id ASC,
      (l.data_scadenza IS NULL) ASC,
      l.data_scadenza ASC,
      l.id ASC
  ";

  $res = mysqli_query($conn, $sqlLots);
  if (!$res) {
    json_out(['ok'=>false,'stage'=>'LOTS','error'=>mysqli_error($conn),'sql'=>$sqlLots], 500);
  }
  while ($l = mysqli_fetch_assoc($res)) {
    $pid = (int)$l['prodotto_id'];
    $lotsByProd[$pid][] = $l;
  }
}

/* ========= META ========= */
$from = $tot ? ($offset + 1) : 0;
$to   = min($offset + $per_page, $tot);

/* ========= ROWS HTML ========= */
ob_start();

if (!$rows) {
  echo '<tr><td colspan="5" class="text-center py-5 text-secondary">Nessun risultato</td></tr>';
} else {
  foreach ($rows as $r) {
    $pid = (int)$r['id'];
    $catTxt = !empty($r['categoria_nome']) ? (($r['categoria_tipo'] ?? '').' • '.($r['categoria_nome'] ?? '')) : '—';
    $lots = $lotsByProd[$pid] ?? [];

    $totQty = 0;
    foreach ($lots as $l) $totQty += (int)($l['giacenza'] ?? 0);

    ?>
    <tr>
      <td>
        <div class="fw-semibold"><?= h($r['nome']) ?></div>
        <?php if (!empty($r['descrizione'])): ?>
          <div class="small text-secondary"><?= h($r['descrizione']) ?></div>
        <?php endif; ?>

        <div class="small text-secondary mt-1">
          Totale: <strong><?= (int)$totQty ?></strong> <?= h($r['unita']) ?>
          <?php if (count($lots) > 1): ?>
            <div class="mt-1">
              <span class="badge text-bg-light border of-lots-badge">Lotti: <?= count($lots) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </td>

      <td><?= h($catTxt) ?></td>

      <td>
        <?php if (!$lots): ?>
          <span class="text-secondary">— nessun lotto —</span>
        <?php else: ?>
          <div class="of-lots">
            <?php foreach ($lots as $l): ?>
              <?php
                $lottoId = (int)$l['id'];
                $qty = (int)($l['giacenza'] ?? 0);

                $scaff = trim((string)($l['scaffale'] ?? ''));
                $ripi  = trim((string)($l['ripiano'] ?? ''));
                if ($scaff === '') $scaff = '—';
                if ($ripi  === '') $ripi  = '—';

                $prezzo = ((float)($l['ultimo_prezzo'] ?? 0) > 0) ? ('€ '.money($l['ultimo_prezzo'])) : '—';

                $today = date('Y-m-d');
                $limit = date('Y-m-d', strtotime('+' . (int)$days . ' day'));
                $rawScad = !empty($l['data_scadenza']) ? (string)$l['data_scadenza'] : '';
                if ($rawScad === '') {
                  $badge = 'of-exp-na';
                  $scadLabel = '—';
                } else {
                  $isExpired = ($rawScad < $today);
                  if ($isExpired) {
                    $badge = 'of-exp-bad';
                  } else {
                    $isWarn = ($rawScad <= $limit);
                    $badge = $isWarn ? 'of-exp-warn' : 'of-exp-ok';
                  }
                  $scadLabel = date_it($rawScad);
                }
              ?>
              <a class="of-lotrow"
                 href="product_form.php?id=<?= $pid ?>&lotto_id=<?= $lottoId ?>#movimenti"
                 title="Modifica lotto">
                <span class="text-truncate"><?= h((string)($l['magazzino_nome'] ?? '—')) ?></span> 
                <span class="fw-semibold"><?= $qty ?> <?= h($r['unita']) ?></span>
                <span><?= h($prezzo) ?></span>
                <span><?= h($scaff) ?></span>
                <span><?= h($ripi) ?></span>
                <span class="text-end">
                  <span class="of-exp <?= $badge ?>"><?= h($scadLabel) ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </td>

      <td class="text-end">
        <a href="product_form.php?id=<?= $pid ?>"
           class="btn btn-sm btn-outline-primary of-icon-btn"
           title="Modifica prodotto"
           aria-label="Modifica">
          <i class="bi bi-pencil-square"></i>
        </a>

        <form class="d-inline"
              method="post"
              action="magazzino.php"
              onsubmit="return confirm('Eliminare il prodotto?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $pid ?>">
          <button type="submit"
                  class="btn btn-sm btn-outline-danger of-icon-btn"
                  title="Elimina prodotto"
                  aria-label="Elimina">
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </td>
    </tr>
    <?php
  }
}

$rowsHtml = ob_get_clean();

/* ========= PAGINATION HTML ========= */
$prev = max(1, $page - 1);
$next = min($total_pages, $page + 1);

$baseParams = $_GET;

$baseParams['page'] = $prev;
$prevUrl = 'magazzino.php?' . http_build_query($baseParams);

$baseParams['page'] = $next;
$nextUrl = 'magazzino.php?' . http_build_query($baseParams);

ob_start();
?>
<div class="btn-group" role="group" aria-label="Paginazione">
  <a class="btn btn-outline-secondary rounded-start-pill <?= ($page <= 1 ? 'disabled' : '') ?>"
     href="<?= h($prevUrl) ?>">← Precedente</a>

  <span class="btn btn-outline-secondary rounded-0 disabled">
    Pagina <?= (int)$page ?> / <?= (int)$total_pages ?>
  </span>

  <a class="btn btn-outline-secondary rounded-end-pill <?= ($page >= $total_pages ? 'disabled' : '') ?>"
     href="<?= h($nextUrl) ?>">Successiva →</a>
</div>
<?php
$pagHtml = ob_get_clean();

json_out([
  'ok' => true,
  'tot' => $tot,
  'from' => $from,
  'to' => $to,
  'rows_html' => $rowsHtml,
  'pagination_html' => $pagHtml,
]);
