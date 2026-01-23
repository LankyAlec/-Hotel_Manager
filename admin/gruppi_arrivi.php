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
        numero_persone INT UNSIGNED NOT NULL DEFAULT 0,
        numero_adulti INT UNSIGNED NOT NULL DEFAULT 0,
        numero_bambini INT UNSIGNED NOT NULL DEFAULT 0,
        tipologia_camere VARCHAR(120) NULL,
        camere_json LONGTEXT NULL,
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
    'numero_persone' => 0,
    'numero_adulti' => 0,
    'numero_bambini' => 0,
    'tipologia_camere' => '',
    'camere_json' => '{}',
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

        if ($nomeGruppo === '' || $referente === '' || $agenzia === '' || $telefono === '' || $email === '') {
            $alertType = 'danger';
            $alert = 'Compila tutti i campi obbligatori prima di salvare la scheda.';
        } else {
            if ($currentId > 0) {
                $stmt = $mysqli->prepare("UPDATE gruppi_arrivi
                    SET nome_gruppo=?, referente=?, agenzia=?, telefono=?, email=?, data_arrivo=?, data_partenza=?,
                        numero_persone=?, numero_adulti=?, numero_bambini=?, tipologia_camere=?, camere_json=?, area_preferita=?,
                        trattamento=?, note_operativa=?, note_ricevimento=?, note_cucina=?, note_housekeeping=?, note_manutenzione=?,
                        pasti_json=?, extra_json=?
                    WHERE id=?");
                $stmt->bind_param(
                    "sssssssiiisssssssssssi",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $numeroPersone,
                    $numeroAdulti,
                    $numeroBambini,
                    $tipologiaCamere,
                    $camereJson,
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
                    (nome_gruppo, referente, agenzia, telefono, email, data_arrivo, data_partenza, numero_persone,
                     numero_adulti, numero_bambini, tipologia_camere, camere_json, area_preferita, trattamento,
                     note_operativa, note_ricevimento, note_cucina, note_housekeeping, note_manutenzione, pasti_json, extra_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "sssssssiiisssssssssss",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $numeroPersone,
                    $numeroAdulti,
                    $numeroBambini,
                    $tipologiaCamere,
                    $camereJson,
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

$gruppiArchivio = [];
$archivioRes = $mysqli->query("SELECT * FROM gruppi_arrivi ORDER BY nome_gruppo ASC");
if ($archivioRes instanceof mysqli_result) {
    while ($row = $archivioRes->fetch_assoc()) {
        $row['pasti'] = json_decode($row['pasti_json'] ?? '[]', true) ?: [];
        $row['extra'] = json_decode($row['extra_json'] ?? '[]', true) ?: [];
        $row['camere'] = json_decode($row['camere_json'] ?? '{}', true) ?: [];
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
                            <label class="form-label">Area riservata</label>
                            <input type="text" class="form-control" id="areaPreferita" name="area_preferita" value="<?= h($currentData['area_preferita']) ?>" placeholder="Es. 2° piano riservato">
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
                    <button class="btn btn-primary" type="button" id="generaPdf">
                        <i class="bi bi-filetype-pdf"></i> Genera PDF
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="position-absolute top-0 start-0 opacity-0" style="pointer-events: none; z-index: -1; width: 1000px;">
    <div id="schedaPreview" class="p-4 border rounded-4 bg-white">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="mb-1" id="previewNome">Nome gruppo</h3>
                <p class="text-muted mb-0" id="previewAgenzia">Agenzia / Ente</p>
            </div>
            <div class="text-end">
                <div class="fw-semibold" id="previewPeriodo">Arrivo - Partenza</div>
                <small class="text-muted" id="previewPartecipanti">0 partecipanti</small>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="text-uppercase text-muted">Referente</h6>
                <p class="mb-1" id="previewReferente">Nome referente</p>
                <p class="mb-1" id="previewTelefono">Telefono</p>
                <p class="mb-0" id="previewEmail">Email</p>
            </div>
            <div class="col-md-6">
                <h6 class="text-uppercase text-muted">Logistica camere</h6>
                <p class="mb-1" id="previewCamere">Tipologia camere</p>
                <p class="mb-1" id="previewTrattamento">Trattamento</p>
                <p class="mb-0" id="previewArea">Area riservata</p>
            </div>
        </div>

        <hr class="my-4">

        <div>
            <h6 class="text-uppercase text-muted">Pasti programmati</h6>
            <div class="table-responsive">
                <table class="table table-sm" id="previewPasti">
                    <thead>
                        <tr>
                            <th class="w-25">Data</th>
                            <th class="w-25">Voce</th>
                            <th class="w-15">Ora</th>
                            <th class="w-25">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-muted">Nessun pasto inserito.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            <h6 class="text-uppercase text-muted">Attività / extra</h6>
            <div class="table-responsive">
                <table class="table table-sm" id="previewExtra">
                    <thead>
                        <tr>
                            <th class="w-25">Data</th>
                            <th class="w-25">Voce</th>
                            <th class="w-15">Ora</th>
                            <th class="w-25">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-muted">Nessuna attività inserita.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-12">
                <h6 class="text-uppercase text-muted">Ricevimento</h6>
                <p class="mb-0" id="previewNoteRicevimento">Nessuna nota per il ricevimento.</p>
            </div>
            <div class="col-12">
                <h6 class="text-uppercase text-muted">Cucina/Ristorante</h6>
                <p class="mb-0" id="previewNoteCucina">Nessuna nota per cucina/ristorante.</p>
            </div>
            <div class="col-12">
                <h6 class="text-uppercase text-muted">Housekeeping</h6>
                <p class="mb-0" id="previewNoteHousekeeping">Nessuna nota per housekeeping.</p>
            </div>
            <div class="col-12">
                <h6 class="text-uppercase text-muted">Manutenzione</h6>
                <p class="mb-0" id="previewNoteManutenzione">Nessuna nota per manutenzione.</p>
            </div>
        </div>
    </div>
</div>

<div class="card table-card mt-4">
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

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
    const form = document.getElementById('gruppoForm');
    const preview = {
        nome: document.getElementById('previewNome'),
        agenzia: document.getElementById('previewAgenzia'),
        periodo: document.getElementById('previewPeriodo'),
        partecipanti: document.getElementById('previewPartecipanti'),
        referente: document.getElementById('previewReferente'),
        telefono: document.getElementById('previewTelefono'),
        email: document.getElementById('previewEmail'),
        camere: document.getElementById('previewCamere'),
        trattamento: document.getElementById('previewTrattamento'),
        area: document.getElementById('previewArea'),
        noteRicevimento: document.getElementById('previewNoteRicevimento'),
        noteCucina: document.getElementById('previewNoteCucina'),
        noteHousekeeping: document.getElementById('previewNoteHousekeeping'),
        noteManutenzione: document.getElementById('previewNoteManutenzione'),
        pasti: document.querySelector('#previewPasti tbody'),
        pastiHeader: document.querySelector('#previewPasti thead'),
        extra: document.querySelector('#previewExtra tbody'),
        extraHeader: document.querySelector('#previewExtra thead')
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

    const aggiornaPreview = () => {
        preview.nome.textContent = document.getElementById('nomeGruppo').value || 'Nome gruppo';
        preview.agenzia.textContent = document.getElementById('agenzia').value || 'Agenzia / Ente';
        const arrivo = document.getElementById('dataArrivo').value;
        const partenza = document.getElementById('dataPartenza').value;
        preview.periodo.textContent = arrivo && partenza ? `${arrivo} → ${partenza}` : 'Arrivo - Partenza';
        const adulti = parseInt(document.getElementById('numeroAdulti').value, 10) || 0;
        const bambini = parseInt(document.getElementById('numeroBambini').value, 10) || 0;
        const totale = adulti + bambini;
        document.getElementById('numeroTotale').value = totale;
        preview.partecipanti.textContent = totale ? `${adulti} adulti, ${bambini} bambini (Totale ${totale})` : '0 partecipanti';
        preview.referente.textContent = document.getElementById('referente').value || 'Nome referente';
        preview.telefono.textContent = document.getElementById('telefono').value || 'Telefono';
        preview.email.textContent = document.getElementById('email').value || 'Email';
        const camereSummary = buildCamereSummary();
        preview.camere.textContent = camereSummary || document.getElementById('tipologiaCamere')?.value || 'Tipologia camere';
        preview.trattamento.textContent = document.getElementById('trattamento').value || 'Trattamento';
        preview.area.textContent = document.getElementById('areaPreferita').value || 'Area riservata';
        preview.noteRicevimento.textContent = document.getElementById('noteRicevimento').value || 'Nessuna nota per il ricevimento.';
        preview.noteCucina.textContent = document.getElementById('noteCucina').value || 'Nessuna nota per cucina/ristorante.';
        preview.noteHousekeeping.textContent = document.getElementById('noteHousekeeping').value || 'Nessuna nota per housekeeping.';
        preview.noteManutenzione.textContent = document.getElementById('noteManutenzione').value || 'Nessuna nota per manutenzione.';

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

        preview.pasti.innerHTML = '';
        if (pastiRows.length === 0) {
            preview.pasti.innerHTML = '<tr><td colspan="4" class="text-muted">Nessun pasto inserito.</td></tr>';
        } else {
            pastiRows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${row.data}</td><td>${row.tipo}</td><td>${row.ora}</td><td>${row.note}</td>`;
                preview.pasti.appendChild(tr);
            });
        }
        if (preview.pastiHeader) {
            preview.pastiHeader.classList.toggle('d-none', pastiRows.length === 0);
        }

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

        preview.extra.innerHTML = '';
        if (extraRows.length === 0) {
            preview.extra.innerHTML = '<tr><td colspan="4" class="text-muted">Nessuna attività inserita.</td></tr>';
        } else {
            extraRows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${row.data}</td><td>${row.descrizione}</td><td>${row.ora}</td><td>${row.note}</td>`;
                preview.extra.appendChild(tr);
            });
        }
        if (preview.extraHeader) {
            preview.extraHeader.classList.toggle('d-none', extraRows.length === 0);
        }
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

    const buildCamereSummary = () => {
        const camereInputs = Array.from(document.querySelectorAll('.camera-qty'));
        const parts = camereInputs.map((input) => {
            const qty = parseInt(input.value, 10) || 0;
            if (!qty) return null;
            const code = input.dataset.code || 'Camera';
            return `${code}: ${qty}`;
        }).filter(Boolean);
        return parts.join(', ');
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
        document.getElementById('areaPreferita').value = data.area_preferita ?? '';
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

    document.getElementById('generaPdf').addEventListener('click', async () => {
        aggiornaPreview();
        const element = document.getElementById('schedaPreview');
        const canvas = await html2canvas(element, { scale: 2, backgroundColor: '#ffffff' });
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const imgProps = pdf.getImageProperties(imgData);
        const pdfWidth = pageWidth;
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
        let position = 0;

        if (pdfHeight <= pageHeight) {
            pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
        } else {
            let remainingHeight = pdfHeight;
            let canvasPosition = 0;
            while (remainingHeight > 0) {
                pdf.addImage(imgData, 'PNG', 0, canvasPosition, pdfWidth, pdfHeight);
                remainingHeight -= pageHeight;
                canvasPosition -= pageHeight;
                if (remainingHeight > 0) {
                    pdf.addPage();
                }
            }
        }

        const nome = document.getElementById('nomeGruppo').value || 'scheda-gruppo';
        pdf.save(`${nome.toLowerCase().replace(/\s+/g, '-')}.pdf`);
    });

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
