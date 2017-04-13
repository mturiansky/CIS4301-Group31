CREATE TABLE account
(id integer not null,
 email_address varchar2(255) unique,
 password varchar2(255) not null,
 name varchar2(255) not null,
 SSN char(11),
 DOB date not null,
 date_joined date not null,
 status integer not null,
 address_street varchar2(255),
 address_city varchar2(255),
 address_state varchar2(2),
 type integer not null,
 phone_number integer,
 gender varchar2(20),
 PRIMARY KEY (id)
);


CREATE TABLE is_friends_with
(friend1 integer not null,
 friend2 integer not null,
 PRIMARY KEY (friend1, friend2),
 FOREIGN KEY (friend1) references Account (id),
 FOREIGN KEY (friend2) references Account (id)
);

CREATE TABLE Reply
(ID integer not null,
 timestamp timestamp not null,
 text varchar2(255),
 PRIMARY KEY (ID)
);


CREATE TABLE Social_Media_Post
(ID integer not null,
 timestamp timestamp not null,
 text varchar2(200),
 PRIMARY KEY (ID)
);

CREATE TABLE Likes
(who integer not null,
 smid integer not null,
 PRIMARY KEY (who, smid),
 FOREIGN KEY (who) references Account (id),
 FOREIGN KEY (smid) references Social_Media_Post (ID)
);

CREATE TABLE Transaction
(ID integer not null,
 timestamp timestamp not null,
 memo varchar2(255),
 type varchar2(20),
 value integer,
 PRIMARY KEY (ID)
);

CREATE TABLE Posts
(who integer not null,
 message integer not null,
 sm_post integer not null,
 PRIMARY KEY (who, message, sm_post),
 FOREIGN KEY (who) references Account (id),
 FOREIGN KEY (message) references Reply (ID),
 FOREIGN KEY (sm_post) references Social_Media_Post(ID)
);

CREATE TABLE Makes
(fromacc integer not null,
 toacc integer not null,
 tid integer not null,
 smid integer,
 PRIMARY KEY (tid),
 FOREIGN KEY (fromacc) references Account (id),
 FOREIGN KEY (toacc) references Account (id),
 FOREIGN KEY (tid) references Transaction (ID),
 FOREIGN KEY (smid) references Social_Media_Post (ID)
);

create sequence seq_transaction minvalue 1 start with 1 increment by 1 cache 10;
create sequence seq_sm minvalue 1 start with 1 increment by 1 cache 10;
create sequence seq_account minvalue 1337 start with 1337 increment by 13 cache 10;
create sequence seq_reply minvalue 1 start with 1 increment by 1 cache 10;

-- drop table Makes;
-- drop table Posts;
-- drop table Transaction;
-- drop table Likes;
-- drop table Social_Media_Post;
-- drop table Reply;
-- drop table is_friends_with;
-- drop table Account;
-- drop sequence seq_transaction;
-- drop sequence seq_sm;
-- drop sequence seq_account;
-- drop sequence seq_reply;

