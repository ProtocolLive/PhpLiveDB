<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use Exception;
use PDO;
use PDOException;

/**
 * @version 2023.05.25.00
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
    string|BackedEnum $Table
  ):Create{
    return new Create(
      $this->Conn,
      $Table->value ?? $Table,
      $this->Prefix,
      $this->Driver,
      $this->OnRun
    );
  }

  public function Delete(
    string|BackedEnum $Table
  ):Delete{
    return new Delete(
      $this->Conn,
      $Table->value ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }
  
  public function GetCustom():PDO{
    return $this->Conn;
  }
  
  public function Insert(
    string|BackedEnum $Table
  ):Insert{
    return new Insert(
      $this->Conn,
      $Table->value ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }

  public function InsertUpdate(
    string|BackedEnum $Table
  ):InsertUpdate{
    return new InsertUpdate(
      $this->Conn,
      $Table->value ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }

  public function Select(
    string|BackedEnum $Table,
    bool $ThrowError = true
  ):Select{
    return new Select(
      $this->Conn,
      $this->Database,
      $Table->value ?? $Table,
      $this->Prefix,
      $ThrowError,
      $this->OnRun
    );
  }

  /**
   * @throws PDOException
   */
  public function Truncate(
    string|BackedEnum $Table
  ):int|false{
    $query = 'truncate '. ($Table->value ?? $Table);
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

  public function Update(
    string|BackedEnum $Table
  ):Update{
    return new Update(
      $this->Conn,
      $Table->value ?? $Table,
      $this->Prefix,
      $this->OnRun
    );
  }
}