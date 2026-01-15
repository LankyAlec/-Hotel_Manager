<?php
/* ===========================
   FILE: task_pulizie_meta_ajax.php
   =========================== */
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
    json_out(['ok'=>false,'msg'=>$e['message'] ?? 'Fatal error'], 500);
  }
});

$type = trim((string)($_GET['type'] ?? ''));
if ($type === '') json_out(['ok'=>false,'msg'=>'type mancante'], 422);

function utenti_name_expr(mysqli $mysqli, string $alias='u'): string {
  $cols = [];
  $rs = $mysqli->query("SHOW COLUMNS FROM utenti");
  if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = (string)$r['Field'];

  $hasNome = in_array('nome', $cols, true);
  $hasCogn = in_array('cognome', $cols, true);
  $hasUser = in_array('username', $cols, true);
  $hasMail = in_array('email', $cols, true);

  if ($hasNome && $hasCogn) {
    $expr = "NULLIF(TRIM(CONCAT(COALESCE($alias.cognome,''),' ',COALESCE($alias.nome,''))), '')";
    if ($hasUser) $expr = "COALESCE($expr, NULLIF(TRIM($alias.username),''))";
    if ($hasMail) $expr = "COALESCE($expr, NULLIF(TRIM($alias.email),''))";
    return "COALESCE($expr, '')";
  }
  if ($hasNome) return "COALESCE(NULLIF(TRIM($alias.nome),''), '')";
  if ($hasUser) return "COALESCE(NULLIF(TRIM($alias.username),''), '')";
  if ($hasMail) return "COALESCE(NULLIF(TRIM($alias.email),''), '')";
  return "''";
}

try {
  if ($type === 'edifici') {
    $items = [];
    $rs = $mysqli->query("SELECT id, nome FROM struttura_edifici ORDER BY nome ASC");
    if ($rs) while ($r = $rs->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['nome']];
    }
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'piani') {
    $eid = (int)($_GET['edificio_id'] ?? ($_GET['edificio'] ?? 0));
    if ($eid <= 0) json_out(['ok'=>true,'items'=>[]]);

    $items = [];
    $st = $mysqli->prepare("SELECT id, nome FROM struttura_piani WHERE edificio_id = ? ORDER BY nome ASC");
    if (!$st) throw new RuntimeException('Errore DB (piani)');
    $st->bind_param("i", $eid);
    $st->execute();
    $rs = $st->get_result();
    if ($rs) while ($r = $rs->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['nome']];
    }
    $st->close();
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'camere') {
    $pid = (int)($_GET['piano_id'] ?? ($_GET['piano'] ?? 0));
    if ($pid <= 0) json_out(['ok'=>true,'items'=>[]]);

    $items = [];
    $sql = "SELECT c.id,
               COALESCE(NULLIF(TRIM(c.codice),''), CONCAT('ID ',c.id)) AS label
        FROM struttura_camere c
        WHERE c.piano_id = ?
        ORDER BY
          (c.codice REGEXP '^[0-9]+$') DESC,
          CAST(c.codice AS UNSIGNED) ASC,
          c.codice ASC,
          c.id ASC";

    $st = $mysqli->prepare($sql);
    if (!$st) throw new RuntimeException('Errore DB (camere)');
    $st->bind_param("i", $pid);
    $st->execute();
    $rs = $st->get_result();
    if ($rs) while ($r = $rs->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['label']];
    }
    $st->close();
    json_out(['ok'=>true,'items'=>$items]);
  }

  if ($type === 'camere_all') {
    $items = [];
    $sql = "SELECT c.id,
               CONCAT(
                 COALESCE(e.nome,'Edificio'), ' / ',
                 COALESCE(p.nome,'Piano'), ' / ',
                 COALESCE(NULLIF(TRIM(c.codice),''), CONCAT('ID ',c.id))
               ) AS label
        FROM struttura_camere c
        LEFT JOIN struttura_piani p ON p.id = c.piano_id
        LEFT JOIN struttura_edifici e ON e.id = p.edificio_id
        ORDER BY
          e.nome ASC, p.nome ASC,
          (c.codice REGEXP '^[0-9]+$') DESC,
          CAST(c.codice AS UNSIGNED) ASC,
          c.codice ASC,
          c.id ASC";

    $rs = $mysqli->query($sql);
    if ($rs) while ($r = $rs->fetch_assoc()) {
      $items[] = ['id'=>(int)$r['id'], 'label'=>(string)$r['label']];
    }
    json_out(['ok'=>true,'items'=>$items]);
  }

  // NEW: path camera -> piano + edificio (per pre-selezionare i dropdown in edit)
  if ($type === 'camera_path') {
    $cid = (int)($_GET['camera_id'] ?? 0);
    if ($cid <= 0) json_out(['ok'=>true,'item'=>null]);

    $sql = "SELECT c.id AS camera_id, c.piano_id, p.edificio_id
            FROM struttura_camere c
            LEFT JOIN piani p ON p.id = c.piano_id
            WHERE c.id = ?
            LIMIT 1";
    $st = $mysqli->prepare($sql);
    if (!$st) throw new RuntimeException('Errore DB (camera_path)');
    $st->bind_param('i', $cid);
    $st->execute();
    $rs = $st->get_result();
    $r = $rs ? $rs->fetch_assoc() : null;
    $st->close();

    if (!$r) json_out(['ok'=>true,'item'=>null]);
    json_out(['ok'=>true,'item'=>[
      'camera_id'=>(int)$r['camera_id'],
      'piano_id'=>(int)($r['piano_id'] ?? 0),
      'edificio_id'=>(int)($r['edificio_id'] ?? 0)
    ]]);
  }

  if ($type === 'assegnate') {
    $expr = utenti_name_expr($mysqli, 'u');
    $items = [];
    $sql = "SELECT u.id, ($expr) AS label
            FROM utenti u
            ORDER BY label ASC";
    $rs = $mysqli->query($sql);
    if ($rs) while ($r = $rs->fetch_assoc()) {
      $label = trim((string)($r['label'] ?? ''));
      $items[] = ['id'=>(int)$r['id'], 'label'=>($label !== '' ? $label : ('Utente #'.(int)$r['id']))];
    }
    json_out(['ok'=>true,'items'=>$items]);
  }

  json_out(['ok'=>false,'msg'=>'type non supportato'], 422);

} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
}
