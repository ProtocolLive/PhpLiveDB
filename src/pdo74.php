<?php
// Protocol Corporation Ltda.
// https://github.com/ProtocolLive/PhpLive/
// Version 2021.03.26.00

define('PdoStr', PDO::PARAM_STR);
define('PdoInt', PDO::PARAM_INT);
define('PdoNull', PDO::PARAM_NULL);
define('PdoSql', 6);

class PhpLivePdo{
  private PDO $Conn;
  private string $Prefix = '';
  private float $Duration = 0;
  private bool $UpdateInsertFlag = false;
  private array $Error = [];

  /**
   * @param string $Drive ($Options)(Optional) MySql as default
   * @param string $Ip ($Options)
   * @param string $User ($Options)
   * @param string $Pwd ($Options)
   * @param string $Db ($Options)
   * @param string $Prefix ($Options)(Optional) Change ## for the tables prefix 
   * @param string $Charset ($Options)(Optional) UTF8 as default
   * @param int $TimeOut ($Options)(Optional) Connection timeout
   * @return object
   */
  public function __construct(array $Options){
    $Options['Drive'] ??= 'mysql';
    $Options['Charset'] ??= 'utf8';
    $Options['TimeOut'] ??= 5;
    $this->Prefix = $Options['Prefix'] ?? '';
    $this->Conn = new PDO(
      $Options['Drive'] . ':host=' . $Options['Ip'] . ';dbname=' . $Options['Db'] . ';charset=' . $Options['Charset'],
      $Options['User'],
      $Options['Pwd']
    );
    $this->Conn->setAttribute(PDO::ATTR_TIMEOUT, $Options['TimeOut']);
    $this->Conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    //Enabling profiling to get duration of querys
    $result = $this->Conn->prepare('set profiling_history_size=1;set profiling=1;');
    $result->execute();
    $error = $result->errorInfo();
    if($error[0] !== '00000'):
      $this->ErrorSet($error[0], $error[2]);
    endif;
  }

  /**
   * @param string $Query
   * @param array $Params
   * @param int Log ($Options)(Optional) Event to be logged
   * @param int User ($Options)(Optional) User executing the query
   * @param int Target ($Options)(Optional) User efected
   * @param bool Debug ($Options)(Optional) Dump the query for debug
   * @param bool Safe ($Options)(Optional) Only runs a safe query
   * @return array|int|bool
   */
  public function Run(string $Query, array $Params = [], array $Options = []){
    if($this->Conn === null):
      return false;
    endif;
    $Options['Log'] ??= null;
    $Options['User'] ??= null;
    $Options['Target'] ??= null;
    $Options['Safe'] ??= true;
    $Options['Debug'] ??= false;

    set_error_handler([$this, 'ErrorSet']);
    if($Options['Debug'] == true):
      print '<pre style="text-align:left">';
        debug_print_backtrace();
      print '</pre>';
    endif;
    $command = explode(' ', trim($Query));
    $command = strtolower(trim($command[0]));
    //Search from PdoSql and parse
    foreach($Params as $id => $param):
      if($param[2] === PdoSql):
        if(is_numeric($param[0])):
          $out = 0;
          for($i = 1; $i <= $param[0]; $i++):
            $in = strpos($Query, '?', $out);
            $out = $in + 1;
          endfor;
          $Query = substr_replace($Query, $param[1], $in, 1);
          unset($Params[$id]);
          //Reorder tokens
          $count = count($Params);
          for($i = 0; $i < $count; $i++):
            $Params[$i][0] = $i + 1;
          endfor;
        else:
          $in = strpos($Query, $param[0]);
          $out = strpos($Query, ',', $in);
          if($out === false):
            $out = strpos($Query, ')', $in);
            if($out === false):
              $out = strpos($Query, ' ', $in);
            endif;
          endif;
          $Query = substr_replace($Query, $param[1], $in, $out - $in);
          unset($Params[$id]);
        endif;
      endif;
    endforeach;
    //Table prefix
    if($this->Prefix !== ''):
      $Query = str_replace('##', $this->Prefix . '_', $Query);
    else:
      $Query = str_replace('##', '', $Query);
    endif;
    //Prepare
    $result = $this->Conn->prepare($Query);
    //Bind tokens
    if($Params !== null):
      foreach($Params as &$Param):
        if(count($Param) < 3 and count($Param) > 5):
          $this->ErrorSet(1, 'Incorrect number of parameters when specifying a token');
        else:
          if($Param[2] === PdoInt):
            $Param[1] = str_replace(',', '.', $Param[1]);
            if(strpos($Param[1], '.') !== false):
              $Param[2] = PdoStr;
            endif;
          endif;
          $result->bindValue($Param[0], $Param[1], $Param[2]);
        endif;
      endforeach;
    endif;
    //Safe execution
    if($Options['Safe'] === true):
      if($command === 'truncate' or (($command === 'update' or $command === 'delete') and strpos($Query, 'where') === false)):
        $this->ErrorSet(2, 'Query not allowed in safe mode');
      endif;
    endif;
    //Execute
    $result->execute();
    //Error
    $error = $result->errorInfo();
    if($error[0] !== '00000'):
      $this->ErrorSet($error[0], $error[2]);
    endif;
    //Debug
    if($Options['Debug'] == true):
      print '<pre style="text-align:left">';
        $result->debugDumpParams();
      print '</pre>';
    endif;
    //Return
    if($command === 'select' or $command === 'show' or $command === 'call'):
      $return = $result->fetchAll();
    elseif($command === 'insert'):
      $return = $this->Conn->lastInsertId();
    elseif($command === 'update' or $command === 'delete'):
      $return = $result->rowCount();
    else:
      $return = true;
    endif;
    //Duration
    $profiles = $this->Conn->prepare('show profiles');
    $profiles->execute();
    $profiles = $profiles->fetchAll();
    $this->Duration = $profiles[0]['Duration'];
    //Log
    if($Options['Log'] !== null and $Options['User'] !== null):
      ob_start();
      $result->debugDumpParams();
      $dump = ob_get_contents();
      ob_end_clean();
      $dump = substr($dump, strpos($dump, 'Sent SQL: ['));
      $dump = substr($dump, strpos($dump, '] ') + 2);
      $dump = substr($dump, 0, strpos($dump, 'Params: '));
      $dump = trim($dump);
      $this->SqlLog([
        'User' => $Options['User'],
        'Dump' => $dump,
        'Log' => $Options['Log'],
        'Target' => $Options['Target']
      ]);
    endif;
    restore_error_handler();
    return $return;
  }

  /**
   * @param string Table
   * @param array Fields
   * @return int
   */
  public function Insert(array $Options, array $Options2 = []):int{
    $return = 'insert into ' . $Options['Table'] . '(';
    $tokens = [];
    foreach($Options['Fields'] as $field):
      $return .= $this->Reserved($field[0]) . ',';
      $tokens[] = [':' . $field[0], $field[1], $field[2]];
    endforeach;
    $return = substr($return, 0, -1);
    $return .= ') values(';
    foreach($Options['Fields'] as $field):
      $return .= ':' . $field[0] . ',';
    endforeach;
    $return = substr($return, 0, -1);
    $return .= ');';
    return $this->Run($return, $tokens, $Options2);
  }

  /**
   * Update only the diferents fields
   * @param string Table ($Options)
   * @param array Fields ($Options)
   * @param array Where ($Options)
   * @return int
   */
  public function Update(array $Options, array $Options2 = []):int{
    $query = '';
    $temp = $this->BuildWhere($Options['Where']);
    //Prepare fields list
    foreach($Options["Fields"] as $field):
      $query .= $this->Reserved($field[0]) . ',';
    endforeach;
    //Check if the entry exists
    $query = 'select ' . substr($query, 0, -1) . ' from ' . $Options['Table'] . ' where ' . $temp['Query'];
    $data = $this->Run($query, $temp['Tokens']);
    if(count($data) === 0):
      //Entry not found
      $this->UpdateInsertFlag = true;
      return 0;
    else:
      $data = $data[0];
      //Check fields for differences
      foreach($Options['Fields'] as $id => $field):
        if($field[1] === $data[$field[0]]):
          unset($Options['Fields'][$id]);
        endif;
      endforeach;
      if(count($Options['Fields']) === 0):
        //None different field
        return 0;
      else:
        $temp = $this->BuildUpdate($Options);
        return $this->Run($temp['Query'], $temp['Tokens'], $Options2);
      endif;
    endif;
  }

  /**
   * Update a row, or insert if not exist
   * @param string Table ($Options)
   * @param array Fields ($Options)
   * @param array Where ($Options)
   * @return int
   */
  public function UpdateInsert(array $Options, array $Options2 = []):int{
    $return = $this->Update([
      'Table' => $Options['Table'],
      'Fields' => $Options['Fields'],
      'Where' => $Options['Where']
    ], $Options2);
    if($this->UpdateInsertFlag === false):
      return $return;
    else:
      $this->UpdateInsertFlag = false;
      return $this->Insert([
        'Table' => $Options['Table'],
        'Fields' => array_merge($Options['Fields'], $Options['Where'])
      ], $Options2);
    endif;
  }

  /**
   * @return array
   */
  public function ErrorGet():array{
    return $this->Error;
  }

    /**
   * @return float
   */
  public function Duration():float{
    return $this->Duration;
  }

  /**
   * @param string Field
   * @return string
   */
  public function Reserved(string $Field):string{
    $names = ['order', 'default', 'group'];
    if(in_array($Field, $names)):
      $Field = '`' . $Field . '`';
    endif;
    return $Field;
  }

  public function ErrorSet(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null, ?array $errcontext = null):bool{
    $this->Error = [$errno, $errstr];
    $folder = __DIR__ . '/errors-pdo/';
    if(is_dir($folder) === false):
      mkdir($folder, 0755);
    endif;
    file_put_contents($folder . date('Y-m-d_H-i-s') . '.txt', json_encode(debug_backtrace(), JSON_PRETTY_PRINT));
    if(ini_get('display_errors')):
      print '<pre style="text-align:left">';
      debug_print_backtrace();
      die();
    endif;
    return true;
  }

  private function BuildUpdate(array $Options):array{
    $return = ['Query' => '', 'Tokens' => []];
    $return['Query'] = 'update ' . $Options['Table'] . ' set ';
    foreach($Options['Fields'] as $field):
      $return['Query'] .= $this->Reserved($field[0]) . '=:' . $field[0] . ',';
      $return['Tokens'][] = [':' . $field[0], $field[1], $field[2]];
    endforeach;
    $return['Query'] = substr($return['Query'], 0, -1);
    $temp = $this->BuildWhere($Options['Where']);
    $return['Query'] .= ' where ' . $temp['Query'];
    $return['Tokens'] = array_merge($return['Tokens'], $temp['Tokens']);
    return $return;
  }

  private function BuildWhere(array $Wheres, int $Count = 1):array{
    // 0 field, 1 value, 2 type, 3 operator, 4 condition
    $return = ['Query' => '', 'Tokens' => []];
    foreach($Wheres as $id => $where):
      $where[3] ??= '=';
      $where[4] ??= 'and';
      if($where[3] === 'is' or $where[3] === 'is not'):
        $where[3] = ' ' . $where[3] . ' ';
      endif;
      if($id === 0):
        $return['Query'] = $this->Reserved($where[0]) . $where[3] . ':' . $where[0];
      else:
        $return['Query'] .= ' ' . $where[4] . ' ' . $where[0] . $where[3] . ':' . $where[0];
      endif;
      $return['Tokens'][] = [':' . $where[0], $where[1], $where[2]];
    endforeach;
    return $return;
  }

  private function SqlLog(array $Options):int{
    if(isset($_SERVER['HTTP_USER_AGENT'])):
      $Agent = $_SERVER['HTTP_USER_AGENT'];
    else:
      $Agent = '';
    endif;
    return $this->Insert([
      'Table' => 'sys_logs',
      'Fields' => [
        ['time', date('Y-m-d H:i:s'), PdoStr],
        ['site', $this->Prefix, $this->Prefix === ''? PdoNull: PdoStr],
        ['user_id', $Options['User'], PdoInt],
        ['log', $Options['Log'], PdoInt],
        ['ip', $_SERVER['REMOTE_ADDR'], PdoStr],
        ['ipreverse', gethostbyaddr($_SERVER['REMOTE_ADDR']), PdoStr],
        ['agent', $Agent, $Agent === ''? PdoNull: PdoStr],
        ['query', $Options['Dump'], PdoStr],
        ['target', $Options['Target'], $Options['Target'] === null? PdoNull: PdoInt]
      ]
    ]);
  }
}