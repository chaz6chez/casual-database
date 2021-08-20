<?php
declare(strict_types=1);

namespace Database\Protocols;

use Database\Driver;

interface DriverInterface {

    /**
     * 连接器构造函数加载
     * @param Driver $driver
     */
    public function construct(Driver &$driver) : void;

    /**
     * @param string $sqlstate
     * @return int 返回StateConstant中的常量
     */
    public function recognizer(string $sqlstate) : int;

    /**
     * 连接器析构函数加载
     * @param Driver $driver
     */
    public function destruct(Driver &$driver) : void;
}