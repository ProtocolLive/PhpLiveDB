<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use PDOException;
use ProtocolLive\PhpLiveDb\Enums\Types;
use UnitEnum;

/**
 * @version 2024.11.23.00
 */
final class InsertUpdate
extends Insert{
  private function BuildQuery():bool{
    if(count($this->Fields) === 0):
      return false;
    endif;

    $this->Query = 'insert into ' . $this->Table . '(';
    $this->InsertFields();

    $this->Query = str_replace('##', $this->Prefix . '_', $this->Query);
    $this->Query .= ' on duplicate key update ';
    foreach($this->Fields as $field):
      if($field->InsertUpdate):
        $this->Query .= parent::Reserved($field->CustomPlaceholder ?? $field->Name);
        $this->Query .= '=values(' . parent::Reserved($field->CustomPlaceholder ?? $field->Name) . '),';
      endif;
    endforeach;
    $this->Query = substr($this->Query, 0, -1);
    return true;
  }

  public function FieldAdd(
    string|UnitEnum $Field,
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
    if($Field instanceof UnitEnum):
      $Field = $Field->value ?? $Field->name;
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

  public function QueryGet():string{
    self::BuildQuery();
    return $this->Query;
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
    int|BackedEnum|null $LogEvent = null,
    int|null $LogUser = null
  ):int{
    if(self::BuildQuery() === false):
      return 0;
    endif;
    $statement = $this->Conn->prepare($this->Query);

    $this->Bind($statement, $this->Fields, $HtmlSafe, $TrimValues);

    $statement->execute();

    $query = $this->LogAndDebug($statement, $Debug, $Log, $LogEvent, $LogUser);

    if($this->OnRun !== null):
      call_user_func_array(
        $this->OnRun,
        [
          'Query' => $query,
          'Result' => 0,
          'Time' => $this->Duration(),
        ]
      );
    endif;
    return 0;
  }
}