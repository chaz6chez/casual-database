<?php
declare(strict_types=1);

namespace Database;

class Helper {
    /**
     * 判断MYSQL是否是被踢出
     * @param \PDOException $e
     * @return bool
     */
    public static function isGoneAwayError(\PDOException $e) : bool {
        return boolval($e->errorInfo[1] == 2006 or $e->errorInfo[1] == 2013);
    }

    /**
     * 判断MYSQL连接异常
     * @param \PDOException $e
     * @return bool
     */
    public static function isConnectionError(\PDOException $e) : bool {
        return boolval(strpos($e->errorInfo[1],'2') === 0 and strlen($e->errorInfo[1]) === 4);
    }
}