<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;

/**
 * @version 2022.12.30.00
 */
enum RefTypes:string{
  case Cascade = 'cascade';
  case None = 'no action';
  case Null = 'set null';
  case Restrict = 'restrict';
}