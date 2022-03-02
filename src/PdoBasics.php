<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2022.03.02.00
//For PHP >= 8.1

enum PhpLivePdoTypes:int{
  case Null = 0;
  case Int = 1;
  case Str = 2;
  case Sql = 6;
}

enum PhpLivePdoOperators{
  case Equal;
  case Different;
  case Smaller;
  case Bigger;
  case SmallerEqual;
  case BiggerEqual;
  case IsNotNull;
  case Like;
  case In;
  case NotIn;
}

enum PhpLivePdoJoins{
  case Default;
  case Left;
  case Right;
  case Inner;
}

enum PhpLivePdoParenthesis{
  case None;
  case Open;
  case Close;
}

enum PhpLivePdoAndOr{
  case And;
  case Or;
}

abstract class PhpLivePdoBasics{
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

  protected function LogSet(
    PDO $Conn,
    int $LogEvent,
    int|null $User,
    string $Query
  ):void{
    $Query = substr($Query, strpos($Query, 'Sent SQL: ['));
    $Query = substr($Query, strpos($Query, '] ') + 2);
    $Query = substr($Query, 0, strpos($Query, 'Params: '));
    $Query = trim($Query);

    $statement = $Conn->prepare('
      insert into sys_logs(time,log,user_id,agent,ip,query)
      values(:time,:log,:user,:agent,:ip,:query)
    ');
    $statement->bindValue('time', time(), PDO::PARAM_INT);
    $statement->bindValue('log', $LogEvent, PDO::PARAM_INT);
    $statement->bindValue('user', $User, PDO::PARAM_INT);
    $statement->bindValue('agent', $_SERVER['HTTP_USER_AGENT'], PDO::PARAM_STR);
    $statement->bindValue('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $statement->bindValue('query', $Query, PDO::PARAM_STR);
    $statement->execute();
  }

  protected function Operator(PhpLivePdoOperators $Operator):string{
    if($Operator === PhpLivePdoOperators::Equal):
      return '=';
    elseif($Operator === PhpLivePdoOperators::Different):
      return '<>';
    elseif($Operator === PhpLivePdoOperators::Smaller):
      return '<';
    elseif($Operator === PhpLivePdoOperators::Bigger):
      return '>';
    elseif($Operator === PhpLivePdoOperators::SmallerEqual):
      return '<=';
    elseif($Operator === PhpLivePdoOperators::BiggerEqual):
      return '>=';
    elseif($Operator === PhpLivePdoOperators::Like):
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
          if($where->AndOr === PhpLivePdoAndOr::And):
            $this->Query .= ' and ';
          endif;
          if($where->AndOr === PhpLivePdoAndOr::Or):
            $this->Query .= ' or ';
          endif;
        endif;
        if($where->Parenthesis === PhpLivePdoParenthesis::Open):
          $this->Query .= '(';
        endif;
        if($where->Operator === PhpLivePdoOperators::IsNotNull):
          $this->Query .= $where->Field . ' is not null';
        elseif($where->Operator === PhpLivePdoOperators::In):
          $this->Query .= $where->Field . ' in(' . $where->Value . ')';
        elseif($where->Operator === PhpLivePdoOperators::NotIn):
          $this->Query .= $where->Field . ' not in(' . $where->Value . ')';
        elseif($where->Value === null or $where->Type === PhpLivePdoTypes::Null):
          $this->Query .= $where->Field . ' is null';
        else:
          $this->Query .= $where->Field . $this->Operator($where->Operator);
          if($where->Type === PhpLivePdoTypes::Sql):
            $this->Query .= $where->Value;
          else:
            $this->Query .= ':' . ($where->CustomPlaceholder ?? $where->Field);
          endif;
        endif;
        if($where->Parenthesis === PhpLivePdoParenthesis::Close):
          $this->Query .= ')';
        endif;
      endforeach;
    endif;
  }

  protected function FieldNeedCustomPlaceholder(string $Field):void{
    if(strpos($Field, '.') !== false
    or strpos($Field, '(') !== false):
      $this->ErrorSet(new PDOException(
        'The field ' . $Field . ' need a custom placeholder',
      ));
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

class PhpLivePdoWhere{
  public function __construct(
    public string $Field,
    public string|null $Value = null,
    public PhpLivePdoTypes|null $Type = null,
    public PhpLivePdoOperators $Operator = PhpLivePdoOperators::Equal,
    public PhpLivePdoAndOr $AndOr = PhpLivePdoAndOr::And,
    public PhpLivePdoParenthesis $Parenthesis = PhpLivePdoParenthesis::None,
    public string|null $CustomPlaceholder = null,
    public bool $BlankIsNull = true,
    public bool $NoField = false,
    public bool $NoBind = false
  ){}
}