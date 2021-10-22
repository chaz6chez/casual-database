<?php
# -------------------------- #
#  Name: chaz6chez           #
#  Email: admin@chaz6chez.cn #
#  Date: 2018/10/17          #
# -------------------------- #
declare(strict_types=1);

namespace Database;

use Database\Exceptions\DatabaseException;
use Database\Exceptions\DatabaseInvalidArgumentException;
use Database\Tools\Raw;
use PDO;
use PDOStatement;
use PDOException;
use Throwable;
use Psr\Log\LoggerInterface;

class Connection {

    /**
     * @var Driver
     */
    protected $_driver;
    protected $_error;

    /**
     * 数据库配置
     * @var array
     */
    protected $_config = [];

    protected $_table;
    protected $_join = [];
    protected $_field = '*';
    protected $_where = [];
    protected $_order;
    protected $_limit;
    protected $_group;
    protected $_having = [];
    protected $_cache = false;
    protected $_logger;

    public function __invoke(array $config, ?LoggerInterface $logger = null) : Connection
    {
        $this->_logger = $logger;
        $this->_config = $config;
        return $this;
    }

    /**
     * 激活
     * @return $this
     */
    public function activate() : Connection
    {
        if(!$this->_driver instanceof Driver){
            if($this->_config){
                try{
                    $this->_driver = new Driver($this->_config, $this->_logger);
                    $this->_error = null;
                }catch (PDOException $e){
                    $this->_error = "db server exception : {$e->getMessage()}";
                }catch (Throwable $e){
                    $this->_error = "exception : {$e->getMessage()}";
                }finally {
                    if(isset($e) and $e instanceof Throwable) throw $e;
                }
            }else{
                $this->_error = 'config error';
            }
        }
        return $this;
    }

    /**
     * 是否激活
     * @return bool
     */
    public function isActivated() : bool
    {
        return boolval($this->_error === null and $this->_driver instanceof Driver);
    }

    /**
     * @return null|mixed
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * 获得驱动
     * @return Driver
     */
    public function driver() : Driver
    {
        return $this->activate()->_driver;
    }

    /**
     * 获得PDO对象
     * @return \PDO|null
     */
    public function pdo() : ?PDO
    {
        return $this->driver()->pdo();
    }

    /**
     * 设置表名
     * @param $table
     * @return Connection
     */
    public function table($table) : Connection
    {
        $this->from($table);
        return $this;
    }

    /**
     * 设置表名
     * @param $table
     * @return Connection
     */
    public function from($table) : Connection
    {
        $this->_table = $table;
        return $this;
    }

    /**
     * @param $join
     * @return Connection
     */
    public function join($join) : Connection
    {
        $this->_join = array_merge_recursive($this->_join, $join);
        return $this;
    }

    /**
     * @param $field
     * @return Connection
     */
    public function field($field) : Connection
    {
        if(is_string($field)){
            $fields = explode(',', $field);
            if(count($fields) > 1){
                $field = $fields;
            }
        }
        if (is_array($field)) {
            if (is_array($this->_field)) {
                $this->_field = array_merge($this->_field, $field);
                return $this;
            }
        }
        $this->_field = $field;
        return $this;
    }

    /**
     * @param $where
     * @return Connection
     */
    public function where($where) : Connection
    {
        if($this->driver()->isRaw($where)){
            $this->_where = $where;
        }
        if(is_array($where)){
            $this->_where = array_merge($this->_where, $where);
        }
        return $this;
    }

    /**
     * @param $order
     * @return Connection
     */
    public function order($order) : Connection
    {
        if (is_string($order)) {
            $order = explode(' ', $order);
            if (count($order) > 1) {
                $this->_order[$order[0]] = $order[1];
            }
        }
        if (is_array($order) or $this->driver()->isRaw($order)) {
            $this->_order = $order;
        }
        return $this;
    }

    /**
     * @param string|int $offset
     * @param null|int $limit
     * @return Connection
     */
    public function limit($offset, $limit = null) : Connection
    {
        $offset = (string)$offset;
        if (strpos($offset, ',')) {
            $rel = explode(',', $offset);
            $offset = (int)$rel[0];
            $limit = (int)$rel[1];
        }
        if (is_null($limit)) {
            $limit = $offset;
            $offset = 0;
        }
        $this->_limit = [$offset, $limit];
        return $this;
    }

    /**
     * @param $group
     * @return Connection
     */
    public function group($group) : Connection
    {
        if (
            is_string($group) or
            is_array($group) or
            $this->driver()->isRaw($group)
        ) {
            $this->_group = $group;
        }
        return $this;
    }

    /**
     * @param $having
     * @return Connection
     */
    public function having($having): Connection
    {
        if ($this->driver()->isRaw($having)) {
            $this->_having = $having;
        }
        if (is_array($having)) {
            if (is_array($this->_having)) {
                $this->_having = array_merge($this->_having, $having);
            }
        }
        return $this;
    }

    /**
     * 获取多条数据
     * @return array|bool|mixed
     */
    public function select()
    {
        if ($this->_join) {
            $res = $this->driver()->select($this->_table, $this->_join, $this->_field, $this->_getWhere());
        } else {
            $res = $this->driver()->select($this->_table, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * 获取单条数据
     * @param false $lock
     * @return array|false|mixed|null
     */
    public function find($lock = false)
    {
        if($this->isActivated()){
            $this->limit(1);
            $where = $lock ? $this->_getWhere([
                'FOR UPDATE' => true
            ]) : $this->_getWhere();
            if ($this->_join) {
                $res = $this->driver()->select($this->_table, $this->_join, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->select($this->_table, $this->_field, $where);
            }
            $this->cleanup();
            return $res ? $res[0] : $res;
        }
        return false;
    }

    /**
     * 新增数据
     * @param array $data
     * @param bool $filter
     * @return false|int|mixed|PDOStatement|string
     */
    public function insert(array $data, bool $filter = false)
    {
        if($filter){
            $array = [];
            foreach ($data as $key => $v){
                preg_match('/(?<column>[\s\S]*(?=\[(?<operator>\+|\-|\*|\/|\>\=?|\<\=?|\!|\<\>|\>\<|\!?~)\]$)|[\s\S]*)/', $key, $match);
                if(isset($match['operator'])){
                    $array[$match['column']] = $v;
                }else{
                    $array[$key] = $v;
                }
            }
            $data = $array;
        }
        $res = $this->driver()->insert($this->_table, $data);
        $this->cleanup();
        return $res instanceof PDOStatement ? $this->driver()->id() : false;
    }

    /**
     * @param string $table
     * @param $data
     * @param array|null $options
     * @return bool
     */
    public function create(string $table, $data, array $options = null) : bool
    {
        $res = $this->driver()->create($table, $data, $options);
        $this->cleanup();
        return $res === null ? false : true;
    }

    /**
     * 更新数据
     * @param $data
     * @return false|int
     */
    public function update($data)
    {
        $res = $this->driver()->update($this->_table, $data, $this->_getWhere());
        $this->cleanup();
        return $res instanceof PDOStatement ? $res->rowCount() : false;
    }

    /**
     * 删除
     * @return PDOStatement|null
     */
    public function delete(): ?PDOStatement
    {
        return $this->driver()->delete($this->_table, $this->_getWhere());
    }

    /**
     * @param $columns
     * @return bool|PDOStatement
     * @throws DatabaseInvalidArgumentException
     */
    public function replace($columns)
    {
        $res = $this->driver()->replace($this->_table, $columns, $this->_getWhere());
        $this->cleanup();
        return $res instanceof PDOStatement ? $res->rowCount() : false;
    }

    /**
     * @param bool $lock
     * @return false|array|string|null|mixed
     */
    public function get(bool $lock = false)
    {
        $res = $this->driver()->get(
            $this->_table,
            $this->_field,
            $lock ? $this->_getWhere([
                'FOR UPDATE' => true
            ]) : $this->_getWhere()
        );
        $this->cleanup();
        return $res;
    }

    /**
     * @return false|int 0:has not 1: has
     */
    public function has() {
        if (!$this->_join) {
            $res = $this->driver()->has($this->_table, $this->_getWhere());
        } else {
            $res = $this->driver()->has($this->_table, $this->_join, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : (int)$res;
    }

    /**
     * @return false|int
     */
    public function count() {
        if (!$this->_join) {
            $res = $this->driver()->count($this->_table, $this->_getWhere());
        } else {
            $res = $this->driver()->count($this->_table, $this->_join, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * @return false|string
     */
    public function max() {
        if (!$this->_join) {
            $res = $this->driver()->max($this->_table, $this->_field, $this->_getWhere());
        } else {
            $res = $this->driver()->max($this->_table, $this->_join, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * @return false|string
     */
    public function min() {
        if (!$this->_join) {
            $res = $this->driver()->min($this->_table, $this->_field, $this->_getWhere());
        } else {
            $res = $this->driver()->min($this->_table, $this->_join, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * @return false|string
     */
    public function avg() {
        if (!$this->_join) {
            $res = $this->driver()->avg($this->_table, $this->_field, $this->_getWhere());
        } else {
            $res = $this->driver()->avg($this->_table, $this->_join, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * @return false|string
     */
    public function sum() {
        if (!$this->_join) {
            $res = $this->driver()->sum($this->_table, $this->_field, $this->_getWhere());
        } else {
            $res = $this->driver()->sum($this->_table, $this->_join, $this->_field, $this->_getWhere());
        }
        $this->cleanup();
        return $res === null ? false : $res;
    }

    /**
     * @return array|false
     */
    public function sumGroup() {
        $data = [];
        $table = $this->_table;
        $where = $this->_getWhere();
        $join = $this->_join;
        if (is_array($this->_field)) {
            foreach ($this->_field as $f) {
                $this->_table = $table;
                $this->_field = [$f];
                $this->_join = $join;
                $this->_where = $where;
                $sum = $this->sum();
                if($sum === null){
                    return false;
                }
                $data[$f] = $sum;
            }
            return $data;
        }
    }

    /**
     * @return array
     */
    public function info() : array
    {
        return $this->driver()->info();
    }

    /**
     * @return array|null
     */
    public function error() : ?array
    {
        return $this->driver()->error();
    }

    /**
     * @return string|null
     */
    public function last() : ?string
    {
        return $this->driver()->last();
    }

    /**
     * @param string $string
     * @return string
     */
    public function quote(string $string) : string
    {
        return $this->driver()->quote($string);
    }

    /**
     * @param string $query
     * @return PDOStatement|null
     */
    public function query(string $query) :?PDOStatement
    {
        return $this->driver()->query($query);
    }

    /**
     * @param string $query
     * @return PDOStatement|null
     */
    public function exec(string $query) : ?PDOStatement
    {
        return $this->driver()->exec($query);
    }

    /**
     * 开启事务
     * @return bool
     */
    public function transaction() : bool
    {
        try {
            $this->driver()->transaction();
            return true;
        }catch (DatabaseException $exception){
            $this->_error = "Transaction error {$exception->getMessage()}";
            return false;
        }
    }

    /**
     * 开启事务
     * @return bool
     * @see Connection::transaction()
     */
    public function beginTransaction() : bool
    {
        return $this->transaction();
    }

    /**
     * 事务回滚
     */
    public function rollback() : void
    {
        $this->driver()->rollback();
    }

    /**
     * 执行事务提交
     */
    public function commit() : void
    {
        $this->driver()->commit();
    }

    /**
     * 属性参数初始化
     */
    public function cleanup(): void
    {
        $this->_join       = [];
        $this->_field      = '*';
        $this->_where      = [];
        $this->_order      = null;
        $this->_limit      = null;
        $this->_group      = null;
        $this->_having     = [];
        $this->_cache      = true;
        $this->_error      = null;
    }

    /**
     * @return array
     */
    public function getParams() : array
    {
        return [
            'table'      => $this->_table,
            'join'       => $this->_join,
            'field'      => $this->_field,
            'where'      => $this->_where,
            'order'      => $this->_order,
            'limit'      => $this->_limit,
            'group'      => $this->_group,
            'having'     => $this->_having,
            'cache'      => $this->_cache,
        ];
    }

    /**
     * @param $params
     */
    public function setParams(array $params) {
        empty($params['table']) || $this->_table = $params['table'];
        empty($params['join'])  || $this->_join  = $params['join'];
        empty($params['field']) || $this->_field = $params['field'];
        empty($params['where']) || $this->_where = $params['where'];
        empty($params['order']) || $this->_order = $params['order'];
        empty($params['limit']) || $this->_limit = $params['limit'];
        empty($params['group']) || $this->_group = $params['group'];
        empty($params['having']) || $this->_having = $params['having'];
        empty($params['cache']) || $this->_cache = $params['cache'];
    }

    /**
     * @param array $array
     * @return array|Raw
     */
    protected function _getWhere(array $array = [])
    {
        $where = $this->_where;
        if($this->driver()->isRaw($where)){
            return $where;
        }
        if ($this->_order) {
            $where['ORDER'] = $this->_order;
        }
        if ($this->_limit) {
            $where['LIMIT'] = $this->_limit;
        }
        if ($this->_group) {
            $where['GROUP'] = $this->_group;
        }
        if ($this->_having) {
            $where['HAVING'] = $this->_having;
        }
        return array_merge($where, $array);
    }

    /**
     * @return array|null
     */
    public function log() : ?array
    {
        return $this->driver()->log();
    }
}