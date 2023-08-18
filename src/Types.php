<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use PDO;

/**
 * @version 2023.08.17.00
 */
enum Types:int{
  case Bool = PDO::PARAM_BOOL;
  case Int = PDO::PARAM_INT;
  case Null = PDO::PARAM_NULL;
  case Sql = 6;
  case Str = PDO::PARAM_STR;
}