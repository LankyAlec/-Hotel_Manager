<?php
// product_save.php
// Endpoint legacy (salva prodotto) aggiornato alla nuova struttura (stock nei lotti).

declare(strict_types=1);
require __DIR__ . '/init.php';

$id  = qint($_POST['id'] ?? 0, 0);
$mid = qint($_POST['mid'] ?? 0, 0); // tenuto per compat UI/redirect, non viene scritto sul prodotto

$nome        = trim((string)($_POST['nome'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));

$categoria_id = $_POST['categoria_id'] ?? ($_POST['id_categoria'] ?? null);
$categoria_id = ($categoria_id === '' || $categoria_id === null) ? null : qint($categoria_id, 1);

$unita = (string)($_POST['unita'] ?? 'pz');
$allowedU = ['pz','kg','g','l','ml','altro'];
if (!in_array($unita, $allowedU, true)) $unita = 'pz';

if ($nome === '') {
  flash_set('danger', 'Nome obbligatorio');
  mag_redirect('product_form.php' . ($id > 0 ? ('?id=' . $id) : ''));
}

$nomeE  = "'" . esc($conn, $nome) . "'";
$descE  = ($descrizione !== '') ? ("'" . esc($conn, $descrizione) . "'") : 'NULL';
$catE   = ($categoria_id !== null) ? (string)(int)$categoria_id : 'NULL';
$unitaE = "'" . esc($conn, $unita) . "'";

try {
  if ($id > 0) {
    $sql = "UPDATE magazzino_prodotti
            SET nome=$nomeE,
                descrizione=$descE,
                categoria_id=$catE,
                unita=$unitaE
            WHERE id=$id
            LIMIT 1";

    if (!mysqli_query($conn, $sql)) {
      throw new RuntimeException(mysqli_error($conn) ?: 'query failed');
    }

    flash_set('success', 'Prodotto aggiornato');
    mag_redirect('product_form.php?id=' . $id . ($mid > 0 ? ('&mid=' . $mid) : ''));
  } else {
    $sql = "INSERT INTO magazzino_prodotti (nome, descrizione, categoria_id, unita, attivo)
            VALUES ($nomeE, $descE, $catE, $unitaE, 1)";

    if (!mysqli_query($conn, $sql)) {
      throw new RuntimeException(mysqli_error($conn) ?: 'query failed');
    }

    $newId = (int)mysqli_insert_id($conn);
    flash_set('success', 'Prodotto creato. Ora aggiungi i lotti.');
    mag_redirect('product_form.php?id=' . $newId . ($mid > 0 ? ('&mid=' . $mid) : ''));
  }
} catch (Throwable $e) {
  error_log('product_save.php: ' . $e->getMessage());
  flash_set('danger', 'Errore salvataggio (controlla error_log PHP).');
  mag_redirect('product_form.php' . ($id > 0 ? ('?id=' . $id) : ''));
}
