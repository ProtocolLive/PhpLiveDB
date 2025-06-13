<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2025.06.13.00
 */
enum Operators:string{
  case Between = ' between';
  case BetweenNot = ' not between';
  case Bigger = '>';
  case BiggerEqual = '>=';
  case Different = '<>';
  case Equal = '=';
  case Exists = 'exists';
  case ExistsNot = 'not exists';
  case In = ' in';
  case InNot = ' not in';
  case IsNotNull = ' is not null';
  case Like = ' like ';
  case LikeNot = ' not like ';
  case Match = 'match';
  case MatchBoolean = 'match2';
  case MatchExpansion = 'match3';
  case Smaller = '<';
  case SmallerEqual = '<=';
}