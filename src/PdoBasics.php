<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2022.02.06.00

abstract class PhpLivePdoBasics{
  public const TypeNull = PDO::PARAM_NULL;
  public const TypeInt = PDO::PARAM_INT;
  public const TypeStr = PDO::PARAM_STR;
  public const TypeSql = 6;

  public const CmdSelect = 0;
  public const CmdInsert = 1;
  public const CmdUpdate = 2;
  public const CmdDelete = 3;

  public const OperatorEqual = 0;
  public const OperatorDifferent = 1;
  public const OperatorSmaller = 2;
  public const OperatorBigger = 3;
  public const OperatorSmallerEqual = 4;
  public const OperatorBiggerEqual = 5;
  public const OperatorIsNotNull = 6;
  public const OperatorLike = 7;
  public const OperatorIn = 8;
  public const OperatorNotIn = 9;

  public const JoinDefault = 0;
  public const JoinLeft = 1;
  public const JoinRight = 2;
  public const JoinInner = 3;
  private string $Prefix;
}