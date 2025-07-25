<?php
//Protocol Corporation Ltda.
//https://github.com/ProtocolLive/PhpLiveDb

namespace ProtocolLive\PhpLiveDb;
use BackedEnum;
use Closure;
use PDO;
use PDOException;
use PDOStatement;
use ProtocolLive\PhpLiveDb\Enums\{
  AndOr,
  Drivers,
  Operators,
  Parenthesis,
  Types
};

/**
 * @version 2025.07.01.00
 */
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

  /**
   * @param Field[] $Fields
   */
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
      and $field->Operator !== Operators::InNot
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
        if($field->Operator === Operators::Between
        or $field->Operator === Operators::BetweenNot):
          $value = $this->ValueFunctions($field->Value2, $HtmlSafe, $TrimValues);
          $Statement->bindValue(
            ($field->CustomPlaceholder ?? $field->Name) . '2',
            $value,
            $field->Type->value
          );
          $this->Binds[] = [
            ($field->CustomPlaceholder ?? $field->Name) . '2',
            $value,
            $field->Type
          ];
        endif;
      endif;
    endforeach;
  }

  protected function BuildWhere(
    array $Wheres
  ):void{
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
    
    $count = count($Wheres);
    if($count === 0):
      return;
    endif;
    $this->Query .= ' where ';
    for($i = 0; $i < $count; $i++):
      //Complicated logic
      if((
        $i === 0
        or $Wheres[$i - 1]->Name === null
        or (
          $Wheres[$i]->Name === null
          and isset($Wheres[$i + 1]) === false
        )
      ) === false):
        if($Wheres[$i]->AndOr === AndOr::And):
          $this->Query .= ' and ';
        elseif($Wheres[$i]->AndOr === AndOr::Or):
          $this->Query .= ' or ';
        endif;
      endif;

      if($Wheres[$i]->Parenthesis === Parenthesis::Open):
        $this->Query .= '(';
      endif;

      if($Wheres[$i]->Operator === Operators::IsNotNull):
        $this->Query .= self::Reserved($Wheres[$i]->Name) . ' is not null';
      elseif($Wheres[$i]->Operator === Operators::In
      or $Wheres[$i]->Operator === Operators::InNot
      or $Wheres[$i]->Operator === Operators::Exists
      or $Wheres[$i]->Operator === Operators::ExistsNot):
        $this->Query .= self::Reserved($Wheres[$i]->Name) . $Wheres[$i]->Operator->value . '(' . $Wheres[$i]->Value . ')';
      elseif($Wheres[$i]->Operator === Operators::Between
      or $Wheres[$i]->Operator === Operators::BetweenNot):
        $this->Query .= self::Reserved($Wheres[$i]->Name) . $Wheres[$i]->Operator->value;
        $this->Query .= ' :' . ($Wheres[$i]->CustomPlaceholder ?? $Wheres[$i]->Name) . ' and ';
        $this->Query .= ':' . ($Wheres[$i]->CustomPlaceholder ?? $Wheres[$i]->Name) . '2';
      elseif($Wheres[$i]->Operator === Operators::Match
      or $Wheres[$i]->Operator === Operators::MatchBoolean
      or $Wheres[$i]->Operator === Operators::MatchExpansion):
        $this->Query .= ' match(' . self::Reserved($Wheres[$i]->Name) . ')';
        $this->Query .= ' against(:' . ($Wheres[$i]->CustomPlaceholder ?? $Wheres[$i]->Name);
        if($Wheres[$i]->Operator === Operators::MatchBoolean):
          $this->Query .= ' in boolean mode';
        elseif($Wheres[$i]->Operator === Operators::MatchExpansion):
          $this->Query .= ' with query expansion';
        endif;
        $this->Query .= ')';
      elseif($Wheres[$i]->Name !== null):
        if($Wheres[$i]->NoBind === false
        and(
          $Wheres[$i]->Value === null
          or $Wheres[$i]->Type === Types::Null
        )):
          $this->Query .= self::Reserved($Wheres[$i]->Name) . ' is null';
        else:
          $this->Query .= self::Reserved($Wheres[$i]->Name) . $Wheres[$i]->Operator->value;
          if($Wheres[$i]->Type === Types::Sql
          or $Wheres[$i]->NoBind):
            if($Wheres[$i]->CustomPlaceholder !== null):
              $this->Query .= ':' . $Wheres[$i]->CustomPlaceholder;
            else:
              $this->Query .= $Wheres[$i]->Value;
            endif;
          else:
            $this->Query .= ':' . ($Wheres[$i]->CustomPlaceholder ?? $Wheres[$i]->Name);
          endif;
        endif;
      endif;

      if($Wheres[$i]->Parenthesis === Parenthesis::Close):
        $this->Query .= ')';
      endif;
    endfor;
  }

  /**
   * Commit the transactions created since last Basics::Begin
   */
  public function Commit():void{
    $this->Conn->commit();
  }

  public function DebugBinds(
    bool $HtmlSafe = true,
    bool $TrimValues = true,
    bool $ForUpdate = false
  ):array{
    $statement = $this->Prepare($ForUpdate);
    if(count($this->Wheres) > 0):
      $this->Bind($statement, $this->Wheres, $HtmlSafe, $TrimValues);
    endif;
    return $this->Binds;
  }

  /**
   * Get the duration of the last query executed
   */
  public function Duration():float{
    $temp = $this->Conn->query('show profiles');
    $temp = $temp->fetchAll();
    return $temp[0]['Duration'];
  }

  protected function FieldNeedCustomPlaceholder(
    string $Field
  ):void{
    if(str_contains($Field, '.') or str_contains($Field, '(')):
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
    int|BackedEnum|null $LogEvent = null,
    int|null $LogUser = null
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
      if(PHP_SAPI !== 'cli'):
        echo htmlspecialchars($Dump);
      endif;
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
    int|BackedEnum $LogEvent,
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
    $statement->bindValue('log', $LogEvent->value ?? $LogEvent, PDO::PARAM_INT);
    $statement->bindValue('user', $User, PDO::PARAM_INT);
    $statement->bindValue('agent', $_SERVER['HTTP_USER_AGENT'], PDO::PARAM_STR);
    $statement->bindValue('ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
    $statement->bindValue('query', $Query, PDO::PARAM_STR);
    $statement->execute();
  }

  public static function Reserved(
    string $Field
  ):string{
    $names = ['order', 'default', 'group', 'update', 'div'];
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
   * @throws PDOException
   */
  protected function WheresControl(
    bool $ThrowError,
    string $Field,
    Operators $Operator,
    bool $NoField,
    bool $NoBind,
    Types|null $Type = null
  ):bool{
    if($Field !== null
    and $NoField === false
    and $NoBind === false
    and $Type !== Types::Sql
    and $Type !== Types::Null
    and $Operator !== Operators::In
    and $Operator !== Operators::InNot
    and $Operator !== Operators::IsNotNull
    and $Operator !== Operators::Like
    and $Operator !== Operators::LikeNot
    and array_search($Field, $this->WheresControl) !== false):
      if($ThrowError):
        throw new PDOException(
          'The where condition "' . $Field . '" already added',
        );
      else:
        return false;
      endif;
    endif;
    return true;
  }
}