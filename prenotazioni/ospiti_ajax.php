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
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

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
    $docTipo = column_exists($db, 'clienti', 'documento_tipo') ? ', c.documento_tipo' : '';
    $docNum = column_exists($db, 'clienti', 'documento_numero') ? ', c.documento_numero' : '';
    $docScad = column_exists($db, 'clienti', 'documento_scadenza') ? ', c.documento_scadenza' : '';
    $docRil = column_exists($db, 'clienti', 'documento_rilasciato_da') ? ', c.documento_rilasciato_da' : '';
    $docNote = column_exists($db, 'clienti', 'documento_note') ? ', c.documento_note' : '';
    $dataNascita = column_exists($db, 'clienti', 'data_nascita') ? ', c.data_nascita' : '';
    $nazionalita = column_exists($db, 'clienti', 'nazionalita') ? ', c.nazionalita' : '';
    $indirizzo = column_exists($db, 'clienti', 'indirizzo') ? ', c.indirizzo' : '';
    $email = column_exists($db, 'clienti', 'email') ? ', c.email' : '';
    $telefono = column_exists($db, 'clienti', 'telefono') ? ', c.telefono' : '';
    $note = column_exists($db, 'clienti', 'note') ? ', c.note' : '';

    $sql = "
        SELECT
            c.id,
            c.nome,
            c.cognome
            $docTipo
            $docNum
            $docScad
            $docRil
            $docNote
            $dataNascita
            $nazionalita
            $indirizzo
            $email
            $telefono
            $note
        FROM soggiorni_clienti sc
        JOIN clienti c ON c.id = sc.cliente_id
        WHERE sc.soggiorno_id = ?
        ORDER BY c.nome ASC, c.cognome ASC
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param('i', $soggiornoId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    json_response(true, 'OK', ['ospiti' => $rows]);
}

function save_documents(mysqli $db, int $soggiornoId, int $clienteId, array $payload): void {
    $stmtCheck = $db->prepare("
        SELECT 1
        FROM soggiorni_clienti
        WHERE soggiorno_id = ? AND cliente_id = ?
        LIMIT 1
    ");
    $stmtCheck?->bind_param('ii', $soggiornoId, $clienteId);
    $stmtCheck?->execute();
    $checkRes = $stmtCheck ? $stmtCheck->get_result() : null;
    if (!$checkRes || $checkRes->num_rows === 0) {
        json_response(false, 'Ospite non associato a questa prenotazione', [], 403);
    }

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
        if (array_key_exists($field, $payload) && column_exists($db, 'clienti', $field)) {
            $fields[] = "$field = ?";
            $types .= $type;
            $values[] = (string)$payload[$field];
        }
    }

    if (empty($fields)) {
        json_response(false, 'Nessun campo documento aggiornabile');
    }

    $sql = "UPDATE clienti SET " . implode(', ', $fields) . " WHERE id = ?";
    $types .= 'i';
    $values[] = $clienteId;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    json_response(true, 'Documenti salvati', ['toast' => ['variant' => 'success']]);
}

function save_guest(mysqli $db, int $soggiornoId, ?int $clienteId, array $payload): void {
    if ($soggiornoId <= 0) {
        json_response(false, 'Prenotazione mancante');
    }

    $fields = [];
    $types = '';
    $values = [];
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

    foreach ($map as $field => $type) {
        if (array_key_exists($field, $payload) && column_exists($db, 'clienti', $field)) {
            $fields[] = "$field = ?";
            $types .= $type;
            $values[] = (string)$payload[$field];
        }
    }

    if (empty($fields)) {
        json_response(false, 'Nessun campo da salvare');
    }

    if ($clienteId && $clienteId > 0) {
        $stmtCheck = $db->prepare("
            SELECT 1
            FROM soggiorni_clienti
            WHERE soggiorno_id = ? AND cliente_id = ?
            LIMIT 1
        ");
        $stmtCheck?->bind_param('ii', $soggiornoId, $clienteId);
        $stmtCheck?->execute();
        $checkRes = $stmtCheck ? $stmtCheck->get_result() : null;
        if (!$checkRes || $checkRes->num_rows === 0) {
            json_response(false, 'Ospite non associato a questa prenotazione', [], 403);
        }

        $sql = "UPDATE clienti SET " . implode(', ', $fields) . " WHERE id = ?";
        $types .= 'i';
        $values[] = $clienteId;
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            json_response(false, 'Errore DB: ' . $db->error, [], 500);
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        json_response(true, 'Ospite aggiornato', ['toast' => ['variant' => 'success']]);
    }

    $columns = array_map(fn($f) => strtok($f, ' '), $fields);
    $sql = "INSERT INTO clienti (" . implode(',', $columns) . ") VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $newClienteId = $stmt->insert_id;

    $stmtLink = $db->prepare("INSERT INTO soggiorni_clienti (soggiorno_id, cliente_id) VALUES (?, ?)");
    if ($stmtLink) {
        $stmtLink->bind_param('ii', $soggiornoId, $newClienteId);
        $stmtLink->execute();
    }

    json_response(true, 'Ospite creato', ['toast' => ['variant' => 'success']]);
}

function search_guests(mysqli $db, string $query): void {
    $query = trim($query);
    if ($query === '') {
        json_response(true, 'OK', ['results' => []]);
    }

    $fields = ['nome', 'cognome', 'email', 'documento_numero'];
    $conditions = [];
    $types = '';
    $values = [];
    foreach ($fields as $field) {
        if (column_exists($db, 'clienti', $field)) {
            $conditions[] = "$field LIKE ?";
            $types .= 's';
            $values[] = '%' . $query . '%';
        }
    }
    if (empty($conditions)) {
        json_response(true, 'OK', ['results' => []]);
    }

    $sql = "
        SELECT id, nome, cognome" .
        (column_exists($db, 'clienti', 'data_nascita') ? ", data_nascita" : "") .
        (column_exists($db, 'clienti', 'documento_numero') ? ", documento_numero" : "") . "
        FROM clienti
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY cognome ASC, nome ASC
        LIMIT 20
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    json_response(true, 'OK', ['results' => $rows]);
}

function attach_guest(mysqli $db, int $soggiornoId, int $clienteId): void {
    $stmtCheck = $db->prepare("
        SELECT 1
        FROM soggiorni_clienti
        WHERE soggiorno_id = ? AND cliente_id = ?
        LIMIT 1
    ");
    $stmtCheck?->bind_param('ii', $soggiornoId, $clienteId);
    $stmtCheck?->execute();
    $checkRes = $stmtCheck ? $stmtCheck->get_result() : null;
    if ($checkRes && $checkRes->num_rows > 0) {
        json_response(true, 'Ospite già associato', ['toast' => ['variant' => 'info']]);
    }

    $stmt = $db->prepare("INSERT INTO soggiorni_clienti (soggiorno_id, cliente_id) VALUES (?, ?)");
    if (!$stmt) {
        json_response(false, 'Errore DB: ' . $db->error, [], 500);
    }
    $stmt->bind_param('ii', $soggiornoId, $clienteId);
    $stmt->execute();
    json_response(true, 'Ospite associato', ['toast' => ['variant' => 'success']]);
}

$payload = get_payload();
$action = strtolower((string)($payload['action'] ?? $_GET['action'] ?? ''));

switch ($action) {
    case 'list':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        if ($soggiornoId <= 0) {
            json_response(false, 'ID prenotazione mancante');
        }
        list_guests($mysqli, $soggiornoId);
        break;

    case 'save_documenti':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $clienteId = (int)($payload['cliente_id'] ?? 0);
        if ($soggiornoId <= 0 || $clienteId <= 0) {
            json_response(false, 'Parametri mancanti');
        }
        save_documents($mysqli, $soggiornoId, $clienteId, $payload);
        break;

    case 'save_guest':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $clienteId = isset($payload['cliente_id']) ? (int)$payload['cliente_id'] : null;
        save_guest($mysqli, $soggiornoId, $clienteId, $payload);
        break;

    case 'search':
        $query = (string)($payload['query'] ?? '');
        search_guests($mysqli, $query);
        break;

    case 'attach_guest':
        $soggiornoId = (int)($payload['soggiorno_id'] ?? 0);
        $clienteId = (int)($payload['cliente_id'] ?? 0);
        if ($soggiornoId <= 0 || $clienteId <= 0) {
            json_response(false, 'Parametri mancanti');
        }
        attach_guest($mysqli, $soggiornoId, $clienteId);
        break;

    default:
        json_response(false, 'Azione non supportata', [], 400);
}
