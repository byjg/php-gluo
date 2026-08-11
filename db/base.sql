-- This table are using by the component byjg/authuser

create table users
(
    userid binary(16) DEFAULT (uuid_to_bin(uuid())) NOT NULL,
    name varchar(50),
    email varchar(120),
    username varchar(20) not null,
    password char(40) not null,
    role varchar(50),
    created_at DATETIME DEFAULT (now()),
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME,

    constraint pk_users primary key (userid),
    constraint ix_username unique (username),
    constraint ix_xmlnuke_users_2 unique (email)
) ENGINE=InnoDB;


-- Index
ALTER TABLE `users`
    ADD INDEX `ix_users_email` (`email` ASC, `password` ASC),
    ADD INDEX `ix_users_username` (`username` ASC, `password` ASC);

-- Default Password is "pwd"
-- Please change it!
insert into users (name, email, username, password, role) VALUES
    ('Administrator', 'admin@example.com', 'admin', '9800aa1b77334ff0952b203062f0fbb0c480d3de', 'admin');   -- !P4ssw0rdstr!

insert into users (userid, name, email, username, password, role) VALUES
    (0x5f6e7fe7bd1b11ed8ca90242ac120002, 'Regular User', 'user@example.com', 'user', '9800aa1b77334ff0952b203062f0fbb0c480d3de', 'user')        -- !P4ssw0rdstr!
;

-- random binary(16) generator
-- select hex(uuid_to_bin(uuid()));


-- This table are using by the component byjg/authuser
create table users_property
(
    id integer AUTO_INCREMENT not null,
    name varchar(50),
    value varchar(250),
    userid binary(16) NOT NULL,

    constraint pk_custom primary key (id),
    constraint fk_custom_user foreign key (userid) references users (userid)
) ENGINE=InnoDB;

insert into users_property (name, value, userid) values
    ('picture', 'https://www.gravatar.com/avatar/9f4d313491a7df705b7071c228fc79cd', 0x5f6e7fe7bd1b11ed8ca90242ac120002);
