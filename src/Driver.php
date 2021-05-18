<?php
declare(strict_types=1);

namespace Database;

use Medoo\Medoo;

class Driver extends Medoo {

    protected $commands    = []; # 命令集
    protected $options     = []; # config
    protected $option      = []; # config->option
    protected $inTran      = false;
    protected $exception   = [];

    public function __construct(array $options)
    {
        $this->options = $options;
        $this->option  = (isset($options[ 'option' ]) && is_array($options[ 'option' ])) ? $options[ 'option' ] : [];
        $this->commands = (isset($options[ 'command' ]) && is_array($options[ 'command' ])) ? $options[ 'command' ] : [];
        parent::__construct($this->options);
    }

    public function execute($query, ?array $map = []) : bool
    {
        $this->reconnect();
        $statement = @$this->pdo->prepare($query);
        if (!$statement) {
            $this->errorInfo = $this->pdo->errorInfo();
            $this->statement = null;
            return false;
        }
        $this->statement = $statement;

        foreach ($map as $key => $value) {
            $statement->bindValue($key, $value[ 0 ], $value[ 1 ]);
        }

        $execute = $statement->execute();
        $this->errorInfo = $statement->errorInfo();
        if (!$execute) {
            $this->statement = null;
        }
    }

    public function exec($query, $map = [])
    {
        $this->statement = null;
        if ($this->debug_mode) {
            $this->debug_mode = false;
            return $this->generate($query, $map);
        }
        if ($this->logging) {
            $this->logs[] = [$query, $map];
        } else {
            $this->logs = [[$query, $map]];
        }
        try {
            $this->execute($query, $map);
        }catch(\PDOException $exception){
            # 服务端断开时重连一次
            if (Helper::isGoneAwayError($exception)) {
                $this->close();
                try {
                    $this->execute($query, $map);
                } catch (\PDOException $ex) {
                    $this->_error($ex);
                    if($this->_setInTran()){
                        $this->rollback();
                    }
                    return false;
                }
            }
            # 连接错误
            if(Helper::isConnectionError($exception)){
                $this->close();
            }
            $this->_error($exception);
            if($this->_setInTran()){
                $this->rollback();
            }
            return false;
        } finally {
            return $this->statement;
        }
    }

    public function command()
    {
        foreach ($this->commands as $value) {
            $this->pdo->exec($value);
        }
    }

    public function reconnect()
    {
        if(!$this->pdo instanceof \PDO){
            $this->pdo = new \PDO(
                $this->dsn,
                isset($this->options['username']) ? $this->options['username'] : null,
                isset($this->options['password']) ? $this->options['password'] : null,
                $this->option
            );
            $this->command();
        }
    }

    public function close() {
        unset($this->pdo);
    }

    protected function tableQuote($table) : string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/i', $table))
        {
            throw new \InvalidArgumentException("Incorrect table name \"$table\"");
        }

        return '`' . $this->prefix . $table . '`';
    }

    protected function columnQuote($string) : string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+(\.?[a-zA-Z0-9_]+)?$/i', $string))
        {
            throw new \InvalidArgumentException("Incorrect column name \"$string\"");
        }

        if (strpos($string, '.') !== false)
        {
            return '`' . $this->prefix . str_replace('.', '`.`', $string) . '`';
        }

        return '`' . $string . '`';
    }

    public function hasTable($table, bool $like = true)
    {
        $sql = $like ? 'SHOW TABLES LIKE' : 'SHOW TABLES';
        $query = $this->exec("{$sql} '{$this->prefix}{$table}'");
        if (!$query) {
            return false;
        }
        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function beginTransaction() : bool
    {
        if(!$this->_setInTran()){
            try {
                return $this->_beginTransaction();
            } catch (\PDOException $e) {
                # 服务端主动断开时重连一次
                if (Helper::isGoneAwayError($e)) {
                    $this->close();
                    try {
                        return $this->_beginTransaction();
                    }catch (\PDOException $e) {
                        $this->_error($e);
                        return false;
                    }
                }
                # 连接错误
                if(Helper::isConnectionError($e)){
                    $this->close();
                }

                $this->_error($e);
                return false;
            }
        }
        return true;
    }

    public function rollback() : bool
    {
        if (!$this->_setInTran()) {
            $this->_error(new \PDOException('Connection: Db is not in transaction.','-1'));
            return false;
        }
        return $this->_rollback();
    }

    public function commit() : bool
    {
        if (!$this->_setInTran()) {
            $this->_error(new \PDOException('Connection: Db is not in transaction.', '-1'));
            return false;
        }
        return $this->_commit();
    }

    private function _error($exception = null)
    {
        $this->exception = ($exception instanceof \PDOException) ? [
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'info'    => $exception->errorInfo,
            'trace'   => $exception->getTraceAsString()
        ] : [];
    }

    private function _setInTran() : bool
    {
        if(!$this->pdo instanceof \PDO){
            return $this->inTran = false;
        }
        return $this->inTran = $this->pdo->inTransaction();
    }


    private function _beginTransaction() : bool
    {
        $this->reconnect();
        if($res = $this->pdo->beginTransaction()){
            $this->_setInTran();
        }
        return $res;
    }

    private function _rollback() : bool
    {
        if(!$this->pdo instanceof \PDO){
            $this->_error(new \PDOException('Connection: Db not connected.','-2'));
            $this->_setInTran();
            return false;
        }
        if($res = $this->pdo->rollBack()){
            $this->_setInTran();
        }
        return $res;
    }

    private function _commit() : bool
    {
        if(!$this->pdo instanceof \PDO){
            $this->_error(new \PDOException('Connection: Db not connected.','-2'));
            $this->_setInTran();
            return false;
        }
        if($res = $this->pdo->commit()){
            $this->_setInTran();
        }
        return $res;
    }
}