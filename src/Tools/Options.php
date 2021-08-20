<?php
declare(strict_types=1);

namespace Database\Tools;

use PDO;
use InvalidArgumentException;

class Options {
    public $driver;
    public $host;
    public $port;
    public $username;
    public $password;
    public $charset;
    public $dbname;
    public $command;
    public $option;
    public $prefix;
    public $error;
    public $debug;
    public $dsn;

    public function __construct(array $option)
    {
        $this->driver = !empty($option['driver']) ? $option['driver'] : null;
        if(!$this->driver){
            throw new InvalidArgumentException('Configuration is missing `driver`');
        }
        $this->dsn    = !empty($option['dsn']) ? $option['dsn'] : null;
        $this->host   = !empty($option['host']) ? $option['host'] : null;
        if(!$this->host and !$this->dsn){
            throw new InvalidArgumentException('Configuration is missing `host`');
        }
        $this->port   = !empty($option['port']) ? $option['port'] : null;
        if(!$this->port and !$this->dsn){
            throw new InvalidArgumentException('Configuration is missing `port`');
        }
        $this->dbname = !empty($option['dbname']) ? $option['dbname'] : null;
        if(!$this->dbname and !$this->dsn){
            throw new InvalidArgumentException('Configuration is missing `dbname`');
        }
        $this->option = !empty($option['option']) ? $option['option'] : [];
        $this->prefix = !empty($option['prefix']) ? $option['prefix'] : null;
        $this->error  = !empty($option['error']) ? $option['error'] : PDO::ERRMODE_SILENT;
        $this->username = !empty($option['username']) ? $option['username'] : null;
        $this->password = !empty($option['password']) ? $option['password'] : null;
        $this->charset  = !empty($option['charset']) ? $option['charset'] : 'utf8';
        $this->command  = !empty($option['command']) ? $option['command'] : [];
        $this->debug  = !empty($option['debug']) ? $option['debug'] : false;
    }
}