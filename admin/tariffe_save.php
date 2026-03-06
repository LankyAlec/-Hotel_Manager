<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/tariffe.php');
    exit;
}

$azione = (string)($_POST['azione'] ?? '');
$tariffaId = (int)($_POST['tariffa_id'] ?? 0);
$redirect = BASE_URL . '/admin/tariffe.php';

$parseMoney = static function ($raw): float {
    $s = str_replace([' ', ','], ['', '.'], (string)$raw);
    if ($s === '' || !is_numeric($s)) {
        return -1;
    }
    return (float)$s;
};

$setFlash = static function (string $type, string $msg) use ($redirect): void {
    $_SESSION['flash_' . $type] = $msg;
    header('Location: ' . $redirect);
    exit;
};

if ($azione === 'delete') {
    if ($tariffaId <= 0) {
        $setFlash('err', 'ID tariffa non valido.');
    }

    $sqlDelete = 'DELETE FROM soggiorni_tariffe WHERE id=' . (int)$tariffaId . ' LIMIT 1';
    if (!mysqli_query($mysqli, $sqlDelete)) {
        $setFlash('err', 'Errore DB in cancellazione: ' . $mysqli->error);
    }

    if (mysqli_affected_rows($mysqli) > 0) {
        $setFlash('ok', 'Tariffa eliminata con successo.');
    }

    $setFlash('err', 'Tariffa non trovata o già eliminata.');
}

if (!in_array($azione, ['insert', 'update'], true)) {
    $setFlash('err', 'Azione non valida.');
}

$codice = trim((string)($_POST['codice'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));
$dataDa = trim((string)($_POST['data_da'] ?? ''));
$dataA = trim((string)($_POST['data_a'] ?? ''));
$prezzoSP = $parseMoney($_POST['prezzo_solo_pernottamento'] ?? '');
$prezzoBB = $parseMoney($_POST['prezzo_BB'] ?? '');
$prezzoHB = $parseMoney($_POST['prezzo_HB'] ?? '');
$prezzoFB = $parseMoney($_POST['prezzo_FB'] ?? '');

if ($codice === '' || $dataDa === '' || $prezzoSP < 0 || $prezzoBB < 0 || $prezzoHB < 0 || $prezzoFB < 0) {
    $setFlash('err', 'Compila tutti i campi obbligatori con valori validi.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataDa)) {
    $setFlash('err', 'Data "da" non valida.');
}

$dataASql = 'NULL';
if ($dataA !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataA)) {
        $setFlash('err', 'Data "a" non valida.');
    }
    if ($dataA < $dataDa) {
        $setFlash('err', 'La data fine non può essere precedente alla data inizio.');
    }
    $dataASql = "'" . esc($mysqli, $dataA) . "'";
}

$codiceSql = "'" . esc($mysqli, $codice) . "'";
$descrizioneSql = "'" . esc($mysqli, $descrizione) . "'";
$dataDaSql = "'" . esc($mysqli, $dataDa) . "'";
$prezzoSPSql = (float)$prezzoSP;
$prezzoBBSql = (float)$prezzoBB;
$prezzoHBSql = (float)$prezzoHB;
$prezzoFBSql = (float)$prezzoFB;
$valutaSql = "'EUR'";
$noteSql = 'NULL';

if ($azione === 'insert') {
    $sqlInsert = "INSERT INTO soggiorni_tariffe
        (codice, descrizione, data_da, data_a, prezzo_solo_pernottamento, prezzo_BB, prezzo_HB, prezzo_FB, valuta, note)
        VALUES ({$codiceSql}, {$descrizioneSql}, {$dataDaSql}, {$dataASql}, {$prezzoSPSql}, {$prezzoBBSql}, {$prezzoHBSql}, {$prezzoFBSql}, {$valutaSql}, {$noteSql})";

    if (!mysqli_query($mysqli, $sqlInsert)) {
        $setFlash('err', 'Impossibile inserire la tariffa: ' . $mysqli->error);
    }

    $setFlash('ok', 'Tariffa inserita correttamente.');
}

if ($tariffaId <= 0) {
    $setFlash('err', 'ID tariffa non valido.');
}

$sqlUpdate = "UPDATE soggiorni_tariffe
    SET codice={$codiceSql},
        descrizione={$descrizioneSql},
        data_da={$dataDaSql},
        data_a={$dataASql},
        prezzo_solo_pernottamento={$prezzoSPSql},
        prezzo_BB={$prezzoBBSql},
        prezzo_HB={$prezzoHBSql},
        prezzo_FB={$prezzoFBSql},
        valuta={$valutaSql},
        note={$noteSql}
    WHERE id=" . (int)$tariffaId . " LIMIT 1";

if (!mysqli_query($mysqli, $sqlUpdate)) {
    $setFlash('err', 'Impossibile aggiornare la tariffa: ' . $mysqli->error);
}

$setFlash('ok', 'Tariffa aggiornata correttamente.');