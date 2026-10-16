# SQL of the other databases

`db/base.sql` and `db/migrations/` are the MySQL SQL of the template. Each other database the installer offers has
its own copy of every one of those files here, with the same names:

- `postgres/`: PostgreSQL (`uuid`, `serial`, `STORED` generated columns, a trigger for `updated_at`)
- `sqlite/`: SQLite (`blob`, `INTEGER PRIMARY KEY AUTOINCREMENT`, a trigger for `updated_at`)

When a project is created, the installer puts the files of the database chosen in `db/` and removes this folder, so
a project has one `db/base.sql` and one `db/migrations/`.

A migration added to `db/migrations/` must be added here too, for every database.
`tests/Builder/PostCreateScriptTest.php` fails when the file names differ, and the create-project CI job runs every
database.

The migration splits a file into statements at a `;` followed by a line break: keep each trigger or function on one
line, and comments on their own line.
