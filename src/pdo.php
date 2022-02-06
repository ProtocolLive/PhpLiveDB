<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2022.02.06.01
//For PHP >= 8

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

  public function RunCustom(
    string $Query,
    bool $OnlyFieldsName = true,
    bool $Debug = false
  ):array|int|null{
    set_exception_handler([$this, 'ErrorSet']);

    $statement = $this->Conn->prepare($Query);
    $statement->execute();

    if($OnlyFieldsName):
      $temp = PDO::FETCH_ASSOC;
    else:
      $temp = PDO::FETCH_DEFAULT;
    endif;
    $return = $statement->fetchAll($temp);

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      $statement->debugDumpParams();
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    restore_error_handler();
    if($this->Error === null):
      return $return;
    else:
      return null;
    endif;
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
    $this->Query = 'select ' . $this->Fields . ' from ';
    if($this->Prefix !== ''):
      $this->Query .= $this->Prefix . '_';
    endif;
    $this->Query .= $this->Table;
  }

  private function JoinBuild():void{
    foreach($this->Join as $join):
      if($join->Type === PhpLivePdoBasics::JoinInner):
        $this->Query .= ' inner';
      elseif($join->Type === PhpLivePdoBasics::JoinLeft):
        $this->Query .= ' left';
      elseif($join->Type === PhpLivePdoBasics::JoinRight):
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
    int $Type = PhpLivePdoBasics::JoinLeft
  ):void{
    $this->Join[] = new class($Table, $Type, $Using, $On){
      public string $Table;
      public int $Type;
      public string|null $Using;
      public string|null $On;

      public function __construct($Table, $Type, $Using, $On){
        $this->Table = $Table;
        $this->Type = $Type;
        $this->Using = $Using;
        $this->On = $On;
      }
    };
  }

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of OperatorNull
   * @param int $Type Field type. Can be null in case of OperatorIsNull
   * @param int $Operator Comparison operator
   * @param int $AndOr Relation with the prev field
   * @param int $Parenthesis Open (0) or close (1) parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string|null $Value = null,
    int|null $Type = null,
    int $Operator = self::OperatorEqual,
    int $AndOr = 0,
    int $Parenthesis = null,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ){
    if($BlankIsNull and $Value === ''):
      $Type = self::TypeNull;
    endif;
    $this->Wheres[] = new class(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $NoField,
      $NoBind
    ){
      public string $Field;
      public string|null $Value;
      public int|null $Type;
      public int $Operator = PhpLivePdoBasics::OperatorEqual;
      public int $AndOr = 0;
      public int|null $Parenthesis = null;
      public string|null $CustomPlaceholder = null;
      public bool $NoField = false;
      public bool $NoBind = false;

      public function __construct(
        $Field,
        $Value,
        $Type,
        $Operator,
        $AndOr,
        $Parenthesis,
        $CustomPlaceholder,
        $NoField,
        $NoBind
      ){
        $this->Field = $Field;
        $this->Value = $Value;
        $this->Type = $Type;
        $this->Operator = $Operator;
        $this->AndOr = $AndOr;
        $this->Parenthesis = $Parenthesis;
        $this->CustomPlaceholder = $CustomPlaceholder;
        $this->NoField = $NoField;
        $this->NoBind = $NoBind;
      }
    };
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
    int $LogUser = null
  ):array|false{
    $WheresCount = count($this->Wheres);

    $this->SelectHead();
    $this->JoinBuild();
    if($WheresCount > 0):
      $this->Wheres($this->Wheres);
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

    $statement = $this->Conn->prepare($this->Query);

    if($WheresCount > 0):
      foreach($this->Wheres as $where):
        if($where->Value !== null
        and $where->Type !== self::TypeNull
        and $where->Operator !== self::OperatorIsNotNull
        and $where->NoBind === false):
          $value = $this->ValueFunctions($where->Value, $HtmlSafe, $TrimValues);
          $statement->bindValue($where->CustomPlaceholder ?? $where->Field, $value, $where->Type);
        endif;
      endforeach;
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

    //Log and Debug
    ob_start();
    $statement->debugDumpParams();
    $Dump = ob_get_contents();
    ob_end_clean();

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo $Dump;
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    if($Log):
      $this->LogSet($this->Conn, $LogUser, $Dump);
    endif;

    return $return;
  }
}

class PhpLivePdoInsert extends PhpLivePdoBasics{
  private PDO $Conn;
  private array $Fields = [];

  private function InsertHead():void{
    $this->Query = 'insert into ';
    if($this->Prefix !== ''):
      $this->Query .= $this->Prefix . '_';
    endif;
    $this->Query .= $this->Table . '(';
  }

  private function InsertFields():void{
    foreach($this->Fields as $field):
      $this->Query .= $field->Field . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields as $id => $field):
      if($field->Type === self::TypeSql):
        $this->Query .= $field->Value . ',';
        unset($this->Fields[$id]);
      else:
        $this->Query .= ':' . $field->Field . ',';
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
    int $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Type = PhpLivePdoBasics::TypeNull;
    endif;
    $this->Fields[] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public int $Type;

      public function __construct(string $Field, string|null $Value, int $Type){
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
    int $LogUser = null
  ):int|false{
    $FieldsCount = count($this->Fields ?? []);
    $WheresCount = count($this->Wheres);

    $this->InsertHead();
    if($FieldsCount > 0):
      $this->InsertFields();
    endif;
    if($WheresCount > 0):
      $this->Wheres($this->Wheres);
    endif;

    $statement = $this->Conn->prepare($this->Query);

    if($FieldsCount > 0):
      foreach($this->Fields as $field):
        if($field->Type !== PhpLivePdoBasics::TypeSql):
          if($field->Value === null):
            $statement->bindValue(':' . $field->Field, null, PDO::PARAM_NULL);
          else:
            $value = $this->ValueFunctions($field->Value, $HtmlSafe, $TrimValues);
            $statement->bindValue(':' . $field->Field, $value, $field->Type);
          endif;
        endif;
      endforeach;
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    $return = $this->Conn->lastInsertId();

    //Log and Debug
    ob_start();
    $statement->debugDumpParams();
    $Dump = ob_get_contents();
    ob_end_clean();

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo $Dump;
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    if($Log):
      $this->LogSet($this->Conn, $LogUser, $Dump);
    endif;

    return $return;
  }
}

class PhpLivePdoUpdate extends PhpLivePdoBasics{
  private PDO $Conn;
  private array $Fields = [];
  private array $Wheres = [];

  private function UpdateHead():void{
    $this->Query = 'update ';
    if($this->Prefix !== ''):
      $this->Query .= $this->Prefix . '_';
    endif;
    $this->Query .= $this->Table . ' set ';
  }

  private function UpdateFields():void{
    foreach($this->Fields as $id => $field):
      if($field->Type === self::TypeSql):
        $this->Query .= $field->Field . '=' . $field->Value . ',';
        unset($this->Fields2[$id]);
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
    int $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Type = PhpLivePdoBasics::TypeNull;
    endif;
    $this->Fields[] = new class(
      $Field,
      $Value,
      $Type
    ){
      public string $Field;
      public string|null $Value;
      public int $Type;

      public function __construct(string $Field, string|null $Value, int $Type){
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
   * @param int $AndOr Relation with the prev field
   * @param int $Parenthesis Open (0) or close (1) parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string|null $Value = null,
    int|null $Type = null,
    int $Operator = self::OperatorEqual,
    int $AndOr = 0,
    int $Parenthesis = null,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ){
    if($BlankIsNull and $Value === ''):
      $Type = self::TypeNull;
    endif;
    $this->Wheres[] = new class(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $NoField,
      $NoBind
    ){
      public string $Field;
      public string|null $Value;
      public int|null $Type;
      public int $Operator = PhpLivePdoBasics::OperatorEqual;
      public int $AndOr = 0;
      public int|null $Parenthesis = null;
      public string|null $CustomPlaceholder = null;
      public bool $NoField = false;
      public bool $NoBind = false;

      public function __construct(
        $Field,
        $Value,
        $Type,
        $Operator,
        $AndOr,
        $Parenthesis,
        $CustomPlaceholder,
        $NoField,
        $NoBind
      ){
        $this->Field = $Field;
        $this->Value = $Value;
        $this->Type = $Type;
        $this->Operator = $Operator;
        $this->AndOr = $AndOr;
        $this->Parenthesis = $Parenthesis;
        $this->CustomPlaceholder = $CustomPlaceholder;
        $this->NoField = $NoField;
        $this->NoBind = $NoBind;
      }
    };
  }

  public function Run(
    bool $OnlyFieldsName = true,
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogUser = null
  ):int|false{
    $FieldsCount = count($this->Fields ?? []);
    $WheresCount = count($this->Wheres);

    $this->UpdateHead();
    $this->UpdateFields();
    if($WheresCount > 0):
      $this->Wheres($this->Wheres);
    endif;

    $statement = $this->Conn->prepare($this->Query);

    foreach($this->Fields as $field):
      if($field->Type !== PhpLivePdoBasics::TypeSql):
        if($field->Value === null):
          $statement->bindValue(':' . $field->Field, null, PDO::PARAM_NULL);
        else:
          $value = $this->ValueFunctions($field->Value, $HtmlSafe, $TrimValues);
          $statement->bindValue(':' . $field->Field, $value, $field->Type);
        endif;
      endif;
    endforeach;
    foreach($this->Wheres as $where):
      if($where->Value !== null
      and $where->Type !== self::TypeNull
      and $where->Operator !== self::OperatorIsNotNull
      and $where->NoBind === false):
        $value = $this->ValueFunctions($where->Value, $HtmlSafe, $TrimValues);
        $statement->bindValue($where->CustomPlaceholder ?? $where->Field, $value, $where->Type);
      endif;
    endforeach;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    $return = $statement->rowCount();

    //Log and Debug
    ob_start();
    $statement->debugDumpParams();
    $Dump = ob_get_contents();
    ob_end_clean();

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo $Dump;
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    if($Log):
      $this->LogSet($this->Conn, $LogUser, $Dump);
    endif;

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
   * @param int $AndOr Relation with the prev field
   * @param int $Parenthesis Open (0) or close (1) parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
   */
  public function WhereAdd(
    string $Field,
    string|null $Value = null,
    int|null $Type = null,
    int $Operator = self::OperatorEqual,
    int $AndOr = 0,
    int $Parenthesis = null,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ){
    if($BlankIsNull and $Value === ''):
      $Type = self::TypeNull;
    endif;
    $this->Wheres[] = new class(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parenthesis,
      $CustomPlaceholder,
      $NoField,
      $NoBind
    ){
      public string $Field;
      public string|null $Value;
      public int|null $Type;
      public int $Operator = PhpLivePdoBasics::OperatorEqual;
      public int $AndOr = 0;
      public int|null $Parenthesis = null;
      public string|null $CustomPlaceholder = null;
      public bool $NoField = false;
      public bool $NoBind = false;

      public function __construct(
        $Field,
        $Value,
        $Type,
        $Operator,
        $AndOr,
        $Parenthesis,
        $CustomPlaceholder,
        $NoField,
        $NoBind
      ){
        $this->Field = $Field;
        $this->Value = $Value;
        $this->Type = $Type;
        $this->Operator = $Operator;
        $this->AndOr = $AndOr;
        $this->Parenthesis = $Parenthesis;
        $this->CustomPlaceholder = $CustomPlaceholder;
        $this->NoField = $NoField;
        $this->NoBind = $NoBind;
      }
    };
  }

  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogUser = null
  ):int|false{
    $WheresCount = count($this->Wheres);

    $this->Query = 'delete from ';
    if($this->Prefix !== ''):
      $this->Query .= $this->Prefix . '_';
    endif;
    $this->Query .= $this->Table;

    if($WheresCount > 0):
      $this->Wheres($this->Wheres);
    endif;

    $statement = $this->Conn->prepare($this->Query);

    if($WheresCount > 0):
      foreach($this->Wheres as $where):
        if($where->Value !== null
        and $where->Type !== self::TypeNull
        and $where->Operator !== self::OperatorIsNotNull
        and $where->NoBind === false):
          $value = $this->ValueFunctions($where->Value, $HtmlSafe, $TrimValues);
          $statement->bindValue($where->CustomPlaceholder ?? $where->Field, $value, $where->Type);
        endif;
      endforeach;
    endif;
    
    try{
      $this->Error = null;
      $statement->execute();
    }catch(PDOException $e){
      $this->ErrorSet($e);
      return false;
    }

    $return = $statement->rowCount();

    //Log and Debug
    ob_start();
    $statement->debugDumpParams();
    $Dump = ob_get_contents();
    ob_end_clean();

    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo $Dump;
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;

    if($Log):
      $this->LogSet($this->Conn, $LogUser, $Dump);
    endif;

    return $return;
  }
}