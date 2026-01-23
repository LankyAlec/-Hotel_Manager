<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';

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
        tipologia_camere VARCHAR(120) NULL,
        area_preferita VARCHAR(120) NULL,
        note_operativa TEXT NULL,
        pasti_json LONGTEXT NULL,
        extra_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gruppi_arrivi_nome (nome_gruppo),
        INDEX idx_gruppi_arrivi_data_arrivo (data_arrivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    'tipologia_camere' => '',
    'area_preferita' => '',
    'note_operativa' => '',
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
        $numeroPersone = (int)($_POST['numero_persone'] ?? 0);
        $tipologiaCamere = trim($_POST['tipologia_camere'] ?? '');
        $areaPreferita = trim($_POST['area_preferita'] ?? '');
        $noteOperativa = trim($_POST['note_operativa'] ?? '');
        $pasti = $_POST['pasti'] ?? [];
        $extra = $_POST['extra'] ?? [];
        $pastiJson = json_encode(array_values($pasti), JSON_UNESCAPED_UNICODE);
        $extraJson = json_encode(array_values($extra), JSON_UNESCAPED_UNICODE);

        if ($nomeGruppo === '' || $referente === '' || $agenzia === '' || $telefono === '' || $email === '') {
            $alertType = 'danger';
            $alert = 'Compila tutti i campi obbligatori prima di salvare la scheda.';
        } else {
            if ($currentId > 0) {
                $stmt = $mysqli->prepare("UPDATE gruppi_arrivi
                    SET nome_gruppo=?, referente=?, agenzia=?, telefono=?, email=?, data_arrivo=?, data_partenza=?,
                        numero_persone=?, tipologia_camere=?, area_preferita=?, note_operativa=?, pasti_json=?, extra_json=?
                    WHERE id=?");
                $stmt->bind_param(
                    "sssssssisssssi",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $numeroPersone,
                    $tipologiaCamere,
                    $areaPreferita,
                    $noteOperativa,
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
                     tipologia_camere, area_preferita, note_operativa, pasti_json, extra_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "sssssssisssss",
                    $nomeGruppo,
                    $referente,
                    $agenzia,
                    $telefono,
                    $email,
                    $dataArrivo,
                    $dataPartenza,
                    $numeroPersone,
                    $tipologiaCamere,
                    $areaPreferita,
                    $noteOperativa,
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

    $stmt = $mysqli->prepare("SELECT id, nome_gruppo, referente, data_arrivo, data_partenza, numero_persone
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

    $stmt = $mysqli->prepare("SELECT id, nome_gruppo, referente, data_arrivo, data_partenza, numero_persone
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
?>

<?php if ($alert): ?>
<div class="alert alert-<?= h($alertType) ?>">
    <?= h($alert) ?>
</div>
<?php endif; ?>

<div class="card table-card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h4 class="mb-1">Scheda gruppi in arrivo</h4>
                <p class="text-muted mb-0">Cerca rapidamente, gestisci le schede e crea una nuova registrazione.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form class="d-flex gap-2" method="get">
                    <input type="text" class="form-control" name="search" placeholder="Cerca gruppo, referente o agenzia" value="<?= h($search) ?>">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Cerca</button>
                </form>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/gruppi_arrivi.php?open=1">
                    <i class="bi bi-plus-circle"></i> Nuova scheda
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="collapse <?= $shouldShowForm ? 'show' : '' ?>" id="gruppoFormCollapse">
            <div class="card toolbar-card mb-3 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h4 class="mb-1">Scheda gruppo in arrivo</h4>
                            <p class="text-muted mb-0">Compila tutti i dati manualmente, salva la scheda e genera il PDF in un click.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-soft"><i class="bi bi-stars"></i> Smart</span>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gruppoFormCollapse" aria-expanded="<?= $shouldShowForm ? 'true' : 'false' ?>">
                                <i class="bi bi-chevron-up"></i> Chiudi scheda
                            </button>
                        </div>
                    </div>

                    <form id="gruppoForm" class="vstack gap-3" method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$currentId ?>">
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
                            <div class="col-md-6">
                                <label class="form-label">Arrivo</label>
                                <input type="date" class="form-control" id="dataArrivo" name="data_arrivo" value="<?= h($currentData['data_arrivo']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Partenza</label>
                                <input type="date" class="form-control" id="dataPartenza" name="data_partenza" value="<?= h($currentData['data_partenza']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Numero persone</label>
                                <input type="number" class="form-control" id="numeroPersone" name="numero_persone" min="1" value="<?= (int)$currentData['numero_persone'] ?>" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipologia camere</label>
                                <input type="text" class="form-control" id="tipologiaCamere" name="tipologia_camere" value="<?= h($currentData['tipologia_camere']) ?>" placeholder="Es. 10 doppie + 2 singole">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Piano / Area preferita</label>
                                <input type="text" class="form-control" id="areaPreferita" name="area_preferita" value="<?= h($currentData['area_preferita']) ?>" placeholder="Es. 2° piano - vista lago">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note operative</label>
                                <textarea class="form-control" id="noteOperative" name="note_operativa" rows="3" placeholder="Richieste speciali, timing check-in, ecc."><?= h($currentData['note_operativa']) ?></textarea>
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
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Ora</th>
                                        <th>Note</th>
                                        <th></th>
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
                                        <th>Data</th>
                                        <th>Descrizione</th>
                                        <th>Orario</th>
                                        <th>Note</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-success" type="submit">
                            <i class="bi bi-save"></i> Salva scheda
                        </button>
                        <button class="btn btn-primary" type="button" id="generaPdf">
                            <i class="bi bi-filetype-pdf"></i> Genera PDF
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/admin/gruppi_arrivi.php">
                            <i class="bi bi-arrow-counterclockwise"></i> Nuova scheda
                        </a>
                    </div>
                    </form>
                </div>
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
                <p class="mb-0" id="previewArea">Area preferita</p>
            </div>
            <div class="col-12">
                <h6 class="text-uppercase text-muted">Note operative</h6>
                <p class="mb-0" id="previewNote">Inserisci eventuali note operative.</p>
            </div>
        </div>

        <hr class="my-4">

        <div>
            <h6 class="text-uppercase text-muted">Pasti programmati</h6>
            <div class="table-responsive">
                <table class="table table-sm" id="previewPasti">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Ora</th>
                            <th>Note</th>
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
                            <th>Data</th>
                            <th>Descrizione</th>
                            <th>Orario</th>
                            <th>Note</th>
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
    </div>
</div>

<div class="card table-card mt-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-1">Schede salvate</h5>
                <p class="text-muted mb-0">Elenco ordinato per data di arrivo (decrescente).</p>
            </div>
            <span class="badge badge-soft"><i class="bi bi-calendar-event"></i> <?= (int)$totalRecords ?> schede</span>
        </div>

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
                    ?>
                    <div class="border rounded-4 p-3 d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold fs-5"><?= h($record['nome_gruppo']) ?></div>
                            <div class="text-muted">Referente: <?= h($record['referente']) ?></div>
                            <div class="text-muted">Periodo: <?= h($periodo) ?></div>
                            <div class="text-muted">Partecipanti: <?= (int)$record['numero_persone'] ?></div>
                            <div class="text-muted small">ID #<?= (int)$record['id'] ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-start justify-content-md-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/gruppi_arrivi.php?id=<?= (int)$record['id'] ?>">
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
        area: document.getElementById('previewArea'),
        note: document.getElementById('previewNote'),
        pasti: document.querySelector('#previewPasti tbody'),
        extra: document.querySelector('#previewExtra tbody')
    };

    const pastiTable = document.querySelector('#pastiTable tbody');
    const extraTable = document.querySelector('#extraTable tbody');

    const storedPasti = <?= json_encode($pastiData, JSON_UNESCAPED_UNICODE) ?>;
    const storedExtra = <?= json_encode($extraData, JSON_UNESCAPED_UNICODE) ?>;

    const creaRigaPasto = (data = {}) => {
        const row = document.createElement('tr');
        row.innerHTML = `
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
            <td><input type="text" class="form-control form-control-sm" value="${data.note || ''}" placeholder="Allergie, menù" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            rinumeraRighe();
            aggiornaPreview();
        });
        row.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return row;
    };

    const creaRigaExtra = (data = {}) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="date" class="form-control form-control-sm" value="${data.data || ''}" required></td>
            <td><input type="text" class="form-control form-control-sm" value="${data.descrizione || ''}" placeholder="Visita guidata, sala meeting" required></td>
            <td><input type="time" class="form-control form-control-sm" value="${data.ora || ''}" required></td>
            <td><input type="text" class="form-control form-control-sm" value="${data.note || ''}" placeholder="Referente, note" required></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
            </td>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            rinumeraRighe();
            aggiornaPreview();
        });
        row.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', aggiornaPreview);
        });
        return row;
    };

    const rinumeraRighe = () => {
        Array.from(pastiTable.querySelectorAll('tr')).forEach((row, index) => {
            const inputs = row.querySelectorAll('input, select');
            inputs[0].name = `pasti[${index}][data]`;
            inputs[1].name = `pasti[${index}][tipo]`;
            inputs[2].name = `pasti[${index}][ora]`;
            inputs[3].name = `pasti[${index}][note]`;
        });
        Array.from(extraTable.querySelectorAll('tr')).forEach((row, index) => {
            const inputs = row.querySelectorAll('input');
            inputs[0].name = `extra[${index}][data]`;
            inputs[1].name = `extra[${index}][descrizione]`;
            inputs[2].name = `extra[${index}][ora]`;
            inputs[3].name = `extra[${index}][note]`;
        });
    };

    const aggiornaPreview = () => {
        preview.nome.textContent = document.getElementById('nomeGruppo').value || 'Nome gruppo';
        preview.agenzia.textContent = document.getElementById('agenzia').value || 'Agenzia / Ente';
        const arrivo = document.getElementById('dataArrivo').value;
        const partenza = document.getElementById('dataPartenza').value;
        preview.periodo.textContent = arrivo && partenza ? `${arrivo} → ${partenza}` : 'Arrivo - Partenza';
        const persone = document.getElementById('numeroPersone').value;
        preview.partecipanti.textContent = persone ? `${persone} partecipanti` : '0 partecipanti';
        preview.referente.textContent = document.getElementById('referente').value || 'Nome referente';
        preview.telefono.textContent = document.getElementById('telefono').value || 'Telefono';
        preview.email.textContent = document.getElementById('email').value || 'Email';
        preview.camere.textContent = document.getElementById('tipologiaCamere').value || 'Tipologia camere';
        preview.area.textContent = document.getElementById('areaPreferita').value || 'Area preferita';
        preview.note.textContent = document.getElementById('noteOperative').value || 'Inserisci eventuali note operative.';

        const pastiRows = Array.from(pastiTable.querySelectorAll('tr')).map((row) => {
            const inputs = row.querySelectorAll('input, select');
            return {
                data: inputs[0].value,
                tipo: inputs[1].value,
                ora: inputs[2].value,
                note: inputs[3].value
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

        const extraRows = Array.from(extraTable.querySelectorAll('tr')).map((row) => {
            const inputs = row.querySelectorAll('input');
            return {
                data: inputs[0].value,
                descrizione: inputs[1].value,
                ora: inputs[2].value,
                note: inputs[3].value
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
    };

    document.getElementById('aggiungiPasto').addEventListener('click', () => {
        pastiTable.appendChild(creaRigaPasto());
        rinumeraRighe();
        aggiornaPreview();
    });

    document.getElementById('aggiungiExtra').addEventListener('click', () => {
        extraTable.appendChild(creaRigaExtra());
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

    if (storedPasti.length) {
        storedPasti.forEach((item) => {
            pastiTable.appendChild(creaRigaPasto(item));
        });
    } else {
        pastiTable.appendChild(creaRigaPasto());
    }

    if (storedExtra.length) {
        storedExtra.forEach((item) => {
            extraTable.appendChild(creaRigaExtra(item));
        });
    }

    rinumeraRighe();
    aggiornaPreview();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
