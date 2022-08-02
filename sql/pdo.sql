create table sys_logs(
  log_id int unsigned not null auto_increment primary key,
  log tinyint unsigned not null,
  user int unsigned,
  agent varchar(255) not null,
  ip varchar(20) not null,
  query text not null
);