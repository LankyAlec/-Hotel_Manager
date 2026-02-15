<?php
require_once '../config/db.php';

$connection = $mysqli;
$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$csrf     = $_POST['csrf_token'] ?? '';

if (!csrf_validate((string)$csrf, 'login_form')) {
    header("Location: login.php?error=1");
    exit;
}

if ($login === '' || $password === '') {
    header("Location: login.php?error=1");
    exit;
}

if (!($connection instanceof mysqli)) {
    error_log('Login: connessione DB non inizializzata');
    header("Location: login.php?error=1");
    exit;
}

$login_esc = mysqli_real_escape_string($connection, $login);
$sql = "SELECT id, username, email, password_hash, privilegi, attivo
        FROM utenti
        WHERE username = '$login_esc' OR email = '$login_esc'
        LIMIT 1";
$ris = mysqli_query($connection, $sql);
$utente = $ris ? mysqli_fetch_assoc($ris) : null;

if (!$utente || !(int)$utente['attivo'] || !password_verify($password, (string)$utente['password_hash'])) {
    header("Location: login.php?error=1");
    exit;
}

session_regenerate_id(true);
$_SESSION['utente_id']  = (int)$utente['id'];
$_SESSION['username']   = (string)$utente['username'];
$_SESSION['email']      = (string)$utente['email'];
$_SESSION['privilegi']  = (string)$utente['privilegi'];
unset($_SESSION['gruppi']);

$uid = (int)$utente['id'];
$sqlUpd = "UPDATE utenti SET ultimo_login = NOW() WHERE id = $uid";
mysqli_query($connection, $sqlUpd);

header("Location: ../dashboard.php");
exit;
