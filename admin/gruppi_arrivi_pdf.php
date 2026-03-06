<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * /admin/gruppi_arrivi_pdf.php
 * PDF “Scheda Arrivo Gruppi” (stile moderno) con Dompdf.
 *
 * NOTE:
 * - NIENTE output prima del PDF (warning/notices rompono il file)
 * - Richiede estensione PHP: zlib (OBBLIGATORIA), gd (consigliata per immagini)
 * - Usa chroot + path relativo per immagini locali
 * - Non include h(): deve essere già disponibile dai tuoi include
 */

ob_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
// Se hai helpers con h(), includilo qui (consigliato):
// require_once __DIR__ . '/../includes/helpers.php';

/* =========================
   AUTH
   ========================= */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

/* =========================
   HOTEL CONFIG (EDITA QUI)
   ========================= */
const HOTEL_NOME       = 'PARK HOTEL PARADISO';
const HOTEL_INDIRIZZO  = 'Via Contrada Ramaldo, 94015 Piazza Armerina (EN)';
const HOTEL_TEL        = '+39 334 819 6774 | +39 0935 684908';
const HOTEL_EMAIL      = 'info@parkhotelparadiso.it';
const HOTEL_WEB        = 'http://www.parkhotelparadiso.it';
const HOTEL_LOGO_REL   = 'img/logo.jpg'; // relativo alla root del progetto (chroot)

/* =========================
   DOMPDF (autoload)
   ========================= */
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $autoloadPaths[] = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/vendor/autoload.php';
}

$autoloaded = false;
foreach (array_unique($autoloadPaths) as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo 'Errore PDF: autoload Dompdf non trovato. Verifica vendor/autoload.php.';
    exit;
}

// zlib obbligatoria (gzcompress) → evita crash Cpdf
if (!function_exists('gzcompress')) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo 'Errore PDF: estensione PHP zlib non attiva (manca gzcompress). Abilita zlib nel profilo PHP di Web Station.';
    exit;
}

/* =========================
   HELPERS LOCALI (no h)
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
$noteDisposizioneTavoli = trim((string)($payload['note_disposizione_tavoli'] ?? ''));
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
$notti     = calc_notti($dataArrivoRaw, $dataPartenzaRaw);

// filename
$filenameBase = $nomeGruppo !== '' ? strtolower(preg_replace('/\s+/', '-', $nomeGruppo)) : 'scheda-gruppo';
$filenameSafe = preg_replace('/[^a-z0-9\-_]/', '', $filenameBase) ?: 'scheda-gruppo';
$filename     = $filenameSafe . '.pdf';

/* =========================
   ASSETS (chroot + logo)
   ========================= */
$rootPath = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

$logoAbs = realpath(
    $rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, HOTEL_LOGO_REL)
);

$logoUri = '';
if ($logoAbs && is_file($logoAbs)) {
    $logoUri = 'file://' . $logoAbs;   // <-- super affidabile con Dompdf
}

/* =========================
   HTML COMPONENTS
   ========================= */
function kv_card(string $label, string $value): string {
    $label = trim($label);
    $value = trim($value);
    if ($label === '' && $value === '') return '';
    if ($value === '') $value = '—';
    return '
      <div class="kv">
        <div class="kv-label">'.h(mb_strtoupper($label)).'</div>
        <div class="kv-value">'.h($value).'</div>
      </div>
    ';
}

function badge(string $text): string {
    $t = trim($text);
    if ($t === '') return '';
    return '<span class="badge">'.h($t).'</span>';
}

function dept_block(string $title, string $value): string {
    $v = trim($value);
    if ($v === '') $v = '—';
    return '
      <div class="dept">
        <div class="dept-head">'.h($title).'</div>
        <div class="dept-body">'.nl2br(h($v)).'</div>
      </div>
    ';
}

/* =========================
   BUILD SECTIONS
   ========================= */

// Alloggi
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

// Pasti
$pastiHtml = '';
if ($pastiRows) {
    foreach ($pastiRows as $row) {
        $tipo = trim((string)($row['tipo'] ?? ''));
        $dd   = !empty($row['data']) ? format_date($row['data']) : '';
        $ora  = format_time($row['ora'] ?? '');

        $sId  = trim((string)($row['sala_ristorante'] ?? ''));
        $sNm  = ($sId !== '' ? ($saleMap[$sId] ?? $sId) : '');
        $sala = ($sNm !== '' ? 'Sala ' . $sNm : '—');

        $lines = parse_lines((string)($row['note'] ?? ''));
        if (!$lines) $lines = ['Menu da definire'];

        $lis = '';
        foreach ($lines as $l) {
            $lis .= '<li>'.h($l).'</li>';
        }

        $pastiHtml .= '
          <div class="item">
            <div class="item-head">
              <table>
                <tr>
                  <td class="item-date">
                    <div class="d">'.h($dd !== '' ? $dd : '—').'</div>
                    <div class="t">'.h($ora !== '' ? $ora : '—').'</div>
                  </td>
                  <td class="item-type">'.h(mb_strtoupper($tipo !== '' ? $tipo : 'PASTO')).'</td>
                  <td class="item-sala">'.h($sala).'</td>
                </tr>
              </table>
            </div>
            <div class="item-body">
              <ul class="bullets">'.$lis.'</ul>
            </div>
          </div>
        ';
    }
} else {
    $pastiHtml = '<div class="muted">Nessun pasto programmato.</div>';
}

// Extra
$extraHtml = '';
if ($extraRows) {
    foreach ($extraRows as $row) {
        $dd   = !empty($row['data']) ? format_date($row['data']) : '';
        $ora  = format_time($row['ora'] ?? '');
        $desc = trim((string)($row['descrizione'] ?? ''));
        $note = trim((string)($row['note'] ?? ''));

        $extraHtml .= '
          <div class="item">
            <div class="item-head">
              <table>
                <tr>
                  <td class="item-date">
                    <div class="d">'.h($dd !== '' ? $dd : '—').'</div>
                    <div class="t">'.h($ora !== '' ? $ora : '—').'</div>
                  </td>
                  <td class="item-type">'.h(mb_strtoupper($desc !== '' ? $desc : 'ATTIVITÀ')).'</td>
                  <td class="item-sala"></td>
                </tr>
              </table>
            </div>
            <div class="item-body">
              '.($note !== '' ? '<div class="note">'.nl2br(h($note)).'</div>' : '<div class="muted">Nessuna nota.</div>').'
            </div>
          </div>
        ';
    }
} else {
    $extraHtml = '<div class="muted">Nessuna attività inserita.</div>';
}

// Notes per reparti
$notesHtml = '
  <div class="dept-grid">
    '.dept_block('Ricevimento', $noteRicevimento).'
    '.dept_block('Cucina / ristorante', $noteCucina).'
    '.dept_block('Housekeeping', $noteHousekeeping).'
    '.dept_block('Manutenzione', $noteManutenzione).'
  </div>
';

// Allergie
$allergieHtml = (trim($noteAllergie) !== '')
    ? '<div class="note">'.nl2br(h($noteAllergie)).'</div>'
    : '<div class="muted">Nessuna allergia segnalata.</div>';

// Disposizione tavoli
$disposizioneHtml = (trim($noteDisposizioneTavoli) !== '')
    ? '<div class="note">'.nl2br(h($noteDisposizioneTavoli)).'</div>'
    : '<div class="muted">Nessuna indicazione inserita.</div>';

// Header label
$gruppoLabel = ($nomeGruppo !== '' ? 'Gruppo: ' . $nomeGruppo : 'Gruppo: —');

$headerInfoParts = [];
$headerInfoParts[] = ($nomeGruppo !== '' ? $nomeGruppo : '—');
$headerInfoParts[] = 'Check-in: ' . ($dataArrivo !== '' ? $dataArrivo : '—');
$headerInfoParts[] = 'Check-out: ' . ($dataPartenza !== '' ? $dataPartenza : '—');
$headerInfoParts[] = 'Persone: ' . (string)$numeroPersone;

$headerInfoLine = implode('  •  ', $headerInfoParts);


// Cover meta line
$coverLineParts = [];
if ($dataArrivo !== '')   $coverLineParts[] = 'Check-in: ' . $dataArrivo;
if ($dataPartenza !== '') $coverLineParts[] = 'Check-out: ' . $dataPartenza;
if ($notti > 0)           $coverLineParts[] = 'Notti: ' . $notti;
$coverLine = implode('  •  ', $coverLineParts);
if ($coverLine === '') $coverLine = 'Date soggiorno non indicate';

$headerInfoLine = 'Gruppo: ' . ($nomeGruppo !== '' ? $nomeGruppo : '—')
    . '  •  Check-in: ' . ($dataArrivo !== '' ? $dataArrivo : '—')
    . '  •  Check-out: ' . ($dataPartenza !== '' ? $dataPartenza : '—')
    . '  •  Persone: ' . (string)$numeroPersone;


/* =========================
   HTML
   ========================= */
$html = '
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 118px 34px 78px 34px; }

    body{ font-family:"DejaVu Sans",sans-serif; font-size:12px; color:#0f172a; }
    .muted{ color:#64748b; }
    .t-r{ text-align:right; }

    /* ===== Header ===== */
    .header{ position:fixed; top:-98px; left:0; right:0; height:92px; }
    .header-wrap{ border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; background:#fff; }
    .header-top{ background:#0b3a82; color:#fff; padding:14px 16px; }
    .header-title{ font-size:16px; font-weight:900; letter-spacing:.3px; margin:0; line-height:1.15; }
    .header-sub{ margin-top:6px; font-size:11px; opacity:.92; line-height:1.2; }

    .logo-img{ height:62px; }

    .header-bottom{ padding:10px 16px; }
    .hotel-meta{ font-size:10px; color:#475569; line-height:1.35; }

    /* ===== Footer ===== */
    .footer{
      position:fixed; bottom:-56px; left:0; right:0; height:46px;
      color:#64748b; font-size:9.5px; padding-top:8px;
      border-top:1px solid #e2e8f0;
    }

    /* ===== Titles ===== */
    .section{ margin:0 0 14px 0; page-break-inside:avoid; }
    .section-title{
      margin:0 0 10px 0; font-size:14px; font-weight:900; color:#0b3a82;
      letter-spacing:.2px;
    }
    .section-title .dot{
      display:inline-block; width:10px; height:10px; border-radius:3px; background:#0b3a82;
      margin-right:8px; vertical-align:middle;
    }
    .section-sub{
      margin:-2px 0 10px 0; color:#64748b; font-size:11px; font-weight:800;
      padding-left: 18px;
    }

    /* ===== Cover ===== */
    .cover{ border:1px solid #e2e8f0; border-radius:16px; background:#fff; padding:14px; margin: 0 0 14px 0; }
    .cover-title{ font-size:15px; font-weight:900; color:#0b3a82; margin:0; line-height:1.2; }
    .cover-meta{ margin-top:8px; color:#475569; font-size:11px; line-height:1.35; font-weight:800; }
    .badge{
      display:inline-block; padding:2px 8px; border-radius:999px;
      background:#eef2ff; border:1px solid #e2e8f0; color:#1e3a8a;
      font-weight:900; font-size:10px; letter-spacing:.2px; margin-left:6px;
      vertical-align: middle;
    }

    /* ===== Cards / KV ===== */
    .card{ border:1px solid #e2e8f0; background:#fff; border-radius:14px; padding:12px; }
    table.kvgrid{ width:100%; border-collapse:separate; border-spacing:10px; }
    td.kvcell{ width:50%; vertical-align:top; }
    .kv{ border:1px solid #eef2f7; border-radius:12px; padding:10px 12px; background:#f8fafc; }
    .kv-label{ font-size:9px; color:#64748b; font-weight:900; letter-spacing:.35px; }
    .kv-value{ margin-top:6px; font-size:12px; font-weight:900; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* ===== Tables ===== */
    .simple-table{
      width:100%; border-collapse:separate; border-spacing:0;
      border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; background:#fff;
    }
    .simple-table th{
      text-align:left; padding:10px 12px; font-size:10px; color:#475569;
      background:#f1f5f9; border-bottom:1px solid #e2e8f0;
    }
    .simple-table td{ padding:10px 12px; border-bottom:1px solid #eef2f7; }
    .simple-table tbody tr:nth-child(even) td{ background:#fcfdff; }
    .simple-table .tr-strong td{ font-weight:900; background:#f8fafc; }

    /* ===== Items ===== */
    .item{
      border:1px solid #e2e8f0; border-radius:14px; overflow:hidden;
      margin:0 0 10px 0; background:#fff;
    }
    .item-head{ background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:10px 12px; }
    .item-head table{ width:100%; border-collapse:collapse; }
    .item-date{ width:170px; font-weight:900; color:#0f172a; }
    .item-date .d{ font-size:12px; }
    .item-date .t{ font-size:11px; font-weight:800; color:#64748b; margin-top:2px; }
    .item-type{ text-align:center; font-weight:900; color:#0b3a82; font-size:16px; letter-spacing:.4px; }
    .item-sala{ width:220px; text-align:right; font-weight:900; color:#0f172a; font-size:12px; }
    .item-body{ padding:10px 14px 12px 14px; }

    ul.bullets{ margin:6px 0 0 18px; padding:0; }
    ul.bullets li{ margin:0 0 4px 0; line-height:1.3; }

    .note{ border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:10px 12px; line-height:1.35; }

    /* ===== Departments grid ===== */
    .dept-grid{ width:100%; }
    .dept{
      border:1px solid #e2e8f0; border-radius:14px; background:#fff;
      padding:12px; margin:0 0 10px 0;
    }
    .dept-head{ font-size:11px; font-weight:900; color:#0b3a82; margin:0 0 8px 0; }
    .dept-body{
      border:1px solid #eef2f7; border-radius:12px; background:#f8fafc;
      padding:10px 12px; line-height:1.35; color:#0f172a; font-weight:800;
    }

    .break{ page-break-before:always; }

    .footer{ position:fixed; bottom:-56px; left:0; right:0; height:46px;
      color:#64748b; font-size:9.5px; padding-top:8px; border-top:1px solid #e2e8f0; }
    .pagebox{ display:inline-block; min-width:40px; text-align:right; }

  </style>
</head>
<body>

  <!-- HEADER -->
  <div class="header">
    <div class="header-wrap">
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td class="header-top" style="width:75%;">
            <div class="header-title">SCHEDA ARRIVO GRUPPI</div>
            <div class="header-meta">'.h($headerInfoLine).'</div>
          </td>
          <td class="header-top" style="text-align:right;">
            '.($logoUri !== '' ? '<img class="logo-img" src="'.h($logoUri).'">' : '').'
          </td>
        </tr>
      </table>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <table width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td style="width:78%;">
          '.h(HOTEL_INDIRIZZO).'  •  Tel. '.h(HOTEL_TEL).'<br>
          '.h(HOTEL_EMAIL).'  •  '.h(HOTEL_WEB).'
        </td>
        <td class="t-r" style="width:22%; font-weight:900;">
          <span class="pagebox"></span>
        </td>
      </tr>
    </table>
  </div>



  <!-- COVER -->

  <!-- PAGINA 1 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Dati gruppo</div>
    <div class="card">
      <table class="kvgrid">
        <tr>
          <td class="kvcell">'.kv_card('Gruppo', $nomeGruppo).'</td>
          <td class="kvcell">'.kv_card('Agenzia / Ente', $agenzia).'</td>
        </tr>
        <tr>
          <td class="kvcell">'.kv_card('Referente', $referente).'</td>
          <td class="kvcell">'.kv_card('Telefono', $telefono).'</td>
        </tr>
        <tr>
          <td class="kvcell">'.kv_card('Email', $email).'</td>
          <td class="kvcell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title"><span class="dot"></span>Soggiorno</div>
    <div class="card">
      <table class="kvgrid">
        <tr>
          <td class="kvcell">'.kv_card('Check-in', $dataArrivo).'</td>
          <td class="kvcell">'.kv_card('Check-out', $dataPartenza).'</td>
        </tr>
        <tr>
          <td class="kvcell">'.kv_card('Orario check-in', $checkinOrario).'</td>
          <td class="kvcell">'.kv_card('Notti', (string)$notti).'</td>
        </tr>
        <tr>
          <td class="kvcell">'.kv_card('Trattamento', $trattamento).'</td>
          <td class="kvcell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title"><span class="dot"></span>Partecipanti</div>
    <div class="card">
      <table class="kvgrid">
        <tr>
          <td class="kvcell">'.kv_card('Adulti', (string)$numeroAdulti).'</td>
          <td class="kvcell">'.kv_card('Bambini', (string)$numeroBambini).'</td>
        </tr>
        <tr>
          <td class="kvcell">'.kv_card('Totale', (string)$numeroPersone).'</td>
          <td class="kvcell"></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title"><span class="dot"></span>Alloggi</div>
    '.$camereHtml.'
  </div>

  <!-- PAGINA 2 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Pasti e sala ristorante</div>
    <div class="section-sub">Elenco pasti</div>
    '.$pastiHtml.'
  </div>

  <!-- PAGINA 3 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Disposizione tavoli</div>
    <div class="section-sub">Disposizione tavoli (generale)</div>
    '.$disposizioneHtml.'
  </div>

  <!-- PAGINA 4 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Allergie / intolleranze</div>
    <div class="section-sub">Allergie / intolleranze (generale)</div>
    '.$allergieHtml.'
  </div>

  <!-- PAGINA 5 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Attività / extra</div>
    <div class="section-sub">Elenco attività</div>
    '.$extraHtml.'
  </div>

  <!-- PAGINA 6 -->
  <div class="section">
    <div class="section-title"><span class="dot"></span>Note per reparti</div>
    '.$notesHtml.'
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
$options->setChroot($rootPath);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');

try {
    $dompdf->render();

    // Paginazione affidabile (evita counter(pages) che spesso è 0)
    $canvas = $dompdf->getCanvas();
    $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');

    // Coordinate tarate per A4 portrait con margini @page sopra
    $canvas->page_text(
        520, 820,                 // X,Y: tarato per finire nella colonna destra del footer
        "{PAGE_NUM}/{PAGE_COUNT}", // <-- formato richiesto
        $font,
        9,
        [100, 116, 139]
    );


} catch (Throwable $e) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo 'Errore PDF: ' . h($e->getMessage());
    exit;
}

/* =========================
   OUTPUT PDF (ultra-safe)
   ========================= */
$pdf = $dompdf->output();

// chiudi QUALSIASI buffer aperto
while (ob_get_level() > 0) ob_end_clean();

// evita encoding/bytes strani
ini_set('zlib.output_compression', '0');
header_remove('Content-Encoding');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf;
exit;
