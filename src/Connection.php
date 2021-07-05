<?php
# -------------------------- #
#  Name: chaz6chez           #
#  Email: admin@chaz6chez.cn #
#  Date: 2018/10/17          #
# -------------------------- #
declare(strict_types=1);

namespace Database;

use Database\Exception\ExpireException;
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
                }catch (\PDOException $e){
                    $this->_error = "db server exception : {$e->getMessage()}";
                }catch(\Throwable $e){
                    $this->_error = "exception : {$e->getMessage()}";
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
        return $this->_driver;
    }

    /**
     * 获得PDO对象
     * @return \PDO|null
     */
    public function pdo() : ?\PDO{
        if($this->isActivated()){
            return $this->_driver->pdo;
        }
        return null;
    }

    /**
     * 设置表名
     * @param $table
     * @return Connection
     */
    public function table($table) : Connection{
        $this->from($table);
        return $this;
    }

    /**
     * 设置表名
     * @param $table
     * @return Connection
     */
    public function from($table) : Connection{
        $this->_table = $table;
        return $this;
    }

    /**
     * @param $join
     * @return Connection
     */
    public function join($join) : Connection{
        $this->_join = array_merge_recursive($this->_join, $join);
        return $this;
    }

    /**
     * @param $field
     * @return Connection
     */
    public function field($field) : Connection{
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
    public function where($where) : Connection{
        $this->_where = array_merge($this->_where, $where);
        return $this;
    }

    /**
     * @param $order
     * @return Connection
     */
    public function order($order) : Connection{
        if (is_array($order)) {
            $this->_order = $order;
            return $this;
        }
        if (is_string($order)){
            $order = explode(' ',$order);
            if(count($order) > 1){
                $this->_order[$order[0]] = $order[1];
            }
        }
        return $this;
    }

    /**
     * @param string|int $offset
     * @param null|int $limit
     * @return Connection
     */
    public function limit($offset, $limit = null) : Connection {
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
    public function group($group) : Connection {
        if (is_array($group)) {
            if (is_array($this->_group)) {
                $this->_group = array_merge($this->_group, $group);
                return $this;
            }
        }
        $this->_group = $group;
        return $this;
    }

    /**
     * 获取多条数据
     * @return array|bool|mixed
     */
    public function select() {
        if($this->isActivated()) {
            if ($this->_join) {
                $res = $this->driver()->select($this->_table, $this->_join, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->select($this->_table, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    /**
     * 获取单条数据
     * @param false $lock
     * @return array|false|mixed|null
     */
    public function find($lock = false) {
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
     * @return false|int|mixed|\PDOStatement|string|null
     */
    public function insert(array $data, bool $filter = false) {
        if($this->isActivated()){
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
            if(is_object($res)){
                $res = $this->driver()->id();
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    /**
     * 更新数据
     * @param $data
     * @return false|int
     */
    public function update($data) {
        if($this->isActivated()){
            $res = $this->driver()->update($this->_table, $data, $this->_getWhere());
            $this->cleanup();
            return $res instanceof \PDOStatement ? $res->rowCount() : false;
        }
        return false;
    }

    /**
     * 删除
     * @return false|int
     */
    public function delete() {
        if($this->isActivated()){
            $res = $this->driver()->delete($this->_table, $this->_getWhere());
            $this->cleanup();
            return $res instanceof \PDOStatement ? $res->rowCount() : false;
        }
        return false;
    }

    /**
     * @param $columns
     * @return bool|\PDOStatement
     */
    public function replace($columns) {
        if($this->isActivated()){
            $res = $this->driver()->replace($this->_table, $columns, $this->_getWhere());
            $this->cleanup();
            return $res;
        }
        return false;

    }

    /**
     * @param bool $lock
     * @return false|mixed
     */
    public function get(bool $lock = false) {
        if($this->isActivated()){
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
        return false;
    }

    public function hasTable(bool $like = true) {
        if($this->isActivated()){
            $res = $this->driver()->hasTable($this->_table, $like);
            $this->cleanup();
            return $res ? $res[0] : $res;
        }
        return false;
    }

    public function has() {
        if($this->isActivated()){
            if (!$this->_join) {
                $res = $this->driver()->has($this->_table, $this->_getWhere());
            } else {
                $res = $this->driver()->has($this->_table, $this->_join, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function count() {
        if($this->isActivated()){
            if (!$this->_join) {
                $res = $this->driver()->count($this->_table, $this->_getWhere());
            } else {
                $res = $this->driver()->count($this->_table, $this->_join, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function max() {
        if ($this->isActivated()) {
            if (!$this->_join) {
                $res = $this->driver()->max($this->_table, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->max($this->_table, $this->_join, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function min() {
        if($this->isActivated()){
            if (!$this->_join) {
                $res = $this->driver()->min($this->_table, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->min($this->_table, $this->_join, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function avg() {
        if($this->isActivated()){
            if (!$this->_join) {
                $res = $this->driver()->avg($this->_table, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->avg($this->_table, $this->_join, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function sumGroup() {
        if($this->isActivated()){
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
                    $data[$f] = $this->sum();
                }
                return $data;
            }
        }
        return false;
    }

    public function sum() {
        if($this->isActivated()){
            if (!$this->_join) {
                $res = $this->driver()->sum($this->_table, $this->_field, $this->_getWhere());
            } else {
                $res = $this->driver()->sum($this->_table, $this->_join, $this->_field, $this->_getWhere());
            }
            $this->cleanup();
            return $res;
        }
        return false;
    }

    public function info() {
        if($this->isActivated()){
            return $this->driver()->info();
        }
        return false;
    }

    public function error() : ?bool {
        if($this->isActivated()){
            return $this->driver()->error();
        }
        return false;
    }

    public function last(){
        if($this->isActivated()){
            return $this->driver()->last();
        }
        return false;
    }

    public function quote(string $string) {
        if($this->isActivated()){
            return $this->driver()->quote($string);
        }
        return false;
    }

    public function query(string $query) {
        if($this->isActivated()){
            return $this->driver()->query($query);
        }
        return false;
    }

    public function exec(string $query) {
        if($this->isActivated()){
            return $this->driver()->exec($query);
        }
        return false;
    }

    /**
     * 开启事务
     * @param int|null $timeout
     * @return bool
     */
    public function beginTransaction(?int $timeout = null) :bool{
        if($this->isActivated()){
            return $this->driver()->beginTransaction($timeout === null ? $timeout : time() + $timeout);
        }
        return false;
    }

    /**
     * 事务回滚
     * @return bool
     */
    public function rollback() : bool{
        if($this->isActivated()){
            return $this->driver()->rollback();
        }
        return false;

    }

    /**
     * 执行事务提交
     * @return bool
     */
    public function commit() : ?bool{
        if($this->isActivated()){
            return $this->driver()->commit();
        }
        return false;
    }

    /**
     * 属性参数初始化
     */
    public function cleanup() {
        $this->_join       = [];
        $this->_field      = '*';
        $this->_where      = [];
        $this->_order      = null;
        $this->_limit      = null;
        $this->_group      = null;
        $this->_cache      = true;
        $this->_error      = null;
    }

    public function getParams() : array{
        return [
            'table'      => $this->_table,
            'join'       => $this->_join,
            'field'      => $this->_field,
            'where'      => $this->_where,
            'order'      => $this->_order,
            'limit'      => $this->_limit,
            'group'      => $this->_group,
            'cache'      => $this->_cache,
        ];
    }

    public function setParams($params) {
        empty($params['table']) || $this->_table = $params['table'];
        empty($params['join'])  || $this->_join  = $params['join'];
        empty($params['field']) || $this->_field = $params['field'];
        empty($params['where']) || $this->_where = $params['where'];
        empty($params['order']) || $this->_order = $params['order'];
        empty($params['limit']) || $this->_limit = $params['limit'];
        empty($params['group']) || $this->_group = $params['group'];
        empty($params['cache']) || $this->_cache = $params['cache'];
    }

    protected function _getWhere(array $array = []) : array{
        $where = $this->_where;
        if ($this->_order) {
            $where['ORDER'] = $this->_order;
        }
        if ($this->_limit) {
            $where['LIMIT'] = $this->_limit;
        }
        if ($this->_group) {
            $where['GROUP'] = $this->_group;
        }
        return array_merge($where, $array);
    }

    public function log(){
        if($this->isActivated()){
            return $this->driver()->log();
        }
        return false;
    }
}