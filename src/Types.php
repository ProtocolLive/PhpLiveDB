<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use PDO;

/**
 * @version 2023.08.17.01
 */
enum Types:int{
  case Bool = PDO::PARAM_BOOL;
  case Date = PDO::PARAM_STR;
  case Float = PDO::PARAM_STR;
  case Int = PDO::PARAM_INT;
  case Null = PDO::PARAM_NULL;
  case Sql = 6;
  case Str = PDO::PARAM_STR;
}