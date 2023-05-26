<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;

/**
 * @version 2022.08.26.00
 */
enum Joins{
  case Default;
  case Left;
  case Right;
  case Inner;
}