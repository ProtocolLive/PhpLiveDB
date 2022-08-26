<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.07.04
//For PHP >= 8.1

namespace ProtocolLive\PhpLiveDb;

enum Drivers:string{
  case MySql = 'mysql';
  case SqLite = 'sqlite';
}