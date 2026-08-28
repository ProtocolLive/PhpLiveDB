<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use PDOException;
use Throwable;

/**
 * @version 2026.08.15.00
 */
final class PhpLiveDbException
extends PDOException{
  public function __construct(
    private string $query,
    private Throwable|null $previous = null
  ){
    $this->code = $previous->getCode();
    $this->message = $previous->getMessage();
  }

  public function getQuery():string{
    return $this->query;
  }
}