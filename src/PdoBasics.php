<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2022.02.06.00

abstract class PhpLivePdoBasics{
  public const TypeNull = PDO::PARAM_NULL;
  public const TypeInt = PDO::PARAM_INT;
  public const TypeStr = PDO::PARAM_STR;
  public const TypeSql = 6;

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

  private string $Table;
  private string $Prefix;

  protected string|null $Query = null;
  
  public PDOException|null $Error = null;

  protected function ValueFunctions(
    string $Value,
    bool $HtmlSafe,
    bool $TrimValues
  ):string{
    if($HtmlSafe):
      $Value = htmlspecialchars($Value);
    endif;
    if($TrimValues):
      $Value = trim($Value);
    endif;
    return $Value;
  }

  protected function LogSet(PDO $Conn, int|null $User, string $Query):void{
    $Query = substr($Query, strpos($Query, 'Sent SQL: ['));
    $Query = substr($Query, strpos($Query, '] ') + 2);
    $Query = substr($Query, 0, strpos($Query, 'Params: '));
    $Query = trim($Query);

    $statement = $Conn->prepare('
      insert into sys_logs(dia,user_id,uagent,ip,query)
      values(:dia,:user,:uagent,:ip,:query)
    ');
    $statement->bindValue('dia', time(), PDO::PARAM_INT);
    $statement->bindValue('user', $User, PDO::PARAM_INT);
    $statement->bindValue('uagent', $_SERVER['HTTP_USER_AGENT'], PDO::PARAM_STR);
    $statement->bindValue('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $statement->bindValue('query', $Query, PDO::PARAM_STR);
    $statement->execute();
  }

  protected function Operator(int $Operator):string{
    if($Operator === self::OperatorEqual):
      return '=';
    elseif($Operator === self::OperatorDifferent):
      return '<>';
    elseif($Operator === self::OperatorSmaller):
      return '<';
    elseif($Operator === self::OperatorBigger):
      return '>';
    elseif($Operator === self::OperatorSmallerEqual):
      return '<=';
    elseif($Operator === self::OperatorBiggerEqual):
      return '>=';
    elseif($Operator === self::OperatorLike):
      return ' like ';
    endif;
  }

  protected function Wheres(array $Wheres):void{
    $WheresTemp = $Wheres;
    foreach($WheresTemp as $id => $where):
      if($where->NoField === true):
        unset($WheresTemp[$id]);
      endif;
    endforeach;
    $Wheres = array_values($WheresTemp);
    if(count($Wheres) > 0):
      $this->Query .= ' where ';
      foreach($Wheres as $id => $where):
        if($id > 0):
          if($where->AndOr === 0):
            $this->Query .= ' and ';
          endif;
          if($where->AndOr === 1):
            $this->Query .= ' or ';
          endif;
        endif;
        if($where->Parenthesis === 0):
          $this->Query .= '(';
        endif;
        if($where->Operator === self::OperatorIsNotNull):
          $this->Query .= $where->Field . ' is not null';
        elseif($where->Operator === self::OperatorIn):
          $this->Query .= $where->Field . ' in(' . $where->Value . ')';
        elseif($where->Operator === self::OperatorNotIn):
          $this->Query .= $where->Field . ' not in(' . $where->Value . ')';
        elseif($where->Value === null or $where->Type === self::TypeNull):
          $this->Query .= $where->Field . ' is null';
        else:
          $this->Query .= $where->Field . $this->Operator($where->Operator) . ':' . ($where->CustomPlaceholder ?? $where->Field);
        endif;
        if($where->Parenthesis === 1):
          $this->Query .= ')';
        endif;
      endforeach;
    endif;
  }

  public function ErrorSet(PDOException $Obj):void{
    $this->Error = $Obj;
    $log = date('Y-m-d H:i:s') . "\n";
    $log .= $Obj->getCode() . ' - ' . $Obj->getMessage() . "\n";
    $log .= 'Query: ' . $this->Query . "\n";
    $log .= $Obj->getTraceAsString();
    error_log($log);
    if(ini_get('display_errors')):
      if(ini_get('html_errors')):
        echo '<pre>' . str_replace("\n", '<br>', $log) . '</pre>';
      else:
        echo $log;
      endif;
    endif;
  }

  public static function Reserved(string $Field):string{
    $names = ['order', 'default', 'group'];
    if(in_array($Field, $names)):
      $Field = '`' . $Field . '`';
    endif;
    return $Field;
  }
}