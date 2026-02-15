<?php
require_once '../config/db.php';

$connection = $mysqli;
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
$scadenza = date('Y-m-d H:i:s', time() + 60*60);

$email_esc = mysqli_real_escape_string($connection, $email);
$sql = "SELECT id, attivo FROM utenti WHERE email='$email_esc' LIMIT 1";
$ris = mysqli_query($connection, $sql);
$u = $ris ? mysqli_fetch_assoc($ris) : null;

if ($u && (int)$u['attivo'] === 1) {
    $token_esc = mysqli_real_escape_string($connection, $token);
    $scadenza_esc = mysqli_real_escape_string($connection, $scadenza);
    $uid = (int)$u['id'];
    $sqlUpd = "UPDATE utenti SET reset_token='$token_esc', reset_scadenza='$scadenza_esc' WHERE id=$uid";
    mysqli_query($connection, $sqlUpd);

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $link = $scheme . '://' . $host . BASE_URL . "/auth/reset.php?token=" . urlencode($token);

    // error_log("RESET LINK per $email: $link");
}

header("Location: forgot.php?ok=1");
exit;
