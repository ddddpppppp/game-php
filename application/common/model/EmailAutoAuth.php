<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property string $email 邮箱
 * @property string $access_token access_token
 * @property string $refresh_token refresh_token
 * @property \DateTime $expires_at 过期时间
 * @property \DateTime $created_at created_at
 * @property \DateTime $updated_at updated_at
 * @property \DateTime $deleted_at deleted_at
 */
class EmailAutoAuth extends BaseModel
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
    'expires_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
  );

  // 只读字段
  protected $readonly = ['id'];
}
