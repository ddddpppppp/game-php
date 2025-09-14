<?php

namespace app\shop\model;

use app\common\model\BaseModel;


/**
 * @property integer $id id
 * @property string $name name
 * @property integer $type type
 * @property string $merchant_id 商户id
 * @property string $access access
 * @property \datetime $created_at created_at
 * @property \datetime $updated_at updated_at
 * @property \datetime $deleted_at deleted_at
 */
class Role extends BaseModel
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
  protected $updateTime = 'updated_at';

  // 时间字段格式
  protected $dateFormat = 'Y-m-d H:i:s';

  // 字段类型转换
  protected $type = array(
    'id' => 'integer',
    'type' => 'integer',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
  );

  // 只读字段
  protected $readonly = ['id'];
}
