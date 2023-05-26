<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;

/**
 * @version 2022.08.07.04
 */
enum Drivers:string{
  case MySql = 'mysql';
  case SqLite = 'sqlite';
}