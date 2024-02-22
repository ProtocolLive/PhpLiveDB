<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use ProtocolLive\PhpLiveDb\Enums\{
  AndOr,
  Operators,
  Parenthesis,
  Types
};

/**
 * @version 2024.02.22.00
 */
final class Field{
  public function __construct(
    public string|null $Name = null,
    public string|null $Value = null,
    public Types|null $Type = null,
    public Operators $Operator = Operators::Equal,
    public AndOr $AndOr = AndOr::And,
    public Parenthesis $Parenthesis = Parenthesis::None,
    public string|null $CustomPlaceholder = null,
    public bool $BlankIsNull = true,
    public bool $NoField = false,
    public bool $NoBind = false,
    public bool $InsertUpdate = false
  ){}
}