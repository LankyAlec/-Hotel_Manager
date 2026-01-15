<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("ID non valido."); }

/* =========================
 * 1) UTENTE
 * ========================= */
$stmt = $mysqli->prepare("SELECT * FROM utenti WHERE id=? LIMIT 1");
if (!$stmt) die("prepare utenti failed: " . $mysqli->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$utente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$utente) { die("Utente non trovato."); }

/* =========================
 * 2) GRUPPI (tutti)
 * ========================= */
$res = $mysqli->query("SELECT id, codice, nome FROM utenti_gruppi ORDER BY nome ASC");
$allGroups = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
if ($res) $res->free();

/* =========================
 * 3) GRUPPI UTENTE (ponte)
 * ========================= */
$stmt = $mysqli->prepare("SELECT gruppo_id FROM utenti_privilegi WHERE utente_id=?");
if (!$stmt) die("prepare utenti_privilegi failed: " . $mysqli->error);
$stmt->bind_param("i", $id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userGroupMap = [];
foreach ($rows as $r) {
    $gid = (int)$r['gruppo_id'];
    $userGroupMap[$gid] = true;
}

/* =========================
 * 4) PERMESSI (tutti)
 * ========================= */
$res = $mysqli->query("SELECT id, codice, descrizione FROM permessi ORDER BY codice ASC");
$allPerms = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
if ($res) $res->free();


/* Ora possiamo includere header.php senza rischiare collisioni */
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Gestisci utente</h3>
    <div class="text-muted small"><?= h($utente['username']) ?> — <?= h($utente['email']) ?></div>
  </div>
  <a class="btn btn-outline-secondary" href="utenti.php">← Torna</a>
</div>

<form method="post" action="utente_save.php" class="row g-3">
  <input type="hidden" name="azione" value="salva_utente">
  <input type="hidden" name="id" value="<?= (int)$utente['id'] ?>">

  <div class="col-12 col-md-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <h5 class="mb-3">Dati principali</h5>

        <div class="mb-2">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" value="<?= h($utente['nome'] ?? '') ?>">
        </div>

        <div class="mb-2">
          <label class="form-label">Cognome</label>
          <input class="form-control" name="cognome" value="<?= h($utente['cognome'] ?? '') ?>">
        </div>

        <div class="mb-2">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" value="<?= h($utente['username'] ?? '') ?>" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= h($utente['email'] ?? '') ?>" required>
        </div>

        <div class="mb-2">
          <label class="form-label">Privilegi</label>
          <select class="form-select" name="privilegi">
            <option value="guest"    <?= (($utente['privilegi'] ?? '')==='guest' ? 'selected' : '') ?>>guest</option>
            <option value="standard" <?= (($utente['privilegi'] ?? '')==='standard' ? 'selected' : '') ?>>standard</option>
            <option value="root"     <?= (($utente['privilegi'] ?? '')==='root' ? 'selected' : '') ?>>root</option>
          </select>
          <div class="form-text">Solo un root può promuovere a root.</div>
        </div>

        <div class="form-check form-switch mt-3">
          <input class="form-check-input" type="checkbox" role="switch" id="attivoSwitch" name="attivo"
                 value="1" <?= ((int)($utente['attivo'] ?? 0)===1 ? 'checked' : '') ?>>
          <label class="form-check-label" for="attivoSwitch">Utente attivo</label>
        </div>

        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" role="switch" id="richiestaSwitch" name="richiesta_registrazione"
                 value="1" <?= ((int)($utente['richiesta_registrazione'] ?? 0)===1 ? 'checked' : '') ?>>
          <label class="form-check-label" for="richiestaSwitch">Flag “richiesta registrazione”</label>
        </div>

        <hr>

        <div class="mb-2">
          <label class="form-label">Nuova password (opzionale)</label>
          <input class="form-control" type="password" name="nuova_password" minlength="8" placeholder="Lascia vuoto per non cambiare">
          <div class="form-text">Minimo 8 caratteri.</div>
        </div>

        <button class="btn btn-primary w-100 mt-3">Salva</button>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">

    <div class="card shadow-sm border-0 mb-3">
      <div class="card-body">
        <h5 class="mb-3">Gruppi (multi-selezione)</h5>

        <?php if (empty($allGroups)): ?>
          <div class="alert alert-warning mb-0">Nessun gruppo trovato in utenti_gruppi.</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach ($allGroups as $g): $gid = (int)$g['id']; ?>
              <div class="col-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox"
                         name="gruppi[]" value="<?= $gid ?>"
                         id="g<?= $gid ?>"
                         <?= isset($userGroupMap[$gid]) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="g<?= $gid ?>">
                    <?= h($g['nome']) ?>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text mt-2">
            I gruppi servono per menu e permessi “di default”.
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>