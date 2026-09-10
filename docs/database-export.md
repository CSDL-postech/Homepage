# Read-only database export

Two update paths are available: public HTTPS import and an operator-run MariaDB
export. The database path uses the existing main-site secret through sudo.
It does not require a new DB account, grant, view, or secret file.

## Run the sudo export

From a shell logged in as `csdl`:

```sh
cd /home/csdl/csdl-github-static-homepage
set -o pipefail
sudo php scripts/export-db.php | php scripts/sync-content.php import
php tests/content.php
php tests/database.php
php scripts/sync-content.php check
git diff --check
git diff --stat
```

Enter your sudo password in your terminal if prompted. Only the left side of
the pipe runs with sudo. Do not put the whole pipeline inside `sudo sh -c` or
run the importer as root: it must write files as the repository owner.
The exporter writes only JSON to stdout after all boards pass validation;
the importer validates the full snapshot before writing the three output files.
A failed export or invalid input leaves the previous snapshot and pages intact.

Review the diff, preview the pages, then commit and push from the normal-user
shell as described in the README. Export does not publish, commit, push, or
install a scheduled job.

## Connection and query scope

The exporter requires PHP CLI with DOM/libxml and MySQLi. The normal-user
importer also requires POSIX. It reads only `DB_NAME`, `DB_USER`, and
`DB_PASSWORD_FILE` from `/home/csdl/csdl-migration/ops/env/.env`. It parses those
assignments as text, without sourcing shell commands or executing main-site PHP.
The current password path is `/etc/csdl-secrets/db_password`; sudo permits the
operator to read it. Passwords are not placed in command arguments or output.

Connections use `/run/mysqld/mysqld.sock`, the verified `g5_` table prefix, and
the existing account. Its grants are unchanged. Sudo grants OS access to the
secret; MariaDB still authenticates the configured account. This is a trusted
operator workflow using that account's existing privileges, not a dedicated
SELECT-only security boundary. Do not grant passwordless sudo to an editable
checkout as a substitute for a restricted export account.

The query code in `scripts/database.php`:

- Opens a repeatable-read, read-only consistent snapshot and requires InnoDB.
- Checks board and group access. It refuses login-only, identity-restricted,
  group-restricted, unknown-skin, unknown-sort, or pinned-record configurations.
- Reads only explicit public fields from the six known board tables; it excludes
  comments and records whose options contain `secret`.
- Preserves the verified public ordering, names, roles, authors, awards, venue,
  year, acceptance status, and patent details. It normalizes titles like the
  main site's public list renderer.
- Closes the transaction with rollback, validates the snapshot, then emits JSON.
  It does not call main-site application code, update views/hit counts, modify
  records, or query account/password columns.

See [MariaDB transaction modes](https://mariadb.com/docs/server/reference/sql-statements/transactions/start-transaction)
for read-only and consistent-snapshot behavior. Query errors or changed source
assumptions stop export; inspect them rather than relaxing visibility checks.

The importer accepts the same snapshot schema used by the HTTPS importer. It
rejects missing/extra fields, unknown record kinds, empty boards, duplicate IDs,
and unexpected source URLs. Only `data/content.json`, `Members.html`, and
`Publications.html` are written. Archive pages and other repositories stay intact.

## Outage use

The exporter can refresh the static snapshot when Apache or the PHP web
container is unavailable, provided the host, MariaDB, and secret file remain
accessible. It cannot refresh when the host or database is down. In that case,
use the already published GitHub Pages site or render the saved snapshot offline.

## Verification evidence: 2026-09-10

- A live read-only export matched all 786 records in the saved HTTPS snapshot:
  32 current members, 110 alumni, 199 international conference papers,
  116 international journal papers, 168 domestic publications, and 161 patents.
- Verified the six boards' skins, access settings, ordering, and InnoDB engines.
- Tested the same export function using the existing readable application
  configuration for connection credentials, without printing secret values.
- Valid JSON imported without changing the existing snapshot or generated pages.
  Empty, malformed, and extra-field inputs failed without changing those files.
- PHP syntax, parser, DB mapping, visibility, snapshot validation, and offline
  consistency checks passed.
- The exact sudo launcher was not run successfully in this session: `sudo -n`
  requires an interactive password. Without sudo, the exporter correctly failed
  to read the password file and emitted no JSON. Run the command above in your
  terminal to verify that final authentication step.

## Optional dedicated account for scheduled exports

For unattended operation, prefer an administrator-provisioned local account
with SELECT only on reviewed public export views. Each view should select only
public columns and enforce anonymous-access and record-visibility filters.
Do not grant access to account tables, private fields, or database writes.
MariaDB supports [object and column SELECT grants](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant).

Store that separate account's configuration and secret outside both repositories
and web roots, in a runner-owned directory with mode `0700` and files with mode
`0600`. A future exporter mode would explicitly load that configuration and query
those views, with the same comparison and failure tests. No dedicated account,
views, or separate-secret mode has been provisioned or implemented here. The
current sudo launcher intentionally reads the existing main-site configuration.
