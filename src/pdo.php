<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLivePDO
//Version 2021.12.20.01
//For PHP >= 7.4

require_once(__DIR__ . '/PdoBasics.php');

class PhpLivePdo{
  private PDO $Conn;
  private string $Prefix = '';

  public function __construct(array $Options){
    $Options['Drive'] ??= 'mysql';
    $Options['Charset'] ??= 'utf8mb4';
    $Options['TimeOut'] ??= 5;
    $this->Prefix = $Options['Prefix'] ?? '';
    $this->Conn = new PDO(
      $Options['Drive'] . ':host=' . $Options['Ip'] . ';dbname=' . $Options['Db'] . ';charset=' . $Options['Charset'],
      $Options['User'],
      $Options['Pwd']
    );
    $this->Conn->setAttribute(PDO::ATTR_TIMEOUT, $Options['TimeOut']);
    $this->Conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    //Enabling profiling to get duration
    $statement = $this->Conn->prepare('set profiling_history_size=1;set profiling=1;');
    $statement->execute();
  }

  public function NewCmd(int $Cmd, string $Table, string $Prefix = ''):PhpLivePdoCmd{
    return new PhpLivePdoCmd($this->Conn, $Cmd, $Table, $Prefix !== '' ? $Prefix : $this->Prefix);
  }
}

class PhpLivePdoCmd extends PhpLivePdoBasics{
  private PDO $Conn;
  private string $Prefix = '';

  private string $Query;
  private int $Cmd;
  private string $Table;
  private array $Join = [];
  private string $Fields1 = '*';
  private ?array $Fields2;
  private array $Wheres = [[], []];
  private ?string $Order = null;
  private ?string $Group = null;
  private ?string $Limit = null;

  public function __construct(PDO $Conn, int $Cmd, string $Table, string $Prefix = ''){
    $this->Conn = $Conn;
    $this->Cmd = $Cmd;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
  }

  public function JoinAdd(
    string $Table,
    string $Using = null,
    string $On = null,
    int $Type = PhpLivePdoJoin::TypeLeft
  ):void{
    $this->Join[] = new PhpLivePdoJoin($Table, $Type, $Using, $On);
  }

  public function Fields(string $Fields):void{
    $this->Fields1 = $Fields;
  }

  public function FieldAdd(
    string $Field,
    string $Value,
    int $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Type = PhpLivePdoBasics::TypeNull;
    endif;
    $this->Fields2[] = new PhpLivePdoField($Field, $Value, $Type);
  }

  /**
   * @param string $Field Field name
   * @param string $Value Field value
   * @param int $Type Field type
   * @param int $Operator Comparison operator
   * @param int $AndOr Relation with the next field
   * @param int $Parentesis Open (0) or close (1) parentesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   */
  public function WhereAdd(
    string $Field,
    string $Value,
    int $Type = 0,
    int $Operator = self::OperatorEqual,
    int $AndOr = 0,
    int $Parentesis = null,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false
  ){
    if($BlankIsNull and $Value === ''):
      $Type = self::TypeNull;
    endif;
    $temp = new PhpLivePdoWhere(
      $Field,
      $Value,
      $Type,
      $Operator,
      $AndOr,
      $Parentesis,
      $CustomPlaceholder
    );
    if($NoField):
      $this->Wheres[1][] = $temp;
    else:
      $this->Wheres[0][] = $temp;
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

  public function Run(array $Options = []){
    $Options['OnlyFieldsName'] ??= true;
    $Options['Debug'] ??= false;
    $Options['HtmlSafe'] ??= true;
    $Fields2Count = count($this->Fields2 ?? []);
    $Wheres1Count = count($this->Wheres[0]);
    $Wheres2Count = count($this->Wheres[1]);
  
    if($this->Cmd === self::CmdSelect):
      $this->SelectHead();
      $this->JoinBuild();
    elseif($this->Cmd === self::CmdInsert):
      $this->InsertHead();
    elseif($this->Cmd === self::CmdUpdate):
      $this->UpdateHead();
    endif;

    /**
     * @var $field PhpLivePdoField
     * @var $where PhpLivePdoWhere
     */
    if($this->Cmd === self::CmdInsert and $Fields2Count > 0):
      $this->InsertFields();
    elseif($this->Cmd === self::CmdUpdate and $Fields2Count > 0):
      $this->UpdateFields();
    endif;

    if($this->Cmd !== self::CmdInsert and $Wheres1Count > 0):
      $this->Wheres();
    endif;

    if($this->Cmd === self::CmdSelect):
      if($this->Order !== null):
        $this->Query .= ' order by ' . $this->Order;
      endif;
      if($this->Group !== null):
        $this->Query .= ' group by ' . $this->Group;
      endif;
      if($this->Limit !== null):
        $this->Query .= ' limit ' . $this->Limit;
      endif;
    endif;
    //echo $Query;

    $statement = $this->Conn->prepare($this->Query);
    if($this->Cmd !== self::CmdSelect and $Fields2Count > 0):
      foreach($this->Fields2 as $field):
        if($Options['HtmlSafe']):
          $value = htmlspecialchars($field->Value);
        else:
          $value = $field->Value;
        endif;
        $statement->bindValue(':' . $field->Name, $value, $field->Type);
      endforeach;
    endif;
    if($this->Cmd !== self::CmdInsert and ($Wheres1Count > 0 or $Wheres2Count > 0)):
      foreach($this->Wheres as $var):
        foreach($var as $where):
          if($where->Type !== self::TypeNull
          and $where->Operator !== self::OperatorIsNotNull):
            if($Options['HtmlSafe']):
              $value = htmlspecialchars($where->Value);
            else:
              $value = $field->Value;
            endif;
            $statement->bindValue($where->CustomPlaceholder ?? $where->Field, $value, $where->Type);
          endif;
        endforeach;
      endforeach;
    endif;

    $statement->execute();

    if($statement->errorCode() !== '00000'):
      $info = $statement->errorInfo();
      $log = date('Y-m-d H:i:s') . "\n";
      $log .= $info[0] . ' - ' . $info[2] . "\n";
      $log .= json_encode(debug_backtrace(), JSON_PRETTY_PRINT) . "\n\n";
      if(is_file(__DIR__ . '/error.log')):
        file_put_contents(__DIR__ . '/error.log', $log, FILE_APPEND);
      else:
        file_put_contents(__DIR__ . '/error.log', $log,);
      endif;
      if(ini_get('display_errors')):
        if(ini_get('html_errors')):
          echo '<pre>' . str_replace("\n", '<br>', $log) . '</pre>';
        else:
          echo $log;
        endif;
        die();
      endif;
    endif;

    if($this->Cmd === self::CmdSelect):
      if($Options['OnlyFieldsName']):
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

    if($Options['Debug'] == true):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      $statement->debugDumpParams();
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;
    return $return;
  }

  private function Operator(int $Operator):string{
    if($Operator === self::OperatorEqual):
      return '=';
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
      if($join->Type === PhpLivePdoJoin::TypeInner):
        $this->Query .= ' inner';
      elseif($join->Type === PhpLivePdoJoin::TypeLeft):
        $this->Query .= ' left';
      elseif($join->Type === PhpLivePdoJoin::TypeRight):
        $this->Query .= ' right';
      endif;
      $this->Query .= ' join ' . $join->Table;
      if($join->Using === null):
        $this->Query .= ' on(' . $join->On . ')';
      else:
        $this->Query .= ' using(' . $join->Using . ')';
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
    foreach($this->Fields2 as $field):
      $this->Query .= $field->Name . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields2 as $id => $field):
      if($field->Type === self::TypeSql):
        $this->Query .= $field->Value . ',';
        unset($this->Fields2[$id]);
      else:
        $this->Query .= ':' . $field->Name . ',';
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
    foreach($this->Fields2 as $id => $field):
      if($field->Type === self::TypeSql):
        $this->Query .= $field->Name . '=' . $field->Value . ',';
        unset($this->Fields2[$id]);
      else:
        $this->Query .= $field->Name . '=:' . $field->Name . ',';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
  }

  private function Wheres():void{
    $this->Query .= ' where ';
    foreach($this->Wheres[0] as $id => $where):
      if($id > 0):
        if($where->AndOr === 0):
          $this->Query .= ' and ';
        else:
          $this->Query .= ' or ';
        endif;
      endif;
      if($where->Operator === self::OperatorIsNotNull):
        $this->Query .= $where->Field . ' is not null';
      elseif($where->Value === null or $where->Type === self::TypeNull):
        $this->Query .= $where->Field . ' is null';
      else:
        $this->Query .= $where->Field . $this->Operator($where->Operator) . ':' . ($where->CustomPlaceholder ?? $where->Field);
      endif;
    endforeach;
  }
}