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

/* note comune (per tutti) */
$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 2000) out(['ok'=>false,'msg'=>'Note troppo lunghe'], 400);
$note_sql = $mysqli->real_escape_string($note);

/* =======================
   EDIFICIO
======================= */
if ($tipo === 'edificio') {

  $nome = trim((string)($_POST['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 120) out(['ok'=>false,'msg'=>'Nome edificio non valido'], 400);
  $nome_sql = $mysqli->real_escape_string($nome);

  $sql = "
    INSERT INTO struttura_edifici (nome, note, attivo)
    VALUES ('{$nome_sql}', '{$note_sql}', 1)
  ";

  $ok = $mysqli->query($sql);
  if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (insert edificio)'], 500);

  out(['ok'=>true,'msg'=>'Creato','id'=>(int)$mysqli->insert_id]);
}

/* =======================
   PIANO
======================= */
if ($tipo === 'piano') {

  $edificio_id = (int)($_POST['edificio_id'] ?? 0);
  $nome = trim((string)($_POST['nome'] ?? ''));

  if ($edificio_id <= 0) out(['ok'=>false,'msg'=>'Edificio non valido'], 400);
  if ($nome === '' || mb_strlen($nome) > 120) out(['ok'=>false,'msg'=>'Nome piano non valido'], 400);

  $nome_sql = $mysqli->real_escape_string($nome);

  $sql = "
    INSERT INTO struttura_piani (edificio_id, nome, note, attivo)
    VALUES ({$edificio_id}, '{$nome_sql}', '{$note_sql}', 1)
  ";

  $ok = $mysqli->query($sql);
  if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (insert piano)'], 500);

  out(['ok'=>true,'msg'=>'Creato','id'=>(int)$mysqli->insert_id]);
}

/* =======================
   CAMERA (codice + tipologia + disabili + note)
   - NO capienza
======================= */

$piano_id = (int)($_POST['piano_id'] ?? 0);
$codice   = trim((string)($_POST['codice'] ?? ''));

$tipologiaId = (int)($_POST['id_tipologia_letti'] ?? 0); // OBBLIGATORIA

$disVal = (int)($_POST['accessibile_disabili'] ?? ($_POST['disabili'] ?? 0));
$disVal = $disVal > 0 ? 1 : 0;

if ($piano_id <= 0) out(['ok'=>false,'msg'=>'Piano non valido'], 400);
if ($codice === '' || mb_strlen($codice) > 30) out(['ok'=>false,'msg'=>'Numero camera non valido'], 400);
if ($tipologiaId <= 0) out(['ok'=>false,'msg'=>'Tipologia letti non valida'], 400);

$codice_sql = $mysqli->real_escape_string($codice);

/* =========
   CONTROLLO UNICITÀ CODICE CAMERA NELLO STESSO EDIFICIO
   (stesso edificio del piano scelto)
   ========= */
$sqlChk = "
  SELECT 1
  FROM struttura_camere c
  JOIN struttura_piani p ON p.id = c.piano_id
  WHERE p.edificio_id = (
    SELECT edificio_id
    FROM struttura_piani
    WHERE id = {$piano_id}
    LIMIT 1
  )
  AND c.codice = '{$codice_sql}'
  LIMIT 1
";

$resChk = $mysqli->query($sqlChk);
if (!$resChk) out(['ok'=>false,'msg'=>'Errore DB (check camera)'], 500);

if ($resChk->num_rows > 0) {
  out(['ok'=>false,'msg'=>"Esiste già una camera con numero {$codice} in questo edificio"], 409);
}

/* =========
   INSERT CAMERA (senza capienza)
   ========= */
$sql = "
  INSERT INTO struttura_camere
    (piano_id, codice, id_tipologia_letti, accessibile_disabili, note, attiva)
  VALUES
    ({$piano_id}, '{$codice_sql}', {$tipologiaId}, {$disVal}, '{$note_sql}', 1)
";

$ok = $mysqli->query($sql);
if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (insert camera)'], 500);

out(['ok'=>true,'msg'=>'Creato','id'=>(int)$mysqli->insert_id]);