<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/pagamenti_lib.php';

$search = trim((string)($_GET['q'] ?? ''));
$soggiornoId = (int)($_GET['soggiorno_id'] ?? 0);
$action = (string)($_GET['action'] ?? '');

function column_exists(mysqli $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = ($res && $res->num_rows > 0);
    $cache[$key] = $exists;
    return $exists;
}

function get_servizi_column(mysqli $db): ?string {
    if (column_exists($db, 'soggiorni', 'servizi_json')) return 'servizi_json';
    if (column_exists($db, 'soggiorni', 'servizi')) return 'servizi';
    return null;
}

function load_customer_stays(mysqli $db, string $search): array {
    $conditions = [];
    $types = '';
    $values = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $fields = [
            'c.nome',
            'c.cognome',
            'c.email',
            'c.telefono',
            'c.documento_numero',
            'c.documento_tipo',
        ];
        $parts = [];
        foreach ($fields as $field) {
            if (str_contains($field, 'documento_') && !column_exists($db, 'soggiorni_clienti', str_replace('c.', '', $field))) {
                continue;
            }
            if ((str_contains($field, 'email') || str_contains($field, 'telefono')) && !column_exists($db, 'soggiorni_clienti', str_replace('c.', '', $field))) {
                continue;
            }
            $parts[] = "$field LIKE ?";
            $types .= 's';
            $values[] = $like;
        }
        if ($parts) {
            $conditions[] = '(' . implode(' OR ', $parts) . ')';
        }
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $selectNome = column_exists($db, 'soggiorni_clienti', 'nome') ? 'c.nome' : "'' AS nome";
    $selectCognome = column_exists($db, 'soggiorni_clienti', 'cognome') ? 'c.cognome' : "'' AS cognome";
    $selectEmail = column_exists($db, 'soggiorni_clienti', 'email') ? 'c.email' : 'NULL AS email';
    $selectTelefono = column_exists($db, 'soggiorni_clienti', 'telefono') ? 'c.telefono' : 'NULL AS telefono';
    $selectDocTipo = column_exists($db, 'soggiorni_clienti', 'documento_tipo') ? 'c.documento_tipo' : 'NULL AS documento_tipo';
    $selectDocNumero = column_exists($db, 'soggiorni_clienti', 'documento_numero') ? 'c.documento_numero' : 'NULL AS documento_numero';

    $selectHb = column_exists($db, 'soggiorni', 'hb_servizio') ? 's.hb_servizio' : 'NULL AS hb_servizio';
    $selectPasto = column_exists($db, 'soggiorni', 'piano_pasto_sigla') ? 's.piano_pasto_sigla' : 'NULL AS piano_pasto_sigla';
    $selectTipologia = '';
    foreach (['tipologia_camera', 'tipo_camera', 'camera_tipo'] as $col) {
        if (column_exists($db, 'soggiorni', $col)) {
            $selectTipologia = "s.$col AS tipologia_camera";
            break;
        }
    }
    if ($selectTipologia === '') {
        $selectTipologia = "'' AS tipologia_camera";
    }
    $selectNote = column_exists($db, 'soggiorni', 'note') ? 's.note' : "'' AS note";
    $serviziCol = get_servizi_column($db);
    $selectServizi = $serviziCol ? ('s.' . $serviziCol . ' AS servizi_json') : 'NULL AS servizi_json';

    $sql = "
        SELECT
            s.id AS soggiorno_id,
            s.camera_id,
            s.data_checkin,
            s.data_checkout,
            s.stato,
            $selectPasto,
            $selectHb,
            $selectTipologia,
            $selectNote,
            $selectServizi,
            $selectNome,
            $selectCognome,
            $selectEmail,
            $selectTelefono,
            $selectDocTipo,
            $selectDocNumero,
            c.created_at AS cliente_created_at,
            c.id AS cliente_id,
            c.nazionalita,
            c.data_nascita
        FROM soggiorni_clienti c
        JOIN soggiorni s ON s.id = c.soggiorno_id
        $where
        ORDER BY c.cognome ASC, c.nome ASC, s.data_checkin DESC
        LIMIT 500
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function load_booking_services(mysqli $db, array $booking, array $serviziMap): array {
    $serviziRaw = $booking['servizi_json'] ?? $booking['servizi'] ?? null;
    if (!$serviziRaw) return [];
    $decoded = is_string($serviziRaw) ? json_decode($serviziRaw, true) : $serviziRaw;
    if (!is_array($decoded)) return [];

    $out = [];
    foreach ($decoded as $item) {
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) continue;
        $label = $serviziMap[$id]['nome'] ?? ('Servizio #' . $id);
        $parent = $serviziMap[$id]['parent_id'] ?? null;
        $out[] = [
            'id' => $id,
            'nome' => $label,
            'mode' => strtoupper((string)($item['mode'] ?? '')),
            'parent_id' => $parent,
        ];
    }
    return $out;
}

function build_servizi_map(mysqli $db): array {
    if (!column_exists($db, 'servizi', 'id') || !column_exists($db, 'servizi', 'nome')) return [];
    $hasParent = column_exists($db, 'servizi', 'parent_id');
    $sql = 'SELECT id, nome' . ($hasParent ? ', parent_id' : '') . ' FROM servizi';
    $res = $db->query($sql);
    if (!$res) return [];
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $map[(int)$row['id']] = [
            'nome' => $row['nome'] ?? '',
            'parent_id' => $hasParent ? $row['parent_id'] : null,
        ];
    }
    return $map;
}

function render_stay_pdf(array $booking, array $guest, array $servizi): string {
    $lines = [];
    $lines[] = 'Dettaglio soggiorno #' . (int)($booking['id'] ?? 0);
    $lines[] = 'Cliente: ' . trim(($guest['cognome'] ?? '') . ' ' . ($guest['nome'] ?? ''));
    $lines[] = 'Check-in: ' . ($booking['data_checkin'] ?? '—') . ' | Check-out: ' . ($booking['data_checkout'] ?? '—');
    $lines[] = 'Camera: #' . (int)($booking['camera_id'] ?? 0) . ' | Stato: ' . ($booking['stato'] ?? '');
    $pasto = $booking['piano_pasto_sigla'] ?? '';
    if ($pasto !== '') {
        $lines[] = 'Piano pasto: ' . $pasto . ' | HB: ' . ($booking['hb_servizio'] ?? '');
    }
    if (!empty($booking['tipologia_camera'])) {
        $lines[] = 'Tipologia camera: ' . $booking['tipologia_camera'];
    }
    if (!empty($booking['note'])) {
        $lines[] = 'Note: ' . $booking['note'];
    }
    $lines[] = str_repeat('-', 40);
    $lines[] = 'Servizi utilizzati:';
    if (!$servizi) {
        $lines[] = 'Nessun servizio associato.';
    } else {
        foreach ($servizi as $srv) {
            $label = trim($srv['nome'] . (isset($srv['mode']) && $srv['mode'] !== '' ? ' (' . $srv['mode'] . ')' : ''));
            $lines[] = '- ' . $label;
        }
    }

    $objects = [];
    $pdf = "%PDF-1.4\n";
    $contentLines = ["BT", "/F1 12 Tf", "50 800 Td"];
    foreach ($lines as $i => $line) {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $contentLines[] = ($i === 0 ? "($safe) Tj" : "T* ($safe) Tj");
    }
    $contentLines[] = "ET";
    $stream = implode("\n", $contentLines);
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Count 1 /Kids [3 0 R] >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /Name /F1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

    $offsets = [0];
    foreach ($objects as $i => $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

function send_csv(array $rows): void {
    $filename = 'soggiorni_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Cliente', 'Email', 'Telefono', 'Soggiorno', 'Check-in', 'Check-out', 'Camera', 'Stato', 'Piano pasto', 'HB', 'Tipologia camera'], ';');
    foreach ($rows as $row) {
        $cliente = trim(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? ''));
        fputcsv($out, [
            $cliente,
            $row['email'] ?? '',
            $row['telefono'] ?? '',
            $row['soggiorno_id'] ?? '',
            $row['data_checkin'] ?? '',
            $row['data_checkout'] ?? '',
            $row['camera_id'] ?? '',
            $row['stato'] ?? '',
            $row['piano_pasto_sigla'] ?? '',
            $row['hb_servizio'] ?? '',
            $row['tipologia_camera'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

if ($action === 'export_csv') {
    $rows = load_customer_stays($mysqli, $search);
    send_csv($rows);
}

if ($action === 'export_pdf' && $soggiornoId > 0) {
    $serviziCol = get_servizi_column($mysqli);
    $selectServizi = $serviziCol ? (', s.' . $serviziCol . ' AS servizi_json') : ', NULL AS servizi_json';
    $stmt = $mysqli->prepare("SELECT s.* $selectServizi, c.nome, c.cognome, c.email, c.telefono FROM soggiorni s JOIN soggiorni_clienti c ON c.soggiorno_id = s.id WHERE s.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $soggiornoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $serviziMap = build_servizi_map($mysqli);
            $servizi = load_booking_services($mysqli, $row, $serviziMap);
            $pdf = render_stay_pdf($row, $row, $servizi);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="soggiorno_' . $soggiornoId . '.pdf"');
            echo $pdf;
            exit;
        }
    }
}

$rows = load_customer_stays($mysqli, $search);
$serviziMap = build_servizi_map($mysqli);
$grouped = [];
foreach ($rows as $row) {
    $key = ($row['cognome'] ?? '') . '|' . ($row['nome'] ?? '') . '|' . ($row['email'] ?? '') . '|' . ($row['telefono'] ?? '');
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'cliente' => $row,
            'soggiorni' => [],
        ];
    }
    $grouped[$key]['soggiorni'][] = $row;
}
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Clienti</h1>
        <div class="text-secondary">Storico soggiorni e servizi utilizzati</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-success" href="<?= BASE_URL ?>/prenotazioni/clienti.php?action=export_csv&amp;q=<?= urlencode($search) ?>">
            <i class="bi bi-filetype-csv"></i> CSV soggiorni
        </a>
    </div>
</div>

<div class="card toolbar-card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label">Cerca cliente</label>
                <input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Nome, cognome, email, telefono, documento">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-search"></i> Cerca
                </button>
                <a class="btn btn-outline-secondary w-100" href="<?= BASE_URL ?>/prenotazioni/clienti.php">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (!$grouped): ?>
    <div class="alert alert-info">Nessun cliente trovato con i filtri selezionati.</div>
<?php else: ?>
    <div class="accordion" id="clientiAccordion">
        <?php $idx = 0; ?>
        <?php foreach ($grouped as $item): ?>
            <?php
                $cliente = $item['cliente'];
                $soggiorni = $item['soggiorni'];
                $clienteName = trim(($cliente['cognome'] ?? '') . ' ' . ($cliente['nome'] ?? ''));
                $email = $cliente['email'] ?? '';
                $telefono = $cliente['telefono'] ?? '';
                $doc = trim(($cliente['documento_tipo'] ?? '') . ' ' . ($cliente['documento_numero'] ?? ''));
                $panelId = 'cliente_' . $idx;
            ?>
            <div class="accordion-item mb-3 table-card">
                <h2 class="accordion-header" id="heading_<?= $panelId ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?= $panelId ?>" aria-expanded="false" aria-controls="collapse_<?= $panelId ?>">
                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 w-100">
                            <div class="fw-semibold"><?= h($clienteName !== '' ? $clienteName : 'Cliente senza nome') ?></div>
                            <?php if ($email !== ''): ?>
                                <div class="text-muted small"><i class="bi bi-envelope"></i> <?= h($email) ?></div>
                            <?php endif; ?>
                            <?php if ($telefono !== ''): ?>
                                <div class="text-muted small"><i class="bi bi-telephone"></i> <?= h($telefono) ?></div>
                            <?php endif; ?>
                            <?php if ($doc !== ''): ?>
                                <div class="text-muted small"><i class="bi bi-file-earmark-text"></i> <?= h($doc) ?></div>
                            <?php endif; ?>
                            <div class="ms-md-auto badge text-bg-light text-dark"><?= count($soggiorni) ?> soggiorni</div>
                        </div>
                    </button>
                </h2>
                <div id="collapse_<?= $panelId ?>" class="accordion-collapse collapse" aria-labelledby="heading_<?= $panelId ?>" data-bs-parent="#clientiAccordion">
                    <div class="accordion-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Soggiorno</th>
                                        <th>Date</th>
                                        <th>Camera</th>
                                        <th>Piano pasto</th>
                                        <th>Servizi</th>
                                        <th class="text-end">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($soggiorni as $soggiorno): ?>
                                        <?php
                                            $servizi = load_booking_services($mysqli, $soggiorno, $serviziMap);
                                            $serviziLabel = $servizi ? implode(', ', array_map(function ($srv) {
                                                $name = $srv['nome'] ?? '';
                                                $mode = $srv['mode'] ?? '';
                                                return trim($name . ($mode ? " ($mode)" : ''));
                                            }, $servizi)) : '—';
                                            $checkin = $soggiorno['data_checkin'] ? date('d/m/Y', strtotime($soggiorno['data_checkin'])) : '—';
                                            $checkout = $soggiorno['data_checkout'] ? date('d/m/Y', strtotime($soggiorno['data_checkout'])) : '—';
                                            $pasto = $soggiorno['piano_pasto_sigla'] ?? '';
                                            $hb = $soggiorno['hb_servizio'] ?? '';
                                            $pastoLabel = $pasto !== '' ? strtoupper($pasto) : '—';
                                            if ($pasto === 'HB' && $hb !== '') {
                                                $pastoLabel .= ' (' . $hb . ')';
                                            }
                                            $room = (int)($soggiorno['camera_id'] ?? 0);
                                            $stato = $soggiorno['stato'] ?? '';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">#<?= (int)($soggiorno['soggiorno_id'] ?? 0) ?></div>
                                                <div class="text-muted small"><?= h($stato !== '' ? ucfirst($stato) : '—') ?></div>
                                            </td>
                                            <td><?= h($checkin) ?> → <?= h($checkout) ?></td>
                                            <td><?= $room > 0 ? 'Camera #' . $room : '—' ?></td>
                                            <td><?= h($pastoLabel) ?></td>
                                            <td class="text-muted small"><?= h($serviziLabel) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/prenotazioni/prenotazioni.php?id=<?= (int)($soggiorno['soggiorno_id'] ?? 0) ?>">
                                                    <i class="bi bi-eye"></i> Dettagli
                                                </a>
                                                <a class="btn btn-outline-danger btn-sm ms-2" href="<?= BASE_URL ?>/prenotazioni/clienti.php?action=export_pdf&amp;soggiorno_id=<?= (int)($soggiorno['soggiorno_id'] ?? 0) ?>">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small mt-3">
                            Puoi scaricare il PDF per ogni soggiorno oppure esportare il CSV completo dalla testata pagina.
                        </div>
                    </div>
                </div>
            </div>
            <?php $idx++; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
