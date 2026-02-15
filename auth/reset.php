<?php
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(400);
    exit('Token mancante.');
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

$err = $_GET['err'] ?? '';
$csrf = csrf_token('reset_form');
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>Reimposta password | <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container vh-100 d-flex justify-content-center align-items-center">
  <div class="card shadow-sm" style="width: 420px;">
    <div class="card-body">
      <h4 class="mb-3">Reimposta password</h4>

      <?php if ($err): ?>
        <div class="alert alert-danger small"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" action="reset_save.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label class="form-label">Nuova password</label>
        <input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password">
        <button class="btn btn-primary w-100 mt-3">Salva</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
