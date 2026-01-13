<?php
declare(strict_types=1);

require __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
error_reporting(E_ALL);

function out(array $p){ echo json_encode($p, JSON_UNESCAPED_UNICODE); exit; }


$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$dest = (int)($_GET['dest'] ?? 0);

$where = ["mv.tipo='SCARICO'"];
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $where[] = "mv.ts >= '".esc($conn,$from)." 00:00:00'";
if ($to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   $where[] = "mv.ts <= '".esc($conn,$to)." 23:59:59'";
if ($dest > 0) $where[] = "mv.id_destinazione = $dest";

$w = "WHERE ".implode(" AND ",$where);

$sql = "
SELECT mv.id, mv.ts, mv.quantita, mv.note, mv.operatore_id,
       p.nome AS prodotto, l.data_scadenza,
       d.nome AS destinazione_nome,
       CONCAT(u.nome,' ',u.cognome) AS operatore_nome
FROM movimenti mv
JOIN prodotti p ON p.id = mv.prodotto_id
LEFT JOIN lotti l ON l.id = mv.lotto_id
LEFT JOIN destinazioni d ON d.id = mv.id_destinazione
LEFT JOIN utenti u ON u.id = mv.operatore_id
$w
ORDER BY mv.ts DESC, mv.id DESC
LIMIT 500
";

$res = mysqli_query($conn,$sql);
if (!$res) {
  // fallback senza utenti
  $sql = "
  SELECT mv.id, mv.ts, mv.quantita, mv.note, mv.operatore_id,
         p.nome AS prodotto, l.data_scadenza,
         d.nome AS destinazione_nome
  FROM movimenti mv
  JOIN prodotti p ON p.id = mv.prodotto_id
  LEFT JOIN lotti l ON l.id = mv.lotto_id
  LEFT JOIN destinazioni d ON d.id = mv.id_destinazione
  $w
  ORDER BY mv.ts DESC, mv.id DESC
  LIMIT 500
  ";
  $res = mysqli_query($conn,$sql);
  if (!$res) out(['ok'=>false,'html'=>'<div class="alert alert-danger mb-0">Errore DB</div>']);
}

$rows = [];
while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;

ob_start();
?>
<div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead class="text-secondary">
      <tr>
        <th>Data</th>
        <th>Prodotto</th>
        <th>Scadenza</th>
        <th class="text-end">Qtà</th>
        <th>Destinazione</th>
        <th>Operatore</th>
        <th>Note</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="text-center text-secondary py-4">Nessuno scarico trovato</td></tr>
    <?php else: foreach ($rows as $r):
      $opNome = trim((string)($r['operatore_nome'] ?? ''));
      $opId   = (int)($r['operatore_id'] ?? 0);
      $opLbl  = $opNome !== '' ? $opNome : ($opId > 0 ? ('ID '.$opId) : '—');
      $scad = trim((string)($r['data_scadenza'] ?? ''));
      $scadLbl = $scad !== '' ? date('d/m/Y', strtotime($scad)) : '—';
    ?>
      <tr>
        <td><?= h(date('d/m/Y H:i', strtotime((string)$r['ts']))) ?></td>
        <td><?= h($r['prodotto'] ?? '') ?></td>
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