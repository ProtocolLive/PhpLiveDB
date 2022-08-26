<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.26.00

namespace ProtocolLive\PhpLiveDb;

enum Operators:string{
  case Equal = '=';
  case Different = '<>';
  case Smaller = '<';
  case Bigger = '>';
  case SmallerEqual = '<=';
  case BiggerEqual = '>=';
  case IsNotNull = ' not null';
  case Like = ' like ';
  case In = ' in';
  case NotIn = ' not in';
}