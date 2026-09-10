# CSDL static backup website

This repository contains the simple GitHub Pages backup website for the
CAD & SoC Design Laboratory at POSTECH. The main lab website is
[csdl.postech.ac.kr](https://csdl.postech.ac.kr/).

The main website is served and developed separately. Its source project is
maintained in `~/csdl-migration`; that directory is not part of this repository.
This backup keeps essential lab information available as static files. Content
updates are manual; there is no automatic sync with the main site.

## Files

| Path | Content |
| --- | --- |
| `index.html` | Lab introduction and links to introductory media |
| `Research.html` | Research topics |
| `Advisor.html` | Advisor profiles |
| `Members.html` | Current members and alumni |
| `Publications.html` | Publications by category and year, plus patents |
| `Image/` | Logos, portraits, and research images |
| `PDF/` | Lab introduction and advisor documents |
| `Video/` | Lab videos |
| `AGENTS.md` | Contributor and coding-agent instructions |

Each page contains its own CSS. Pages with tabs also contain a small JavaScript
script. There is no build step or dependency installation.

## Preview and verify

Open `index.html` in a browser. Follow the navigation to each changed page.
Check desktop and mobile widths, tabs, images, document and video links, and the
main-site link. Local paths must match filename case because hosting is case
sensitive. Run `git diff --check` before submitting changes.

The site is intended for GitHub Pages. Confirm the publishing branch and folder
in the repository's **Settings → Pages** before deployment; this checkout does
not define a deployment workflow. Keep page and asset links relative so they
work under a GitHub Pages repository path.

## Content sources

Start with `/home/csdl/README_home_map.md` for server orientation. The main
project's `docs/11-current-production-architecture.md` and
`docs/12-operations-handbook.md` explain production storage and operations.
Application code lives in `~/csdl-migration/www`; current board records live
in host MariaDB, and uploaded media lives in `/home/csdl/csdl-data`.
A Git checkout alone does not contain the current member and publication lists.
This static website is not a database or service-recovery backup.

| Backup section | Public source |
| --- | --- |
| Current members | [Current member board](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_1) |
| Alumni | [Alumni board](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub4_2) |
| Publications | [Publication board](https://csdl.postech.ac.kr/bbs/board.php?bo_table=sub5_1) |

These routes are defined in `www/top_navi.php` in the main project.
Check all relevant board pages and categories, including pagination.
`www/research_map/data.json` is a derived research-map dataset; do not assume
it covers every publication or patent in this backup. Historical SQL dumps are
not a current content source. Copy only the public fields and media needed by
this site, not database dumps or runtime directories.

## Update members and publications

1. Compare the backup with confirmed public content on the main site or in its
   source project. Identify additions, corrections, and member status changes.
2. Edit `Members.html` in the Current Members or Alumni section. Preserve each
   person's confirmed name, status, and public profile details.
3. Edit `Publications.html` in the matching category and year. Keep the existing
   newest-first year order. Check author order, title, venue, year, and links.
   Check for an existing entry before adding a paper.
4. Add required public media to the matching asset folder. Keep existing paths
   stable and verify new links.
5. Preview the affected pages and review the diff. Record the source URLs,
   review date, sections checked, additions, corrections, and checks in the
   commit or pull request description. State any sections that remain unchecked.

Update the main site first, then reconcile this backup in the same maintenance
session. For members, check moves to alumni as well as new arrivals. For papers,
match by DOI when available, otherwise by title and authors; publication status
or venue changes can be corrections to existing entries. Do not delete an entry
only because it is absent from one source page.

For now, direct HTML edits keep the maintenance process small. If update volume
requires automation, add an explicit export of public member and publication
fields from the main project and generate these static pages from that export.
Keep record IDs stable, validate required fields, and review the generated diff
before publishing. The published backup must serve its content without a live
request to the main site. This export workflow is a future option, not an
existing sync feature.

Keep this backup small and independently usable. Update shared navigation and
the backup notice consistently across all five pages. Content synchronization
is the next maintenance task; the presence of the main-site link does not mean
that the member and publication lists are current.
