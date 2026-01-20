<?php
// product_delete.php
// Elimina prodotto + movimenti + lotti (stock nei lotti)

declare(strict_types=1);
require __DIR__ . '/init.php';

$id  = qint($_GET['id'] ?? 0, 0);
$mid = qint($_GET['mid'] ?? 0, 0);

if ($id <= 0) {
  flash_set('danger', 'ID non valido');
  mag_redirect('magazzini.php?mid=' . $mid);
}

mysqli_begin_transaction($conn);
try {
  // 1) movimenti (dipendono dai lotti)
  $sql = "DELETE FROM magazzino_movimenti WHERE prodotto_id=$id";
  if (!mysqli_query($conn, $sql)) {
    throw new RuntimeException('Delete movimenti: ' . (mysqli_error($conn) ?: 'query failed'));
  }

  // 2) lotti
  $sql = "DELETE FROM magazzino_lotti WHERE prodotto_id=$id";
  if (!mysqli_query($conn, $sql)) {
    throw new RuntimeException('Delete lotti: ' . (mysqli_error($conn) ?: 'query failed'));
  }

  // 3) prodotto
  $sql = "DELETE FROM magazzino_prodotti WHERE id=$id LIMIT 1";
  if (!mysqli_query($conn, $sql)) {
    throw new RuntimeException('Delete prodotto: ' . (mysqli_error($conn) ?: 'query failed'));
  }

  mysqli_commit($conn);
  flash_set('success', 'Prodotto eliminato');
} catch (Throwable $e) {
  mysqli_rollback($conn);
  error_log('product_delete.php: ' . $e->getMessage());
  flash_set('danger', 'Errore eliminazione (controlla error_log PHP).');
}

mag_redirect('magazzini.php?mid=' . $mid);
