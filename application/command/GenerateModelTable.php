<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Db;

class GenerateModelTable extends Command
{
    protected function configure()
    {
        $this->setName('make:model-table')
            ->addArgument('name', Argument::REQUIRED, 'Model name (e.g. index/User)')
            ->addOption('table', 't', Option::VALUE_REQUIRED, 'Table name')
            ->addOption('softdelete', 's', Option::VALUE_NONE, 'Add soft delete trait')
            ->addOption('connection', 'c', Option::VALUE_OPTIONAL, 'Database connection')
            ->setDescription('Create model with field attributes from database table');
    }

    protected function execute(Input $input, Output $output)
    {
        $name = trim($input->getArgument('name'));
        $table = $input->getOption('table');
        $softDelete = $input->getOption('softdelete');
        $connection = $input->getOption('connection') ?: '';

        // 解析模块和模型名
        if (strpos($name, '/') !== false) {
            list($module, $modelName) = explode('/', $name, 2);
        } else {
            $module = 'common';
            $modelName = $name;
        }

        // 检查模块是否存在
        $modulePath = app()->getAppPath() . $module;
        if (!is_dir($modulePath)) {
            $output->writeln("<error>Module '{$module}' not exists!</error>");
            return;
        }

        // 获取表名
        if (empty($table)) {
            $table = $this->getDefaultTableName($modelName);
        }

        // 检查表是否存在
        try {
            list($fields, $primaryKey) = $this->getTableFields($table, $connection);
            if (empty($fields)) {
                $output->writeln("<error>Table '{$table}' not exists or has no fields!</error>");
                return;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error reading table: " . $e->getMessage() . "</error>");
            return;
        }

        // 创建model目录
        $modelDir = $modulePath . '/model';
        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        // 模型文件路径
        $modelFile = $modelDir . '/' . $modelName . '.php';

        // if (file_exists($modelFile)) {
        //     $output->writeln("<error>Model '{$modelName}' already exists!</error>");
        //     return;
        // }

        // 生成模型内容
        $content = $this->buildModelContent($module, $modelName, $primaryKey, $fields, $softDelete, $connection);

        // 写入文件
        file_put_contents($modelFile, $content);

        $output->writeln("<info>Model created successfully:</info> " . $modelFile);
    }

    protected function getTableFields($table, $connection = '')
    {
        $prefix = config('database.prefix');
        $fullTableName = $prefix . $table;

        $sql = "SHOW FULL COLUMNS FROM `{$fullTableName}`";
        $fields = Db::connect($connection)->query($sql);
        $primaryKey = $fields[0]['Field'];

        $result = [];
        foreach ($fields as $field) {
            $result[$field['Field']] = [
                'type' => $this->parseFieldType($field['Type']),
                'comment' => $field['Comment'],
                'default' => $field['Default'],
                'nullable' => $field['Null'] === 'YES',
            ];
        }

        return [$result, $primaryKey];
    }

    protected function parseFieldType($type)
    {
        if (strpos($type, 'int') !== false)
            return 'integer';
        if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false)
            return 'float';
        if (strpos($type, 'bool') !== false)
            return 'boolean';
        if (strpos($type, 'date') !== false || strpos($type, 'time') !== false || strpos($type, 'year') !== false)
            return 'datetime';
        if (strpos($type, 'json') !== false)
            return 'json';
        return 'string';
    }

    protected function buildModelContent($module, $modelName, $primaryKey, $fields, $softDelete, $connection)
    {
        $namespace = 'app\\' . $module . '\\model';
        $softDeleteTrait = $softDelete ? "use \\think\\model\\concern\\SoftDelete;\n" : '';
        $softDeleteProperty = $softDelete ? "protected \$deleteTime = 'deleted_at';\n" : '';
        $connectionProperty = $connection ? "protected \$connection = '{$connection}';\n" : '';

        // 生成字段类型转换
        $typeMap = [];
        foreach ($fields as $field => $info) {
            if ($info['type'] !== 'string') {
                $typeMap[$field] = $info['type'];
            }
        }
        $typeMapContent = var_export($typeMap, true);

        // 生成属性注释
        $propertyComments = '';
        foreach ($fields as $field => $info) {
            $comment = $info['comment'] ?: $field;
            $type = $info['type'];

            // 转换类型格式
            if ($type === 'datetime') {
                $type = '\\DateTime';
            } elseif ($type === 'json') {
                $type = 'array';
            }

            $propertyComments .= " * @property {$type} \${$field} {$comment}\n";
        }

        $content = <<<EOT
<?php
namespace {$namespace};

use app\common\model\BaseModel;


/**
{$propertyComments} */
class {$modelName} extends BaseModel
{
    {$softDeleteTrait}
    {$softDeleteProperty}
    {$connectionProperty}
  
    // 主键字段
    protected \$pk = '{$primaryKey}';
    
    // 自动时间戳
    protected \$autoWriteTimestamp = true;
    
    // 创建时间字段
    protected \$createTime = '{$this->guessTimeField($fields, 'create')}';
    
    // 更新时间字段
    protected \$updateTime = '{$this->guessTimeField($fields, 'update')}';
    
    // 时间字段格式
    protected \$dateFormat = 'Y-m-d H:i:s';
    
    // 字段类型转换
    protected \$type = {$typeMapContent};
    
    // 只读字段
    protected \$readonly = ['{$primaryKey}'];
    
}

EOT;

        return $content;
    }

    protected function guessTimeField($fields, $type = 'create')
    {
        $possibleNames = [
            'create' => ['create_time', 'created_at', 'createtime', 'add_time'],
            'update' => ['update_time', 'updated_at', 'updatetime', 'modify_time']
        ];

        foreach ($possibleNames[$type] as $name) {
            if (isset($fields[$name])) {
                return $name;
            }
        }

        return $type === 'create' ? 'create_time' : 'update_time';
    }

    protected function getDefaultTableName($modelName)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
    }
}
