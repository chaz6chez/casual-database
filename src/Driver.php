<?php
declare(strict_types=1);

namespace Database;

use Database\Exception\ExpireException;
use Database\Exception\TransactionException;
use Medoo\Medoo;
use Psr\Log\LoggerInterface;

class Driver extends Medoo {

    protected $commands    = []; # 命令集
    protected $options     = []; # config
    protected $option      = []; # config->option
    protected $inTran      = false;
    protected $exception   = [];
    protected $expireTimestamp = 0;
    protected $logger;

    public function __construct(array $options, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->options = $options;
        $this->option  = (isset($options[ 'option' ]) && is_array($options[ 'option' ])) ? $options[ 'option' ] : [];
        $this->commands = (isset($options[ 'command' ]) && is_array($options[ 'command' ])) ? $options[ 'command' ] : [];
        parent::__construct($this->options);
    }

    public function setExpireTimestamp(?int $timestamp = null) : void
    {
        $this->expireTimestamp = $timestamp === null ? 0 : $timestamp;
    }

    public function getExpireTimestamp() : int
    {
        return $this->expireTimestamp;
    }

    public function execute($query, ?array $map = []) : void
    {
        $this->reconnect();
        $expire = $this->getExpireTimestamp();
        if($expire > 0 and time() > $expire){
            $this->setExpireTimestamp(0);
            if($this->_setInTran()){
                $this->rollback();
            }
            throw new ExpireException('Transaction has expired.','-303');
        }
        $statement = @$this->pdo->prepare($query);
        if (!$statement) {
            $this->errorInfo = $this->pdo->errorInfo();
            $this->statement = null;
            return;
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
                } catch (ExpireException $exception){
                    throw $exception;
                }
            }
            # 连接错误
            if(Helper::isConnectionError($exception)){
                $this->close(true);
            }
            $this->_error($exception);
            if($this->_setInTran()){
                $this->rollback();
            }
            return false;
        } catch (ExpireException $exception){
            throw $exception;
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

    public function close(bool $reset = false) {
        if($reset){
            $this->setExpireTimestamp(0);
        }
        unset($this->pdo);
    }

    protected function tableQuote($table) : string
    {
        if ($table === null)
        {
            throw new \InvalidArgumentException("Incorrect table name \"null\"");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/i', $table))
        {
            throw new \InvalidArgumentException("Incorrect table name \"$table\"");
        }

        return '`' . $this->prefix . $table . '`';
    }

    protected function columnQuote($string) : string
    {
        if ($string === null)
        {
            throw new \InvalidArgumentException("Incorrect column name \"null\"");
        }
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

    public function beginTransaction(?int $expire = null) : bool
    {
        if(!$this->_setInTran()){
            try {
                return $this->_beginTransaction($expire);
            } catch (\PDOException $e) {
                if (Helper::isGoneAwayError($e)) {
                    $this->close();
                    try {
                        return $this->_beginTransaction($expire);
                    }catch (\PDOException $e) {
                        $this->_error($e);
                        return false;
                    }
                }
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
            $this->setExpireTimestamp(0);
            $this->_error(new TransactionException('Not in transaction.','-404'));
            return false;
        }
        return $this->_rollback();
    }

    public function commit() : bool
    {
        if (!$this->_setInTran()) {
            $this->setExpireTimestamp(0);
            $this->_error(new TransactionException('Not in transaction.','-404'));
            return false;
        }
        return $this->_commit();
    }

    private function _error($exception = null)
    {
        if($this->logger and $exception){
            $this->logger->error('Database error.', (array)$exception);
        }
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

    private function _beginTransaction(?int $expire = null) : bool
    {
        $this->reconnect();
        if($res = $this->pdo->beginTransaction()){
            $this->setExpireTimestamp($expire);
            $this->_setInTran();
        }
        return $res;
    }

    private function _rollback() : bool
    {
        if(!$this->pdo instanceof \PDO){
            $this->_error(new \PDOException('Database is not connected.','-1'));
            $this->_setInTran();
            return false;
        }
        if($res = $this->pdo->rollBack()){
            $this->setExpireTimestamp(0);
            $this->_setInTran();
        }
        return $res;
    }

    private function _commit() : bool
    {
        if(!$this->pdo instanceof \PDO){
            $this->_error(new \PDOException('Database is not connected.','-1'));
            $this->_setInTran();
            return false;
        }
        if($res = $this->pdo->commit()){
            $this->setExpireTimestamp(0);
            $this->_setInTran();
        }
        return $res;
    }
}