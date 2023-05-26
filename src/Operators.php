<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;

/**
 * @version 2022.09.01.00
 */
enum Operators:string{
  case Bigger = '>';
  case BiggerEqual = '>=';
  case Different = '<>';
  case Equal = '=';
  case In = ' in';
  case IsNotNull = ' not null';
  case Like = ' like ';
  case NotIn = ' not in';
  case Smaller = '<';
  case SmallerEqual = '<=';
}