<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2022.09.23.00

namespace ProtocolLive\PhpLiveDb;
use \PDO;
use \PDOException;

class Insert extends Basics{
  protected array $Fields = [];

  public function __construct(
    PDO $Conn,
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
    Types $Type,
    bool $BlankIsNull = true
  ){
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = Types::Null;
    endif;
    $this->Fields[$Field] = new Field(
      $Field,
      $Value,
      $Type
    );
  }

  protected function InsertFields():void{
    foreach($this->Fields as $field):
      $this->Query .= $field->Name . ',';
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ') values(';
    foreach($this->Fields as $id => $field):
      if($field->Type === Types::Null):
        $this->Query .= 'null,';
      elseif($field->Type === Types::Sql):
        $this->Query .= $field->Value . ',';
      else:
        $this->Query .= ':' . ($field->CustomPlaceholder ?? $field->Name) . ',';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1) . ')';
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