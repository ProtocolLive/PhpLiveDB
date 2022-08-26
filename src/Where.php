<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.26.00

namespace ProtocolLive\PhpLiveDb;

final class Where{
  public function __construct(
    public string $Field,
    public string|null $Value = null,
    public Types|null $Type = null,
    public Operators $Operator = Operators::Equal,
    public AndOr $AndOr = AndOr::And,
    public Parenthesis $Parenthesis = Parenthesis::None,
    public string|null $CustomPlaceholder = null,
    public bool $BlankIsNull = true,
    public bool $NoField = false,
    public bool $NoBind = false
  ){}
}