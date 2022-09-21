<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2022.09.21.00

namespace ProtocolLive\PhpLiveDb;

final class Field{
  public function __construct(
    public string $Name,
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