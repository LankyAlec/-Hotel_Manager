<?php
require_once '../config/db.php';

$csrf = $_POST['csrf_token'] ?? '';
if (!csrf_validate((string)$csrf, 'forgot_form')) {
    header("Location: forgot.php?ok=1");
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot.php?ok=1");
    exit;
}

$token = bin2hex(random_bytes(32));
$scadenza = date('Y-m-d H:i:s', time() + 60*60); // 1 ora

$stmt = $mysqli->prepare("SELECT id, attivo FROM utenti WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if ($u && (int)$u['attivo'] === 1) {
    $stmt = $mysqli->prepare("UPDATE utenti SET reset_token=?, reset_scadenza=? WHERE id=?");
    $stmt->bind_param("ssi", $token, $scadenza, $u['id']);
    $stmt->execute();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $link = $scheme . '://' . $host . BASE_URL . "/auth/reset.php?token=" . urlencode($token);

    // error_log("RESET LINK per $email: $link");
}

header("Location: forgot.php?ok=1");
exit;
