<?php
require_once '../config/db.php';

$connection = $mysqli;
$token = $_POST['token'] ?? '';
$pass  = $_POST['password'] ?? '';
$csrf  = $_POST['csrf_token'] ?? '';

if (!csrf_validate((string)$csrf, 'reset_form')) {
    header("Location: reset.php?token=" . urlencode((string)$token) . "&err=" . urlencode('Sessione scaduta. Riprova.'));
    exit;
}

if ($token === '' || strlen($pass) < 8) {
    header("Location: reset.php?token=" . urlencode((string)$token) . "&err=" . urlencode("Password non valida (min 8)."));
    exit;
}

$token_esc = mysqli_real_escape_string($connection, $token);
$sql = "SELECT id, reset_scadenza FROM utenti WHERE reset_token='$token_esc' LIMIT 1";
$ris = mysqli_query($connection, $sql);
$u = $ris ? mysqli_fetch_assoc($ris) : null;

if (!$u) {
    http_response_code(400);
    exit('Token non valido.');
}
if (empty($u['reset_scadenza']) || strtotime((string)$u['reset_scadenza']) < time()) {
    http_response_code(400);
    exit('Token scaduto.');
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$hash_esc = mysqli_real_escape_string($connection, $hash);
$uid = (int)$u['id'];
$sqlUpd = "UPDATE utenti SET password_hash='$hash_esc', reset_token=NULL, reset_scadenza=NULL WHERE id=$uid";
mysqli_query($connection, $sqlUpd);

header("Location: login.php");
exit;
