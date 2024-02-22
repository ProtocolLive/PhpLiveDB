<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2024.02.22.00
 */
enum Drivers:string{
  case MySql = 'mysql';
  case SqLite = 'sqlite';
}