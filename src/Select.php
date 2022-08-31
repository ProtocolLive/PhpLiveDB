<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//Version 2022.08.31.01

namespace ProtocolLive\PhpLiveDb;
use \PDO;
use \PDOStatement;
use \PDOException;

final class Select extends Basics{
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
    Joins $Type = Joins::Left
  ):void{
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
   * @param AndOr $AndOr Relation with the prev field
   * @param Parenthesis $Parenthesis Open or close parenthesis
   * @param bool $SqlInField The field have a SQL function?
   * @param bool $BlankIsNull Convert '' to null
   * @param bool $NoField Bind values with fields declared in Fields function
   * @param bool $NoBind Don't bind values who are already binded
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
    bool $NoBind = false,
    bool $Debug = false
  ):bool{
    if($CustomPlaceholder === null):
      $this->FieldNeedCustomPlaceholder($Field);
    endif;
    if($BlankIsNull and $Value === ''):
      $Value = null;
      $Type = Types::Null;
    endif;
    $this->WheresControl(
      $this->ThrowError,
      $Field,
      $Type,
      $Operator,
      $NoField,
      $NoBind
    );
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
    $this->WheresControl[] = $CustomPlaceholder ?? $Field;
    if($Debug):
      var_dump($this->Wheres);
    endif;
    return true;
  }
}