<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2022.11.10.00

namespace ProtocolLive\PhpLiveDb;
use PDO;
use PDOException;

final class Delete extends Basics{
  private array $Wheres = [];

  public function __construct(
    PDO $Conn,
    string $Table,
    string $Prefix,
    callable $OnRun = null
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
    $this->OnRun = $OnRun;
  }

  /**
   * @throws PDOException
   */
  public function Run(
    bool $Debug = false,
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):int{
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
    
    $statement->execute();
    $return = $statement->rowCount();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

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

  /**
   * @param string $Field Field name
   * @param string $Value Field value. Can be null in case of use another field value. If null, sets the $Operator to Operator::Null
   * @param Types $Type Field type. Can be null in case of Operator::IsNull. Are changed to Types::Null if $Value is null
   * @param Operators $Operator Comparison operator. Operator::Sql sets NoBind to true
   * @param AndOr $AndOr Relation with the prev field
   * @param Parenthesis $Parenthesis Open or close parenthesis
   * @param string $CustomPlaceholder Substitute the field name as placeholder
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values. Are set to true if Operator is Operators::Sql
   * @throws PDOException
   */
  public function WhereAdd(
    string $Field,
    string $Value = null,
    Types $Type = null,
    Operators $Operator = Operators::Equal,
    AndOr $AndOr = AndOr::And,
    Parenthesis $Parenthesis = Parenthesis::None,
    string $CustomPlaceholder = null,
    bool $BlankIsNull = true,
    bool $NoField = false,
    bool $NoBind = false
  ):void{
    if(isset($this->Wheres[$CustomPlaceholder ?? $Field])):
      throw new PDOException(
        'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
      );
    endif;
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = Types::Null;
    endif;
    $this->Wheres[$CustomPlaceholder ?? $Field] = new Field(
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
}