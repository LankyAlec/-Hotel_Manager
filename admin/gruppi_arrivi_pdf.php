<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * /admin/gruppi_arrivi_pdf.php
 * Genera PDF “Scheda Arrivo Gruppi” con Dompdf (HTML/CSS).
 *
 * NOTE IMPORTANTI:
 * - NIENTE output prima del PDF (anche un warning rompe il file)
 * - usa DejaVu Sans per UTF-8
 * - immagini locali: usa file:// + realpath (e abilita chroot)
 */

ob_start();

require_once __DIR__ . '/../config/db.php';

/* =========================
   DOMPDF (autoload)
   ========================= */
$autoloadPath = null;
$dir = __DIR__;

for ($i = 0; $i < 6; $i++) {
    $candidate = $dir . '/vendor/autoload.php';
    if (is_file($candidate)) {
        $autoloadPath = $candidate;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}

if ($autoloadPath === null && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
    $candidate = $docRoot . '/vendor/autoload.php';
    if (is_file($candidate)) {
        $autoloadPath = $candidate;
    }
}

if ($autoloadPath !== null) {
    require_once $autoloadPath;
} else {
    ob_end_clean();
    http_response_code(500);
    echo 'Errore PDF: autoload Dompdf non trovato. Verifica il percorso vendor/autoload.php.';
    exit;
}

/* =========================
   AUTH
   ========================= */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

/* =========================
   CONFIG HOTEL (EDITA QUI)
   ========================= */
const HOTEL_NOME       = 'PARK HOTEL PARADISO';
const HOTEL_INDIRIZZO  = 'Via Contrada Ramaldo, 94015 Piazza Armerina (EN)';
const HOTEL_TEL        = '+39 334 819 6774 | +39 0935 684908';
const HOTEL_EMAIL      = 'info@parkhotelparadiso.it';
const HOTEL_WEB        = 'http://www.parkhotelparadiso.it';
const HOTEL_LOGO_PATH  = __DIR__ . '/../img/logo.jpg'; // JPG consigliato

/* =========================
   HELPERS
   ========================= */

function format_date(?string $value): string {
    $v = trim((string)$value);
    if ($v === '') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    return ($dt instanceof DateTime) ? $dt->format('d/m/Y') : $v;
}

function format_time(?string $value): string {
    $v = trim((string)$value);
    if ($v === '') return '';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) return substr($v, 0, 5);
    if (preg_match('/^\d{2}:\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{2}):(\d{2})/', $v, $m)) return $m[1] . ':' . $m[2];
    return $v;
}

function calc_notti(?string $arrivo, ?string $partenza): int {
    $a = trim((string)$arrivo);
    $p = trim((string)$partenza);
    if ($a === '' || $p === '') return 0;

    $start = DateTime::createFromFormat('Y-m-d', $a);
    $end   = DateTime::createFromFormat('Y-m-d', $p);
    if (!($start instanceof DateTime) || !($end instanceof DateTime)) return 0;

    // notti = giorni tra checkin e checkout
    return max(0, (int)$start->diff($end)->format('%a'));
}

function normalize_rows($rows, array $fields): array {
    if (is_string($rows)) {
        $decoded = json_decode($rows, true);
        if (is_array($decoded)) $rows = $decoded;
    }
    if (!is_array($rows)) return [];

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $norm = [];
        $has = false;
        foreach ($fields as $f) {
            $v = trim((string)($row[$f] ?? ''));
            $norm[$f] = $v;
            if ($v !== '') $has = true;
        }
        if ($has) $out[] = $norm;
    }
    return $out;
}

function parse_lines(string $text): array {
    $parts = preg_split('/\r?\n/', (string)$text) ?: [];
    $clean = [];
    foreach ($parts as $line) {
        $s = trim((string)$line);
        $s = preg_replace('/^[\-\•\*]\s*/u', '', $s);
        $s = trim((string)$s);
        if ($s !== '') $clean[] = $s;
    }
    return array_values($clean);
}

function table_exists(mysqli $db, string $table): bool {
    $escaped = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$escaped}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

function get_sale_ristoranti_map(mysqli $db): array {
    if (!table_exists($db, 'sale_ristoranti')) return [];
    $map = [];
    $res = $db->query("SELECT id, nome FROM sale_ristoranti ORDER BY nome ASC");
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) {
            $id = (string)($r['id'] ?? '');
            $nm = trim((string)($r['nome'] ?? ''));
            if ($id !== '' && $nm !== '') $map[$id] = $nm;
        }
        $res->free();
    }
    return $map;
}

function file_uri(?string $path): string {
    $p = trim((string)$path);
    if ($p === '') return '';
    $rp = realpath($p);
    if ($rp === false || !is_file($rp)) return '';
    return 'file://' . $rp;
}

/* =========================
   PAYLOAD (POST)
   ========================= */
$payload = $_POST;

$nomeGruppo = trim((string)($payload['nome_gruppo'] ?? ''));
$referente  = trim((string)($payload['referente'] ?? ''));
$agenzia    = trim((string)($payload['agenzia'] ?? ''));
$telefono   = trim((string)($payload['telefono'] ?? ''));
$email      = trim((string)($payload['email'] ?? ''));

$dataArrivoRaw   = trim((string)($payload['data_arrivo'] ?? ''));
$dataPartenzaRaw = trim((string)($payload['data_partenza'] ?? ''));
$dataArrivo      = format_date($dataArrivoRaw);
$dataPartenza    = format_date($dataPartenzaRaw);

$checkinOrario = format_time($payload['checkin_orario'] ?? '');
$numeroAdulti  = (int)($payload['numero_adulti'] ?? 0);
$numeroBambini = (int)($payload['numero_bambini'] ?? 0);
$numeroPersone = max(0, $numeroAdulti + $numeroBambini);

$trattamento      = trim((string)($payload['trattamento'] ?? ''));
$noteRicevimento  = trim((string)($payload['note_ricevimento'] ?? ''));
$noteCucina       = trim((string)($payload['note_cucina'] ?? ''));
$noteAllergie     = trim((string)($payload['note_allergie'] ?? ''));
$noteHousekeeping = trim((string)($payload['note_housekeeping'] ?? ''));
$noteManutenzione = trim((string)($payload['note_manutenzione'] ?? ''));

// Camere
$camereInput = $payload['camere'] ?? [];
$camere = [];
if (is_array($camereInput)) {
    foreach ($camereInput as $codice => $qty) {
        $q = (int)$qty;
        if ($q > 0) $camere[(string)$codice] = $q;
    }
}

// Pasti / extra
$pastiRows = normalize_rows($payload['pasti'] ?? [], ['data','tipo','ora','sala_ristorante','note']);
$extraRows = normalize_rows($payload['extra'] ?? [], ['data','descrizione','ora','note']);

$saleMap = get_sale_ristoranti_map($mysqli);

$printedAt = (new DateTime())->format('d/m/Y H:i');
$logoUri   = file_uri(HOTEL_LOGO_PATH);

// filename
$filenameBase = $nomeGruppo !== '' ? strtolower(preg_replace('/\s+/', '-', $nomeGruppo)) : 'scheda-gruppo';
$filenameSafe = preg_replace('/[^a-z0-9\-_]/', '', $filenameBase) ?: 'scheda-gruppo';
$filename     = $filenameSafe . '.pdf';

/* =========================
   HTML BUILDER
   ========================= */
function kv_card(string $label, string $value): string {
    $label = trim($label);
    $value = trim($value);
    if ($label === '' && $value === '') return '';
    return '
      <div class="kv">
        <div class="kv-label">'.h(mb_strtoupper($label)).'</div>
        <div class="kv-value">'.h($value).'</div>
      </div>
    ';
}

$camereHtml = '';
if ($camere) {
    $tot = 0;
    $rows = '';
    foreach ($camere as $cod => $q) {
        $tot += $q;
        $rows .= '<tr><td>'.h($cod).'</td><td class="t-r">'.h((string)$q).'</td></tr>';
    }
    $rows .= '<tr class="tr-strong"><td>Totale camere</td><td class="t-r">'.h((string)$tot).'</td></tr>';

    $camereHtml = '
      <table class="simple-table">
        <thead><tr><th>Tipologia</th><th class="t-r">Qtà</th></tr></thead>
        <tbody>'.$rows.'</tbody>
      </table>
    ';
} else {
    $camereHtml = '<div class="muted">Nessuna camera selezionata.</div>';
}

$pastiHtml = '';
if ($pastiRows) {
    foreach ($pastiRows as $row) {
        $tipo = trim((string)($row['tipo'] ?? ''));
        $dd   = !empty($row['data']) ? format_date($row['data']) : '';
        $ora  = format_time($row['ora'] ?? '');

        $sId  = trim((string)($row['sala_ristorante'] ?? ''));
        $sNm  = ($sId !== '' ? ($saleMap[$sId] ?? $sId) : '');
        $sala = ($sNm !== '' ? 'Sala ' . $sNm : '');

        $lines = parse_lines((string)($row['note'] ?? ''));
        if (!$lines) $lines = ['Menu da definire'];

        $lis = '';
        foreach ($lines as $l) {
            $lis .= '<li>'.h($l).'</li>';
        }

        $pastiHtml .= '
          <div class="block">
            <table class="mealbar" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="mealbar-date">
                  <div class="d1">'.h($dd).'</div>
                  <div class="d2">'.h($ora).'</div>
                </td>
                <td class="mealbar-type">'.h(mb_strtoupper($tipo)).'</td>
                <td class="mealbar-sala">'.h($sala).'</td>
              </tr>
            </table>
            <ul class="bullets">'.$lis.'</ul>
          </div>
        ';
    }
} else {
    $pastiHtml = '<div class="muted">Nessun pasto programmato.</div>';
}

$extraHtml = '';
if ($extraRows) {
    foreach ($extraRows as $row) {
        $dd   = !empty($row['data']) ? format_date($row['data']) : '';
        $ora  = format_time($row['ora'] ?? '');
        $desc = trim((string)($row['descrizione'] ?? ''));

        $note = trim((string)($row['note'] ?? ''));

        $extraHtml .= '
          <div class="block">
            <table class="mealbar" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="mealbar-date">
                  <div class="d1">'.h($dd).'</div>
                  <div class="d2">'.h($ora).'</div>
                </td>
                <td class="mealbar-type">'.h(mb_strtoupper($desc)).'</td>
                <td class="mealbar-sala"></td>
              </tr>
            </table>
            '.($note !== '' ? '<div class="note">'.nl2br(h($note)).'</div>' : '').'
          </div>
        ';
    }
} else {
    $extraHtml = '<div class="muted">Nessuna attività inserita.</div>';
}

$notesHtml = '';
$notes = [
    'Ricevimento'         => $noteRicevimento,
    'Cucina / ristorante' => $noteCucina,
    'Housekeeping'        => $noteHousekeeping,
    'Manutenzione'        => $noteManutenzione,
];
foreach ($notes as $title => $val) {
    $val = trim((string)$val);
    if ($val === '') continue;
    $notesHtml .= '
      <div class="note-block">
        <div class="subhead">'.h($title).'</div>
        <div class="note">'.nl2br(h($val)).'</div>
      </div>
    ';
}
if ($notesHtml === '') {
    $notesHtml = '<div class="muted">Nessuna nota inserita.</div>';
}

$allergieHtml = '';
if (trim($noteAllergie) !== '') {
    $allergieHtml = '<div class="note">'.nl2br(h($noteAllergie)).'</div>';
} else {
    $allergieHtml = '<div class="muted">Nessuna allergia segnalata.</div>';
}

$gruppoLabel = ($nomeGruppo !== '' ? 'Gruppo: ' . $nomeGruppo : '');

$rootPath = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'); // per chroot

$html = '
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 110px 34px 80px 34px; } /* top right bottom left */

    body {
      font-family: "DejaVu Sans", sans-serif;
      font-size: 12px;
      color: #111827;
    }

    /* Header / Footer (fixed) */
    .header {
      position: fixed;
      top: -90px;
      left: 0;
      right: 0;
      height: 80px;
    }
    .header .bar {
      background: #1C4A8F;
      color: #fff;
      padding: 14px 16px;
    }
    .header .title {
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.3px;
      margin: 0;
      line-height: 1.1;
    }
    .header .meta {
      font-size: 11px;
      opacity: 0.9;
      margin-top: 4px;
      line-height: 1.2;
    }
    .header .logo {
      text-align: right;
      vertical-align: middle;
      width: 140px;
    }
    .header .line {
      border-bottom: 1px solid #E5E7EB;
    }

    .footer {
      position: fixed;
      bottom: -55px;
      left: 0;
      right: 0;
      height: 45px;
      border-top: 1px solid #E5E7EB;
      color: #6B7280;
      font-size: 10px;
      padding-top: 8px;
    }
    .pageNumber:before { content: counter(page); }
    .totalPages:before { content: counter(pages); }

    /* Titles */
    .section {
      margin: 0 0 14px 0;
      page-break-inside: avoid;
    }
    .section-title {
      margin: 0 0 10px 0;
      padding: 10px 12px;
      border: 1px solid #E5E7EB;
      border-left: 8px solid #1C4A8F;
      background: #F8FAFC;
      color: #1C4A8F;
      font-size: 15px;
      font-weight: 800;
      letter-spacing: .2px;
    }
    /* sottotitolo: volutamente più “leggero” del titolo */
    .section-sub {
      margin: -2px 0 12px 0;
      padding-left: 14px;
      color: #6B7280;
      font-size: 12px;
      font-weight: 600;
    }

    /* KV grid */
    .grid {
      border: 1px solid #E5E7EB;
      background: #fff;
      padding: 10px;
    }
    .kv-row { width: 100%; border-collapse: collapse; }
    .kv-cell { width: 50%; vertical-align: top; padding: 6px; }
    .kv {
      border: 1px solid #EEF2F7;
      background: #FFFFFF;
      padding: 10px;
      height: 46px;
    }
    .kv-label {
      font-size: 9px;
      color: #6B7280;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .kv-value {
      margin-top: 4px;
      font-size: 12px;
      color: #111827;
      font-weight: 700;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Tables */
    .simple-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #E5E7EB;
      background: #fff;
    }
    .simple-table th {
      text-align: left;
      padding: 10px;
      font-size: 10px;
      color: #6B7280;
      background: #F8FAFC;
      border-bottom: 1px solid #E5E7EB;
    }
    .simple-table td {
      padding: 10px;
      border-bottom: 1px solid #EEF2F7;
    }
    .simple-table .tr-strong td { font-weight: 800; }
    .t-r { text-align: right; }

    /* Meal bar (NO bordi interni, un unico rettangolo) */
    .block { margin: 0 0 10px 0; }
    .mealbar {
      border: 1px solid #E5E7EB;
      background: #F8FAFC;
    }
    .mealbar td { padding: 12px 14px; vertical-align: middle; }
    .mealbar-date { width: 140px; }
    .mealbar-date .d1 { font-size: 14px; font-weight: 800; color:#111827; }
    .mealbar-date .d2 { font-size: 11px; font-weight: 700; color:#6B7280; margin-top: 3px; }
    .mealbar-type {
      text-align: center;
      font-size: 20px;
      font-weight: 900;
      color: #1C4A8F;
      letter-spacing: 0.6px;
    }
    .mealbar-sala {
      width: 220px;
      text-align: right;
      font-size: 13px;
      font-weight: 800;
      color:#111827;
    }

    /* Bullets */
    .bullets {
      margin: 8px 0 0 22px;
      padding: 0;
    }
    .bullets li {
      margin: 0 0 4px 0;
      line-height: 1.25;
    }

    .note-block { margin: 0 0 12px 0; }
    .subhead {
      font-size: 11px;
      font-weight: 800;
      color: #1C4A8F;
      margin: 0 0 6px 0;
      padding-left: 2px;
    }
    .note {
      border: 1px solid #E5E7EB;
      background: #FFFFFF;
      padding: 10px 12px;
      color: #111827;
      line-height: 1.35;
    }

    .muted { color:#6B7280; }

    /* Page breaks */
    .break { page-break-before: always; }
  </style>
</head>
<body>

  <!-- HEADER -->
  <div class="header">
    <table width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td class="bar">
          <div class="title">SCHEDA ARRIVO GRUPPI</div>
          <div class="meta">'.h(HOTEL_NOME).($gruppoLabel !== '' ? ' &nbsp;|&nbsp; '.h($gruppoLabel) : '').'</div>
        </td>
        <td class="bar logo">
          '.($logoUri !== '' ? '<img src="'.h($logoUri).'" style="height:34px;">' : '').'
        </td>
      </tr>
      <tr><td colspan="2" class="line"></td></tr>
    </table>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <table width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td>
          '.h(HOTEL_NOME).' - '.h(HOTEL_INDIRIZZO).' - Tel. '.h(HOTEL_TEL).'<br>
          Email: '.h(HOTEL_EMAIL).' &nbsp;|&nbsp; Web: '.h(HOTEL_WEB).'
        </td>
        <td style="text-align:right;">
          Pag. <span class="pageNumber"></span>/<span class="totalPages"></span><br>
          Creato: '.h($printedAt).'
        </td>
      </tr>
    </table>
  </div>

  <!-- PAGINA 1 -->
  <div class="section">
    <div class="section-title">Dati gruppo</div>
    <div class="grid">
      <table class="kv-row">
        <tr>
          <td class="kv-cell">'.kv_card('Gruppo', $nomeGruppo).'</td>
          <td class="kv-cell">'.kv_card('Agenzia / Ente', $agenzia).'</td>
        </tr>
        <tr>
          <td class="kv-cell">'.kv_card('Referente', $referente).'</td>
          <td class="kv-cell">'.kv_card('Telefono', $telefono).'</td>
        </tr>
        <tr>
          <td class="kv-cell">'.kv_card('Email', $email).'</td>
          <td class="kv-cell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Soggiorno</div>
    <div class="grid">
      <table class="kv-row">
        <tr>
          <td class="kv-cell">'.kv_card('Check-in', $dataArrivo).'</td>
          <td class="kv-cell">'.kv_card('Check-out', $dataPartenza).'</td>
        </tr>
        <tr>
          <td class="kv-cell">'.kv_card('Orario check-in', $checkinOrario).'</td>
          <td class="kv-cell">'.kv_card('Notti', (string)calc_notti($dataArrivoRaw, $dataPartenzaRaw)).'</td>
        </tr>
        <tr>
          <td class="kv-cell">'.kv_card('Trattamento', $trattamento).'</td>
          <td class="kv-cell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Partecipanti</div>
    <div class="grid">
      <table class="kv-row">
        <tr>
          <td class="kv-cell">'.kv_card('Adulti', (string)$numeroAdulti).'</td>
          <td class="kv-cell">'.kv_card('Bambini', (string)$numeroBambini).'</td>
        </tr>
        <tr>
          <td class="kv-cell">'.kv_card('Totale', (string)$numeroPersone).'</td>
          <td class="kv-cell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Alloggi</div>
    '.$camereHtml.'
  </div>

  <!-- PAGINA 2 -->
  <div class="break"></div>
  <div class="section">
    <div class="section-title">Pasti e sala ristorante</div>
    <div class="section-sub">Elenco pasti</div>
    '.$pastiHtml.'
  </div>

  <!-- PAGINA 3 -->
  <div class="break"></div>
  <div class="section">
    <div class="section-title">Attività / extra</div>
    <div class="section-sub">Elenco attività</div>
    '.$extraHtml.'
  </div>

  <!-- PAGINA 4 -->
  <div class="break"></div>
  <div class="section">
    <div class="section-title">Note per reparti</div>
    '.$notesHtml.'
  </div>

  <!-- PAGINA 5 -->
  <div class="break"></div>
  <div class="section">
    <div class="section-title">Allergie / intolleranze</div>
    '.$allergieHtml.'
  </div>

</body>
</html>
';

/* =========================
   DOMPDF RENDER
   ========================= */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

// IMPORTANTISSIMO per immagini locali
// limita l'accesso ai file al root del progetto
$options->setChroot($rootPath);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');

try {
    $dompdf->render();
} catch (Throwable $e) {
    // se succede, non mandare output sporco: meglio errore pulito
    ob_end_clean();
    http_response_code(500);
    echo 'Errore PDF: ' . h($e->getMessage());
    exit;
}

/* =========================
   OUTPUT PDF (NO OUTPUT SPORCO)
   ========================= */
ob_end_clean();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $dompdf->output();
exit;
