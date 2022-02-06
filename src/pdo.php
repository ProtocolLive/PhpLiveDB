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
  }

  public function NewCmd(int $Cmd, string $Table):PhpLivePdoCmd{
    return new PhpLivePdoCmd($this->Conn, $Cmd, $Table);
  }

  public function RunCustom(
    string $Query,
    bool $OnlyFieldsName = true,
    bool $Debug = false
  ):array{
    set_exception_handler([$this, 'Error']);

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
    return $return;
  }

  public static function Reserved(string $Field):string{
    $names = ['order', 'default', 'group'];
    if(in_array($Field, $names)):
      $Field = '`' . $Field . '`';
    endif;
    return $Field;
  }

  public function Error(object $Obj):void{
    $log = date('Y-m-d H:i:s') . "\n";
    $log .= $Obj->getCode() . ' - ' . $Obj->getMessage() . "\n";
    $log .= $Obj->getTraceAsString();
    error_log($log);
    if(ini_get('display_errors')):
      if(ini_get('html_errors')):
        echo '<pre>' . str_replace("\n", '<br>', $log) . '</pre>';
      else:
        echo $log;
      endif;
      die();
    endif;
  }
}

class PhpLivePdoCmd extends PhpLivePdoBasics{
  private string $Query;
  private array $Join = [];
  private string $Fields1 = '*';
  private array|null $Fields2;
  private array $Wheres = [];
  private string|null $Order = null;
  private string|null $Group = null;
  private string|null $Limit = null;
  public PDOException|null $Error = null;

  public function __construct(
    private PDO $Conn,
    private int $Cmd,
    private string $Table,
    private string $Prefix = ''
  ){}

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

  public function Fields(string $Fields):void{
    $this->Fields1 = $Fields;
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
  ):array|int{
    $FieldsCount = count($this->Fields ?? []);
    $WheresCount = count($this->Wheres);

    if($this->Cmd === self::CmdSelect):
      $this->SelectHead();
      $this->JoinBuild();
    elseif($this->Cmd === self::CmdInsert):
      $this->InsertHead();
    elseif($this->Cmd === self::CmdUpdate):
      $this->UpdateHead();
    elseif($this->Cmd === self::CmdDelete):
      $this->Query = 'delete from ' . $this->Table;
    endif;

    /**
     * @var $field PhpLivePdoField
     * @var $where PhpLivePdoWhere
     */
    if($this->Cmd === self::CmdInsert and $FieldsCount > 0):
      $this->InsertFields();
    elseif($this->Cmd === self::CmdUpdate and $FieldsCount > 0):
      $this->UpdateFields();
    endif;

    if($this->Cmd !== self::CmdInsert and $WheresCount > 0):
      $this->Wheres();
    endif;

    if($this->Cmd === self::CmdSelect):
      if($this->Group !== null):
        $this->Query .= ' group by ' . $this->Group;
      endif;
      if($this->Order !== null):
        $this->Query .= ' order by ' . $this->Order;
      endif;
      if($this->Limit !== null):
        $this->Query .= ' limit ' . $this->Limit;
      endif;
    endif;

    $statement = $this->Conn->prepare($this->Query);

    if($this->Cmd !== self::CmdSelect and $FieldsCount > 0):
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
    if($this->Cmd !== self::CmdInsert and $WheresCount > 0):
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
    }

    if($this->Cmd === self::CmdSelect):
      if($OnlyFieldsName):
        $temp = PDO::FETCH_ASSOC;
      else:
        $temp = PDO::FETCH_DEFAULT;
      endif;
      $return = $statement->fetchAll($temp);
    elseif($this->Cmd === self::CmdInsert):
      $return = $this->Conn->lastInsertId();
      if($return == 0):
        $return = false;
      endif;
    elseif($this->Cmd === self::CmdUpdate or $this->Cmd === self::CmdDelete):
      $return = $statement->rowCount();
    endif;

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
      $this->LogSet($LogUser, $Dump);
    endif;

    return $return;
  }

  public function ErrorSet(PDOException $Obj):void{
    $log = date('Y-m-d H:i:s') . "\n";
    $log .= $Obj->getCode() . ' - ' . $Obj->getMessage() . "\n";
    $log .= $this->Query . "\n";
    $log .= $Obj->getTraceAsString();
    error_log($log);
    if(ini_get('display_errors')):
      if(ini_get('html_errors')):
        echo '<pre>' . str_replace("\n", '<br>', $log) . '</pre>';
      else:
        echo $log;
      endif;
    endif;
    $this->Error = $Obj;
  }

  private function Operator(int $Operator):string{
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

  private function SelectHead():void{
    $this->Query = 'select ' . $this->Fields1 . ' from ';
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

  private function Wheres():void{
    $WheresTemp = $this->Wheres;
    foreach($WheresTemp as $id => $where):
      if($where->NoField === true):
        unset($WheresTemp[$id]);
      endif;
    endforeach;
    $WheresTemp = array_values($WheresTemp);
    if(count($WheresTemp) > 0):
      $this->Query .= ' where ';
      foreach($WheresTemp as $id => $where):
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

  private function LogSet(int|null $User, string $Query):void{
    $Query = substr($Query, strpos($Query, 'Sent SQL: ['));
    $Query = substr($Query, strpos($Query, '] ') + 2);
    $Query = substr($Query, 0, strpos($Query, 'Params: '));
    $Query = trim($Query);

    $statement = $this->Conn->prepare('
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

  private function ValueFunctions(
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
}