<?php
declare(strict_types=1);

namespace Database\Tools;

final class SQLSTATE {

    /**
     * @var array[]
     * @link http://www.postgres.cn/docs/9.6/errcodes-appendix.html
     */
    protected static $_CLASS_MAP = [
        '00' => [
            'Successful Completion',
            StateConstant::SUCCESS
        ],
        '01' => [
            'Warning',
            StateConstant::ERROR
        ],
        '02' => [
            'No Data (this is also a warning class per the SQL standard)',
            StateConstant::ERROR
        ],
        '03' => [
            'SQL Statement Not Yet Complete',
            StateConstant::ERROR
        ],
        '08' => [
            'Connection Exception',
            StateConstant::RECONNECTION
        ],
        '09' => [
            'Triggered Action Exception',
            StateConstant::ERROR
        ],
        '0A' => [
            'Feature Not Supported',
            StateConstant::ERROR
        ],
        '0B' => [
            'Invalid Transaction Initiation',
            StateConstant::ERROR
        ],
        '0F' => [
            'Locator Exception',
            StateConstant::ERROR
        ],
        '0L' => [
            'Invalid Grantor',
            StateConstant::INTERRUPT
        ],
        '0P' => [
            'Invalid Role Specification',
            StateConstant::ERROR
        ],
        '0Z' => [
            'Diagnostics Exception',
            StateConstant::INTERRUPT
        ],
        '20' => [
            'Case Not Found',
            StateConstant::ERROR
        ],
        '21' => [
            'Cardinality Violation',
            StateConstant::ERROR
        ],
        '22' => [
            'Data Exception',
            StateConstant::ERROR
        ],
        '23' => [
            'Integrity Constraint Violation',
            StateConstant::ERROR
        ],
        '24' => [
            'Invalid Cursor State',
            StateConstant::ERROR
        ],
        '25' => [
            'Invalid Transaction State',
            StateConstant::ERROR
        ],
        '26' => [
            'Invalid SQL Statement Name',
            StateConstant::ERROR
        ],
        '27' => [
            'Triggered Data Change Violation',
            StateConstant::ERROR
        ],
        '28' => [
            'Invalid Authorization Specification',
            StateConstant::ERROR
        ],
        '2B' => [
            'Dependent Privilege Descriptors Still Exist',
            StateConstant::ERROR
        ],
        '2D' => [
            'Invalid Transaction Termination',
            StateConstant::ERROR
        ],
        '2F' => [
            'SQL Routine Exception',
            StateConstant::ERROR
        ],
        '34' => [
            'Invalid Cursor Name',
            StateConstant::ERROR
        ],
        '38' => [
            'External Routine Exception',
            StateConstant::ERROR
        ],
        '39' => [
            'External Routine Invocation Exception',
            StateConstant::ERROR
        ],
        '3B' => [
            'Savepoint Exception',
            StateConstant::INTERRUPT
        ],
        '3D' => [
            'Invalid Catalog Name',
            StateConstant::ERROR
        ],
        '3F' => [
            'Invalid Schema Name',
            StateConstant::ERROR
        ],
        '40' => [
            'Transaction Rollback',
            StateConstant::ERROR
        ],
        '42' => [
            'Syntax Error or Access Rule Violation',
            StateConstant::ERROR
        ],
        '44' => [
            'WITH CHECK OPTION Violation',
            StateConstant::ERROR
        ],
        '53' => [
            'Insufficient Resources',
            StateConstant::INTERRUPT
        ],
        '54' => [
            'Program Limit Exceeded',
            StateConstant::ERROR
        ],
        '55' => [
            'Object Not In Prerequisite State',
            StateConstant::ERROR
        ],
        '57' => [
            'Operator Intervention',
            StateConstant::INTERRUPT
        ],
        '58' => [
            'System Error (errors external to PostgreSQL itself)',
            StateConstant::INTERRUPT
        ],
        '72' => [
            'Snapshot Failure',
            StateConstant::ERROR
        ],
        'F0' => [
            'Configuration File Error',
            StateConstant::INTERRUPT
        ],
        'HV' => [
            'Foreign Data Wrapper Error (SQL/MED)',
            StateConstant::ERROR
        ],
        'P0' => [
            'PL/pgSQL Error',
            StateConstant::INTERRUPT
        ],
        'XX' => [
            'Internal Error',
            StateConstant::INTERRUPT
        ]
    ];

    /**
     * @param string $sqlstate
     * @return array [描述,StateConstant]
     */
    public static function getStateArray(string $sqlstate) : array
    {
        return !empty(self::$_CLASS_MAP[self::getSqlStateClass($sqlstate)]) ?
            self::$_CLASS_MAP[self::getSqlStateClass($sqlstate)] :
            [
                'Undefined Error',
                StateConstant::RECONNECTION
            ];
    }

    /**
     * 获取SQLSTATE所属错误类
     * @param string $sqlstate
     * @return string
     */
    public static function getSqlStateClass(string $sqlstate) : string
    {
        return (string)substr($sqlstate, 0,2);
    }

}