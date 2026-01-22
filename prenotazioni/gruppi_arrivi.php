<?php
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

header("Location: " . BASE_URL . "/admin/gruppi_arrivi.php");
exit;
