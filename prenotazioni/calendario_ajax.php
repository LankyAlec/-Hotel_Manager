<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Non autorizzato']);
    exit;
}

function json_response(bool $ok, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function table_exists(mysqli $db, string $table): bool
{
    $tableEsc = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$tableEsc}'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $column): bool
{
    $tableEsc = $db->real_escape_string($table);
    $colEsc = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
    return $res && $res->num_rows > 0;
}

function bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    $stmt->bind_param($types, ...$refs);
}

$edificioId = (int)($_GET['edificio_id'] ?? 0);
$pianoId = (int)($_GET['piano_id'] ?? 0);
$startInput = (string)($_GET['start'] ?? date('Y-m-d'));
$days = (int)($_GET['days'] ?? 14);

if ($days < 1 || $days > 31) {
    $days = 14;
}

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime();
$startDate->setTime(0, 0, 0);
$endDate = (clone $startDate)->modify('+' . $days . ' days');

$daysList = [];
for ($i = 0; $i < $days; $i++) {
    $daysList[] = (clone $startDate)->modify('+' . $i . ' days')->format('Y-m-d');
}

$hasAccessibile = column_exists($mysqli, 'camere', 'accessibile_disabili');
$hasAttiva = column_exists($mysqli, 'camere', 'attiva');
$hasNote = column_exists($mysqli, 'camere', 'note');

$selectAccessibile = $hasAccessibile ? ', c.accessibile_disabili' : ', 0 AS accessibile_disabili';
$selectAttiva = $hasAttiva ? ', c.attiva' : ', 1 AS attiva';
$selectNote = $hasNote ? ', c.note' : ', NULL AS note';

$sqlRooms = "
    SELECT
        c.id, c.codice{$selectAttiva}{$selectAccessibile}{$selectNote},
        p.id AS piano_id, p.nome AS piano_nome, p.livello,
        e.id AS edificio_id, e.nome AS edificio_nome
    FROM camere c
    JOIN piani p ON p.id = c.piano_id AND p.attivo = 1
    JOIN edifici e ON e.id = p.edificio_id AND e.attivo = 1
    WHERE 1=1
";

$bind = [];
$types = '';

if ($pianoId > 0) {
    $sqlRooms .= " AND c.piano_id = ?";
    $bind[] = $pianoId;
    $types .= 'i';
} elseif ($edificioId > 0) {
    $sqlRooms .= " AND p.edificio_id = ?";
    $bind[] = $edificioId;
    $types .= 'i';
}

$sqlRooms .= " ORDER BY p.livello ASC, c.codice ASC";

$stmtRooms = $mysqli->prepare($sqlRooms);
if (!$stmtRooms) {
    json_response(false, 'Errore DB: ' . $mysqli->error, [], 500);
}

if ($bind) {
    bind_params($stmtRooms, $types, $bind);
}

$stmtRooms->execute();
$resRooms = $stmtRooms->get_result();
$rooms = $resRooms ? $resRooms->fetch_all(MYSQLI_ASSOC) : [];

$roomIds = array_map('intval', array_column($rooms, 'id'));

$manutenzioni = [];
$pulizie = [];
$bookings = [];

if ($roomIds) {
    $ph = implode(',', array_fill(0, count($roomIds), '?'));
    $commonTypes = str_repeat('i', count($roomIds));

    if (table_exists($mysqli, 'ticket_manutenzione') && column_exists($mysqli, 'ticket_manutenzione', 'stato')) {
        $sqlMan = "
            SELECT camera_id, stato
            FROM ticket_manutenzione
            WHERE camera_id IN ({$ph})
              AND stato <> 'RISOLTO'
        ";
        $stmtMan = $mysqli->prepare($sqlMan);
        if ($stmtMan) {
            bind_params($stmtMan, $commonTypes, $roomIds);
            $stmtMan->execute();
            $resMan = $stmtMan->get_result();
            while ($row = $resMan->fetch_assoc()) {
                $manutenzioni[] = [
                    'camera_id' => (int)$row['camera_id'],
                    'stato' => (string)$row['stato'],
                ];
            }
        }
    }

    if (table_exists($mysqli, 'task_pulizie') && column_exists($mysqli, 'task_pulizie', 'stato')) {
        $sqlPul = "
            SELECT camera_id, stato
            FROM task_pulizie
            WHERE camera_id IN ({$ph})
              AND stato <> 'RISOLTO'
        ";
        $stmtPul = $mysqli->prepare($sqlPul);
        if ($stmtPul) {
            bind_params($stmtPul, $commonTypes, $roomIds);
            $stmtPul->execute();
            $resPul = $stmtPul->get_result();
            while ($row = $resPul->fetch_assoc()) {
                $pulizie[] = [
                    'camera_id' => (int)$row['camera_id'],
                    'stato' => (string)$row['stato'],
                ];
            }
        }
    }

    $bookingTable = null;
    if (table_exists($mysqli, 'prenotazioni') && column_exists($mysqli, 'prenotazioni', 'camera_id')) {
        $bookingTable = 'prenotazioni';
    } elseif (table_exists($mysqli, 'soggiorni')) {
        $bookingTable = 'soggiorni';
    }

    if ($bookingTable) {
        $hasCodice = column_exists($mysqli, $bookingTable, 'codice');
        $hasStato = column_exists($mysqli, $bookingTable, 'stato');
        $selectCodice = $hasCodice ? ', b.codice' : ', NULL AS codice';
        $selectStato = $hasStato ? ', b.stato' : ', NULL AS stato';

        $sqlBook = "
            SELECT b.id, b.camera_id, b.data_checkin, b.data_checkout{$selectCodice}{$selectStato}
            FROM {$bookingTable} b
            WHERE b.camera_id IN ({$ph})
              AND NOT (? >= b.data_checkout OR ? <= b.data_checkin)
        ";

        if ($hasStato) {
            $sqlBook .= " AND b.stato IN ('prenotato','occupato')";
        }

        $stmtBook = $mysqli->prepare($sqlBook);
        if ($stmtBook) {
            $paramsBook = array_merge([$startDate->format('Y-m-d'), $endDate->format('Y-m-d')], $roomIds);
            bind_params($stmtBook, 'ss' . $commonTypes, $paramsBook);
            $stmtBook->execute();
            $resBook = $stmtBook->get_result();
            while ($row = $resBook->fetch_assoc()) {
                $bookings[] = [
                    'id' => (int)$row['id'],
                    'camera_id' => (int)$row['camera_id'],
                    'checkin' => (string)$row['data_checkin'],
                    'checkout' => (string)$row['data_checkout'],
                    'codice' => (string)($row['codice'] ?? ''),
                    'stato' => (string)($row['stato'] ?? ''),
                ];
            }
        }
    }
}

json_response(true, 'OK', [
    'days' => $daysList,
    'start' => $startDate->format('Y-m-d'),
    'end' => $endDate->format('Y-m-d'),
    'rooms' => $rooms,
    'manutenzioni' => $manutenzioni,
    'pulizie' => $pulizie,
    'bookings' => $bookings,
]);
