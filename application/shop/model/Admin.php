<?php
namespace app\shop\model;

use app\common\model\BaseModel;


/**
 * @property integer $id id
 * @property string $uuid uuid
 * @property string $nickname nickname
 * @property string $avatar avatar
 * @property string $username username
 * @property string $password password
 * @property string $salt salt
 * @property string $merchant_id 对应merchant表
 * @property integer $role_id 对应role表
 * @property integer $status -1冻结，1开启
 * @property \DateTime $created_at created_at
 * @property \DateTime $updated_at updated_at
 * @property \DateTime $deleted_at deleted_at
 * @property string $parent_id 推荐人ID
 * @property string $path 路径（记录所有上级ID包括自己，如0-1-2-3）
 * @property integer $depth 层级深度
 * @property float $balance 余额
 */
class Admin extends BaseModel
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
    protected $type = array (
  'id' => 'integer',
  'role_id' => 'integer',
  'status' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
  'depth' => 'integer',
  'balance' => 'float',
);
    
    // 只读字段
    protected $readonly = ['id'];
    
}
