create table sys_logs(
  log_id int unsigned not null auto_increment primary key,
  time datetime not null,
  site varchar(45),
  user_id int unsigned not null,
  log int unsigned not null,
  target int unsigned,
  ip varchar(100) not null,
  ipreverse varchar(255) not null,
  agent varchar(255),
  query varchar(1024) not null
);