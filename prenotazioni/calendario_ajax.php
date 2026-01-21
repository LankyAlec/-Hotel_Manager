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
$pianoId    = (int)($_GET['piano_id'] ?? 0);
$startInput = (string)($_GET['start'] ?? date('Y-m-d'));
$days       = (int)($_GET['days'] ?? 14);

if ($days < 1 || $days > 31) $days = 14;

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime();
$startDate->setTime(0, 0, 0);
$endDate = (clone $startDate)->modify('+' . $days . ' days');

$daysList = [];
for ($i = 0; $i < $days; $i++) {
    $daysList[] = (clone $startDate)->modify('+' . $i . ' days')->format('Y-m-d');
}

/* === ROOMS === */
$hasAccessibile = column_exists($mysqli, 'struttura_camere', 'accessibile_disabili');
$hasAttiva      = column_exists($mysqli, 'struttura_camere', 'attiva');
$hasNote        = column_exists($mysqli, 'struttura_camere', 'note');

$selectAccessibile = $hasAccessibile ? ', c.accessibile_disabili' : ', 0 AS accessibile_disabili';
$selectAttiva      = $hasAttiva      ? ', c.attiva AS attiva'      : ', 1 AS attiva';
$selectNote        = $hasNote        ? ', c.note'                  : ', NULL AS note';

$sqlRooms = "
    SELECT
        c.id, c.codice{$selectAttiva}{$selectAccessibile}{$selectNote},
        p.id AS piano_id, p.nome AS piano_nome, p.livello,
        e.id AS edificio_id, e.nome AS edificio_nome
    FROM struttura_camere c
    JOIN struttura_piani p ON p.id = c.piano_id AND p.attivo = 1
    JOIN struttura_edifici e ON e.id = p.edificio_id AND e.attivo = 1
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
    json_response(false, 'Errore DB (rooms): ' . $mysqli->error, [], 500);
}
if ($bind) bind_params($stmtRooms, $types, $bind);

$stmtRooms->execute();
$resRooms = $stmtRooms->get_result();
$rooms = $resRooms ? $resRooms->fetch_all(MYSQLI_ASSOC) : [];
$stmtRooms->close();

$roomIds = array_map('intval', array_column($rooms, 'id'));

$manutenzioni = [];
$pulizie = [];
$bookings = [];

if ($roomIds) {
    $ph = implode(',', array_fill(0, count($roomIds), '?'));
    $commonTypes = str_repeat('i', count($roomIds));

    /* === MANUT === */
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
            $stmtMan->close();
        }
    }

    /* === PULIZIE === */
    if (table_exists($mysqli, 'pulizie_task') && column_exists($mysqli, 'pulizie_task', 'stato')) {
        $sqlPul = "
            SELECT camera_id, stato
            FROM pulizie_task
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
            $stmtPul->close();
        }
    }

    /* === BOOKINGS: scegli tabella === */
    $bookingTable = null;
    if (table_exists($mysqli, 'prenotazioni') && column_exists($mysqli, 'prenotazioni', 'camera_id')) {
        $bookingTable = 'prenotazioni';
    } elseif (table_exists($mysqli, 'soggiorni') && column_exists($mysqli, 'soggiorni', 'camera_id')) {
        $bookingTable = 'soggiorni';
    }

    if ($bookingTable) {
        $hasCodice = column_exists($mysqli, $bookingTable, 'codice');
        $hasStato  = column_exists($mysqli, $bookingTable, 'stato');
        $hasReferente = column_exists($mysqli, $bookingTable, 'referente');
        $hasPasto = column_exists($mysqli, $bookingTable, 'piano_pasto_sigla');
        $hasHb = column_exists($mysqli, $bookingTable, 'hb_servizio');
        $hasHbDa = column_exists($mysqli, $bookingTable, 'hb_da');
        $hasHbA = column_exists($mysqli, $bookingTable, 'hb_a');
        $hasHousekeeping = column_exists($mysqli, $bookingTable, 'housekeeping')
            || column_exists($mysqli, $bookingTable, 'housekeeping_qty')
            || column_exists($mysqli, $bookingTable, 'housekeeping_qta');
        $hasHbDettagli = column_exists($mysqli, $bookingTable, 'hb_dettagli')
            || column_exists($mysqli, $bookingTable, 'hb_dettagli_json');
        $hasNote = column_exists($mysqli, $bookingTable, 'note');
        $hasPastoNote = column_exists($mysqli, $bookingTable, 'note_pasti')
            || column_exists($mysqli, $bookingTable, 'note_pasto')
            || column_exists($mysqli, $bookingTable, 'pasto_note');
        $hasTipoCamera = column_exists($mysqli, $bookingTable, 'tipologia_camera')
            || column_exists($mysqli, $bookingTable, 'tipo_camera')
            || column_exists($mysqli, $bookingTable, 'camera_tipo');

        $selectCodice = $hasCodice ? ', b.codice' : ', NULL AS codice';
        $selectStato  = $hasStato  ? ', b.stato'  : ', NULL AS stato';

        $canFallback = table_exists($mysqli, 'soggiorni_clienti')
            && column_exists($mysqli, 'soggiorni_clienti', 'soggiorno_id')
            && column_exists($mysqli, 'soggiorni_clienti', 'nome')
            && column_exists($mysqli, 'soggiorni_clienti', 'cognome');

        // ✅ referente: se esiste colonna, usa b.referente, altrimenti fallback su soggiorni_clienti
        if ($hasReferente && $canFallback) {
            $selectReferente = ",
                COALESCE(
                    NULLIF(b.referente, ''),
                    (
                        SELECT TRIM(CONCAT(COALESCE(sc.cognome,''), ' ', COALESCE(sc.nome,'')))
                        FROM soggiorni_clienti sc
                        WHERE sc.soggiorno_id = b.id
                        ORDER BY sc.id ASC
                        LIMIT 1
                    )
                ) AS referente
            ";
        } elseif ($hasReferente) {
            $selectReferente = ", b.referente AS referente";
        } elseif ($canFallback) {
            $selectReferente = ",
                (
                    SELECT TRIM(CONCAT(COALESCE(sc.cognome,''), ' ', COALESCE(sc.nome,'')))
                    FROM soggiorni_clienti sc
                    WHERE sc.soggiorno_id = b.id
                    ORDER BY sc.id ASC
                    LIMIT 1
                ) AS referente
            ";
        } else {
            $selectReferente = ", NULL AS referente";
        }

        $selectPasto = $hasPasto ? ', b.piano_pasto_sigla' : ', NULL AS piano_pasto_sigla';
        $selectHb = $hasHb ? ', b.hb_servizio' : ', NULL AS hb_servizio';
        $selectHbDa = $hasHbDa ? ', b.hb_da' : ', NULL AS hb_da';
        $selectHbA = $hasHbA ? ', b.hb_a' : ', NULL AS hb_a';
        if ($hasHousekeeping) {
            if (column_exists($mysqli, $bookingTable, 'housekeeping')) {
                $selectHousekeeping = ', b.housekeeping AS housekeeping';
            } elseif (column_exists($mysqli, $bookingTable, 'housekeeping_qty')) {
                $selectHousekeeping = ', b.housekeeping_qty AS housekeeping';
            } else {
                $selectHousekeeping = ', b.housekeeping_qta AS housekeeping';
            }
        } else {
            $selectHousekeeping = ', NULL AS housekeeping';
        }
        if ($hasHbDettagli) {
            if (column_exists($mysqli, $bookingTable, 'hb_dettagli')) {
                $selectHbDettagli = ', b.hb_dettagli AS hb_dettagli';
            } else {
                $selectHbDettagli = ', b.hb_dettagli_json AS hb_dettagli';
            }
        } else {
            $selectHbDettagli = ', NULL AS hb_dettagli';
        }
        $selectNote = $hasNote ? ', b.note' : ', NULL AS note';
        if ($hasPastoNote) {
            if (column_exists($mysqli, $bookingTable, 'note_pasti')) {
                $selectPastoNote = ', b.note_pasti AS note_pasti';
            } elseif (column_exists($mysqli, $bookingTable, 'note_pasto')) {
                $selectPastoNote = ', b.note_pasto AS note_pasti';
            } else {
                $selectPastoNote = ', b.pasto_note AS note_pasti';
            }
        } else {
            $selectPastoNote = ', NULL AS note_pasti';
        }
        if ($hasTipoCamera) {
            if (column_exists($mysqli, $bookingTable, 'tipologia_camera')) {
                $selectTipoCamera = ', b.tipologia_camera AS tipologia_camera';
            } elseif (column_exists($mysqli, $bookingTable, 'tipo_camera')) {
                $selectTipoCamera = ', b.tipo_camera AS tipologia_camera';
            } else {
                $selectTipoCamera = ', b.camera_tipo AS tipologia_camera';
            }
        } else {
            $selectTipoCamera = ', NULL AS tipologia_camera';
        }
        if (column_exists($mysqli, $bookingTable, 'servizi_json')) {
            $selectServizi = ', b.servizi_json';
        } elseif (column_exists($mysqli, $bookingTable, 'servizi')) {
            $selectServizi = ', b.servizi';
        } else {
            $selectServizi = ', NULL AS servizi_json';
        }

        $sqlBook = "
            SELECT
                b.id, b.camera_id, b.data_checkin, b.data_checkout
                {$selectCodice}
                {$selectStato}
                {$selectReferente}
                {$selectPasto}
                {$selectHb}
                {$selectHbDa}
                {$selectHbA}
                {$selectHousekeeping}
                {$selectHbDettagli}
                {$selectNote}
                {$selectPastoNote}
                {$selectTipoCamera}
                {$selectServizi}
            FROM {$bookingTable} b
            WHERE b.camera_id IN ({$ph})
              AND NOT (? >= b.data_checkout OR ? <= b.data_checkin)
        ";

        if ($hasStato) {
            $sqlBook .= " AND b.stato IN ('prenotato','occupato')";
        }

        $stmtBook = $mysqli->prepare($sqlBook);
        if ($stmtBook) {
            $paramsBook = array_merge($roomIds, [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
            bind_params($stmtBook, $commonTypes . 'ss', $paramsBook);

            $stmtBook->execute();
            $resBook = $stmtBook->get_result();
            while ($row = $resBook->fetch_assoc()) {
                $serviziRaw = $row['servizi_json'] ?? $row['servizi'] ?? null;
                $serviziParsed = [];
                if (is_string($serviziRaw) && $serviziRaw !== '') {
                    $decoded = json_decode($serviziRaw, true);
                    if (is_array($decoded)) $serviziParsed = $decoded;
                }
                $bookings[] = [
                    'id'       => (int)$row['id'],
                    'camera_id' => (int)$row['camera_id'],
                    'checkin'   => (string)$row['data_checkin'],
                    'checkout'  => (string)$row['data_checkout'],
                    'codice'    => (string)($row['codice'] ?? ''),
                    'stato'     => (string)($row['stato'] ?? ''),
                    'referente' => (string)($row['referente'] ?? ''),
                    'pasto' => (string)($row['piano_pasto_sigla'] ?? ''),
                    'hb_servizio' => (string)($row['hb_servizio'] ?? ''),
                    'hb_da' => (string)($row['hb_da'] ?? ''),
                    'hb_a' => (string)($row['hb_a'] ?? ''),
                    'housekeeping' => $row['housekeeping'] !== null ? (int)$row['housekeeping'] : null,
                    'hb_dettagli' => (string)($row['hb_dettagli'] ?? ''),
                    'note' => (string)($row['note'] ?? ''),
                    'note_pasti' => (string)($row['note_pasti'] ?? ''),
                    'tipologia_camera' => (string)($row['tipologia_camera'] ?? ''),
                    'servizi' => $serviziParsed,
                ];
            }
            $stmtBook->close();
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
