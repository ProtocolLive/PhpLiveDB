<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb\Enums;

/**
 * @version 2024.02.22.00
 */
enum RefTypes:string{
  case Cascade = 'cascade';
  case None = 'no action';
  case Null = 'set null';
  case Restrict = 'restrict';
}