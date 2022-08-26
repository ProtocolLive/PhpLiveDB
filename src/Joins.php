<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.26.00

namespace ProtocolLive\PhpLiveDb;

enum Joins{
  case Default;
  case Left;
  case Right;
  case Inner;
}