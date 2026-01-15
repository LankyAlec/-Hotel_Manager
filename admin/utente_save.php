<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['utente_id']) || ($_SESSION['privilegi'] ?? '') !== 'root') {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$azione = (string)($_POST['azione'] ?? '');

if ($azione === 'toggle_attivo') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { header("Location: utenti.php"); exit; }

    // Evita di disattivare te stesso
    if ((int)$_SESSION['utente_id'] === $id) {
        header("Location: utenti.php?tab=tutti&msg=self_toggle_block");
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE utenti SET attivo = IF(attivo=1,0,1) WHERE id=?");
    if (!$stmt) die("prepare toggle_attivo failed: " . $mysqli->error);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: utenti.php?tab=tutti");
    exit;
}

if ($azione === 'salva_utente') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { die("ID non valido."); }

    $nome      = trim((string)($_POST['nome'] ?? ''));
    $cognome   = trim((string)($_POST['cognome'] ?? ''));
    $username  = trim((string)($_POST['username'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $privilegi = (string)($_POST['privilegi'] ?? 'standard');
    $attivo    = !empty($_POST['attivo']) ? 1 : 0;
    $richiesta = !empty($_POST['richiesta_registrazione']) ? 1 : 0;

    $nuova_password = (string)($_POST['nuova_password'] ?? '');
    $gruppiPost     = $_POST['gruppi'] ?? [];
    $permOverride   = $_POST['permesso_override'] ?? [];

    if ($username === '' || $email === '') { die("Username/email mancanti."); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { die("Email non valida."); }
    if (!in_array($privilegi, ['guest','standard','root'], true)) $privilegi = 'standard';

    // collisioni username/email
    $stmt = $mysqli->prepare("SELECT id FROM utenti WHERE (username=? OR email=?) AND id<>? LIMIT 1");
    if (!$stmt) die("prepare dup check failed: " . $mysqli->error);
    $stmt->bind_param("ssi", $username, $email, $id);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($dup) { die("Username o email già usati da un altro utente."); }

    $mysqli->begin_transaction();

    try {
        // Update utente
        if ($nuova_password !== '') {
            if (strlen($nuova_password) < 8) throw new Exception("Password troppo corta.");
            $hash = password_hash($nuova_password, PASSWORD_DEFAULT);

            $stmt = $mysqli->prepare("
                UPDATE utenti
                SET nome=?, cognome=?, username=?, email=?, privilegi=?, attivo=?, richiesta_registrazione=?, password_hash=?
                WHERE id=?
            ");
            if (!$stmt) throw new Exception("prepare update (pwd) failed: " . $mysqli->error);
            $stmt->bind_param("sssssissi", $nome, $cognome, $username, $email, $privilegi, $attivo, $richiesta, $hash, $id);
        } else {
            $stmt = $mysqli->prepare("
                UPDATE utenti
                SET nome=?, cognome=?, username=?, email=?, privilegi=?, attivo=?, richiesta_registrazione=?
                WHERE id=?
            ");
            if (!$stmt) throw new Exception("prepare update failed: " . $mysqli->error);
            $stmt->bind_param("sssssiii", $nome, $cognome, $username, $email, $privilegi, $attivo, $richiesta, $id);
        }
        $stmt->execute();
        $stmt->close();

        /* =========================
         * GRUPPI: ponte utenti_privilegi
         * ========================= */
        $stmt = $mysqli->prepare("DELETE FROM utenti_privilegi WHERE utente_id=?");
        if (!$stmt) throw new Exception("prepare delete utenti_privilegi failed: " . $mysqli->error);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        if (is_array($gruppiPost) && !empty($gruppiPost)) {
            $stmt = $mysqli->prepare("INSERT INTO utenti_privilegi (utente_id, gruppo_id) VALUES (?, ?)");
            if (!$stmt) throw new Exception("prepare insert utenti_privilegi failed: " . $mysqli->error);

            foreach ($gruppiPost as $gid) {
                $gid = (int)$gid;
                if ($gid > 0) {
                    $stmt->bind_param("ii", $id, $gid);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        die("Errore salvataggio: " . $e->getMessage());
    }

    header("Location: utente_edit.php?id=" . $id . "&ok=1");
    exit;
}

header("Location: utenti.php");
exit;