<?php
declare(strict_types=1);

namespace Database\Tools;

class StateConstant {
    // 成功
    const SUCCESS      = 1;
    // 普通
    const ERROR        = 0;
    // 重连
    const RECONNECTION = -1;
    // 中断
    const INTERRUPT    = -2;

    public static function isSuccess(int $constant) : bool
    {
        return boolval($constant === self::SUCCESS);
    }

    public static function isInterrupt(int $constant) : bool
    {
        return boolval($constant === self::INTERRUPT);
    }

    public static function isReconnection(int $constant) : bool
    {
        return boolval($constant === self::RECONNECTION);
    }

    public static function isError(int $constant) : bool
    {
        return boolval($constant === self::ERROR);
    }
}