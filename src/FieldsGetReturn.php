<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;

/**
 * @version 2023.09.01.00
 */
enum FieldsGetReturn{
  case Array;
  case Sql;
  case String;
}