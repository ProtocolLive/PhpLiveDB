<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.09.01.00

namespace ProtocolLive\PhpLiveDb;

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