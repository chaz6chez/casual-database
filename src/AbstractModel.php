<?php
# -------------------------- #
#  Name: chaz6chez           #
#  Email: admin@chaz6chez.cn #
#  Date: 2018/10/17          #
# -------------------------- #
declare(strict_types=1);

namespace Database;

use Database\Exceptions\DbnameException;
use Psr\Log\LoggerInterface;

abstract class AbstractModel {
    /**
     * @var string 表名
     * @example $_table = 'demo'
     */
    protected $_table;

    /**
     * @var string 库名
     * @example $_dbName = 'demo'
     */
    protected $_dbName;

    /**
     * @var Connection[]
     */
    protected static $_dbMaster = [];

    /**
     * @var Connection[]
     */
    protected static $_dbSlave = [];

    protected $_slave   = false;
    protected $_logger  = null;

    /**
     * @param bool $key
     * @return $this
     */
    public function slave(bool $key) : AbstractModel
    {
        $this->_slave = $key;
        return $this;
    }

    /**
     * 设置日志
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger) : AbstractModel
    {
        $this->_logger = $logger;
        return $this;
    }

    /**
     * 是否开启了时效限制
     * @return bool
     */
    public function isTimeLimited() : bool
    {
        return boolval($this->dbName()->driver()->getExpireTimestamp() !== 0);
    }

    /**
     * 获得主数据库连接
     * @param bool $master
     * @return Connection
     */
    public function dbName(bool $master = true) : Connection
    {
        if(!$this->_dbName){
            throw new DbnameException('dbName cannot be empty.');
        }
        if(!$master){
            if (
                !isset(self::$_dbSlave[$this->_dbName]) or
                !self::$_dbSlave[$this->_dbName] instanceof Connection
            ) {
                $res = self::$_dbSlave[$this->_dbName] =
                    (new Connection())($this->_slaveConfig(), $this->_logger)->activate();
            }else{
                $res = self::$_dbSlave[$this->_dbName];
            }
        }else{
            if (
                !isset(self::$_dbMaster[$this->_dbName]) or
                !self::$_dbMaster[$this->_dbName] instanceof Connection
            ) {
                $res = self::$_dbMaster[$this->_dbName] =
                    (new Connection())($this->_masterConfig(), $this->_logger)->activate();
            }else{
                $res = self::$_dbMaster[$this->_dbName];
            }
        }
        return $res;
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function table(?string $name = null) : string
    {
        if ($name === null) {
            return $this->_table;
        }
        $v = "_table_{$name}";
        return $this->{$v};
    }

    /**
     * 获取表名
     * @param string $name
     * @return string
     * @deprecated
     */
    public function tb(string $name = '') : string{
        return $this->table($name === '' ? null : $name);
    }

    /**
     * @return array
     */
    abstract protected function _masterConfig() : array;

    /**
     * @return array
     */
    abstract protected function _slaveConfig() : array;
}
