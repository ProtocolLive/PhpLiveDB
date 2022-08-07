<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.07.04
//For PHP >= 8.1

require_once(__DIR__ . '/DbBasics.php');

enum PhpLiveDbDrivers:string{
  case MySql = 'mysql';
  case SqLite = 'sqlite';
}

final class PhpLiveDb extends PhpLiveDbBasics{
  /**
   * @throws Exception
   */
  public function __construct(
    string $Ip,
    string $User = null,
    string $Pwd = null,
    string $Db = null,
    PhpLiveDbDrivers $Driver = PhpLiveDbDrivers::MySql,
    string $Charset = 'utf8mb4',
    int $TimeOut = 5,
    string $Prefix = ''
  ){
    $dsn = $Driver->value . ':';
    if($Driver === PhpLiveDbDrivers::MySql):
      if(extension_loaded('pdo_mysql') === false):
        throw new Exception('MySQL PDO driver not founded');
      endif;
      $dsn .= 'host=' . $Ip . ';dbname=' . $Db . ';charset=' . $Charset;
    elseif($Driver === PhpLiveDbDrivers::SqLite):
      if(extension_loaded('pdo_sqlite') === false):
        throw new Exception('SQLite PDO driver not founded');
      endif;
      $dsn .= $Ip;
    endif;
    $this->Conn = new PDO($dsn, $User, $Pwd);
    $this->Conn->setAttribute(PDO::ATTR_TIMEOUT, $TimeOut);
    $this->Conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $this->Conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->Conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if($Driver === PhpLiveDbDrivers::MySql):
      //Enabling profiling to get duration
      $this->Conn->exec('set profiling_history_size=1;set profiling=1;');
    endif;
    if($Driver === PhpLiveDbDrivers::SqLite):
      $this->Conn->exec('pragma foreign_keys=on');
    endif;
    $this->Prefix = $Prefix;
  }

  public function Select(
    string $Table,
    bool $ThrowError = true
  ):PhpLiveDbSelect{
    return new PhpLiveDbSelect(
      $this->Conn,
      $Table,
      $this->Prefix,
      $ThrowError
    );
  }

  public function Insert(string $Table):PhpLiveDbInsert{
    return new PhpLiveDbInsert(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Update(string $Table):PhpLiveDbUpdate{
    return new PhpLiveDbUpdate(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Delete(string $Table):PhpLiveDbDelete{
    return new PhpLiveDbDelete(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function GetCustom():PDO{
    return $this->Conn;
  }
}

final class PhpLiveDbSelect extends PhpLiveDbBasics{
  private string $Fields = '*';
  private array $Join = [];
  private array $Wheres = [];
  private string|null $Order = null;
  private string|null $Group = null;
  private string|null $Limit = null;
  private bool $ThrowError = true;
  private PDOStatement|null $Statement = null;

  public function __construct(
    PDO &$Conn,
    string $Table,
    string $Prefix,
    bool $ThrowError = true
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
    $this->ThrowError = $ThrowError;
  }

  public function Fetch(
    bool $FetchBoth = false,
    int $Offset = 0
  ):array|false{
    if($FetchBoth):
      $this->Statement->setFetchMode(PDO::FETCH_BOTH);
    endif;
    return $this->Statement->fetch(cursorOffset: $Offset);
  }

  public function Fields(string $Fields):void{
    $this->Fields = $Fields;
  }

  public function Group(string $Fields):void{
    $this->Group = $Fields;
  }

  public function JoinAdd(
    string $Table,
    string|null $Using = null,
    string|null $On = null,
    PhpLiveDbJoins $Type = PhpLiveDbJoins::Left
  ):void{
    $this->Join[] = new class($Table, $Type, $Using, $On){
      public string $Table;
      public PhpLiveDbJoins $Type;
      public string|null $Using;
      public string|null $On;

      public function __construct(
        string $Table,
        PhpLiveDbJoins $Type,
        string|null $Using,
        string|null $On
      ){
        $this->Table = $Table;
        $this->Type = $Type;
        $this->Using = $Using;
        $this->On = $On;
      }
    };
  }

  private function JoinBuild():void{
    foreach($this->Join as $join):
      if($join->Type === PhpLiveDbJoins::Inner):
        $this->Query .= ' inner';
      elseif($join->Type === PhpLiveDbJoins::Left):
        $this->Query .= ' left';
      elseif($join->Type === PhpLiveDbJoins::Right):
        $this->Query .= ' right';
      endif;
      $this->Query .= ' join ' . $join->Table;
      if($join->On === null):
        $this->Query .= ' using(' . $join->Using . ')';
      else:
        $this->Query .= ' on(' . $join->On . ')';
      endif;
    endforeach;
  }

  public function Limit(int $Amount, int $First = 0):void{
    $this->Limit = $First . ',' . $Amount;
  }

  public function Order(string $Fields):void{
    $this->Order = $Fields;
  }

  private function Prepare():PDOStatement{
    $this->SelectHead();
    $this->JoinBuild();
    if(count($this->Wheres) > 0):
      $this->BuildWhere($this->Wheres);
    endif;
    if($this->Group !== null):
      $this->Query .= ' group by ' . $this->Group;
    endif;
    if($this->Order !== null):
      $this->Query .= ' order by ' . $this->Order;
    endif;
    if($this->Limit !== null):
      $this->Query .= ' limit ' . $this->Limit;
    endif;

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    return $this->Conn->prepare($this->Query);
  }

  public function QueryGet():string{
    $this->Prepare();
    return $this->Query;
  }

  public function Run(
    bool $FetchBoth = false,
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null,
    bool $Fetch = false
  ):array|bool|null{
    $statement = $this->Prepare();
    if(count($this->Wheres) > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return null;
    }

    $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($Fetch):
      $this->Statement = $statement;
      return true;
    else:
      if($FetchBoth):
        $statement->setFetchMode(PDO::FETCH_BOTH);
      endif;
      return $statement->fetchAll();
    endif;
  }

  private function SelectHead():void{
    $this->Query = 'select ' . $this->Fields . ' from ' . $this->Table;
  }

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of OperatorNull or to use another field custom placeholder
   * @param int $Type Field type. Can be null in case of OperatorIsNull
   * @param int $Operator Comparison operator
   * @param PhpLiveDbAndOr $AndOr Relation with the prev field
   * @param PhpLiveDbParenthesis $Parenthesis Open or close parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLiveDbTypes $Type = null,
    PhpLiveDbOperators $Operator = PhpLiveDbOperators::Equal,
    PhpLiveDbAndOr $AndOr = PhpLiveDbAndOr::And,
    PhpLiveDbParenthesis $Parenthesis = PhpLiveDbParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false,
    bool $Debug = false
  ):bool{
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLiveDbTypes::Null;
    endif;
    if(array_search($CustomPlaceholder ?? $Field, $this->WheresControl) !== false
    and $NoField === false
    and $NoBind === false):
      $error = new Exception(
        'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
      );
      if($this->ThrowError):
        throw $error;
      else:
        $this->ErrorSet($error);
        return false;
      endif;
    endif;
    $this->Wheres[] = new PhpLiveDbWhere(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $BlankIsNull,
      $NoField,
      $NoBind
    );
    $this->WheresControl[] = $CustomPlaceholder ?? $Field;
    if($Debug):
      var_dump($this->Wheres);
    endif;
    return true;
  }
}

final class PhpLiveDbInsert extends PhpLiveDbBasics{
  private array $Fields = [];

  private function InsertFields():void{
    foreach($this->Fields as $field):
      $this->Query .= $field->Field . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields as $id => $field):
      if($field->Type === PhpLiveDbTypes::Null):
        $this->Query .= 'null,';
        unset($this->Fields[$id]);
      elseif($field->Type === PhpLiveDbTypes::Sql):
        $this->Query .= $field->Value . ',';
        unset($this->Fields[$id]);
      else:
        $this->Query .= ':' . ($field->CustomPlaceholder ?? $field->Field) . ',';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ')';
  }

  public function __construct(
    PDO &$Conn,
    string $Table,
    string $Prefix
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
  }

  public function FieldAdd(
    string $Field,
    string|bool|null $Value,
    PhpLiveDbTypes $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = PhpLiveDbTypes::Null;
    endif;
    $this->Fields[$Field] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public PhpLiveDbTypes $Type;

      public function __construct(
        string $Field,
        string|null $Value,
        PhpLiveDbTypes $Type
      ){
        $this->Field = $Field;
        $this->Value = $Value;
        $this->Type = $Type;
      }
    };
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int|null{
    $FieldsCount = count($this->Fields);
    if($FieldsCount === 0):
      return null;
    endif;

    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);

    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return null;
    }

    $return = $this->Conn->lastInsertId();

    $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}

final class PhpLiveDbUpdate extends PhpLiveDbBasics{
  private array $Fields = [];
  private array $Wheres = [];

  private function UpdateFields():void{
    foreach($this->Fields as $id => $field):
      if($field->Type === PhpLiveDbTypes::Null):
        $this->Query .= $field->Field . '=null,';
        unset($this->Fields[$id]);
      elseif($field->Type === PhpLiveDbTypes::Sql):
        $this->Query .= $field->Field . '=' . $field->Value . ',';
        unset($this->Fields[$id]);
      else:
        $this->Query .= $field->Field . '=:' . $field->Field . ',';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
  }

  public function __construct(
    PDO &$Conn,
    string $Table,
    string $Prefix
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
  }

  public function FieldAdd(
    string $Field,
    string|bool|null $Value,
    PhpLiveDbTypes $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = PhpLiveDbTypes::Null;
    endif;
    $this->Fields[$Field] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public PhpLiveDbTypes $Type;

      public function __construct(
        string $Field,
        string|null $Value,
        PhpLiveDbTypes $Type
      ){
        $this->Field = $Field;
        $this->Value = $Value;
        $this->Type = $Type;
      }
    };
  }

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of OperatorNull
   * @param int $Type Field type. Can be null in case of OperatorIsNull
   * @param int $Operator Comparison operator
   * @param PhpLiveDbAndOr $AndOr Relation with the prev field
   * @param PhpLiveDbParenthesis $Parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLiveDbTypes $Type = null,
    PhpLiveDbOperators $Operator = PhpLiveDbOperators::Equal,
    PhpLiveDbAndOr $AndOr = PhpLiveDbAndOr::And,
    PhpLiveDbParenthesis $Parenthesis = PhpLiveDbParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoBind = false
  ):bool{
    if(isset($this->Wheres[$CustomPlaceholder ?? $Field])):
      $this->ErrorSet(new PDOException(
        'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
      ));
      return false;
    endif;
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder(($Field));
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLiveDbTypes::Null;
    endif;
    $this->Wheres[$CustomPlaceholder ?? $Field] = new PhpLiveDbWhere(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $BlankIsNull,
      false,
      $NoBind
    );
    return true;
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int|null{
    $WheresCount = count($this->Wheres);

    $this->Query = 'update ' . $this->Table . ' set ';
    $this->UpdateFields();
    if($WheresCount > 0):
      $this->BuildWhere($this->Wheres);
    endif;

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);
    if($WheresCount > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return null;
    }

    $return = $statement->rowCount();

    $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}

final class PhpLiveDbDelete extends PhpLiveDbBasics{
  private array $Wheres = [];

  public function __construct(
    PDO &$Conn,
    string $Table,
    string $Prefix
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
  }

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of OperatorNull
   * @param int $Type Field type. Can be null in case of OperatorIsNull
   * @param int $Operator Comparison operator
   * @param PhpLiveDbAndOr $AndOr Relation with the prev field
   * @param PhpLiveDbParenthesis $Parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLiveDbTypes $Type = null,
    PhpLiveDbOperators $Operator = PhpLiveDbOperators::Equal,
    PhpLiveDbAndOr $AndOr = PhpLiveDbAndOr::And,
    PhpLiveDbParenthesis $Parenthesis = PhpLiveDbParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ):bool{
    if(isset($this->Wheres[$CustomPlaceholder ?? $Field])):
      $this->ErrorSet(new PDOException(
        'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
      ));
      return false;
    endif;
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLiveDbTypes::Null;
    endif;
    $this->Wheres[$CustomPlaceholder ?? $Field] = new PhpLiveDbWhere(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $BlankIsNull,
      $NoField,
      $NoBind
    );
    return true;
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int|null{
    $WheresCount = count($this->Wheres);

    $this->Query = 'delete from ' . $this->Table;
    if($WheresCount > 0):
      $this->BuildWhere($this->Wheres);
    endif;

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $statement = $this->Conn->prepare($this->Query);

    if($WheresCount > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return null;
    }

    $return = $statement->rowCount();

    $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}