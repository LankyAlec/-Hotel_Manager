<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$pianoId = (int)($_GET['piano_id'] ?? 0);

if($pianoId <= 0){
  echo '<div class="hint-sel"><i class="bi bi-arrow-left-circle"></i> Seleziona un <b>piano</b>.</div>';
  exit;
}

$sql = "
SELECT
  c.id, c.nome, c.codice, c.attiva,
  p.attivo AS piano_attivo, p.nome AS piano_nome,
  e.attivo AS edificio_attivo
FROM camere c
JOIN piani p ON p.id = c.piano_id
JOIN edifici e ON e.id = p.edificio_id
WHERE c.piano_id = ?
ORDER BY c.codice ASC, c.nome ASC
";

$st = $mysqli->prepare($sql);
$st->bind_param("i", $pianoId);
$st->execute();
$rs = $st->get_result();

while($r = $rs->fetch_assoc()){
  $id = (int)$r['id'];
  $nome = (string)$r['nome'];
  $codice = (string)$r['codice'];

  $edAttivo = ((int)$r['edificio_attivo'] === 1);
  $pAttivo  = ((int)$r['piano_attivo'] === 1);
  $cAttivo  = ((int)$r['attiva'] === 1);

  $on = $edAttivo && $pAttivo && $cAttivo;
  $disabled = ($edAttivo && $pAttivo) ? '' : 'disabled';

  $badge = $on ? '<span class="badge text-bg-success">Attiva</span>' : '<span class="badge text-bg-secondary">Disattiva</span>';
  $checked = $on ? 'checked' : '';

  echo '<div class="list-group-item d-flex align-items-center gap-2">';
  echo '  <div class="flex-grow-1">';
  echo '    <div class="fw-semibold">'.h($codice).' - '.h($nome).'</div>';
  echo '    <div class="small opacity-75">'.$badge.'</div>';
  echo '  </div>';

  echo '  <button type="button" class="btn btn-sm btn-outline-secondary js-btn-schedule"'
      .' data-tipo="camera" data-id="'.$id.'" data-current="'.($on?1:0).'" data-label="'.h($codice.' - '.$nome).'"'
      .' title="Schedula attivazione/disattivazione"><i class="bi bi-clock"></i></button>';

  echo '  <div class="form-check form-switch m-0">';
  echo '    <input class="form-check-input js-toggle-attivo" type="checkbox" data-tipo="camera" data-id="'.(int)$r['id'].'" '.$attivaEffettiva ? 'checked' : ''.' '.$toggleDisabled.'>';

  echo '  </div>';
  echo '</div>';
}

$st->close();

if($rs->num_rows === 0){
  echo '<div class="hint-sel"><i class="bi bi-info-circle"></i> Nessuna camera su questo piano.</div>';
}
