<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2022.05.09.00
//For PHP >= 8.1

require_once(__DIR__ . '/PdoBasics.php');

class PhpLivePdo extends PhpLivePdoBasics{
  private PDO $Conn;

  public function __construct(
    string $Ip,
    string $User,
    string $Pwd,
    string $Db,
    string $Drive = 'mysql',
    string $Charset = 'utf8mb4',
    int $TimeOut = 5,
    string $Prefix = ''
  ){
    $this->Conn = new PDO(
      $Drive . ':host=' . $Ip . ';dbname=' . $Db . ';charset=' . $Charset,
      $User,
      $Pwd
    );
    $this->Conn->setAttribute(PDO::ATTR_TIMEOUT, $TimeOut);
    $this->Conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $this->Conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //Enabling profiling to get duration
    $statement = $this->Conn->prepare('set profiling_history_size=1;set profiling=1;');
    $statement->execute();
    $this->Prefix = $Prefix;
  }

  public function Select(string $Table){
    return new PhpLivePdoSelect(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Insert(string $Table){
    return new PhpLivePdoInsert(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Update(string $Table){
    return new PhpLivePdoUpdate(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Delete(string $Table){
    return new PhpLivePdoDelete(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function GetCustom():PDO{
    return $this->Conn;
  }
}

class PhpLivePdoSelect extends PhpLivePdoBasics{
  private PDO $Conn;
  private string $Fields = '*';
  private array $Join = [];
  private array $Wheres = [];
  private string|null $Order = null;
  private string|null $Group = null;
  private string|null $Limit = null;

  private function SelectHead():void{
    $this->Query = 'select ' . $this->Fields . ' from ' . $this->Table;
  }

  private function JoinBuild():void{
    foreach($this->Join as $join):
      if($join->Type === PhpLivePdoJoins::Inner):
        $this->Query .= ' inner';
      elseif($join->Type === PhpLivePdoJoins::Left):
        $this->Query .= ' left';
      elseif($join->Type === PhpLivePdoJoins::Right):
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

  public function __construct(
    PDO &$Conn,
    string $Table,
    string $Prefix
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
  }

  public function Fields(string $Fields):void{
    $this->Fields = $Fields;
  }

  public function JoinAdd(
    string $Table,
    string|null $Using = null,
    string|null $On = null,
    PhpLivePdoJoins $Type = PhpLivePdoJoins::Left
  ):void{
    $this->Join[] = new class($Table, $Type, $Using, $On){
      public string $Table;
      public PhpLivePdoJoins $Type;
      public string|null $Using;
      public string|null $On;

      public function __construct(
        string $Table,
        PhpLivePdoJoins $Type,
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

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of OperatorNull or to use another field custom placeholder
   * @param int $Type Field type. Can be null in case of OperatorIsNull
   * @param int $Operator Comparison operator
   * @param PhpLivePdoAndOr $AndOr Relation with the prev field
   * @param PhpLivePdoParenthesis $Parenthesis Open or close parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLivePdoTypes $Type = null,
    PhpLivePdoOperators $Operator = PhpLivePdoOperators::Equal,
    PhpLivePdoAndOr $AndOr = PhpLivePdoAndOr::And,
    PhpLivePdoParenthesis $Parenthesis = PhpLivePdoParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false,
    bool $Debug = false
  ){
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLivePdoTypes::Null;
    endif;
    $this->Wheres[] = new PhpLivePdoWhere(
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
    if($Debug):
      var_dump($this->Wheres);
    endif;
  }

  public function Order(string $Fields):void{
    $this->Order = $Fields;
  }

  public function Group(string $Fields):void{
    $this->Group = $Fields;
  }

  public function Limit(int $Amount, int $First = 0):void{
    $this->Limit = $First . ',' . $Amount;
  }

  public function Run(
    bool $OnlyFieldsName = true,
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):array|false{
    $WheresCount = count($this->Wheres);

    $this->SelectHead();
    $this->JoinBuild();
    if($WheresCount > 0):
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
    $statement = $this->Conn->prepare($this->Query);

    if($WheresCount > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    if($OnlyFieldsName):
      $temp = PDO::FETCH_ASSOC;
    else:
      $temp = PDO::FETCH_DEFAULT;
    endif;
    $return = $statement->fetchAll($temp);

    $this->LogAndDebug($this->Conn, $statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}

class PhpLivePdoInsert extends PhpLivePdoBasics{
  private PDO $Conn;
  private array $Fields = [];

  private function InsertFields():void{
    foreach($this->Fields as $field):
      $this->Query .= $field->Field . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields as $id => $field):
      if($field->Type === PhpLivePdoTypes::Null):
        $this->Query .= 'null,';
        unset($this->Fields[$id]);
      elseif($field->Type === PhpLivePdoTypes::Sql):
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
    string|null $Value,
    PhpLivePdoTypes $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLivePdoTypes::Null;
    endif;
    $this->Fields[$Field] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public PhpLivePdoTypes $Type;

      public function __construct(
        string $Field,
        string|null $Value,
        PhpLivePdoTypes $Type
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
  ):int|false{
    $FieldsCount = count($this->Fields);
    if($FieldsCount === 0):
      return false;
    endif;

    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $statement = $this->Conn->prepare($this->Query);

    foreach($this->Fields as $field):
      $statement->bindValue(
        $field->Field,
        $this->ValueFunctions($field->Value, $HtmlSafe, $TrimValues),
        $field->Type->value
      );
    endforeach;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    $return = $this->Conn->lastInsertId();

    $this->LogAndDebug($this->Conn, $statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}

class PhpLivePdoUpdate extends PhpLivePdoBasics{
  private PDO $Conn;
  private array $Fields = [];
  private array $Wheres = [];

  private function UpdateFields():void{
    foreach($this->Fields as $id => $field):
      if($field->Type === PhpLivePdoTypes::Null):
        $this->Query .= $field->Field . '=null,';
        unset($this->Fields[$id]);
      elseif($field->Type === PhpLivePdoTypes::Sql):
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
    string|null $Value,
    PhpLivePdoTypes $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLivePdoTypes::Null;
    endif;
    $this->Fields[$Field] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public PhpLivePdoTypes $Type;

      public function __construct(
        string $Field,
        string|null $Value,
        PhpLivePdoTypes $Type
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
   * @param PhpLivePdoAndOr $AndOr Relation with the prev field
   * @param PhpLivePdoParenthesis $Parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLivePdoTypes $Type = null,
    PhpLivePdoOperators $Operator = PhpLivePdoOperators::Equal,
    PhpLivePdoAndOr $AndOr = PhpLivePdoAndOr::And,
    PhpLivePdoParenthesis $Parenthesis = PhpLivePdoParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoBind = false
  ){
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder(($Field));
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLivePdoTypes::Null;
    endif;
    $this->Wheres[] = new PhpLivePdoWhere(
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
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int|false{
    $WheresCount = count($this->Wheres);

    $this->Query = 'update ' . $this->Table . ' set ';
    $this->UpdateFields();
    if($WheresCount > 0):
      $this->BuildWhere($this->Wheres);
    endif;

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $statement = $this->Conn->prepare($this->Query);

    foreach($this->Fields as $field):
      if($field->Type !== PhpLivePdoTypes::Null
      and $field->Type !== PhpLivePdoTypes::Sql):
        $value = $this->ValueFunctions($field->Value, $HtmlSafe, $TrimValues);
        $statement->bindValue(
          $field->CustomPlaceholder ?? $field->Field,
          $value,
          $field->Type->value
        );
      endif;
    endforeach;
    if($WheresCount > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    $return = $statement->rowCount();

    $this->LogAndDebug($this->Conn, $statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}

class PhpLivePdoDelete extends PhpLivePdoBasics{
  private PDO $Conn;
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
   * @param PhpLivePdoAndOr $AndOr Relation with the prev field
   * @param PhpLivePdoParenthesis $Parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    PhpLivePdoTypes $Type = null,
    PhpLivePdoOperators $Operator = PhpLivePdoOperators::Equal,
    PhpLivePdoAndOr $AndOr = PhpLivePdoAndOr::And,
    PhpLivePdoParenthesis $Parenthesis = PhpLivePdoParenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ){
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = PhpLivePdoTypes::Null;
    endif;
    $this->Wheres[] = new PhpLivePdoWhere(
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
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int|false{
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
      return false;
    }

    $return = $statement->rowCount();

    $this->LogAndDebug($this->Conn, $statement, $Debug, $Log, $LogEvent, $LogUser);

    return $return;
  }
}