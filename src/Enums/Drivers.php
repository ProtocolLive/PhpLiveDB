<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2024.04.04.00
 */
enum Drivers:string{
  case Firebird = 'firebird';
  case MySql = 'mysql';
  case SqLite = 'sqlite';
}