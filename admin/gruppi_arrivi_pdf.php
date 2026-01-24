<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../librerie/fpdf/fpdf.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

function table_exists(mysqli $db, string $table): bool
{
    $escaped = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$escaped}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
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

function calc_notti(?string $arrivo, ?string $partenza): int
{
    if (!$arrivo || !$partenza) {
        return 0;
    }
    $start = DateTime::createFromFormat('Y-m-d', $arrivo);
    $end = DateTime::createFromFormat('Y-m-d', $partenza);
    if (!$start || !$end) {
        return 0;
    }
    $diff = (int)$end->diff($start)->format('%r%a');
    return max(0, $diff);
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

function parse_lines(string $text): array
{
    return array_values(array_filter(array_map(static function (string $line): string {
        $clean = trim($line);
        $clean = preg_replace('/^[\-\•\*]\s*/', '', $clean);
        return trim((string)$clean);
    }, preg_split('/\r?\n/', $text) ?: [])));
}

function get_sale_ristoranti_map(mysqli $db): array
{
    if (!table_exists($db, 'sale_ristoranti')) {
        return [];
    }

    $res = $db->query("SELECT id, nome FROM sale_ristoranti ORDER BY nome ASC");
    if (!$res instanceof mysqli_result) {
        return [];
    }
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $map[(string)$id] = (string)($row['nome'] ?? '');
    }
    $res->free();
    return $map;
}

$payload = $_POST;

$nomeGruppo = trim((string)($payload['nome_gruppo'] ?? ''));
$referente = trim((string)($payload['referente'] ?? ''));
$agenzia = trim((string)($payload['agenzia'] ?? ''));
$telefono = trim((string)($payload['telefono'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$dataArrivoRaw = trim((string)($payload['data_arrivo'] ?? ''));
$dataPartenzaRaw = trim((string)($payload['data_partenza'] ?? ''));
$dataArrivo = format_date($dataArrivoRaw);
$dataPartenza = format_date($dataPartenzaRaw);
$checkinOrario = trim((string)($payload['checkin_orario'] ?? ''));
$numeroAdulti = (int)($payload['numero_adulti'] ?? 0);
$numeroBambini = (int)($payload['numero_bambini'] ?? 0);
$numeroPersone = max(0, $numeroAdulti + $numeroBambini);
$tipologiaCamere = trim((string)($payload['tipologia_camere'] ?? ''));
$areaPreferita = trim((string)($payload['area_preferita'] ?? ''));
$trattamento = trim((string)($payload['trattamento'] ?? ''));
$noteRicevimento = trim((string)($payload['note_ricevimento'] ?? ''));
$noteCucina = trim((string)($payload['note_cucina'] ?? ''));
$noteAllergie = trim((string)($payload['note_allergie'] ?? ''));
$noteHousekeeping = trim((string)($payload['note_housekeeping'] ?? ''));
$noteManutenzione = trim((string)($payload['note_manutenzione'] ?? ''));

$areeRiservate = $payload['aree_riservate'] ?? [];
if (!is_array($areeRiservate)) {
    $areeRiservate = [];
}
$areeRiservate = array_values(array_filter(array_map('intval', $areeRiservate)));

if ($areeRiservate && table_exists($mysqli, 'sale_congressi')) {
    $placeholders = implode(',', array_fill(0, count($areeRiservate), '?'));
    $stmt = $mysqli->prepare("SELECT id, nome FROM sale_congressi WHERE id IN ({$placeholders}) ORDER BY nome ASC");
    if ($stmt) {
        $types = str_repeat('i', count($areeRiservate));
        $stmt->bind_param($types, ...$areeRiservate);
        $stmt->execute();
        $res = $stmt->get_result();
        $nomi = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $nome = trim((string)($row['nome'] ?? ''));
                if ($nome !== '') {
                    $nomi[] = $nome;
                }
            }
        }
        $stmt->close();
        if ($nomi) {
            $areaPreferita = implode(', ', $nomi);
        }
    }
}

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

$pastiRows = normalize_rows($payload['pasti'] ?? [], ['data', 'tipo', 'ora', 'sala_ristorante', 'note']);
$extraRows = normalize_rows($payload['extra'] ?? [], ['data', 'descrizione', 'ora', 'note']);
$saleRistorantiMap = get_sale_ristoranti_map($mysqli);

$blue = [28, 74, 143];
$dark = [17, 24, 39];
$red = [200, 0, 0];
$leftMargin = 18;
$rightMargin = 18;
$usableWidth = 210 - $leftMargin - $rightMargin;

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins($leftMargin, 18, $rightMargin);
$pdf->SetAutoPageBreak(true, 18);

$setBlue = static function (FPDF $pdf) use ($blue): void {
    $pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
};

$setDark = static function (FPDF $pdf) use ($dark): void {
    $pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
};

$drawField = static function (FPDF $pdf, float $x, float $y, string $label, string $value, float $width) use ($setBlue, $setDark): float {
    $pdf->SetXY($x, $y);
    $setBlue($pdf);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($width, 4, to_pdf_text($label), 0, 2);
    $setDark($pdf);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($width, 5, to_pdf_text($value !== '' ? $value : '-'), 0, 2);
    $lineY = $pdf->GetY() + 1;
    $pdf->Line($x, $lineY, $x + $width, $lineY);
    return $lineY + 6;
};

$drawListRow = static function (FPDF $pdf, float $x, float $width, string $label, string $value) use ($setDark): void {
    $setDark($pdf);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX($x);
    $pdf->Cell($width - 15, 5, to_pdf_text($label), 0, 0);
    $pdf->Cell(15, 5, to_pdf_text($value), 0, 1, 'R');
    $lineY = $pdf->GetY();
    $pdf->Line($x, $lineY, $x + $width, $lineY);
};

$drawSectionTitle = static function (FPDF $pdf, string $title, string $align = 'L') use ($setBlue): void {
    $setBlue($pdf);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, to_pdf_text($title), 0, 1, $align);
};

$drawParagraph = static function (FPDF $pdf, string $text): void {
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, to_pdf_text($text));
};

$pdf->AddPage();

$setBlue($pdf);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, to_pdf_text('GESTIONE ARRIVO GRUPPI'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('ISTRUZIONI OPERATIVE INTERNE'), 0, 1, 'C');
$pdf->SetFont('Arial', 'BU', 11);
$pdf->Cell(0, 6, to_pdf_text('SCHEDA ARRIVO GRUPPI'), 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(75, 85, 99);
$pdf->Cell(0, 5, to_pdf_text('Documento operativo interno - valido per tutti i reparti'), 0, 1, 'C');
$pdf->Ln(6);

$colWidth = 78;
$colGap = 16;
$leftX = $leftMargin;
$rightX = $leftX + $colWidth + $colGap;

$y = $pdf->GetY();
$yNext = $drawField($pdf, $leftX, $y, 'GRUPPO:', $nomeGruppo, $colWidth);
$yNext = max($yNext, $drawField($pdf, $rightX, $y, 'CODICE GRUPPO:', $agenzia, $colWidth));

$y = $yNext;
$yNext = $drawField($pdf, $leftX, $y, 'REFERENTE:', $referente, $colWidth);
$yNext = max($yNext, $drawField($pdf, $rightX, $y, 'CHECK OUT:', $dataPartenza, $colWidth));

$y = $yNext;
$yNext = $drawField($pdf, $leftX, $y, 'CHECK IN:', $dataArrivo, $colWidth);
$yNext = max($yNext, $drawField($pdf, $rightX, $y, 'ORARIO DI ARRIVO', $checkinOrario, $colWidth));

$y = $yNext;
$yNext = $drawField($pdf, $leftX, $y, 'N° NOTTI', (string)calc_notti($dataArrivoRaw, $dataPartenzaRaw), $colWidth);

$pdf->SetY($yNext + 2);

$startY = $pdf->GetY();
$pdf->SetX($leftX);
$drawSectionTitle($pdf, 'OSPITI');
$drawListRow($pdf, $leftX, $colWidth, 'Adulti', (string)$numeroAdulti);
$drawListRow($pdf, $leftX, $colWidth, 'Bambini', (string)$numeroBambini);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX($leftX);
$pdf->Cell($colWidth - 15, 6, to_pdf_text('Totale'), 0, 0, 'R');
$pdf->Cell(15, 6, to_pdf_text((string)$numeroPersone), 0, 1, 'R');

$pdf->SetY($startY);
$pdf->SetX($rightX);
$drawSectionTitle($pdf, 'ALLOGGI');
if ($camere) {
    $totCamere = 0;
    foreach ($camere as $codice => $quantita) {
        $drawListRow($pdf, $rightX, $colWidth, $codice, (string)$quantita);
        $totCamere += $quantita;
    }
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX($rightX);
    $pdf->Cell($colWidth - 15, 6, to_pdf_text('Totale'), 0, 0, 'R');
    $pdf->Cell(15, 6, to_pdf_text((string)$totCamere), 0, 1, 'R');
} elseif ($tipologiaCamere !== '') {
    $drawParagraph($pdf, $tipologiaCamere);
} else {
    $drawParagraph($pdf, 'Nessuna camera selezionata.');
}

$pdf->AddPage();

$drawSectionTitle($pdf, 'TRATTAMENTO');
$drawParagraph($pdf, $trattamento !== '' ? $trattamento : '---');
$drawParagraph($pdf, $areaPreferita !== '' ? $areaPreferita : '---');
$pdf->Ln(6);

$pdf->SetY(20);
$pdf->SetX(110);
$drawSectionTitle($pdf, 'SALA RISTORANTE');
$pdf->SetX(110);
$saleRistoranteLines = [];
foreach ($pastiRows as $row) {
    $salaId = trim((string)($row['sala_ristorante'] ?? ''));
    if ($salaId === '') {
        continue;
    }
    $salaLabel = $saleRistorantiMap[$salaId] ?? $salaId;
    $parts = array_filter([
        $row['tipo'] ?: 'Pasto',
        $row['data'] ? format_date($row['data']) : '',
        $row['ora'] ?: ''
    ]);
    $saleRistoranteLines[] = trim(implode(' ', $parts) . ' - ' . $salaLabel);
}
if (!$saleRistoranteLines) {
    $drawParagraph($pdf, 'Nessuna sala ristorante indicata.');
} else {
    foreach ($saleRistoranteLines as $line) {
        $drawParagraph($pdf, $line);
    }
}

$pdf->Ln(6);
$pdf->SetX($leftMargin);
$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
$pdf->Ln(6);

$drawSectionTitle($pdf, 'MENÙ');

if ($pastiRows) {
    foreach ($pastiRows as $row) {
        $titleParts = array_filter([
            $row['tipo'] ?: 'Pasto',
            $row['data'] ? format_date($row['data']) : '',
            $row['ora'] ? $row['ora'] : ''
        ]);
        $pdf->SetFont('Arial', 'B', 10);
        $setBlue($pdf);
        $pdf->Cell(0, 6, to_pdf_text(strtoupper(implode(' ', $titleParts))), 0, 1);
        $setDark($pdf);
        $lines = parse_lines((string)$row['note']);
        if (!$lines) {
            $lines = ['Menù da definire'];
        }
        foreach ($lines as $line) {
            $pdf->SetX($leftMargin + 6);
            $pdf->Cell(4, 5, '-', 0, 0);
            $pdf->Cell(0, 5, to_pdf_text($line), 0, 1);
        }
        $pdf->Ln(3);
    }
} else {
    $drawParagraph($pdf, 'Nessun menù inserito.');
}

$pdf->AddPage();

$drawSectionTitle($pdf, 'ALLERGIE / INTOLLERANZE');
$allergieLines = $noteAllergie !== '' ? parse_lines($noteAllergie) : [];
$lineCount = max(8, count($allergieLines) + 2);
for ($i = 0; $i < $lineCount; $i++) {
    $text = $allergieLines[$i] ?? '';
    if ($text !== '') {
        $pdf->SetTextColor($red[0], $red[1], $red[2]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, to_pdf_text($text), 0, 1);
    } else {
        $pdf->Ln(6);
    }
    $pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
    $pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
}

$pdf->Ln(10);
$setBlue($pdf);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, to_pdf_text('DISTRIBUZIONE TAVOLI'), 0, 1);

$distribuzioneLines = [];
foreach ($extraRows as $row) {
    $parts = array_filter([
        $row['descrizione'],
        $row['data'] ? format_date($row['data']) : '',
        $row['ora'],
        $row['note']
    ]);
    if ($parts) {
        $distribuzioneLines[] = implode(' - ', $parts);
    }
}

if (!$distribuzioneLines) {
    $pdf->SetTextColor($red[0], $red[1], $red[2]);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, to_pdf_text('(in attesa)'), 0, 1);
}

$lineCount = max(6, count($distribuzioneLines) + 2);
for ($i = 0; $i < $lineCount; $i++) {
    $text = $distribuzioneLines[$i] ?? '';
    if ($text !== '') {
        $pdf->SetTextColor($red[0], $red[1], $red[2]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, to_pdf_text($text), 0, 1);
    } else {
        $pdf->Ln(6);
    }
    $pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
    $pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
}

$pdf->AddPage();

$drawSectionTitle($pdf, 'NOTE PER REPARTI', 'C');
$pdf->Ln(4);

$drawSectionTitle($pdf, 'RICEVIMENTO');
$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
$pdf->Ln(4);
$drawParagraph($pdf, $noteRicevimento !== '' ? $noteRicevimento : 'Nessuna nota per il ricevimento.');
$pdf->Ln(6);

$drawSectionTitle($pdf, 'CUCINA / RISTORANTE');
$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
$pdf->Ln(4);
$drawParagraph($pdf, $noteCucina !== '' ? $noteCucina : 'Nessuna nota per cucina/ristorante.');
$pdf->Ln(6);

$drawSectionTitle($pdf, 'HOUSEKEEPING');
$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
$pdf->Ln(4);
$drawParagraph($pdf, $noteHousekeeping !== '' ? $noteHousekeeping : 'Nessuna nota per housekeeping.');

$pdf->AddPage();

$drawSectionTitle($pdf, 'MANUTENZIONE');
$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->Line($leftMargin, $pdf->GetY(), 210 - $rightMargin, $pdf->GetY());
$pdf->Ln(4);
$drawParagraph($pdf, $noteManutenzione !== '' ? $noteManutenzione : 'Nessuna segnalazione particolare.');

$pdf->Ln(30);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor($dark[0], $dark[1], $dark[2]);
$pdf->SetX(120);
$pdf->Cell(0, 6, to_pdf_text('La Direzione'), 0, 1, 'L');
$pdf->SetX(120);
$pdf->Line(120, $pdf->GetY(), 190, $pdf->GetY());

$filenameBase = $nomeGruppo !== '' ? strtolower(preg_replace('/\s+/', '-', $nomeGruppo)) : 'scheda-gruppo';
$filename = preg_replace('/[^a-z0-9\-_]/', '', $filenameBase) ?: 'scheda-gruppo';

$pdf->Output('D', $filename . '.pdf');
