-- PostgreSQL version of db/migrations/up/00001-create-table-examples.sql.
-- Example schema: Project (1) --> Task (N) --> Note (N)
-- Demonstrates the three supported patterns. Remove with install_examples=false.

-- Repository pattern, integer auto-increment primary key
create table project (
    id serial PRIMARY KEY NOT NULL,
    name varchar(100) not null,
    description varchar(255) null
);

create index ix_project_name on project(name);

insert into project (name, description) values ('Sample Project', 'A demo project');
insert into project (name, description) values ('Website Redesign', 'Q3 marketing site');

-- Repository pattern, UUID primary key (a native uuid). The `id` field round-trips as a
-- formatted UUID string through the FieldUuidAttribute select/update mappers.
create table task (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY NOT NULL,
    project_id INTEGER not null,
    title varchar(150) not null,
    status varchar(20) not null default 'open',
    constraint fk_task_project foreign key (project_id) references project(id)
);

-- Give the first task a fixed UUID so the seed note below can reference it.
insert into task (id, project_id, title, status)
    values ('11111111-2222-3333-4444-555555555555', 1, 'Set up repository', 'done');
insert into task (project_id, title, status) values (1, 'Write first endpoint', 'open');

-- ActiveRecord pattern, with a uuid foreign key to task, soft-delete (deleted_at via the
-- OaDeletedAt trait), and timestamps.
--
-- `body_length` is a generated column: the database computes it from `body` and it is never
-- written by the app. PostgreSQL only has STORED generated columns (computed on write).
create table note (
    id serial not null,
    task_id uuid not null,
    body varchar(500) not null,
    body_length int generated always as (char_length(body)) stored,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP,
    deleted_at timestamp NULL,
    constraint pk_note primary key (id),
    constraint fk_note_task foreign key (task_id) references task(id)
);

create index ix_note_task on note(task_id);

-- updated_at is kept by a trigger, see set_updated_at() in base.sql
create trigger tr_note_updated_at before update on note for each row execute function set_updated_at();

-- Seed note referencing the fixed-UUID task above.
insert into note (task_id, body)
    values ('11111111-2222-3333-4444-555555555555', 'Kickoff note for the first task');
