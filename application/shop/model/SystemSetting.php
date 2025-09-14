<?php

namespace app\shop\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property string $name 设置名称
 * @property string $title 设置标题
 * @property string $description 设置描述
 * @property string $config 设置配置JSON数据
 * @property integer $status 状态
 * @property integer $sort 排序
 * @property \DateTime $created_at created_at
 * @property \DateTime $updated_at updated_at
 * @property \DateTime $deleted_at deleted_at
 */
class SystemSetting extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';

    protected $connection = 'mysql';

    // 主键字段
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 创建时间字段
    protected $createTime = 'created_at';

    // 更新时间字段
    protected $updateTime = 'updated_at';

    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型转换
    protected $type = array(
        'id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    );

    // 只读字段
    protected $readonly = ['id'];

    /**
     * 获取配置数据
     * @return array
     */
    public function getConfigAttr($value)
    {
        return json_decode($value, true) ?: [];
    }

    /**
     * 设置配置数据
     * @param array $value
     * @return string
     */
    public function setConfigAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    /**
     * 根据名称获取设置
     * @param string $name
     * @return SystemSetting|null
     */
    public static function getByName($name)
    {
        return self::where('name', $name)->where('status', 1)->find();
    }

    /**
     * 批量获取设置
     * @param array $names
     * @return array
     */
    public static function getByNames($names)
    {
        $list = self::where('name', 'in', $names)->where('status', 1)->select();
        $result = [];
        foreach ($list as $item) {
            $result[$item->name] = $item;
        }
        return $result;
    }

    /**
     * 更新设置
     * @param string $name
     * @param array $config
     * @return bool
     */
    public static function updateConfig($name, array $config)
    {
        return self::where('name', $name)->update(['config' => json_encode($config)]);
    }
}
