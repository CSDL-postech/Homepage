<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/database.php';

function rejectsDatabase(callable $test): void {
    try { $test(); } catch (RuntimeException $error) { return; }
    throw new RuntimeException('Expected invalid export input to fail');
}

$board = ['bo_list_level' => '1', 'bo_read_level' => '1', 'bo_use_cert' => '', 'gr_use_access' => '0', 'bo_notice' => '', 'bo_skin' => 'member', 'bo_sort_field' => 'wr_datetime desc', 'bo_page_rows' => '16'];
publicBoard($board, 'member');
foreach (['bo_list_level' => '2', 'bo_read_level' => '2', 'bo_use_cert' => 'adult', 'gr_use_access' => '1', 'bo_notice' => '42', 'bo_skin' => 'custom', 'bo_sort_field' => 'wr_id; DELETE FROM g5_board', 'bo_page_rows' => '0'] as $field => $value) {
    rejectsDatabase(function () use ($board, $field, $value) { $board[$field] = $value; publicBoard($board, 'member'); });
}
$member = databaseRecord(['wr_id' => '2', 'wr_subject' => 'Name <literal>', 'wr_1' => '<b>MS.</b> student'], 'sub4_1', 1);
ensure($member['name'] === 'Name <literal>' && $member['roles'] === ['MS. student'], 'Member text conversion');
$paper = databaseRecord(['wr_id' => '3', 'wr_subject' => "Amdahl\\'s Law", 'ca_name' => '2027', 'wr_1' => 'A, B', 'wr_2' => 'Conference', 'wr_4' => 'accepted', 'wr_6' => 'Best Paper', 'wr_8' => '2027'], 'sub5_1', 1);
ensure($paper['title'] === "[Best Paper] Amdahl's Law" && $paper['details'] === ', 2027 , accepted', 'Publication title, award, and status');
$patent = databaseRecord(['wr_id' => '4', 'wr_subject' => 'Patent', 'wr_1' => '123', 'wr_2' => '', 'wr_3' => '', 'wr_4' => 'Inventor', 'wr_5' => '', 'wr_6' => '2026.01.01', 'wr_7' => '', 'wr_8' => '', 'wr_9' => '', 'wr_10' => ''], 'sub5_1_c', 2);
ensure($patent['text'] === 'Patent Inventor : Inventor Application No. : 123 Application date : 2026.01.01' && $patent['source'] === sourceUrl('sub5_1_c', 2), 'Patent metadata');

$snapshot = json_decode(file_get_contents(__DIR__ . '/../data/content.json'), true, 512, JSON_THROW_ON_ERROR);
validateSnapshot($snapshot);
rejectsDatabase(function () use ($snapshot) { $snapshot['boards']['sub4_1'][0]['password'] = 'must not be imported'; validateSnapshot($snapshot); });
rejectsDatabase(function () use ($snapshot) { $snapshot['boards']['sub4_1'][] = $snapshot['boards']['sub4_1'][0]; validateSnapshot($snapshot); });
rejectsDatabase(function () use ($snapshot) { $snapshot['boards']['sub4_1'] = []; validateSnapshot($snapshot); });
rejectsDatabase(function () use ($snapshot) { $snapshot['boards']['sub4_1'][0]['source'] = 'https://example.com/'; validateSnapshot($snapshot); });
rejectsDatabase(function () use ($snapshot) { $snapshot['boards']['sub4_1'][0]['kind'] = 'private'; validateSnapshot($snapshot); });
echo "Database mapping, visibility, ordering, and snapshot validation checks passed.\n";
