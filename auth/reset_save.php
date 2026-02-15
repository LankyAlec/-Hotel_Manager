<?php
require_once '../config/db.php';

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

$stmt = $mysqli->prepare("SELECT id, reset_scadenza FROM utenti WHERE reset_token=? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if (!$u) {
    http_response_code(400);
    exit('Token non valido.');
}
if (empty($u['reset_scadenza']) || strtotime((string)$u['reset_scadenza']) < time()) {
    http_response_code(400);
    exit('Token scaduto.');
}

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("UPDATE utenti SET password_hash=?, reset_token=NULL, reset_scadenza=NULL WHERE id=?");
$stmt->bind_param("si", $hash, $u['id']);
$stmt->execute();

header("Location: login.php");
exit;
