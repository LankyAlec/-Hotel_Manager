<?php
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

function fmt_dt(?string $s): string {
  if (!$s) return '';
  try { return (new DateTime($s))->format('d/m/Y H:i'); }
  catch (Throwable $e) { return (string)$s; }
}

/**
 * Ritorna un'espressione SQL (string) per ottenere un "nome visuale" da tabella utenti.
 * Usa l'alias passato (es. uass/uap/uch) per poter fare più JOIN contemporaneamente.
 */
function utenti_name_expr(mysqli $mysqli, string $alias): string {
  $cols = [];
  $rs = $mysqli->query("SHOW COLUMNS FROM utenti");
  if ($rs) {
    while ($r = $rs->fetch_assoc()) $cols[] = (string)$r['Field'];
  }

  $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
  if ($a === '') $a = 'u';

  $hasNome = in_array('nome', $cols, true);
  $hasCogn = in_array('cognome', $cols, true);
  $hasUser = in_array('username', $cols, true);
  $hasMail = in_array('email', $cols, true);

  // preferisci "Cognome Nome", fallback username/email
  if ($hasNome && $hasCogn) {
    $expr = "NULLIF(TRIM(CONCAT(COALESCE($a.cognome,''),' ',COALESCE($a.nome,''))), '')";
    if ($hasUser) $expr = "COALESCE($expr, NULLIF(TRIM($a.username),''))";
    if ($hasMail) $expr = "COALESCE($expr, NULLIF(TRIM($a.email),''))";
    return "COALESCE($expr, '')";
  }
  if ($hasNome) return "COALESCE(NULLIF(TRIM($a.nome),''), '')";
  if ($hasUser) return "COALESCE(NULLIF(TRIM($a.username),''), '')";
  if ($hasMail) return "COALESCE(NULLIF(TRIM($a.email),''), '')";
  return "''";
}

/* ========= input ========= */
$q = trim((string)($_GET['q'] ?? ''));
$priorita = trim((string)($_GET['priorita'] ?? 'ALL'));
$perPage = max(1, min(50, (int)($_GET['per_page'] ?? 15)));

$pageA = max(1, (int)($_GET['page_aperto'] ?? 1));
$pageI = max(1, (int)($_GET['page_in_corso'] ?? 1));
$pageR = max(1, (int)($_GET['page_risolto'] ?? 1));

$wantAnnullatiHtml = ((int)($_GET['want_annullati_html'] ?? 0) === 1);
$annPage = max(1, (int)($_GET['annullati_page'] ?? 1));

/* ========= name expressions (3 join) ========= */
$nameExprAss = utenti_name_expr($mysqli, 'uass'); // assegnato_a
$nameExprAp  = utenti_name_expr($mysqli, 'uap');  // aperto_da
$nameExprCh  = utenti_name_expr($mysqli, 'uch');  // chiuso_da

/* ========= filtri ========= */
$where = [];
$types = '';
$vals  = [];

if ($q !== '') {
  $where[] = "(t.titolo LIKE CONCAT('%',?,'%')
           OR t.descrizione LIKE CONCAT('%',?,'%')
           OR ($nameExprAss) LIKE CONCAT('%',?,'%')
           OR ($nameExprAp)  LIKE CONCAT('%',?,'%')
           OR ($nameExprCh)  LIKE CONCAT('%',?,'%'))";
  $types .= 'sssss';
  $vals[] = $q; $vals[] = $q; $vals[] = $q; $vals[] = $q; $vals[] = $q;
}

if ($priorita !== '' && $priorita !== 'ALL') {
  $where[] = "t.priorita = ?";
  $types .= 's';
  $vals[] = $priorita;
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

function bind_params(mysqli_stmt $st, string $types, array $vals): void {
  if ($types === '') return;
  $refs = [];
  $refs[] = $types;
  foreach ($vals as $k => $v) $refs[] = &$vals[$k];
  $st->bind_param(...$refs);
}

function fetch_state(
  mysqli $mysqli,
  string $stato,
  int $page,
  int $perPage,
  string $whereSql,
  string $types,
  array $vals,
  string $nameExprAss,
  string $nameExprAp,
  string $nameExprCh
): array {
  $offset = ($page - 1) * $perPage;
  $limit = $perPage + 1;

  $sql = "SELECT
          t.id, t.titolo, t.descrizione, t.stato, t.priorita,
          t.edificio_id, t.piano_id, t.camera_id,
          t.assegnato_a, t.aperto_da, t.chiuso_da,
          t.opened_at AS opened_at,
          t.closed_at AS closed_at,

          se.nome   AS edificio_nome,
          sp.nome   AS piano_nome,
          sc.codice AS camera_codice,

          ($nameExprAss) AS assegnato_nome,
          ($nameExprAp)  AS aperto_nome,
          ($nameExprCh)  AS chiuso_nome
        FROM ticket_manutenzione t
        LEFT JOIN utenti uass ON uass.id = t.assegnato_a
        LEFT JOIN utenti uap  ON uap.id  = t.aperto_da
        LEFT JOIN utenti uch  ON uch.id  = t.chiuso_da

        LEFT JOIN struttura_edifici se ON se.id = t.edificio_id
        LEFT JOIN struttura_piani    sp ON sp.id = t.piano_id
        LEFT JOIN struttura_camere   sc ON sc.id = t.camera_id

        $whereSql
        ".($whereSql ? "AND" : "WHERE")." t.stato = ?
        ORDER BY t.id DESC
        LIMIT $limit OFFSET $offset";


  $st = $mysqli->prepare($sql);
  if (!$st) throw new RuntimeException("Errore DB (prepare $stato)");

  $t = $types . 's';
  $v = $vals;
  $v[] = $stato;
  bind_params($st, $t, $v);

  $st->execute();
  $res = $st->get_result();
  $rows = [];
  if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();

  $hasMore = (count($rows) > $perPage);
  if ($hasMore) array_pop($rows);

  $sqlCount = "SELECT COUNT(*) AS n
               FROM ticket_manutenzione t
               LEFT JOIN utenti uass ON uass.id = t.assegnato_a
               LEFT JOIN utenti uap  ON uap.id  = t.aperto_da
               LEFT JOIN utenti uch  ON uch.id  = t.chiuso_da
               $whereSql
               ".($whereSql ? "AND" : "WHERE")." t.stato = ?";

  $stc = $mysqli->prepare($sqlCount);
  if (!$stc) throw new RuntimeException("Errore DB (prepare count $stato)");

  $t2 = $types . 's';
  $v2 = $vals; $v2[] = $stato;
  bind_params($stc, $t2, $v2);

  $stc->execute();
  $rc = $stc->get_result();
  $n = 0;
  if ($rc && ($x = $rc->fetch_assoc())) $n = (int)$x['n'];
  $stc->close();

  return ['rows'=>$rows,'count'=>$n,'has_more'=>$hasMore];
}

function makeCard(array $r, bool $annullatoList=false): string {
  $id     = (int)$r['id'];
  $titolo = (string)($r['titolo'] ?? '');
  $desc   = (string)($r['descrizione'] ?? '');
  $stato  = (string)($r['stato'] ?? '');
  $prio   = (string)($r['priorita'] ?? 'MEDIA');

  $edificioId = (int)($r['edificio_id'] ?? 0);
  $pianoId    = (int)($r['piano_id'] ?? 0);
  $cameraId   = (int)($r['camera_id'] ?? 0);
  $edificioNome = trim((string)($r['edificio_nome'] ?? ''));
  $pianoNome    = trim((string)($r['piano_nome'] ?? ''));
  $cameraCodice = trim((string)($r['camera_codice'] ?? ''));

  // Costruisci "luogo" (priorità: camera > piano > edificio)
  $luogo = '';
  if ($cameraCodice !== '') {
    $luogo = 'Camera ' . $cameraCodice;
  } elseif ($pianoNome !== '') {
    $luogo = 'Piano ' . $pianoNome;
  } elseif ($edificioNome !== '') {
    $luogo = $edificioNome;
  }

  $badgeLuogo = '';
  if ($luogo !== '') {
    $badgeLuogo = '<span class="badge badge-soft"><i class="bi bi-geo-alt"></i> <b>'.h($luogo).'</b></span>';
  }

  $asId     = (int)($r['assegnato_a'] ?? 0);
  $asNome   = trim((string)($r['assegnato_nome'] ?? ''));

  $apertoId   = (int)($r['aperto_da'] ?? 0);
  $apertoNome = trim((string)($r['aperto_nome'] ?? ''));

  $chiusoId   = (int)($r['chiuso_da'] ?? 0);
  $chiusoNome = trim((string)($r['chiuso_nome'] ?? ''));

  $openedRaw = (string)($r['opened_at'] ?? '');
  $closedRaw = (string)($r['closed_at'] ?? '');

  $opened = fmt_dt($openedRaw);
  $closed = fmt_dt($closedRaw);

  // ===== badges =====
  $prioClass = match ($prio) {
    'BASSA'   => 'prio-bassa',
    'MEDIA'   => 'prio-media',
    'ALTA'    => 'prio-alta',
    'URGENTE' => 'prio-urgente',
    default   => 'prio-media',
  };

  $badgePrio   = '<span class="badge prio '.$prioClass.'"><i class="bi bi-flag"></i> '.h($prio).'</span>';
  $badgeAperto = '<span class="badge badge-soft"><i class="bi bi-calendar-plus"></i> Aperto: <b>'.h($opened !== '' ? $opened : '—').'</b></span>';

  $badgeChiuso = '';
  if ($closed !== '') {
    $badgeChiuso = '<span class="badge badge-soft"><i class="bi bi-calendar-check"></i> Chiuso: <b>'.h($closed).'</b></span>';
  }

  // Riga: assegnato SOLO se definito
  $badgeAssegnato = '';
  if ($asNome !== '') {
    $badgeAssegnato = '<div class="meta-row mt-2">
        <span class="badge badge-soft"><i class="bi bi-person"></i> <b>'.h($asNome).'</b></span>
      </div>';
  }

  $descHtml = trim($desc) !== '' ? '<div class="desc">'.h($desc).'</div>' : '';

  $actions = [];
  $actions[] = '<button type="button" class="btn btn-outline-secondary btn-mini js-edit-ticket" title="Modifica"><i class="bi bi-pencil"></i></button>';

  if (!$annullatoList) {
    if ($stato === 'APERTO') {
      $actions[] = '<button type="button" class="btn btn-outline-primary btn-mini js-move" data-id="'.$id.'" data-to="IN_CORSO" title="Sposta in IN_CORSO"><i class="bi bi-play-circle"></i></button>';
    } elseif ($stato === 'IN_CORSO') {
      $actions[] = '<button type="button" class="btn btn-outline-primary btn-mini js-move" data-id="'.$id.'" data-to="APERTO" title="Torna in APERTO"><i class="bi bi-inbox"></i></button>';
      $actions[] = '<button type="button" class="btn btn-outline-primary btn-mini js-move" data-id="'.$id.'" data-to="RISOLTO" title="Sposta in COMPLETATO"><i class="bi bi-check2-circle"></i></button>';
    } elseif ($stato === 'RISOLTO') {
      $actions[] = '<button type="button" class="btn btn-outline-primary btn-mini js-move" data-id="'.$id.'" data-to="IN_CORSO" title="Riporta in IN_CORSO"><i class="bi bi-play-circle"></i></button>';
    }
  }

  return '<div class="tcard"
    data-id="'.$id.'"
    data-titolo="'.h($titolo).'"
    data-descrizione="'.h($desc).'"
    data-priorita="'.h($prio).'"
    data-stato="'.h($stato).'"
    data-edificio-id="'.$edificioId.'"
    data-piano-id="'.$pianoId.'"
    data-camera-id="'.$cameraId.'"
    data-assegnato-a="'.$asId.'"
    data-assegnato-nome="'.h($asNome).'"
    data-aperto-da="'.$apertoId.'"
    data-aperto-nome="'.h($apertoNome).'"
    data-chiuso-da="'.$chiusoId.'"
    data-chiuso-nome="'.h($chiusoNome).'"
    data-opened-at="'.h($openedRaw).'"
    data-closed-at="'.h($closedRaw).'"
  >
    <div class="top">
      <div style="min-width:0; flex:1;">
        <div class="title">#'.$id.' · '.h($titolo).'</div>

        <div class="meta-row mt-1">'.$badgePrio.'</div>
        <div class="meta-col mt-2">
          '.($badgeLuogo !== '' ? '<div class="meta-row">'.$badgeLuogo.'</div>' : '').'

          <div class="meta-row mt-1">'.$badgeAperto.'</div>
          '.($badgeChiuso !== '' ? '<div class="meta-row mt-1">'.$badgeChiuso.'</div>' : '').'
          '.$badgeAssegnato.'
        </div>

        '.$descHtml.'
      </div>

      <div class="tacts">'.implode('', $actions).'</div>
    </div>
  </div>';
}

/* ========= output ========= */
try {
  $A = fetch_state($mysqli,'APERTO',$pageA,$perPage,$whereSql,$types,$vals,$nameExprAss,$nameExprAp,$nameExprCh);
  $I = fetch_state($mysqli,'IN_CORSO',$pageI,$perPage,$whereSql,$types,$vals,$nameExprAss,$nameExprAp,$nameExprCh);
  $R = fetch_state($mysqli,'RISOLTO',$pageR,$perPage,$whereSql,$types,$vals,$nameExprAss,$nameExprAp,$nameExprCh);

  $html = [
    'APERTO'   => $A['rows'] ? implode('', array_map(fn($r)=>makeCard($r,false), $A['rows'])) : "<div class='muted-empty'>—</div>",
    'IN_CORSO' => $I['rows'] ? implode('', array_map(fn($r)=>makeCard($r,false), $I['rows'])) : "<div class='muted-empty'>—</div>",
    'RISOLTO'  => $R['rows'] ? implode('', array_map(fn($r)=>makeCard($r,false), $R['rows'])) : "<div class='muted-empty'>—</div>",
  ];

  $counts = [
    'APERTO'   => $A['count'],
    'IN_CORSO' => $I['count'],
    'RISOLTO'  => $R['count'],
  ];

  $has_more = [
    'APERTO'   => $A['has_more'],
    'IN_CORSO' => $I['has_more'],
    'RISOLTO'  => $R['has_more'],
  ];

  $Ann = fetch_state($mysqli,'ANNULLATO',$annPage,$perPage,$whereSql,$types,$vals,$nameExprAss,$nameExprAp,$nameExprCh);
  $counts['ANNULLATO'] = $Ann['count'];

  if ($wantAnnullatiHtml) {
    $html['ANNULLATO'] = $Ann['rows'] ? implode('', array_map(fn($r)=>makeCard($r,true), $Ann['rows'])) : "<div class='muted-empty'>Nessun annullato.</div>";
    $has_more['ANNULLATO'] = $Ann['has_more'];
  }

  json_out(['ok'=>true,'html'=>$html,'counts'=>$counts,'has_more'=>$has_more]);

} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
}
