<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';
use Database\Test\Model\DemoModel;

$model = new DemoModel();
$model->dbName()->beginTransaction(2);

try {

    echo 'before the select' . PHP_EOL;
    $res = $model->dbName()->table($model->table())->select();
    echo 'after the select' . PHP_EOL;
}catch (Throwable $exception){
    exit('select' . $exception->getMessage() . PHP_EOL);
}

try {
    echo 'before the commit' . PHP_EOL;
    sleep(3);
    $model->dbName()->commit();
    echo 'after the commit' . PHP_EOL;
}catch (Throwable $exception){
    var_dump($model->dbName()->last());
    exit('commit' . $exception->getMessage() . PHP_EOL);
}

var_dump($res);