<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_root();

$servizio_id = (int)($_POST['servizio_id'] ?? 0);
$tariffa_id  = (int)($_POST['tariffa_id'] ?? 0);

if ($servizio_id <= 0 || $tariffa_id <= 0) die("Dati non validi.");

$sql = "DELETE FROM servizi_tariffe WHERE id=" . (int)$tariffa_id . " AND servizio_id=" . (int)$servizio_id;
mysqli_query($mysqli, $sql);

header("Location: servizio_prezzi.php?id=$servizio_id&del=1");
exit;
