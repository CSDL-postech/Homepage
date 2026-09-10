<?php
declare(strict_types=1);
require __DIR__ . '/content.php';
ensure($argc === 2 && in_array($argv[1], ['fetch', 'import', 'render', 'check'], true), 'Usage: php scripts/sync-content.php fetch|import|render|check');
$root = dirname(__DIR__);
if ($argv[1] === 'fetch') {
    $snapshot = ['date' => gmdate('Y-m-d'), 'boards' => []];
    foreach (BOARDS as $board => $definition) {
        $records = [];
        $last = 1;
        $total = null;
        for ($page = 1; $page <= $last; $page++) {
            $html = file_get_contents(sourceUrl($board, $page), false, stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 0, 'user_agent' => 'CSDL-static-backup-sync']]));
            ensure($html !== false, 'Could not fetch ' . $board);
            $parsed = parsePage($html, $board, $page);
            if ($page === 1) {
                $last = $parsed['last'];
                $total = $parsed['total'];
            }
            ensure($last === $parsed['last'] && $total === $parsed['total'], 'Board pagination changed during fetch');
            $records = array_merge($records, $parsed['records']);
            usleep(100000);
        }
        if ($definition['kind'] !== 'member') ensure(count($records) === $total, 'Incomplete board: ' . $board);
        $keys = array_map(function ($record) {
            return $record['kind'] === 'patent' ? $record['text'] : $record['source'];
        }, $records);
        ensure(count(array_unique($keys)) === count($keys), 'Duplicate records: ' . $board);
        $snapshot['boards'][$board] = $records;
        echo $board . ': ' . count($records) . " records\n";
    }
} elseif ($argv[1] === 'import') {
    ensure(posix_geteuid() !== 0, 'Run import as the repository owner, without sudo');
    $snapshot = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
} else {
    $snapshot = json_decode(file_get_contents($root . '/data/content.json'), true, 512, JSON_THROW_ON_ERROR);
}
validateSnapshot($snapshot);
$pages = [
    'Members.html' => ['sub4_1', 'sub4_2'],
    'Publications.html' => ['sub5_1', 'sub5_1_b', 'sub5_1_d', 'sub5_1_c'],
];
$output = [];
foreach ($pages as $file => $boards) {
    $html = file_get_contents($root . '/' . $file);
    ensure(substr_count($html, '<!-- BEGIN SYNCED CONTENT -->') === 1 && substr_count($html, '<!-- END SYNCED CONTENT -->') === 1, 'Missing or duplicate sync markers');
    $output[$file] = preg_replace_callback('/<!-- BEGIN SYNCED CONTENT -->.*?<!-- END SYNCED CONTENT -->/s', function () use ($snapshot, $boards) { return renderContent($snapshot, $boards); }, $html);
    if ($argv[1] === 'check') ensure($output[$file] === $html, $file . ' differs from snapshot');
}
if ($argv[1] === 'check') {
    echo "Generated pages match the snapshot.\n";
    exit;
}
// Fetch and validate every board and page template before writing any output.
if (in_array($argv[1], ['fetch', 'import'], true)) file_put_contents($root . '/data/content.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
foreach ($output as $file => $html) file_put_contents($root . '/' . $file, $html);
