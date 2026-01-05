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

/* =======================
   INPUT BASE
   ======================= */
$tipo = (string)($_POST['tipo'] ?? '');
$id   = (int)($_POST['id'] ?? 0);
$nome = trim((string)($_POST['nome'] ?? ''));

if ($id <= 0 || !in_array($tipo, ['edificio','piano','camera'], true)) {
  out(['ok'=>false,'msg'=>'Parametri non validi'], 400);
}

if (mb_strlen($nome) > 120) {
  out(['ok'=>false,'msg'=>'Nome troppo lungo'], 400);
}

/* =======================
   EDIFICIO
   ======================= */
if ($tipo === 'edificio') {

  $sql = "UPDATE edifici SET nome=? WHERE id=? LIMIT 1";
  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare edificio)'], 500);

  $st->bind_param("si", $nome, $id);
}

/* =======================
   PIANO
   ======================= */
elseif ($tipo === 'piano') {

  $sql = "UPDATE piani SET nome=? WHERE id=? LIMIT 1";
  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare piano)'], 500);

  $st->bind_param("si", $nome, $id);
}

/* =======================
   CAMERA (CON CONTROLLO)
   ======================= */
else {

  $codice   = trim((string)($_POST['codice'] ?? ''));
  $capienza = (int)($_POST['capienza_base'] ?? 2);
  $disVal   = (int)($_POST['accessibile_disabili'] ?? 0);

  if ($codice === '' || mb_strlen($codice) > 30) {
    out(['ok'=>false,'msg'=>'Numero camera non valido'], 400);
  }
  if ($capienza < 1 || $capienza > 10) {
    out(['ok'=>false,'msg'=>'Capienza non valida'], 400);
  }

  $disVal = $disVal > 0 ? 1 : 0;

  /* =========
     CONTROLLO UNICITÀ NUMERO CAMERA (STESSO EDIFICIO, ESCLUDENDO SE STESSA)
     ========= */
  $sqlChk = "
    SELECT 1
    FROM camere c
    JOIN piani p ON p.id = c.piano_id
    WHERE p.edificio_id = (
      SELECT p2.edificio_id
      FROM camere c2
      JOIN piani p2 ON p2.id = c2.piano_id
      WHERE c2.id = ?
    )
    AND c.codice = ?
    AND c.id <> ?
    LIMIT 1
  ";

  $chk = $mysqli->prepare($sqlChk);
  if (!$chk) out(['ok'=>false,'msg'=>'Errore DB (check camera)'], 500);

  $chk->bind_param("isi", $id, $codice, $id);
  $chk->execute();
  $chk->store_result();

  if ($chk->num_rows > 0) {
    $chk->close();
    out([
      'ok'  => false,
      'msg' => "Esiste già un'altra camera con numero $codice in questo edificio"
    ], 409);
  }
  $chk->close();

  /* =========
     UPDATE CAMERA
     ========= */
  $sql = "UPDATE camere
          SET codice = ?,
              nome = ?,
              capienza_base = ?,
              accessibile_disabili = ?
          WHERE id = ?
          LIMIT 1";

  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare camera)'], 500);

  $st->bind_param(
    "ssiii",
    $codice,
    $nome,
    $capienza,
    $disVal,
    $id
  );
}

/* =======================
   EXEC
   ======================= */
if (!$st->execute()) {
  $st->close();
  out(['ok'=>false,'msg'=>'Errore DB (execute)'], 500);
}
$st->close();

out(['ok'=>true,'msg'=>'Salvato']);
