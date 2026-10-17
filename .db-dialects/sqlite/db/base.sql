-- SQLite version of db/base.sql: the installer puts it in place when the project is created
-- with db_schema "sqlite". Keep it in step with the MySQL one.

-- This table is used by the component byjg/authuser
create table users
(
    userid blob DEFAULT (randomblob(16)) NOT NULL,
    name varchar(50),
    email varchar(120),
    username varchar(20) not null,
    password char(40) not null,
    role varchar(50),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime,
    deleted_at datetime,

    constraint pk_users primary key (userid),
    constraint ix_username unique (username),
    constraint ix_xmlnuke_users_2 unique (email)
);

create index ix_users_email on users (email, password);
create index ix_users_username on users (username, password);

-- updated_at is not written by the application (OaUpdatedAt maps it read-only): MySQL keeps it
-- with ON UPDATE CURRENT_TIMESTAMP, SQLite with this trigger. One line per statement: the
-- migration splits the file at ";" followed by a line break.
create trigger tr_users_updated_at after update on users for each row begin update users set updated_at = CURRENT_TIMESTAMP where userid = new.userid; end;

-- The password of both users below is "!P4ssw0rdstr!". Please change it!
-- (Comments go on their own line: one after the ";" would keep the migration from splitting
-- the statements there.)
insert into users (name, email, username, password, role) VALUES
    ('Administrator', 'admin@example.com', 'admin', '9800aa1b77334ff0952b203062f0fbb0c480d3de', 'admin');

insert into users (userid, name, email, username, password, role) VALUES
    (X'5f6e7fe7bd1b11ed8ca90242ac120002', 'Regular User', 'user@example.com', 'user', '9800aa1b77334ff0952b203062f0fbb0c480d3de', 'user')
;

-- This table is used by the component byjg/authuser
create table users_property
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name varchar(50),
    value varchar(250),
    userid blob NOT NULL,

    constraint fk_custom_user foreign key (userid) references users (userid)
);

insert into users_property (name, value, userid) values
    ('picture', 'https://www.gravatar.com/avatar/9f4d313491a7df705b7071c228fc79cd', X'5f6e7fe7bd1b11ed8ca90242ac120002');
