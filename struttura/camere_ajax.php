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

/**
 * Schema reale (dal tuo screenshot):
 * camere: id, piano_id, codice, nome (nullable), capienza_base, note (nullable), attiva
 * Campo disabili: opzionale (se aggiungi una colonna tipo accessibile_disabili / disabili lo useremo).
 */

$sql = "SELECT *
        FROM camere
        WHERE piano_id = ?
        ORDER BY (codice REGEXP '^[0-9]+$') DESC,
          CAST(codice AS UNSIGNED),
          codice ASC;";

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
  $id      = (int)$row['id'];
  $codice  = trim((string)$row['codice']);
  $nome    = trim((string)$row['nome']);
  $cap     = (int)$row['capienza_base'];
  $note    = (string)$row['note'];
  $attiva  = (int)$row['attiva'] === 1;
  $dis     = ((int)$row['accessibile_disabili'] > 0);


  // Titolo camera: codice - nome (solo se nome valido e diverso)
  $title = $codice;
  if ($nome !== '' && mb_strtolower($nome) !== mb_strtolower($codice)) {
    $title = $codice . ' - ' . $nome;
  }

  $badge = $attiva
    ? '<span class="badge bg-success badge-stato">Attivo</span>'
    : '<span class="badge bg-secondary badge-stato">Disattiva</span>';

  $checked = $attiva ? 'checked' : '';

  echo '<div class="item" data-id="'.$id.'" data-nome="'.h($nome).'" data-codice="'.h($codice).'"
              data-capienza="'.(int)$cap.'" data-disabili="'.($dis?1:0).'" data-note="'.h($note).'">
          <div class="main">
            <div class="name">'.h($title).'</div>
            <div class="meta d-flex flex-wrap gap-2 align-items-center">
              '.$badge.'
              <span class="badge bg-light text-dark border">
                <i class="bi bi-people"></i> '.$cap.' pax
              </span>';

              if($dis =="1")
                {
                  echo'
                  <span class="badge bg-primary-subtle text-primary border">
                    <i class="bi bi-person-wheelchair"></i> Accessibile
                  </span>';
                }

            echo'
            </div>
          </div>

          <div class="acts">
            <button type="button" class="btn btn-outline-primary btn-mini js-edit"
              data-tipo="camera" data-id="'.$id.'" data-label="'.h($nome).'"
              data-codice="'.h($codice).'" data-capienza="'.(int)$cap.'" data-disabili="'.($dis?1:0).'"
              title="Modifica">
              <i class="bi bi-pencil"></i>
            </button>

            <button type="button" class="btn btn-outline-danger btn-mini js-delete"
              data-tipo="camera" data-id="'.$id.'" data-label="'.h($title).'"
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
