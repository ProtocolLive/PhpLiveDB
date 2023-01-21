<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb
//2023.01.21.00

namespace ProtocolLive\PhpLiveDb;
use Closure;
use Exception;
use PDO;
use PDOException;
use PDOStatement;

abstract class Basics{
  protected string $Table;
  protected string|null $Prefix = null;
  protected PDO $Conn;
  protected array $Binds = [];
  protected string|null $Query = null;
  protected array $WheresControl = [];
  protected string|Closure|null $OnRun = null;
  protected Drivers $Driver = Drivers::MySql;
  public string|null $Error = null;
  public string|null $Database = null;

  /**
   * Start a transaction
   */
  public function Begin():void{
    $this->Conn->beginTransaction();
  }

  protected function Bind(
    PDOStatement $Statement,
    array $Fields,
    bool $HtmlSafe = true,
    bool $TrimValues = true
  ):void{
    foreach($Fields as $field):
      if($field->Value !== null
      and $field->Type !== null
      and $field->Type !== Types::Null
      and $field->Type !== Types::Sql
      and $field->Operator !== Operators::In
      and $field->Operator !== Operators::NotIn
      and $field->Operator !== Operators::IsNotNull
      and ($field->NoBind ?? false) === false):
        $value = $this->ValueFunctions($field->Value, $HtmlSafe, $TrimValues);
        $Statement->bindValue(
          $field->CustomPlaceholder ?? $field->Name,
          $value,
          $field->Type->value
        );
        $this->Binds[] = [
          $field->CustomPlaceholder ?? $field->Name,
          $value,
          $field->Type
        ];
      endif;
    endforeach;
  }

  protected function BuildWhere(array $Wheres):void{
    /**
     * @var Field[] $Wheres
     */
    //Wipe the NoField to not create problem with index 0
    $WheresTemp = $Wheres;
    foreach($WheresTemp as $id => $where):
      if($where->NoField):
        unset($WheresTemp[$id]);
      endif;
    endforeach;
    $Wheres = array_values($WheresTemp);

    if(count($Wheres) > 0):
      $this->Query .= ' where ';
      $i = 0;
      /**
       * @var DbWhere $where
       */
      foreach($Wheres as $where):
        if($i > 0):
          if($where->AndOr === AndOr::And):
            $this->Query .= ' and ';
          elseif($where->AndOr === AndOr::Or):
            $this->Query .= ' or ';
          endif;
        endif;
        $i++;
        if($where->Parenthesis === Parenthesis::Open):
          $this->Query .= '(';
        endif;
        if($where->Operator === Operators::IsNotNull):
          $this->Query .= $where->Name . ' is not null';
        elseif($where->Operator === Operators::In):
          $this->Query .= $where->Name . ' in(' . $where->Value . ')';
        elseif($where->Operator === Operators::NotIn):
          $this->Query .= $where->Name . ' not in(' . $where->Value . ')';
        elseif($where->NoBind === false
        and(
          $where->Value === null
          or $where->Type === Types::Null
        )):
          $this->Query .= $where->Name . ' is null';
        else:
          $this->Query .= $where->Name . $where->Operator->value;
          if($where->Type === Types::Sql):
            $this->Query .= $where->Value;
          else:
            $this->Query .= ':' . ($where->CustomPlaceholder ?? $where->Name);
          endif;
        endif;
        if($where->Parenthesis === Parenthesis::Close):
          $this->Query .= ')';
        endif;
      endforeach;
    endif;
  }

  /**
   * Commit the transactions created since last Basics::Begin
   */
  public function Commit():void{
    $this->Conn->commit();
  }

  /**
   * Get the duration of the last query executed
   */
  public function Duration():float{
    $temp = $this->Conn->query('show profiles');
    $temp = $temp->fetchAll();
    return $temp[0]['Duration'];
  }

  protected function FieldNeedCustomPlaceholder(string $Field):void{
    if(strpos($Field, '.') !== false
    or strpos($Field, '(') !== false):
      throw new PDOException(
        'The field ' . $Field . ' need a custom placeholder',
      );
    endif;
  }

  /**
   * @return string The final query
   */
  protected function LogAndDebug(
    PDOStatement $Statement,
    bool $Debug = false,
    bool $Log = false,
    int $LogEvent = null,
    int $LogUser = null
  ):string{
    ob_start();
    $Statement->debugDumpParams();
    debug_print_backtrace();
    $Dump = ob_get_contents();
    ob_end_clean();
    if($Debug):
      if(ini_get('html_errors') == true):
        print '<pre style="text-align:left">';
      endif;
      echo htmlspecialchars($Dump);
      error_log($Dump);
      if(ini_get('html_errors') == true):
        print '</pre>';
      endif;
    endif;
    if($Log):
      $this->LogSet($LogEvent, $LogUser, $Dump);
    endif;
    return $Dump;
  }

  protected function LogSet(
    int $LogEvent,
    int|null $User,
    string $Query
  ):void{
    $Query = substr($Query, strpos($Query, 'Sent SQL: ['));
    $Query = substr($Query, strpos($Query, '] ') + 2);
    $Query = substr($Query, 0, strpos($Query, 'Params: '));
    $Query = trim($Query);

    $statement = $this->Conn->prepare('
      insert into sys_logs(time,log,user_id,agent,ip,query)
      values(:time,:log,:user,:agent,:ip,:query)
    ');
    $statement->bindValue('time', time(), PDO::PARAM_INT);
    $statement->bindValue('log', $LogEvent, PDO::PARAM_INT);
    $statement->bindValue('user', $User, PDO::PARAM_INT);
    $statement->bindValue('agent', $_SERVER['HTTP_USER_AGENT'], PDO::PARAM_STR);
    $statement->bindValue('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $statement->bindValue('query', $Query, PDO::PARAM_STR);
    $statement->execute();
  }

  public static function Reserved(string $Field):string{
    $names = ['order', 'default', 'group'];
    if(in_array($Field, $names)):
      $Field = '`' . $Field . '`';
    endif;
    return $Field;
  }

  /**
   * Drop the transactions created since last Basics::Begin
   */
  public function Rollback():void{
    $this->Conn->rollBack();
  }

  /**
   * Run a callable function. The begin command will be executed before, and a commit command will be executed later. If an exception occurs, a rollback is executed.
   * @param callable $Code A callable function to be executed
   * @param bool $RunDefinedExceptionHandler If the custom (or default) exception handler must be executed if an exception occurs inside the callable function.
   * @return mixed Returns the value returned by the callable function or the PDOException occurred
   */
  public function Transaction(
    callable $Code,
    bool $RunDefinedExceptionHandler = false
  ):mixed{
    try{
      self::Begin();
      $return = $Code();
      self::Commit();
      return $return;
    }catch(PDOException $e){
      self::Rollback();
      if($RunDefinedExceptionHandler):
        $handler = set_exception_handler(null);
        $handler($e);
        set_exception_handler($handler);
      else:
        return $e;
      endif;
    }
  }

  protected function ValueFunctions(
    string $Value,
    bool $HtmlSafe,
    bool $TrimValues
  ):string{
    if($HtmlSafe):
      $Value = htmlspecialchars($Value);
    endif;
    if($TrimValues):
      $Value = trim($Value);
    endif;
    return $Value;
  }

  /**
   * @throws Exception
   */
  protected function WheresControl(
    bool $ThrowError,
    string $Field,
    Types $Type = null,
    Operators $Operator,
    bool $NoField,
    bool $NoBind
  ):bool{
    if($NoField === false
    and $NoBind === false
    and $Type !== Types::Sql
    and $Type !== Types::Null
    and $Operator !== Operators::In
    and $Operator !== Operators::NotIn
    and $Operator !== Operators::IsNotNull):
      //Search separated for performance improvement
      if(array_search($CustomPlaceholder ?? $Field, $this->WheresControl) !== false):
        if($ThrowError):
          throw new Exception(
            'The where condition "' . ($CustomPlaceholder ?? $Field) . '" already added',
          );
        else:
          return false;
        endif;
      endif;
    endif;
    return true;
  }
}