<?php
declare(strict_types=1);

namespace Database;

use Database\Drivers\Mysql;
use Database\Drivers\Odbc;
use Database\Drivers\Pgsql;
use Database\Drivers\Sqlite;
use Database\Exceptions\DatabaseException;
use Database\Exceptions\DatabaseInvalidArgumentException;
use Database\Exceptions\TransactionException;
use Database\Protocols\DriverInterface;
use Database\Tools\Options;
use Database\Tools\Raw;
use Database\Tools\StateConstant;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Driver {
    protected static $_factory = [
        'mariadb' => Mysql::class,
        'mysql'   => Mysql::class,
        'pgsql'   => Pgsql::class,
        'odbc'    => Odbc::class,
        'sqlite'  => Sqlite::class
    ];

    protected $_logger;
    protected $_options;
    protected $_driver;
    protected $_dsn;
    protected $_pdo;

    protected $_statement;
    protected $_expire_timestamp = 0;
    protected $_count = 0;

    protected $_logs = [];
    protected $_guid = 0;

    protected $_error;
    protected $_sqlstate;
    protected $_driver_code;
    protected $_driver_message;

    /**
     * @var null|callable
     */
    public $onBeforePrepare = null;
    /**
     * @var null|callable
     */
    public $onBeforeBind = null;
    /**
     * @var null|callable
     */
    public $onBeforeExec = null;
    /**
     * @var null|callable
     */
    public $onAfterExec = null;

    public $queryString;

    /**
     * Driver constructor.
     * @param array $options
     * @param LoggerInterface|null $logger
     * @throws DatabaseException
     */
    public function __construct(array $options, ?LoggerInterface $logger = null)
    {
        $this->_options = new Options($options);
        $this->_logger = $logger;
        try {
            $this->_driver = self::factory($this->options()->driver);
            $this->_driver->construct($this);
            $this->reconnect();
        } catch (DatabaseException $e){
            $msg = 'Database DatabaseException.';
        } catch (PDOException $e) {
            $msg = 'Database PDOException.';
        } finally {
            if(isset($e)){
                if($this->_logger){
                    $this->_logger->error(isset($msg) ? $msg : $e->getMessage(),[
                        $e->getMessage(),
                        $e->getCode(),
                        $e->getTrace(),
                        $e
                    ]);
                }
                throw $e;
            }
        }
    }

    public function __destruct()
    {
        $this->_driver->destruct($this);
    }

    /**
     * @return LoggerInterface|null
     */
    public function logger(): ?LoggerInterface
    {
        return $this->_logger;
    }

    /**
     * @throws PDOException
     */
    public function reconnect() : void
    {
        if($this->isDebug()){
            return;
        }
        if(!$this->_pdo instanceof PDO){
            $this->_pdo = new PDO(
                $this->getDsn(),
                $this->options()->username,
                $this->options()->password,
                $this->options()->option
            );

            if ($this->options()->error) {
                $this->pdo()->setAttribute(
                    PDO::ATTR_ERRMODE,
                    in_array($this->options()->error, [
                        PDO::ERRMODE_SILENT,
                        PDO::ERRMODE_WARNING,
                        PDO::ERRMODE_EXCEPTION
                    ]) ?
                        $this->options()->error :
                        PDO::ERRMODE_SILENT
                );
            }

            foreach ($this->options()->command as $value) {
                $this->pdo()->exec($value);
            }
        }
    }

    /**
     * @return array|null
     */
    public function error() : ?array
    {
        return $this->_error;
    }

    /**
     * 设置过期时间戳
     * @param int|null $timestamp
     */
    public function setExpireTimestamp(?int $timestamp = null) : void
    {
        $this->_expire_timestamp = $timestamp === null ? 0 : $timestamp;
    }

    /**
     * 获取过期时间戳
     * @return int
     */
    public function getExpireTimestamp() : int
    {
        return $this->_expire_timestamp;
    }

    /**
     * 关闭连接
     * @param bool $reset
     */
    public function close(bool $reset = false) {
        if($reset){
            $this->setExpireTimestamp(0);
        }
        $this->_driver->destruct($this);
        $this->_pdo = null;
    }

    /**
     * @param string $dsn
     */
    public function setDsn(string $dsn){
        $this->_dsn = $dsn;
    }

    /**
     * @return string
     */
    public function getDsn() : string
    {
        return $this->_dsn;
    }

    public function isDebug(): bool
    {
        return $this->options()->debug;
    }

    /**
     * @return $this
     */
    public function debug() : Driver
    {
        $this->_options->debug = true;
        return $this;
    }

    /**
     * 获取PDO实例
     * @return PDO|null
     */
    public function pdo() : ?PDO
    {
        return $this->_pdo;
    }

    /**
     * 获取配置信息
     * @return Options|null
     */
    public function options() : ?Options
    {
        return $this->_options;
    }

    /**
     * 注册
     * @param string $driver
     * @param string $class
     * @return bool
     */
    public static function register(string $driver, string $class) : bool
    {
        if(class_exists($class,false)){
            if((new $class) instanceof DriverInterface){
                self::$_factory[$driver] = $class;
                return true;
            }
        }
        return false;
    }

    /**
     * 工厂入口
     * @param string $driver
     * @return DriverInterface
     * @throws DatabaseInvalidArgumentException
     */
    public static function factory(string $driver) : DriverInterface
    {
        if(empty(self::$_factory[$driver])){
            throw new DatabaseInvalidArgumentException("Unregistered {$driver}.");
        }
        if (!in_array($driver, PDO::getAvailableDrivers())) {
            throw new DatabaseInvalidArgumentException("Unsupported pdo_{$driver}.");
        }
        return (new self::$_factory[$driver]);
    }

    /**
     * @param string $string
     * @param array $map
     * @return Raw
     */
    public static function raw(string $string, array $map = []): Raw
    {
        $raw = new Raw();
        $raw->map = $map;
        $raw->value = $string;
        return $raw;
    }

    /**
     * Execute customized raw statement.
     *
     * @param string $statement The raw SQL statement.
     * @param array $map The array of input parameters value for prepared statement.
     * @return PDOStatement|null
     */
    public function query(string $statement, array $map = []): ?PDOStatement
    {
        $raw = $this->raw($statement, $map);
        $statement = $this->_buildRaw($raw, $map);
        return $this->exec($statement, $map);
    }

    /**
     * Execute the raw statement.
     *
     * @param string $statement The SQL statement.
     * @param array $map The array of input parameters value for prepared statement.
     * @param callable|null $callback
     * @return PDOStatement|null
     */
    public function exec(string $statement, array $map = [], ?callable $callback = null): ?PDOStatement
    {
        try {
            $this->_clean();
            $this->reconnect();
            if ($this->isDebug()){
                $this->queryString = $this->_generate($statement, $map);
                return null;
            }
            $this->_logs = [[$statement, $map]];
            if(is_callable($this->onBeforePrepare)){
                ($this->onBeforePrepare)($this);
            }
            $this->_statement = $this->pdo()->prepare($statement);
            [
                $this->_sqlstate,
                $this->_driver_code,
                $this->_driver_message
            ] = $this->_error = $this->pdo()->errorInfo();
            if(!StateConstant::isSuccess($this->_driver->recognizer($this->_sqlstate))){
                return null;
            }
            if(is_callable($this->onBeforeBind)){
                ($this->onBeforeBind)($this);
            }
            foreach ($map as $key => $value) {
                $this->_statement->bindValue($key, $value[0], $value[1]);
            }
            if(is_callable($this->onBeforeExec)){
                ($this->onBeforeExec)($this);
            }
            if(!$this->_statement->execute()){
                goto exec_crash;
            }
            $this->_count = 0;
            return $this->_statement;
        }catch (PDOException $exception){
            exec_crash:
            $this->_count++;
            $this->_error = isset($exception) ? $exception->errorInfo : $this->pdo()->errorInfo();
            [
                $this->_sqlstate,
                $this->_driver_code,
                $this->_driver_message
            ] = $this->_error;
            switch ($state = $this->_driver->recognizer($this->_sqlstate)) {
                case StateConstant::isReconnection($state):
                    if($this->_count <= 3){
                        $this->close();
                        usleep(500);
                        return $this->exec($statement, $map, $callback);
                    }
                    break;
                case StateConstant::isInterrupt($state):
                case StateConstant::isError($state):
                default:
                    $this->_count = 0;
                    $this->_statement = null;
                    break;
            }
            return $this->_statement;
        } finally {
            if($this->_logger){
                if($error = $this->error()){
                    $this->_logger->error('Database Execute Error.',$this->error());
                }else{
                    $this->_logger->debug('Database Execute Success.', [$this->last()]);
                }
            }
            if(is_callable($this->onAfterExec)){
                ($this->onAfterExec)($this);
            }
        }
    }

    /**
     * @param $raw
     * @return bool
     */
    public function isRaw($raw) : bool
    {
        if(is_object($raw) && $raw instanceof Raw){
            return true;
        }
        return false;
    }

    /**
     * Quote a string for use in a query.
     *
     * @param string $string
     * @return string
     */
    public function quote(string $string): string
    {
        if ($this->options()->driver === 'mysql') {
            return "'" . preg_replace(['/([\'"])/', '/(\\\\\\\")/'], ["\\\\\${1}", '\\\${1}'], $string) . "'";
        }

        return "'" . preg_replace('/\'/', '\'\'', $string) . "'";
    }

    /**
     * Quote table name for use in a query.
     *
     * @param string $table
     * @return string
     * @throws DatabaseInvalidArgumentException
     */
    public function tableQuote(string $table): string
    {
        if (preg_match('/^[\p{L}_][\p{L}\p{N}@$#\-_]*$/u', $table)) {
            return '`' . $this->options()->prefix . $table . '`';
        }
        throw new DatabaseInvalidArgumentException("Incorrect Table Name: {$table}.");
    }

    /**
     * Quote column name for use in a query.
     *
     * @param string $column
     * @return string
     * @throws DatabaseInvalidArgumentException
     */
    public function columnQuote(string $column): string
    {
        if (preg_match('/^[\p{L}_][\p{L}\p{N}@$#\-_]*(\.?[\p{L}_][\p{L}\p{N}@$#\-_]*)?$/u', $column)) {
            return strpos($column, '.') !== false ?
                '`' . $this->options()->prefix . str_replace('.', '`.`', $column) . '`' :
                '`' . $column . '`';
        }
        throw new DatabaseInvalidArgumentException("Incorrect column name: {$column}.");
    }

    /**
     * Create a table.
     * @param string $table
     * @param $columns [Columns definition.]
     * @param array $options Additional table options for creating a table.
     * @return PDOStatement|null
     */
    public function create(string $table, $columns, $options = null): ?PDOStatement
    {
        $stack = [];
        $tableOption = '';
        $tableName = $this->tableQuote($table);

        foreach ($columns as $name => $definition) {
            if (is_int($name)) {
                $stack[] = preg_replace('/\<([\p{L}_][\p{L}\p{N}@$#\-_]*)\>/u', '"$1"', $definition);
            } elseif (is_array($definition)) {
                $stack[] = $this->columnQuote($name) . ' ' . implode(' ', $definition);
            } elseif (is_string($definition)) {
                $stack[] = $this->columnQuote($name) . ' ' . $definition;
            }
        }

        if (is_array($options)) {
            $optionStack = [];

            foreach ($options as $key => $value) {
                if (is_string($value) || is_int($value)) {
                    $optionStack[] = "{$key} = {$value}";
                }
            }

            $tableOption = ' ' . implode(', ', $optionStack);
        } elseif (is_string($options)) {
            $tableOption = ' ' . $options;
        }

        $command = 'CREATE TABLE';

        if (in_array($this->options()->driver, ['mysql', 'pgsql', 'sqlite', 'odbc'])) {
            $command .= ' IF NOT EXISTS';
        }

        return $this->exec("{$command} {$tableName} (" . implode(', ', $stack) . "){$tableOption}");
    }

    /**
     * Drop a table.
     * @param string $table
     * @return PDOStatement|null
     */
    public function drop(string $table): ?PDOStatement
    {
        return $this->exec('DROP TABLE IF EXISTS ' . $this->tableQuote($this->options()->prefix . $table));
    }

    /**
     * Select data from the table.
     *
     * @param string $table
     * @param $join
     * @param array|string|Raw $columns
     * @param array $where
     * @return array|null
     */
    public function select(string $table, $join, $columns = null, $where = null): ?array
    {
        $map = [];
        $result = [];
        $columnMap = [];

        $args = func_get_args();
        $lastArgs = $args[array_key_last($args)];
        $callback = is_callable($lastArgs) ? $lastArgs : null;

        $where = is_callable($where) ? null : $where;
        $columns = is_callable($columns) ? null : $columns;

        $column = $where === null ? $join : $columns;
        $isSingle = (is_string($column) && $column !== '*');

        $statement = $this->exec($this->_selectContext($table, $map, $join, $columns, $where), $map);

        $this->_columnMap($columns, $columnMap, true);

        if (!$this->_statement) {
            return $result;
        }

        if ($columns === '*') {
            if (isset($callback)) {
                while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $callback($data);
                }

                return null;
            }

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
            $currentStack = [];

            if (isset($callback)) {
                $this->_dataMap($data, $columns, $columnMap, $currentStack, true);

                $callback(
                    $isSingle ?
                        $currentStack[$columnMap[$column][0]] :
                        $currentStack
                );
            } else {
                $this->_dataMap($data, $columns, $columnMap, $currentStack, true, $result);
            }
        }

        if (isset($callback)) {
            return null;
        }

        if ($isSingle) {
            $singleResult = [];
            $resultKey = $columnMap[$column][0];

            foreach ($result as $item) {
                $singleResult[] = $item[$resultKey];
            }

            return $singleResult;
        }

        return $result;
    }

    /**
     * Insert one or more records into the table.
     *
     * @param string $table
     * @param array $values
     * #param string|null $primaryKey
     * @return PDOStatement|null
     */
    public function insert(string $table, array $values): ?PDOStatement
    {
        $stack = [];
        $columns = [];
        $fields = [];
        $map = [];

        if (!isset($values[0])) {
            $values = [$values];
        }

        foreach ($values as $data) {
            foreach ($data as $key => $value) {
                $columns[] = $key;
            }
        }

        $columns = array_unique($columns);

        foreach ($values as $data) {
            $values = [];

            foreach ($columns as $key) {
                $value = $data[$key];
                $type = gettype($value);

                if ($raw = $this->_buildRaw($data[$key], $map)) {
                    $values[] = $raw;
                    continue;
                }

                $mapKey = $this->_mapKey();
                $values[] = $mapKey;

                switch ($type) {

                    case 'array':
                        $map[$mapKey] = [
                            strpos($key, '[JSON]') === strlen($key) - 6 ?
                                json_encode($value) :
                                serialize($value),
                            PDO::PARAM_STR
                        ];
                        break;

                    case 'object':
                        $value = serialize($value);
                        $map[$mapKey] = $this->_typeMap($value, $type);
                        break;

                    case 'NULL':
                    case 'resource':
                    case 'boolean':
                    case 'integer':
                    case 'double':
                    case 'string':
                        $map[$mapKey] = $this->_typeMap($value, $type);
                        break;
                }
            }

            $stack[] = '(' . implode(', ', $values) . ')';
        }

        foreach ($columns as $key) {
            $fields[] = $this->columnQuote(preg_replace("/(\s*\[JSON\]$)/i", '', $key));
        }

        $query = 'INSERT INTO ' . $this->tableQuote($table) . ' (' . implode(', ', $fields) . ') VALUES ' . implode(', ', $stack);

        return $this->exec($query, $map);
    }

    /**
     * Modify data from the table.
     * @param string $table
     * @param array $data
     * @param null $where
     * @return PDOStatement|null
     */
    public function update(string $table, array $data, $where = null): ?PDOStatement
    {
        $fields = [];
        $map = [];

        foreach ($data as $key => $value) {
            $column = $this->columnQuote(preg_replace("/(\s*\[(JSON|\+|\-|\*|\/)\]$)/", '', $key));
            $type = gettype($value);

            if ($raw = $this->_buildRaw($value, $map)) {
                $fields[] = "{$column} = {$raw}";
                continue;
            }

            preg_match('/(?<column>[\p{L}_][\p{L}\p{N}@$#\-_]*)(\[(?<operator>\+|\-|\*|\/)\])?/u', $key, $match);

            if (isset($match['operator'])) {
                if (is_numeric($value)) {
                    $fields[] = "{$column} = {$column} {$match['operator']} {$value}";
                }
            } else {
                $mapKey = $this->_mapKey();
                $fields[] = "{$column} = {$mapKey}";

                switch ($type) {

                    case 'array':
                        $map[$mapKey] = [
                            strpos($key, '[JSON]') === strlen($key) - 6 ?
                                json_encode($value) :
                                serialize($value),
                            PDO::PARAM_STR
                        ];
                        break;

                    case 'object':
                        $value = serialize($value);
                        $map[$mapKey] = $this->_typeMap($value, $type);
                        break;
                    case 'NULL':
                    case 'resource':
                    case 'boolean':
                    case 'integer':
                    case 'double':
                    case 'string':
                        $map[$mapKey] = $this->_typeMap($value, $type);
                        break;
                }
            }
        }

        $query = 'UPDATE ' . $this->tableQuote($table) . ' SET ' . implode(', ', $fields) . $this->_whereClause($where, $map);
        return $this->exec($query, $map);
    }

    /**
     * Delete data from the table.
     *
     * @param string $table
     * @param array $where
     * @return PDOStatement|null
     */
    public function delete(string $table, array $where): ?PDOStatement
    {
        $map = [];

        return $this->exec('DELETE FROM ' . $this->tableQuote($table) . $this->_whereClause($where, $map), $map);
    }

    /**
     * Replace old data with a new one.
     *
     * @param string $table
     * @param array $columns
     * @param array $where
     * @return PDOStatement|null
     */
    public function replace(string $table, array $columns, $where = null): ?PDOStatement
    {
        $map = [];
        $stack = [];

        foreach ($columns as $column => $replacements) {
            if (is_array($replacements)) {
                foreach ($replacements as $old => $new) {
                    $mapKey = $this->_mapKey();
                    $columnName = $this->columnQuote($column);
                    $stack[] = "{$columnName} = REPLACE({$columnName}, {$mapKey}a, {$mapKey}b)";

                    $map[$mapKey . 'a'] = [$old, PDO::PARAM_STR];
                    $map[$mapKey . 'b'] = [$new, PDO::PARAM_STR];
                }
            }
        }

        if (empty($stack)) {
            throw new DatabaseInvalidArgumentException('Invalid columns supplied.');
        }

        return $this->exec('UPDATE ' . $this->tableQuote($table) . ' SET ' . implode(', ', $stack) . $this->_whereClause($where, $map), $map);
    }

    /**
     * Get only one record from the table.
     *
     * @param string $table
     * @param array $join
     * @param array|string $columns
     * @param array $where
     * @return array|int|string|bool|mixed
     */
    public function get(string $table, $join = null, $columns = null, $where = null)
    {
        $map = [];
        $result = [];
        $columnMap = [];
        $currentStack = [];

        if ($where === null) {
            if ($this->_isJoin($join)) {
                $where['LIMIT'] = 1;
            } else {
                $columns['LIMIT'] = 1;
            }

            $column = $join;
        } else {
            $column = $columns;
            $where['LIMIT'] = 1;
        }

        $isSingle = (is_string($column) && $column !== '*');
        $query = $this->exec($this->_selectContext($table, $map, $join, $columns, $where), $map);

        if (!$this->_statement) {
            return false;
        }

        $data = $query->fetchAll(PDO::FETCH_ASSOC);

        if (isset($data[0])) {
            if ($column === '*') {
                return $data[0];
            }

            $this->_columnMap($columns, $columnMap, true);
            $this->_dataMap($data[0], $columns, $columnMap, $currentStack, true, $result);

            if ($isSingle) {
                return $result[0][$columnMap[$column][0]];
            }

            return $result[0];
        }
        return false;
    }

    /**
     * Determine whether the target data existed from the table.
     *
     * @param string $table
     * @param $join
     * @param array $where
     * @return bool|null
     */
    public function has(string $table, $join, $where = null): ?bool
    {
        $map = [];
        $column = null;

        $query = $this->exec(
            'SELECT EXISTS(' . $this->_selectContext($table, $map, $join, $column, $where, 1) . ')',
            $map
        );

        if (!$this->_statement) {
            return null;
        }

        $result = $query->fetchColumn();

        return $result === '1' || $result === 1 || $result === true;
    }

    /**
     * Randomly fetch data from the table.
     *
     * @param string $table
     * @param array $join
     * @param array|string $columns
     * @param array $where
     * @return array
     */
    public function rand(string $table, $join = null, $columns = null, $where = null): array
    {
        $orderRaw = $this->raw($this->options()->driver === 'mysql' ? 'RAND()': 'RANDOM()');
        if ($where === null) {
            if ($this->_isJoin($join)) {
                $where['ORDER'] = $orderRaw;
            } else {
                $columns['ORDER'] = $orderRaw;
            }
        } else {
            $where['ORDER'] = $orderRaw;
        }
        return $this->select($table, $join, $columns, $where);
    }

    /**
     * Count the number of rows from the table.
     *
     * @param string $table
     * @param $join
     * @param string $column
     * @param array $where
     * @return int|null
     */
    public function count(string $table, $join = null, $column = null, $where = null): ?int
    {
        return (int) $this->_aggregate('COUNT', $table, $join, $column, $where);
    }

    /**
     * Calculate the average value of the column.
     *
     * @param string $table
     * @param $join
     * @param string $column
     * @param array $where
     * @return string|null
     */
    public function avg(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->_aggregate('AVG', $table, $join, $column, $where);
    }

    /**
     * Get the maximum value of the column.
     *
     * @param string $table
     * @param $join
     * @param string $column
     * @param array $where
     * @return string|null
     */
    public function max(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->_aggregate('MAX', $table, $join, $column, $where);
    }

    /**
     * Get the minimum value of the column.
     *
     * @param string $table
     * @param $join
     * @param string $column
     * @param array $where
     * @return string|null
     */
    public function min(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->_aggregate('MIN', $table, $join, $column, $where);
    }

    /**
     * Calculate the total value of the column.
     *
     * @param string $table
     * @param $join
     * @param string $column
     * @param array $where
     * @return string|null
     */
    public function sum(string $table, $join, $column = null, $where = null): ?string
    {
        return $this->_aggregate('SUM', $table, $join, $column, $where);
    }

    /**
     * Start a transaction.
     * @param callable $actions
     */
    public function action(callable $actions): void
    {
        if (is_callable($actions)) {
            try {
                $this->transaction();
                $result = $actions($this);
                if ($result === false) {
                    $this->pdo()->rollBack();
                } else {
                    $this->pdo()->commit();
                }
            } catch (Throwable $e) {
                $this->pdo()->rollBack();
                throw new DatabaseException($e->getMessage(),$e->getCode(),$e);
            }
        }
    }

    /**
     * @throws TransactionException
     * @throws DatabaseException
     */
    public function transaction() : void
    {
        try {
            $this->_clean();
            $this->reconnect();
            if(!$this->pdo()->inTransaction()){
                $res = $this->pdo()->beginTransaction();
                if(!$res){
                    goto tran_crash;
                }
                $this->_count = 0;
            }
        }catch (PDOException $exception){
            tran_crash:
            $this->_count++;
            $this->_error = isset($exception) ? $exception->errorInfo : $this->pdo()->errorInfo();
            [
                $this->_sqlstate,
                $this->_driver_code,
                $this->_driver_message
            ] = $this->_error;
            switch ($state = $this->_driver->recognizer($this->_sqlstate)) {
                case StateConstant::isReconnection($state):
                    if($this->_count <= 3){
                        $this->close();
                        usleep(500);
                        $this->transaction();
                    }
                    break;
                case StateConstant::isInterrupt($state):
                    throw new DatabaseException($this->_driver_message, $this->_driver_code);
            }
            throw new TransactionException($this->_driver_message, $this->_driver_code);
        } finally {
            if($this->_logger){
                if($error = $this->error()){
                    $this->_logger->error('Database Begin Transaction Error.',$this->error());
                }else{
                    $this->_logger->debug('Database Begin Transaction Success.', [$this->last()]);
                }
            }
        }
    }

    public function rollback(){
        try {
            if($this->pdo()->inTransaction()){
                $this->_clean();
                $this->pdo()->rollBack();
            }
        }catch (PDOException $exception) {
            $this->_error = isset($exception) ? $exception->errorInfo : $this->pdo()->errorInfo();
            [
                $this->_sqlstate,
                $this->_driver_code,
                $this->_driver_message
            ] = $this->_error;
            $this->close();
        } finally {
            if($this->_logger){
                if($error = $this->error()){
                    $this->_logger->error('Database Rollback Transaction Error.',$this->error());
                }else{
                    $this->_logger->debug('Database Rollback Transaction Success.', [$this->last()]);
                }
            }
        }
    }

    public function commit(){
        try {
            if($this->pdo()->inTransaction()){
                $this->_clean();
                $this->pdo()->commit();
            }
        }catch (PDOException $exception) {
            $this->_error = isset($exception) ? $exception->errorInfo : $this->pdo()->errorInfo();
            [
                $this->_sqlstate,
                $this->_driver_code,
                $this->_driver_message
            ] = $this->_error;
            $this->close();
        } finally {
            if($this->_logger){
                if($error = $this->error()){
                    $this->_logger->error('Database Commit Transaction Error.',$this->error());
                }else{
                    $this->_logger->debug('Database Commit Transaction Success.', [$this->last()]);
                }
            }
        }
    }

    /**
     * Return the ID for the last inserted row.
     *
     * @param string|null $name
     * @return string|null
     */
    public function id(?string $name = null): ?string
    {
        if($this->options()->driver === 'pgsql'){
            $id = $this->pdo()->query('SELECT LASTVAL()')->fetchColumn();
            return (string) $id ?: null;
        }
        return $this->pdo()->lastInsertId($name);
    }

    /**
     * Return the last performed statement.
     *
     * @codeCoverageIgnore
     * @return string|null
     */
    public function last(): ?string
    {
        if (empty($this->logs)) {
            return null;
        }

        $log = $this->logs[array_key_last($this->logs)];

        return $this->_generate($log[0], $log[1]);
    }

    /**
     * Return all executed statements.
     *
     * @return string[]
     */
    public function log(): array
    {
        return array_map(
            function ($log) {
                return $this->_generate($log[0], $log[1]);
            },
            $this->_logs
        );
    }

    /**
     * @return array
     */
    public function info(): array
    {
        $output = [
            'server' => 'SERVER_INFO',
            'driver' => 'DRIVER_NAME',
            'client' => 'CLIENT_VERSION',
            'version' => 'SERVER_VERSION',
            'connection' => 'CONNECTION_STATUS'
        ];
        foreach ($output as $key => $value) {
            $output[$key] = @$this->pdo()->getAttribute(constant('PDO::ATTR_' . $value));
        }
        $output['dsn'] = $this->getDsn();
        return $output;
    }

    /**
     * Build the statement part for the column stack.
     *
     * @param array|string $columns
     * @param array $map
     * @param bool $root
     * @param bool $isJoin
     * @return string
     */
    protected function _columnPush(&$columns, array &$map, bool $root, bool $isJoin = false): string
    {
        if ($columns === '*') {
            return $columns;
        }

        $stack = [];
        $hasDistinct = false;

        if (is_string($columns)) {
            $columns = [$columns];
        }

        foreach ($columns as $key => $value) {
            $isIntKey = is_int($key);
            $isArrayValue = is_array($value);

            if (!$isIntKey && $isArrayValue && $root && count(array_keys($columns)) === 1) {
                $stack[] = $this->columnQuote((string)$key);
                $stack[] = $this->_columnPush($value, $map, false, $isJoin);
            } elseif ($isArrayValue) {
                $stack[] = $this->_columnPush($value, $map, false, $isJoin);
            } elseif (!$isIntKey && $raw = $this->_buildRaw($value, $map)) {
                preg_match('/(?<column>[\p{L}_][\p{L}\p{N}@$#\-_\.]*)(\s*\[(?<type>(String|Bool|Int|Number))\])?/u', $key, $match);
                $stack[] = "{$raw} AS {$this->columnQuote($match['column'])}";
            } elseif ($isIntKey && is_string($value)) {
                if ($isJoin && strpos($value, '*') !== false) {
                    throw new InvalidArgumentException('Cannot use table.* to select all columns while joining table.');
                }

                preg_match('/(?<column>[\p{L}_][\p{L}\p{N}@$#\-_\.]*)(?:\s*\((?<alias>[\p{L}_][\p{L}\p{N}@$#\-_]*)\))?(?:\s*\[(?<type>(?:String|Bool|Int|Number|Object|JSON))\])?/u', $value, $match);

                if (!empty($match['alias'])) {
                    $columnString = "{$this->columnQuote($match['column'])} AS {$this->columnQuote($match['alias'])}";
                    $columns[$key] = $match['alias'];

                    if (!empty($match['type'])) {
                        $columns[$key] .= ' [' . $match['type'] . ']';
                    }
                } else {
                    $columnString = $this->columnQuote($match['column']);
                }

                if (!$hasDistinct && strpos($value, '@') === 0) {
                    $columnString = 'DISTINCT ' . $columnString;
                    $hasDistinct = true;
                    array_unshift($stack, $columnString);

                    continue;
                }

                $stack[] = $columnString;
            }
        }

        return implode(',', $stack);
    }

    /**
     * Implode where conditions.
     *
     * @param array $data
     * @param array $map
     * @param string $conjunctor
     * @return string
     */
    protected function _dataImplode(array $data, array &$map, string $conjunctor): string
    {
        $stack = [];

        foreach ($data as $key => $value) {
            $type = gettype($value);

            if (
                $type === 'array' &&
                preg_match("/^(AND|OR)(\s+#.*)?$/", $key, $relationMatch)
            ) {
                $stack[] = '(' . $this->_dataImplode($value, $map, ' ' . $relationMatch[1]) . ')';
                continue;
            }

            $mapKey = $this->_mapKey();
            $isIndex = is_int($key);

            preg_match(
                '/([\p{L}_][\p{L}\p{N}@$#\-_\.]*)(\[(?<operator>\>\=?|\<\=?|\!|\<\>|\>\<|\!?~|REGEXP)\])?([\p{L}_][\p{L}\p{N}@$#\-_\.]*)?/u',
                $isIndex ? $value : $key,
                $match
            );

            $column = $this->columnQuote($match[1]);
            $operator = $match['operator'] ?? null;

            if ($isIndex && isset($match[4]) && in_array($operator, ['>', '>=', '<', '<=', '=', '!='])) {
                $stack[] = "${column} ${operator} " . $this->columnQuote($match[4]);
                continue;
            }

            if ($operator) {
                if (in_array($operator, ['>', '>=', '<', '<='])) {
                    $condition = "{$column} {$operator} ";

                    if (is_numeric($value)) {
                        $condition .= $mapKey;
                        $map[$mapKey] = [$value, is_float($value) ? PDO::PARAM_STR : PDO::PARAM_INT];
                    } elseif ($raw = $this->_buildRaw($value, $map)) {
                        $condition .= $raw;
                    } else {
                        $condition .= $mapKey;
                        $map[$mapKey] = [$value, PDO::PARAM_STR];
                    }

                    $stack[] = $condition;
                } elseif ($operator === '!') {
                    switch ($type) {

                        case 'NULL':
                            $stack[] = $column . ' IS NOT NULL';
                            break;

                        case 'array':
                            $placeholders = [];

                            foreach ($value as $index => $item) {
                                $stackKey = $mapKey . $index . '_i';
                                $placeholders[] = $stackKey;
                                $map[$stackKey] = $this->_typeMap($item, gettype($item));
                            }

//                            $stack[] = $column . ' NOT IN (' . implode(', ', $placeholders) . ')';
                            $stack[] = $column . ' NOT IN (' . ($placeholders ? implode(', ', $placeholders): 'NULL') . ')';
                            break;

                        case 'object':
                            if ($raw = $this->_buildRaw($value, $map)) {
                                $stack[] = "{$column} != {$raw}";
                            }
                            break;

                        case 'integer':
                        case 'double':
                        case 'boolean':
                        case 'string':
                            $stack[] = "{$column} != {$mapKey}";
                            $map[$mapKey] = $this->_typeMap($value, $type);
                            break;
                    }
                } elseif ($operator === '~' || $operator === '!~') {
                    if ($type !== 'array') {
                        $value = [$value];
                    }

                    $connector = ' OR ';
                    $data = array_values($value);

                    if (is_array($data[0])) {
                        if (isset($value['AND']) || isset($value['OR'])) {
                            $connector = ' ' . array_keys($value)[0] . ' ';
                            $value = $data[0];
                        }
                    }

                    $likeClauses = [];

                    foreach ($value as $index => $item) {
                        $item = strval($item);

                        if (!preg_match('/((?<!\\\)\[.+(?<!\\\)\]|(?<!\\\)[\*\?\!\%\-#^_]|%.+|.+%)/', $item)) {
                            $item = '%' . $item . '%';
                        }

                        $likeClauses[] = $column . ($operator === '!~' ? ' NOT' : '') . " LIKE {$mapKey}L{$index}";
                        $map["{$mapKey}L{$index}"] = [$item, PDO::PARAM_STR];
                    }

                    $stack[] = '(' . implode($connector, $likeClauses) . ')';
                } elseif ($operator === '<>' || $operator === '><') {
                    if ($type === 'array') {
                        if ($operator === '><') {
                            $column .= ' NOT';
                        }

                        if ($this->_isRaw($value[0]) && $this->_isRaw($value[1])) {
                            $stack[] = "({$column} BETWEEN {$this->_buildRaw($value[0], $map)} AND {$this->_buildRaw($value[1], $map)})";
                        } else {
                            $stack[] = "({$column} BETWEEN {$mapKey}a AND {$mapKey}b)";
                            $dataType = (is_numeric($value[0]) && is_numeric($value[1])) ? PDO::PARAM_INT : PDO::PARAM_STR;

                            $map[$mapKey . 'a'] = [$value[0], $dataType];
                            $map[$mapKey . 'b'] = [$value[1], $dataType];
                        }
                    }
                } elseif ($operator === 'REGEXP') {
                    $stack[] = "{$column} REGEXP {$mapKey}";
                    $map[$mapKey] = [$value, PDO::PARAM_STR];
                }

                continue;
            }

            switch ($type) {

                case 'NULL':
                    $stack[] = $column . ' IS NULL';
                    break;

                case 'array':
                    $placeholders = [];

                    foreach ($value as $index => $item) {
                        $stackKey = $mapKey . $index . '_i';

                        $placeholders[] = $stackKey;
                        $map[$stackKey] = $this->_typeMap($item, gettype($item));
                    }

//                    $stack[] = $column . ' IN (' . implode(', ', $placeholders) . ')';
                    $stack[] = $column . ' IN (' . ($placeholders ? implode(', ', $placeholders): 'NULL') . ')';
                    break;

                case 'object':
                    if ($raw = $this->_buildRaw($value, $map)) {
                        $stack[] = "{$column} = {$raw}";
                    }
                    break;

                case 'integer':
                case 'double':
                case 'boolean':
                case 'string':
                    $stack[] = "{$column} = {$mapKey}";
                    $map[$mapKey] = $this->_typeMap($value, $type);
                    break;
            }
        }

        return implode($conjunctor . ' ', $stack);
    }

    /**
     * Build for the aggregate function.
     *
     * @param string $type
     * @param string $table
     * @param array $join
     * @param string $column
     * @param array $where
     * @return string|null
     */
    protected function _aggregate(string $type, string $table, $join = null, $column = null, $where = null): ?string
    {
        $map = [];

        $query = $this->exec($this->_selectContext($table, $map, $join, $column, $where, $type), $map);

        if (!$this->_statement) {
            return null;
        }
        return (string) $query->fetchColumn();
    }

    /**
     * Mapping the type name as PDO data type.
     *
     * @param mixed $value
     * @param string $type
     * @return array
     */
    protected function _typeMap($value, string $type): array
    {
        $map = [
            'NULL' => PDO::PARAM_NULL,
            'integer' => PDO::PARAM_INT,
            'double' => PDO::PARAM_STR,
            'boolean' => PDO::PARAM_BOOL,
            'string' => PDO::PARAM_STR,
            'object' => PDO::PARAM_STR,
            'resource' => PDO::PARAM_LOB
        ];

        if ($type === 'boolean') {
            $value = ($value ? '1' : '0');
        } elseif ($type === 'NULL') {
            $value = null;
        }

        return [$value, $map[$type]];
    }

    /**
     * Generate a new map key for placeholder.
     *
     * @return string
     */
    protected function _mapKey(): string
    {
        return ':CaX' . $this->_guid++ . '_zC';
    }

    /**
     * @param $object
     * @return bool
     */
    protected function _isRaw($object): bool
    {
        if(is_object($object)){
            return $object instanceof Raw;
        }
        return false;
    }

    /**
     * @param mixed $raw
     * @param array $map
     * @return string|null
     */
    protected function _buildRaw($raw, array &$map): ?string
    {
        if (!$this->_isRaw($raw)) {
            return null;
        }
        $query = preg_replace_callback(
            '/(([`\']).*?)?((FROM|TABLE|INTO|UPDATE|JOIN)\s*)?\<(([\p{L}_][\p{L}\p{N}@$#\-_]*)(\.[\p{L}_][\p{L}\p{N}@$#\-_]*)?)\>([^,]*?\2)?/u',
            function ($matches) {
                if (!empty($matches[2]) && isset($matches[8])) {
                    return $matches[0];
                }
                if (!empty($matches[4])) {
                    return $matches[1] . $matches[4] . ' ' . $this->tableQuote($matches[5]);
                }
                return $matches[1] . $this->columnQuote($matches[5]);
            },
            $raw->value
        );
        $rawMap = $raw->map;
        if (!empty($rawMap)) {
            foreach ($rawMap as $key => $value) {
                $map[$key] = $this->_typeMap($value, gettype($value));
            }
        }
        return $query;
    }

    /**
     * Determine the array is with join syntax.
     *
     * @param mixed $join
     * @return bool
     */
    protected function _isJoin($join): bool
    {
        if (!is_array($join)) {
            return false;
        }

        $keys = array_keys($join);

        if (
            isset($keys[0]) &&
            is_string($keys[0]) &&
            strpos($keys[0], '[') === 0
        ) {
            return true;
        }

        return false;
    }

    /**
     * Build the join statement.
     *
     * @param string $table
     * @param array $join
     * @param array $map
     * @return string
     */
    protected function _buildJoin(string $table, array $join, array &$map): string
    {
        $tableJoin = [];
        $type = [
            '>' => 'LEFT',
            '<' => 'RIGHT',
            '<>' => 'FULL',
            '><' => 'INNER'
        ];

        foreach ($join as $subtable => $relation) {
            preg_match('/(\[(?<join>\<\>?|\>\<?)\])?(?<table>[\p{L}_][\p{L}\p{N}@$#\-_]*)\s?(\((?<alias>[\p{L}_][\p{L}\p{N}@$#\-_]*)\))?/u', $subtable, $match);

            if ($match['join'] === '' || $match['table'] === '') {
                continue;
            }

            if (is_string($relation)) {
                $relation = 'USING (`' . $relation . '`)';
            } elseif (is_array($relation)) {
                // For ['column1', 'column2']
                if (isset($relation[0])) {
                    $relation = 'USING (`' . implode('`, `', $relation) . '`)';
                } else {
                    $joins = [];

                    foreach ($relation as $key => $value) {
                        if ($key === 'AND' && is_array($value)) {
                            $joins[] = $this->_dataImplode($value, $map, ' AND');
                            continue;
                        }

                        $joins[] = (
                            strpos($key, '.') > 0 ?
                                // For ['tableB.column' => 'column']
                                $this->columnQuote($key) :

                                // For ['column1' => 'column2']
                                $table . '.' . $this->columnQuote($key)
                            ) .
                            ' = ' .
                            $this->tableQuote($match['alias'] ?? $match['table']) . '.' . $this->columnQuote($value);
                    }

                    $relation = 'ON ' . implode(' AND ', $joins);
                }
            } elseif ($raw = $this->_buildRaw($relation, $map)) {
                $relation = $raw;
            }

            $tableName = $this->tableQuote($match['table']);

            if (isset($match['alias'])) {
                $tableName .= ' AS ' . $this->tableQuote($match['alias']);
            }

            $tableJoin[] = $type[$match['join']] . " JOIN ${tableName} ${relation}";
        }

        return implode(' ', $tableJoin);
    }

    /**
     * Mapping columns for the stack.
     *
     * @param array|string $columns
     * @param array $stack
     * @param bool $root
     * @return array
     */
    protected function _columnMap($columns, array &$stack, bool $root): array
    {
        if ($columns === '*') {
            return $stack;
        }

        foreach ($columns as $key => $value) {
            if (is_int($key)) {
                preg_match('/([\p{L}_][\p{L}\p{N}@$#\-_]*\.)?(?<column>[\p{L}_][\p{L}\p{N}@$#\-_]*)(?:\s*\((?<alias>[\p{L}_][\p{L}\p{N}@$#\-_]*)\))?(?:\s*\[(?<type>(?:String|Bool|Int|Number|Object|JSON))\])?/u', $value, $keyMatch);

                $columnKey = !empty($keyMatch['alias']) ?
                    $keyMatch['alias'] :
                    $keyMatch['column'];

                $stack[$value] = isset($keyMatch['type']) ?
                    [$columnKey, $keyMatch['type']] :
                    [$columnKey, 'String'];
            } elseif ($this->_isRaw($value)) {
                preg_match('/([\p{L}_][\p{L}\p{N}@$#\-_]*\.)?(?<column>[\p{L}_][\p{L}\p{N}@$#\-_]*)(\s*\[(?<type>(String|Bool|Int|Number))\])?/u', $key, $keyMatch);
                $columnKey = $keyMatch['column'];

                $stack[$key] = isset($keyMatch['type']) ?
                    [$columnKey, $keyMatch['type']] :
                    [$columnKey, 'String'];
            } elseif (!is_int($key) && is_array($value)) {
                if ($root && count(array_keys($columns)) === 1) {
                    $stack[$key] = [$key, 'String'];
                }

                $this->_columnMap($value, $stack, false);
            }
        }

        return $stack;
    }

    /**
     * Mapping the data from the table.
     *
     * @param array $data
     * @param array $columns
     * @param array $columnMap
     * @param array $stack
     * @param bool $root
     * @param array|null $result
     * @return void
     */
    protected function _dataMap(
        array $data,
        array $columns,
        array $columnMap,
        array &$stack,
        bool $root,
        array &$result = null
    ): void {
        if ($root) {
            $columnsKey = array_keys($columns);

            if (count($columnsKey) === 1 && is_array($columns[$columnsKey[0]])) {
                $indexKey = array_keys($columns)[0];
                $dataKey = preg_replace("/^[\p{L}_][\p{L}\p{N}@$#\-_]*\./u", '', $indexKey);
                $currentStack = [];

                $count = count($data);
                $i = 0;
                while($i <= $count){
                    $this->_dataMap($data, $columns[$indexKey], $columnMap, $currentStack, false, $result);
                    $index = $data[$dataKey];

                    if (isset($result)) {
                        $result[$index] = $currentStack;
                    } else {
                        $stack[$index] = $currentStack;
                    }
                }
            } else {
                $currentStack = [];
                $this->_dataMap($data, $columns, $columnMap, $currentStack, false, $result);

                if (isset($result)) {
                    $result[] = $currentStack;
                } else {
                    $stack = $currentStack;
                }
            }

            return;
        }

        foreach ($columns as $key => $value) {
            $isRaw = $this->_isRaw($value);

            if (is_int($key) || $isRaw) {
                $map = $columnMap[$isRaw ? $key : $value];
                $columnKey = $map[0];
                $item = $data[$columnKey];

                if (isset($map[1])) {
                    if ($isRaw && in_array($map[1], ['Object', 'JSON'])) {
                        continue;
                    }

                    if (is_null($item)) {
                        $stack[$columnKey] = null;
                        continue;
                    }

                    switch ($map[1]) {

                        case 'Number':
                            $stack[$columnKey] = (float) $item;
                            break;

                        case 'Int':
                            $stack[$columnKey] = (int) $item;
                            break;

                        case 'Bool':
                            $stack[$columnKey] = (bool) $item;
                            break;

                        case 'Object':
                            $stack[$columnKey] = unserialize($item);
                            break;

                        case 'JSON':
                            $stack[$columnKey] = json_decode($item, true);
                            break;

                        case 'String':
                            $stack[$columnKey] = $item;
                            break;
                    }
                } else {
                    $stack[$columnKey] = $item;
                }
            } else {
                $currentStack = [];
                $this->_dataMap($data, $value, $columnMap, $currentStack, false, $result);

                $stack[$key] = $currentStack;
            }
        }
    }

    /**
     * Build and execute returning query.
     *
     * @param string $query
     * @param array $map
     * @param array $data
     * @return PDOStatement|null
     */
    protected function _returningQuery(string $query, array &$map, array &$data): ?PDOStatement
    {
        $returnColumns = array_map(
            function ($value) {
                return $value[0];
            },
            $data
        );

        $query .= ' RETURNING ' .
            implode(', ', array_map([$this, 'columnQuote'], $returnColumns)) .
            ' INTO ' .
            implode(', ', array_keys($data));

        return $this->exec($query, $map, function ($statement) use (&$data) {
            foreach ($data as $key => $return) {
                if (isset($return[3])) {
                    $statement->bindParam($key, $data[$key][1], $return[2], $return[3]);
                } else {
                    $statement->bindParam($key, $data[$key][1], $return[2]);
                }
            }
        });
    }

    /**
     * Build the where clause.
     *
     * @param $where
     * @param array $map
     * @return string
     */
    protected function _whereClause($where, array &$map): string
    {
        $clause = '';

        if (is_array($where)) {
            $conditions = array_diff_key($where, array_flip(
                ['GROUP', 'ORDER', 'HAVING', 'LIMIT', 'LIKE', 'MATCH']
            ));

            if (!empty($conditions)) {
                $clause = ' WHERE ' . $this->_dataImplode($conditions, $map, ' AND');
            }

            if (isset($where['MATCH']) && $this->options()->driver === 'mysql') {
                $match = $where['MATCH'];

                if (is_array($match) && isset($match['columns'], $match['keyword'])) {
                    $mode = '';

                    $options = [
                        'natural' => 'IN NATURAL LANGUAGE MODE',
                        'natural+query' => 'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION',
                        'boolean' => 'IN BOOLEAN MODE',
                        'query' => 'WITH QUERY EXPANSION'
                    ];

                    if (isset($match['mode'], $options[$match['mode']])) {
                        $mode = ' ' . $options[$match['mode']];
                    }

                    $columns = implode(', ', array_map([$this, 'columnQuote'], $match['columns']));
                    $mapKey = $this->_mapKey();
                    $map[$mapKey] = [$match['keyword'], PDO::PARAM_STR];
                    $clause .= ($clause !== '' ? ' AND ' : ' WHERE') . ' MATCH (' . $columns . ') AGAINST (' . $mapKey . $mode . ')';
                }
            }

            if (isset($where['GROUP'])) {
                $group = $where['GROUP'];

                if (is_array($group)) {
                    $stack = [];

                    foreach ($group as $column => $value) {
                        $stack[] = $this->columnQuote($value);
                    }

                    $clause .= ' GROUP BY ' . implode(',', $stack);
                } elseif ($raw = $this->_buildRaw($group, $map)) {
                    $clause .= ' GROUP BY ' . $raw;
                } else {
                    $clause .= ' GROUP BY ' . $this->columnQuote($group);
                }
            }

            if (isset($where['HAVING'])) {
                $having = $where['HAVING'];

                if ($raw = $this->_buildRaw($having, $map)) {
                    $clause .= ' HAVING ' . $raw;
                } else {
                    $clause .= ' HAVING ' . $this->_dataImplode($having, $map, ' AND');
                }
            }

            if (isset($where['ORDER'])) {
                $order = $where['ORDER'];

                if (is_array($order)) {
                    $stack = [];

                    foreach ($order as $column => $value) {
                        if (is_array($value)) {
                            $valueStack = [];

                            foreach ($value as $item) {
                                $valueStack[] = is_int($item) ? $item : $this->quote($item);
                            }

                            $valueString = implode(',', $valueStack);
                            $stack[] = "FIELD({$this->columnQuote($column)}, {$valueString})";
                        } elseif ($value === 'ASC' || $value === 'DESC') {
                            $stack[] = $this->columnQuote($column) . ' ' . $value;
                        } elseif (is_int($column)) {
                            $stack[] = $this->columnQuote($value);
                        }
                    }

                    $clause .= ' ORDER BY ' . implode(',', $stack);
                } elseif ($raw = $this->_buildRaw($order, $map)) {
                    $clause .= ' ORDER BY ' . $raw;
                } else {
                    $clause .= ' ORDER BY ' . $this->columnQuote($order);
                }
            }

            if (isset($where['LIMIT'])) {
                $limit = $where['LIMIT'];

                if (is_numeric($limit)) {
                    $clause .= ' LIMIT ' . $limit;
                } elseif (
                    is_array($limit) &&
                    is_numeric($limit[0]) &&
                    is_numeric($limit[1])
                ) {
                    $clause .= " LIMIT {$limit[1]} OFFSET {$limit[0]}";
                }
            }
        } elseif ($raw = $this->_buildRaw($where, $map)) {
            $clause .= ' ' . $raw;
        }

        return $clause;
    }

    /**
     * Build statement for the select query.
     *
     * @param string $table
     * @param array $map
     * @param array|string $join
     * @param array|string $columns
     * @param array|null $where
     * @param string $columnFn
     * @return string
     */
    protected function _selectContext(
        string $table,
        array &$map,
        $join,
        &$columns = null,
        array $where = null,
        $columnFn = null
    ): string {
        preg_match('/(?<table>[\p{L}_][\p{L}\p{N}@$#\-_]*)\s*\((?<alias>[\p{L}_][\p{L}\p{N}@$#\-_]*)\)/u', $table, $tableMatch);

        if (isset($tableMatch['table'], $tableMatch['alias'])) {
            $table = $this->tableQuote($tableMatch['table']);
            $tableAlias = $this->tableQuote($tableMatch['alias']);
            $tableQuery = "{$table} AS {$tableAlias}";
        } else {
            $table = $this->tableQuote($table);
            $tableQuery = $table;
        }

        $isJoin = $this->_isJoin($join);

        if ($isJoin) {
            $tableQuery .= ' ' . $this->_buildJoin($tableAlias ?? $table, $join, $map);
        } else {
            if (is_null($columns)) {
                if (
                    !is_null($where) ||
                    (is_array($join) && isset($columnFn))
                ) {
                    $where = $join;
                    $columns = null;
                } else {
                    $where = null;
                    $columns = $join;
                }
            } else {
                $where = $columns;
                $columns = $join;
            }
        }

        if (isset($columnFn)) {
            if ($columnFn === 1) {
                $column = '1';

                if (is_null($where)) {
                    $where = $columns;
                }
            } elseif ($raw = $this->_buildRaw($columnFn, $map)) {
                $column = $raw;
            } else {
                if (empty($columns) || (is_object($columns) && $this->_isRaw($columns))) {
                    $columns = '*';
                    $where = $join;
                }

                $column = $columnFn . '(' . $this->_columnPush($columns, $map, true) . ')';
            }
        } else {
            $column = $this->_columnPush($columns, $map, true, $isJoin);
        }

        return 'SELECT ' . $column . ' FROM ' . $tableQuery . $this->_whereClause($where, $map);
    }

    /**
     * Generate readable statement.
     *
     * @param string $statement
     * @param array $map
     * @return string
     */
    protected function _generate(string $statement, array $map): string
    {
        $statement = preg_replace(
            '/(?!\'[^\s]+\s?)"([\p{L}_][\p{L}\p{N}@$#\-_]*)"(?!\s?[^\s]+\')/u',
            '`$1`',
            $statement
        );
        foreach ($map as $key => $value) {
            if ($value[1] === PDO::PARAM_STR) {
                $replace = $this->quote((string)$value[0]);
            } elseif ($value[1] === PDO::PARAM_NULL) {
                $replace = 'NULL';
            } elseif ($value[1] === PDO::PARAM_LOB) {
                $replace = '{LOB_DATA}';
            } else {
                $replace = $value[0] . '';
            }

            $statement = str_replace($key, $replace, $statement);
        }

        return $statement;
    }

    /**
     * 初始化信息
     */
    protected function _clean() : void
    {
        $this->_statement = null;
        $this->_error = null;
        $this->_sqlstate = null;
        $this->_driver_code = null;
        $this->_driver_message = null;
    }
}