# CSDL static backup website

This repository contains the GitHub Pages backup website for the CAD & SoC
Design Laboratory at POSTECH. The main website is
[csdl.postech.ac.kr](https://csdl.postech.ac.kr/).

The published site is plain HTML, CSS, JavaScript, and local media. It needs no
server application or build service. Members and publications are generated
from a checked-in snapshot of the main site's public boards. Reading the lists
does not require the main site to be available; external profile and paper
links still lead to the main site.

## Files

| Path | Content |
| --- | --- |
| `index.html` | Lab introduction and introductory media links |
| `Research.html` | Research topics |
| `Advisor.html` | Advisor profiles |
| `Members.html` | Generated current members and alumni |
| `Publications.html` | Generated conferences, journals, domestic publications, and patents |
| `Members-archive.html`, `Publications-archive.html` | Historical lists before the first sync; not maintained |
| `data/content.json` | Public content snapshot with source URLs and fetch date |
| `scripts/sync-content.php` | Fetch, render, and consistency-check commands |
| `scripts/content.php` | Public board parser and HTML renderer |
| `tests/content.php` | Parser and renderer regression checks |
| `Image/`, `PDF/`, `Video/` | Local media |
| `AGENTS.md` | Contributor and coding-agent instructions |

## Update members and publications

Run from this repository with PHP CLI 7.4 or later, DOM/libxml, and HTTPS stream
support. These are maintenance tools only; GitHub Pages does not run PHP.
No Composer, Python, Node, or database dependency is required.

```sh
php scripts/sync-content.php fetch
php tests/content.php
php scripts/sync-content.php check
git diff --check
git diff --stat
```

`fetch` sends sequential, unauthenticated HTTPS GET requests to the six public
boards below. It follows pagination, checks publication totals, rejects duplicate
records and unexpected markup, and validates both page templates before writing
`data/content.json`, `Members.html`, and `Publications.html`. A fetch or parsing
failure leaves those files unchanged. Review the resulting diff before commit
and publication, especially removals, member status changes, and source typos.
The importer does not commit, push, schedule jobs, access MariaDB, or write to
other repositories.

Update the main site first, then run the sync in the same maintenance session.
The command is repeatable and can be run by a future scheduled review job.
No scheduled job is installed. Do not edit generated blocks between the sync
markers by hand; the next render replaces them. Make factual corrections on the
main site before fetching again. Shared layout remains outside those markers.

To regenerate from the saved snapshot without network access:

```sh
php scripts/sync-content.php render
```

`check` also works offline. It exits with an error if the generated HTML differs
from the saved snapshot. The regression checks cover pagination, wrong-page
responses, missing markup, stable record URLs, acceptance status, HTML escaping,
and unknown record kinds.

## Content sources and scope

| Section | Public source board |
| --- | --- |
| Current members | [sub4_1](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_1) |
| Alumni | [sub4_2](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_2) |
| International conferences | [sub5_1](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1) |
| International journals | [sub5_1_b](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1_b) |
| Domestic publications | [sub5_1_d](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1_d) |
| Patents | [sub5_1_c](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1_c) |

Members include public names, current roles or positions, and profile links.
The importer does not extract email images or copy portraits. Publications
include titles, author order, venues, year groups, awards, and status text.
Patents retain the public inventor and application/publication details. Patent
source links point to their board page because not all rows expose record URLs.
Temporary download links are excluded. Source spelling and ordering are kept.
The source combines domestic conferences and journals in one board, so this
backup now uses one Domestic Publications tab.

The historical pages preserve prior information that may not appear on the
current boards, including older contact details and publication categories.
They are labeled as historical and linked from the current lists.

The first sync on **2026-09-10** imported **32 current members, 110 alumni,
199 international conference papers, 116 international journal papers,
168 domestic publications, and 161 patents**. Publication totals matched the
boards. Member pages expose pagination but no total; all listed pages were read
and profile IDs were checked for duplicates. PHP syntax, parser regression,
offline consistency, local-link, and tab checks passed. A browser visual check
was not run because no browser was available in the maintenance environment.

## Main-site project

Start with `/home/csdl/README_home_map.md` for server orientation. Main-site code
lives in `~/csdl-migration/www`. Its `docs/11-current-production-architecture.md`
and `docs/12-operations-handbook.md` describe the service. Current content lives
in host MariaDB and uploaded media in `/home/csdl/csdl-data`, outside that Git
checkout. Historical SQL dumps and `www/research_map/data.json` are not complete
current content sources. This static website is not a service-recovery backup.

Public-page import avoids database credentials and production code changes.
If the board markup changes, update the parser and its tests here. If a future
DB export is needed, use a dedicated SELECT-only account and an explicit list
of public fields; do not import whole tables or runtime directories.

## Preview and publish

Open `index.html` in a browser and follow navigation to the changed pages. Check
desktop and mobile widths, tabs, main-site links, archives, and local media.
Local paths must match filename case. Keep asset links relative for GitHub Pages
repository paths. Update shared navigation and backup notices consistently on
the five current pages.

Confirm the publishing branch and folder in **Settings → Pages** before
deployment; this checkout does not define a deployment workflow. Review and
commit the snapshot and both generated pages together. Record the source date,
counts, and validation results in the commit or pull request description.
