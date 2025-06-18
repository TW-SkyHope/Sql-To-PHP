<?php
/**
 • MySQL数据库操作类（基于PDO）- 完全封装版
 • 用户无需编写任何SQL语句，所有操作通过PHP数据类型完成
 */
class MySQLiPDO {
    public $pdo;
    private $error;

    public function __construct($connection) {
        if ($connection instanceof PDO) {
            $this->pdo = $connection;
        } else {
            if (!is_array($connection)) {
                throw new InvalidArgumentException("构造参数必须是PDO实例或配置数组");
            }
            
            $config = $this->validateConnectionConfig($connection);
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
            
            try {
                $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                $this->error = "数据库连接失败: " . $e->getMessage();
                throw new RuntimeException($this->error);
            }
        }
    }

    private function validateConnectionConfig($config) {
        return [
            'host' => $config['host'] ?? 'localhost',
            'port' => isset($config['port']) ? (int)$config['port'] : 3306,
            'dbname' => $config['dbname'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? ''
        ];
    }

    /**
     ◦ 根据条件查询单条记录
     ◦ @param string $table 表名
     ◦ @param array $conditions 条件数组 ['字段名' => '值']
     ◦ @param array $fields 要查询的字段(默认全部)
     ◦ @return array|null 单条记录或null
     */
    public function findOne($table, $conditions = [], $fields = ['*']) {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $fieldList = is_array($fields) ? implode(', ', $fields) : $fields;
        $sql = "SELECT {$fieldList} FROM `{$table}` {$where} LIMIT 1";
        
        $result = $this->internalQuery($sql, $params);
        return $result ? $result[0] : null;
    }

    /**
     ◦ 根据条件查询多条记录
     ◦ @param string $table 表名
     ◦ @param array $conditions 条件数组 ['字段名' => '值']
     ◦ @param array $options 选项 ['fields' => [], 'order' => '', 'limit' => '', 'offset' => '']
     ◦ @return array 结果数组
     */
    public function findAll($table, $conditions = [], $options = []) {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $fields = isset($options['fields']) ? 
                 (is_array($options['fields']) ? implode(', ', $options['fields']) : $options['fields']) : 
                 '*';
        
        $order = isset($options['order']) ? "ORDER BY {$options['order']}" : '';
        $limit = isset($options['limit']) ? "LIMIT {$options['limit']}" : '';
        $offset = isset($options['offset']) ? "OFFSET {$options['offset']}" : '';
        
        $sql = "SELECT {$fields} FROM `{$table}` {$where} {$order} {$limit} {$offset}";
        return $this->internalQuery($sql, $params);
    }

    /**
     ◦ 根据条件查询记录数量
     ◦ @param string $table 表名
     ◦ @param array $conditions 条件数组
     ◦ @return int 记录数量
     */
    public function count($table, $conditions = []) {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $sql = "SELECT COUNT(*) AS count FROM `{$table}` {$where}";
        $result = $this->internalQuery($sql, $params);
        return $result ? (int)$result[0]['count'] : 0;
    }

    /**
     ◦ 参数消毒处理
     */
    private function sanitizeParams($params) {
        return array_map(function($value) {
            if (is_string($value)) {
                return htmlspecialchars($value, ENT_QUOTES);
            }
            return $value;
        }, $params);
    }

    /**
     ◦ 标准化数据格式
     */
    private function normalizeData($data, $columns = []) {
        if (is_object($data)) {
            $data = (array)$data;
        }
        
        // 处理索引数组
        if (isset($data[0])) {
            if (empty($columns)) {
                throw new InvalidArgumentException("索引数组必须提供字段名（columns参数）");
            }
            
            $assocData = [];
            foreach ($columns as $index => $column) {
                if (isset($data[$index])) {
                    $assocData[$column] = $data[$index];
                }
            }
            return $assocData;
        }
        
        return $data;
    }
    
    /**
     ◦ 插入单条数据
     ◦ @param string $table 表名
     ◦ @param array|object $data 数据(支持关联数组/对象/索引数组)
     ◦ @param array $columns 可选字段名(当$data为索引数组时使用)
     ◦ @return int|false 返回插入ID或false
     */
    public function insert($table, $data, $columns = []) {
        $data = $this->normalizeData($data, $columns);
        if (empty($data)) {
            throw new InvalidArgumentException("插入数据不能为空");
        }

        $columns = implode('`, `', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})";
        
        try {
            $this->internalExecute($sql, $data);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->error = "插入失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 批量插入数据
     ◦ @param string $table 表名
     ◦ @param array $dataList 数据列表
     ◦ @param array $columns 可选字段名(当$dataList包含索引数组时使用)
     ◦ @return int|false 返回影响行数或false
     */
    public function batchInsert($table, $dataList, $columns = []) {
        if (empty($dataList)) return 0;
        
        // 标准化首行数据并确定列顺序
        $firstData = $this->normalizeData($dataList[0], $columns);
        $columnOrder = array_keys($firstData);
        $columnStr = implode('`, `', $columnOrder);
        
        $placeholders = [];
        $params = [];
        $placeholderRow = '(' . implode(', ', array_fill(0, count($columnOrder), '?')) . ')';
        
        foreach ($dataList as $row) {
            $rowData = $this->normalizeData($row, $columnOrder);
            $placeholders[] = $placeholderRow;
            // 严格按照列顺序添加参数
            foreach ($columnOrder as $col) {
                $params[] = $rowData[$col] ?? null;
            }
        }
        
        $sql = "INSERT INTO `{$table}` (`{$columnStr}`) VALUES " . implode(', ', $placeholders);
        
        try {
            return $this->internalExecute($sql, $params);
        } catch (PDOException $e) {
            $this->error = "批量插入失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 更新记录
     ◦ @param string $table 表名
     ◦ @param array|object $data 更新数据
     ◦ @param array $conditions 条件数组 ['字段名' => '值']
     ◦ @return int|false 影响行数或false
     */
    public function update($table, $data, $conditions = []) {
        $data = $this->normalizeData($data);
        $set = [];
        $setParams = [];
        
        foreach ($data as $key => $value) {
            $paramKey = ':__set_' . $key; // 使用唯一前缀
            $set[] = "`{$key}` = {$paramKey}";
            $setParams[$paramKey] = $value;
        }
        
        $where = '';
        $whereParams = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`{$field}` = :__where_{$field}";
                $whereParams[":__where_{$field}"] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " {$where}";
        
        try {
            return $this->internalExecute($sql, array_merge($setParams, $whereParams));
        } catch (PDOException $e) {
            $this->error = "更新失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 删除记录
     ◦ @param string $table 表名
     ◦ @param array $conditions 条件数组 ['字段名' => '值']
     ◦ @return int|false 影响行数或false
     */
    public function delete($table, $conditions = []) {
        $where = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $field => $value) {
                $whereParts[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $value;
            }
            $where = 'WHERE ' . implode(' AND ', $whereParts);
        }
        
        $sql = "DELETE FROM `{$table}` {$where}";
        
        try {
            return $this->internalExecute($sql, $params);
        } catch (PDOException $e) {
            $this->error = "删除失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 事务处理
     */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }
    public function commit() {
        $this->pdo->commit();
    }
    public function rollBack() {
        $this->pdo->rollBack();
    }

    /**
     ◦ 获取表结构
     ◦ @param string $table 表名
     ◦ @return array 表结构数组
     */
    public function getTableStructure($table) {
        $sql = "DESCRIBE `{$table}`";
        return $this->internalQuery($sql);
    }

    /**
     ◦ 创建表
     ◦ @param string $table 表名
     ◦ @param array $fields 字段定义(支持多种格式)
     ◦ @param array $options 表选项(ENGINE/CHARSET等)
     ◦ @return bool 是否成功
     */
    public function createTable($table, $fields, $options = []) {
        $fieldDefinitions = [];
        
        foreach ($fields as $name => $definition) {
            // 简化格式: '字段名' => '类型 属性'
            if (is_string($definition)) {
                $fieldDefinitions[] = "`{$name}` {$definition}";
            } 
            // 标准格式: ['name'=>'字段', 'type'=>'类型', ...]
            else if (is_array($definition)) {
                $fieldDefinitions[] = $this->buildColumnDefinition($definition);
            }
        }
        
        $engine = $options['engine'] ?? 'InnoDB';
        $charset = $options['charset'] ?? 'utf8mb4';
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (" . 
               implode(', ', $fieldDefinitions) . 
               ") ENGINE={$engine} DEFAULT CHARSET={$charset}";
        
        try {
            return $this->internalExecute($sql) !== false;
        } catch (PDOException $e) {
            $this->error = "创建表失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 构建列定义
     */
    private function buildColumnDefinition($field) {
        if (!isset($field['name']) || !isset($field['type'])) {
            throw new InvalidArgumentException("字段定义必须包含name和type属性");
        }

        $definition = "`{$field['name']}` {$field['type']}";

        // 处理长度定义
        if (isset($field['length'])) {
            $length = is_array($field['length']) ? 
                     implode(',', $field['length']) : 
                     $field['length'];
            $definition .= "({$length})";
        }

        // 处理无符号
        if (!empty($field['unsigned'])) {
            $definition .= ' UNSIGNED';
        }

        // 处理非空约束
        $nullHandled = false;
        if (isset($field['notnull'])) {
            $definition .= $field['notnull'] ? ' NOT NULL' : ' NULL';
            $nullHandled = true;
        }

        // 处理默认值
        if (array_key_exists('default', $field)) {
            if ($field['default'] === null) {
                $definition .= ' DEFAULT NULL';
            } elseif (strtoupper($field['default']) === 'CURRENT_TIMESTAMP') {
                $definition .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $defaultValue = $this->formatDefaultValue($field);
                $definition .= " DEFAULT {$defaultValue}";
            }
        } elseif (!$nullHandled) {
            $definition .= ' NULL';
        }

        // 处理自增
        if (!empty($field['auto_increment'])) {
            $definition .= ' AUTO_INCREMENT';
        }

        // 处理注释
        if (isset($field['comment'])) {
            $definition .= " COMMENT '" . str_replace("'", "''", $field['comment']) . "'";
        }

        return $definition;
    }

    /**
     ◦ 格式化默认值
     */
    private function formatDefaultValue($field) {
        $type = strtoupper($field['type']);
        $default = $field['default'];
        
        // 数字类型且不是字符串类字段
        if (is_numeric($default) && !in_array($type, ['CHAR','VARCHAR','TEXT','ENUM','SET'])) {
            return $default;
        }
        
        // 布尔值转换为数字
        if (is_bool($default)) {
            return $default ? 1 : 0;
        }
        
        // 其他情况加引号
        return "'" . str_replace("'", "''", $default) . "'";
    }

    /**
     ◦ 修改表结构
     ◦ @param string $table 表名
     ◦ @param array $alter 修改操作数组
     ◦ @return bool 是否成功
     */
    public function alterTable($table, $alter) {
        $sql = "ALTER TABLE `{$table}` " . implode(', ', array_map(function($action) {
            if (!isset($action['type']) || !isset($action['field'])) {
                throw new InvalidArgumentException("ALTER操作必须包含type和field属性");
            }
            
            $definition = "{$action['type']} `{$action['field']}`";
            if (isset($action['definition'])) {
                $definition .= " {$action['definition']}";
            }
            if (isset($action['after'])) {
                $definition .= " AFTER `{$action['after']}`";
            }
            return $definition;
        }, $alter));
        
        try {
            return $this->internalExecute($sql) !== false;
        } catch (PDOException $e) {
            $this->error = "修改表结构失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 获取错误信息
     ◦ @return string 错误信息
     */
    public function getError() {
        return $this->error;
    }

    /********************* 内部方法 *********************/
    /**
     ◦ 内部查询方法
     */
    protected function internalQuery($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->error = "查询失败: " . $e->getMessage();
            return false;
        }
    }

    /**
     ◦ 内部执行方法
     */
    protected function internalExecute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->error = "执行失败: " . $e->getMessage();
            return false;
        }
    }
}