<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PDOException;
use ProtocolLive\PhpLiveDb\Enums\{
  AndOr,
  FieldsGetReturn,
  Joins,
  Operators,
  Parenthesis,
  Types
};
use UnitEnum;

/**
 * @version 2025.07.02.00
 */
final class Select
extends Basics{
  private string $Fields = '*';
  private array $Join = [];
  protected array $Wheres = [];
  private string|null $Order = null;
  private string|null $Group = null;
  private string|null $Limit = null;
  private PDOStatement|null $Statement = null;
  private bool $WhereBlock = false;

  public function __construct(
    protected PDO $Conn,
    protected string $Table,
    string|null $Database = null, //not promoted because the null value
    string|null $Prefix = null, //not promoted because the null value
    private bool $ThrowError = true,
    callable|null $OnRun = null //not promoted because the callable
  ){
    $this->Database = $Database;
    $this->Prefix = $Prefix;
    $this->OnRun = $OnRun;
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

  /**
   * @param string|UnitEnum|string[]|UnitEnum[] $Fields
   */
  public function Fields(
    string|UnitEnum|array $Fields
  ):self{
    if(is_array($Fields) === false):
      $Fields = [$Fields];
    endif;
    foreach($Fields as &$field):
      $field = $field->value ?? $field->name ?? $field;
    endforeach;
    $this->Fields = implode(',', $Fields);
    return $this;
  }

  /**
   * To run * except one field
   * @throws PDOException
   */
  public function FieldsExcept(
    string|UnitEnum $Field,
    string|null $Alias = null
  ):self{
    return $this->Fields($this->FieldsGetExcept($Field, Alias: $Alias));
  }

  /**
   * @throws PDOException
   */
  public function FieldsGet(
    string|UnitEnum|null $Table = null,
    FieldsGetReturn $Return = FieldsGetReturn::String,
    string|null $Alias = null
  ):array|string{
    $Table = $Table->value ?? $Table->name ?? $Table ?? $this->Table;
    if(str_contains($Table, ' ')):
      $Table = substr($Table, 0, strpos($Table, ' '));
    endif;
    $query = 'select ';
    if(empty($Alias) === false):
      $query .= 'concat(\'' . $Alias . '.\',COLUMN_NAME) as ';
    endif;
    $query .= '
      COLUMN_NAME
      from information_schema.columns
      where table_schema=\'' . $this->Database . '\'
      and table_name=\'' . $Table . '\'
    ';
    if($Return === FieldsGetReturn::Sql):
      return $query;
    endif;
    $return = $this->Conn->query($query);
    $return = array_column($return->fetchAll(), 'COLUMN_NAME');
    if($Return === FieldsGetReturn::String):
      return implode(',', $return);
    endif;
    return $return;
  }

  /**
   * Return all table's fields except the expecified field
   * @param string|UnitEnum|string[]|UnitEnum[] $Fields
   * @throws PDOException
   */
  public function FieldsGetExcept(
    string|UnitEnum|array $Fields,
    string|UnitEnum|null $Table = null,
    FieldsGetReturn $Return = FieldsGetReturn::String,
    string|null $Alias = null
  ):array|string{
    $return = $this->FieldsGet($Table, FieldsGetReturn::Array, $Alias);
    if(is_string($Fields)
    or $Fields instanceof UnitEnum):
      $Fields = [$Fields];
    endif;
    foreach($Fields as $field):
      $field = $field->value ?? $field->name ?? $field;
      $pos = array_search($Alias . '.' . $field, $return);
      if($pos !== false):
        unset($return[$pos]);
      endif;
    endforeach;
    if($Return === FieldsGetReturn::String):
      return implode(',', $return);
    endif;
    return $return;
  }

  public function Group(
    string|UnitEnum $Fields
  ):self{
    $this->Group = $Fields->value ?? $Fields->name ?? $Fields;
    return $this;
  }

  /**
   * @throws InvalidArgumentException
   */
  public function JoinAdd(
    string|UnitEnum $Table,
    string|UnitEnum|null $Using = null,
    string|null $On = null,
    Joins $Type = Joins::Left
  ):self{
    if($Using === null
    and $On === null):
      throw new InvalidArgumentException('Need to specify Using or On');
    endif;
    $Table = $Table->value ?? $Table->name ?? $Table;
    $Using = $Using->value ?? $Using->name ?? $Using;
    $this->Join[] = (object)[
      'Table' => $Table,
      'Type' => $Type,
      'Using' => $Using,
      'On' => $On
    ];
    return $this;

  }

  private function JoinBuild():void{
    foreach($this->Join as $join):
      if($join->Type === Joins::Inner):
        $this->Query .= ' inner';
      elseif($join->Type === Joins::Left):
        $this->Query .= ' left';
      elseif($join->Type === Joins::Right):
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

  public function Limit(
    int|null $Amount,
    int $First = 0
  ):self{
    if($Amount !== null):
      $this->Limit = $First . ',' . $Amount;
    endif;
    return $this;
  }

  /**
   * Send empty fields to clear
   */
  public function Order(
    string|UnitEnum|null $Fields = null
  ):self{
    if($Fields === ''):
      $Fields = null;
    endif;
    $this->Order = $Fields->value ?? $Fields->name ?? $Fields;
    return $this;
  }

  public function OrderAdd(
    string|UnitEnum $Field
  ):self{
    if($Field === ''):
      return $this;
    endif;
    if($this->Order !== null):
      $this->Order .= ',';
    endif;
    $this->Order .= $Field->value ?? $Field->name ?? $Field;
    return $this;
  }

  protected function Prepare(
    bool $ForUpdate
  ):PDOStatement{
    $this->SelectHead();
    $this->JoinBuild();
    if(count($this->Wheres) > 0):
      $this->BuildWhere($this->Wheres);
    endif;
    if($this->Group !== null):
      $this->Query .= ' group by ' . parent::Reserved($this->Group);
    endif;
    if($this->Order !== null):
      $this->Query .= ' order by ' . parent::Reserved($this->Order);
    endif;
    if($this->Limit !== null):
      $this->Query .= ' limit ' . $this->Limit;
    endif;
    if($ForUpdate):
      $this->Query .= ' for update';
    endif;

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
    return $this->Conn->prepare($this->Query);
  }

  public function QueryGet(
    bool $ForUpdate = false
  ):string{
    $this->Prepare($ForUpdate);
    return $this->Query;
  }

  /**
   * @throws PDOException
   */
  public function Run(
    bool $FetchBoth = false,
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int|BackedEnum|null $LogEvent = null,
    int|null $LogUser = null,
    bool $Fetch = false,
    bool $ForUpdate = false
  ):array|self{
    $statement = $this->Prepare($ForUpdate);
    if(count($this->Wheres) > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;

    $statement->execute();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($Fetch):
      $this->Statement = $statement;
      $return = $this;
    else:
      if($FetchBoth):
        $statement->setFetchMode(PDO::FETCH_BOTH);
      endif;
      $return = $statement->fetchAll();
    endif;

    if($this->OnRun !== null):
      call_user_func_array(
        $this->OnRun,
        [
          'Query' => $query,
          'Result' => $return,
          'Time' => $this->Duration(),
        ]
      );
    endif;
    return $return;
  }

  private function SelectHead():void{
    $this->Query = 'select ' . $this->Fields . ' from ' . $this->Table;
  }

  /**
   * Note 1: For run like in same field, use different custom placeholders
   * @param string|string[]|UnitEnum|UnitEnum[] $Field Field name. Can be null to only add parenthesis or add Exists operator
   * @param string|bool|UnitEnum $Value Field value. Can be null in case of use another field value. If null, sets the $Operator to Operators::Null. Can be UnitEnum in case of NoBind
   * @param Types $Type Field type. Can be null in case of Operators::IsNull. Are changed to Types::Null if $Value is null
   * @param Operators $Operator Comparison operator. Operator::Sql sets NoBind to true
   * @param AndOr $AndOr Relation with the previous field
   * @param Parenthesis $Parenthesis Open or close parenthesis
   * @param string $CustomPlaceholder Substitute the field name as placeholder
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind in that call. Used with Operators::Sql or same value for different or same field. (Changed to true if Operators::Sql are used)
   * @param bool $Debug Show debug information
   * @param string|int $Value2 A second value. Can be used in case of Operators::Between
   * @return self|false Return false if ThrowError are set or all wheres if $Debug are set
   * @throws PDOException
   */
  public function WhereAdd(
    string|array|UnitEnum|null $Field = null,
    string|bool|UnitEnum|null $Value = null,
    Types|null $Type = null,
    Operators $Operator = Operators::Equal,
    AndOr $AndOr = AndOr::And,
    Parenthesis $Parenthesis = Parenthesis::None,
    string|null $CustomPlaceholder = null,
    string|int|null $Value2 = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false,
    bool $Debug = false
  ):self|array|false{
    if($this->WhereBlock):
      if($this->ThrowError):
        throw new PDOException('No more conditions allowed');
      else:
        error_log('No more conditions allowed');
        return false;
      endif;
    endif;
    if($Operator === Operators::Exists):
      if(count($this->Wheres) === 0):
        $this->WhereBlock = true;
      else:
        if($this->ThrowError):
          throw new PDOException('The operator \'Exists\' must be the first condition');
        else:
          error_log('The operator \'Exists\' must be the first condition');
          return false;
        endif;
      endif;
    endif;
    if($Operator === Operators::Between
    and $Value2 === null):
      if($this->ThrowError):
        throw new PDOException('Value2 can\'t be null');
      else:
        error_log('Value2 can\'t be null');
        return false;
      endif;
    endif;
    if(is_array($Field) === false):
      $Field = [$Field];
    endif;
    foreach($Field as $field):
      if($field !== null):
        $field = $field->value ?? $field->name ?? $field;
        if($CustomPlaceholder === null):
          $this->FieldNeedCustomPlaceholder($field);
        endif;
        if($BlankIsNull and $Value === ''):
          $Value = null;
          $Type = Types::Null;
        endif;
        $temp = $this->WheresControl(
          ThrowError: $this->ThrowError,
          Field: $CustomPlaceholder ?? $field,
          Type: $Type,
          Operator: $Operator,
          NoField: $NoField,
          NoBind: $NoBind
        );
        if($temp === false):
          return false;
        endif;
        if($NoBind === false):
          $this->WheresControl[] = $CustomPlaceholder ?? $field;
        endif;
      endif;
      $this->Wheres[] = new Field(
        Name: $field,
        Value: $Value->value ?? $Value->name ?? $Value,
        Type: $Type,
        Operator: $Operator,
        AndOr: $AndOr,
        Parenthesis: $Parenthesis,
        CustomPlaceholder: $CustomPlaceholder,
        BlankIsNull: $BlankIsNull,
        NoField: $NoField,
        NoBind: $NoBind,
        Value2: $Value2
      );
    endforeach;
    if($Debug):
      return $this->Wheres;
    endif;
    return $this;
  }
}