<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$flashOk = (string)($_SESSION['flash_ok'] ?? '');
$flashErr = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$rows = [];
$sql = "SELECT id, codice, descrizione, data_da, data_a, prezzo_solo_pernottamento, prezzo_BB, prezzo_HB, prezzo_FB
        FROM soggiorni_tariffe
        ORDER BY prezzo_BB ASC, data_da DESC";
$res = $mysqli->query($sql);
if ($res instanceof mysqli_result) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}

$fmtMoney = static function ($value): string {
    return number_format((float)$value, 2, ',', '.');
};

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Tariffe soggiorni</h3>
        <div class="text-muted small">Gestione listino camere e trattamenti (solo root)</div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2">
            <?= count($rows) ?> tariffe
        </span>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTariffaModal">
            <i class="bi bi-plus-circle"></i> Nuova tariffa
        </button>
    </div>
</div>

<?php if ($flashOk !== ''): ?>
    <div class="alert alert-success"><?= h($flashOk) ?></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
    <div class="alert alert-danger"><?= h($flashErr) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if (empty($rows)): ?>
            <div class="alert alert-info mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span>Nessuna tariffa presente nella tabella <code>soggiorni_tariffe</code>.</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTariffaModal">
                    <i class="bi bi-plus-circle"></i> Inserisci la prima tariffa
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>Data da</th>
                            <th>Data a</th>
                            <th class="text-end">Solo pern.</th>
                            <th class="text-end">BB</th>
                            <th class="text-end">HB</th>
                            <th class="text-end">FB</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="fw-semibold text-nowrap"><?= h((string)$r['codice']) ?></td>
                                <td><?= h((string)($r['descrizione'] ?? '')) ?></td>
                                <td class="text-nowrap"><?= h((string)$r['data_da']) ?></td>
                                <td class="text-nowrap">
                                    <?php if (!empty($r['data_a'])): ?>
                                        <?= h((string)$r['data_a']) ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Aperta</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= $fmtMoney($r['prezzo_solo_pernottamento']) ?></td>
                                <td class="text-end text-nowrap"><?= $fmtMoney($r['prezzo_BB']) ?></td>
                                <td class="text-end text-nowrap"><?= $fmtMoney($r['prezzo_HB']) ?></td>
                                <td class="text-end text-nowrap"><?= $fmtMoney($r['prezzo_FB']) ?></td>
                                <td class="text-end text-nowrap">
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editTariffaModal"
                                        data-id="<?= (int)$r['id'] ?>"
                                        data-codice="<?= h((string)$r['codice']) ?>"
                                        data-descrizione="<?= h((string)($r['descrizione'] ?? '')) ?>"
                                        data-data-da="<?= h((string)$r['data_da']) ?>"
                                        data-data-a="<?= h((string)($r['data_a'] ?? '')) ?>"
                                        data-prezzo-sp="<?= h((string)$r['prezzo_solo_pernottamento']) ?>"
                                        data-prezzo-bb="<?= h((string)$r['prezzo_BB']) ?>"
                                        data-prezzo-hb="<?= h((string)$r['prezzo_HB']) ?>"
                                        data-prezzo-fb="<?= h((string)$r['prezzo_FB']) ?>"
                                        title="Modifica"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <button
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteTariffaModal"
                                        data-id="<?= (int)$r['id'] ?>"
                                        data-label="<?= h((string)$r['codice']) ?>"
                                        title="Elimina"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Nuova tariffa -->
<div class="modal fade" id="addTariffaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="tariffe_save.php" class="needs-validation" novalidate>
                <input type="hidden" name="azione" value="insert">
                <div class="modal-header">
                    <h5 class="modal-title">Nuova tariffa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Codice *</label>
                            <input type="text" name="codice" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrizione</label>
                            <input type="text" name="descrizione" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data da *</label>
                            <input type="date" name="data_da" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data a</label>
                            <input type="date" name="data_a" class="form-control">
                            <div class="form-text">Vuoto = aperta</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Solo pernottamento *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_solo_pernottamento" class="form-control" required value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">BB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_BB" class="form-control" required value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">HB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_HB" class="form-control" required value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">FB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_FB" class="form-control" required value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Salva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Modifica tariffa -->
<div class="modal fade" id="editTariffaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="tariffe_save.php" class="needs-validation" novalidate>
                <input type="hidden" name="azione" value="update">
                <input type="hidden" name="tariffa_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica tariffa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Codice *</label>
                            <input type="text" name="codice" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrizione</label>
                            <input type="text" name="descrizione" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data da *</label>
                            <input type="date" name="data_da" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data a</label>
                            <input type="date" name="data_a" class="form-control">
                            <div class="form-text">Vuoto = aperta</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Solo pernottamento *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_solo_pernottamento" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">BB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_BB" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">HB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_HB" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">FB *</label>
                            <input type="number" min="0" step="0.01" name="prezzo_FB" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Aggiorna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Elimina tariffa -->
<div class="modal fade" id="deleteTariffaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="tariffe_save.php">
                <input type="hidden" name="azione" value="delete">
                <input type="hidden" name="tariffa_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma eliminazione</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Vuoi eliminare la tariffa <strong data-delete-label>—</strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button class="btn btn-danger"><i class="bi bi-trash"></i> Elimina</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
  const editModal = document.getElementById('editTariffaModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      editModal.querySelector('input[name="tariffa_id"]').value = button.getAttribute('data-id') || '';
      editModal.querySelector('input[name="codice"]').value = button.getAttribute('data-codice') || '';
      editModal.querySelector('input[name="descrizione"]').value = button.getAttribute('data-descrizione') || '';
      editModal.querySelector('input[name="data_da"]').value = button.getAttribute('data-data-da') || '';
      editModal.querySelector('input[name="data_a"]').value = button.getAttribute('data-data-a') || '';
      editModal.querySelector('input[name="prezzo_solo_pernottamento"]').value = button.getAttribute('data-prezzo-sp') || '0.00';
      editModal.querySelector('input[name="prezzo_BB"]').value = button.getAttribute('data-prezzo-bb') || '0.00';
      editModal.querySelector('input[name="prezzo_HB"]').value = button.getAttribute('data-prezzo-hb') || '0.00';
      editModal.querySelector('input[name="prezzo_FB"]').value = button.getAttribute('data-prezzo-fb') || '0.00';
    });
  }

  const deleteModal = document.getElementById('deleteTariffaModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      const id = button.getAttribute('data-id') || '';
      const label = button.getAttribute('data-label') || '—';

      const input = deleteModal.querySelector('input[name="tariffa_id"]');
      if (input) input.value = id;

      const strong = deleteModal.querySelector('[data-delete-label]');
      if (strong) strong.textContent = label;
    });
  }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>