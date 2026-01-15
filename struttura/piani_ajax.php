<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$edificio_id = (int)($_GET['edificio_id'] ?? 0);
$selected    = (int)($_GET['piano_id'] ?? 0);

if ($edificio_id <= 0) {
  echo '<div class="hint-sel"><i class="bi bi-arrow-left-right"></i> Seleziona un <b>edificio</b> per visualizzare i piani.</div>';
  exit;
}

/* aggiungo note */
$stmt = $mysqli->prepare("SELECT id, nome, note, attivo FROM struttura_piani WHERE edificio_id=? ORDER BY nome ASC");
if (!$stmt) {
  echo '<div class="alert alert-danger m-3">Errore DB (prepare piani)</div>';
  exit;
}

$stmt->bind_param("i", $edificio_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if (!$res) {
  echo '<div class="alert alert-danger m-3">Errore DB (piani)</div>';
  exit;
}

if ($res->num_rows === 0) {
  echo '<div class="muted-empty">Nessun piano per questo edificio. Usa <b>+</b> per crearne uno.</div>';
  exit;
}

while ($r = $res->fetch_assoc()) {
  $id     = (int)$r['id'];
  $nome   = (string)($r['nome'] ?? '');
  $note   = (string)($r['note'] ?? '');
  $attivo = (int)($r['attivo'] ?? 0);

  $on = ($attivo === 1);
  $badge = $on
    ? '<span class="badge text-bg-success badge-stato">Attivo</span>'
    : '<span class="badge text-bg-secondary badge-stato">Disattivo</span>';
  $checked = $on ? 'checked' : '';

  $rowClass = 'item' . (($selected === $id) ? ' active' : '');

  // (opzionale) mini badge se note presenti
  $noteBadge = (trim($note) !== '')
    ? '<span class="badge bg-light text-dark border"><i class="bi bi-journal-text"></i> Note</span>'
    : '';

  echo '<div class="'.h($rowClass).'"
              data-id="'.$id.'"
              data-nome="'.h($nome).'"
              data-note="'.h($note).'">';

  echo '  <div class="main">';
  echo '    <div class="name">'.h($nome).'</div>';
  echo '    <div class="meta d-flex flex-wrap gap-2 align-items-center">'.$badge.' '.$noteBadge.'</div>';
  echo '  </div>';

  echo '  <div class="acts">';

  echo '    <button type="button" class="btn btn-outline-primary btn-mini js-edit" title="Modifica"'
      .' data-tipo="piano"'
      .' data-id="'.$id.'"'
      .' data-label="'.h($nome).'"'
      .' data-nome="'.h($nome).'"'
      .' data-note="'.h($note).'"'
      .'><i class="bi bi-pencil"></i></button>';

  echo '    <button type="button" class="btn btn-outline-danger btn-mini js-delete" title="Elimina"'
      .' data-tipo="piano" data-id="'.$id.'" data-label="'.h($nome).'"'
      .'><i class="bi bi-trash"></i></button>';

  echo '    <div class="form-check form-switch m-0">';
  echo '      <input class="form-check-input js-toggle-attivo" type="checkbox" data-tipo="piano" data-id="'.$id.'" '.$checked.'>';
  echo '    </div>';

  echo '  </div>';
  echo '</div>';
}
