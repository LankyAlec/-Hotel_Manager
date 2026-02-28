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

$tipo = (string)($_POST['tipo'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($tipo, ['edificio','piano','camera'], true)) {
  out(['ok'=>false,'msg'=>'Parametri non validi'], 400);
}

$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 2000) {
  out(['ok'=>false,'msg'=>'Note troppo lunghe'], 400);
}

$note_sql = $mysqli->real_escape_string($note);

/* =======================
   EDIFICIO
======================= */
if ($tipo === 'edificio') {

  $nome = trim((string)($_POST['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 120) {
    out(['ok'=>false,'msg'=>'Nome edificio non valido'], 400);
  }

  $nome_sql = $mysqli->real_escape_string($nome);

  $sql = "
    UPDATE struttura_edifici
    SET nome = '{$nome_sql}',
        note = '{$note_sql}'
    WHERE id = {$id}
    LIMIT 1
  ";

  $ok = $mysqli->query($sql);
  if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (update edificio)'], 500);

  out(['ok'=>true,'msg'=>'Salvato']);
}

/* =======================
   PIANO
======================= */
if ($tipo === 'piano') {

  $nome = trim((string)($_POST['nome'] ?? ''));
  if ($nome === '' || mb_strlen($nome) > 120) {
    out(['ok'=>false,'msg'=>'Nome piano non valido'], 400);
  }

  $nome_sql = $mysqli->real_escape_string($nome);

  $sql = "
    UPDATE struttura_piani
    SET nome = '{$nome_sql}',
        note = '{$note_sql}'
    WHERE id = {$id}
    LIMIT 1
  ";

  $ok = $mysqli->query($sql);
  if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (update piano)'], 500);

  out(['ok'=>true,'msg'=>'Salvato']);
}

/* =======================
   CAMERA (codice + tipologia + disabili + note)
   - NO capienza
======================= */

$codice = trim((string)($_POST['codice'] ?? ''));
if ($codice === '' || mb_strlen($codice) > 30) {
  out(['ok'=>false,'msg'=>'Numero camera non valido'], 400);
}
$codice_sql = $mysqli->real_escape_string($codice);

// tipologia letti OBBLIGATORIA
$tipologiaId = (int)($_POST['id_tipologia_letti'] ?? 0);
if ($tipologiaId <= 0) {
  out(['ok'=>false,'msg'=>'Tipologia letti non valida'], 400);
}

// compatibilità: se arriva disabili invece di accessibile_disabili
$disVal = (int)($_POST['accessibile_disabili'] ?? ($_POST['disabili'] ?? 0));
$disVal = $disVal > 0 ? 1 : 0;

/* =========
   CONTROLLO UNICITÀ CODICE NELLO STESSO EDIFICIO (escludendo se stessa)
   ========= */
$sqlChk = "
  SELECT 1
  FROM struttura_camere c
  JOIN struttura_piani p ON p.id = c.piano_id
  WHERE p.edificio_id = (
    SELECT p2.edificio_id
    FROM struttura_camere c2
    JOIN struttura_piani p2 ON p2.id = c2.piano_id
    WHERE c2.id = {$id}
    LIMIT 1
  )
  AND c.codice = '{$codice_sql}'
  AND c.id <> {$id}
  LIMIT 1
";

$resChk = $mysqli->query($sqlChk);
if (!$resChk) out(['ok'=>false,'msg'=>'Errore DB (check camera)'], 500);

if ($resChk->num_rows > 0) {
  out(['ok'=>false,'msg'=>"Esiste già un'altra camera con numero {$codice} in questo edificio"], 409);
}

/* =========
   UPDATE CAMERA (senza capienza)
   ========= */
$sql = "
  UPDATE struttura_camere
  SET codice = '{$codice_sql}',
      id_tipologia_letti = {$tipologiaId},
      accessibile_disabili = {$disVal},
      note = '{$note_sql}'
  WHERE id = {$id}
  LIMIT 1
";

$ok = $mysqli->query($sql);
if (!$ok) out(['ok'=>false,'msg'=>'Errore DB (update camera)'], 500);

out(['ok'=>true,'msg'=>'Salvato']);