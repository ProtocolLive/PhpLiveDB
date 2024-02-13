<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use PDO;
use PDOStatement;
use PDOException;
use UnitEnum;

/**
 * @version 2024.02.13.00
 */
final class Select
extends Basics{
  private string $Fields = '*';
  private array $Join = [];
  private array $Wheres = [];
  private string|null $Order = null;
  private string|null $Group = null;
  private string|null $Limit = null;
  private PDOStatement|null $Statement = null;
  private bool $WhereBlock = false;

  public function __construct(
    PDO $Conn,
    string $Database = null,
    string $Table,
    string $Prefix = null,
    private bool $ThrowError = true,
    callable $OnRun = null
  ){
    $this->Conn = $Conn;
    $this->Database = $Database;
    $this->Table = $Table;
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

  public function Fields(
    string|UnitEnum $Fields
  ):self{
    $this->Fields = $Fields->value ?? $Fields->name ?? $Fields;
    return $this;
  }

  /**
   * To run * except one field
   * @throws PDOException
   */
  public function FieldsExcept(
    string|UnitEnum $Field,
    string $Alias = null
  ):self{
    return $this->Fields($this->FieldsGetExcept($Field, Alias: $Alias));
  }

  /**
   * @throws PDOException
   */
  public function FieldsGet(
    string|UnitEnum $Table = null,
    FieldsGetReturn $Return = FieldsGetReturn::String,
    string $Alias = null
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
   * @throws PDOException
   */
  public function FieldsGetExcept(
    string|UnitEnum $Field,
    string|UnitEnum $Table = null,
    FieldsGetReturn $Return = FieldsGetReturn::String,
    string $Alias = null
  ):array|string{
    $Field = $Field->value ?? $Field->name ?? $Field;
    $return = $this->FieldsGet($Table, FieldsGetReturn::Array, $Alias);
    unset($return[array_search($Alias . '.' . $Field, $return)]);
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

  public function JoinAdd(
    string|UnitEnum $Table,
    string|null|UnitEnum $Using = null,
    string|null $On = null,
    Joins $Type = Joins::Left
  ):self{
    if($Table instanceof UnitEnum):
      $Table = $Table->value ?? $Table->name;
    endif;
    if($Using instanceof UnitEnum):
      $Using = $Using->value ?? $Using->name;
    endif;
    $this->Join[] = new class($Table, $Type, $Using, $On){
      public string $Table;
      public Joins $Type;
      public string|null $Using;
      public string|null $On;

      public function __construct(
        string $Table,
        Joins $Type,
        string|null $Using,
        string|null $On
      ){
        $this->Table = $Table;
        $this->Type = $Type;
        $this->Using = $Using;
        $this->On = $On;
      }
    };
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

  public function Limit(int $Amount, int $First = 0):self{
    $this->Limit = $First . ',' . $Amount;
    return $this;
  }

  public function Order(
    string|UnitEnum|null $Fields
  ):self{
    if($Fields === ''):
      $Fields = null;
    endif;
    if($Fields instanceof UnitEnum):
      $Fields = $Fields->value ?? $Fields->name;
    endif;
    $this->Order = $Fields;
    return $this;
  }

  private function Prepare():PDOStatement{
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

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
    return $this->Conn->prepare($this->Query);
  }

  public function QueryGet():string{
    $this->Prepare();
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
    int|BackedEnum $LogEvent = null,
    int $LogUser = null,
    bool $Fetch = false
  ):array|bool{
    $statement = $this->Prepare();
    if(count($this->Wheres) > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;

    $statement->execute();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($Fetch):
      $this->Statement = $statement;
      $return = true;
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
   * @param string|UnitEnum $Field Field name. Can be null to only add parenthesis or add Exists operator
   * @param string|bool $Value Field value. Can be null in case of use another field value. If null, sets the $Operator to Operator::Null
   * @param Types $Type Field type. Can be null in case of Operator::IsNull. Are changed to Types::Null if $Value is null
   * @param Operators $Operator Comparison operator. Operator::Sql sets NoBind to true
   * @param AndOr $AndOr Relation with the previous field
   * @param Parenthesis $Parenthesis Open or close parenthesis
   * @param string $CustomPlaceholder Substitute the field name as placeholder
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind in that call. Used with Operators::Sql or same value for different or same field. (Changed to true if Operators::Sql are used)
   * @param bool $Debug Show debug information
   * @return self|false Return false if ThrowError are set or all wheres if $Debug are set
   * @throws PDOException
   */
  public function WhereAdd(
    string|UnitEnum $Field = null,
    string|bool $Value = null,
    Types $Type = null,
    Operators $Operator = Operators::Equal,
    AndOr $AndOr = AndOr::And,
    Parenthesis $Parenthesis = Parenthesis::None,
    string $CustomPlaceholder = null,
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
    if($Field !== null):
      if($Field instanceof UnitEnum):
        $Field = $Field->value ?? $Field->name;
      endif;
      if($CustomPlaceholder === null):
        $this->FieldNeedCustomPlaceholder($Field);
      endif;
      if($BlankIsNull and $Value === ''):
        $Value = null;
        $Type = Types::Null;
      endif;
      $temp = $this->WheresControl(
        $this->ThrowError,
        $CustomPlaceholder ?? $Field,
        $Type,
        $Operator,
        $NoField,
        $NoBind
      );
      if($temp === false):
        return false;
      endif;
      if($NoBind === false):
        $this->WheresControl[] = $CustomPlaceholder ?? $Field;
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
    $this->Wheres[] = new Field(
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
      return $this->Wheres;
    endif;
    return $this;
  }
}