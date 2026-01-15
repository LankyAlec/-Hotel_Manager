<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$tab = (string)($_GET['tab'] ?? 'richieste'); // richieste | tutti
$msg = (string)($_GET['msg'] ?? '');

if ($tab === 'tutti') {
    $sql = "SELECT id, username, email, nome, cognome, privilegi, attivo, richiesta_registrazione, ultimo_login, created_at
            FROM utenti
            ORDER BY created_at DESC";
} else {
    $sql = "SELECT id, username, email, nome, cognome, privilegi, attivo, richiesta_registrazione, ultimo_login, created_at
            FROM utenti
            WHERE richiesta_registrazione = 1 OR attivo = 0
            ORDER BY created_at DESC";
}

$res = $mysqli->query($sql);
$utentiList = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
if ($res) $res->free();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Amministrazione Utenti</h3>
    <div class="text-muted small">Solo root</div>
  </div>
</div>

<?php if ($msg === 'self_toggle_block'): ?>
  <div class="alert alert-warning">
    Non puoi disattivare il tuo stesso utente mentre sei loggato.
  </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab!=='tutti' ? 'active' : '' ?>" href="?tab=richieste">
      Richieste / Inattivi
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab==='tutti' ? 'active' : '' ?>" href="?tab=tutti">
      Tutti gli utenti
    </a>
  </li>
</ul>

<div class="card shadow-sm border-0">
  <div class="card-body">

    <?php if (empty($utentiList)): ?>
      <div class="alert alert-info mb-0">Nessun utente da mostrare.</div>
    <?php else: ?>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
          <tr>
            <th>Utente</th>
            <th>Email</th>
            <th>Privilegi</th>
            <th>Stato</th>
            <th class="text-end">Azioni</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($utentiList as $u): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h($u['username']) ?></div>
                <div class="text-muted small"><?= h(trim(($u['nome'] ?? '') . ' ' . ($u['cognome'] ?? ''))) ?></div>
              </td>
              <td><?= h($u['email']) ?></td>
              <td>
                <span class="badge bg-<?= ($u['privilegi']==='root' ? 'danger' : ($u['privilegi']==='guest' ? 'secondary' : 'primary')) ?>">
                  <?= h($u['privilegi']) ?>
                </span>
              </td>
              <td>
                <?php if ((int)$u['attivo'] === 1): ?>
                  <span class="badge bg-success">Attivo</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Disattivo</span>
                <?php endif; ?>

                <?php if ((int)$u['richiesta_registrazione'] === 1): ?>
                  <span class="badge bg-info text-dark ms-1">Richiesta</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a style="width:74px" class="btn btn-sm btn-outline-primary"
                   href="utente_edit.php?id=<?= (int)$u['id'] ?>">
                  Gestisci
                </a>

                <form class="d-inline" method="post" action="utente_save.php" onsubmit="return confirm('Confermi?');">
                  <input type="hidden" name="azione" value="toggle_attivo">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button style="width:74px" class="btn btn-sm btn-outline-<?= ((int)$u['attivo']===1 ? 'danger' : 'success') ?>">
                    <?= ((int)$u['attivo']===1 ? 'Disattiva' : 'Attiva') ?>
                  </button>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>