<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

function out(array $p): void { echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }

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
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $where[] = "mv.ts >= '".esc($conn,$from)." 00:00:00'";
if ($to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $where[] = "mv.ts <= '".esc($conn,$to)." 23:59:59'";
if ($dest > 0) $where[] = "mv.id_destinazione = $dest";

$w = "WHERE ".implode(" AND ", $where);

/* ===== operatore join dinamico ===== */
$joinOperatore = "";
$selectOperatore = "'' AS operatore_nome";

if (table_exists($conn, 'magazzino_utenti')) {
  $joinOperatore   = "LEFT JOIN magazzino_utenti u ON u.id = mv.operatore_id";
  $selectOperatore = "CONCAT(COALESCE(u.nome,''),' ',COALESCE(u.cognome,'')) AS operatore_nome";
} elseif (table_exists($conn, 'utenti')) {
  $joinOperatore   = "LEFT JOIN utenti u ON u.id = mv.operatore_id";
  $selectOperatore = "CONCAT(COALESCE(u.nome,''),' ',COALESCE(u.cognome,'')) AS operatore_nome";
}

/* ===== query: JOIN lotti + magazzini ===== */
$sql = "
SELECT
  mv.id,
  mv.ts,
  mv.quantita,
  mv.note,
  mv.operatore_id,
  $selectOperatore,

  p.nome AS prodotto,

  mv.lotto_id,
  l.data_scadenza,
  l.scaffale,
  l.ripiano,
  mz.nome AS magazzino_nome,

  d.nome AS destinazione_nome

FROM magazzino_movimenti mv
JOIN magazzino_prodotti p ON p.id = mv.prodotto_id
JOIN magazzino_lotti l ON l.id = mv.lotto_id
LEFT JOIN magazzini mz ON mz.id = l.magazzino_id
LEFT JOIN magazzino_destinazioni d ON d.id = mv.id_destinazione
$joinOperatore
$w
ORDER BY mv.ts DESC, mv.id DESC
LIMIT 500
";

$res = mysqli_query($conn, $sql);
if (!$res) {
  out(['ok'=>false,'html'=>'<div class="alert alert-danger mb-0">Errore DB</div>']);
}

$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

/* ===== render ===== */
ob_start();
?>
<div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead class="text-secondary">
      <tr>
        <th>Data</th>
        <th>Prodotto</th>
        <th>Magazzino</th>
        <th>Lotto</th>
        <th>Scaffale</th>
        <th>Ripiano</th>
        <th>Scadenza</th>
        <th class="text-end">Qtà</th>
        <th>Destinazione</th>
        <th>Operatore</th>
        <th>Note</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="11" class="text-center text-secondary py-4">Nessuno scarico trovato</td></tr>
    <?php else: foreach ($rows as $r):
      $opNome = trim((string)($r['operatore_nome'] ?? ''));
      $opId   = (int)($r['operatore_id'] ?? 0);
      $opLbl  = $opNome !== '' ? $opNome : ($opId > 0 ? ('ID '.$opId) : '—');

      $scad = trim((string)($r['data_scadenza'] ?? ''));
      $scadLbl = $scad !== '' ? date('d/m/Y', strtotime($scad)) : '—';

      $scaff = trim((string)($r['scaffale'] ?? ''));
      $ripi  = trim((string)($r['ripiano'] ?? ''));
      if ($scaff === '') $scaff = '—';
      if ($ripi  === '') $ripi  = '—';

      $magNome = trim((string)($r['magazzino_nome'] ?? ''));
      if ($magNome === '') $magNome = '—';
    ?>
      <tr>
        <td><?= h(date('d/m/Y H:i', strtotime((string)$r['ts']))) ?></td>
        <td><?= h($r['prodotto'] ?? '') ?></td>
        <td><?= h($magNome) ?></td>
        <td><?= (int)($r['lotto_id'] ?? 0) ?></td>
        <td><?= h($scaff) ?></td>
        <td><?= h($ripi) ?></td>
        <td><?= h($scadLbl) ?></td>
        <td class="text-end"><b><?= (int)($r['quantita'] ?? 0) ?></b></td>
        <td><?= h($r['destinazione_nome'] ?? '—') ?></td>
        <td><?= h($opLbl) ?></td>
        <td><?= h($r['note'] ?? '') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<div class="small text-secondary mt-2">
  Mostrati max 500 record (raffina i filtri per restringere).
</div>
<?php
$html = ob_get_clean();

out(['ok'=>true,'html'=>$html]);
