<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2021.12.20.00

class PhpLivePdoBasics{
  public const TypeNull = PDO::PARAM_NULL;
  public const TypeInt = PDO::PARAM_INT;
  public const TypeStr = PDO::PARAM_STR;
  public const TypeSql = 6;

  public const CmdSelect = 0;
  public const CmdInsert = 1;
  public const CmdUpdate = 2;
  public const CmdDelete = 3;

  public const OperatorEqual = 0;
  public const OperatorSmaller = 1;
  public const OperatorBigger = 2;
  public const OperatorSmallerEqual = 3;
  public const OperatorBiggerEqual = 4;
  public const OperatorIsNotNull = 5;
  public const OperatorLike = 6;
  public const OperatorIn = 7;
  public const OperatorNotIn = 8;
}

class PhpLivePdoJoin{
  public const TypeDefault = 0;
  public const TypeLeft = 1;
  public const TypeRight = 2;
  public const TypeInner = 3;
  
  public string $Table;
  public int $Type;
  public ?string $Using;
  public ?string $On;

  public function __construct(
    string $Table,
    int $Type,
    string $Using = null,
    string $On = null
  ){
    $this->Table = $Table;
    $this->Type = $Type;
    $this->Using = $Using;
    $this->On = $On;
  }
}

class PhpLivePdoField{
  public string $Name;
  public ?string $Value;
  public int $Type;

  public function __construct(
    string $Name,
    string $Value,
    int $Type
  ){
    $this->Name = $Name;
    $this->Value = $Value;
    $this->Type = $Type;
  }
}

class PhpLivePdoWhere{
  public string $Field;
  public string $Value;
  public int $Type;
  public int $Operator;
  public int $AndOr;
  public ?int $Parentesis;
  public ?string $CustomPlaceholder;

  public function __construct(
    string $Field,
    string $Value,
    int $Type,
    int $Operator = PhpLivePdoBasics::OperatorEqual,
    int $AndOr = 0,
    int $Parentesis = null,
    string $CustomPlaceholder = null
  ){
    $this->Field = $Field;
    $this->Value = $Value;
    $this->Type = $Type;
    $this->Operator = $Operator;
    $this->AndOr = $AndOr;
    $this->Parentesis = $Parentesis;
    $this->CustomPlaceholder = $CustomPlaceholder;
  }
}