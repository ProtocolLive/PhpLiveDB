<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2022.10.08.00

namespace ProtocolLive\PhpLiveDb;
use \Exception;
use \PDO;

final class PhpLiveDb extends Basics{
  /**
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
    string $Prefix = ''
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
  }

  public function Delete(string $Table):Delete{
    return new Delete(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }
  
  public function GetCustom():PDO{
    return $this->Conn;
  }
  
  public function Insert(string $Table):Insert{
    return new Insert(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function InsertUpdate(string $Table):InsertUpdate{
    return new InsertUpdate(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }

  public function Select(
    string $Table,
    bool $ThrowError = true
  ):Select{
    return new Select(
      $this->Conn,
      $this->Database,
      $Table,
      $this->Prefix,
      $ThrowError
    );
  }

  public function Truncate(string $Table):int|false{
    return $this->Conn->exec('truncate '. $Table);
  }

  public function Update(string $Table):Update{
    return new Update(
      $this->Conn,
      $Table,
      $this->Prefix
    );
  }
}