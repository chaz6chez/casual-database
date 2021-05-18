<?php
# -------------------------- #
#  Name: chaz6chez           #
#  Email: admin@chaz6chez.cn #
#  Date: 2018/10/17          #
# -------------------------- #
declare(strict_types=1);

namespace Database;

abstract class AbstractModel {

    protected $_table;
    protected $_dbName;
    /**
     * @var Connection[]
     */
    protected $_dbMaster = [];
    /**
     * @var Connection[]
     */
    protected $_dbSlave  = [];
    protected $_slave    = false;

    public function slave(bool $key) : AbstractModel {
        $this->_slave = $key;
        return $this;
    }

    /**
     * @return array
     */
    abstract protected function _masterConfig() : array;

    /**
     * @return array
     */
    abstract protected function _slaveConfig() : array;

    /**
     * 获得主数据库连接
     * @param bool $master
     * @return Connection
     */
    public function dbName(bool $master = true) : Connection{
        if(!$master){
            if (
                !isset($this->_dbSlave[$this->_dbName]) or
                !$this->_dbSlave[$this->_dbName] instanceof Connection
            ) {
                $res = $this->_dbSlave[$this->_dbName] = (new Connection())($this->_slaveConfig())->activate();
            }else{
                $res = $this->_dbSlave[$this->_dbName];
            }
        }else{
            if (
                !isset($this->_dbMaster[$this->_dbName]) or
                !$this->_dbMaster[$this->_dbName] instanceof Connection
            ) {
                $res = $this->_dbMaster[$this->_dbName] = (new Connection())($this->_masterConfig())->activate();
            }else{
                $res = $this->_dbMaster[$this->_dbName];
            }
        }
        return $res;
    }

    /**
     * 获取表名
     * @param string $name
     * @return string
     */
    public function tb(string $name = '') : string{
        if ($name === '') {
            return $this->_table;
        }
        $v = "_table_{$name}";
        return $this->{$v};
    }
}
