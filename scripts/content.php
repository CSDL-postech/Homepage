<?php
declare(strict_types=1);
// Source HTML is never copied into output.
const BOARDS = [
    'sub4_1' => ['kind' => 'member', 'label' => 'Current Members', 'tab' => 'current'],
    'sub4_2' => ['kind' => 'member', 'label' => 'Alumni', 'tab' => 'alum'],
    'sub5_1' => ['kind' => 'publication', 'label' => 'International Conference', 'tab' => 'internal_conf'],
    'sub5_1_b' => ['kind' => 'publication', 'label' => 'International Journal', 'tab' => 'internal_jour'],
    'sub5_1_d' => ['kind' => 'publication', 'label' => 'Domestic Publications', 'tab' => 'domestic_conf'],
    'sub5_1_c' => ['kind' => 'patent', 'label' => 'Patents', 'tab' => 'pat'],
];

function ensure(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function cleanText(DOMNode $node): string {
    return trim(preg_replace('/\s+/u', ' ', $node->textContent));
}

function sourceUrl(string $board, int $page): string {
    return 'https://csdl.postech.ac.kr/bbs/board.php?bo_table=' . $board . '&page=' . $page;
}

function recordUrl(string $url, string $board): string {
    ensure(parse_url($url, PHP_URL_HOST) === 'csdl.postech.ac.kr', 'Unexpected record host');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    ensure($query['bo_table'] === $board && ctype_digit($query['wr_id']), 'Invalid record ID');
    return 'https://csdl.postech.ac.kr/bbs/board.php?bo_table=' . $board . '&wr_id=' . $query['wr_id'];
}

function parsePage(string $html, string $board, int $page): array {
    ensure(isset(BOARDS[$board]), 'Unknown board');
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // The legacy site has invalid HTML and NUL bytes.
    ensure($dom->loadHTML(str_replace("\0", '', $html)), 'Invalid source HTML');
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    ensure($xpath->query('//*[@id="fboardlist"]')->length === 1, 'Missing board form');
    ensure($xpath->query('//input[@name="bo_table"]')->item(0)->getAttribute('value') === $board, 'Wrong board returned');
    ensure((int) $xpath->query('//form[@id="fboardlist"]/input[@name="page"]')->item(0)->getAttribute('value') === $page, 'Wrong page returned');
    $last = 1;
    foreach ($xpath->query('//a[contains(@class,"pg_page")]') as $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        ensure($query['bo_table'] === $board, 'Pagination changed board');
        $last = max($last, (int) $query['page']);
    }
    $total = null; // Member lists have no total; publication boards do.
    if (BOARDS[$board]['kind'] !== 'member') {
        ensure(preg_match('/Total\s+([\d,]+)/u', cleanText($xpath->query('//*[@id="bo_list_total"]')->item(0)), $match) === 1, 'Missing total');
        $total = (int) str_replace(',', '', $match[1]);
    }
    $records = [];
    switch (BOARDS[$board]['kind']) {
        case 'member':
            foreach ($xpath->query('//div[@class="mbBox"]') as $box) {
                $name = $xpath->query('.//div[@class="name"]', $box);
                $link = $xpath->query('.//a[@class="btn_more"]', $box);
                ensure($name->length === 1 && $link->length === 1, 'Member markup changed');
                ensure(cleanText($name->item(0)) !== '', 'Empty member name');
                $roles = [];
                foreach ($xpath->query('.//div[@class="txt"]/ul/li[not(@class="office")]', $box) as $role) {
                    if (cleanText($role) !== '') $roles[] = cleanText($role);
                }
                $records[] = ['kind' => 'member', 'name' => cleanText($name->item(0)), 'roles' => $roles, 'source' => recordUrl($link->item(0)->getAttribute('href'), $board)];
            }
            break;
        case 'publication':
            foreach ($xpath->query('//td[@class="name_title"]') as $cell) {
                $title = $xpath->query('.//span[@class="text_title"]', $cell);
                $link = $xpath->query('./a', $cell);
                $year = $xpath->query('preceding::div[@class="year_title"][1]', $cell);
                ensure($title->length === 1 && $link->length === 1 && $year->length === 1, 'Publication markup changed');
                ensure(cleanText($title->item(0)) !== '' && cleanText($year->item(0)) !== '', 'Empty publication title or year');
                $fields = [];
                foreach (['text_author', 'text_journal', 'text_vol'] as $class) {
                    $fields[$class] = [];
                    foreach ($xpath->query('.//span[@class="' . $class . '"]', $cell) as $field) $fields[$class][] = cleanText($field);
                }
                $records[] = ['kind' => 'publication', 'year' => cleanText($year->item(0)), 'title' => cleanText($title->item(0)), 'authors' => implode(' ', $fields['text_author']), 'venue' => implode(' ', $fields['text_journal']), 'details' => implode(' ', $fields['text_vol']), 'source' => recordUrl($link->item(0)->getAttribute('href'), $board)];
            }
            break;
        case 'patent':
            foreach ($xpath->query('//td[@class="name_title"]') as $cell) {
                ensure($xpath->query('.//span[contains(@class,"text_title")]', $cell)->length === 1, 'Patent markup changed');
                $records[] = ['kind' => 'patent', 'text' => cleanText($cell), 'source' => sourceUrl($board, $page)];
            }
            break;
        default:
            throw new RuntimeException('Unknown record kind');
    }
    ensure(count($records) > 0, 'Empty source page: ' . $board);
    return ['last' => max($last, $page), 'total' => $total, 'records' => $records];
}

function escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function renderContent(array $snapshot, array $boards): string {
    $html = '<!-- BEGIN SYNCED CONTENT -->' . "\n";
    $html .= '<div class="tab-box">' . "\n";
    foreach ($boards as $index => $board) {
        $html .= '<button type="button" class="tab-btn' . ($index === 0 ? ' active' : '') . '" data-target="' . BOARDS[$board]['tab'] . '">' . BOARDS[$board]['label'] . '</button>' . "\n";
    }
    $html .= "</div>\n";
    foreach ($boards as $index => $board) {
        $html .= '<div id="' . BOARDS[$board]['tab'] . '" class="tab-content' . ($index === 0 ? ' active' : '') . '">' . "\n";
        $html .= '<h2>' . BOARDS[$board]['label'] . '</h2>' . "\n";
        if (BOARDS[$board]['kind'] !== 'publication') $html .= "<ul>\n";
        $year = '';
        foreach ($snapshot['boards'][$board] as $record) {
            ensure($record['kind'] === BOARDS[$board]['kind'], 'Record kind does not match board');
            ensure(strpos($record['source'], 'https://csdl.postech.ac.kr/bbs/board.php?') === 0, 'Unexpected source URL');
            switch ($record['kind']) {
                case 'member':
                    $html .= '<li><a href="' . escape($record['source']) . '">' . escape($record['name']) . '</a>' . (count($record['roles']) ? ' — ' . escape(implode('; ', $record['roles'])) : '') . "</li>\n";
                    break;
                case 'publication':
                    if ($year !== $record['year']) {
                        if ($year !== '') $html .= "</ul>\n";
                        $year = $record['year'];
                        $html .= '<h4>' . escape($year) . "</h4><ul>\n";
                    }
                    $html .= '<li>' . escape($record['authors']) . ' <strong><a href="' . escape($record['source']) . '">' . escape($record['title']) . '</a></strong>. <em>' . escape($record['venue']) . '</em>' . escape($record['details']) . ".</li>\n";
                    break;
                case 'patent':
                    $html .= '<li>' . escape($record['text']) . ' <a href="' . escape($record['source']) . '">Source</a>.</li>' . "\n";
                    break;
                default:
                    throw new RuntimeException('Unknown record kind');
            }
        }
        $html .= "</ul>\n</div>\n";
    }
    $html .= '<p class="sync-note">Last updated: ' . escape($snapshot['date']) . '.</p>' . "\n";
    return $html . '<!-- END SYNCED CONTENT -->';
}

function validateSnapshot(array $snapshot): void {
    ensure(array_keys($snapshot) === ['date', 'boards'], 'Unexpected snapshot fields');
    ensure(is_string($snapshot['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshot['date']) === 1, 'Invalid snapshot date');
    ensure(array_keys($snapshot['boards']) === array_keys(BOARDS), 'Snapshot board set changed');
    $fields = [
        'member' => ['kind', 'name', 'roles', 'source'],
        'publication' => ['kind', 'year', 'title', 'authors', 'venue', 'details', 'source'],
        'patent' => ['kind', 'text', 'source'],
    ];
    foreach (BOARDS as $board => $definition) {
        ensure(count($snapshot['boards'][$board]) > 0, 'Empty board: ' . $board);
        $keys = [];
        foreach ($snapshot['boards'][$board] as $record) {
            ensure($record['kind'] === $definition['kind'], 'Record kind does not match board');
            ensure(array_keys($record) === $fields[$definition['kind']], 'Unexpected record fields');
            foreach ($record as $field => $value) {
                if ($field === 'roles') {
                    ensure(is_array($value), 'Invalid member roles');
                    foreach ($value as $role) ensure(is_string($role), 'Invalid role');
                } else ensure(is_string($value), 'Invalid record field: ' . $field);
            }
            if ($definition['kind'] === 'patent') {
                ensure($record['text'] !== '' && preg_match('~^https://csdl\.postech\.ac\.kr/bbs/board\.php\?bo_table=' . $board . '&page=[1-9][0-9]*$~', $record['source']) === 1, 'Invalid patent');
                $keys[] = $record['text'];
            } else {
                ensure($record['source'] === recordUrl($record['source'], $board), 'Noncanonical record URL');
                ensure($record[$definition['kind'] === 'member' ? 'name' : 'title'] !== '', 'Empty record title');
                if ($definition['kind'] === 'publication') ensure($record['year'] !== '', 'Empty publication year');
                $keys[] = $record['source'];
            }
        }
        ensure(count(array_unique($keys)) === count($keys), 'Duplicate records: ' . $board);
    }
}
