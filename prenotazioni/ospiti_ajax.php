<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non autorizzato']);
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

function json_response(bool $ok, string $message, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function get_payload(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? array_merge($_POST, $input) : $_POST;
}

function list_guests(mysqli $db, int $soggiornoId): void {
    $sql = "
        SELECT
            id,
            soggiorno_id,
            nome,
            cognome,
            " . (column_exists($db,'soggiorni_clienti','data_nascita') ? "data_nascita," : "") . "
            " . (column_exists($db,'soggiorni_clienti','nazionalita') ? "nazionalita," : "") . "
            " . (column_exists($db,'soggiorni_clienti','documento_tipo') ? "documento_tipo," : "") . "
            " . (column_exists($db,'soggiorni_clienti','documento_numero') ? "documento_numero," : "") . "
            " . (column_exists($db,'soggiorni_clienti','documento_scadenza') ? "documento_scadenza," : "") . "
            " . (column_exists($db,'soggiorni_clienti','documento_rilasciato_da') ? "documento_rilasciato_da," : "") . "
            " . (column_exists($db,'soggiorni_clienti','documento_note') ? "documento_note," : "") . "
            " . (column_exists($db,'soggiorni_clienti','email') ? "email," : "") . "
            " . (column_exists($db,'soggiorni_clienti','telefono') ? "telefono," : "") . "
            " . (column_exists($db,'soggiorni_clienti','indirizzo') ? "indirizzo," : "") . "
            " . (column_exists($db,'soggiorni_clienti','note') ? "note," : "") . "
            created_at,
            updated_at
        FROM soggiorni_clienti
        WHERE soggiorno_id = ?
        ORDER BY cognome ASC, nome ASC
    ";

    // pulizia virgole finali (nel caso qualche colonna non esista)
    $sql = preg_replace('/,\s*created_at/', ', created_at', $sql);

    $stmt = $db->prepare($sql);
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);

    $stmt->bind_param('i', $soggiornoId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    json_response(true, 'OK', ['ospiti' => $rows]);
}

function assert_guest_belongs(mysqli $db, int $soggiornoId, int $guestId): void {
    $stmt = $db->prepare("SELECT 1 FROM soggiorni_clienti WHERE soggiorno_id = ? AND id = ? LIMIT 1");
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);
    $stmt->bind_param('ii', $soggiornoId, $guestId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        json_response(false, 'Ospite non associato a questa prenotazione', [], 403);
    }
}

function save_documents(mysqli $db, int $soggiornoId, int $guestId, array $payload): void {
    assert_guest_belongs($db, $soggiornoId, $guestId);

    $fields = [];
    $types = '';
    $values = [];

    $map = [
        'documento_tipo' => 's',
        'documento_numero' => 's',
        'documento_scadenza' => 's',
        'documento_rilasciato_da' => 's',
        'documento_note' => 's',
    ];

    foreach ($map as $field => $type) {
        if (array_key_exists($field, $payload) && column_exists($db, 'soggiorni_clienti', $field)) {
            $fields[] = "$field = ?";
            $types .= $type;
            $values[] = (string)$payload[$field];
        }
    }

    if (empty($fields)) json_response(false, 'Nessun campo documento aggiornabile');

    $sql = "UPDATE soggiorni_clienti SET " . implode(', ', $fields) . " WHERE id = ? AND soggiorno_id = ?";
    $types .= 'ii';
    $values[] = $guestId;
    $values[] = $soggiornoId;

    $stmt = $db->prepare($sql);
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);

    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    json_response(true, 'Documenti salvati', ['toast' => ['variant' => 'success']]);
}

function save_guest(mysqli $db, int $soggiornoId, ?int $guestId, array $payload): void {
    if ($soggiornoId <= 0) json_response(false, 'Prenotazione mancante');

    $map = [
        'nome' => 's',
        'cognome' => 's',
        'data_nascita' => 's',
        'nazionalita' => 's',
        'indirizzo' => 's',
        'documento_tipo' => 's',
        'documento_numero' => 's',
        'email' => 's',
        'telefono' => 's',
        'note' => 's',
    ];

    $fields = [];
    $types = '';
    $values = [];

    foreach ($map as $field => $type) {
        if (array_key_exists($field, $payload) && column_exists($db, 'soggiorni_clienti', $field)) {
            $fields[$field] = (string)$payload[$field];
            $types .= $type;
            $values[] = (string)$payload[$field];
        }
    }

    // minimo: nome/cognome (se esistono come colonne)
    if (column_exists($db,'soggiorni_clienti','nome') && trim((string)($payload['nome'] ?? '')) === '') {
        json_response(false, 'Nome obbligatorio');
    }
    if (column_exists($db,'soggiorni_clienti','cognome') && trim((string)($payload['cognome'] ?? '')) === '') {
        json_response(false, 'Cognome obbligatorio');
    }

    if (empty($fields)) json_response(false, 'Nessun campo da salvare');

    // UPDATE
    if ($guestId && $guestId > 0) {
        assert_guest_belongs($db, $soggiornoId, $guestId);

        $setParts = [];
        foreach (array_keys($fields) as $col) $setParts[] = "$col = ?";

        $sql = "UPDATE soggiorni_clienti SET " . implode(', ', $setParts) . " WHERE id = ? AND soggiorno_id = ?";
        $typesUpd = $types . 'ii';
        $valuesUpd = array_merge($values, [$guestId, $soggiornoId]);

        $stmt = $db->prepare($sql);
        if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);
        $stmt->bind_param($typesUpd, ...$valuesUpd);
        $stmt->execute();

        json_response(true, 'Ospite aggiornato', ['toast' => ['variant' => 'success']]);
    }

    // INSERT (con soggiorno_id SEMPRE)
    $columns = ['soggiorno_id'];
    $placeholders = ['?'];
    $typesIns = 'i';
    $valuesIns = [$soggiornoId];

    foreach ($fields as $col => $val) {
        $columns[] = $col;
        $placeholders[] = '?';
    }
    $typesIns .= $types;
    $valuesIns = array_merge($valuesIns, $values);

    $sql = "INSERT INTO soggiorni_clienti (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);
    $stmt->bind_param($typesIns, ...$valuesIns);
    $stmt->execute();

    json_response(true, 'Ospite creato', [
        'toast' => ['variant' => 'success'],
        'guest_id' => $stmt->insert_id
    ]);
}

function search_guests(mysqli $db, string $query): void {
    $query = trim($query);
    if ($query === '') json_response(true, 'OK', ['results' => []]);

    $fields = ['nome', 'cognome', 'email', 'documento_numero'];
    $conditions = [];
    $types = '';
    $values = [];

    foreach ($fields as $field) {
        if (column_exists($db, 'soggiorni_clienti', $field)) {
            $conditions[] = "$field LIKE ?";
            $types .= 's';
            $values[] = '%' . $query . '%';
        }
    }
    if (empty($conditions)) json_response(true, 'OK', ['results' => []]);

    $sql = "
        SELECT id, nome, cognome" .
        (column_exists($db, 'soggiorni_clienti', 'data_nascita') ? ", data_nascita" : "") .
        (column_exists($db, 'soggiorni_clienti', 'documento_numero') ? ", documento_numero" : "") . "
        FROM soggiorni_clienti
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY cognome ASC, nome ASC
        LIMIT 20
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);

    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    json_response(true, 'OK', ['results' => $rows]);
}

/**
 * "attach" nel tuo vecchio codice era pensato come tabella ponte.
 * Qui lo trasformiamo in: CLONA un ospite esistente dentro un altro soggiorno.
 */
function attach_guest(mysqli $db, int $soggiornoId, int $guestId): void {
    // prendo i campi clonabili se esistono
    $cloneCols = ['nome','cognome','data_nascita','nazionalita','indirizzo','documento_tipo','documento_numero','email','telefono','note'];
    $selectCols = [];
    foreach ($cloneCols as $c) {
        if (column_exists($db,'soggiorni_clienti',$c)) $selectCols[] = $c;
    }
    if (empty($selectCols)) json_response(false, 'Nessun campo clonabile');

    $sqlSel = "SELECT " . implode(',', $selectCols) . " FROM soggiorni_clienti WHERE id = ? LIMIT 1";
    $stmt = $db->prepare($sqlSel);
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);
    $stmt->bind_param('i', $guestId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) json_response(false, 'Ospite non trovato', [], 404);

    $columns = array_merge(['soggiorno_id'], $selectCols);
    $placeholders = array_fill(0, count($columns), '?');
    $types = 'i';
    $values = [$soggiornoId];

    foreach ($selectCols as $c) {
        $types .= 's';
        $values[] = (string)($row[$c] ?? '');
    }

    $sqlIns = "INSERT INTO soggiorni_clienti (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt2 = $db->prepare($sqlIns);
    if (!$stmt2) json_response(false, 'Errore DB: ' . $db->error, [], 500);
    $stmt2->bind_param($types, ...$values);
    $stmt2->execute();

    json_response(true, 'Ospite associato (clonato)', ['toast' => ['variant' => 'success'], 'guest_id' => $stmt2->insert_id]);
}

function delete_guest(mysqli $db, int $soggiornoId, int $guestId): void {
    assert_guest_belongs($db, $soggiornoId, $guestId);
    $stmt = $db->prepare("DELETE FROM soggiorni_clienti WHERE id = ? AND soggiorno_id = ? LIMIT 1");
    if (!$stmt) json_response(false, 'Errore DB: ' . $db->error, [], 500);
    $stmt->bind_param('ii', $guestId, $soggiornoId);
    $stmt->execute();
    json_response(true, 'Ospite rimosso', ['toast' => ['variant' => 'success']]);
}

$payload = get_payload();
$action = strtolower((string)($payload['action'] ?? $_GET['action'] ?? ''));

switch ($action) {
    case 'list':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        if ($soggiornoId <= 0) json_response(false, 'ID prenotazione mancante');
        list_guests($mysqli, $soggiornoId);
        break;

    case 'save_documenti':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $guestId = (int)($payload['cliente_id'] ?? 0); // compat: dal frontend arriva cliente_id
        if ($soggiornoId <= 0 || $guestId <= 0) json_response(false, 'Parametri mancanti');
        save_documents($mysqli, $soggiornoId, $guestId, $payload);
        break;

    case 'save_guest':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $guestId = isset($payload['cliente_id']) ? (int)$payload['cliente_id'] : null; // compat
        save_guest($mysqli, $soggiornoId, $guestId, $payload);
        break;

    case 'search':
        $query = (string)($payload['query'] ?? '');
        search_guests($mysqli, $query);
        break;

    case 'attach_guest':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $guestId = (int)($payload['cliente_id'] ?? 0); // compat
        if ($soggiornoId <= 0 || $guestId <= 0) json_response(false, 'Parametri mancanti');
        attach_guest($mysqli, $soggiornoId, $guestId);
        break;

    case 'delete_guest':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $guestId = (int)($payload['cliente_id'] ?? 0);
        if ($soggiornoId <= 0 || $guestId <= 0) json_response(false, 'Parametri mancanti');
        delete_guest($mysqli, $soggiornoId, $guestId);
        break;

    default:
        json_response(false, 'Azione non supportata', [], 400);
}
