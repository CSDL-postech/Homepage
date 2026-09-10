# Optional read-only database export

Status: design only. The current importer reads public HTTPS pages and needs
no database credentials. No database user, grant, secret file, or DB exporter
has been created for this backup.

A DB exporter could refresh the snapshot when PHP or Apache is unavailable but
MariaDB and this host are still accessible. It cannot refresh data when the
host or database is down. The already published GitHub Pages snapshot remains
the outage reference in either case.

## Access and secrets

A `.env` file supplies configuration; it does not bypass MariaDB authentication,
SQL privileges, filesystem permissions, or `sudo`. The earlier local access
check failed for `csdl` without a password, and `sudo -n` required a password.
An administrator must provision the export account and secret once. Later
exports should run without elevated privileges.

The main project uses `DB_PASSWORD_FILE` in
`~/csdl-migration/ops/env/.env.example`, with secrets outside Git under
`/etc/csdl-secrets`. Follow that separation with a dedicated export credential.
Do not reuse the main application's account, root password, or live `.env`.

Proposed host-only configuration, outside both repositories and web roots:

```dotenv
# /home/csdl/.config/csdl-static-backup/export.env
DB_SOCKET=/run/mysqld/mysqld.sock
DB_NAME=REPLACE_WITH_VERIFIED_DATABASE
DB_USER=csdl_static_export
DB_PASSWORD_FILE=/home/csdl/.config/csdl-static-backup/db_password
```

The password file would contain only the dedicated account's password. The
administrator should make the directory private (mode `0700`) and the files
readable only by the export runner (mode `0600`), with ownership assigned to
that runner. The future exporter must explicitly load the configuration as
data; PHP does not load `.env` automatically. It must not shell-source the file
or print the password. Only public output belongs in this GitHub repository.
These proposed paths and keys are not supported by the current importer.

## Work needed before implementation

1. An administrator confirms the database, table prefix, storage engines, and
   the fields used by the six public boards listed in the repository README.
   Check board and record visibility, secret-post flags, comments, and ordering.
   Reading a raw table must not turn private records into public output.
2. Define six export views with explicit public columns and row filters that
   match anonymous website access. Include stable record IDs. Use a restricted
   view definer and review the view definitions with the site maintainer.
3. Create `csdl_static_export` for local socket access and grant `SELECT` only
   on those views. Give the exporter no base-table, account-table, write,
   schema-change, file, or grant privileges. Keep MariaDB off the public network.
   MariaDB supports [object and column SELECT grants](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant).
4. Provision the dedicated secret and configuration outside Git. Confirm the
   runner can read those files and connect without `sudo`. Review its effective
   grants without publishing authentication details.
5. Implement an explicit DB export command in this repository that produces the
   existing public snapshot schema. Use fixed SELECT queries and a short
   read-only transaction. A consistent snapshot is available for compatible
   engines such as InnoDB; do not assume consistency across nontransactional
   tables. See [MariaDB transaction modes](https://mariadb.com/docs/server/reference/sql-statements/transactions/start-transaction).
6. Compare all six exported lists with anonymous public pages: record counts,
   IDs, member roles, category/year order, authors, status, and patent details.
   Test visibility filters, malformed records, and write-denial behavior on
   test fixtures or a disposable database, not production records.
7. Reuse the renderer and checks. Leave the previous snapshot unchanged if
   export or validation fails. Review the generated diff before committing and
   pushing; neither account setup nor a secret file should trigger publication.

Account/view creation and secret provisioning are separate administrator tasks.
They are unnecessary for today's public-page sync. Adopt this route only if it
solves an operational need that public-page import cannot meet.
