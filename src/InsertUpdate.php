<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2022.12.16.00

namespace ProtocolLive\PhpLiveDb;
use PDOException;

final class InsertUpdate extends Insert{
  public function FieldAdd(
    string $Field,
    string|bool|null $Value,
    Types $Type,
    bool $BlankIsNull = true,
    bool $Update = false
  ):self{
    if($BlankIsNull and $Value === ''):
      $Value = null;
    endif;
    if($Value === null):
      $Type = Types::Null;
    endif;
    $this->Fields[$Field] = new Field(
      $Field,
      $Value,
      $Type,
      InsertUpdate: $Update
    );
    return $this;
  }

  public function IdGet():int{
    return $this->Conn->lastInsertId();
  }

  /**
   * @return void
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
    if(count($this->Fields) === 0):
      return 0;
    endif;

    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    if($this->Prefix !== null):
      $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    endif;
    $this->Query .= ' on duplicate key update ';
    foreach($this->Fields as $field):
      if($field->InsertUpdate):
        $this->Query .= ($field->CustomPlaceholder ?? $field->Name);
        $this->Query .= '=values(' . ($field->CustomPlaceholder ?? $field->Name) . '),';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);

    $statement->execute();

    $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);
    return 0;
  }
}