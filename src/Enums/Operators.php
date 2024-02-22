<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2024.02.22.00
 */
enum Operators:string{
  case Bigger = '>';
  case BiggerEqual = '>=';
  case Different = '<>';
  case Equal = '=';
  case Exists = 'exists';
  case In = ' in';
  case IsNotNull = ' not null';
  case Like = ' like ';
  case NotIn = ' not in';
  case Smaller = '<';
  case SmallerEqual = '<=';
}