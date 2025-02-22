<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2025.02.21.01
 */
enum Operators:string{
  case Bigger = '>';
  case BiggerEqual = '>=';
  case Different = '<>';
  case Equal = '=';
  case Exists = 'exists';
  case ExistsNot = 'not exists';
  case In = ' in';
  case InNot = ' not in';
  case IsNotNull = ' not null';
  case Like = ' like ';
  case Smaller = '<';
  case SmallerEqual = '<=';
}