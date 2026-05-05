<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2026.05.04.00
 */
enum FieldsGetReturn{
  case Array;
  /**
   * Don't return, just add the fields in current object
   */
  case None;
  case Sql;
  case String;
}