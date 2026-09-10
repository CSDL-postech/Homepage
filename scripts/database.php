<?php
declare(strict_types=1);
require_once __DIR__ . '/content.php';

function publicBoard(array $board, string $kind): void {
    ensure((int) $board['bo_list_level'] <= 1 && (int) $board['bo_read_level'] <= 1, 'Board requires login');
    ensure($board['bo_use_cert'] === '' && (int) $board['gr_use_access'] === 0, 'Board restricts public access');
    ensure($board['bo_notice'] === '', 'Pinned records need explicit ordering support');
    $skins = ['member' => 'member', 'publication' => 'publication', 'patent' => 'Patent'];
    ensure(isset($skins[$kind]) && $board['bo_skin'] === $skins[$kind], 'Unknown board skin');
    ensure(in_array($board['bo_sort_field'], ['', 'wr_datetime desc', 'ca_name desc, wr_num, wr_reply'], true), 'Unknown board sort order');
    ensure((int) $board['bo_page_rows'] > 0, 'Invalid board page size');
}

function databaseRecord(array $row, string $board, int $page): array {
    ensure($row['wr_subject'] !== '', 'Empty record title');
    // Parse stored markup as text, matching the public list parser.
    foreach ($row as $field => $value) {
        if ($field === 'wr_id') continue;
        if ($field === 'wr_subject') {
            // Gnuboard get_text renders titles literally and unescapes apostrophes.
            $row[$field] = trim(preg_replace('/\s+/u', ' ', str_replace("\\'", "'", $value)));
            continue;
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        ensure($dom->loadHTML('<meta charset="utf-8"><body>' . str_replace("\0", '', $value) . '</body>'), 'Invalid field markup');
        libxml_clear_errors();
        $row[$field] = cleanText($dom->getElementsByTagName('body')->item(0));
    }
    switch (BOARDS[$board]['kind']) {
        case 'member':
            return ['kind' => 'member', 'name' => $row['wr_subject'], 'roles' => $row['wr_1'] === '' ? [] : [$row['wr_1']], 'source' => recordUrl(sourceUrl($board, 1) . '&wr_id=' . $row['wr_id'], $board)];
        case 'publication':
            ensure($row['ca_name'] !== '', 'Missing publication year');
            $details = [];
            foreach (['wr_8', 'wr_4'] as $field) if ($row[$field] !== '') $details[] = ', ' . $row[$field];
            return ['kind' => 'publication', 'year' => $row['ca_name'], 'title' => ($row['wr_6'] === '' ? '' : '[' . $row['wr_6'] . '] ') . $row['wr_subject'], 'authors' => $row['wr_1'], 'venue' => $row['wr_2'], 'details' => implode(' ', $details), 'source' => recordUrl(sourceUrl($board, 1) . '&wr_id=' . $row['wr_id'], $board)];
        case 'patent':
            $text = $row['wr_subject'];
            foreach (['wr_4' => 'Inventor', 'wr_1' => 'Application No.', 'wr_6' => 'Application date', 'wr_3' => 'Registration No.', 'wr_5' => 'Registration date', 'wr_2' => 'Publication No.', 'wr_7' => 'Published date', 'wr_9' => 'Firm No.', 'wr_10' => 'Firm date'] as $field => $label) {
                if ($row[$field] !== '') $text .= ' ' . $label . ' : ' . $row[$field];
            }
            if ($row['wr_8'] !== '') $text .= ' ' . $row['wr_8'];
            return ['kind' => 'patent', 'text' => $text, 'source' => sourceUrl($board, $page)];
        default:
            throw new RuntimeException('Unknown board kind');
    }
}

function exportDatabase(mysqli $db): array {
    ensure($db->set_charset('utf8mb4'), 'Cannot set database charset');
    ensure($db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ') === true, 'Cannot set snapshot isolation');
    ensure($db->query('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT') === true, 'Cannot start read-only transaction');
    $snapshot = ['date' => gmdate('Y-m-d'), 'boards' => []];
    foreach (BOARDS as $board => $definition) {
        // Board IDs come only from the fixed BOARDS map, never user input.
        $metadata = $db->query("SELECT b.bo_list_level, b.bo_read_level, b.bo_use_cert, b.bo_notice, b.bo_skin, b.bo_sort_field, b.bo_page_rows, g.gr_use_access FROM g5_board b JOIN g5_group g ON b.gr_id = g.gr_id WHERE b.bo_table = '$board'");
        ensure($metadata !== false && $metadata->num_rows === 1, 'Missing board configuration: ' . $board);
        $settings = $metadata->fetch_assoc();
        publicBoard($settings, $definition['kind']);
        $engines = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('g5_board', 'g5_group', 'g5_write_$board')");
        ensure($engines !== false && $engines->num_rows === 3, 'Missing export tables');
        foreach ($engines as $engine) ensure($engine['ENGINE'] === 'InnoDB', 'Consistent export requires InnoDB');
        switch ($definition['kind']) {
            case 'member': $fields = 'wr_id, wr_subject, wr_1'; break;
            case 'publication': $fields = 'wr_id, wr_subject, ca_name, wr_1, wr_2, wr_4, wr_6, wr_8'; break;
            case 'patent': $fields = 'wr_id, wr_subject, wr_1, wr_2, wr_3, wr_4, wr_5, wr_6, wr_7, wr_8, wr_9, wr_10'; break;
            default: throw new RuntimeException('Unknown board kind');
        }
        $order = $settings['bo_sort_field'] === '' ? 'wr_num, wr_reply' : $settings['bo_sort_field'];
        $rows = $db->query("SELECT $fields FROM g5_write_$board WHERE wr_is_comment = 0 AND wr_option NOT LIKE '%secret%' ORDER BY $order, wr_id");
        ensure($rows !== false && $rows->num_rows > 0, 'Empty or invalid public board: ' . $board);
        $snapshot['boards'][$board] = [];
        foreach ($rows as $index => $row) $snapshot['boards'][$board][] = databaseRecord($row, $board, intdiv($index, (int) $settings['bo_page_rows']) + 1);
    }
    ensure($db->rollback(), 'Cannot close read-only transaction');
    validateSnapshot($snapshot);
    return $snapshot;
}
