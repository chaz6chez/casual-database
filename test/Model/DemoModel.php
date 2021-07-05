<?php
declare(strict_types=1);

namespace Database\Test\Model;

use Database\AbstractModel;
use \PDO;

class DemoModel extends AbstractModel {
    protected $_dbName = 'demo';
    protected $_table = 'demo';

    protected function _masterConfig(): array
    {
        return [
            'database_type' => 'mysql',
            'server'        => '192.168.4.228',
            'username'      => 'root',
            'password'      => 'bi7ZtNpjdEewPmsx',
            'database_file' => '',
            'port'          => '3306',
            'charset'       => 'utf8mb4',
            'database_name' => 'demo',
            'option'        => [
                PDO::ATTR_PERSISTENT       => true, # 长连接
                PDO::ATTR_TIMEOUT          => 2,
                PDO::ATTR_EMULATE_PREPARES => false
            ],
            'prefix'        => '',
            'slave' => [
                'database_type' => 'mysql',
                'server'        => '192.168.4.228',
                'username'      => 'root',
                'password'      => 'bi7ZtNpjdEewPmsx',
                'database_file' => '',
                'port'          => '3306',
                'charset'       => 'utf8mb4',
                'database_name' => 'demo',
                'option'        => [
                    PDO::ATTR_PERSISTENT       => true, # 长连接
                    PDO::ATTR_TIMEOUT          => 2,
                    PDO::ATTR_EMULATE_PREPARES => false
                ],
                'prefix'        => '',
            ]
        ];
    }

    protected function _slaveConfig(): array
    {
        return [
            'database_type' => 'mysql',
            'server'        => '192.168.4.228',
            'username'      => 'root',
            'password'      => 'bi7ZtNpjdEewPmsx',
            'database_file' => '',
            'port'          => '3306',
            'charset'       => 'utf8mb4',
            'database_name' => 'demo',
            'option'        => [
                PDO::ATTR_PERSISTENT       => true, # 长连接
                PDO::ATTR_TIMEOUT          => 2,
                PDO::ATTR_EMULATE_PREPARES => false
            ],
            'prefix'        => '',
            'slave' => [
                'database_type' => 'mysql',
                'server'        => '192.168.4.228',
                'username'      => 'root',
                'password'      => 'bi7ZtNpjdEewPmsx',
                'database_file' => '',
                'port'          => '3306',
                'charset'       => 'utf8mb4',
                'database_name' => 'demo',
                'option'        => [
                    PDO::ATTR_PERSISTENT       => true, # 长连接
                    PDO::ATTR_TIMEOUT          => 2,
                    PDO::ATTR_EMULATE_PREPARES => false
                ],
                'prefix'        => '',
            ]
        ];
    }
}