<?php
declare(strict_types=1);
ini_set('zend.exception_ignore_args', '1');
require __DIR__ . '/database.php';
ensure($argc === 1, 'Usage: sudo php scripts/export-db.php');
// Read only the three needed settings; never execute the main site's env file.
$env = file_get_contents('/home/csdl/csdl-migration/ops/env/.env');
ensure($env !== false, 'Cannot read main-site environment');
$settings = [];
foreach (['DB_NAME', 'DB_USER', 'DB_PASSWORD_FILE'] as $key) {
    ensure(preg_match_all('/^' . $key . '=([^\r\n]+)$/m', $env, $matches) === 1, 'Missing or duplicate setting: ' . $key);
    $settings[$key] = trim($matches[1][0], " \t\"'");
}
unset($env);
ensure(preg_match('/^[a-zA-Z0-9_]+$/', $settings['DB_NAME']) === 1, 'Invalid database name');
ensure($settings['DB_PASSWORD_FILE'][0] === '/', 'Password file must use an absolute path');
$password = @file_get_contents($settings['DB_PASSWORD_FILE']);
ensure($password !== false && rtrim($password, "\r\n") !== '', 'Cannot read DB password file; run the exporter with sudo');
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
ensure(@$db->real_connect('localhost', $settings['DB_USER'], rtrim($password, "\r\n"), $settings['DB_NAME'], 0, '/run/mysqld/mysqld.sock'), 'Database connection failed; check the existing main-site credentials');
unset($password);
// Only public JSON goes to stdout. This process never writes repository files.
echo json_encode(exportDatabase($db), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
$db->close();
