-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Creato il: Gen 22, 2026 alle 11:27
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

--
-- Dump dei dati per la tabella `magazzino_lotti`
--

INSERT INTO `magazzino_lotti` (`id`, `prodotto_id`, `data_scadenza`, `magazzino_id`, `scaffale`, `ripiano`, `created_at`, `updated_at`) VALUES
(4, 11, '2026-01-14', 3, '1', '2', '2026-01-19 18:46:53', '2026-01-19 18:47:04');

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

--
-- Dump dei dati per la tabella `magazzino_movimenti`
--

INSERT INTO `magazzino_movimenti` (`id`, `ts`, `tipo`, `prodotto_id`, `lotto_id`, `quantita`, `prezzo`, `fornitore_id`, `operatore_id`, `id_destinazione`, `note`, `doc_tipo`, `doc_numero`, `doc_data`) VALUES
(16, '2026-01-19 19:02:00', 'CARICO', 11, 4, 100, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(17, '2026-01-19 19:10:00', 'SCARICO', 11, 4, 20, NULL, NULL, 1, 8, NULL, NULL, NULL, NULL);

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

--
-- Dump dei dati per la tabella `magazzino_prodotti`
--

INSERT INTO `magazzino_prodotti` (`id`, `nome`, `descrizione`, `categoria_id`, `unita`, `attivo`, `created_at`, `updated_at`) VALUES
(11, 'Castello 33 Cl', NULL, 4, 'pz', 1, '2026-01-19 18:44:13', NULL);

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
(4, 7, '2026-06-30', NULL, 10.00, 15.00, 'Cuffie € 3,00 - Sdraio € 3,00', 1, '2025-12-14 19:39:59', NULL);

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
(1, 55, '2026-01-19', '2026-01-20', 'prenotato', 'BB', 'CENA', NULL, '2026-01-15 13:24:42', '2026-01-15 13:24:42'),
(2, 58, '2026-01-19', '2026-01-24', 'prenotato', 'BB', 'CENA', NULL, '2026-01-15 16:56:22', '2026-01-15 16:56:22'),
(3, 55, '2026-01-21', '2026-01-25', 'prenotato', 'BB', 'CENA', '', '2026-01-15 17:00:11', '2026-01-20 21:57:30'),
(4, 55, '2026-02-02', '2026-02-20', 'prenotato', 'BB', 'CENA', NULL, '2026-01-16 19:09:22', '2026-01-16 19:09:22'),
(5, 58, '2026-01-25', '2026-01-30', 'prenotato', 'BB', 'CENA', NULL, '2026-01-18 20:12:08', '2026-01-18 20:12:08'),
(6, 60, '2026-01-26', '2026-01-30', 'prenotato', 'BB', 'CENA', NULL, '2026-01-18 20:14:16', '2026-01-18 20:14:16'),
(7, 61, '2026-01-22', '2026-01-27', 'prenotato', 'BB', 'CENA', NULL, '2026-01-18 20:17:13', '2026-01-18 20:17:13'),
(8, 63, '2026-01-22', '2026-01-27', 'prenotato', 'HB', 'PRANZO', '', '2026-01-21 18:20:06', '2026-01-21 18:26:39'),
(9, 70, '2026-01-21', '2026-01-25', 'prenotato', 'BB', 'CENA', '', '2026-01-21 19:19:45', '2026-01-21 19:22:25');

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
(5, 9, 'MATILDE', 'FARINA', '1989-11-20', 'ITALIA', NULL, 'AV565XX', 'M', '3890549540', 'PIAZZALE GRAMSCI, 3', '', '2026-01-21 19:19:45', '2026-01-21 19:19:45');

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
  `valuta` char(3) NOT NULL DEFAULT 'EUR',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `soggiorni_tariffe`
--

INSERT INTO `soggiorni_tariffe` (`id`, `codice`, `descrizione`, `data_da`, `data_a`, `prezzo_solo_pernottamento`, `prezzo_BB`, `prezzo_HB`, `prezzo_FB`, `valuta`, `note`) VALUES
(1, 'singola', 'Letto singolo', '2026-01-01', NULL, 10.00, 70.00, 130.00, 190.00, 'EUR', NULL),
(2, 'matrimoniale', 'Letto matrimoniale', '2026-01-01', NULL, 20.00, 80.00, 140.00, 200.00, 'EUR', NULL),
(3, 'matrimoniale_uso_singola', 'Matrimoniale uso singola', '2026-01-01', NULL, 30.00, 90.00, 150.00, 210.00, 'EUR', NULL),
(4, 'twin', 'singoli', '2026-01-01', NULL, 40.00, 100.00, 160.00, 220.00, 'EUR', NULL),
(5, 'doux', 'Letto francese', '2026-01-01', NULL, 50.00, 110.00, 170.00, 230.00, 'EUR', NULL),
(6, 'siute', 'suite', '2026-01-01', NULL, 60.00, 120.00, 180.00, 240.00, 'EUR', NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `struttura_camere`
--

CREATE TABLE `struttura_camere` (
  `id` int(10) UNSIGNED NOT NULL,
  `piano_id` int(10) UNSIGNED NOT NULL,
  `codice` varchar(30) NOT NULL,
  `capienza_base` int(11) NOT NULL DEFAULT 2,
  `accessibile_disabili` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `attiva` tinyint(1) NOT NULL DEFAULT 1,
  `ricorrenza_cambio_biancheria_notti` int(11) NOT NULL DEFAULT 0,
  `ultimo_cambio_biancheria` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `struttura_camere`
--

INSERT INTO `struttura_camere` (`id`, `piano_id`, `codice`, `capienza_base`, `accessibile_disabili`, `note`, `attiva`, `ricorrenza_cambio_biancheria_notti`, `ultimo_cambio_biancheria`) VALUES
(1, 1, '501', 2, 0, 'Normale', 1, 0, NULL),
(2, 1, '502', 2, 0, 'Normale', 1, 0, NULL),
(3, 1, '503', 2, 0, 'Normale', 1, 0, NULL),
(4, 1, '504', 2, 0, 'Normale', 1, 0, NULL),
(5, 1, '505', 2, 0, 'Normale', 1, 0, NULL),
(6, 1, '506', 2, 0, 'Normale', 1, 0, NULL),
(7, 1, '507', 2, 0, 'Normale', 1, 0, NULL),
(8, 1, '508', 2, 0, 'Normale', 1, 0, NULL),
(9, 1, '509', 2, 0, 'Normale', 1, 0, NULL),
(10, 1, '510', 2, 0, 'Normale', 1, 0, NULL),
(11, 2, '601', 2, 0, 'Normale', 1, 0, NULL),
(12, 2, '602', 2, 0, 'Normale', 1, 0, NULL),
(13, 2, '603', 2, 0, 'Normale', 1, 0, NULL),
(14, 2, '604', 2, 0, 'Normale', 1, 0, NULL),
(15, 2, '605', 2, 0, 'Normale', 1, 0, NULL),
(16, 2, '606', 2, 0, 'Normale', 1, 0, NULL),
(17, 2, '607', 2, 0, 'Normale', 1, 0, NULL),
(18, 2, '608', 2, 0, 'Normale', 1, 0, NULL),
(19, 2, '609', 2, 0, 'Normale', 1, 0, NULL),
(20, 2, '610', 2, 0, 'Normale', 1, 0, NULL),
(21, 3, '701', 2, 0, 'Normale', 1, 0, NULL),
(22, 3, '702', 2, 0, 'Normale', 1, 0, NULL),
(23, 3, '703', 2, 0, 'Normale', 1, 0, NULL),
(24, 3, '704', 2, 0, 'Normale', 1, 0, NULL),
(25, 3, '705', 2, 0, 'Normale', 1, 0, NULL),
(26, 3, '706', 2, 0, 'Normale', 1, 0, NULL),
(27, 3, '707', 2, 0, 'Normale', 1, 0, NULL),
(28, 3, '708', 2, 0, 'Normale', 1, 0, NULL),
(29, 3, '709', 2, 0, 'Normale', 1, 0, NULL),
(30, 3, '710', 2, 0, 'Normale', 1, 0, NULL),
(31, 4, '26', 2, 0, 'Normale', 1, 0, NULL),
(32, 4, '27', 2, 0, 'Normale', 1, 0, NULL),
(33, 4, '28', 2, 0, 'Normale', 1, 0, NULL),
(34, 4, '29', 2, 0, 'Normale', 1, 0, NULL),
(35, 4, '30', 2, 0, 'Normale', 1, 0, NULL),
(36, 4, '31', 2, 0, 'Normale', 1, 0, NULL),
(37, 4, '32', 2, 0, 'Normale', 1, 0, NULL),
(38, 4, '33', 2, 0, 'Normale', 1, 0, NULL),
(39, 4, '34', 2, 0, 'Normale', 1, 0, NULL),
(40, 4, '35', 2, 0, 'Normale', 1, 0, NULL),
(41, 4, '36', 2, 0, 'Normale', 1, 0, NULL),
(42, 4, '37', 2, 0, 'Normale', 1, 0, NULL),
(43, 4, '38', 2, 0, 'Normale', 1, 0, NULL),
(44, 4, '39', 2, 0, 'Normale', 1, 0, NULL),
(45, 4, '40', 2, 0, 'Normale', 1, 0, NULL),
(46, 5, '401', 2, 0, 'Normale', 1, 0, NULL),
(47, 5, '402', 2, 0, 'Normale', 1, 0, NULL),
(48, 5, '403', 2, 0, 'Normale', 1, 0, NULL),
(49, 5, '404', 2, 0, 'Normale', 1, 0, NULL),
(50, 5, '405', 2, 0, 'Normale', 1, 0, NULL),
(51, 5, '406', 2, 0, 'Normale', 1, 0, NULL),
(52, 6, '407', 2, 0, 'Normale', 1, 0, NULL),
(53, 6, '408', 2, 0, 'Normale', 1, 0, NULL),
(54, 6, '409', 2, 0, 'Normale', 1, 0, NULL),
(55, 7, '101', 5, 0, 'MXXX', 1, 0, NULL),
(56, 7, '103', 2, 1, '', 1, 0, NULL),
(57, 7, '104', 2, 1, 'Normale', 1, 0, NULL),
(58, 7, '105', 2, 0, 'Normale', 1, 0, NULL),
(59, 7, '106', 2, 0, 'Normale', 0, 0, NULL),
(60, 7, '107', 2, 0, 'Normale', 1, 0, NULL),
(61, 7, '109', 2, 0, 'Normale', 1, 0, NULL),
(62, 7, '110', 2, 0, 'Normale', 0, 0, NULL),
(63, 7, '111', 2, 0, 'Normale', 1, 0, NULL),
(64, 8, '201', 2, 0, 'Normale', 1, 0, NULL),
(65, 8, '202', 2, 0, 'Normale', 1, 0, NULL),
(66, 8, '203', 2, 0, 'Normale', 1, 0, NULL),
(67, 8, '204', 2, 0, 'Normale', 1, 0, NULL),
(68, 8, '205', 2, 0, 'Normale', 1, 0, NULL),
(69, 8, '206', 2, 0, 'Normale', 1, 0, NULL),
(70, 8, '207', 2, 0, 'Normale', 1, 0, NULL),
(71, 8, '208', 2, 0, 'Normale', 1, 0, NULL),
(72, 8, '209', 2, 0, 'Normale', 1, 0, NULL),
(73, 8, '210', 2, 0, 'Normale', 1, 0, NULL),
(74, 8, '211', 2, 0, 'Normale', 1, 0, NULL),
(75, 9, 'Suite Antea', 2, 0, 'Suite', 1, 0, NULL),
(76, 9, 'Suite Nereide', 2, 0, 'Suite', 1, 0, NULL),
(77, 9, 'Suite Amorini', 2, 0, 'Suite', 1, 0, NULL);

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
(1, 4, 7, 56, '1111', NULL, 'MEDIA', 'RISOLTO', 1, 1, 1, '2026-01-11 12:10:48', '2026-01-19 21:01:06');

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
(1, 'alessio', 'alessio.patamia@gmail.com', '$2a$12$a3Nd6gUMG6JT2pC4ENyYlujlTltyE8takVCNZAVPS/vyrn5bVXcnG', 'Alessio', 'Patamia', NULL, 'root', 1, 0, NULL, NULL, NULL, NULL, '2026-01-22 10:58:25', '2025-12-13 16:51:13', '2026-01-22 10:58:25'),
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

-- --------------------------------------------------------

--
-- Struttura della tabella `gruppi_arrivi`
--

CREATE TABLE `gruppi_arrivi` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_gruppo` varchar(120) NOT NULL,
  `referente` varchar(120) NOT NULL,
  `agenzia` varchar(120) NOT NULL,
  `telefono` varchar(40) NOT NULL,
  `email` varchar(120) NOT NULL,
  `data_arrivo` date DEFAULT NULL,
  `data_partenza` date DEFAULT NULL,
  `numero_persone` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `numero_adulti` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `numero_bambini` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `tipologia_camere` varchar(120) DEFAULT NULL,
  `camere_json` longtext DEFAULT NULL,
  `area_preferita` varchar(120) DEFAULT NULL,
  `trattamento` varchar(20) DEFAULT NULL,
  `note_operativa` text DEFAULT NULL,
  `pasti_json` longtext DEFAULT NULL,
  `extra_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
-- Indici per le tabelle `gruppi_arrivi`
--
ALTER TABLE `gruppi_arrivi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gruppi_arrivi_nome` (`nome_gruppo`),
  ADD KEY `idx_gruppi_arrivi_data` (`data_arrivo`);

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
-- AUTO_INCREMENT per la tabella `pulizie_task`
--
ALTER TABLE `pulizie_task`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT per la tabella `soggiorni_clienti`
--
ALTER TABLE `soggiorni_clienti`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `soggiorni_tariffe`
--
ALTER TABLE `soggiorni_tariffe`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- AUTO_INCREMENT per la tabella `gruppi_arrivi`
--
ALTER TABLE `gruppi_arrivi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
