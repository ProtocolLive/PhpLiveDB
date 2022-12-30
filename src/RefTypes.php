<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.12.30.00

namespace ProtocolLive\PhpLiveDb;

enum RefTypes:string{
  case Cascade = 'cascade';
  case None = 'no action';
  case Null = 'set null';
  case Restrict = 'restrict';
}