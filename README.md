
# casual-database

**Casual Long-living MySQL connection for daemon.**

    This project is forked from Medoo and made a resident memory adaptation.
    Thanks Medoo.

## 说明
- 基于 [Medoo2.1.x](https://medoo.in/doc) 语法
  - [where](./docs/where.md)
  - [select](./docs/where.md)
    
- 兼容链式调用
  
- 符合常驻内存应用的长连接
  
- 支持 驱动
  
  |Name|Driver|
  |:---:|:---:|
  |MySQL, MariaDB|	php_pdo_mysql|
  |ODBC	|php_pdo_odbc|
  |SQLite	|php_pdo_sqlite|
  |PostgreSQL|	php_pdo_pgsql|
  
- 符合SQL标准错误码的 SQLSTATE 映射表
    - [SQLSTATE参考表](./docs/SQLSTATE.md)  
- 预置事件 

  |事件|类型|描述|
  |:---:|:---:|:---:|
  |onBeforePrepare|callable|预处理前|
  |onBeforeBind|callable|绑定前|
  |onBeforeExec|callable|执行前|
  |onAfterExec|callable|执行后|
  
- 支持 logger  

