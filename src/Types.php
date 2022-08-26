<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.26.00

namespace ProtocolLive\PhpLiveDb;

enum Types:int{
  case Bool = 5;
  case Int = 1;
  case Null = 0;
  case Sql = 6;
  case Str = 2;
}