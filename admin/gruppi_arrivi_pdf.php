<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

// ===== FUNZIONI (prima di tutto) =====
function format_date(?string $value): string {
    if (!$value) return '';
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date instanceof DateTime) ? $date->format('d/m/Y') : $value;
}

function to_pdf_text(string $value): string
{
    // FPDF classico non gestisce UTF-8: vuole ISO-8859-1 / Windows-1252
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
        return $converted === false ? $value : $converted;
    }

    // fallback: prova mb_convert_encoding se disponibile
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
    }

    // fallback finale: rimuove caratteri non stampabili/extra (evita crash PDF)
    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/', '', $value) ?? $value;
}


function normalize_rows(array $rows, array $fields): array {
    $output = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $normalized = [];
        $hasValue = false;
        foreach ($fields as $field) {
            $value = trim((string)($row[$field] ?? ''));
            $normalized[$field] = $value;
            if ($value !== '') $hasValue = true;
        }
        if ($hasValue) $output[] = $normalized;
    }
    return $output;
}

// ===== INCLUDE FPDF (con check) =====
$fpdfPath = __DIR__ . '/../librerie/fpdf/fpdf.php';
if (!is_file($fpdfPath)) {
    http_response_code(500);
    exit('FPDF non trovata: ' . $fpdfPath);
}
require_once $fpdfPath;

// ===== AUTH =====
if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// Guard-rail (se per qualunque motivo non c’è, lo scopri subito)
if (!function_exists('to_pdf_text')) {
    http_response_code(500);
    exit('DEBUG: funzione to_pdf_text non definita (stai eseguendo un altro file o c’è un errore prima).');
}

$payload = $_POST;

$nomeGruppo = trim((string)($payload['nome_gruppo'] ?? ''));
$referente = trim((string)($payload['referente'] ?? ''));
$agenzia = trim((string)($payload['agenzia'] ?? ''));
$telefono = trim((string)($payload['telefono'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$dataArrivo = format_date((string)($payload['data_arrivo'] ?? ''));
$dataPartenza = format_date((string)($payload['data_partenza'] ?? ''));
$numeroAdulti = (int)($payload['numero_adulti'] ?? 0);
$numeroBambini = (int)($payload['numero_bambini'] ?? 0);
$numeroPersone = max(0, $numeroAdulti + $numeroBambini);
$tipologiaCamere = trim((string)($payload['tipologia_camere'] ?? ''));
$areaPreferita = trim((string)($payload['area_preferita'] ?? ''));
$trattamento = trim((string)($payload['trattamento'] ?? ''));
$noteRicevimento = trim((string)($payload['note_ricevimento'] ?? ''));
$noteCucina = trim((string)($payload['note_cucina'] ?? ''));
$noteHousekeeping = trim((string)($payload['note_housekeeping'] ?? ''));
$noteManutenzione = trim((string)($payload['note_manutenzione'] ?? ''));

$camereInput = $payload['camere'] ?? [];
$camere = [];
if (is_array($camereInput)) {
    foreach ($camereInput as $codice => $qty) {
        $quantita = (int)$qty;
        if ($quantita > 0) {
            $camere[(string)$codice] = $quantita;
        }
    }
}

$pastiRows = normalize_rows($payload['pasti'] ?? [], ['data', 'tipo', 'ora', 'note']);
$extraRows = normalize_rows($payload['extra'] ?? [], ['data', 'descrizione', 'ora', 'note']);

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();

$logoPath = __DIR__ . '/../img/logo.png';
if (is_file($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 24);
}

$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(10, 12);
$pdf->SetTextColor(0, 70, 160);
$pdf->Cell(0, 7, 'GESTIONE ARRIVO GRUPPI', 0, 1,'C');

$pdf->Cell(0, 5, 'ISTRUZIONI OPERATIVE INTERNE', 0, 1,'C');
$y=$pdf->GetY();
$pdf->SetY($y+5);
$pdf->SetFont('Arial', '', 15);
$pdf->Cell(60, 5, '', 0, 0,'C');
$pdf->Cell(70, 5, 'SCHEDA ARRIVO GRUPPI', 'B', 0,'C');
$pdf->Cell(60, 5, '', 0, 1,'C');

$y=$pdf->GetY();
$pdf->SetY($y+5);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Documento operativo interno - valido per tutti i reparti', 0, 1,'C');

$pdf->Ln(6);
$pdf->SetFont('Arial', '', 10);

$y=$pdf->GetY();
$pdf->SetY($y+10);
$pdf->SetTextColor(0, 70, 160);


$pdf->Cell(65, 7, 'GRUPPO', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, '', 0, 1,'C');


$pdf->Cell(65, 7, $nomeGruppo, 'B', 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, '', 0, 1,'C');


$y=$pdf->GetY();
$pdf->SetY($y+10);


$pdf->Cell(65, 7, 'AGENZIA', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, 'REFERENTE', 0, 1,'C');


$pdf->Cell(65, 7, $agenzia, 'B', 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, $referente, 'B', 1,'C');


$y=$pdf->GetY();
$pdf->SetY($y+10);


$pdf->Cell(65, 7, 'TELEFONO', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, 'EMAIL', 0, 1,'C');


$pdf->Cell(65, 7, $telefono, 'B', 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, $email, 'B', 1,'C');


$y=$pdf->GetY();
$pdf->SetY($y+10);


$pdf->Cell(65, 7, 'ARRIVO', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, 'PARTENZA', 0, 1,'C');


$pdf->Cell(65, 7, $dataArrivo, 'B', 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, $dataPartenza, 'B', 1,'C');


$y=$pdf->GetY();
$pdf->SetY($y+10);


/* =========================
 * BLOCCO: N° NOTTI + ORA ARRIVO (CORRETTO)
 * ========================= */

// prendo le date raw per i calcoli (formato atteso: Y-m-d)
$dataArrivoRaw   = trim((string)($payload['data_arrivo'] ?? ''));
$dataPartenzaRaw = trim((string)($payload['data_partenza'] ?? ''));

// versioni formattate per stampa (d/m/Y) — se vuoi puoi usare quelle già calcolate sopra
$dataArrivo   = format_date($dataArrivoRaw);
$dataPartenza = format_date($dataPartenzaRaw);

// ora di arrivo (campo dedicato nel form: name="ora_arrivo")
$oraArrivo = trim((string)($payload['ora_arrivo'] ?? ''));

// calcolo notti (diff in giorni)
$notti = '';
if ($dataArrivoRaw !== '' && $dataPartenzaRaw !== '') {
    $da = DateTime::createFromFormat('Y-m-d', $dataArrivoRaw);
    $dp = DateTime::createFromFormat('Y-m-d', $dataPartenzaRaw);

    if ($da instanceof DateTime && $dp instanceof DateTime) {
        $diff = $da->diff($dp);
        $notti = (string)max(0, (int)$diff->days); // notti = giorni tra arrivo e partenza
    }
}


// intestazioni
$pdf->Cell(65, 7, 'N° NOTTI', 0, 0, 'C');
$pdf->Cell(60, 7, '', 0, 0, 'C');
$pdf->Cell(65, 7, 'ORA DI ARRIVO', 0, 1, 'C');

// valori (con bordo solo sotto)
$pdf->Cell(65, 7, $notti, 'B', 0, 'C');
$pdf->Cell(60, 7, '', 0, 0, 'C');
$pdf->Cell(65, 7, $oraArrivo, 'B', 1, 'C');


$y=$pdf->GetY();
$pdf->SetY($y+10);


$pdf->Cell(65, 7, 'OSPITI', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0, 'C');
$pdf->Cell(65, 7, 'ALLOGGI', 0, 1, 'C');

$y=$pdf->GetY();

$pdf->Cell(35, 7, 'Adulti:', '0', 0,'C');
$pdf->Cell(30, 7, $numeroAdulti, '0',1,'C');
$pdf->SetX(10);
$pdf->Cell(35, 7, 'Bambini:', 'B', 0,'C');
$pdf->Cell(30, 7, $numeroBambini, 'B', 1,'C');




$pdf->SetY($y);


if ($camere) {
    $count=count($camere);
    $c=0;
    foreach ($camere as $codice => $quantita) {
        $bordo = ($c === $count - 1) ? 'B' : 0;
        $pdf->SetX(135);
        $pdf->Cell(35, 7, $codice . ':', $bordo, 0,'C');
        $pdf->Cell(30, 7, $quantita, $bordo, 1,'C');
        $c++;
    }
} else {
    $pdf->SetX(135);
    $pdf->Cell(65, 7, 'Nessuna camera selezionata.', 'B', 1,'C');
}


$y=$pdf->GetY();
$pdf->SetY($y+10);


$pdf->Cell(65, 7, 'TRATTAMENTO', 0, 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, 'AREA RISERVATA', 0, 1,'C');


$pdf->Cell(65, 7, $trattamento, 'B', 0,'C');
$pdf->Cell(60, 7, '', 0, 0,'C');
$pdf->Cell(65, 7, $areaPreferita, 'B', 1,'C');


$pdf->AddPage();




$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Menù', 'B', 1);
$pdf->SetFont('Arial', '', 10);
if ($pastiRows) {
    foreach ($pastiRows as $row) {
        $titleParts = array_filter([
            $row['tipo'] ?: 'Pasto',
            $row['data'] ? format_date($row['data']) : '',
            $row['ora'] ? 'ore ' . $row['ora'] : ''
        ]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, implode(' - ', $titleParts), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $note = $row['note'] !== '' ? $row['note'] : 'Menù da definire';
        $pdf->MultiCell(0, 5, $note);
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0, 5, 'Nessun menù inserito.', 0, 1);
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'Extra / Distribuzione', 0, 1);
$pdf->SetFont('Arial', '', 10);
if ($extraRows) {
    foreach ($extraRows as $row) {
        $lineParts = array_filter([
            $row['descrizione'],
            $row['data'] ? format_date($row['data']) : '',
            $row['ora'] ? 'ore ' . $row['ora'] : '',
        ]);
        $line = implode(' - ', $lineParts);
        $pdf->Cell(0, 5, $line, 0, 1);
        if ($row['note'] !== '') {
            $pdf->MultiCell(0, 5, $row['note']);
        }
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0, 5, 'Nessuna indicazione inserita.', 0, 1);
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'Note reparti', 0, 1);
$pdf->SetFont('Arial', '', 10);
/*
$addRow($pdf, 'Ricevimento:', $noteRicevimento !== '' ? $noteRicevimento : 'Nessuna nota per il ricevimento.');
$addRow($pdf, 'Cucina:', $noteCucina !== '' ? $noteCucina : 'Nessuna nota per cucina/ristorante.');
$addRow($pdf, 'Housekeeping:', $noteHousekeeping !== '' ? $noteHousekeeping : 'Nessuna nota per housekeeping.');
$addRow($pdf, 'Manutenzione:', $noteManutenzione !== '' ? $noteManutenzione : 'Nessuna nota per manutenzione.');
*/

$filenameBase = $nomeGruppo !== '' ? strtolower(preg_replace('/\s+/', '-', $nomeGruppo)) : 'scheda-gruppo';
$filename = preg_replace('/[^a-z0-9\-_]/', '', $filenameBase) ?: 'scheda-gruppo';

$pdf->Output('D', $filename . '.pdf');
