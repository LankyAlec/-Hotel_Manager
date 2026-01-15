<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$piano_id = (int)($_GET['piano_id'] ?? 0);
if ($piano_id <= 0) {
  echo '<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>piano</b> per visualizzare le camere.</div>';
  exit;
}

$sql = "SELECT *
        FROM struttura_camere
        WHERE piano_id = ?
        ORDER BY (codice REGEXP '^[0-9]+$') DESC,
                 CAST(codice AS UNSIGNED),
                 codice ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  echo '<div class="alert alert-danger rounded-4 p-4"><b>Errore DB</b> (prepare camere)</div>';
  exit;
}
$stmt->bind_param("i", $piano_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
  echo '<div class="muted-empty">Nessuna camera trovata.</div>';
  $stmt->close();
  exit;
}

while ($row = $res->fetch_assoc()) {
  $id     = (int)$row['id'];
  $codice = trim((string)$row['codice']);
  $cap    = (int)$row['capienza_base'];
  $note   = (string)($row['note'] ?? '');
  $attiva = ((int)$row['attiva'] === 1);

  // colonna accessibile_disabili: se non esiste, questo potrebbe dare notice.
  // Se vuoi renderlo "robusto", dimmelo e lo facciamo con controllo colonna.
  $dis = isset($row['accessibile_disabili']) ? ((int)$row['accessibile_disabili'] > 0) : false;

  $badge = $attiva
    ? '<span class="badge bg-success badge-stato">Attivo</span>'
    : '<span class="badge bg-secondary badge-stato">Disattiva</span>';

  $checked = $attiva ? 'checked' : '';

  $noteBadge = (trim($note) !== '')
    ? '<span class="badge bg-light text-dark border"><i class="bi bi-journal-text"></i> Note</span>'
    : '';

  echo '<div class="item"
              data-id="'.$id.'"
              data-codice="'.h($codice).'"
              data-capienza="'.$cap.'"
              data-disabili="'.($dis ? 1 : 0).'"
              data-note="'.h($note).'">

          <div class="main">
            <div class="name">'.h($codice).'</div>

              <div class="meta d-flex flex-wrap gap-2 align-items-center">
              '.$badge.' '.$noteBadge.'
              <span class="badge bg-light text-dark border">
                <i class="bi bi-people"></i> '.$cap.' pax
              </span>';


              if ($dis) {
                echo '<span class="badge bg-primary-subtle text-primary border">
                        <i class="bi bi-person-wheelchair"></i> Accessibile
                      </span>';
              }

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

$stmt->close();
