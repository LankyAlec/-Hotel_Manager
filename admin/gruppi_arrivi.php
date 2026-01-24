<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';

function table_exists(mysqli $db, string $table): bool
{
    $escaped = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$escaped}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool
{
    $escapedTable = $db->real_escape_string($table);
    $escapedColumn = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function add_column_if_missing(mysqli $db, string $table, string $column, string $definition): void
{
    if (!column_exists($db, $table, $column)) {
        $db->query("ALTER TABLE `{$table}` ADD COLUMN {$column} {$definition}");
    }
}

function get_tipologie_camere(mysqli $db): array
{
    $tipologie = [];

    if (empty($tipologie) && table_exists($db, 'soggiorni_tariffe')) {
        $res = $db->query("SELECT codice, descrizione FROM soggiorni_tariffe GROUP BY codice, descrizione ORDER BY  prezzo_solo_pernottamento ASC");
        $tipologie = $res instanceof mysqli_result ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    return $tipologie;
}

function get_sale_congressi(mysqli $db): array
{
    if (!table_exists($db, 'sale_congressi')) {
        return [];
    }

    $res = $db->query("SELECT id, nome FROM sale_congressi ORDER BY nome ASC");
    return $res instanceof mysqli_result ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function format_camere_summary(array $camere, array $tipologieMap): string
{
    $parts = [];
    foreach ($camere as $codice => $qta) {
        if ($qta <= 0) {
            continue;
        }
        $parts[] = $codice . ': ' . $qta;
    }
    return implode(', ', $parts);
}

function ensure_gruppi_arrivi_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS gruppi_arrivi (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nome_gruppo VARCHAR(120) NOT NULL,
        referente VARCHAR(120) NOT NULL,
        agenzia VARCHAR(120) NOT NULL,
        telefono VARCHAR(40) NOT NULL,
        email VARCHAR(120) NOT NULL,
        data_arrivo DATE NULL,
        data_partenza DATE NULL,
        checkin_orario TIME NULL,
        numero_persone INT UNSIGNED NOT NULL DEFAULT 0,
        numero_adulti INT UNSIGNED NOT NULL DEFAULT 0,
        numero_bambini INT UNSIGNED NOT NULL DEFAULT 0,
        tipologia_camere VARCHAR(120) NULL,
        camere_json LONGTEXT NULL,
        aree_riservate_json LONGTEXT NULL,
        area_preferita VARCHAR(120) NULL,
        trattamento VARCHAR(20) NULL,
        note_operativa TEXT NULL,
        note_ricevimento TEXT NULL,
        note_cucina TEXT NULL,
        note_housekeeping TEXT NULL,
        note_manutenzione TEXT NULL,
        pasti_json LONGTEXT NULL,
        extra_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gruppi_arrivi_nome (nome_gruppo),
        INDEX idx_gruppi_arrivi_data_arrivo (data_arrivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    add_column_if_missing($mysqli, 'gruppi_arrivi', 'numero_adulti', 'INT UNSIGNED NOT NULL DEFAULT 0');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'numero_bambini', 'INT UNSIGNED NOT NULL DEFAULT 0');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'camere_json', 'LONGTEXT NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'checkin_orario', 'TIME NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'aree_riservate_json', 'LONGTEXT NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'trattamento', 'VARCHAR(20) NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'note_ricevimento', 'TEXT NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'note_cucina', 'TEXT NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'note_housekeeping', 'TEXT NULL');
    add_column_if_missing($mysqli, 'gruppi_arrivi', 'note_manutenzione', 'TEXT NULL');
}

ensure_gruppi_arrivi_table($mysqli);

$alert = null;
$alertType = 'success';
$currentId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$emptyData = [
    'id' => 0,
    'nome_gruppo' => '',
    'referente' => '',
    'agenzia' => '',
    'telefono' => '',
    'email' => '',
    'data_arrivo' => '',
    'data_partenza' => '',
    'checkin_orario' => '',
    'numero_persone' => 0,
    'numero_adulti' => 0,
    'numero_bambini' => 0,
    'tipologia_camere' => '',
    'camere_json' => '{}',
    'aree_riservate_json' => '[]',
    'area_preferita' => '',
    'trattamento' => '',
    'note_operativa' => '',
    'note_ricevimento' => '',
    'note_cucina' => '',
    'note_housekeeping' => '',
    'note_manutenzione' => '',
    'pasti_json' => '[]',
    'extra_json' => '[]'
];

$currentData = $emptyData;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $nomeGruppo = trim($_POST['nome_gruppo'] ?? '');
        $referente = trim($_POST['referente'] ?? '');
        $agenzia = trim($_POST['agenzia'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dataArrivo = $_POST['data_arrivo'] ?: null;
        $dataPartenza = $_POST['data_partenza'] ?: null;
        $checkinOrario = trim($_POST['checkin_orario'] ?? '');
        $checkinOrario = $checkinOrario !== '' ? $checkinOrario : null;
        $numeroAdulti = (int)($_POST['numero_adulti'] ?? 0);
        $numeroBambini = (int)($_POST['numero_bambini'] ?? 0);
        $numeroPersone = max(0, $numeroAdulti + $numeroBambini);
        $trattamento = trim($_POST['trattamento'] ?? '');
        $camereInput = $_POST['camere'] ?? [];
        $camereSanitized = [];
        if (is_array($camereInput)) {
            foreach ($camereInput as $codice => $qta) {
                $qty = (int)$qta;
                if ($qty > 0) {
                    $camereSanitized[(string)$codice] = $qty;
                }
            }
        }
        $camereJson = json_encode($camereSanitized, JSON_UNESCAPED_UNICODE);
        $tipologiaCamere = trim($_POST['tipologia_camere'] ?? '');
        $areaPreferita = trim($_POST['area_preferita'] ?? '');
        $areeRiservateInput = $_POST['aree_riservate'] ?? [];
        $areeRiservate = [];
        if (is_array($areeRiservateInput)) {
            foreach ($areeRiservateInput as $value) {
                $id = (int)$value;
                if ($id > 0) {
                    $areeRiservate[] = $id;
                }
            }
        }
        $areeRiservate = array_values(array_unique($areeRiservate));
        $areeRiservateJson = json_encode($areeRiservate, JSON_UNESCAPED_UNICODE);
        $noteOperativa = trim($_POST['note_operativa'] ?? '');
        $noteRicevimento = trim($_POST['note_ricevimento'] ?? '');
        $noteCucina = trim($_POST['note_cucina'] ?? '');
        $noteHousekeeping = trim($_POST['note_housekeeping'] ?? '');
        $noteManutenzione = trim($_POST['note_manutenzione'] ?? '');
        $pasti = $_POST['pasti'] ?? [];
        $extra = $_POST['extra'] ?? [];
        $pastiJson = json_encode(array_values($pasti), JSON_UNESCAPED_UNICODE);
        $extraJson = json_encode(array_values($extra), JSON_UNESCAPED_UNICODE);
        $tipologieMap = [];
        foreach (get_tipologie_camere($mysqli) as $tipologia) {
            $codice = (string)($tipologia['codice'] ?? '');
            if ($codice === '') {
                continue;
            }
            $label = (string)($tipologia['descrizione'] ?? $codice);
            $tipologieMap[$codice] = $label;
        }
        if ($camereSanitized) {
            $tipologiaCamere = format_camere_summary($camereSanitized, $tipologieMap);
        }
        if ($areeRiservate) {
            $saleCongressi = get_sale_congressi($mysqli);
            $saleMap = [];
            foreach ($saleCongressi as $sala) {
                $saleId = (int)($sala['id'] ?? 0);
                if ($saleId <= 0) {
                    continue;
                }
                $saleMap[$saleId] = (string)($sala['nome'] ?? '');
            }
            $nomiAree = [];
            foreach ($areeRiservate as $salaId) {
                if (!empty($saleMap[$salaId])) {
                    $nomiAree[] = $saleMap[$salaId];
                }
            }
            if ($nomiAree) {
                $areaPreferita = implode(', ', $nomiAree);
            }
        }

        if ($nomeGruppo === '' || $referente === '' || $agenzia === '' || $telefono === '' || $email === '') {
            $alertType = 'danger';
            $alert = 'Compila tutti i campi obbligatori prima di salvare la scheda.';
        } else {
            if ($currentId > 0) {
                $stmt = $mysqli->prepare("UPDATE gruppi_arrivi
                    SET nome_gruppo=?, referente=?, agenzia=?, telefono=?, email=?, data_arrivo=?, data_partenza=?, checkin_orario=?,
                        numero_persone=?, numero_adulti=?, numero_bambini=?, tipologia_camere=?, camere_json=?, aree_riservate_json=?,
                        area_preferita=?, trattamento=?, note_operativa=?, note_ricevimento=?, note_cucina=?, note_housekeeping=?,
                        note_manutenzione=?, pasti_json=?, extra_json=?
                    WHERE id=?");
                $stmt->bind_param(
                    "ssssssssiiissssssssssssi",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $checkinOrario,
                    $numeroPersone,
                    $numeroAdulti,
                    $numeroBambini,
                    $tipologiaCamere,
                    $camereJson,
                    $areeRiservateJson,
                    $areaPreferita,
                    $trattamento,
                    $noteOperativa,
                    $noteRicevimento,
                    $noteCucina,
                    $noteHousekeeping,
                    $noteManutenzione,
                    $pastiJson,
                    $extraJson,
                    $currentId
                );
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    $alertType = 'success';
                    $alert = 'Scheda aggiornata con successo.';
                } else {
                    $alertType = 'danger';
                    $alert = 'Errore durante l\'aggiornamento della scheda.';
                }
            } else {
                $stmt = $mysqli->prepare("INSERT INTO gruppi_arrivi
                    (nome_gruppo, referente, agenzia, telefono, email, data_arrivo, data_partenza, checkin_orario, numero_persone,
                     numero_adulti, numero_bambini, tipologia_camere, camere_json, aree_riservate_json, area_preferita,
                     trattamento, note_operativa, note_ricevimento, note_cucina, note_housekeeping, note_manutenzione, pasti_json, extra_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "ssssssssiiissssssssssss",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $checkinOrario,
                    $numeroPersone,
                    $numeroAdulti,
                    $numeroBambini,
                    $tipologiaCamere,
                    $camereJson,
                    $areeRiservateJson,
                    $areaPreferita,
                    $trattamento,
                    $noteOperativa,
                    $noteRicevimento,
                    $noteCucina,
                    $noteHousekeeping,
                    $noteManutenzione,
                    $pastiJson,
                    $extraJson
                );
                $ok = $stmt->execute();
                $currentId = $ok ? (int)$stmt->insert_id : 0;
                $stmt->close();
                if ($ok) {
                    $alertType = 'success';
                    $alert = 'Scheda salvata con successo.';
                } else {
                    $alertType = 'danger';
                    $alert = 'Errore durante il salvataggio della scheda.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $deleteId = (int)($_POST['delete_id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = $mysqli->prepare("DELETE FROM gruppi_arrivi WHERE id=?");
            $stmt->bind_param("i", $deleteId);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                $alertType = 'success';
                $alert = 'Scheda eliminata.';
                if ($currentId === $deleteId) {
                    $currentId = 0;
                }
            } else {
                $alertType = 'danger';
                $alert = 'Errore durante l\'eliminazione della scheda.';
            }
        }
    }
}

if ($currentId > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM gruppi_arrivi WHERE id=?");
    $stmt->bind_param("i", $currentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
        $currentData = array_merge($emptyData, $row);
    } else {
        $alertType = 'warning';
        $alert = 'Scheda richiesta non trovata.';
        $currentId = 0;
    }
}

$tipologieCamere = get_tipologie_camere($mysqli);
$tipologieMap = [];
foreach ($tipologieCamere as $tipologia) {
    $codice = (string)($tipologia['codice'] ?? '');
    if ($codice === '') {
        continue;
    }
    $tipologieMap[$codice] = (string)($tipologia['descrizione'] ?? $codice);
}
$camereData = json_decode($currentData['camere_json'] ?? '{}', true) ?: [];
$areeRiservateData = json_decode($currentData['aree_riservate_json'] ?? '[]', true) ?: [];
$areeRiservateData = array_values(array_filter(array_map('intval', (array)$areeRiservateData)));
$currentData['aree_riservate'] = $areeRiservateData;
$saleCongressi = get_sale_congressi($mysqli);

$gruppiArchivio = [];
$archivioRes = $mysqli->query("SELECT * FROM gruppi_arrivi ORDER BY nome_gruppo ASC");
if ($archivioRes instanceof mysqli_result) {
    while ($row = $archivioRes->fetch_assoc()) {
        $row['pasti'] = json_decode($row['pasti_json'] ?? '[]', true) ?: [];
        $row['extra'] = json_decode($row['extra_json'] ?? '[]', true) ?: [];
        $row['camere'] = json_decode($row['camere_json'] ?? '{}', true) ?: [];
        $areeRiservate = json_decode($row['aree_riservate_json'] ?? '[]', true) ?: [];
        $row['aree_riservate'] = array_values(array_filter(array_map('intval', (array)$areeRiservate)));
        $gruppiArchivio[] = $row;
    }
    $archivioRes->free();
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$records = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $countStmt = $mysqli->prepare("SELECT COUNT(*) AS total
        FROM gruppi_arrivi
        WHERE nome_gruppo LIKE ? OR referente LIKE ? OR agenzia LIKE ?");
    $countStmt->bind_param("sss", $like, $like, $like);
    $countStmt->execute();
    $countRes = $countStmt->get_result();
    $totalRecords = $countRes ? (int)($countRes->fetch_assoc()['total'] ?? 0) : 0;
    $countStmt->close();

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $mysqli->prepare("SELECT id, nome_gruppo, referente, data_arrivo, data_partenza, numero_persone, numero_adulti, numero_bambini
        FROM gruppi_arrivi
        WHERE nome_gruppo LIKE ? OR referente LIKE ? OR agenzia LIKE ?
        ORDER BY data_arrivo DESC, id DESC
        LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $like, $like, $like, $perPage, $offset);
} else {
    $countRes = $mysqli->query("SELECT COUNT(*) AS total FROM gruppi_arrivi");
    $totalRecords = $countRes ? (int)($countRes->fetch_assoc()['total'] ?? 0) : 0;

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $mysqli->prepare("SELECT id, nome_gruppo, referente, data_arrivo, data_partenza, numero_persone, numero_adulti, numero_bambini
        FROM gruppi_arrivi
        ORDER BY data_arrivo DESC, id DESC
        LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
$records = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$pastiData = json_decode($currentData['pasti_json'] ?? '[]', true) ?: [];
$extraData = json_decode($currentData['extra_json'] ?? '[]', true) ?: [];
$queryBase = $search !== '' ? 'search=' . urlencode($search) . '&' : '';
$shouldShowForm = $currentId > 0 || ($_GET['open'] ?? '') === '1';
$shouldShowModal = $shouldShowForm;
?>

<style>
    .topbar {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
    }

    .topbar .left h3 {
        margin: 0;
    }

    .topbar .left .sub {
        color: #6c757d;
        font-size: 0.9rem;
        margin-top: 4px;
    }

    .pillbar {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        padding: 10px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 0.2rem 0.7rem rgba(0, 0, 0, 0.04);
    }

    .searchbox {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        background: #f9fafb;
        border-radius: 14px;
        padding: 8px 10px;
        min-width: 320px;
    }

    .searchbox i {
        opacity: 0.75;
    }

    .searchbox input {
        border: 0;
        outline: 0;
        background: transparent;
        width: 100%;
        font-size: 0.95rem;
    }

    @media (max-width: 992px) {
        .searchbox {
            min-width: 100%;
        }

        .pillbar {
            width: 100%;
        }
    }

    .btn-cta {
        border-radius: 14px;
        height: 42px;
        padding: 0 14px;
        font-weight: 800;
        box-shadow: 0 0.25rem 0.85rem rgba(13, 110, 253, 0.18);
    }

    .btn-cta i {
        margin-right: 6px;
    }

    .saved-schede-card {
        min-height: 58vh;
    }

    .saved-schede-card .card-body {
        display: flex;
        flex-direction: column;
    }

    .scheda-pdf {
        font-family: "Helvetica Neue", Arial, sans-serif;
        color: #1f2a44;
    }

    .scheda-pdf .pdf-page {
        padding: 36px 42px 48px;
        min-height: 1040px;
        box-sizing: border-box;
    }

    .scheda-pdf .pdf-header {
        text-align: center;
        margin-bottom: 18px;
    }

    .scheda-pdf .pdf-logo {
        max-width: 120px;
        height: auto;
        display: block;
        margin: 0 auto 6px;
    }

    .scheda-pdf .pdf-hotel {
        font-size: 14px;
        letter-spacing: 0.08em;
        font-weight: 600;
        color: #1f2a44;
    }

    .scheda-pdf .pdf-title {
        text-align: center;
        margin-bottom: 28px;
    }

    .scheda-pdf .pdf-title .title-main,
    .scheda-pdf .pdf-title .title-sub,
    .scheda-pdf .pdf-title .title-link {
        color: #1c4a8f;
        font-weight: 700;
        letter-spacing: 0.05em;
    }

    .scheda-pdf .pdf-title .title-main {
        font-size: 16px;
        margin-bottom: 4px;
    }

    .scheda-pdf .pdf-title .title-sub {
        font-size: 14px;
        margin-bottom: 6px;
    }

    .scheda-pdf .pdf-title .title-link {
        font-size: 14px;
        text-decoration: underline;
        margin-bottom: 6px;
        display: inline-block;
    }

    .scheda-pdf .pdf-title .title-note {
        font-size: 12px;
        font-style: italic;
        color: #4b5563;
    }

    .scheda-pdf .pdf-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px 40px;
        margin-bottom: 24px;
    }

    .scheda-pdf .pdf-field {
        font-size: 12px;
    }

    .scheda-pdf .pdf-field .pdf-label {
        color: #1c4a8f;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 0.08em;
        margin-bottom: 4px;
    }

    .scheda-pdf .pdf-field .pdf-value {
        font-weight: 600;
        font-size: 12px;
        color: #111827;
    }

    .scheda-pdf .pdf-line {
        border-bottom: 1px solid #1f2a44;
        margin-top: 4px;
    }

    .scheda-pdf .pdf-two-cols {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 40px;
        margin-top: 10px;
    }

    .scheda-pdf .pdf-section-title {
        font-size: 12px;
        color: #1c4a8f;
        font-weight: 700;
        letter-spacing: 0.08em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .scheda-pdf .pdf-list {
        font-size: 12px;
        color: #111827;
    }

    .scheda-pdf .pdf-list-row {
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #1f2a44;
        padding: 2px 0;
        font-weight: 600;
    }

    .scheda-pdf .pdf-list-row span:last-child {
        min-width: 40px;
        text-align: right;
    }

    .scheda-pdf .pdf-list-total {
        display: flex;
        justify-content: flex-end;
        font-weight: 700;
        margin-top: 6px;
        font-size: 12px;
    }

    .scheda-pdf .pdf-section-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 32px;
        margin-bottom: 18px;
    }

    .scheda-pdf .pdf-box {
        font-size: 12px;
    }

    .scheda-pdf .pdf-box .pdf-line {
        border-color: #1c4a8f;
    }

    .scheda-pdf .pdf-menu-title {
        font-size: 12px;
        font-weight: 700;
        color: #1c4a8f;
        margin: 18px 0 6px;
        text-transform: uppercase;
    }

    .scheda-pdf .pdf-menu-list {
        margin: 0 0 0 18px;
        padding: 0;
        font-size: 12px;
    }

    .scheda-pdf .pdf-menu-list li {
        margin-bottom: 4px;
    }

    .scheda-pdf .pdf-divider {
        border-top: 1px solid #1c4a8f;
        margin: 18px 0;
    }

    .scheda-pdf .pdf-notes-section {
        margin-bottom: 22px;
    }

    .scheda-pdf .pdf-notes-title {
        font-size: 12px;
        color: #1c4a8f;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        border-bottom: 1px solid #1c4a8f;
        padding-bottom: 4px;
        margin-bottom: 8px;
    }

    .scheda-pdf .pdf-paragraph {
        font-size: 12px;
        color: #111827;
        margin: 0 0 6px;
        white-space: pre-line;
    }

    .scheda-pdf .pdf-signature {
        text-align: right;
        font-style: italic;
        margin-top: 30px;
        font-size: 12px;
    }

    .scheda-pdf .pdf-signature-line {
        display: inline-block;
        min-width: 180px;
        border-bottom: 1px solid #1c4a8f;
        margin-top: 8px;
    }
</style>

<?php if ($alert): ?>
<div class="alert alert-<?= h($alertType) ?>">
    <?= h($alert) ?>
</div>
<?php endif; ?>

<div class="topbar mb-4">
    <div class="left">
        <h3><i class="bi bi-people"></i> Scheda gruppi in arrivo</h3>
        <div class="sub">Cerca rapidamente, gestisci le schede e crea una nuova registrazione.</div>
    </div>
    <div class="topbar-right">
        <div class="pillbar">
            <form class="searchbox" method="get">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Cerca gruppo, referente o agenzia" value="<?= h($search) ?>">
            </form>
            <button class="btn btn-primary btn-cta" type="button" id="openNewScheda" data-bs-toggle="modal" data-bs-target="#gruppoModal">
                <i class="bi bi-plus-circle"></i> Nuova scheda
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="gruppoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h4 class="modal-title mb-1">Scheda gruppo in arrivo</h4>
                    <p class="text-muted mb-0">Compila tutti i dati manualmente, salva la scheda e genera il PDF in un click.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <form id="gruppoForm" class="vstack gap-3" method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$currentId ?>">
                <div class="border rounded-4 p-3 bg-light">
                    <h6 class="text-uppercase text-muted mb-3">Carica gruppo registrato</h6>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Cerca gruppo esistente</label>
                            <input type="text" class="form-control" id="gruppoLookup" list="gruppiList" placeholder="Digita il nome del gruppo">
                            <datalist id="gruppiList">
                                <?php foreach ($gruppiArchivio as $gruppo): ?>
                                    <option value="<?= h($gruppo['nome_gruppo']) ?>" data-id="<?= (int)$gruppo['id'] ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-outline-primary w-100" type="button" id="caricaGruppo">
                                <i class="bi bi-folder2-open"></i> Carica scheda
                            </button>
                        </div>
                    </div>
                </div>
                <div class="border rounded-4 p-3">
                    <h6 class="text-uppercase text-muted mb-3">Anagrafica gruppo</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nome gruppo *</label>
                            <input type="text" class="form-control" id="nomeGruppo" name="nome_gruppo" value="<?= h($currentData['nome_gruppo']) ?>" placeholder="Es. Gruppo Lago Blu" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Referente principale *</label>
                            <input type="text" class="form-control" id="referente" name="referente" value="<?= h($currentData['referente']) ?>" placeholder="Nome e cognome" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Agenzia / Ente *</label>
                            <input type="text" class="form-control" id="agenzia" name="agenzia" value="<?= h($currentData['agenzia']) ?>" placeholder="Es. Tour Operator" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefono *</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= h($currentData['telefono']) ?>" placeholder="+39 ..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= h($currentData['email']) ?>" placeholder="referente@email.it" required>
                        </div>
                    </div>
                </div>

                <div class="border rounded-4 p-3">
                    <h6 class="text-uppercase text-muted mb-3">Soggiorno</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Arrivo</label>
                            <input type="date" class="form-control" id="dataArrivo" name="data_arrivo" value="<?= h($currentData['data_arrivo']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Partenza</label>
                            <input type="date" class="form-control" id="dataPartenza" name="data_partenza" value="<?= h($currentData['data_partenza']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Orario previsto check-in</label>
                            <input type="time" class="form-control" id="checkinOrario" name="checkin_orario" value="<?= h($currentData['checkin_orario']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trattamento</label>
                            <select class="form-select" id="trattamento" name="trattamento">
                                <option value="">Seleziona</option>
                                <option value="Solo pernottamento" <?= $currentData['trattamento'] === 'Solo pernottamento' ? 'selected' : '' ?>>Solo pernottamento</option>
                                <option value="BB" <?= $currentData['trattamento'] === 'BB' ? 'selected' : '' ?>>BB</option>
                                <option value="HB" <?= $currentData['trattamento'] === 'HB' ? 'selected' : '' ?>>HB</option>
                                <option value="FB" <?= $currentData['trattamento'] === 'FB' ? 'selected' : '' ?>>FB</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Numero adulti</label>
                            <input type="number" class="form-control" id="numeroAdulti" name="numero_adulti" min="0" value="<?= (int)$currentData['numero_adulti'] ?>" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Numero bambini</label>
                            <input type="number" class="form-control" id="numeroBambini" name="numero_bambini" min="0" value="<?= (int)$currentData['numero_bambini'] ?>" placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Totale partecipanti</label>
                            <input type="number" class="form-control" id="numeroTotale" value="<?= (int)$currentData['numero_persone'] ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Aree riservate</label>
                            <?php if (!empty($saleCongressi)): ?>
                                <input type="hidden" name="area_preferita" id="areaPreferitaHidden" value="<?= h($currentData['area_preferita']) ?>">
                                <div class="border rounded-3 p-2" id="areaPreferita">
                                    <?php foreach ($saleCongressi as $sala): ?>
                                        <?php
                                            $salaId = (int)($sala['id'] ?? 0);
                                            $salaNome = (string)($sala['nome'] ?? '');
                                            $isSelected = in_array($salaId, $areeRiservateData, true);
                                        ?>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input area-riservata"
                                                type="checkbox"
                                                id="areaRiservata<?= $salaId ?>"
                                                name="aree_riservate[]"
                                                value="<?= $salaId ?>"
                                                data-label="<?= h($salaNome) ?>"
                                                <?= $isSelected ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="areaRiservata<?= $salaId ?>">
                                                <?= h($salaNome) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Seleziona una o più sale riservate.</div>
                            <?php else: ?>
                                <input type="text" class="form-control" id="areaPreferita" name="area_preferita" value="<?= h($currentData['area_preferita']) ?>" placeholder="Es. 2° piano riservato">
                                <div class="form-text">Nessuna sala disponibile nella tabella sale_congressi.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Composizione camere</label>
                            <?php if (!empty($tipologieCamere)): ?>
                                <div class="row g-3">
                                    <?php foreach ($tipologieCamere as $tipologia): ?>
                                        <?php
                                            $codice = (string)($tipologia['codice'] ?? '');
                                            $label = $codice;
                                            $qty = (int)($camereData[$codice] ?? 0);
                                        ?>
                                        <div class="col-6 col-lg-3">
                                            <label class="form-label small"><?= h($label) ?></label>
                                            <input type="number"
                                                class="form-control camera-qty"
                                                name="camere[<?= h($codice) ?>]"
                                                data-code="<?= h($codice) ?>"
                                                data-label="<?= h($label) ?>"
                                                min="0"
                                                value="<?= $qty ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <input type="text" class="form-control" id="tipologiaCamere" name="tipologia_camere" value="<?= h($currentData['tipologia_camere']) ?>" placeholder="Es. 10 doppie + 2 singole">
                                <div class="form-text">Nessuna tipologia configurata, usa una descrizione libera.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="border rounded-4 p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h6 class="text-uppercase text-muted mb-0">Pasti programmati</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" id="aggiungiPasto">
                            <i class="bi bi-plus-circle"></i> Aggiungi pasto
                        </button>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0" id="pastiTable">
                            <thead>
                                <tr>
                                    <th class="w-30">Data</th>
                                    <th class="w-35">Voce</th>
                                    <th class="w-20">Ora</th>
                                    <th class="w-15"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="border rounded-4 p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h6 class="text-uppercase text-muted mb-0">Attività / extra</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" id="aggiungiExtra">
                            <i class="bi bi-plus-circle"></i> Aggiungi attività
                        </button>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0" id="extraTable">
                            <thead>
                                <tr>
                                    <th class="w-30">Data</th>
                                    <th class="w-35">Voce</th>
                                    <th class="w-20">Ora</th>
                                    <th class="w-15"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="border rounded-4 p-3">
                    <h6 class="text-uppercase text-muted mb-3">Note reparti</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">RICEVIMENTO</label>
                            <textarea class="form-control" name="note_ricevimento" id="noteRicevimento" rows="4" placeholder="Note per il ricevimento"><?= h($currentData['note_ricevimento']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">CUCINA/RISTORANTE</label>
                            <textarea class="form-control" name="note_cucina" id="noteCucina" rows="4" placeholder="Note per cucina/ristorante"><?= h($currentData['note_cucina']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">HOUSEKEEPING</label>
                            <textarea class="form-control" name="note_housekeeping" id="noteHousekeeping" rows="4" placeholder="Note per housekeeping"><?= h($currentData['note_housekeeping']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">MANUTENZIONE</label>
                            <textarea class="form-control" name="note_manutenzione" id="noteManutenzione" rows="4" placeholder="Note per manutenzione"><?= h($currentData['note_manutenzione']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success" type="submit">
                        <i class="bi bi-save"></i> Salva scheda
                    </button>
                    <button class="btn btn-primary" type="submit" formaction="<?= BASE_URL ?>/admin/gruppi_arrivi_pdf.php" formtarget="_blank">
                        <i class="bi bi-filetype-pdf"></i> Genera PDF
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="position-absolute top-0 start-0 opacity-0" style="pointer-events: none; z-index: -1; width: 1000px;">
    <div id="schedaPreview" class="scheda-pdf border rounded-4 bg-white">
        <div class="pdf-page">
            <div class="pdf-header">
                <img src="<?= BASE_URL ?>/img/logo.png" class="pdf-logo" alt="Park Hotel Paradiso">
                <div class="pdf-hotel">PARK HOTEL PARADISO</div>
            </div>
            <div class="pdf-title">
                <div class="title-main">GESTIONE ARRIVO GRUPPI</div>
                <div class="title-sub">ISTRUZIONI OPERATIVE INTERNE</div>
                <div class="title-link">SCHEDA ARRIVO GRUPPI</div>
                <div class="title-note">Documento operativo interno - valido per tutti i reparti</div>
            </div>

            <div class="pdf-info-grid">
                <div class="pdf-field">
                    <div class="pdf-label">GRUPPO:</div>
                    <div class="pdf-value" id="previewNome">Nome gruppo</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">CODICE GRUPPO:</div>
                    <div class="pdf-value" id="previewCodiceGruppo">Agenzia / Ente</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">REFERENTE:</div>
                    <div class="pdf-value" id="previewReferente">Nome referente</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">CHECK IN:</div>
                    <div class="pdf-value" id="previewCheckIn">--/--/----</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">ORARIO CHECK IN:</div>
                    <div class="pdf-value" id="previewCheckInOrario">--:--</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">CHECK OUT:</div>
                    <div class="pdf-value" id="previewCheckOut">--/--/----</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-field">
                    <div class="pdf-label">N° NOTTI:</div>
                    <div class="pdf-value" id="previewNotti">0</div>
                    <div class="pdf-line"></div>
                </div>
            </div>

            <div class="pdf-two-cols">
                <div>
                    <div class="pdf-section-title">OSPITI</div>
                    <div class="pdf-list" id="previewOspiti">
                        <div class="pdf-list-row"><span>Adulti</span><span id="previewAdulti">0</span></div>
                        <div class="pdf-list-row"><span>Bambini</span><span id="previewBambini">0</span></div>
                    </div>
                    <div class="pdf-list-total">Totale: <span id="previewTotale">0</span></div>
                </div>
                <div>
                    <div class="pdf-section-title">ALLOGGI</div>
                    <div class="pdf-list" id="previewAlloggi">
                        <div class="pdf-list-row"><span>Nessuna camera</span><span>0</span></div>
                    </div>
                    <div class="pdf-list-total">Totale: <span id="previewCamereTotale">0</span></div>
                </div>
            </div>
        </div>

        <div class="pdf-page">
            <div class="pdf-section-row">
                <div class="pdf-box">
                    <div class="pdf-section-title">TRATTAMENTO</div>
                    <div class="pdf-paragraph" id="previewTrattamento">Trattamento</div>
                    <div class="pdf-paragraph" id="previewArea">Area riservata</div>
                    <div class="pdf-line"></div>
                </div>
                <div class="pdf-box">
                    <div class="pdf-section-title">SALA RISTORANTE</div>
                    <div class="pdf-paragraph" id="previewSalaRistorante">Nessuna nota inserita.</div>
                    <div class="pdf-line"></div>
                </div>
            </div>

            <div class="pdf-divider"></div>
            <div class="pdf-section-title">MENÙ</div>
            <div id="previewMenu">
                <div class="pdf-paragraph">Nessun menù inserito.</div>
            </div>
        </div>

        <div class="pdf-page">
            <div class="pdf-section-title">ALLERGIE / INTOLLERANZE</div>
            <div class="pdf-paragraph" id="previewAllergie">Nessuna allergia segnalata.</div>
            <div class="pdf-divider"></div>
            <div class="pdf-section-title">DISTRIBUZIONE TAVOLI</div>
            <div class="pdf-paragraph" id="previewDistribuzione">Nessuna indicazione inserita.</div>
        </div>

        <div class="pdf-page">
            <div class="pdf-title">
                <div class="title-main">NOTE PER REPARTI</div>
            </div>

            <div class="pdf-notes-section">
                <div class="pdf-notes-title">RICEVIMENTO</div>
                <div class="pdf-paragraph" id="previewNoteRicevimento">Nessuna nota per il ricevimento.</div>
            </div>
            <div class="pdf-notes-section">
                <div class="pdf-notes-title">CUCINA / RISTORANTE</div>
                <div class="pdf-paragraph" id="previewNoteCucina">Nessuna nota per cucina/ristorante.</div>
            </div>
            <div class="pdf-notes-section">
                <div class="pdf-notes-title">HOUSEKEEPING</div>
                <div class="pdf-paragraph" id="previewNoteHousekeeping">Nessuna nota per housekeeping.</div>
            </div>
            <div class="pdf-notes-section">
                <div class="pdf-notes-title">MANUTENZIONE</div>
                <div class="pdf-paragraph" id="previewNoteManutenzione">Nessuna nota per manutenzione.</div>
            </div>

            <div class="pdf-divider"></div>
            <div class="pdf-section-title">MANUTENZIONE</div>
            <div class="pdf-paragraph" id="previewNoteManutenzioneSintesi">Nessuna segnalazione particolare.</div>
            <div class="pdf-signature">
                La Direzione
                <div class="pdf-signature-line"></div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card mt-4 saved-schede-card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Schede salvate</h5>
                <p class="text-muted mb-0">Elenco ordinato per data di arrivo (decrescente).</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-soft"><i class="bi bi-calendar-event"></i> <?= (int)$totalRecords ?> schede</span>
                <span class="text-muted small">Max 10 schede per pagina</span>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small">Pagina <?= (int)$page ?> di <?= (int)$totalPages ?></span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Navigazione schede">
                    <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= $queryBase ?>page=<?= max(1, $page - 1) ?>">
                        <i class="bi bi-arrow-left"></i> Più recenti
                    </a>
                    <a class="btn btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= $queryBase ?>page=<?= min($totalPages, $page + 1) ?>">
                        Più vecchie <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($records)): ?>
            <div class="text-muted">Nessuna scheda trovata.</div>
        <?php else: ?>
            <div class="vstack gap-3">
                <?php foreach ($records as $record): ?>
                    <?php
                        $periodo = '—';
                        if (!empty($record['data_arrivo']) || !empty($record['data_partenza'])) {
                            $arrivo = !empty($record['data_arrivo']) ? date('d/m/Y', strtotime($record['data_arrivo'])) : '—';
                            $partenza = !empty($record['data_partenza']) ? date('d/m/Y', strtotime($record['data_partenza'])) : '—';
                            $periodo = $arrivo . ' → ' . $partenza;
                        }
                        $adulti = (int)($record['numero_adulti'] ?? 0);
                        $bambini = (int)($record['numero_bambini'] ?? 0);
                        $totale = (int)($record['numero_persone'] ?? ($adulti + $bambini));
                        $dettaglioPartecipanti = $totale > 0
                            ? "{$totale} (Adulti {$adulti}, Bambini {$bambini})"
                            : '0';
                    ?>
                    <div class="border rounded-4 p-3 d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold fs-5"><?= h($record['nome_gruppo']) ?></div>
                            <div class="text-muted">Referente: <?= h($record['referente']) ?></div>
                            <div class="text-muted">Periodo: <?= h($periodo) ?></div>
                            <div class="text-muted">Partecipanti: <?= h($dettaglioPartecipanti) ?></div>
                            <div class="text-muted small">ID #<?= (int)$record['id'] ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/gruppi_arrivi.php?id=<?= (int)$record['id'] ?>&open=1">
                                <i class="bi bi-pencil-square"></i> Modifica
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Confermi l\'eliminazione della scheda?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?= (int)$record['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i> Elimina
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4" aria-label="Paginazione schede">
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $queryBase ?>page=<?= max(1, $page - 1) ?>" aria-label="Pagina precedente">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $queryBase ?>page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $queryBase ?>page=<?= min($totalPages, $page + 1) ?>" aria-label="Pagina successiva">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
    const form = document.getElementById('gruppoForm');
    const preview = {
        nome: document.getElementById('previewNome'),
        codiceGruppo: document.getElementById('previewCodiceGruppo'),
        checkIn: document.getElementById('previewCheckIn'),
        checkInOrario: document.getElementById('previewCheckInOrario'),
        checkOut: document.getElementById('previewCheckOut'),
        notti: document.getElementById('previewNotti'),
        adulti: document.getElementById('previewAdulti'),
        bambini: document.getElementById('previewBambini'),
        totale: document.getElementById('previewTotale'),
        alloggi: document.getElementById('previewAlloggi'),
        camereTotale: document.getElementById('previewCamereTotale'),
        referente: document.getElementById('previewReferente'),
        trattamento: document.getElementById('previewTrattamento'),
        area: document.getElementById('previewArea'),
        salaRistorante: document.getElementById('previewSalaRistorante'),
        menu: document.getElementById('previewMenu'),
        allergie: document.getElementById('previewAllergie'),
        distribuzione: document.getElementById('previewDistribuzione'),
        noteRicevimento: document.getElementById('previewNoteRicevimento'),
        noteCucina: document.getElementById('previewNoteCucina'),
        noteHousekeeping: document.getElementById('previewNoteHousekeeping'),
        noteManutenzione: document.getElementById('previewNoteManutenzione'),
        noteManutenzioneSintesi: document.getElementById('previewNoteManutenzioneSintesi')
    };

    const pastiTable = document.querySelector('#pastiTable tbody');
    const extraTable = document.querySelector('#extraTable tbody');
    const pastiTableHeader = document.querySelector('#pastiTable thead');
    const extraTableHeader = document.querySelector('#extraTable thead');

    const storedPasti = <?= json_encode($pastiData, JSON_UNESCAPED_UNICODE) ?>;
    const storedExtra = <?= json_encode($extraData, JSON_UNESCAPED_UNICODE) ?>;
    const storedCamere = <?= json_encode($camereData, JSON_UNESCAPED_UNICODE) ?>;
    const currentData = <?= json_encode($currentData, JSON_UNESCAPED_UNICODE) ?>;
    const emptyData = <?= json_encode($emptyData, JSON_UNESCAPED_UNICODE) ?>;
    const gruppiArchivio = <?= json_encode($gruppiArchivio, JSON_UNESCAPED_UNICODE) ?>;
    const shouldShowModal = <?= $shouldShowModal ? 'true' : 'false' ?>;

    let rowCounter = 0;
    const createRowGroupId = () => `row-${Date.now()}-${rowCounter++}`;

    const creaRigaPasto = (data = {}) => {
        const groupId = createRowGroupId();
        const mainRow = document.createElement('tr');
        mainRow.dataset.group = groupId;
        mainRow.dataset.type = 'pasto-main';
        mainRow.innerHTML = `
            <td><input type="date" class="form-control form-control-sm" value="${data.data || ''}" required></td>
            <td>
                <select class="form-select form-select-sm" required>
                    <option value="">Seleziona</option>
                    <option ${data.tipo === 'Colazione' ? 'selected' : ''}>Colazione</option>
                    <option ${data.tipo === 'Pranzo' ? 'selected' : ''}>Pranzo</option>
                    <option ${data.tipo === 'Cena' ? 'selected' : ''}>Cena</option>
                    <option ${data.tipo === 'Brunch' ? 'selected' : ''}>Brunch</option>
                    <option ${data.tipo === 'Altro' ? 'selected' : ''}>Altro</option>
                </select>
            </td>
            <td><input type="time" class="form-control form-control-sm" value="${data.ora || ''}" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        const noteRow = document.createElement('tr');
        noteRow.dataset.group = groupId;
        noteRow.dataset.type = 'pasto-note';
        noteRow.innerHTML = `
            <td colspan="4">
                <label class="form-label small text-muted mb-1">Note</label>
                <textarea class="form-control form-control-sm" rows="7" placeholder="Allergie, menù">${data.note || ''}</textarea>
            </td>
        `;
        mainRow.querySelector('button').addEventListener('click', () => {
            mainRow.remove();
            noteRow.remove();
            rinumeraRighe();
            aggiornaPreview();
        });
        mainRow.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        noteRow.querySelectorAll('textarea').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return [mainRow, noteRow];
    };

    const creaRigaExtra = (data = {}) => {
        const groupId = createRowGroupId();
        const mainRow = document.createElement('tr');
        mainRow.dataset.group = groupId;
        mainRow.dataset.type = 'extra-main';
        mainRow.innerHTML = `
            <td><input type="date" class="form-control form-control-sm" value="${data.data || ''}" required></td>
            <td><input type="text" class="form-control form-control-sm" value="${data.descrizione || ''}" placeholder="Visita guidata, sala meeting" required></td>
            <td><input type="time" class="form-control form-control-sm" value="${data.ora || ''}" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        const noteRow = document.createElement('tr');
        noteRow.dataset.group = groupId;
        noteRow.dataset.type = 'extra-note';
        noteRow.innerHTML = `
            <td colspan="4">
                <label class="form-label small text-muted mb-1">Note</label>
                <textarea class="form-control form-control-sm" rows="7" placeholder="Referente, note">${data.note || ''}</textarea>
            </td>
        `;
        mainRow.querySelector('button').addEventListener('click', () => {
            mainRow.remove();
            noteRow.remove();
            rinumeraRighe();
            aggiornaPreview();
        });
        mainRow.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        noteRow.querySelectorAll('textarea').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return [mainRow, noteRow];
    };

    const rinumeraRighe = () => {
        const pastiMainRows = Array.from(pastiTable.querySelectorAll('tr[data-type="pasto-main"]'));
        pastiMainRows.forEach((row, index) => {
            const groupId = row.dataset.group;
            const noteRow = pastiTable.querySelector(`tr[data-type="pasto-note"][data-group="${groupId}"]`);
            const inputs = row.querySelectorAll('input, select');
            const noteTextarea = noteRow?.querySelector('textarea');
            inputs[0].name = `pasti[${index}][data]`;
            inputs[1].name = `pasti[${index}][tipo]`;
            inputs[2].name = `pasti[${index}][ora]`;
            if (noteTextarea) {
                noteTextarea.name = `pasti[${index}][note]`;
            }
        });
        const extraMainRows = Array.from(extraTable.querySelectorAll('tr[data-type="extra-main"]'));
        extraMainRows.forEach((row, index) => {
            const groupId = row.dataset.group;
            const noteRow = extraTable.querySelector(`tr[data-type="extra-note"][data-group="${groupId}"]`);
            const inputs = row.querySelectorAll('input');
            const noteTextarea = noteRow?.querySelector('textarea');
            inputs[0].name = `extra[${index}][data]`;
            inputs[1].name = `extra[${index}][descrizione]`;
            inputs[2].name = `extra[${index}][ora]`;
            if (noteTextarea) {
                noteTextarea.name = `extra[${index}][note]`;
            }
        });
    };

    const toggleTableHeader = (tableBody, headerEl) => {
        if (!headerEl) return;
        const hasRows = tableBody.querySelectorAll('tr').length > 0;
        headerEl.classList.toggle('d-none', !hasRows);
    };

    const formatDate = (value) => {
        if (!value) return '--/--/----';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('it-IT');
    };

    const calcNotti = (arrivo, partenza) => {
        if (!arrivo || !partenza) return 0;
        const start = new Date(arrivo);
        const end = new Date(partenza);
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0;
        const diff = Math.round((end - start) / (1000 * 60 * 60 * 24));
        return diff > 0 ? diff : 0;
    };

    const buildAlloggiList = () => {
        const camereInputs = Array.from(document.querySelectorAll('.camera-qty'));
        const cameraRows = camereInputs.map((input) => {
            const qty = parseInt(input.value, 10) || 0;
            if (!qty) return null;
            return {
                label: input.dataset.label || input.dataset.code || 'Camera',
                qty
            };
        }).filter(Boolean);

        const fallback = document.getElementById('tipologiaCamere')?.value;
        preview.alloggi.innerHTML = '';

        if (cameraRows.length === 0 && fallback) {
            preview.alloggi.innerHTML = `<div class="pdf-list-row"><span>${fallback}</span><span></span></div>`;
            preview.camereTotale.textContent = '0';
            return;
        }

        if (cameraRows.length === 0) {
            preview.alloggi.innerHTML = '<div class="pdf-list-row"><span>Nessuna camera</span><span>0</span></div>';
            preview.camereTotale.textContent = '0';
            return;
        }

        let total = 0;
        cameraRows.forEach((row) => {
            total += row.qty;
            const rowEl = document.createElement('div');
            rowEl.className = 'pdf-list-row';
            rowEl.innerHTML = `<span>${row.label}</span><span>${row.qty}</span>`;
            preview.alloggi.appendChild(rowEl);
        });
        preview.camereTotale.textContent = total.toString();
    };

    const buildMenu = (pastiRows) => {
        preview.menu.innerHTML = '';
        if (!pastiRows.length) {
            preview.menu.innerHTML = '<div class="pdf-paragraph">Nessun menù inserito.</div>';
            return;
        }

        pastiRows.forEach((row) => {
            const title = document.createElement('div');
            const dateLabel = row.data ? formatDate(row.data) : '';
            const oraLabel = row.ora ? ` - ${row.ora}` : '';
            title.className = 'pdf-menu-title';
            title.textContent = `${row.tipo || 'Pasto'} ${dateLabel}${oraLabel}`.trim();
            preview.menu.appendChild(title);

            const list = document.createElement('ul');
            list.className = 'pdf-menu-list';
            const noteLines = (row.note || '')
                .split(/\r?\n/)
                .map((line) => line.replace(/^[\-\•\*]\s*/, '').trim())
                .filter(Boolean);

            if (noteLines.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'Menù da definire';
                list.appendChild(li);
            } else {
                noteLines.forEach((line) => {
                    const li = document.createElement('li');
                    li.textContent = line;
                    list.appendChild(li);
                });
            }
            preview.menu.appendChild(list);
        });
    };

    const buildDistribuzione = (extraRows) => {
        if (!extraRows.length) {
            preview.distribuzione.textContent = 'Nessuna indicazione inserita.';
            return;
        }
        const lines = extraRows.map((row) => {
            const parts = [row.descrizione, row.data, row.ora, row.note].filter(Boolean);
            return parts.join(' - ');
        }).filter(Boolean);
        preview.distribuzione.textContent = lines.join('\n');
    };

    const aggiornaPreview = () => {
        preview.nome.textContent = document.getElementById('nomeGruppo').value || 'Nome gruppo';
        preview.codiceGruppo.textContent = document.getElementById('agenzia').value || 'Agenzia / Ente';
        const arrivo = document.getElementById('dataArrivo').value;
        const partenza = document.getElementById('dataPartenza').value;
        preview.checkIn.textContent = formatDate(arrivo);
        preview.checkOut.textContent = formatDate(partenza);
        preview.notti.textContent = calcNotti(arrivo, partenza).toString();
        const checkinOrarioInput = document.getElementById('checkinOrario');
        const checkinOrarioValue = checkinOrarioInput?.value || '';
        if (preview.checkInOrario) {
            preview.checkInOrario.textContent = checkinOrarioValue || '--:--';
        }
        const adulti = parseInt(document.getElementById('numeroAdulti').value, 10) || 0;
        const bambini = parseInt(document.getElementById('numeroBambini').value, 10) || 0;
        const totale = adulti + bambini;
        document.getElementById('numeroTotale').value = totale;
        preview.adulti.textContent = adulti.toString();
        preview.bambini.textContent = bambini.toString();
        preview.totale.textContent = totale.toString();
        preview.referente.textContent = document.getElementById('referente').value || 'Nome referente';
        preview.trattamento.textContent = document.getElementById('trattamento').value || 'Trattamento';
        const areaSelect = document.getElementById('areaPreferita');
        let areaValue = 'Area riservata';
        const areaCheckboxes = Array.from(document.querySelectorAll('.area-riservata'));
        if (areaCheckboxes.length) {
            const selected = areaCheckboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.dataset.label || '')
                .filter(Boolean);
            areaValue = selected.length ? selected.join(', ') : 'Area riservata';
            const hiddenArea = document.getElementById('areaPreferitaHidden');
            if (hiddenArea) {
                hiddenArea.value = selected.join(', ');
            }
        } else if (areaSelect instanceof HTMLSelectElement && areaSelect.multiple) {
            const selected = Array.from(areaSelect.selectedOptions).map((opt) => opt.text.trim()).filter(Boolean);
            areaValue = selected.length ? selected.join(', ') : 'Area riservata';
            const hiddenArea = document.getElementById('areaPreferitaHidden');
            if (hiddenArea) {
                hiddenArea.value = selected.join(', ');
            }
        } else if (areaSelect) {
            areaValue = areaSelect.value || 'Area riservata';
        }
        preview.area.textContent = areaValue;
        buildAlloggiList();

        const noteRicevimento = document.getElementById('noteRicevimento').value || 'Nessuna nota per il ricevimento.';
        const noteCucinaRaw = document.getElementById('noteCucina').value || '';
        const noteCucina = noteCucinaRaw || 'Nessuna nota per cucina/ristorante.';
        const noteHousekeeping = document.getElementById('noteHousekeeping').value || 'Nessuna nota per housekeeping.';
        const noteManutenzione = document.getElementById('noteManutenzione').value || 'Nessuna nota per manutenzione.';
        preview.noteRicevimento.textContent = noteRicevimento;
        preview.noteCucina.textContent = noteCucina;
        preview.noteHousekeeping.textContent = noteHousekeeping;
        preview.noteManutenzione.textContent = noteManutenzione;
        preview.noteManutenzioneSintesi.textContent = noteManutenzione;
        preview.salaRistorante.textContent = noteCucinaRaw || 'Nessuna nota inserita.';
        preview.allergie.textContent = noteCucinaRaw || 'Nessuna allergia segnalata.';

        const pastiRows = Array.from(pastiTable.querySelectorAll('tr[data-type="pasto-main"]')).map((row) => {
            const inputs = row.querySelectorAll('input, select');
            const noteRow = pastiTable.querySelector(`tr[data-type="pasto-note"][data-group="${row.dataset.group}"]`);
            const noteTextarea = noteRow?.querySelector('textarea');
            return {
                data: inputs[0].value,
                tipo: inputs[1].value,
                ora: inputs[2].value,
                note: noteTextarea?.value || ''
            };
        }).filter((row) => row.data || row.tipo || row.ora || row.note);

        const extraRows = Array.from(extraTable.querySelectorAll('tr[data-type="extra-main"]')).map((row) => {
            const inputs = row.querySelectorAll('input');
            const noteRow = extraTable.querySelector(`tr[data-type="extra-note"][data-group="${row.dataset.group}"]`);
            const noteTextarea = noteRow?.querySelector('textarea');
            return {
                data: inputs[0].value,
                descrizione: inputs[1].value,
                ora: inputs[2].value,
                note: noteTextarea?.value || ''
            };
        }).filter((row) => row.data || row.descrizione || row.ora || row.note);

        buildMenu(pastiRows);
        buildDistribuzione(extraRows);

        toggleTableHeader(pastiTable, pastiTableHeader);
        toggleTableHeader(extraTable, extraTableHeader);
    };

    const renderPasti = (rows = []) => {
        pastiTable.innerHTML = '';
        if (rows.length) {
            rows.forEach((item) => {
                const rowItems = creaRigaPasto(item);
                rowItems.forEach((row) => pastiTable.appendChild(row));
            });
        }
        toggleTableHeader(pastiTable, pastiTableHeader);
    };

    const renderExtra = (rows = []) => {
        extraTable.innerHTML = '';
        if (rows.length) {
            rows.forEach((item) => {
                const rowItems = creaRigaExtra(item);
                rowItems.forEach((row) => extraTable.appendChild(row));
            });
        }
        toggleTableHeader(extraTable, extraTableHeader);
    };

    const resetFormData = (data, pastiRows = [], extraRows = [], camereRows = {}) => {
        form.querySelector('input[name="id"]').value = data.id ?? 0;
        document.getElementById('nomeGruppo').value = data.nome_gruppo ?? '';
        document.getElementById('referente').value = data.referente ?? '';
        document.getElementById('agenzia').value = data.agenzia ?? '';
        document.getElementById('telefono').value = data.telefono ?? '';
        document.getElementById('email').value = data.email ?? '';
        document.getElementById('dataArrivo').value = data.data_arrivo ?? '';
        document.getElementById('dataPartenza').value = data.data_partenza ?? '';
        document.getElementById('checkinOrario').value = data.checkin_orario ?? '';
        let adulti = data.numero_adulti ?? 0;
        let bambini = data.numero_bambini ?? 0;
        if (!adulti && !bambini && (data.numero_persone ?? 0) > 0) {
            adulti = data.numero_persone ?? 0;
        }
        document.getElementById('numeroAdulti').value = adulti;
        document.getElementById('numeroBambini').value = bambini;
        document.getElementById('numeroTotale').value = data.numero_persone ?? 0;
        const tipologiaFallback = document.getElementById('tipologiaCamere');
        if (tipologiaFallback) {
            tipologiaFallback.value = data.tipologia_camere ?? '';
        }
        const trattamento = document.getElementById('trattamento');
        if (trattamento) {
            trattamento.value = data.trattamento ?? '';
        }
        const areaSelect = document.getElementById('areaPreferita');
        const areaCheckboxes = Array.from(document.querySelectorAll('.area-riservata'));
        if (areaCheckboxes.length) {
            let selected = (data.aree_riservate || data.aree_riservate_json || []) ?? [];
            if (typeof selected === 'string') {
                try {
                    selected = JSON.parse(selected);
                } catch (error) {
                    selected = [];
                }
            }
            const selectedIds = Array.isArray(selected) ? selected.map((value) => String(value)) : [];
            areaCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectedIds.includes(checkbox.value);
            });
            const hiddenArea = document.getElementById('areaPreferitaHidden');
            if (hiddenArea) {
                hiddenArea.value = data.area_preferita ?? '';
            }
        } else if (areaSelect instanceof HTMLSelectElement && areaSelect.multiple) {
            let selected = (data.aree_riservate || data.aree_riservate_json || []) ?? [];
            if (typeof selected === 'string') {
                try {
                    selected = JSON.parse(selected);
                } catch (error) {
                    selected = [];
                }
            }
            const selectedIds = Array.isArray(selected) ? selected.map((value) => String(value)) : [];
            Array.from(areaSelect.options).forEach((option) => {
                option.selected = selectedIds.includes(option.value);
            });
            const hiddenArea = document.getElementById('areaPreferitaHidden');
            if (hiddenArea) {
                hiddenArea.value = data.area_preferita ?? '';
            }
        } else if (areaSelect) {
            areaSelect.value = data.area_preferita ?? '';
        }
        document.getElementById('noteRicevimento').value = data.note_ricevimento ?? '';
        document.getElementById('noteCucina').value = data.note_cucina ?? '';
        document.getElementById('noteHousekeeping').value = data.note_housekeeping ?? '';
        document.getElementById('noteManutenzione').value = data.note_manutenzione ?? '';
        document.querySelectorAll('.camera-qty').forEach((input) => {
            const code = input.dataset.code || '';
            input.value = camereRows[code] ?? 0;
        });
        renderPasti(pastiRows);
        renderExtra(extraRows);
        rinumeraRighe();
        aggiornaPreview();
    };

    document.getElementById('aggiungiPasto').addEventListener('click', () => {
        const rowItems = creaRigaPasto();
        rowItems.forEach((row) => pastiTable.appendChild(row));
        rinumeraRighe();
        aggiornaPreview();
    });

    document.getElementById('aggiungiExtra').addEventListener('click', () => {
        const rowItems = creaRigaExtra();
        rowItems.forEach((row) => extraTable.appendChild(row));
        rinumeraRighe();
        aggiornaPreview();
    });

    form.addEventListener('input', aggiornaPreview);

    resetFormData(currentData, storedPasti, storedExtra, storedCamere);

    const newSchedaBtn = document.getElementById('openNewScheda');
    newSchedaBtn?.addEventListener('click', () => {
        resetFormData(emptyData, [], [], {});
        const lookupInput = document.getElementById('gruppoLookup');
        if (lookupInput) lookupInput.value = '';
    });

    if (shouldShowModal && window.bootstrap) {
        const modalEl = document.getElementById('gruppoModal');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        modal?.show();
    }

    const gruppiById = new Map((gruppiArchivio || []).map((gruppo) => [String(gruppo.id), gruppo]));
    const lookupInput = document.getElementById('gruppoLookup');
    const caricaBtn = document.getElementById('caricaGruppo');

    const getGruppoSelezionato = () => {
        if (!lookupInput) return null;
        const value = lookupInput.value.trim();
        if (!value) return null;
        const option = Array.from(document.querySelectorAll('#gruppiList option'))
            .find((opt) => opt.value === value);
        const id = option?.dataset.id;
        return id ? gruppiById.get(String(id)) : null;
    };

    caricaBtn?.addEventListener('click', () => {
        const gruppo = getGruppoSelezionato();
        if (!gruppo) {
            alert('Seleziona un gruppo esistente dalla lista.');
            return;
        }
        resetFormData(gruppo, gruppo.pasti || [], gruppo.extra || [], gruppo.camere || {});
    });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
