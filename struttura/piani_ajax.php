<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$edificioId = (int)($_GET['edificio_id'] ?? 0);
$selectedPiano = (int)($_GET['piano_id'] ?? 0);

if($edificioId <= 0){
  echo '<div class="hint-sel"><i class="bi bi-arrow-left-circle"></i> Seleziona un <b>edificio</b>.</div>';
  exit;
}

$st = $mysqli->prepare("SELECT attivo, nome FROM edifici WHERE id=?");
$st->bind_param("i", $edificioId);
$st->execute();
$ed = $st->get_result()->fetch_assoc();
$st->close();

if(!$ed){
  echo '<div class="alert alert-warning m-3">Edificio non trovato.</div>';
  exit;
}

$edificioAttivo = ((int)$ed['attivo'] === 1);

$st = $mysqli->prepare("SELECT id, nome, attivo FROM piani WHERE edificio_id=? ORDER BY nome ASC");
$st->bind_param("i", $edificioId);
$st->execute();
$rs = $st->get_result();

while($r = $rs->fetch_assoc()){
  $id = (int)$r['id'];
  $nome = (string)$r['nome'];
  $pAttivo = ((int)$r['attivo'] === 1);

  $on = $edificioAttivo && $pAttivo;
  $disabled = $edificioAttivo ? '' : 'disabled';
  $badge = $on ? '<span class="badge text-bg-success">Attivo</span>' : '<span class="badge text-bg-secondary">Disattivo</span>';
  $checked = $on ? 'checked' : '';

  $rowClass = 'item list-group-item d-flex align-items-center gap-2';
  if($selectedPiano === $id) $rowClass .= ' active';

  echo '<div class="'.h($rowClass).'" role="button" data-id="'.$id.'" data-nome="'.h($nome).'">';
  echo '  <div class="flex-grow-1">';
  echo '    <div class="fw-semibold">'.h($nome).'</div>';
  echo '    <div class="small opacity-75">'.$badge.'</div>';
  echo '  </div>';

  echo '  <button type="button" class="btn btn-sm btn-outline-secondary js-btn-schedule"'
      .' data-tipo="piano" data-id="'.$id.'" data-current="'.($on?1:0).'" data-label="'.h($nome).'"'
      .' title="Schedula attivazione/disattivazione"><i class="bi bi-clock"></i></button>';

  echo '  <div class="form-check form-switch m-0">';
  echo '    <input class="form-check-input js-toggle-attivo" type="checkbox" data-tipo="piano" data-id="'.$id.'" '.$checked.' '.$disabled.'>';
  echo '  </div>';
  echo '</div>';
}

$st->close();

if($rs->num_rows === 0){
  echo '<div class="hint-sel"><i class="bi bi-info-circle"></i> Nessun piano in questo edificio.</div>';
}
