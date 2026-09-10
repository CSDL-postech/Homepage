<?php
declare(strict_types=1);
require __DIR__ . '/../scripts/content.php';

function rejects(callable $test): void {
    try {
        $test();
    } catch (RuntimeException $error) {
        return;
    }
    throw new RuntimeException('Expected invalid input to fail');
}

$member = '<meta charset="utf-8"><form id="fboardlist"><input name="bo_table" value="sub4_1"><input name="page" value="1"><div class="mbBox"><div class="txt"><div class="name">A &amp; B</div><ul><li>Ph.D. student</li><li class="office"><img src="email.png"></li><!-- <li>Old role</li> --></ul><a class="btn_more" href="https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_1&amp;wr_id=7&amp;page=1">Profile</a></div></div></form><a class="pg_page pg_end" href="https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_1&amp;page=3">Last</a>';
$parsed = parsePage($member, 'sub4_1', 1);
ensure($parsed['last'] === 3 && count($parsed['records']) === 1, 'Pagination parsing');
ensure($parsed['records'][0]['name'] === 'A & B' && $parsed['records'][0]['roles'] === ['Ph.D. student'], 'Public member fields only');
ensure($parsed['records'][0]['source'] === 'https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_1&wr_id=7', 'Stable record URL');
rejects(function () use ($member) { parsePage($member, 'sub4_1', 2); });
rejects(function () use ($member) { parsePage(str_replace('mbBox', 'changed', $member), 'sub4_1', 1); });
rejects(function () use ($member) { parsePage($member, 'unknown', 1); });
rejects(function () { recordUrl('https://example.com/?bo_table=sub4_1&wr_id=7', 'sub4_1'); });

$publication = '<meta charset="utf-8"><div id="bo_list_total">Total 1건</div><form id="fboardlist"><input name="bo_table" value="sub5_1"><input name="page" value="1"><div class="year_title">2027</div><table><tr><td class="name_title"><a href="https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1&amp;wr_id=9"><span class="text_title"><span>[Award]</span> Paper &amp; title</span><span class="text_author">First, Second</span><span class="text_journal">Conference</span><span class="text_vol">, accepted</span></a></td></tr></table></form>';
$parsed = parsePage($publication, 'sub5_1', 1);
ensure($parsed['total'] === 1 && $parsed['records'][0]['year'] === '2027', 'Publication year and total');
ensure($parsed['records'][0]['title'] === '[Award] Paper & title' && $parsed['records'][0]['details'] === ', accepted', 'Awards and acceptance status');
rejects(function () use ($publication) { parsePage(str_replace('text_title', 'changed', $publication), 'sub5_1', 1); });
$patent = str_replace(['sub5_1', 'class="text_title"'], ['sub5_1_c', 'class="text_title stit"'], $publication);
ensure(parsePage($patent, 'sub5_1_c', 1)['records'][0]['kind'] === 'patent', 'Patent branch');

$snapshot = ['date' => '2026-09-10', 'boards' => ['sub5_1' => $parsed['records']]];
$snapshot['boards']['sub5_1'][0]['title'] = '<script>alert(1)</script>';
$html = renderContent($snapshot, ['sub5_1']);
ensure(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Escape imported text');
$snapshot['boards']['sub5_1'][0]['kind'] = 'unknown';
rejects(function () use ($snapshot) { renderContent($snapshot, ['sub5_1']); });
echo "Parser, pagination, source URL, escaping, and failure checks passed.\n";
