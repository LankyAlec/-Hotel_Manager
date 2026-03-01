-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Creato il: Mar 01, 2026 alle 10:16
-- Versione del server: 10.11.11-MariaDB
-- Versione PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `Hotel`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `city_tax`
--

CREATE TABLE `city_tax` (
  `id` int(11) NOT NULL,
  `costo` decimal(10,2) NOT NULL,
  `esenzione_per_eta_fino` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `city_tax`
--

INSERT INTO `city_tax` (`id`, `costo`, `esenzione_per_eta_fino`) VALUES
(1, 3.00, 8);

-- --------------------------------------------------------

--
-- Struttura della tabella `gruppi_arrivi`
--

CREATE TABLE `gruppi_arrivi` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_gruppo` varchar(120) NOT NULL,
  `referente` varchar(120) NOT NULL,
  `agenzia` varchar(120) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `data_arrivo` date DEFAULT NULL,
  `data_partenza` date DEFAULT NULL,
  `numero_persone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `numero_adulti` int(10) UNSIGNED DEFAULT NULL,
  `numero_bambini` int(10) UNSIGNED DEFAULT NULL,
  `camere_json` longtext DEFAULT NULL,
  `trattamento` varchar(10) DEFAULT NULL,
  `note_operativa` text DEFAULT NULL,
  `pasti_json` longtext DEFAULT NULL,
  `extra_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note_ricevimento` text DEFAULT NULL,
  `note_cucina` text DEFAULT NULL,
  `note_disposizione_tavoli` text DEFAULT NULL,
  `note_housekeeping` text DEFAULT NULL,
  `note_manutenzione` text DEFAULT NULL,
  `checkin_orario` time DEFAULT NULL,
  `aree_riservate_json` longtext DEFAULT NULL,
  `note_allergie` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `gruppi_arrivi`
--

INSERT INTO `gruppi_arrivi` (`id`, `nome_gruppo`, `referente`, `agenzia`, `telefono`, `email`, `data_arrivo`, `data_partenza`, `numero_persone`, `numero_adulti`, `numero_bambini`, `camere_json`, `trattamento`, `note_operativa`, `pasti_json`, `extra_json`, `created_at`, `updated_at`, `note_ricevimento`, `note_cucina`, `note_disposizione_tavoli`, `note_housekeeping`, `note_manutenzione`, `checkin_orario`, `aree_riservate_json`, `note_allergie`) VALUES
(4, 'Extravaganze', 'D\'Alcantari Samuele', '', '', '', '2026-01-28', '2026-02-01', 47, 47, 0, '{\"Matrimoniale\":5,\"Dus\":10,\"Twin\":1,\"Tripla\":2,\"Matrimoniale + Lettino\":3,\"Family 5\":1,\"Family 6\":1}', 'FB', 'I clienti hanno incluso l\'utilizzo della piscina', '[{\"data\":\"2026-01-28\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"10\",\"note\":\"Risotto carnaroli con zucca e crema di parmiggiano\\r\\nRiso in bianco \\r\\nZuppa di lenticchie\\r\\nPasta fredda con pesto di basilico e mandorle\\r\\nUova sode condite\\r\\nCaponata di melanzane\\r\\nFagiolini al vapore\\r\\nHummus di ceci\\r\\nPane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-29\",\"tipo\":\"Colazione\",\"ora\":\"08:30\",\"sala_ristorante\":\"10\",\"note\":\"Croissant \\r\\nCrostate\\r\\nPlumcake\\r\\nBiscotti (anche senza lattosio)\\r\\nFette biscottate (anche integrali)\\r\\nMarmellate\\r\\nSucchi \\r\\nCereali\\r\\nFrutta\\r\\nYogurt (anche senza lattosio)\\r\\nProsciutto\\r\\nFormaggio\\r\\nUova sode\\r\\nPancarrè\"},{\"data\":\"2026-01-29\",\"tipo\":\"Pranzo\",\"ora\":\"13:00\",\"sala_ristorante\":\"10\",\"note\":\"Risotto mantecato alla barbabietola e scaglie di parmigiano\\r\\nRiso basmati in bianco\\r\\nQuadrucci di patate alla curcuma\\r\\nMelanzane grigliate\\r\\nCavolfiore\\r\\nBroccoli gratinati con besciamella vegetale leggera\\r\\nPane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-29\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"10\",\"note\":\"Zuppa di fagioli\\r\\nLasagne alle verdure\\r\\nRiso in bianco\\r\\nSformato di uova con patate\\r\\nZucchine cipolle rosse grigliate e condite\\r\\nInsalata di finocchi e arance con olive nere e prezzemolo\\r\\nInsalata mista\\r\\nPane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-30\",\"tipo\":\"Colazione\",\"ora\":\"08:30\",\"sala_ristorante\":\"10\",\"note\":\"Croissant \\r\\nCrostate\\r\\nPlumcake\\r\\nBiscotti (anche senza lattosio)\\r\\nFette biscottate (anche integrali)\\r\\nMarmellate\\r\\nSucchi \\r\\nCereali\\r\\nFrutta\\r\\nYogurt (anche senza lattosio)\\r\\nProsciutto\\r\\nFormaggio\\r\\nUova sode\\r\\nPancarrè\"},{\"data\":\"2026-01-30\",\"tipo\":\"Pranzo\",\"ora\":\"13:30\",\"sala_ristorante\":\"10\",\"note\":\"Zuppa di ceci\\r\\nFarro perlato con frutta e verdure\\r\\nRiso in bianco\\r\\nCrema di patate con noce moscata\\r\\nZucchine cipolle rosse grigliate\\r\\nParmigiana di melanzane\\r\\nPane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-30\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"10\",\"note\":\"Zuppa di lenticchie\\r\\nRiso in bianco\\r\\nMozzarelle\\r\\nFinocchi gratinati\\r\\nZucca in agrodolce\\r\\nInsalata di pomodoro e mais dolce\\r\\nPane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-31\",\"tipo\":\"Colazione\",\"ora\":\"08:30\",\"sala_ristorante\":\"10\",\"note\":\"Pane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-31\",\"tipo\":\"Pranzo\",\"ora\":\"13:30\",\"sala_ristorante\":\"10\",\"note\":\"Pane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-31\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"10\",\"note\":\"Pane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-01-31\",\"tipo\":\"Colazione\",\"ora\":\"08:30\",\"sala_ristorante\":\"10\",\"note\":\"Pane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"},{\"data\":\"2026-02-01\",\"tipo\":\"Pranzo\",\"ora\":\"13:30\",\"sala_ristorante\":\"10\",\"note\":\"Pane, Gallette di riso Creckers integrali e Grissini\\r\\nFrutta aresca \\r\\nAcqua aromatizzata con cetriolo limone e menta\\r\\nSucci di frutta non zuccherati \\r\\nAcqua\\r\\nVino\"}]', '[]', '2026-01-26 18:55:56', '2026-01-26 19:43:16', 'Richiedere documenti al CHECK IN\r\nLe  quote saldate con il POS che vanno conservate a parte rispetto agli altri clienti presenti in Hotel.', '', '', '', '', NULL, '[1,7]', ''),
(5, 'Gruppo Studenti', 'Autista', 'Rete Vacanze', '3890549540', 'group@retevacanze.it', '2026-03-14', '2026-03-16', 59, 7, 52, '{\"Dus\":8,\"Twin\":4,\"Tripla\":14}', 'HB', 'Gita scolastica', '[{\"data\":\"2026-03-14\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"4\",\"note\":\"Pasta al pesto di basilico\\r\\nCotoletta di maiale\\r\\nCarote e piselli\\r\\nFrutta\\r\\nAcqua\"},{\"data\":\"2026-03-15\",\"tipo\":\"Colazione\",\"ora\":\"08:00\",\"sala_ristorante\":\"4\",\"note\":\"Dolce\"},{\"data\":\"2026-03-15\",\"tipo\":\"Cena\",\"ora\":\"20:30\",\"sala_ristorante\":\"4\",\"note\":\"Pasta al pomodoro\\r\\nPollo al forno con patate\\r\\nTorta al cioccolato\\r\\nAcqua\"},{\"data\":\"2026-03-16\",\"tipo\":\"Colazione\",\"ora\":\"08:00\",\"sala_ristorante\":\"4\",\"note\":\"Dolci\"}]', '[{\"data\":\"2026-03-14\",\"descrizione\":\"Corpo Tondo\",\"ora\":\"22:00\",\"note\":\"Giochi, quiz e musica\"},{\"data\":\"2026-03-15\",\"descrizione\":\"Corpo Tondo\",\"ora\":\"22:00\",\"note\":\"Giochi, quiz e musica\"}]', '2026-02-25 12:02:10', '2026-02-25 17:44:45', 'prova 1', 'prova 2', '1 tavolo da 6 (Docenti)\r\n1 tavolo da 1 (Autista)\r\n52 bambini', 'prova 3', 'prova 4', NULL, '[7]', '4 vegetariani\r\n5 no maiale\r\n2 no manzo\r\n2 halal\r\n1 no lattosio\r\n1 no verdure sotterranee (ravanello, cipolla, aglio, patata, funghi, carota, zenzero, ecc)');

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzini`
--

CREATE TABLE `magazzini` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `magazzini`
--

INSERT INTO `magazzini` (`id`, `nome`, `note`, `attivo`, `created_at`) VALUES
(1, 'Ristorante', 'Cucina e dispensa', 1, '2025-12-25 17:35:41'),
(2, 'Bar', 'Bevande e miscelazione', 1, '2025-12-25 17:35:41'),
(3, 'Hotel / Housekeeping', 'Amenities e pulizie', 1, '2025-12-25 17:35:41'),
(4, 'Deposito', 'Scorta generale', 1, '2025-12-25 17:35:41');

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_categorie`
--

CREATE TABLE `magazzino_categorie` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('alcolici','analcolici','food','non_food','altro') NOT NULL DEFAULT 'altro',
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `magazzino_categorie`
--

INSERT INTO `magazzino_categorie` (`id`, `nome`, `tipo`, `attivo`, `created_at`) VALUES
(1, 'Vini rossi', 'alcolici', 1, '2025-12-25 17:35:41'),
(2, 'Vini bianchi', 'alcolici', 1, '2025-12-25 17:35:41'),
(3, 'Spumanti / Champagne', 'alcolici', 1, '2025-12-25 17:35:41'),
(4, 'Birre', 'alcolici', 1, '2025-12-25 17:35:41'),
(5, 'Amari e digestivi', 'alcolici', 1, '2025-12-25 17:35:41'),
(6, 'Distillati (whisky, rum, gin, vodka)', 'alcolici', 1, '2025-12-25 17:35:41'),
(7, 'Liquori e aperitivi', 'alcolici', 1, '2025-12-25 17:35:41'),
(8, 'Cocktail premix', 'alcolici', 1, '2025-12-25 17:35:41'),
(9, 'Acque', 'analcolici', 1, '2025-12-25 17:35:41'),
(10, 'Bibite gassate', 'analcolici', 1, '2025-12-25 17:35:41'),
(11, 'Succhi e nettari', 'analcolici', 1, '2025-12-25 17:35:41'),
(12, 'Energy drink', 'analcolici', 1, '2025-12-25 17:35:41'),
(13, 'Tè / infusi pronti', 'analcolici', 1, '2025-12-25 17:35:41'),
(14, 'Sciroppi e mixer', 'analcolici', 1, '2025-12-25 17:35:41'),
(15, 'Caffè / capsule', 'analcolici', 1, '2025-12-25 17:35:41'),
(16, 'Zucchero / dolcificanti', 'food', 1, '2025-12-25 17:35:41'),
(17, 'Pasta / riso / cereali', 'food', 1, '2025-12-25 17:35:41'),
(18, 'Conserve / sottoli', 'food', 1, '2025-12-25 17:35:41'),
(19, 'Spezie / aromi / condimenti', 'food', 1, '2025-12-25 17:35:41'),
(20, 'Olio / aceti', 'food', 1, '2025-12-25 17:35:41'),
(21, 'Latticini', 'food', 1, '2025-12-25 17:35:41'),
(22, 'Carni', 'food', 1, '2025-12-25 17:35:41'),
(23, 'Pesce', 'food', 1, '2025-12-25 17:35:41'),
(24, 'Verdure', 'food', 1, '2025-12-25 17:35:41'),
(25, 'Surgelati', 'food', 1, '2025-12-25 17:35:41'),
(26, 'Pane / farine', 'food', 1, '2025-12-25 17:35:41'),
(27, 'Dolci / dessert', 'food', 1, '2025-12-25 17:35:41'),
(28, 'Colazione', 'food', 1, '2025-12-25 17:35:41'),
(29, 'Amenities hotel (saponi, cuffie, kit)', 'non_food', 1, '2025-12-25 17:35:41'),
(30, 'Pulizie (detergenti, carta)', 'non_food', 1, '2025-12-25 17:35:41'),
(31, 'Monouso (bicchieri, tovaglie, posate)', 'non_food', 1, '2025-12-25 17:35:41'),
(32, 'Biancheria (lenzuola, asciugamani)', 'non_food', 1, '2025-12-25 17:35:41'),
(33, 'Manutenzione (lampadine, batterie)', 'non_food', 1, '2025-12-25 17:35:41');

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_destinazioni`
--

CREATE TABLE `magazzino_destinazioni` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `magazzino_destinazioni`
--

INSERT INTO `magazzino_destinazioni` (`id`, `nome`) VALUES
(1, 'Park Hotel Paradiso'),
(2, 'Imperial'),
(3, 'Villa delle meraviglie'),
(4, 'Cunina 1'),
(5, 'Cucina 2'),
(6, 'Office/Sala 1'),
(7, 'Office/Sala 2'),
(8, 'Bar'),
(9, 'Lavanderia'),
(10, 'Piani'),
(11, 'Accoglienza');

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_fornitori`
--

CREATE TABLE `magazzino_fornitori` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(190) NOT NULL,
  `piva` varchar(20) DEFAULT NULL,
  `codice_fiscale` varchar(20) DEFAULT NULL,
  `codice_sdi` varchar(15) DEFAULT NULL,
  `pec` varchar(190) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `cellulare` varchar(40) DEFAULT NULL,
  `sito_web` varchar(190) DEFAULT NULL,
  `referente_nome` varchar(120) DEFAULT NULL,
  `referente_tel` varchar(40) DEFAULT NULL,
  `referente_email` varchar(190) DEFAULT NULL,
  `indirizzo` varchar(190) DEFAULT NULL,
  `civico` varchar(20) DEFAULT NULL,
  `cap` varchar(15) DEFAULT NULL,
  `citta` varchar(120) DEFAULT NULL,
  `provincia` varchar(10) DEFAULT NULL,
  `regione` varchar(120) DEFAULT NULL,
  `nazione` varchar(80) DEFAULT 'Italia',
  `iban` varchar(34) DEFAULT NULL,
  `pagamento_metodo` varchar(40) DEFAULT NULL,
  `pagamento_giorni` smallint(5) UNSIGNED DEFAULT NULL,
  `pagamento_note` varchar(190) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_lotti`
--

CREATE TABLE `magazzino_lotti` (
  `id` int(10) UNSIGNED NOT NULL,
  `prodotto_id` int(10) UNSIGNED NOT NULL,
  `data_scadenza` date DEFAULT NULL,
  `magazzino_id` int(10) UNSIGNED DEFAULT NULL,
  `scaffale` varchar(60) DEFAULT NULL,
  `ripiano` varchar(60) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_movimenti`
--

CREATE TABLE `magazzino_movimenti` (
  `id` int(10) UNSIGNED NOT NULL,
  `ts` timestamp NULL DEFAULT current_timestamp(),
  `tipo` enum('CARICO','SCARICO') NOT NULL,
  `prodotto_id` int(10) UNSIGNED DEFAULT NULL,
  `lotto_id` int(10) UNSIGNED NOT NULL,
  `quantita` int(11) DEFAULT 0,
  `prezzo` double(10,2) DEFAULT NULL,
  `fornitore_id` bigint(20) UNSIGNED DEFAULT NULL,
  `operatore_id` int(10) UNSIGNED DEFAULT NULL,
  `id_destinazione` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `doc_tipo` enum('FATTURA','DDT','ALTRO') DEFAULT NULL,
  `doc_numero` varchar(40) DEFAULT NULL,
  `doc_data` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino_prodotti`
--

CREATE TABLE `magazzino_prodotti` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(180) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `categoria_id` int(10) UNSIGNED DEFAULT NULL,
  `unita` varchar(20) NOT NULL DEFAULT 'pz',
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `personale_turni`
--

CREATE TABLE `personale_turni` (
  `id` int(10) UNSIGNED NOT NULL,
  `utente_id` int(10) UNSIGNED NOT NULL,
  `data_turno` date NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `ruolo` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `creato_da` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pulizie_camere_giornaliero`
--

CREATE TABLE `pulizie_camere_giornaliero` (
  `camera_id` int(10) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `stato_occupazione` enum('LIBERA','OCCUPATA','ARRIVO_OGGI','PARTENZA_OGGI','FUORI_SERVIZIO') NOT NULL DEFAULT 'LIBERA',
  `stato_pulizia` enum('PULITA','SPORCA','DA_PULIRE_OGGI','IN_CORSO') NOT NULL DEFAULT 'PULITA',
  `stato_manutenzione` enum('NESSUNA','APERTA','IN_CORSO','BLOCCANTE') NOT NULL DEFAULT 'NESSUNA',
  `colore_hex` char(7) DEFAULT NULL,
  `ultimo_aggiornamento_da` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pulizie_task`
--

CREATE TABLE `pulizie_task` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `camera_id` int(10) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `tipo` enum('STANDARD','EXTRA','CAMBIO_BIANCHERIA','CHECK') NOT NULL DEFAULT 'STANDARD',
  `stato` enum('DA_FARE','IN_CORSO','COMPLETATA','ANNULLATA') NOT NULL DEFAULT 'DA_FARE',
  `assegnata_a` bigint(20) UNSIGNED DEFAULT NULL,
  `checklist_json` longtext DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `started_by` bigint(20) UNSIGNED DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `pulizie_task`
--

INSERT INTO `pulizie_task` (`id`, `camera_id`, `data`, `tipo`, `stato`, `assegnata_a`, `checklist_json`, `note`, `created_at`, `updated_at`, `created_by`, `started_at`, `started_by`, `completed_at`, `completed_by`, `cancelled_at`, `cancelled_by`) VALUES
(1, 57, '2026-01-10', 'STANDARD', 'IN_CORSO', 1, NULL, 'blablabla', '2026-01-11 12:10:20', '2026-01-11 12:21:37', NULL, '2026-01-11 12:21:37', NULL, NULL, NULL, NULL, NULL),
(2, 59, '2026-01-15', 'CAMBIO_BIANCHERIA', 'IN_CORSO', NULL, NULL, NULL, '2026-01-15 16:06:49', '2026-01-15 16:10:37', NULL, '2026-01-15 16:10:37', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `sale_congressi`
--

CREATE TABLE `sale_congressi` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `capienza` int(10) NOT NULL,
  `note` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `sale_congressi`
--

INSERT INTO `sale_congressi` (`id`, `nome`, `capienza`, `note`) VALUES
(1, 'Congressi', 450, ''),
(2, 'Emilia', 80, ''),
(3, 'Emilia 2', 40, ''),
(4, 'Canali', 250, ''),
(5, 'Imperiale', 350, ''),
(6, 'Angolare', 30, ''),
(7, 'Corpo tondo', 80, ''),
(8, 'Madonnina', 15, ''),
(9, 'Ufficio ovale', 10, '');

-- --------------------------------------------------------

--
-- Struttura della tabella `sale_ristoranti`
--

CREATE TABLE `sale_ristoranti` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `capienza` int(10) NOT NULL,
  `note` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `sale_ristoranti`
--

INSERT INTO `sale_ristoranti` (`id`, `nome`, `capienza`, `note`) VALUES
(4, 'Canali', 250, ''),
(5, 'Imperiale', 350, ''),
(10, 'Castellina', 80, '');

-- --------------------------------------------------------

--
-- Struttura della tabella `servizi`
--

CREATE TABLE `servizi` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `max_persone` int(11) NOT NULL DEFAULT 1,
  `durata_slot_min` int(11) DEFAULT NULL,
  `step_extra_min` int(11) DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `prenotabile` tinyint(1) NOT NULL DEFAULT 1,
  `slot_illimitato` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `servizi`
--

INSERT INTO `servizi` (`id`, `parent_id`, `nome`, `descrizione`, `max_persone`, `durata_slot_min`, `step_extra_min`, `attivo`, `prenotabile`, `slot_illimitato`, `note`) VALUES
(3, NULL, 'SPA2', '', 10, 60, 30, 1, 0, 0, ''),
(7, NULL, 'PISCINA ESTERNA', '', 100, NULL, NULL, 1, 1, 1, ''),
(8, 3, 'sauna', '', 1, 60, 30, 1, 0, 0, '');

-- --------------------------------------------------------

--
-- Struttura della tabella `servizi_piani_pasto`
--

CREATE TABLE `servizi_piani_pasto` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `sigla` varchar(10) NOT NULL,
  `nome` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `servizi_piani_pasto`
--

INSERT INTO `servizi_piani_pasto` (`id`, `sigla`, `nome`) VALUES
(1, 'BB', 'Solo colazione'),
(2, 'HB', 'Mezza pensione'),
(3, 'FB', 'Pensione completa'),
(4, 'RO', 'Solo pernottamento');

-- --------------------------------------------------------

--
-- Struttura della tabella `servizi_tariffe`
--

CREATE TABLE `servizi_tariffe` (
  `id` int(11) NOT NULL,
  `servizio_id` int(11) NOT NULL,
  `dal` date NOT NULL,
  `al` date DEFAULT NULL,
  `prezzo_slot` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prezzo_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `attiva` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `servizi_tariffe`
--

INSERT INTO `servizi_tariffe` (`id`, `servizio_id`, `dal`, `al`, `prezzo_slot`, `prezzo_extra`, `note`, `attiva`, `created_at`, `updated_at`) VALUES
(1, 6, '2025-12-01', '2025-12-16', 10.00, 22.33, '', 1, '2025-12-14 11:00:53', '2026-01-22 11:26:23'),
(2, 6, '2026-01-01', '2025-12-31', 1000.00, 10.00, '', 1, '2025-12-14 11:20:15', '2026-01-22 11:26:05'),
(3, 3, '2025-12-14', NULL, 10.00, 10.00, '', 1, '2025-12-14 15:30:30', NULL),
(4, 7, '2026-01-01', NULL, 20.00, 15.00, 'Cuffie € 3,00 - Sdraio € 3,00', 1, '2025-12-14 19:39:59', '2026-01-22 12:03:43');

-- --------------------------------------------------------

--
-- Struttura della tabella `soggiorni`
--

CREATE TABLE `soggiorni` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `camera_id` int(10) UNSIGNED NOT NULL,
  `data_checkin` date NOT NULL,
  `data_checkout` date NOT NULL,
  `stato` enum('prenotato','occupato','chiuso','annullato','fuori_servizio') NOT NULL DEFAULT 'prenotato',
  `piano_pasto_sigla` varchar(10) NOT NULL DEFAULT 'BB',
  `hb_servizio` enum('PRANZO','CENA') DEFAULT 'CENA',
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `soggiorni`
--

INSERT INTO `soggiorni` (`id`, `camera_id`, `data_checkin`, `data_checkout`, `stato`, `piano_pasto_sigla`, `hb_servizio`, `note`, `created_at`, `updated_at`) VALUES
(1, 55, '2026-01-19', '2026-01-20', 'annullato', 'BB', 'CENA', NULL, '2026-01-15 13:24:42', '2026-01-22 15:29:55'),
(2, 58, '2026-01-19', '2026-01-24', 'annullato', 'BB', 'CENA', NULL, '2026-01-15 16:56:22', '2026-01-22 11:45:49'),
(3, 55, '2026-01-21', '2026-01-25', 'annullato', 'BB', 'CENA', '', '2026-01-15 17:00:11', '2026-01-22 11:48:38'),
(4, 55, '2026-02-02', '2026-02-20', 'annullato', 'BB', 'CENA', NULL, '2026-01-16 19:09:22', '2026-01-22 11:48:49'),
(5, 58, '2026-01-25', '2026-01-30', 'annullato', 'BB', 'CENA', NULL, '2026-01-18 20:12:08', '2026-01-22 11:44:13'),
(6, 60, '2026-01-26', '2026-01-30', 'annullato', 'BB', 'CENA', NULL, '2026-01-18 20:14:16', '2026-01-22 11:48:45'),
(7, 61, '2026-01-22', '2026-01-27', 'annullato', 'BB', 'CENA', NULL, '2026-01-18 20:17:13', '2026-01-22 11:48:42'),
(8, 63, '2026-01-22', '2026-01-27', 'prenotato', 'HB', 'PRANZO', '', '2026-01-21 18:20:06', '2026-01-21 18:26:39'),
(9, 70, '2026-01-21', '2026-01-25', 'prenotato', 'BB', 'CENA', '', '2026-01-21 19:19:45', '2026-01-21 19:22:25'),
(10, 63, '2026-01-27', '2026-01-30', 'prenotato', '', 'CENA', '', '2026-01-22 15:00:08', '2026-01-22 15:00:08');

-- --------------------------------------------------------

--
-- Struttura della tabella `soggiorni_clienti`
--

CREATE TABLE `soggiorni_clienti` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `soggiorno_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `cognome` varchar(80) NOT NULL,
  `data_nascita` date DEFAULT NULL,
  `nazionalita` varchar(80) DEFAULT NULL,
  `documento_tipo` varchar(40) DEFAULT NULL,
  `documento_numero` varchar(80) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `indirizzo` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `soggiorni_clienti`
--

INSERT INTO `soggiorni_clienti` (`id`, `soggiorno_id`, `nome`, `cognome`, `data_nascita`, `nazionalita`, `documento_tipo`, `documento_numero`, `email`, `telefono`, `indirizzo`, `note`, `created_at`, `updated_at`) VALUES
(4, 8, 'Alessio', 'Patamia', '1989-04-22', 'italiana', NULL, 'fdghj', 'alessio.patamia@gmail.com', '3200230544', 'Via E Macri, 6', '', '2026-01-21 18:20:06', '2026-01-21 18:20:06'),
(5, 9, 'MATILDE', 'FARINA', '1989-11-20', 'ITALIA', NULL, 'AV565XX', 'M', '3890549540', 'PIAZZALE GRAMSCI, 3', '', '2026-01-21 19:19:45', '2026-01-21 19:19:45'),
(6, 3, 'Alessio', 'Patamia', '1989-04-22', 'italiana', '', 'fdghj', 'alessio.patamia@gmail.com', '3200230544', 'Via E Macri, 6', '', '2026-01-22 11:48:29', '2026-01-22 11:48:29'),
(7, 10, 'fake name', 'fake surname', '2026-01-08', 'pojh', NULL, 'dhsjh', '', '', 'ghsjh', '', '2026-01-22 15:00:08', '2026-01-22 15:00:08');

-- --------------------------------------------------------

--
-- Struttura della tabella `soggiorni_pagamenti`
--

CREATE TABLE `soggiorni_pagamenti` (
  `id` int(11) NOT NULL,
  `soggiorno_id` int(11) NOT NULL,
  `importo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo` varchar(20) NOT NULL,
  `note` text DEFAULT NULL,
  `is_saldo_finale` tinyint(1) NOT NULL DEFAULT 0,
  `utente_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `soggiorni_tariffe`
--

CREATE TABLE `soggiorni_tariffe` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `codice` varchar(75) NOT NULL,
  `descrizione` varchar(255) DEFAULT NULL,
  `data_da` date NOT NULL,
  `data_a` date DEFAULT NULL,
  `prezzo_solo_pernottamento` decimal(10,2) NOT NULL,
  `prezzo_BB` double(10,2) NOT NULL,
  `prezzo_HB` double(10,2) NOT NULL,
  `prezzo_FB` double(10,2) NOT NULL,
  `0-3` int(11) DEFAULT NULL,
  `4-8` int(11) DEFAULT NULL,
  `valuta` char(3) NOT NULL DEFAULT 'EUR',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `soggiorni_tariffe`
--

INSERT INTO `soggiorni_tariffe` (`id`, `codice`, `descrizione`, `data_da`, `data_a`, `prezzo_solo_pernottamento`, `prezzo_BB`, `prezzo_HB`, `prezzo_FB`, `0-3`, `4-8`, `valuta`, `note`) VALUES
(1, 'Singola', 'X', '2026-01-01', NULL, 45.00, 50.00, 75.00, 100.00, 100, 50, 'EUR', NULL),
(2, 'Matrimoniale', 'M', '2026-01-01', NULL, 40.00, 45.00, 70.00, 95.00, 100, 50, 'EUR', NULL),
(3, 'Dus', 'M', '2026-01-01', NULL, 55.00, 60.00, 85.00, 110.00, 100, 50, 'EUR', NULL),
(4, 'Twin', 'XX', '2026-01-01', NULL, 40.00, 45.00, 70.00, 95.00, 100, 50, 'EUR', NULL),
(5, 'Tripla', 'XXX', '2026-01-01', NULL, 35.00, 40.00, 65.00, 90.00, 100, 50, 'EUR', NULL),
(6, 'Suite', 'MXX', '2026-01-01', NULL, 245.00, 250.00, 275.00, 300.00, 100, 50, 'EUR', NULL),
(7, 'Matrimoniale + Lettino', 'MX', '2026-01-01', NULL, 35.00, 40.00, 65.00, 90.00, 100, 50, 'EUR', NULL),
(8, 'Quadrupla', 'MXX', '2026-01-01', NULL, 30.00, 35.00, 60.00, 85.00, 100, 50, 'EUR', NULL),
(9, 'Matrimoniale + Divano letto', 'MD', '2026-01-01', NULL, 35.00, 40.00, 65.00, 90.00, 100, 50, 'EUR', NULL),
(10, 'Matrimoniale + Lettino + Divano letto', 'MXD', '2026-01-01', NULL, 30.00, 35.00, 60.00, 85.00, 100, 50, 'EUR', NULL),
(11, 'Family 5', 'MXXX', '2026-01-01', NULL, 25.00, 30.00, 55.00, 80.00, 100, 50, 'EUR', NULL),
(12, 'Family 6', 'MXXXX', '2026-01-01', NULL, 25.00, 30.00, 55.00, 80.00, 100, 50, 'EUR', NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `struttura_camere`
--

CREATE TABLE `struttura_camere` (
  `id` int(10) UNSIGNED NOT NULL,
  `piano_id` int(10) UNSIGNED NOT NULL,
  `codice` varchar(30) NOT NULL,
  `id_tipologia_letti` text NOT NULL,
  `accessibile_disabili` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `attiva` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `struttura_camere`
--

INSERT INTO `struttura_camere` (`id`, `piano_id`, `codice`, `id_tipologia_letti`, `accessibile_disabili`, `note`, `attiva`) VALUES
(1, 1, '501', '0', 0, 'Normale', 1),
(2, 1, '502', '0', 0, 'Normale', 1),
(3, 1, '503', '0', 0, 'Normale', 1),
(4, 1, '504', '0', 0, 'Normale', 1),
(5, 1, '505', '0', 0, 'Normale', 1),
(6, 1, '506', '0', 0, 'Normale', 1),
(7, 1, '507', '0', 0, 'Normale', 1),
(8, 1, '508', '0', 0, 'Normale', 1),
(9, 1, '509', '0', 0, 'Normale', 1),
(10, 1, '510', '0', 0, 'Normale', 1),
(11, 2, '601', '0', 0, 'Normale', 1),
(12, 2, '602', '0', 0, 'Normale', 1),
(13, 2, '603', '0', 0, 'Normale', 1),
(14, 2, '604', '0', 0, 'Normale', 1),
(15, 2, '605', '0', 0, 'Normale', 1),
(16, 2, '606', '0', 0, 'Normale', 1),
(17, 2, '607', '0', 0, 'Normale', 1),
(18, 2, '608', '0', 0, 'Normale', 1),
(19, 2, '609', '0', 0, 'Normale', 1),
(20, 2, '610', '0', 0, 'Normale', 1),
(21, 3, '701', '0', 0, 'Normale', 1),
(22, 3, '702', '0', 0, 'Normale', 1),
(23, 3, '703', '0', 0, 'Normale', 1),
(24, 3, '704', '0', 0, 'Normale', 1),
(25, 3, '705', '0', 0, 'Normale', 1),
(26, 3, '706', '0', 0, 'Normale', 1),
(27, 3, '707', '0', 0, 'Normale', 1),
(28, 3, '708', '0', 0, 'Normale', 1),
(29, 3, '709', '0', 0, 'Normale', 1),
(30, 3, '710', '0', 0, 'Normale', 1),
(31, 4, '26', '0', 0, 'Normale', 1),
(32, 4, '27', '0', 0, 'Normale', 1),
(33, 4, '28', '0', 0, 'Normale', 1),
(34, 4, '29', '0', 0, 'Normale', 1),
(35, 4, '30', '0', 0, 'Normale', 1),
(36, 4, '31', '0', 0, 'Normale', 1),
(37, 4, '32', '0', 0, 'Normale', 1),
(38, 4, '33', '0', 0, 'Normale', 1),
(39, 4, '34', '0', 0, 'Normale', 1),
(40, 4, '35', '0', 0, 'Normale', 1),
(41, 4, '36', '0', 0, 'Normale', 1),
(42, 4, '37', '0', 0, 'Normale', 1),
(43, 4, '38', '0', 0, 'Normale', 1),
(44, 4, '39', '0', 0, 'Normale', 1),
(45, 4, '40', '0', 0, 'Normale', 1),
(46, 5, '401', '0', 0, 'Normale', 1),
(47, 5, '402', '0', 0, 'Normale', 1),
(48, 5, '403', '0', 0, 'Normale', 1),
(49, 5, '404', '0', 0, 'Normale', 1),
(50, 5, '405', '0', 0, 'Normale', 1),
(51, 5, '406', '0', 0, 'Normale', 1),
(52, 6, '407', '0', 0, 'Normale', 1),
(53, 6, '408', '0', 0, 'Normale', 1),
(54, 6, '409', '0', 0, 'Normale', 1),
(55, 7, '101', '11', 0, '', 1),
(56, 7, '103', '4', 0, '', 1),
(57, 7, '104', '2', 0, '', 1),
(58, 7, '105', '7', 0, '', 1),
(59, 7, '106', '7', 0, '', 1),
(60, 7, '107', '12', 0, '', 1),
(61, 7, '109', '7', 0, '', 1),
(62, 7, '110', '2', 0, '', 1),
(63, 7, '111', '7', 0, '', 1),
(64, 8, '201', '0', 0, 'Normale', 1),
(65, 8, '202', '0', 0, 'Normale', 1),
(66, 8, '203', '0', 0, 'Normale', 1),
(67, 8, '204', '0', 0, 'Normale', 1),
(68, 8, '205', '0', 0, 'Normale', 1),
(69, 8, '206', '0', 0, 'Normale', 1),
(70, 8, '207', '0', 0, 'Normale', 1),
(71, 8, '208', '0', 0, 'Normale', 1),
(72, 8, '209', '0', 0, 'Normale', 1),
(73, 8, '210', '0', 0, 'Normale', 1),
(74, 8, '211', '0', 0, 'Normale', 1),
(75, 9, 'Suite Antea', '0', 0, 'Suite', 1),
(76, 9, 'Suite Nereide', '0', 0, 'Suite', 1),
(77, 9, 'Suite Amorini', '0', 0, 'Suite', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `struttura_edifici`
--

CREATE TABLE `struttura_edifici` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `struttura_edifici`
--

INSERT INTO `struttura_edifici` (`id`, `nome`, `note`, `attivo`, `created_at`) VALUES
(1, 'Villa Silvia', '', 1, '2025-12-14 10:57:15'),
(2, 'Villa Daniele', '', 1, '2025-12-14 10:57:15'),
(3, 'Depandance', '', 1, '2025-12-14 10:57:15'),
(4, 'Corpo centrale', 'fdbjsk', 1, '2025-12-14 10:57:15');

-- --------------------------------------------------------

--
-- Struttura della tabella `struttura_piani`
--

CREATE TABLE `struttura_piani` (
  `id` int(10) UNSIGNED NOT NULL,
  `edificio_id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(60) NOT NULL,
  `livello` int(11) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `struttura_piani`
--

INSERT INTO `struttura_piani` (`id`, `edificio_id`, `nome`, `livello`, `note`, `attivo`) VALUES
(1, 1, 'Piano 1', 1, 'Camere 501-510', 1),
(2, 1, 'Piano 2', 2, 'Camere 601-610', 1),
(3, 1, 'Piano 3', 3, 'Camere 701-710', 1),
(4, 2, 'Piano 1', 1, 'Camere 26-40', 1),
(5, 3, 'Piano 1', 1, 'Camere 401-406', 1),
(6, 3, 'Piano 2', 2, 'Camere 407-409', 1),
(7, 4, 'Piano 1', 1, 'Camere 101-111 (mancano 102 e 108)', 1),
(8, 4, 'Piano 2', 2, 'Camere 201-211', 1),
(9, 4, 'Piano 3', 3, 'Suite: Antea, Nereide, Amorini', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `ticket_manutenzione`
--

CREATE TABLE `ticket_manutenzione` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `edificio_id` int(10) UNSIGNED DEFAULT NULL,
  `piano_id` int(10) UNSIGNED DEFAULT NULL,
  `camera_id` int(10) UNSIGNED DEFAULT NULL,
  `titolo` varchar(180) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `priorita` enum('BASSA','MEDIA','ALTA','URGENTE') NOT NULL DEFAULT 'MEDIA',
  `stato` enum('APERTO','IN_CORSO','RISOLTO','ANNULLATO') NOT NULL DEFAULT 'APERTO',
  `assegnato_a` bigint(20) UNSIGNED DEFAULT NULL,
  `aperto_da` bigint(20) UNSIGNED DEFAULT NULL,
  `chiuso_da` bigint(20) UNSIGNED DEFAULT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `ticket_manutenzione`
--

INSERT INTO `ticket_manutenzione` (`id`, `edificio_id`, `piano_id`, `camera_id`, `titolo`, `descrizione`, `priorita`, `stato`, `assegnato_a`, `aperto_da`, `chiuso_da`, `opened_at`, `closed_at`) VALUES
(1, 4, 7, 56, '1111', 'nessun problema boss', 'MEDIA', 'IN_CORSO', 1, 1, NULL, '2026-01-11 12:10:48', NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti`
--

CREATE TABLE `utenti` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nome` varchar(80) DEFAULT NULL,
  `cognome` varchar(80) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `privilegi` enum('guest','standard','root') NOT NULL DEFAULT 'standard',
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `richiesta_registrazione` tinyint(1) NOT NULL DEFAULT 0,
  `registrazione_token` varchar(64) DEFAULT NULL,
  `registrazione_scadenza` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_scadenza` datetime DEFAULT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utenti`
--

INSERT INTO `utenti` (`id`, `username`, `email`, `password_hash`, `nome`, `cognome`, `telefono`, `privilegi`, `attivo`, `richiesta_registrazione`, `registrazione_token`, `registrazione_scadenza`, `reset_token`, `reset_scadenza`, `ultimo_login`, `created_at`, `updated_at`) VALUES
(1, 'alessio', 'alessio.patamia@gmail.com', '$2a$12$a3Nd6gUMG6JT2pC4ENyYlujlTltyE8takVCNZAVPS/vyrn5bVXcnG', 'Alessio', 'Patamia', NULL, 'root', 1, 0, NULL, NULL, NULL, NULL, '2026-02-25 17:27:28', '2025-12-13 16:51:13', '2026-02-25 17:27:28'),
(3, 'alessio2', 'alessio.patamia@gmail.com2', '$2a$12$a3Nd6gUMG6JT2pC4ENyYlujlTltyE8takVCNZAVPS/vyrn5bVXcnG', 'Alessio', 'Patamia', NULL, 'root', 1, 0, NULL, NULL, NULL, NULL, '2026-01-15 10:22:35', '2025-12-13 16:51:13', '2026-01-15 16:05:04');

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti_gruppi`
--

CREATE TABLE `utenti_gruppi` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `codice` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utenti_gruppi`
--

INSERT INTO `utenti_gruppi` (`id`, `codice`, `nome`) VALUES
(1, 'Reception', 'Reception'),
(2, 'Ristorante', 'Ristorante'),
(3, 'Pulizia', 'Pulizia'),
(4, 'Manutenzione', 'Manutenzione'),
(5, 'Altro', 'Altro');

-- --------------------------------------------------------

--
-- Struttura della tabella `utenti_privilegi`
--

CREATE TABLE `utenti_privilegi` (
  `utente_id` bigint(20) UNSIGNED NOT NULL,
  `gruppo_id` smallint(5) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utenti_privilegi`
--

INSERT INTO `utenti_privilegi` (`utente_id`, `gruppo_id`) VALUES
(1, 1),
(1, 2),
(1, 3);

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `city_tax`
--
ALTER TABLE `city_tax`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `gruppi_arrivi`
--
ALTER TABLE `gruppi_arrivi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gruppi_arrivi_nome` (`nome_gruppo`),
  ADD KEY `idx_gruppi_arrivi_data` (`data_arrivo`);

--
-- Indici per le tabelle `magazzini`
--
ALTER TABLE `magazzini`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_magazzini_nome` (`nome`);

--
-- Indici per le tabelle `magazzino_categorie`
--
ALTER TABLE `magazzino_categorie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categorie_nome` (`nome`),
  ADD KEY `idx_categorie_tipo` (`tipo`);

--
-- Indici per le tabelle `magazzino_destinazioni`
--
ALTER TABLE `magazzino_destinazioni`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `magazzino_fornitori`
--
ALTER TABLE `magazzino_fornitori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fornitori_piva` (`piva`),
  ADD UNIQUE KEY `uq_fornitori_cf` (`codice_fiscale`),
  ADD UNIQUE KEY `uq_fornitori_pec` (`pec`),
  ADD KEY `idx_fornitori_nome` (`nome`),
  ADD KEY `idx_fornitori_piva` (`piva`),
  ADD KEY `idx_fornitori_cf` (`codice_fiscale`),
  ADD KEY `idx_fornitori_email` (`email`),
  ADD KEY `idx_fornitori_attivo` (`attivo`);

--
-- Indici per le tabelle `magazzino_lotti`
--
ALTER TABLE `magazzino_lotti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lotti_prodotto` (`prodotto_id`),
  ADD KEY `idx_lotti_scadenza` (`data_scadenza`),
  ADD KEY `idx_lotti_magazzino` (`magazzino_id`),
  ADD KEY `idx_lotti_posizione` (`scaffale`,`ripiano`);

--
-- Indici per le tabelle `magazzino_movimenti`
--
ALTER TABLE `magazzino_movimenti`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `magazzino_prodotti`
--
ALTER TABLE `magazzino_prodotti`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `personale_turni`
--
ALTER TABLE `personale_turni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_turni_data` (`data_turno`),
  ADD KEY `idx_turni_utente_data` (`utente_id`,`data_turno`);

--
-- Indici per le tabelle `pulizie_camere_giornaliero`
--
ALTER TABLE `pulizie_camere_giornaliero`
  ADD PRIMARY KEY (`camera_id`,`data`),
  ADD KEY `idx_stato_data` (`data`),
  ADD KEY `fk_scg_utente` (`ultimo_aggiornamento_da`);

--
-- Indici per le tabelle `pulizie_task`
--
ALTER TABLE `pulizie_task`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_task_camera_data_tipo` (`camera_id`,`data`,`tipo`),
  ADD UNIQUE KEY `uniq_task_camera_tipo_data` (`camera_id`,`tipo`,`data`),
  ADD KEY `idx_task_data_stato` (`data`,`stato`),
  ADD KEY `fk_tp_utente` (`assegnata_a`);

--
-- Indici per le tabelle `sale_congressi`
--
ALTER TABLE `sale_congressi`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `sale_ristoranti`
--
ALTER TABLE `sale_ristoranti`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `servizi`
--
ALTER TABLE `servizi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_servizi_nome` (`nome`),
  ADD KEY `idx_servizi_parent` (`parent_id`),
  ADD KEY `idx_servizi_parent_id` (`parent_id`);

--
-- Indici per le tabelle `servizi_piani_pasto`
--
ALTER TABLE `servizi_piani_pasto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sigla` (`sigla`);

--
-- Indici per le tabelle `servizi_tariffe`
--
ALTER TABLE `servizi_tariffe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servizio` (`servizio_id`),
  ADD KEY `idx_periodo` (`servizio_id`,`dal`,`al`);

--
-- Indici per le tabelle `soggiorni`
--
ALTER TABLE `soggiorni`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soggiorni_camera_date` (`camera_id`,`data_checkin`,`data_checkout`),
  ADD KEY `idx_soggiorni_stato` (`stato`);

--
-- Indici per le tabelle `soggiorni_clienti`
--
ALTER TABLE `soggiorni_clienti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clienti_cognome` (`cognome`,`nome`),
  ADD KEY `idx_clienti_documento` (`documento_numero`),
  ADD KEY `idx_soggiorno_id` (`soggiorno_id`);

--
-- Indici per le tabelle `soggiorni_pagamenti`
--
ALTER TABLE `soggiorni_pagamenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soggiorno` (`soggiorno_id`);

--
-- Indici per le tabelle `soggiorni_tariffe`
--
ALTER TABLE `soggiorni_tariffe`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `struttura_camere`
--
ALTER TABLE `struttura_camere`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_camera` (`piano_id`,`codice`),
  ADD KEY `idx_camere_piano` (`piano_id`);

--
-- Indici per le tabelle `struttura_edifici`
--
ALTER TABLE `struttura_edifici`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_edifici_nome` (`nome`);

--
-- Indici per le tabelle `struttura_piani`
--
ALTER TABLE `struttura_piani`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_piani_edificio` (`edificio_id`);

--
-- Indici per le tabelle `ticket_manutenzione`
--
ALTER TABLE `ticket_manutenzione`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tm_stato_priorita` (`stato`,`priorita`),
  ADD KEY `idx_tm_camera` (`camera_id`),
  ADD KEY `fk_tm_edificio` (`edificio_id`),
  ADD KEY `fk_tm_piano` (`piano_id`),
  ADD KEY `fk_tm_ass` (`assegnato_a`),
  ADD KEY `fk_tm_aperto` (`aperto_da`),
  ADD KEY `fk_tm_chiuso` (`chiuso_da`);

--
-- Indici per le tabelle `utenti`
--
ALTER TABLE `utenti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `idx_utenti_reg_token` (`registrazione_token`),
  ADD KEY `idx_utenti_reset_token` (`reset_token`);

--
-- Indici per le tabelle `utenti_gruppi`
--
ALTER TABLE `utenti_gruppi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_gruppi_codice` (`codice`);

--
-- Indici per le tabelle `utenti_privilegi`
--
ALTER TABLE `utenti_privilegi`
  ADD PRIMARY KEY (`utente_id`,`gruppo_id`),
  ADD KEY `fk_utenti_privilegi_gruppo` (`gruppo_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `city_tax`
--
ALTER TABLE `city_tax`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `gruppi_arrivi`
--
ALTER TABLE `gruppi_arrivi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `magazzini`
--
ALTER TABLE `magazzini`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `magazzino_categorie`
--
ALTER TABLE `magazzino_categorie`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT per la tabella `magazzino_destinazioni`
--
ALTER TABLE `magazzino_destinazioni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT per la tabella `magazzino_fornitori`
--
ALTER TABLE `magazzino_fornitori`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `magazzino_lotti`
--
ALTER TABLE `magazzino_lotti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `magazzino_movimenti`
--
ALTER TABLE `magazzino_movimenti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT per la tabella `magazzino_prodotti`
--
ALTER TABLE `magazzino_prodotti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT per la tabella `personale_turni`
--
ALTER TABLE `personale_turni`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pulizie_task`
--
ALTER TABLE `pulizie_task`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `sale_congressi`
--
ALTER TABLE `sale_congressi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT per la tabella `sale_ristoranti`
--
ALTER TABLE `sale_ristoranti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `servizi`
--
ALTER TABLE `servizi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT per la tabella `servizi_piani_pasto`
--
ALTER TABLE `servizi_piani_pasto`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `servizi_tariffe`
--
ALTER TABLE `servizi_tariffe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `soggiorni`
--
ALTER TABLE `soggiorni`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `soggiorni_clienti`
--
ALTER TABLE `soggiorni_clienti`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `soggiorni_pagamenti`
--
ALTER TABLE `soggiorni_pagamenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `soggiorni_tariffe`
--
ALTER TABLE `soggiorni_tariffe`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `struttura_camere`
--
ALTER TABLE `struttura_camere`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT per la tabella `struttura_edifici`
--
ALTER TABLE `struttura_edifici`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT per la tabella `struttura_piani`
--
ALTER TABLE `struttura_piani`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `ticket_manutenzione`
--
ALTER TABLE `ticket_manutenzione`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `utenti`
--
ALTER TABLE `utenti`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `utenti_gruppi`
--
ALTER TABLE `utenti_gruppi`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `magazzino_prodotti`
--
ALTER TABLE `magazzino_prodotti`
  ADD CONSTRAINT `fk_prodotti_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `magazzino_categorie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `pulizie_camere_giornaliero`
--
ALTER TABLE `pulizie_camere_giornaliero`
  ADD CONSTRAINT `fk_scg_camera` FOREIGN KEY (`camera_id`) REFERENCES `struttura_camere` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scg_utente` FOREIGN KEY (`ultimo_aggiornamento_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `pulizie_task`
--
ALTER TABLE `pulizie_task`
  ADD CONSTRAINT `fk_tp_camera` FOREIGN KEY (`camera_id`) REFERENCES `struttura_camere` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tp_utente` FOREIGN KEY (`assegnata_a`) REFERENCES `utenti` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `servizi`
--
ALTER TABLE `servizi`
  ADD CONSTRAINT `fk_servizi_parent` FOREIGN KEY (`parent_id`) REFERENCES `servizi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_servizi_parent_id_cascade` FOREIGN KEY (`parent_id`) REFERENCES `servizi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `soggiorni`
--
ALTER TABLE `soggiorni`
  ADD CONSTRAINT `fk_sogg_camera` FOREIGN KEY (`camera_id`) REFERENCES `struttura_camere` (`id`);

--
-- Limiti per la tabella `soggiorni_clienti`
--
ALTER TABLE `soggiorni_clienti`
  ADD CONSTRAINT `fk_soggiorni_clienti_soggiorno` FOREIGN KEY (`soggiorno_id`) REFERENCES `soggiorni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_soggiorno` FOREIGN KEY (`soggiorno_id`) REFERENCES `soggiorni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `struttura_camere`
--
ALTER TABLE `struttura_camere`
  ADD CONSTRAINT `fk_camere_piano_id` FOREIGN KEY (`piano_id`) REFERENCES `struttura_piani` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `struttura_camere_ibfk_1` FOREIGN KEY (`piano_id`) REFERENCES `struttura_piani` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `struttura_piani`
--
ALTER TABLE `struttura_piani`
  ADD CONSTRAINT `fk_piani_edificio_id` FOREIGN KEY (`edificio_id`) REFERENCES `struttura_edifici` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `struttura_piani_ibfk_1` FOREIGN KEY (`edificio_id`) REFERENCES `struttura_edifici` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `ticket_manutenzione`
--
ALTER TABLE `ticket_manutenzione`
  ADD CONSTRAINT `fk_tm_aperto` FOREIGN KEY (`aperto_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tm_ass` FOREIGN KEY (`assegnato_a`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tm_camera` FOREIGN KEY (`camera_id`) REFERENCES `struttura_camere` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tm_chiuso` FOREIGN KEY (`chiuso_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tm_edificio` FOREIGN KEY (`edificio_id`) REFERENCES `struttura_edifici` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tm_piano` FOREIGN KEY (`piano_id`) REFERENCES `struttura_piani` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `utenti_privilegi`
--
ALTER TABLE `utenti_privilegi`
  ADD CONSTRAINT `fk_utenti_privilegi_gruppo` FOREIGN KEY (`gruppo_id`) REFERENCES `utenti_gruppi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `utenti_privilegi_ibfk_1` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
