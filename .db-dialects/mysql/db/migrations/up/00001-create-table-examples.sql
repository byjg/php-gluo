-- Example schema: Project (1) --> Task (N) --> Note (N)
-- Demonstrates the three supported patterns. Remove with install_examples=false.

-- Repository pattern, integer auto-increment primary key
create table project (
    id INTEGER PRIMARY KEY AUTO_INCREMENT NOT NULL,
    name varchar(100) not null,
    description varchar(255) null
) ENGINE=InnoDB;

create index ix_project_name on project(name);

insert into project (name, description) values ('Sample Project', 'A demo project');
insert into project (name, description) values ('Website Redesign', 'Q3 marketing site');

-- Repository pattern, UUID (binary) primary key. The `id` field round-trips as a
-- formatted UUID string through the FieldUuidAttribute select/update mappers.
create table task (
    id binary(16) DEFAULT (uuid_to_bin(uuid())) PRIMARY KEY NOT NULL,
    project_id INTEGER not null,
    title varchar(150) not null,
    status varchar(20) not null default 'open',
    constraint fk_task_project foreign key (project_id) references project(id)
) ENGINE=InnoDB;

-- Give the first task a fixed UUID so the seed note below can reference it.
insert into task (id, project_id, title, status)
    values (uuid_to_bin('11111111-2222-3333-4444-555555555555'), 1, 'Set up repository', 'done');
insert into task (project_id, title, status) values (1, 'Write first endpoint', 'open');

-- ActiveRecord pattern, with a real binary(16) foreign key to task, soft-delete
-- (deleted_at via the OaDeletedAt trait), and timestamps.
--
-- `body_length` is a real MySQL VIRTUAL GENERATED column: the DB computes it from
-- `body` on every read and it is never written by the app. It must be deterministic
-- (char_length), which is why "days since created" cannot be a generated column
-- (NOW() is non-deterministic and rejected here) and is computed in the model instead.
create table note (
    id int auto_increment not null,
    task_id binary(16) not null,
    body varchar(500) not null,
    body_length int generated always as (char_length(body)) virtual,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    constraint pk_note primary key (id),
    constraint fk_note_task foreign key (task_id) references task(id)
) ENGINE=InnoDB;

create index ix_note_task on note(task_id);

-- Seed note referencing the fixed-UUID task above.
insert into note (task_id, body)
    values (uuid_to_bin('11111111-2222-3333-4444-555555555555'), 'Kickoff note for the first task');
