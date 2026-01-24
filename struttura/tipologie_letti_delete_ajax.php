<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $p, int $code = 200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

function table_exists(mysqli $db, string $table): bool {
  $safe = $db->real_escape_string($table);
  $res = $db->query("SHOW TABLES LIKE '{$safe}'");
  return $res && $res->num_rows > 0;
}

$table = null;
if (table_exists($mysqli, 'soggiorni_tipologie_letti')) {
  $table = 'soggiorni_tipologie_letti';
} elseif (table_exists($mysqli, 'soggiorni_tariffe')) {
  $table = 'soggiorni_tariffe';
}

if (!$table) {
  out(['ok' => false, 'msg' => 'Tabella tipologie letti non trovata.'], 404);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  out(['ok' => false, 'msg' => 'ID non valido.'], 400);
}

$sql = "DELETE FROM {$table} WHERE id = ? LIMIT 1";
$st = $mysqli->prepare($sql);
if (!$st) out(['ok' => false, 'msg' => 'Errore DB (prepare delete).'], 500);
$st->bind_param("i", $id);

if (!$st->execute()) {
  $st->close();
  out(['ok' => false, 'msg' => 'Errore DB (execute).'], 500);
}
$st->close();

out(['ok' => true, 'msg' => 'Eliminato']);
