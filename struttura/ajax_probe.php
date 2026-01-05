<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);

function out_json(array $p, int $code=200): void {
  while (ob_get_level() > 0) { @ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function require_probe(string $file, array &$log): void {
  $before = ob_get_length();
  $err = null;

  // intercetta warning/notice SOLO durante questo include
  $prev = set_error_handler(function($severity,$message,$f,$line) use (&$err, $file) {
    $err = "PHP warning/notice in $file: $message ($f:$line)";
    return true; // blocca output warning
  });

  ob_start();
  require $file;
  $buf = ob_get_clean();

  if ($prev) set_error_handler($prev); else restore_error_handler();

  $log[] = [
    'file' => $file,
    'output_len' => strlen($buf),
    'output_head' => $buf === '' ? '' : substr($buf, 0, 200),
    'error' => $err
  ];
}

$log = [];
ob_start();

// ✅ ADATTA QUI SOLO I REQUIRE “BASE” CHE USI NEI TUOI AJAX
// Esempi comuni nel tuo progetto: db.php / config.php / auth.php / funzioni.php
// Metti quelli reali del tuo progetto:
$base = __DIR__;

// ESEMPIO: cambiali coi tuoi
$includes = [
  $base . '/config.php',
  $base . '/db.php',
  $base . '/auth.php',
  $base . '/funzioni.php',
];

foreach ($includes as $f) {
  if (is_file($f)) require_probe($f, $log);
}

$finalBuf = ob_get_clean();

out_json([
  'ok' => true,
  'final_output_len' => strlen($finalBuf),
  'final_output_head' => $finalBuf === '' ? '' : substr($finalBuf, 0, 200),
  'includes' => $log
]);
