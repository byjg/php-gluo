-- SQLite version of db/migrations/up/00001-create-table-examples.sql.
-- Example schema: Project (1) --> Task (N) --> Note (N)
-- Demonstrates the three supported patterns. Remove with install_examples=false.

-- Repository pattern, integer auto-increment primary key
create table project (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name varchar(100) not null,
    description varchar(255) null
);

create index ix_project_name on project(name);

insert into project (name, description) values ('Sample Project', 'A demo project');
insert into project (name, description) values ('Website Redesign', 'Q3 marketing site');

-- Repository pattern, UUID (binary) primary key. The `id` field round-trips as a
-- formatted UUID string through the FieldUuidAttribute select/update mappers.
create table task (
    id blob DEFAULT (randomblob(16)) PRIMARY KEY NOT NULL,
    project_id INTEGER not null,
    title varchar(150) not null,
    status varchar(20) not null default 'open',
    constraint fk_task_project foreign key (project_id) references project(id)
);

-- Give the first task a fixed UUID so the seed note below can reference it.
insert into task (id, project_id, title, status)
    values (X'11111111222233334444555555555555', 1, 'Set up repository', 'done');
insert into task (project_id, title, status) values (1, 'Write first endpoint', 'open');

-- ActiveRecord pattern, with a binary foreign key to task, soft-delete (deleted_at via the
-- OaDeletedAt trait), and timestamps.
--
-- `body_length` is a VIRTUAL generated column: the database computes it from `body` on every
-- read and it is never written by the app.
create table note (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id blob not null,
    body varchar(500) not null,
    body_length int generated always as (length(body)) virtual,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    deleted_at datetime NULL,
    constraint fk_note_task foreign key (task_id) references task(id)
);

create index ix_note_task on note(task_id);

-- updated_at is kept by a trigger, as in base.sql
create trigger tr_note_updated_at after update on note for each row begin update note set updated_at = CURRENT_TIMESTAMP where id = new.id; end;

-- Seed note referencing the fixed-UUID task above.
insert into note (task_id, body)
    values (X'11111111222233334444555555555555', 'Kickoff note for the first task');
