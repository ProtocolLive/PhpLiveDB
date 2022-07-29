<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.07.29.01
//For PHP >= 8.1

enum PhpLiveDbTypes:int{
  case Null = 0;
  case Int = 1;
  case Str = 2;
  case Sql = 6;
}

enum PhpLiveDbOperators:string{
  case Equal = '=';
  case Different = '<>';
  case Smaller = '<';
  case Bigger = '>';
  case SmallerEqual = '<=';
  case BiggerEqual = '>=';
  case IsNotNull = ' not null';
  case Like = ' like ';
  case In = ' in';
  case NotIn = ' not in';
}

enum PhpLiveDbJoins{
  case Default;
  case Left;
  case Right;
  case Inner;
}

enum PhpLiveDbParenthesis{
  case None;
  case Open;
  case Close;
}

enum PhpLiveDbAndOr{
  case And;
  case Or;
}

abstract class PhpLiveDbBasics{
  private string $Table;
  private string $Prefix;
  protected array $Binds = [];

  protected string|null $Query = null;
  
  public string|null $Error = null;

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

  protected function BuildWhere(array $Wheres):void{
    //Wipe the NoField to not create problem with index 0
    $WheresTemp = $Wheres;
    foreach($WheresTemp as $id => $where):
      if($where->NoField):
        unset($WheresTemp[$id]);
      endif;
    endforeach;
    $Wheres = array_values($WheresTemp);

    if(count($Wheres) > 0):
      $this->Query .= ' where ';
      $i = 0;
      /**
       * @var PhpLiveDbWhere $where
       */
      foreach($Wheres as $where):
        if($i > 0):
          if($where->AndOr === PhpLiveDbAndOr::And):
            $this->Query .= ' and ';
          elseif($where->AndOr === PhpLiveDbAndOr::Or):
            $this->Query .= ' or ';
          endif;
        endif;
        $i++;
        if($where->Parenthesis === PhpLiveDbParenthesis::Open):
          $this->Query .= '(';
        endif;
        if($where->Operator === PhpLiveDbOperators::IsNotNull):
          $this->Query .= $where->Field . ' is not null';
        elseif($where->Operator === PhpLiveDbOperators::In):
          $this->Query .= $where->Field . ' in(' . $where->Value . ')';
        elseif($where->Operator === PhpLiveDbOperators::NotIn):
          $this->Query .= $where->Field . ' not in(' . $where->Value . ')';
        elseif($where->NoBind === false
        and(
          $where->Value === null
          or $where->Type === PhpLiveDbTypes::Null
        )):
          $this->Query .= $where->Field . ' is null';
        else:
          $this->Query .= $where->Field . $where->Operator->value;
          if($where->Type === PhpLiveDbTypes::Sql):
            $this->Query .= $where->Value;
          else:
            $this->Query .= ':' . ($where->CustomPlaceholder ?? $where->Field);
          endif;
        endif;
        if($where->Parenthesis === PhpLiveDbParenthesis::Close):
          $this->Query .= ')';
        endif;
      endforeach;
    endif;
  }

  protected function Bind(
    PDOStatement &$Statement,
    array $Wheres,
    bool $HtmlSafe = true,
    bool $TrimValues = true
  ):void{
    foreach($Wheres as $where):
      if($where->Value !== null
      and $where->Type !== null
      and $where->Type !== PhpLiveDbTypes::Null
      and $where->Type !== PhpLiveDbTypes::Sql
      and ($where->NoBind ?? false) === false):
        $value = $this->ValueFunctions($where->Value, $HtmlSafe, $TrimValues);
        $Statement->bindValue(
          $where->CustomPlaceholder ?? $where->Field,
          $value,
          $where->Type->value
        );
        $this->Binds[] = [
          $where->CustomPlaceholder ?? $where->Field,
          $value,
          $where->Type
        ];
      endif;
    endforeach;
  }

  protected function FieldNeedCustomPlaceholder(string $Field):void{
    if(strpos($Field, '.') !== false
    or strpos($Field, '(') !== false):
      $this->ErrorSet(new PDOException(
        'The field ' . $Field . ' need a custom placeholder',
      ));
    endif;
  }

  protected function ErrorSet(PDOException $Obj):void{
    $log = date('Y-m-d H:i:s') . PHP_EOL;
    $log .= $Obj->getCode() . ' - ' . htmlspecialchars($Obj->getMessage()) . PHP_EOL;
    $log .= 'Query:' . PHP_EOL;
    $log .= $this->Query . PHP_EOL;
    $log .= 'Binds:' . PHP_EOL;
    $log .= var_export($this->Binds, true) . PHP_EOL;
    $log .= $Obj->getTraceAsString();
    $this->Error = $log;
    error_log($log);
    if(ini_get('display_errors')):
      if(ini_get('html_errors')):
        echo '<pre>' . $log . '</pre>';
      else:
        echo $log;
      endif;
    endif;
  }

  protected function LogAndDebug(
    PDO &$Conn,
    PDOStatement &$Statement,
    bool $Debug = false,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):void{
    ob_start();
    $Statement->debugDumpParams();
    $Dump = ob_get_contents();
    ob_end_clean();

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo htmlspecialchars($Dump);
      error_log($Dump);
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    if($Log):
      $this->LogSet($Conn, $LogEvent, $LogUser, $Dump);
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

class PhpLiveDbWhere{
  public function __construct(
    public string $Field,
    public string|null $Value = null,
    public PhpLiveDbTypes|null $Type = null,
    public PhpLiveDbOperators $Operator = PhpLiveDbOperators::Equal,
    public PhpLiveDbAndOr $AndOr = PhpLiveDbAndOr::And,
    public PhpLiveDbParenthesis $Parenthesis = PhpLiveDbParenthesis::None,
    public string|null $CustomPlaceholder = null,
    public bool $BlankIsNull = true,
    public bool $NoField = false,
    public bool $NoBind = false
  ){}
}