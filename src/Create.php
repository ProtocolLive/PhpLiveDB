<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use InvalidArgumentException;
use PDO;
use PDOException;
use ProtocolLive\PhpLiveDb\Enums\{
  Drivers,
  Engines,
  Formats,
  RefTypes
};
use UnitEnum;

/**
 * @version 2024.11.23.00
 */
final class Create
extends Basics{
  private array $Fields = [];
  private array $Unique = [];

  public function __construct(
    PDO $Conn,
    string $Table,
    string|null $Prefix = null,
    Drivers $Driver = Drivers::MySql,
    callable|null $OnRun = null
  ){
    $this->Conn = $Conn;
    $this->Table = $Table;
    $this->Prefix = $Prefix;
    $this->Driver = $Driver;
    $this->OnRun = $OnRun;
  }

  /**
   * @throws InvalidArgumentException
   */
  public function Add(
    string|UnitEnum $Name,
    Formats $Format,
    int|null $Size = null,
    bool $Unsigned = false,
    string|null $CharSet = null,
    string|null $Collate = null,
    bool $NotNull = false,
    string|null $Default = null,
    bool $Primary = false,
    bool $AutoIncrement = false,
    bool $Unique = false,
    string|UnitEnum|null $RefTable = null,
    string|UnitEnum|null $RefField = null,
    RefTypes $RefUpdate = RefTypes::Restrict,
    RefTypes $RefDelete = RefTypes::Restrict,
  ):self{
    if($Format === Formats::Varchar
    and $Size === null):
      throw new InvalidArgumentException('Varchar must have a size parameter');
    endif;
    if($Name instanceof UnitEnum):
      $Name = $Name->value ?? $Name->name;
    endif;
    $this->Fields[$Name] = [
      'Name' => $Name,
      'Format' => $Format,
      'Size' => $Size,
      'Unsigned' => $Unsigned,
      'CharSet' => $CharSet,
      'Collate' => $Collate,
      'NotNull' => $NotNull,
      'Default' => $Default,
      'Primary' => $Primary,
      'AutoIncrement' => $AutoIncrement,
      'Unique' => $Unique,
      'RefTable' => $RefTable->value ?? $RefTable,
      'RefField' => $RefField->value ?? $RefField,
      'RefUpdate' => $RefUpdate,
      'RefDelete' => $RefDelete
    ];
    return $this;
  }

  /**
   * @throws PDOException
   */
  private function BuildRun(
    Engines $Engine = Engines::InnoDB,
    string $CharSet = 'utf8mb4',
    string $Collate = 'utf8mb4_unicode_ci',
    bool $IfNotExists = false
  ):string{
    $Query = 'create table ';
    if($IfNotExists):
      $Query .= 'if not exists ';
    endif;
    $Query .= $this->Table . '(';
    foreach($this->Fields as $field):
      $Query .= parent::Reserved($field['Name']) . ' ';
      $Query .= self::BuildFormat(
        $field['Format'],
        $field['Size'],
        $field['Unsigned']
      );
      if($field['CharSet'] !== null):
        $Query .= ' character set ' . $field['CharSet'];
        $Query .= ' collate ' . $field['Collate'];
      endif;
      if($field['NotNull']):
        $Query .= ' not null';
      endif;
      if($field['Default'] !== null):
        $Query .= ' default \'' . $field['Default'] . '\'';
      endif;
      if($field['Primary']):
        $Query .= ' primary key';
      endif;
      if($field['AutoIncrement']):
        if($this->Driver === Drivers::MySql):
          $Query .= ' auto_increment';
        else:
          $Query .= ' autoincrement';
        endif;
      endif;
      if($field['Unique']):
        $Query .= ' unique';
      endif;
      if($field['RefTable'] !== null):
        $Query .= ' references ' . $field['RefTable'];
        $Query .= '(' . $field['RefField'] . ')';
        if($field['RefDelete'] !== RefTypes::Restrict):
          $Query .= ' on delete ' . $field['RefDelete']->value;
        endif;
        if($field['RefUpdate'] !== RefTypes::Restrict):
          $Query .= ' on update ' . $field['RefUpdate']->value;
        endif;
      endif;
      $Query .= ',';
    endforeach;
    foreach($this->Unique as $fields):
      $Query .= 'unique(';
      foreach($fields as $field):
        $Query .= $field . ',';
      endforeach;
      $Query = substr($Query, 0, -1);
      $Query .= '),';
    endforeach;
    $Query = substr($Query, 0, -1);
    $Query .= ')';
    if($this->Driver === Drivers::MySql):
      $Query .= ' Engine=' . $Engine->name;
      $Query .= ' default charset=' . $CharSet;
      $Query .= ' collate=' . $Collate;
    endif;
    return $Query;
  }

  private function BuildFormat(
    Formats $Format,
    int|null $Size = null,
    bool $Unsigned = false
  ):string{
    if($this->Driver === Drivers::MySql):
      $Query = match($Format){
        Formats::Int => 'int',
        Formats::IntBig => 'bigint',
        Formats::IntTiny => 'tinyint',
        Formats::Text => 'text',
        Formats::Varchar => 'varchar',
      };
      if($Format === Formats::Varchar):
        $Query .= '(' . $Size . ')';
      elseif($Format === Formats::Int
      or $Format === Formats::IntTiny
      or $Format === Formats::IntBig):
        if($Unsigned):
          $Query .= ' unsigned';
        endif;
      endif;
      return $Query;
    else:
      return match($Format){
        Formats::Int,
        Formats::IntBig,
        Formats::IntTiny => 'integer',
        Formats::Text,
        Formats::Varchar => 'text',
      };
    endif;
  }

  public function QueryGet(
    Engines $Engine = Engines::InnoDB,
    string $CharSet = 'utf8mb4',
    string $Collate = 'utf8mb4_unicode_ci',
    bool $IfNotExists = false
  ):string{
    return self::BuildRun(
      $Engine,
      $CharSet,
      $Collate,
      $IfNotExists
    );
  }

  /**
   * @throws PDOException
   */
  public function Run(
    Engines $Engine = Engines::InnoDB,
    string $CharSet = 'utf8mb4',
    string $Collate = 'utf8mb4_unicode_ci',
    bool $IfNotExists = false
  ):void{
    $Query = self::BuildRun(
      $Engine,
      $CharSet,
      $Collate,
      $IfNotExists
    );
    $this->Conn->exec($Query);
  }

  /**
   * @param string[]|UnitEnum[] $Fields
   * @throws InvalidArgumentException
   */
  public function Unique(
    array $Fields
  ):self{
    foreach($Fields as &$field):
      if($field instanceof UnitEnum):
        $field = $field->value ?? $field->name;
      endif;
      if(isset($this->Fields[$field]) === false):
        throw new InvalidArgumentException('Field ' . $field . ' not found');
      endif;
    endforeach;
    $this->Unique[] = $Fields;
    return $this;
  }
}