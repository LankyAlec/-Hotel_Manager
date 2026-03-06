<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

function table_exists(mysqli $db, string $table): bool {
  $safe = $db->real_escape_string($table);
  $res = $db->query("SHOW TABLES LIKE '{$safe}'");
  return $res && $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool {
  $safeTable  = $db->real_escape_string($table);
  $safeColumn = $db->real_escape_string($column);
  $res = $db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  return $res && $res->num_rows > 0;
}

$piano_id = (int)($_GET['piano_id'] ?? 0);
if ($piano_id <= 0) {
  echo '<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>';
  exit;
}

/* ====== colonne presenti? (evitiamo warning) ====== */
$hasCapienzaBase = column_exists($mysqli, 'struttura_camere', 'capienza_base');
$hasDisabili     = column_exists($mysqli, 'struttura_camere', 'accessibile_disabili');
$hasTipologiaLetti = column_exists($mysqli, 'struttura_camere', 'id_tipologia_letti');

/* ====== tipologie letti (se esiste la colonna) ====== */
$tipologieMap = [];
if ($hasTipologiaLetti) {
  if (table_exists($mysqli, 'soggiorni_tipologie_letti')) {
    $q = "SELECT id, codice, descrizione FROM soggiorni_tipologie_letti ORDER BY id ASC";
    $resTipologie = $mysqli->query($q);
  } elseif (table_exists($mysqli, 'soggiorni_tariffe')) {
    $q = "SELECT id, codice, descrizione FROM soggiorni_tariffe ORDER BY id ASC";
    $resTipologie = $mysqli->query($q);
  } else {
    $resTipologie = null;
  }

  if ($resTipologie) {
    while ($r = $resTipologie->fetch_assoc()) {
      $tipologieMap[(int)$r['id']] = [
        'codice' => (string)$r['codice'],
        'descrizione' => (string)($r['descrizione'] ?? ''),
      ];
    }
  }
}

/* ====== query “semplice” ====== */
$piano_id_sql = (int)$piano_id; // già int
$sql = "
  SELECT *
  FROM struttura_camere
  WHERE piano_id = {$piano_id_sql}
  ORDER BY
    (codice REGEXP '^[0-9]+$') DESC,
    CAST(codice AS UNSIGNED),
    codice ASC
";

$res = $mysqli->query($sql);
if (!$res) {
  echo '<div class="alert alert-danger rounded-4 p-4"><b>Errore DB</b> (camere)</div>';
  exit;
}

if ($res->num_rows === 0) {
  echo '<div class="muted-empty">Nessuna camera trovata.</div>';
  exit;
}

while ($row = $res->fetch_assoc()) {

  $id     = (int)($row['id'] ?? 0);
  $codice = trim((string)($row['codice'] ?? ''));

  // FIX: se capienza_base non esiste -> niente warning
  $cap = 0;
  if ($hasCapienzaBase) {
    $cap = (int)($row['capienza_base'] ?? 0);
  } else {
    // fallback: se hai un altro campo (es. capienza / posti_letto), mettilo qui
    // $cap = (int)($row['capienza'] ?? 0);
    $cap = 0;
  }

  $note   = (string)($row['note'] ?? '');
  $attiva = ((int)($row['attiva'] ?? 0) === 1);

  $dis = false;
  if ($hasDisabili) {
    $dis = ((int)($row['accessibile_disabili'] ?? 0) > 0);
  }

  $tipologiaId = ($hasTipologiaLetti) ? (int)($row['id_tipologia_letti'] ?? 0) : 0;
  $tipologiaLabel = '';
  if ($tipologiaId > 0 && isset($tipologieMap[$tipologiaId])) {
    $t = $tipologieMap[$tipologiaId];
    $tipologiaLabel = $t['descrizione'] ? $t['codice'].' - '.$t['descrizione'] : $t['codice'];
  }

  $badge = $attiva
    ? '<span class="badge bg-success badge-stato">Attivo</span>'
    : '<span class="badge bg-secondary badge-stato">Disattiva</span>';

  $checked = $attiva ? 'checked' : '';

  $noteBadge = (trim($note) !== '')
    ? '<span class="badge bg-light text-dark border"><i class="bi bi-journal-text"></i> Note</span>'
    : '';

  $tipologiaBadge = ($tipologiaLabel !== '')
    ? '<span class="badge bg-light text-dark border"><i class="bi bi-layers"></i> '.h($tipologiaLabel).'</span>'
    : '';

  echo '<div class="item"
              data-id="'.$id.'"
              data-codice="'.h($codice).'"
              data-capienza="'.$cap.'"
              data-disabili="'.($dis ? 1 : 0).'"
              data-tipologia-id="'.($tipologiaId ?: '').'"
              data-note="'.h($note).'">

          <div class="main">
            <div class="name">'.h($codice).'</div>

            <div class="meta d-flex flex-wrap gap-2 align-items-center">
              '.$badge.' '.$noteBadge;

  if ($dis) {
    echo '<span class="badge bg-primary-subtle text-primary border">
            <i class="bi bi-person-wheelchair"></i> Accessibile
          </span>';
  }

  if ($tipologiaBadge) echo $tipologiaBadge;

  echo '    </div>
          </div>

          <div class="acts">
            <button type="button" class="btn btn-outline-primary btn-mini js-edit"
              data-tipo="camera"
              data-id="'.$id.'"
              data-label="'.h($codice).'"
              data-codice="'.h($codice).'"
              data-capienza="'.$cap.'"
              data-disabili="'.($dis ? 1 : 0).'"
              data-tipologia-id="'.($tipologiaId ?: '').'"
              data-note="'.h($note).'"
              title="Modifica">
              <i class="bi bi-pencil"></i>
            </button>

            <button type="button" class="btn btn-outline-danger btn-mini js-delete"
              data-tipo="camera"
              data-id="'.$id.'"
              data-label="'.h($codice).'"
              title="Elimina">
              <i class="bi bi-trash"></i>
            </button>

            <div class="form-check form-switch m-0">
              <input class="form-check-input js-toggle-attivo" type="checkbox"
                data-tipo="camera" data-id="'.$id.'" '.$checked.'>
            </div>
          </div>
        </div>';
}