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

$id = (int)($_POST['id'] ?? 0);
$assegnato = (int)($_POST['assegnato_a'] ?? 0);

if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID non valido'], 422);

$sql = "UPDATE ticket_manutenzione
        SET assegnato_a = NULLIF(?,0)
        WHERE id = ?";
$st = $mysqli->prepare($sql);
if (!$st) json_out(['ok'=>false,'msg'=>'Errore DB (prepare assign)'], 500);

$st->bind_param("ii", $assegnato, $id);
if (!$st->execute()) {
  $err = $st->error ?: 'Errore update';
  $st->close();
  json_out(['ok'=>false,'msg'=>$err], 500);
}
$st->close();

json_out(['ok'=>true,'msg'=>'Assegnazione aggiornata']);
