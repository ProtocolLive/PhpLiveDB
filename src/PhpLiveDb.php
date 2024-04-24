<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use Exception;
use PDO;
use PDOException;
use ProtocolLive\PhpLiveDb\Enums\Drivers;
use UnitEnum;

/**
 * @version 2024.04.24.00
 */
final class PhpLiveDb
extends Basics{
  /**
   * @param callable $OnRun A callable function to be executed after a Run. Arguments: string $Query, array|int $Result, float $Time
   * @throws Exception
   */
  public function __construct(
    string $Ip,
    string $User = null,
    string $Pwd = null,
    string $Db = null,
    Drivers $Driver = Drivers::MySql,
    string $Charset = 'utf8mb4',
    int $TimeOut = 5,
    string $Prefix = null,
    callable $OnRun = null
  ){
    $dsn = $Driver->value . ':';
    if($Driver === Drivers::MySql):
      if(extension_loaded('pdo_mysql') === false):
        throw new Exception('MySQL PDO driver not founded');
      endif;
      $dsn .= 'host=' . $Ip . ';dbname=' . $Db . ';charset=' . $Charset;
    elseif($Driver === Drivers::SqLite):
      if(extension_loaded('pdo_sqlite') === false):
        throw new Exception('SQLite PDO driver not founded');
      endif;
      $dsn .= $Ip;
    endif;
    $this->Conn = new PDO($dsn, $User, $Pwd);
    $this->Conn->setAttribute(PDO::ATTR_TIMEOUT, $TimeOut);
    $this->Conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $this->Conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->Conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if($Driver === Drivers::MySql):
      //Enabling profiling to get duration
      $this->Conn->exec('set profiling_history_size=1;set profiling=1;');
    endif;
    if($Driver === Drivers::SqLite):
      $this->Conn->exec('pragma foreign_keys=on');
    endif;
    $this->Prefix = $Prefix;
    $this->Database = $Db;
    $this->OnRun = $OnRun;
    $this->Driver = $Driver;
  }

  public function Create(
    string|UnitEnum $Table
  ):Create{
    return new Create(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Prefix,
      $this->Driver,
      $this->OnRun
    );
  }

  public function Delete(
    string|UnitEnum $Table
  ):Delete{
    return new Delete(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }
  
  public function GetCustom():PDO{
    return $this->Conn;
  }
  
  public function Insert(
    string|UnitEnum $Table
  ):Insert{
    return new Insert(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }

  public function InsertUpdate(
    string|UnitEnum $Table
  ):InsertUpdate{
    return new InsertUpdate(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }

  public function Lock(
    string|UnitEnum $Table,
    bool $Write = true
  ):void{
    $this->Conn->exec('lock tables ' . $Table->value ?? $Table->name ?? $Table .
      ' ' . ($Write ? 'write' : 'read'));
  }

  public function Select(
    string|UnitEnum $Table,
    bool $ThrowError = true
  ):Select{
    return new Select(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Database,
      $this->Prefix,
      $ThrowError,
      $this->OnRun
    );
  }

  /**
   * @throws PDOException
   */
  public function Truncate(
    string|UnitEnum $Table
  ):int|false{
    $query = 'truncate '. ($Table->value ?? $Table->name ?? $Table);
    $return = $this->Conn->exec($query);
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

  public function Unlock():void{
    $this->Conn->exec('unlock tables');
  }

  public function Update(
    string|UnitEnum $Table
  ):Update{
    return new Update(
      $this->Conn,
      $Table->value ?? $Table->name ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }
}