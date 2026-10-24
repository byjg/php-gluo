# Database files of the template

Everything that depends on the database lives here, one folder per database the installer offers:

```
.db-dialects/
├── docker-compose.yml     what every database shares: the API, the frontend, the network
├── mysql/
│   ├── docker-compose.yml the database server (the `gluo-database` service) and its volume
│   └── db/                base.sql and migrations/
├── postgres/              same files: PostgreSQL (`uuid`, `serial`, `STORED` generated columns, a trigger for `updated_at`)
└── sqlite/                same files: SQLite (`blob`, `INTEGER PRIMARY KEY AUTOINCREMENT`, a trigger for `updated_at`);
                           its docker-compose.yml has no server, the databases are files in db/
```

`php gluo --env=dev db:dialect <mysql|postgres|sqlite>` writes the files of one database to the repository root:
`docker-compose.yml`, `docker-compose.database.yml` (the one `docker-compose.yml` includes), `db/base.sql` and
`db/migrations/`. In this repository they are not versioned: change them here, then run the command again.

When a project is created, the installer writes the files of the database chosen the same way and removes this
folder and the command: the project keeps one `docker-compose.yml`, one `docker-compose.database.yml`, one
`db/base.sql` and one `db/migrations/`, and versions them.

A migration added for one database must be added for every database, with the same file name.
`tests/Builder/PostCreateScriptTest.php` fails when the file names differ, and the create-project CI job runs every
database.

The migration splits a file into statements at a `;` followed by a line break: keep each trigger or function on one
line, and comments on their own line.
