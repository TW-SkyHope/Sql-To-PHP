# Sql-To-PHP
<h2>一个用于在php项目中简便操作各种数据库方法的库，而无需书写任何sql语法</h2>  
<h3>适用于php8.0+(其他版本大多也可以)，目前仅支持Mysql(推荐8.0+)</h3>

---
<h2>部署方法(直接使用mysql.php代码)：</h2>  
<h3>

- 将mysql.php放入项目文件中
- require "mysql.php"在需要的php文件开头导入
- 即可使用mysql.php中的函数简易操作mysql

</h3>

---
<h2>使用方法：</h2>
<h3>
  
> 连接数据库 
</h3> 

<h3>通过PDO实例连接</h3>

```
$pdo = new PDO('mysql:host=localhost;dbname=test;charset=utf8mb4', 'username', 'password', [  
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION  
]);  
$db = new MySQLiPDO($pdo);  
```
<h3>通过配置数组连接</h3>

```
$config = [  
    'host' => 'localhost',  // 数据库服务器地址  
    'port' => 3306,         // 数据库端口(默认3306)  
    'dbname' => 'test',     // 数据库名  
    'username' => 'username', // 数据库用户名  
    'password' => 'password'  // 数据库密码  
];  
  
$db = new MySQLiPDO($config);  
```

<h3>
  
> 连接数据库 
</h3> 
