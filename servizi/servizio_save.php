<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function flash_back(string $msg, array $old = [], int $id = 0): void {
    $_SESSION['flash_err'] = $msg;
    $_SESSION['flash_old'] = $old;

    $url = "servizio_edit.php" . ($id > 0 ? "?id=$id" : "");
    header("Location: $url");
    exit;
}

$azione = $_POST['azione'] ?? '';

/* =========================
   Toggle attivo (form classico)
========================= */
if ($azione === 'toggle_attivo') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['flash_err'] = "ID non valido.";
        header("Location: servizi.php");
        exit;
    }

    $sql = "UPDATE servizi SET attivo = IF(attivo=1,0,1) WHERE id=" . (int)$id;
    if (!mysqli_query($mysqli, $sql)) {
        $_SESSION['flash_err'] = "Errore DB: " . $mysqli->error;
        header("Location: servizi.php");
        exit;
    }

    header("Location: servizi.php");
    exit;
}

/* =========================
   Cancella (manuale: figli + genitore)
========================= */
if ($azione === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) die("ID non valido.");

    $mysqli->begin_transaction();

    try {
        // cancella prima i figli (se id è un figlio, non cancella nulla qui: ok)
        $sqlChild = "DELETE FROM servizi WHERE parent_id=" . (int)$id;
        if (!mysqli_query($mysqli, $sqlChild)) throw new RuntimeException($mysqli->error);

        // poi il record richiesto
        $sqlMain = "DELETE FROM servizi WHERE id=" . (int)$id;
        if (!mysqli_query($mysqli, $sqlMain)) throw new RuntimeException($mysqli->error);

        $mysqli->commit();
        header("Location: servizi.php");
        exit;

    } catch (Throwable $e) {
        $mysqli->rollback();
        die("Errore eliminazione: " . $e->getMessage());
    }
}

/* =========================
   Salva
========================= */
if ($azione !== 'salva') {
    flash_back("Azione non valida o mancante.", $_POST, (int)($_POST['id'] ?? 0));
}

$id = (int)($_POST['id'] ?? 0);

/* OLD per ripopolare */
$old = [
    'id' => $id,
    'nome' => $_POST['nome'] ?? '',
    'descrizione' => $_POST['descrizione'] ?? '',
    'note' => $_POST['note'] ?? '',
    'max_persone' => $_POST['max_persone'] ?? 1,
    'durata_slot_min' => $_POST['durata_slot_min'] ?? '',
    'step_extra_min' => $_POST['step_extra_min'] ?? '',
    'attivo' => !empty($_POST['attivo']) ? 1 : 0,
    'prenotabile' => !empty($_POST['prenotabile']) ? 1 : 0,
    'slot_illimitato' => !empty($_POST['slot_illimitato']) ? 1 : 0,
    'parent_id' => $_POST['parent_id'] ?? '',
];

$nome        = trim((string)$old['nome']);
$descrizione = trim((string)$old['descrizione']);
$note        = trim((string)$old['note']);

$max_persone = (int)($old['max_persone'] ?? 1);
if ($max_persone < 1) $max_persone = 1;

$slot_illimitato = (int)($old['slot_illimitato'] ?? 0);

$durata_slot_min = ($old['durata_slot_min'] === '' ? null : (int)$old['durata_slot_min']);
$step_extra_min  = ($old['step_extra_min'] === '' ? null : (int)$old['step_extra_min']);

$attivo      = (int)($old['attivo'] ?? 0);
$prenotabile = (int)($old['prenotabile'] ?? 0);

$parent_id_raw = (string)($old['parent_id'] ?? '');
$parent_id = ($parent_id_raw === '' ? null : (int)$parent_id_raw);

/* Validazioni base */
if ($nome === '') flash_back("Nome mancante.", $old, $id);

if ($id > 0 && $parent_id !== null && $parent_id === $id) {
    flash_back("Errore: un servizio non può appartenere a se stesso.", $old, $id);
}

/* Se parent_id valorizzato: deve esistere ed essere un padre */
if ($parent_id !== null) {
    $sqlParent = "SELECT id FROM servizi WHERE id=" . (int)$parent_id . " AND parent_id IS NULL LIMIT 1";
    $resParent = mysqli_query($mysqli, $sqlParent);
    if (!$resParent) flash_back("Errore DB: " . $mysqli->error, $old, $id);

    if (!mysqli_fetch_assoc($resParent)) {
        flash_back("Servizio padre non valido.", $old, $id);
    }

    // regole componente
    $prenotabile = 0;
    $slot_illimitato = 0;
    $durata_slot_min = null;
    $step_extra_min  = null;
}

/* Se tempo illimitato: NULL su durata/extra */
if ($slot_illimitato === 1) {
    $durata_slot_min = null;
    $step_extra_min  = null;
} else {
    if ($durata_slot_min === null || $durata_slot_min < 15) $durata_slot_min = 60;
    if ($step_extra_min  === null || $step_extra_min  < 5)  $step_extra_min  = 30;
}

/* Nome unico */
$nomeEsc = mysqli_real_escape_string($mysqli, $nome);
if ($id > 0) {
    $sqlNome = "SELECT id FROM servizi WHERE nome='{$nomeEsc}' AND id<>" . (int)$id . " LIMIT 1";
} else {
    $sqlNome = "SELECT id FROM servizi WHERE nome='{$nomeEsc}' LIMIT 1";
}
$resNome = mysqli_query($mysqli, $sqlNome);
if (!$resNome) flash_back("Errore DB: " . $mysqli->error, $old, $id);
if (mysqli_fetch_assoc($resNome)) {
    flash_back("Esiste già un servizio con questo nome.", $old, $id);
}

/* UPDATE / INSERT */
$descrEsc = mysqli_real_escape_string($mysqli, $descrizione);
$noteEsc = mysqli_real_escape_string($mysqli, $note);
$durataSql = ($durata_slot_min === null) ? 'NULL' : (string)(int)$durata_slot_min;
$stepSql = ($step_extra_min === null) ? 'NULL' : (string)(int)$step_extra_min;
$parentSql = ($parent_id === null) ? 'NULL' : (string)(int)$parent_id;

if ($id > 0) {
    $sql = "UPDATE servizi SET "
        . "nome='" . $nomeEsc . "', "
        . "descrizione='" . $descrEsc . "', "
        . "max_persone=" . (int)$max_persone . ", "
        . "durata_slot_min=" . $durataSql . ", "
        . "step_extra_min=" . $stepSql . ", "
        . "attivo=" . (int)$attivo . ", "
        . "prenotabile=" . (int)$prenotabile . ", "
        . "slot_illimitato=" . (int)$slot_illimitato . ", "
        . "parent_id=" . $parentSql . ", "
        . "note='" . $noteEsc . "' "
        . "WHERE id=" . (int)$id;

    if (!mysqli_query($mysqli, $sql)) flash_back("Errore DB: " . $mysqli->error, $old, $id);
    $savedId = $id;

} else {
    $sql = "INSERT INTO servizi "
        . "(nome, descrizione, max_persone, durata_slot_min, step_extra_min, attivo, prenotabile, slot_illimitato, parent_id, note) VALUES ("
        . "'" . $nomeEsc . "', "
        . "'" . $descrEsc . "', "
        . (int)$max_persone . ", "
        . $durataSql . ", "
        . $stepSql . ", "
        . (int)$attivo . ", "
        . (int)$prenotabile . ", "
        . (int)$slot_illimitato . ", "
        . $parentSql . ", "
        . "'" . $noteEsc . "')";

    if (!mysqli_query($mysqli, $sql)) flash_back("Errore DB: " . $mysqli->error, $old, 0);

    $savedId = (int)$mysqli->insert_id;
    if ($savedId <= 0) flash_back("Inserimento eseguito ma ID non ottenuto.", $old, 0);
}

/* Redirect tariffe */
if ($parent_id !== null) {
    header("Location: servizio_prezzi.php?id=" . (int)$parent_id);
} else {
    header("Location: servizio_prezzi.php?id=" . (int)$savedId);
}
exit;
