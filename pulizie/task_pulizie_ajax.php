<?php
/* ===========================
   FILE: pulizie_task_ajax.php
   (NO prepare / NO bind_param)
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

function qint($v, int $min = 0): int { return max($min, (int)$v); }
function qstr($v): string { return trim((string)$v); }

function table_has_col(mysqli $mysqli, string $table, string $col): bool {
  $t = $mysqli->real_escape_string($table);
  $c = $mysqli->real_escape_string($col);
  $rs = $mysqli->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $rs && $rs->num_rows > 0;
}

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
  // ===== filtri =====
  $q          = qstr($_GET['q'] ?? '');
  $data       = qstr($_GET['data'] ?? '');
  $tipo       = qstr($_GET['tipo'] ?? 'ALL');

  $edificioId = qint($_GET['edificio_id'] ?? 0, 0);
  $pianoId    = qint($_GET['piano_id'] ?? 0, 0);
  $cameraId   = qint($_GET['camera_id'] ?? 0, 0);

  $perPage = qint($_GET['per_page'] ?? 10, 1);
  $pD = qint($_GET['page_da_fare'] ?? 1, 1);
  $pI = qint($_GET['page_in_corso'] ?? 1, 1);
  $pC = qint($_GET['page_completata'] ?? 1, 1);

  // colonne opzionali
  $hasCreatedAt = table_has_col($mysqli, 'pulizie_task', 'created_at');
  $hasStartedAt = table_has_col($mysqli, 'pulizie_task', 'started_at');
  $hasCompletedAt = table_has_col($mysqli, 'pulizie_task', 'completed_at');

  $hasCreatedBy = table_has_col($mysqli, 'pulizie_task', 'created_by');
  $hasStartedBy = table_has_col($mysqli, 'pulizie_task', 'started_by');
  $hasCompletedBy = table_has_col($mysqli, 'pulizie_task', 'completed_by');

  // join utenti (solo se servono)
  $joinsUsers = "";
  if ($hasCreatedBy)   $joinsUsers .= " LEFT JOIN utenti uC ON uC.id = t.created_by";
  if ($hasStartedBy)   $joinsUsers .= " LEFT JOIN utenti uS ON uS.id = t.started_by";
  if ($hasCompletedBy) $joinsUsers .= " LEFT JOIN utenti uF ON uF.id = t.completed_by";

  $select = "
    t.id, t.camera_id, t.data, t.tipo, t.stato,
    COALESCE(t.assegnata_a, 0) AS assegnata_a,
    COALESCE(t.note,'') AS note,

    e.nome AS edificio_nome,
    p.nome AS piano_nome,
    c.codice AS camera_codice
  ";

  if ($hasCreatedAt)   $select .= ", t.created_at";
  if ($hasStartedAt)   $select .= ", t.started_at";
  if ($hasCompletedAt) $select .= ", t.completed_at";

  if ($hasCreatedBy)   $select .= ", (" . utenti_name_expr($mysqli, 'uC') . ") AS created_by_name";
  if ($hasStartedBy)   $select .= ", (" . utenti_name_expr($mysqli, 'uS') . ") AS started_by_name";
  if ($hasCompletedBy) $select .= ", (" . utenti_name_expr($mysqli, 'uF') . ") AS completed_by_name";

  $from = "
    FROM pulizie_task t
    LEFT JOIN struttura_camere c ON c.id = t.camera_id
    LEFT JOIN struttura_piani  p ON p.id = c.piano_id
    LEFT JOIN struttura_edifici e ON e.id = p.edificio_id
    $joinsUsers
  ";

  // ===== WHERE (senza prepare) =====
  $where = [];
  $where[] = "1=1";

  if ($data !== '') {
    $dataEsc = $mysqli->real_escape_string($data);
    $where[] = "t.data = '$dataEsc'";
  }

  if ($tipo !== '' && $tipo !== 'ALL') {
    $tipoEsc = $mysqli->real_escape_string($tipo);
    $where[] = "t.tipo = '$tipoEsc'";
  }

  if ($cameraId > 0) {
    $where[] = "t.camera_id = " . (int)$cameraId;
  } elseif ($pianoId > 0) {
    $where[] = "c.piano_id = " . (int)$pianoId;
  } elseif ($edificioId > 0) {
    $where[] = "p.edificio_id = " . (int)$edificioId;
  }

  if ($q !== '') {
    $qEsc = $mysqli->real_escape_string($q);
    $like = "%$qEsc%";
    $where[] = "(
      t.note LIKE '$like'
      OR c.nome LIKE '$like'
      OR c.codice LIKE '$like'
      OR p.nome LIKE '$like'
      OR e.nome LIKE '$like'
    )";
  }

  $whereSql = implode(" AND ", $where);
  $order = " ORDER BY t.data DESC, t.id DESC ";

  // ===== counts =====
  $counts = ['DA_FARE'=>0,'IN_CORSO'=>0,'COMPLETATA'=>0];

  $countSql = "
    SELECT t.stato, COUNT(*) AS cnt
    $from
    WHERE $whereSql
    GROUP BY t.stato
  ";

  $rsCnt = $mysqli->query($countSql);
  if ($rsCnt === false) {
    json_out(['ok'=>false,'msg'=>'Errore DB (count): '.$mysqli->error], 500);
  }
  while ($r = $rsCnt->fetch_assoc()) {
    $stato = (string)$r['stato'];
    if (isset($counts[$stato])) $counts[$stato] = (int)$r['cnt'];
  }

  // ===== liste per stato =====
  $map = [
    'DA_FARE'    => $pD,
    'IN_CORSO'   => $pI,
    'COMPLETATA' => $pC,
  ];

  $html = [];
  $hasMore = ['DA_FARE'=>false,'IN_CORSO'=>false,'COMPLETATA'=>false];

  foreach ($map as $stato => $page) {
    $offset = ($page - 1) * $perPage;
    $statoEsc = $mysqli->real_escape_string($stato);

    $sql = "
      SELECT $select
      $from
      WHERE $whereSql AND t.stato = '$statoEsc'
      $order
      LIMIT " . (int)($perPage + 1) . " OFFSET " . (int)$offset;

    $rs = $mysqli->query($sql);
    if ($rs === false) {
      json_out(['ok'=>false,'msg'=>'Errore DB (list): '.$mysqli->error], 500);
    }

    $rows = [];
    while ($r = $rs->fetch_assoc()) $rows[] = $r;

    if (count($rows) > $perPage) {
      $hasMore[$stato] = true;
      $rows = array_slice($rows, 0, $perPage);
    }

    if (!$rows) {
      $html[$stato] = "<div class='muted-empty'>Nessuna pulizia.</div>";
      continue;
    }

    $buf = "";
    foreach ($rows as $r) {
      $id = (int)$r['id'];
      $camId = (int)$r['camera_id'];
      $dataTask = (string)$r['data'];
      $tipoTask = (string)$r['tipo'];
      $assA = (int)$r['assegnata_a'];
      $note = (string)$r['note'];

      $edNome = (string)($r['edificio_nome'] ?? '');
      $piNome = (string)($r['piano_nome'] ?? '');
      $cCod   = (string)($r['camera_codice'] ?? '');

      // Le camere non hanno nome: mostra solo codice (fallback ID)
      $tit = trim($cCod !== '' ? $cCod : ("ID " . $camId));

      $sub = trim(($edNome !== '' ? $edNome : 'Edificio') . " / " . ($piNome !== '' ? $piNome : 'Piano'));

      $createdAt = $hasCreatedAt ? (string)($r['created_at'] ?? '') : '';
      $startedAt = $hasStartedAt ? (string)($r['started_at'] ?? '') : '';
      $completedAt = $hasCompletedAt ? (string)($r['completed_at'] ?? '') : '';

      $createdByName = $hasCreatedBy ? (string)($r['created_by_name'] ?? '') : '';
      $startedByName = $hasStartedBy ? (string)($r['started_by_name'] ?? '') : '';
      $completedByName = $hasCompletedBy ? (string)($r['completed_by_name'] ?? '') : '';

      // move (no annullamento)
      $moveBtns = "";
      if ($stato === 'DA_FARE') {
        $moveBtns .= "<button type='button' class='btn btn-outline-primary btn-mini js-move' data-id='$id' data-to='IN_CORSO' title='Avvia'><i class='bi bi-play-circle'></i></button>";
      } elseif ($stato === 'IN_CORSO') {
        $moveBtns .= "<button type='button' class='btn btn-outline-secondary btn-mini js-move' data-id='$id' data-to='DA_FARE' title='Riporta a Da fare'><i class='bi bi-inbox'></i></button>";
        $moveBtns .= "<button type='button' class='btn btn-outline-success btn-mini js-move' data-id='$id' data-to='COMPLETATA' title='Completa'><i class='bi bi-check2-circle'></i></button>";
      } else { // COMPLETATA
        $moveBtns .= "<button type='button' class='btn btn-outline-primary btn-mini js-move' data-id='$id' data-to='IN_CORSO' title='Riapri'><i class='bi bi-arrow-counterclockwise'></i></button>";
      }

      $buf .= "
        <div class='tcard'
          data-id='".h((string)$id)."'
          data-stato='".h($stato)."'
          data-camera-id='".h((string)$camId)."'
          data-data='".h($dataTask)."'
          data-tipo='".h($tipoTask)."'
          data-assegnata-a='".h((string)$assA)."'
          data-note='".h($note)."'
          data-created-at='".h($createdAt)."'
          data-started-at='".h($startedAt)."'
          data-completed-at='".h($completedAt)."'
          data-created-by-name='".h($createdByName)."'
          data-started-by-name='".h($startedByName)."'
          data-completed-by-name='".h($completedByName)."'
        >
          <div class='top'>
            <div>
              <div class='title'>".h($tit)."</div>
              <div class='small text-muted'>".h($sub)." • <span class='badge-soft'>".h($tipoTask)."</span> • <span class='badge-soft'>".h($dataTask)."</span></div>
            </div>
            <div class='tacts'>
              <button type='button' class='btn btn-outline-secondary btn-mini js-edit' title='Modifica'>
                <i class='bi bi-pencil'></i>
              </button>
              $moveBtns
            </div>
          </div>
      ";

      if (trim($note) !== '') {
        $buf .= "<div class='desc'>".h($note)."</div>";
      }

      $buf .= "</div>";
    }

    $html[$stato] = $buf;
  }

  json_out([
    'ok' => true,
    'html' => $html,
    'counts' => $counts,
    'has_more' => $hasMore
  ]);

} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
}
