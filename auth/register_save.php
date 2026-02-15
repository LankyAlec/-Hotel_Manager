<?php
require_once '../config/db.php';

$connection = $mysqli;

function back_err($msg){
    header("Location: register.php?err=" . urlencode($msg));
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!csrf_validate((string)$csrf, 'register_form')) {
    back_err('Sessione scaduta. Riprova.');
}

$nome     = trim($_POST['nome'] ?? '');
$cognome  = trim($_POST['cognome'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$pass     = $_POST['password'] ?? '';

if ($nome==='' || $cognome==='' || $username==='' || $email==='' || $pass==='') {
    back_err("Compila tutti i campi.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_err("Email non valida.");
}
if (strlen($pass) < 8) {
    back_err("La password deve avere almeno 8 caratteri.");
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$token = bin2hex(random_bytes(32));
$scadenza = date('Y-m-d H:i:s', time() + 48*3600);

$nome_esc = mysqli_real_escape_string($connection, $nome);
$cognome_esc = mysqli_real_escape_string($connection, $cognome);
$username_esc = mysqli_real_escape_string($connection, $username);
$email_esc = mysqli_real_escape_string($connection, $email);
$hash_esc = mysqli_real_escape_string($connection, $hash);
$token_esc = mysqli_real_escape_string($connection, $token);
$scadenza_esc = mysqli_real_escape_string($connection, $scadenza);

$sql = "SELECT id FROM utenti WHERE username='$username_esc' OR email='$email_esc' LIMIT 1";
$ris = mysqli_query($connection, $sql);
$exists = $ris ? mysqli_fetch_assoc($ris) : null;
if ($exists) {
    back_err("Username o email già in uso.");
}

$sqlIns = "INSERT INTO utenti (username,email,password_hash,nome,cognome,privilegi,attivo,richiesta_registrazione,registrazione_token,registrazione_scadenza)
           VALUES ('$username_esc','$email_esc','$hash_esc','$nome_esc','$cognome_esc','standard',0,1,'$token_esc','$scadenza_esc')";

if (!mysqli_query($connection, $sqlIns)) {
    back_err("Errore salvataggio registrazione.");
}

header("Location: register_ok.php");
exit;
