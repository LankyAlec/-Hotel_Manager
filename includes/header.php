<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['utente_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

/* -------------------------------------------------
   Gruppi utente (cache in sessione)
------------------------------------------------- */
if (!isset($_SESSION['gruppi'])) {
    $_SESSION['gruppi'] = [];
    $stmt = $mysqli->prepare("
        SELECT g.codice
        FROM utenti_gruppi g
        JOIN utenti_privilegi up ON up.gruppo_id = g.id
        WHERE up.utente_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['utente_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $_SESSION['gruppi'][] = $r['codice'];
    }
}

$isRoot = (($_SESSION['privilegi'] ?? '') === 'root');
$gruppi = $_SESSION['gruppi'];

function in_gruppo($codice){
    return in_array($codice, $_SESSION['gruppi'] ?? [], true);
}
?>

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title><?= APP_NAME ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color:#f8f9fa; }
        .navbar-brand { font-weight:600; }
        .nav-section-title {
            font-size:.75rem;
            text-transform:uppercase;
            opacity:.7;
        }
        .toolbar-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 25px rgba(0,0,0,.08); }
        .table-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 25px rgba(0,0,0,.08); overflow: hidden; }
        .badge-soft { background: rgba(13,110,253,.1); color: #0d6efd; }
        .meta-right { color:#6c757d; font-size:.95rem; }
        .btn-icon { display:inline-flex; align-items:center; gap:.4rem; }
        .pagination .page-link { border-radius:.8rem; }
        .of-recbar { padding: .25rem .25rem; border-radius: 999px; }
        .of-badge{
          background:#0d6efd; color:#fff; border-radius:999px;
          padding:.45rem .75rem; font-weight:600;
        }
        .of-csvbtn{
          border-radius:999px;
          border:1px solid rgba(13,110,253,.35);
          background:#fff;
          padding:.35rem .55rem;
          line-height:1;
        }
        .of-csvbtn:hover{ background:rgba(13,110,253,.06); }
        .app-footer {
            background: #fff;
            border-top: 1px solid rgba(0,0,0,.08);
            box-shadow: 0 -6px 18px rgba(0,0,0,.04);
        }
        .app-footer .footer-brand { font-weight: 600; }
        .app-footer .footer-pill {
            background: #f1f3f5;
            border-radius: 999px;
            padding: .3rem .75rem;
        }
        .app-footer .footer-link {
            color: #6c757d;
            text-decoration: none;
        }
        .app-footer .footer-link:hover { color: #0d6efd; }
    </style>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">

        <!-- BRAND -->
        <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard.php">
            <i class="bi bi-building"></i> <?= APP_NAME ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <!-- MENU SINISTRA -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- DASHBOARD -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                <!-- PRENOTAZIONI -->
                <?php if ($isRoot || in_gruppo('Reception')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar-check"></i> Prenotazioni
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/prenotazioni/calendario.php">Calendario prenotazioni</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/prenotazioni/calendario.php">Calendario</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/prenotazioni/clienti.php">Clienti</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- PULIZIE -->
                <?php if ($isRoot || in_gruppo('Pulizia')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/pulizie/pulizie.php">
                        <i class="bi bi-bucket"></i> Pulizie
                    </a>
                </li>
                <?php endif; ?>

                <!-- MANUTENZIONE -->
                <?php if ($isRoot || in_gruppo('Manutenzione')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/manutenzione/tickets.php">
                        <i class="bi bi-tools"></i> Manutenzione
                    </a>
                </li>
                <?php endif; ?>

                <!-- RISTORANTE -->
                <?php if ($isRoot || in_gruppo('Ristorante')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-cup-hot"></i> Ristorante
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/tavoli.php">Tavoli</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/ordini.php">Ordini</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/ristorante/menu.php">Menu</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- MAGAZZINO -->
                <?php if ($isRoot || in_gruppo('Magazzino')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/magazzino/magazzino.php">
                        <i class="bi bi-box-seam"></i> Magazzino
                    </a>
                </li>
                <?php endif; ?>

                <!-- AMMINISTRAZIONE -->
                <?php if ($isRoot): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock"></i> Amministrazione
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/tariffe.php">Tariffe</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/servizi/servizi.php">Servizi</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/preventivi/preventivi.php">Preventivi</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/struttura/struttura.php">Struttura</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/magazzino/gestione_magazzini.php">Magazzini</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/gruppi_arrivi.php">Scheda gruppi in arrivo</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/utenti.php">Utenti</a></li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <!-- MENU DESTRA -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= ucfirst(h($_SESSION['username'])) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">
                            <?= h($_SESSION['email'] ?? '') ?>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/profilo.php">
                                <i class="bi bi-person"></i> Profilo
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>

        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
