<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

function ensure_turni_personale_table(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS personale_turni (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        utente_id INT UNSIGNED NOT NULL,
        data_turno DATE NOT NULL,
        ora_inizio TIME NOT NULL,
        ora_fine TIME NOT NULL,
        ruolo VARCHAR(100) NULL,
        note TEXT NULL,
        creato_da INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_turni_data (data_turno),
        INDEX idx_turni_utente_data (utente_id, data_turno)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function parse_week_start(string $value): DateTimeImmutable
{
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    return new DateTimeImmutable('monday this week');
}

ensure_turni_personale_table($mysqli);

$weekInput = (string)($_GET['week'] ?? $_POST['week'] ?? '');
$weekStart = parse_week_start($weekInput)->setTime(0, 0, 0);
$weekEnd = $weekStart->modify('+6 days');

$alert = null;
$alertType = 'success';
$editTurno = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $utenteId = (int)($_POST['utente_id'] ?? 0);
        $dataTurno = trim((string)($_POST['data_turno'] ?? ''));
        $oraInizio = trim((string)($_POST['ora_inizio'] ?? ''));
        $oraFine = trim((string)($_POST['ora_fine'] ?? ''));
        $ruolo = trim((string)($_POST['ruolo'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        $isDateValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataTurno) === 1;
        $isStartValid = preg_match('/^\d{2}:\d{2}$/', $oraInizio) === 1;
        $isEndValid = preg_match('/^\d{2}:\d{2}$/', $oraFine) === 1;

        if ($utenteId <= 0 || !$isDateValid || !$isStartValid || !$isEndValid) {
            $alertType = 'danger';
            $alert = 'Compila correttamente dipendente, data e orari del turno.';
        } elseif ($oraFine <= $oraInizio) {
            $alertType = 'danger';
            $alert = 'L\'orario di fine deve essere successivo all\'orario di inizio.';
        } else {
            if ($id > 0) {
                $stmt = $mysqli->prepare('UPDATE personale_turni SET utente_id=?, data_turno=?, ora_inizio=?, ora_fine=?, ruolo=?, note=? WHERE id=?');
                if ($stmt) {
                    $stmt->bind_param('isssssi', $utenteId, $dataTurno, $oraInizio, $oraFine, $ruolo, $note, $id);
                    $stmt->execute();
                    $stmt->close();
                    $alert = 'Turno aggiornato con successo.';
                } else {
                    $alertType = 'danger';
                    $alert = 'Errore durante l\'aggiornamento del turno.';
                }
            } else {
                $creatorId = (int)($_SESSION['utente_id'] ?? 0);
                $stmt = $mysqli->prepare('INSERT INTO personale_turni (utente_id, data_turno, ora_inizio, ora_fine, ruolo, note, creato_da) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('isssssi', $utenteId, $dataTurno, $oraInizio, $oraFine, $ruolo, $note, $creatorId);
                    $stmt->execute();
                    $stmt->close();
                    $alert = 'Turno inserito con successo.';
                } else {
                    $alertType = 'danger';
                    $alert = 'Errore durante l\'inserimento del turno.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $mysqli->prepare('DELETE FROM personale_turni WHERE id=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $alert = 'Turno eliminato.';
            }
        }
    }
}

$staffRes = $mysqli->query("SELECT id, username, nome, cognome
    FROM utenti
    WHERE attivo = 1
    ORDER BY cognome ASC, nome ASC, username ASC");
$staffList = $staffRes instanceof mysqli_result ? $staffRes->fetch_all(MYSQLI_ASSOC) : [];
if ($staffRes instanceof mysqli_result) {
    $staffRes->free();
}

$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $mysqli->prepare('SELECT id, utente_id, data_turno, ora_inizio, ora_fine, ruolo, note FROM personale_turni WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $resEdit = $stmt->get_result();
        $editTurno = $resEdit ? $resEdit->fetch_assoc() : null;
        if ($resEdit) {
            $resEdit->free();
        }
        $stmt->close();
    }
}

$stmtWeek = $mysqli->prepare("SELECT t.id, t.utente_id, t.data_turno, t.ora_inizio, t.ora_fine, t.ruolo, t.note,
        COALESCE(NULLIF(TRIM(CONCAT(IFNULL(u.cognome,''), ' ', IFNULL(u.nome,''))), ''), u.username) AS personale
    FROM personale_turni t
    LEFT JOIN utenti u ON u.id = t.utente_id
    WHERE t.data_turno BETWEEN ? AND ?
    ORDER BY t.data_turno ASC, t.ora_inizio ASC, personale ASC");
$turni = [];
if ($stmtWeek) {
    $startSql = $weekStart->format('Y-m-d');
    $endSql = $weekEnd->format('Y-m-d');
    $stmtWeek->bind_param('ss', $startSql, $endSql);
    $stmtWeek->execute();
    $resTurni = $stmtWeek->get_result();
    $turni = $resTurni ? $resTurni->fetch_all(MYSQLI_ASSOC) : [];
    if ($resTurni) {
        $resTurni->free();
    }
    $stmtWeek->close();
}

$formData = [
    'id' => (int)($editTurno['id'] ?? 0),
    'utente_id' => (int)($editTurno['utente_id'] ?? 0),
    'data_turno' => (string)($editTurno['data_turno'] ?? $weekStart->format('Y-m-d')),
    'ora_inizio' => substr((string)($editTurno['ora_inizio'] ?? '09:00:00'), 0, 5),
    'ora_fine' => substr((string)($editTurno['ora_fine'] ?? '17:00:00'), 0, 5),
    'ruolo' => (string)($editTurno['ruolo'] ?? ''),
    'note' => (string)($editTurno['note'] ?? '')
];

$weekPrev = $weekStart->modify('-7 days')->format('Y-m-d');
$weekNext = $weekStart->modify('+7 days')->format('Y-m-d');
$dayNames = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica'];

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Turni personale</h3>
        <div class="text-muted small">Pianificazione settimanale del personale</div>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline-secondary" href="?week=<?= h($weekPrev) ?>">&laquo; Settimana precedente</a>
        <a class="btn btn-outline-secondary" href="?week=<?= h((new DateTimeImmutable('monday this week'))->format('Y-m-d')) ?>">Settimana corrente</a>
        <a class="btn btn-outline-secondary" href="?week=<?= h($weekNext) ?>">Settimana successiva &raquo;</a>
    </div>
</div>

<div class="alert alert-info">
    Settimana dal <strong><?= h($weekStart->format('d/m/Y')) ?></strong> al <strong><?= h($weekEnd->format('d/m/Y')) ?></strong>.
</div>

<?php if ($alert !== null): ?>
    <div class="alert alert-<?= h($alertType) ?>"><?= h($alert) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><?= $formData['id'] > 0 ? 'Modifica turno' : 'Nuovo turno' ?></h5>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$formData['id'] ?>">
                    <input type="hidden" name="week" value="<?= h($weekStart->format('Y-m-d')) ?>">

                    <div class="mb-3">
                        <label class="form-label">Dipendente</label>
                        <select class="form-select" name="utente_id" required>
                            <option value="">Seleziona</option>
                            <?php foreach ($staffList as $person): ?>
                                <?php $label = trim(($person['cognome'] ?? '') . ' ' . ($person['nome'] ?? '')); ?>
                                <option value="<?= (int)$person['id'] ?>" <?= (int)$formData['utente_id'] === (int)$person['id'] ? 'selected' : '' ?>>
                                    <?= h($label !== '' ? $label : ($person['username'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input class="form-control" type="date" name="data_turno" value="<?= h($formData['data_turno']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Inizio</label>
                            <input class="form-control" type="time" name="ora_inizio" value="<?= h($formData['ora_inizio']) ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fine</label>
                            <input class="form-control" type="time" name="ora_fine" value="<?= h($formData['ora_fine']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ruolo / reparto</label>
                        <input class="form-control" type="text" name="ruolo" value="<?= h($formData['ruolo']) ?>" placeholder="es. Ricevimento, Sala, Housekeeping">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="3" placeholder="Eventuali note operative"><?= h($formData['note']) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Salva turno</button>
                        <?php if ($formData['id'] > 0): ?>
                            <a class="btn btn-outline-secondary" href="?week=<?= h($weekStart->format('Y-m-d')) ?>">Annulla modifica</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Turni programmati</h5>
                <?php if (empty($turni)): ?>
                    <div class="alert alert-light border mb-0">Nessun turno inserito per questa settimana.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Giorno</th>
                                <th>Personale</th>
                                <th>Orario</th>
                                <th>Ruolo</th>
                                <th>Note</th>
                                <th class="text-end">Azioni</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($turni as $turno): ?>
                                <?php $dayNum = (int)(new DateTimeImmutable((string)$turno['data_turno']))->format('N'); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h($dayNames[$dayNum] ?? '') ?></div>
                                        <div class="small text-muted"><?= h((new DateTimeImmutable((string)$turno['data_turno']))->format('d/m/Y')) ?></div>
                                    </td>
                                    <td><?= h($turno['personale'] ?? '-') ?></td>
                                    <td><?= h(substr((string)$turno['ora_inizio'], 0, 5)) ?> - <?= h(substr((string)$turno['ora_fine'], 0, 5)) ?></td>
                                    <td><?= h((string)($turno['ruolo'] ?? '')) ?></td>
                                    <td><?= h((string)($turno['note'] ?? '')) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="?week=<?= h($weekStart->format('Y-m-d')) ?>&edit=<?= (int)$turno['id'] ?>">Modifica</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Eliminare il turno selezionato?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$turno['id'] ?>">
                                            <input type="hidden" name="week" value="<?= h($weekStart->format('Y-m-d')) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Elimina</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
