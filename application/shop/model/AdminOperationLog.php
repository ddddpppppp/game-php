<?php
namespace app\shop\model;

use app\common\model\BaseModel;


/**
 * @property integer $id id
 * @property string $admin_id 管理员id
 * @property string $merchant_id 商户id
 * @property string $content content
 * @property \datetime $created_at created_at
 * @property \datetime $deleted_at deleted_at
 */
class AdminOperationLog extends BaseModel
{
  use \think\model\concern\SoftDelete;

  protected $connection = 'mysql';

  protected $deleteTime = 'deleted_at';



  // 主键字段
  protected $pk = 'id';

  // 自动时间戳
  protected $autoWriteTimestamp = true;

  // 创建时间字段
  protected $createTime = 'created_at';

  // 更新时间字段
  protected $updateTime = 'update_time';

  // 时间字段格式
  protected $dateFormat = 'Y-m-d H:i:s';

  // 字段类型转换
  protected $type = array(
    'id' => 'integer',
    'created_at' => 'datetime',
    'deleted_at' => 'datetime',
  );

  // 只读字段
  protected $readonly = ['id'];
}
