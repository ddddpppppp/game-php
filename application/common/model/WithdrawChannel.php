<?php

namespace app\common\model;

use app\common\model\BaseModel;


/**
 * @property integer $id id
 * @property string $name 名称
 * @property string $type 类型
 * @property float $rate 费率
 * @property float $charge_fee 单笔手续费
 * @property string $remark 备注
 * @property integer $status -1停用，1开启
 * @property integer $is_backup 是否备用渠道
 * @property array $params 渠道参数
 * @property integer $sort sort
 * @property \DateTime $created_at created_at
 * @property \DateTime $updated_at updated_at
 * @property \DateTime $deleted_at deleted_at
 * @property float $day_limit_money 每日限额
 * @property integer $day_limit_count 每日限额次数
 */
class WithdrawChannel extends BaseModel
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
    'rate' => 'float',
    'charge_fee' => 'float',
    'status' => 'integer',
    'is_backup' => 'integer',
    'params' => 'json',
    'sort' => 'integer',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'day_limit_money' => 'float',
    'day_limit_count' => 'integer',
  );

  // 只读字段
  protected $readonly = ['id'];
}
