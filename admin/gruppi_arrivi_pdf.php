<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../librerie/fpdf/fpdf.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

function format_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if ($date instanceof DateTime) {
        return $date->format('d/m/Y');
    }
    return $value;
}

function to_pdf_text(string $value): string
{
    $converted = iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
    return $converted === false ? $value : $converted;
}

function normalize_rows(array $rows, array $fields): array
{
    $output = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = [];
        $hasValue = false;
        foreach ($fields as $field) {
            $value = trim((string)($row[$field] ?? ''));
            $normalized[$field] = $value;
            if ($value !== '') {
                $hasValue = true;
            }
        }
        if ($hasValue) {
            $output[] = $normalized;
        }
    }
    return $output;
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

$pdf->SetFont('Arial', 'B', 15);
$pdf->SetXY(40, 12);
$pdf->Cell(0, 7, to_pdf_text('Gestione arrivo gruppi'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetX(40);
$pdf->Cell(0, 5, to_pdf_text('Scheda operativa interna'), 0, 1);

$pdf->Ln(6);

$labelWidth = 35;
$lineHeight = 6;

$addRow = static function (FPDF $pdf, string $label, string $value) use ($labelWidth, $lineHeight): void {
    $value = $value !== '' ? $value : '-';
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($labelWidth, $lineHeight, to_pdf_text($label), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, $lineHeight, to_pdf_text($value));
};

$addRow($pdf, 'Gruppo:', $nomeGruppo);
$addRow($pdf, 'Agenzia:', $agenzia);
$addRow($pdf, 'Referente:', $referente);
$addRow($pdf, 'Telefono:', $telefono);
$addRow($pdf, 'Email:', $email);

$pdf->Ln(2);
$addRow($pdf, 'Arrivo:', $dataArrivo);
$addRow($pdf, 'Partenza:', $dataPartenza);
$addRow($pdf, 'Adulti:', (string)$numeroAdulti);
$addRow($pdf, 'Bambini:', (string)$numeroBambini);
$addRow($pdf, 'Totale:', (string)$numeroPersone);

$pdf->Ln(2);
$addRow($pdf, 'Trattamento:', $trattamento);
$addRow($pdf, 'Area:', $areaPreferita);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('Alloggi'), 0, 1);
$pdf->SetFont('Arial', '', 10);
if ($camere) {
    foreach ($camere as $codice => $quantita) {
        $pdf->Cell(0, 5, to_pdf_text($codice . ': ' . $quantita), 0, 1);
    }
} elseif ($tipologiaCamere !== '') {
    $pdf->MultiCell(0, 5, to_pdf_text($tipologiaCamere));
} else {
    $pdf->Cell(0, 5, to_pdf_text('Nessuna camera selezionata.'), 0, 1);
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('Menù / Pasti'), 0, 1);
$pdf->SetFont('Arial', '', 10);
if ($pastiRows) {
    foreach ($pastiRows as $row) {
        $titleParts = array_filter([
            $row['tipo'] ?: 'Pasto',
            $row['data'] ? format_date($row['data']) : '',
            $row['ora'] ? 'ore ' . $row['ora'] : ''
        ]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, to_pdf_text(implode(' - ', $titleParts)), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $note = $row['note'] !== '' ? $row['note'] : 'Menù da definire';
        $pdf->MultiCell(0, 5, to_pdf_text($note));
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0, 5, to_pdf_text('Nessun menù inserito.'), 0, 1);
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('Extra / Distribuzione'), 0, 1);
$pdf->SetFont('Arial', '', 10);
if ($extraRows) {
    foreach ($extraRows as $row) {
        $lineParts = array_filter([
            $row['descrizione'],
            $row['data'] ? format_date($row['data']) : '',
            $row['ora'] ? 'ore ' . $row['ora'] : '',
        ]);
        $line = implode(' - ', $lineParts);
        $pdf->Cell(0, 5, to_pdf_text($line), 0, 1);
        if ($row['note'] !== '') {
            $pdf->MultiCell(0, 5, to_pdf_text($row['note']));
        }
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0, 5, to_pdf_text('Nessuna indicazione inserita.'), 0, 1);
}

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('Note reparti'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$addRow($pdf, 'Ricevimento:', $noteRicevimento !== '' ? $noteRicevimento : 'Nessuna nota per il ricevimento.');
$addRow($pdf, 'Cucina:', $noteCucina !== '' ? $noteCucina : 'Nessuna nota per cucina/ristorante.');
$addRow($pdf, 'Housekeeping:', $noteHousekeeping !== '' ? $noteHousekeeping : 'Nessuna nota per housekeeping.');
$addRow($pdf, 'Manutenzione:', $noteManutenzione !== '' ? $noteManutenzione : 'Nessuna nota per manutenzione.');

$filenameBase = $nomeGruppo !== '' ? strtolower(preg_replace('/\s+/', '-', $nomeGruppo)) : 'scheda-gruppo';
$filename = preg_replace('/[^a-z0-9\-_]/', '', $filenameBase) ?: 'scheda-gruppo';

$pdf->Output('D', $filename . '.pdf');
