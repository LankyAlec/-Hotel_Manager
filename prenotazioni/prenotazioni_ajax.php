<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non autorizzato']);
    exit;
}

function json_response(bool $ok, string $message, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

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

function table_exists(mysqli $db, string $table): bool {
    $tableEsc = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res && $res->num_rows > 0;
}

function get_action(): string {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $_POST = array_merge($_POST, $input);
    }
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    return strtolower((string)$action);
}

/** ospiti può arrivare come array o come JSON string */
function normalize_ospiti($raw): array {
    if (is_array($raw)) return $raw;
    if (is_string($raw) && trim($raw) !== '') {
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }
    return [];
}

function normalize_servizi($raw): array {
    if (is_array($raw)) return $raw;
    if (is_string($raw) && trim($raw) !== '') {
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }
    return [];
}

function get_servizi_column(mysqli $db): ?string {
    if (column_exists($db, 'soggiorni', 'servizi_json')) return 'servizi_json';
    if (column_exists($db, 'soggiorni', 'servizi')) return 'servizi';
    return null;
}

function get_tipologia_camera_column(mysqli $db): ?string {
    if (column_exists($db, 'soggiorni', 'tipologia_camera')) return 'tipologia_camera';
    if (column_exists($db, 'soggiorni', 'tipo_camera')) return 'tipo_camera';
    if (column_exists($db, 'soggiorni', 'camera_tipo')) return 'camera_tipo';
    return null;
}

function get_housekeeping_column(mysqli $db): ?string {
    if (column_exists($db, 'soggiorni', 'housekeeping')) return 'housekeeping';
    if (column_exists($db, 'soggiorni', 'housekeeping_qty')) return 'housekeeping_qty';
    if (column_exists($db, 'soggiorni', 'housekeeping_qta')) return 'housekeeping_qta';
    return null;
}

function get_hb_dettagli_column(mysqli $db): ?string {
    if (column_exists($db, 'soggiorni', 'hb_dettagli')) return 'hb_dettagli';
    if (column_exists($db, 'soggiorni', 'hb_dettagli_json')) return 'hb_dettagli_json';
    return null;
}

function get_tariffe_tipologia_column(mysqli $db): ?string {
    if (!table_exists($db, 'soggiorni_tariffe')) return null;
    if (column_exists($db, 'soggiorni_tariffe', 'tipologia_camera')) return 'tipologia_camera';
    if (column_exists($db, 'soggiorni_tariffe', 'tipo_camera')) return 'tipo_camera';
    if (column_exists($db, 'soggiorni_tariffe', 'camera_tipo')) return 'camera_tipo';
    return null;
}

function get_soggiorni_tariffe_price_column(mysqli $db): ?string {
    if (!table_exists($db, 'soggiorni_tariffe')) return null;
    $candidates = ['prezzo', 'prezzo_notte', 'prezzo_camera', 'prezzo_giorno', 'tariffa'];
    foreach ($candidates as $col) {
        if (column_exists($db, 'soggiorni_tariffe', $col)) return $col;
    }
    return null;
}

function get_servizi(mysqli $db): array {
    if (!table_exists($db, 'servizi')) return [];
    $hasAttivo = column_exists($db, 'servizi', 'attivo');
    $hasParent = column_exists($db, 'servizi', 'parent_id');
    $where = [];
    if ($hasAttivo) $where[] = 'attivo = 1';
    $sql = "SELECT id, nome" . ($hasParent ? ", parent_id" : ", NULL AS parent_id") . " FROM servizi";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY " . ($hasParent ? "parent_id ASC, nome ASC" : "nome ASC");
    $res = $db->query($sql);
    if (!$res) return [];
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    if (!$hasParent) {
        return $rows;
    }

    $byParent = [];
    foreach ($rows as $row) {
        $parentId = $row['parent_id'];
        if ($parentId === null) {
            $byParent[(int)$row['id']] = [
                'id' => (int)$row['id'],
                'nome' => (string)$row['nome'],
                'children' => [],
            ];
        } else {
            $byParent['children:' . (int)$parentId][] = [
                'id' => (int)$row['id'],
                'nome' => (string)$row['nome'],
            ];
        }
    }

    foreach ($byParent as $key => $value) {
        if (strpos((string)$key, 'children:') === 0) continue;
        $childrenKey = 'children:' . (int)$value['id'];
        $byParent[$key]['children'] = $byParent[$childrenKey] ?? [];
        unset($byParent[$childrenKey]);
    }

    return array_values(array_filter($byParent, fn($item) => is_array($item) && array_key_exists('id', $item)));
}

function get_camera_pricing_preview(mysqli $db, int $cameraId, ?string $tipologia, string $checkin, string $checkout): array {
    if (!table_exists($db, 'soggiorni_tariffe')) return ['breakdown' => [], 'total' => 0.0];
    $priceCol = get_soggiorni_tariffe_price_column($db);
    if (!$priceCol) return ['breakdown' => [], 'total' => 0.0];

    $dalCol = column_exists($db, 'soggiorni_tariffe', 'dal');
    $alCol = column_exists($db, 'soggiorni_tariffe', 'al');
    $attivaCol = column_exists($db, 'soggiorni_tariffe', 'attiva');
    $cameraCol = column_exists($db, 'soggiorni_tariffe', 'camera_id');
    $tipologiaCol = get_tariffe_tipologia_column($db);

    $start = DateTime::createFromFormat('Y-m-d', $checkin);
    $end = DateTime::createFromFormat('Y-m-d', $checkout);
    if (!$start || !$end || $start >= $end) {
        return ['breakdown' => [], 'total' => 0.0];
    }

    $breakdown = [];
    $total = 0.0;
    $iter = clone $start;
    while ($iter < $end) {
        $date = $iter->format('Y-m-d');
        $conditions = [];
        $params = [];
        $types = '';

        if ($attivaCol) {
            $conditions[] = 'attiva = 1';
        }
        if ($cameraCol && $cameraId > 0) {
            $conditions[] = 'camera_id = ?';
            $types .= 'i';
            $params[] = $cameraId;
        }
        if ($tipologiaCol && $tipologia) {
            $conditions[] = "{$tipologiaCol} = ?";
            $types .= 's';
            $params[] = $tipologia;
        }
        if ($dalCol) {
            $conditions[] = 'dal <= ?';
            $types .= 's';
            $params[] = $date;
        }
        if ($alCol) {
            $conditions[] = '(al IS NULL OR al >= ?)';
            $types .= 's';
            $params[] = $date;
        }

        $sql = "SELECT {$priceCol} AS prezzo FROM soggiorni_tariffe";
        if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
        if ($dalCol) {
            $sql .= " ORDER BY dal DESC, id DESC LIMIT 1";
        } else {
            $sql .= " ORDER BY id DESC LIMIT 1";
        }

        $price = null;
        $stmt = $db->prepare($sql);
        if ($stmt) {
            if ($types) $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && $row['prezzo'] !== null) {
                $price = (float)$row['prezzo'];
            }
            $stmt->close();
        }

        $breakdown[] = ['date' => $date, 'price' => $price];
        if ($price !== null) $total += $price;
        $iter->modify('+1 day');
    }

    return ['breakdown' => $breakdown, 'total' => $total];
}

function get_servizi_pricing_preview(mysqli $db, array $servizi, string $checkin): array {
    if (!table_exists($db, 'servizi_tariffe') || !table_exists($db, 'servizi')) {
        return ['items' => [], 'total' => 0.0];
    }
    $serviceIds = [];
    $serviceModes = [];
    foreach ($servizi as $item) {
        $id = (int)($item['id'] ?? 0);
        $mode = (string)($item['mode'] ?? '');
        if ($id > 0) {
            $serviceIds[] = $id;
            $serviceModes[$id] = $mode ?: 'EXTRA';
        }
    }
    if (!$serviceIds) return ['items' => [], 'total' => 0.0];

    $in = implode(',', array_map('intval', $serviceIds));
    $names = [];
    $res = $db->query("SELECT id, nome FROM servizi WHERE id IN ({$in})");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $names[(int)$row['id']] = (string)$row['nome'];
        }
    }

    $attivaCol = column_exists($db, 'servizi_tariffe', 'attiva');
    $dalCol = column_exists($db, 'servizi_tariffe', 'dal');
    $alCol = column_exists($db, 'servizi_tariffe', 'al');
    $priceCol = column_exists($db, 'servizi_tariffe', 'prezzo_slot') ? 'prezzo_slot' : null;
    $orderBy = $dalCol ? 'dal DESC, id DESC' : 'id DESC';

    $items = [];
    $total = 0.0;
    foreach ($serviceIds as $serviceId) {
        $mode = $serviceModes[$serviceId] ?? 'EXTRA';
        $price = 0.0;
        if ($mode === 'EXTRA' && $priceCol) {
            $conditions = ['servizio_id = ?'];
            $types = 'i';
            $params = [$serviceId];
            if ($attivaCol) {
                $conditions[] = 'attiva = 1';
            }
            if ($dalCol) {
                $conditions[] = 'dal <= ?';
                $types .= 's';
                $params[] = $checkin;
            }
            if ($alCol) {
                $conditions[] = '(al IS NULL OR al >= ?)';
                $types .= 's';
                $params[] = $checkin;
            }
            $sql = "SELECT {$priceCol} AS prezzo FROM servizi_tariffe WHERE " . implode(' AND ', $conditions) . " ORDER BY {$orderBy} LIMIT 1";
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row && $row['prezzo'] !== null) {
                    $price = (float)$row['prezzo'];
                }
                $stmt->close();
            }
        }

        $items[] = [
            'id' => $serviceId,
            'nome' => $names[$serviceId] ?? "Servizio {$serviceId}",
            'mode' => $mode,
            'price' => $mode === 'EXTRA' ? $price : 0.0,
        ];
        if ($mode === 'EXTRA') $total += $price;
    }

    return ['items' => $items, 'total' => $total];
}

function is_range_available(mysqli $db, int $cameraId, string $checkin, string $checkout, ?int $excludeId = null): bool {
    $sql = "
        SELECT COUNT(*) AS tot
        FROM soggiorni
        WHERE camera_id = ?
          AND stato IN ('prenotato','occupato')
          AND NOT (? >= data_checkout OR ? <= data_checkin)
    ";
    if ($excludeId) $sql .= " AND id <> ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) return false;

    if ($excludeId) {
        $stmt->bind_param('issi', $cameraId, $checkin, $checkout, $excludeId);
    } else {
        $stmt->bind_param('iss', $cameraId, $checkin, $checkout);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return ((int)($row['tot'] ?? 0) === 0);
}

function get_camere(mysqli $db): array {
    if (table_exists($db, 'struttura_camere')) {
        $hasNome = column_exists($db, 'struttura_camere', 'nome');
        $hasAttiva = column_exists($db, 'struttura_camere', 'attiva');
        $selectNome = $hasNome ? ', nome' : ', NULL AS nome';
        $selectAttiva = $hasAttiva ? ', attiva' : ', 1 AS attiva';

        $res = $db->query("SELECT id, codice{$selectNome}{$selectAttiva} FROM struttura_camere ORDER BY codice ASC");
        if (!$res) return [];

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'codice' => (string)$r['codice'],
                'nome' => (string)($r['nome'] ?? ''),
                'attiva' => (int)($r['attiva'] ?? 1),
            ];
        }
        return $rows;
    }

    // fallback: typo fix (strattura_camere -> struttura_camere)
    if (table_exists($db, 'strattura_camere')) {
        $res = $db->query("SELECT id, codice, nome, capienza_base FROM strattura_camere ORDER BY codice ASC");
        if (!$res) return [];
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'codice' => (string)$r['codice'],
                'nome' => (string)($r['nome'] ?? ''),
                'capienza' => (int)($r['capienza_base'] ?? 0),
            ];
        }
        return $rows;
    }

    return [];
}

function list_bookings(mysqli $db): void {
    $selectNote = column_exists($db, 'soggiorni', 'note') ? ', s.note' : '';
    $selectLead = column_exists($db, 'soggiorni', 'referente') ? ', s.referente' : '';
    $selectPasto = column_exists($db, 'soggiorni', 'piano_pasto_sigla') ? ', s.piano_pasto_sigla' : '';
    $selectHb = column_exists($db, 'soggiorni', 'hb_servizio') ? ', s.hb_servizio' : '';
    $tipoCameraCol = get_tipologia_camera_column($db);
    $selectTipoCamera = $tipoCameraCol ? ", s.{$tipoCameraCol}" : '';

    $sql = "
        SELECT
            s.id,
            s.camera_id,
            s.stato,
            s.data_checkin,
            s.data_checkout
            $selectNote
            $selectLead
            $selectPasto
            $selectHb
            $selectTipoCamera
        FROM soggiorni s
        ORDER BY s.data_checkin DESC
        LIMIT 200
    ";

    $res = $db->query($sql);
    if (!$res) {
        json_response(false, 'Errore durante il caricamento delle prenotazioni: ' . $db->error, [], 500);
    }

    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $bookings = [];

    foreach ($rows as $row) {
        $bookingId = (int)$row['id'];

        // ✅ FIX: qui prima usavi alias c inesistente
        $stmtGuests = $db->prepare("
            SELECT
                TRIM(CONCAT(COALESCE(sc.nome,''), ' ', COALESCE(sc.cognome,''))) AS nominativo,
                sc.id
            FROM soggiorni_clienti sc
            WHERE sc.soggiorno_id = ?
            ORDER BY sc.id ASC
        ");
        $stmtGuests?->bind_param('i', $bookingId);
        $stmtGuests?->execute();
        $guestsRes = $stmtGuests ? $stmtGuests->get_result() : null;

        $firstGuest = $guestsRes && $guestsRes->num_rows > 0 ? ((string)($guestsRes->fetch_assoc()['nominativo'] ?? '')) : '';
        $guestsCount = $guestsRes ? $guestsRes->num_rows : 0;

        // ✅ FIX: tabella camere corretta
        $cameraId = (int)$row['camera_id'];
        $camera = null;
        if ($cameraId > 0 && table_exists($db, 'struttura_camere')) {
            $stmtCamera = $db->prepare("SELECT codice, nome FROM struttura_camere WHERE id = ? LIMIT 1");
            $stmtCamera?->bind_param('i', $cameraId);
            $stmtCamera?->execute();
            $cameraRes = $stmtCamera ? $stmtCamera->get_result() : null;
            $camera = $cameraRes ? $cameraRes->fetch_assoc() : null;
        }

        $bookings[] = [
            'id' => $bookingId,
            'camera_id' => (int)$row['camera_id'],
            'camera_label' => $camera ? trim(($camera['codice'] ?? '') . ' ' . ($camera['nome'] ?? '')) : '',
            'stato' => (string)($row['stato'] ?? 'prenotato'),
            'checkin' => (string)$row['data_checkin'],
            'checkout' => (string)$row['data_checkout'],
            'note' => $row['note'] ?? '',
            'referente' => $row['referente'] ?? $firstGuest,
            'pasto' => $row['piano_pasto_sigla'] ?? '',
            'hb' => $row['hb_servizio'] ?? '',
            'tipologia_camera' => $tipoCameraCol ? ($row[$tipoCameraCol] ?? '') : '',
            'ospiti' => $guestsCount,
        ];
    }

    json_response(true, 'OK', ['bookings' => $bookings]);
}

function insert_guests_for_booking(mysqli $db, int $soggiornoId, array $ospiti): void {
    // Colonne consentite (solo se esistono davvero)
    $allowed = [
        'nome', 'cognome', 'data_nascita', 'nazionalita', 'indirizzo',
        'documento_tipo', 'documento_numero', 'email', 'telefono', 'note'
    ];

    foreach ($ospiti as $o) {
        if (!is_array($o)) continue;
        if (!empty($o['id']) || !empty($o['cliente_id'])) continue;

        $nome = trim((string)($o['nome'] ?? ''));
        $cognome = trim((string)($o['cognome'] ?? ''));
        if ($nome === '' || $cognome === '') continue;

        $cols = ['soggiorno_id'];
        $ph   = ['?'];
        $types = 'i';
        $vals  = [$soggiornoId];

        foreach ($allowed as $k) {
            if (!array_key_exists($k, $o)) continue;
            if (!column_exists($db, 'soggiorni_clienti', $k)) continue;
            $cols[] = $k;
            $ph[] = '?';
            $types .= 's';
            $vals[] = (string)$o[$k];
        }

        // Se per qualche motivo non sono entrati nome/cognome nella mappa, li forzo
        if (!in_array('nome', $cols, true) && column_exists($db,'soggiorni_clienti','nome')) {
            $cols[] = 'nome'; $ph[]='?'; $types.='s'; $vals[]=$nome;
        }
        if (!in_array('cognome', $cols, true) && column_exists($db,'soggiorni_clienti','cognome')) {
            $cols[] = 'cognome'; $ph[]='?'; $types.='s'; $vals[]=$cognome;
        }

        $sql = "INSERT INTO soggiorni_clienti (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Errore DB (insert ospiti): ' . $db->error);
        }
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
    }
}

function save_booking(mysqli $db, array $payload): void {
    $id = (int)($payload['id'] ?? 0);

    $cameraId = isset($payload['camera_id']) ? (int)$payload['camera_id'] : null;
    $checkin = $payload['data_checkin'] ?? null;
    $checkout = $payload['data_checkout'] ?? null;
    $stato = $payload['stato'] ?? null;
    $referente = $payload['referente'] ?? null;
    $note = $payload['note'] ?? null;

    $pasto = $payload['piano_pasto_sigla'] ?? null;
    $hb = $payload['hb_servizio'] ?? null;
    $tipologiaCamera = $payload['tipologia_camera'] ?? null;
    $housekeeping = $payload['housekeeping'] ?? null;
    $hbMode = $payload['hb_modalita'] ?? null;
    $hbDettagliRaw = $payload['hb_dettagli'] ?? null;
    $serviziProvided = array_key_exists('servizi', $payload);
    $servizi = normalize_servizi($payload['servizi'] ?? null);
    $serviziCol = get_servizi_column($db);
    $serviziJson = ($serviziCol && $serviziProvided) ? json_encode($servizi, JSON_UNESCAPED_UNICODE) : null;

    // opzionali se esistono nel DB
    $hb_da = $payload['hb_da'] ?? null;
    $hb_a  = $payload['hb_a'] ?? null;
    $hbDettagli = null;
    $hbDettagliProvided = array_key_exists('hb_dettagli', $payload);
    if (is_string($hbDettagliRaw) && trim($hbDettagliRaw) !== '') {
        $decoded = json_decode($hbDettagliRaw, true);
        if (is_array($decoded)) {
            $hbDettagli = $decoded;
        }
    }

    // ✅ Regole date
    if ($checkin && $checkout && $checkin >= $checkout) {
        json_response(false, 'La data di check-out deve essere successiva al check-in', ['toast' => ['variant' => 'warning']]);
    }

    // ✅ Disponibilità camera
    if ($cameraId && $checkin && $checkout && !is_range_available($db, $cameraId, $checkin, $checkout, $id ?: null)) {
        json_response(false, 'La camera non è disponibile per l\'intervallo selezionato', ['conflict' => true, 'toast' => ['variant' => 'danger']]);
    }

    // ✅ Validazione pasto/HB
    if ($pasto !== null && $pasto !== '' && $pasto === 'HB') {
        if ($hbMode === 'personalizzato') {
            if (!$hbDettagli || !is_array($hbDettagli)) {
                json_response(false, 'Per HB personalizzato devi indicare pranzo o cena per ogni data', ['toast' => ['variant' => 'warning']]);
            }
            foreach ($hbDettagli as $val) {
                if (!in_array((string)$val, ['PRANZO', 'CENA'], true)) {
                    json_response(false, 'Valori HB non validi: scegli PRANZO o CENA', ['toast' => ['variant' => 'warning']]);
                }
            }
            $hb = null;
        } else {
            if (!in_array((string)$hb, ['PRANZO','CENA'], true)) {
                json_response(false, 'Per HB devi specificare PRANZO o CENA', ['toast' => ['variant' => 'warning']]);
            }
            $hbDettagli = null;
        }
        if ($hb_da && $hb_a && $hb_da > $hb_a) {
            json_response(false, 'Intervallo HB non valido (da > a)', ['toast' => ['variant' => 'warning']]);
        }
    } else {
        $hb = null;
        $hbDettagli = null;
        $hbMode = null;
    }

    $hbDettagliJson = null;
    if ($hbDettagli !== null) {
        $hbDettagliJson = json_encode($hbDettagli, JSON_UNESCAPED_UNICODE);
    }

    // ✅ Blocco: non creare prenotazione senza ospiti
    // (solo creazione, aggiornamento lo lasciamo libero)
    $ospiti = normalize_ospiti($payload['ospiti'] ?? null);
    if ($id <= 0) {
        // almeno 1 ospite con nome e cognome
        $valid = 0;
        foreach ($ospiti as $o) {
            if (is_array($o) && trim((string)($o['nome'] ?? '')) !== '' && trim((string)($o['cognome'] ?? '')) !== '') {
                $valid++;
            }
        }
        if ($valid < 1) {
            json_response(false, 'Prima di salvare la prenotazione devi inserire almeno 1 ospite', ['toast' => ['variant' => 'warning']]);
        }
    }

    $housekeepingVal = null;
    if ($housekeeping !== null && $housekeeping !== '') {
        $housekeepingVal = max(0, (int)$housekeeping);
    }

    $fields = [];
    $values = [];
    $types = '';

    if ($cameraId !== null) { $fields[] = 'camera_id = ?'; $values[] = $cameraId; $types .= 'i'; }
    if ($stato !== null) { $fields[] = 'stato = ?'; $values[] = $stato; $types .= 's'; }
    if ($checkin !== null) { $fields[] = 'data_checkin = ?'; $values[] = $checkin; $types .= 's'; }
    if ($checkout !== null) { $fields[] = 'data_checkout = ?'; $values[] = $checkout; $types .= 's'; }

    if ($referente !== null && column_exists($db, 'soggiorni', 'referente')) { $fields[] = 'referente = ?'; $values[] = $referente; $types .= 's'; }
    if ($note !== null && column_exists($db, 'soggiorni', 'note')) { $fields[] = 'note = ?'; $values[] = $note; $types .= 's'; }
    if ($pasto !== null && column_exists($db, 'soggiorni', 'piano_pasto_sigla')) { $fields[] = 'piano_pasto_sigla = ?'; $values[] = $pasto; $types .= 's'; }
    if ($hb !== null && column_exists($db, 'soggiorni', 'hb_servizio')) { $fields[] = 'hb_servizio = ?'; $values[] = $hb; $types .= 's'; }
    if ($tipologiaCamera !== null) {
        $tipoCameraCol = get_tipologia_camera_column($db);
        if ($tipoCameraCol) { $fields[] = "{$tipoCameraCol} = ?"; $values[] = $tipologiaCamera; $types .= 's'; }
    }
    if ($housekeepingVal !== null) {
        $housekeepingCol = get_housekeeping_column($db);
        if ($housekeepingCol) { $fields[] = "{$housekeepingCol} = ?"; $values[] = $housekeepingVal; $types .= 'i'; }
    }
    if ($hbDettagliProvided) {
        $hbDettagliCol = get_hb_dettagli_column($db);
        if ($hbDettagliCol) { $fields[] = "{$hbDettagliCol} = ?"; $values[] = $hbDettagliJson; $types .= 's'; }
    }

    if ($hb_da !== null && column_exists($db, 'soggiorni', 'hb_da')) { $fields[] = 'hb_da = ?'; $values[] = $hb_da; $types .= 's'; }
    if ($hb_a !== null && column_exists($db, 'soggiorni', 'hb_a')) { $fields[] = 'hb_a = ?'; $values[] = $hb_a; $types .= 's'; }
    if ($serviziCol && $serviziJson !== null) { $fields[] = "{$serviziCol} = ?"; $values[] = $serviziJson; $types .= 's'; }

    if ($id > 0) {
        if (empty($fields)) json_response(false, 'Nessun campo da aggiornare');
        $sql = "UPDATE soggiorni SET " . implode(', ', $fields) . " WHERE id = ?";
        $values[] = $id; $types .= 'i';

        $stmt = $db->prepare($sql);
        if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);

        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        if (!empty($ospiti)) {
            insert_guests_for_booking($db, $id, $ospiti);
        }
        json_response(true, 'Prenotazione aggiornata', ['toast' => ['variant' => 'success']]);
    }

    // INSERT
    if ($cameraId === null || $checkin === null || $checkout === null) {
        json_response(false, 'Camera, check-in e check-out sono obbligatori per creare una prenotazione');
    }

    $columns = ['camera_id', 'data_checkin', 'data_checkout', 'stato'];
    $placeholders = ['?', '?', '?', '?'];
    $insertTypes = 'isss';
    $insertValues = [$cameraId, $checkin, $checkout, ($stato !== null ? $stato : 'prenotato')];

    if ($referente !== null && column_exists($db, 'soggiorni', 'referente')) {
        $columns[] = 'referente'; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $referente;
    }
    if ($note !== null && column_exists($db, 'soggiorni', 'note')) {
        $columns[] = 'note';
        $placeholders[] = '?';
        $insertTypes .= 's';
        $insertValues[] = $note;
    }

    if ($pasto !== null && column_exists($db, 'soggiorni', 'piano_pasto_sigla')) {
        $columns[] = 'piano_pasto_sigla'; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $pasto;
    }
    if ($hb !== null && column_exists($db, 'soggiorni', 'hb_servizio')) {
        $columns[] = 'hb_servizio'; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $hb;
    }
    if ($tipologiaCamera !== null) {
        $tipoCameraCol = get_tipologia_camera_column($db);
        if ($tipoCameraCol) {
            $columns[] = $tipoCameraCol; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $tipologiaCamera;
        }
    }
    if ($housekeepingVal !== null) {
        $housekeepingCol = get_housekeeping_column($db);
        if ($housekeepingCol) {
            $columns[] = $housekeepingCol; $placeholders[] = '?'; $insertTypes .= 'i'; $insertValues[] = $housekeepingVal;
        }
    }
    if ($hbDettagliProvided) {
        $hbDettagliCol = get_hb_dettagli_column($db);
        if ($hbDettagliCol) {
            $columns[] = $hbDettagliCol; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $hbDettagliJson;
        }
    }
    if ($hb_da !== null && column_exists($db, 'soggiorni', 'hb_da')) {
        $columns[] = 'hb_da'; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $hb_da;
    }
    if ($hb_a !== null && column_exists($db, 'soggiorni', 'hb_a')) {
        $columns[] = 'hb_a'; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $hb_a;
    }
    if ($serviziCol && $serviziJson !== null) {
        $columns[] = $serviziCol; $placeholders[] = '?'; $insertTypes .= 's'; $insertValues[] = $serviziJson;
    }

    // transazione: creo soggiorno + creo ospiti
    $db->begin_transaction();
    try {
        $sql = "INSERT INTO soggiorni (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Errore DB: ' . $db->error);
        $stmt->bind_param($insertTypes, ...$insertValues);
        $stmt->execute();
        $newId = (int)$stmt->insert_id;

        // ✅ inserisco ospiti (obbligatori)
        insert_guests_for_booking($db, $newId, $ospiti);

        $db->commit();
        json_response(true, 'Prenotazione creata', ['id' => $newId, 'toast' => ['variant' => 'success']]);
    } catch (Throwable $e) {
        $db->rollback();
        json_response(false, 'Errore durante il salvataggio: ' . $e->getMessage(), [], 500);
    }
}

function check_availability(mysqli $db, array $payload): void {
    $cameraId = (int)($payload['camera_id'] ?? 0);
    $checkin = $payload['data_checkin'] ?? null;
    $checkout = $payload['data_checkout'] ?? null;
    $exclude = isset($payload['id']) ? (int)$payload['id'] : null;

    if (!$cameraId || !$checkin || !$checkout) {
        json_response(false, 'Parametri mancanti per la verifica della disponibilità');
    }

    $available = is_range_available($db, $cameraId, $checkin, $checkout, $exclude);
    json_response(true, $available ? 'Camera disponibile' : 'Camera non disponibile', [
        'available' => $available,
        'toast' => ['variant' => $available ? 'success' : 'warning']
    ]);
}

function pricing_preview(mysqli $db, array $payload): void {
    $cameraId = (int)($payload['camera_id'] ?? 0);
    $checkin = $payload['data_checkin'] ?? null;
    $checkout = $payload['data_checkout'] ?? null;
    $tipologia = $payload['tipologia_camera'] ?? null;
    $servizi = normalize_servizi($payload['servizi'] ?? null);

    if (!$cameraId || !$checkin || !$checkout) {
        json_response(false, 'Parametri mancanti per il calcolo prezzi');
    }

    $cameraPreview = get_camera_pricing_preview($db, $cameraId, $tipologia, $checkin, $checkout);
    $serviziPreview = get_servizi_pricing_preview($db, $servizi, $checkin);
    $total = (float)($cameraPreview['total'] ?? 0) + (float)($serviziPreview['total'] ?? 0);

    json_response(true, 'OK', [
        'camera' => $cameraPreview,
        'servizi' => $serviziPreview,
        'total' => $total,
    ]);
}

$action = get_action();

switch ($action) {
    case 'list':
        list_bookings($mysqli);
        break;

    case 'metadata':
        json_response(true, 'OK', [
            'camere' => get_camere($mysqli),
            'stati' => ['prenotato', 'occupato', 'annullato', 'checkout'],
            'servizi' => get_servizi($mysqli),
        ]);
        break;

    case 'pricing_preview':
        pricing_preview($mysqli, $_POST);
        break;

    case 'check_availability':
        check_availability($mysqli, $_POST);
        break;

    case 'assign_room':
    case 'save_booking':
        save_booking($mysqli, $_POST);
        break;

    default:
        json_response(false, 'Azione non supportata', [], 400);
}
