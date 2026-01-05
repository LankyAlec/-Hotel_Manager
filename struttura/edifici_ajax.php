<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

$selected = (int)($_GET['edificio_id'] ?? 0);

$sql = "SELECT id, nome, attivo FROM edifici ORDER BY nome ASC";
$res = $mysqli->query($sql);
if(!$res){
  echo '<div class="alert alert-danger m-3">Errore DB</div>';
  exit;
}

while($r = $res->fetch_assoc()){
  $id = (int)$r['id'];
  $nome = (string)$r['nome'];
  $attivo = ((int)$r['attivo'] === 1);
  $isSel = ($selected === $id);

  $badge = $attivo ? '<span class="badge text-bg-success">Attivo</span>' : '<span class="badge text-bg-secondary">Disattivo</span>';
  $checked = $attivo ? 'checked' : '';
  $rowClass = 'item list-group-item d-flex align-items-center gap-2';
  if($isSel) $rowClass .= ' active';

  echo '<div class="'.h($rowClass).'" role="button" data-id="'.$id.'" data-nome="'.h($nome).'">';
  echo '  <div class="flex-grow-1">';
  echo '    <div class="fw-semibold">'.h($nome).'</div>';
  echo '    <div class="small opacity-75">'.$badge.'</div>';
  echo '  </div>';

  echo '  <button type="button" class="btn btn-sm btn-outline-secondary js-btn-schedule"'
      .' data-tipo="edificio" data-id="'.$id.'" data-current="'.($attivo?1:0).'" data-label="'.h($nome).'"'
      .' title="Schedula attivazione/disattivazione"><i class="bi bi-clock"></i></button>';

  echo '  <div class="form-check form-switch m-0">';
  echo '    <input class="form-check-input js-toggle-attivo" type="checkbox" data-tipo="edificio" data-id="'.(int)$r['id'].'" '.$attivaEffettiva ? 'checked' : ''.' '.$toggleDisabled.'>';
  echo '  </div>';
  echo '</div>';
}
