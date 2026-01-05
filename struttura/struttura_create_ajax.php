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

if (!in_array($tipo, ['edificio','piano','camera'], true)) {
  out(['ok'=>false,'msg'=>'Tipo non valido'], 400);
}

/* =======================
   EDIFICIO
   ======================= */
if ($tipo === 'edificio') {

  $nome = trim((string)($_POST['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 120) {
    out(['ok'=>false,'msg'=>'Nome edificio non valido'], 400);
  }

  $sql = "INSERT INTO edifici (nome, attivo) VALUES (?, 1)";
  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare edificio)'], 500);

  $st->bind_param("s", $nome);
}

/* =======================
   PIANO
   ======================= */
elseif ($tipo === 'piano') {

  $edificio_id = (int)($_POST['edificio_id'] ?? 0);
  $nome = trim((string)($_POST['nome'] ?? ''));

  if ($edificio_id <= 0) out(['ok'=>false,'msg'=>'Edificio non valido'], 400);
  if ($nome === '' || mb_strlen($nome) > 120) out(['ok'=>false,'msg'=>'Nome piano non valido'], 400);

  $sql = "INSERT INTO piani (edificio_id, nome, attivo) VALUES (?, ?, 1)";
  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare piano)'], 500);

  $st->bind_param("is", $edificio_id, $nome);
}

/* =======================
   CAMERA (CON CONTROLLO)
   ======================= */
else {

  $piano_id = (int)($_POST['piano_id'] ?? 0);
  $codice   = trim((string)($_POST['codice'] ?? ''));
  $nomeCam  = trim((string)($_POST['nome'] ?? ''));
  $capienza = (int)($_POST['capienza_base'] ?? 2);
  $disVal   = (int)($_POST['accessibile_disabili'] ?? 0);

  if ($piano_id <= 0) out(['ok'=>false,'msg'=>'Piano non valido'], 400);
  if ($codice === '' || mb_strlen($codice) > 30) out(['ok'=>false,'msg'=>'Numero camera non valido'], 400);
  if (mb_strlen($nomeCam) > 120) out(['ok'=>false,'msg'=>'Nome camera troppo lungo'], 400);
  if ($capienza < 1 || $capienza > 10) out(['ok'=>false,'msg'=>'Capienza non valida'], 400);

  $disVal = $disVal > 0 ? 1 : 0;

  /* =========
     CONTROLLO UNICITÀ CODICE CAMERA NELLO STESSO EDIFICIO
     ========= */
  $sqlChk = "
    SELECT 1
    FROM camere c
    JOIN piani p ON p.id = c.piano_id
    WHERE p.edificio_id = (
      SELECT edificio_id FROM piani WHERE id = ?
    )
    AND c.codice = ?
    LIMIT 1
  ";

  $chk = $mysqli->prepare($sqlChk);
  if (!$chk) out(['ok'=>false,'msg'=>'Errore DB (check camera)'], 500);

  $chk->bind_param("is", $piano_id, $codice);
  $chk->execute();
  $chk->store_result();

  if ($chk->num_rows > 0) {
    $chk->close();
    out([
      'ok'  => false,
      'msg' => "Esiste già una camera con numero $codice in questo edificio"
    ], 409);
  }
  $chk->close();

  /* =========
     INSERT CAMERA
     ========= */
  $sql = "INSERT INTO camere
          (piano_id, codice, nome, capienza_base, accessibile_disabili, attiva)
          VALUES (?, ?, ?, ?, ?, 1)";

  $st = $mysqli->prepare($sql);
  if (!$st) out(['ok'=>false,'msg'=>'Errore DB (prepare camera)'], 500);

  $st->bind_param(
    "issii",
    $piano_id,
    $codice,
    $nomeCam,
    $capienza,
    $disVal
  );
}

/* =======================
   EXEC
   ======================= */
if (!$st->execute()) {
  $st->close();
  out(['ok'=>false,'msg'=>'Errore DB (execute)'], 500);
}

$newId = $mysqli->insert_id;
$st->close();

out([
  'ok'  => true,
  'msg' => 'Creato',
  'id'  => $newId
]);
