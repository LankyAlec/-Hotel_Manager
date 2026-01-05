<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

/* fallback se in helpers non esiste require_root */
if (!function_exists('require_root')) { function require_root(){} }
require_root();

function json_out(array $payload, int $code = 200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

$tipo = (string)($_POST['tipo'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($tipo, ['edificio','piano','camera'], true)) {
  json_out(['ok'=>false,'msg'=>'Parametri non validi'], 400);
}

$mysqli->begin_transaction();
try {

  if ($tipo === 'camera') {
    $st = $mysqli->prepare("DELETE FROM camere WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $aff = $st->affected_rows;
    $st->close();

    if ($aff <= 0) throw new RuntimeException('Camera non trovata o già eliminata.');

    $mysqli->commit();
    json_out(['ok'=>true,'msg'=>'Camera eliminata.']);
  }

  if ($tipo === 'piano') {
    // Cancella camere del piano, poi il piano
    $st = $mysqli->prepare("DELETE FROM camere WHERE piano_id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $st->close();

    $st = $mysqli->prepare("DELETE FROM piani WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $aff = $st->affected_rows;
    $st->close();

    if ($aff <= 0) throw new RuntimeException('Piano non trovato o già eliminato.');

    $mysqli->commit();
    json_out(['ok'=>true,'msg'=>'Piano eliminato (camere collegate rimosse).']);
  }

  if ($tipo === 'edificio') {
    // Cancella camere dei piani dell'edificio
    $sql = "DELETE c
            FROM camere c
            JOIN piani p ON p.id = c.piano_id
            WHERE p.edificio_id = ?";
    $st = $mysqli->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $st->close();

    // Cancella piani dell'edificio
    $st = $mysqli->prepare("DELETE FROM piani WHERE edificio_id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $st->close();

    // Cancella edificio
    $st = $mysqli->prepare("DELETE FROM edifici WHERE id=?");
    $st->bind_param("i", $id);
    $st->execute();
    $aff = $st->affected_rows;
    $st->close();

    if ($aff <= 0) throw new RuntimeException('Edificio non trovato o già eliminato.');

    $mysqli->commit();
    json_out(['ok'=>true,'msg'=>'Edificio eliminato (piani e camere collegate rimossi).']);
  }

  throw new RuntimeException('Tipo non gestito.');

} catch (Throwable $e) {
  $mysqli->rollback();
  json_out(['ok'=>false,'msg'=>$e->getMessage()], 500);
}
