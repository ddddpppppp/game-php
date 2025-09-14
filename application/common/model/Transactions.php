<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property integer $user_id 用户ID
 * @property string $type 交易类型
 * @property string $channel_id 支付渠道ID
 * @property float $amount 交易金额
 * @property string $account 账户
 * @property float $actual_amount 实际金额
 * @property float $gift 赠送金额
 * @property string $order_no 订单号
 * @property float $fee 手续费
 * @property string $status 状态
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $completed_at 完成时间
 * @property \DateTime $expired_at 过期时间
 * @property \DateTime $deleted_at 删除时间
 */
class Transactions extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $connection = 'mysql';
    protected $table = 'game_transactions';

    // 主键字段
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 创建时间字段
    protected $createTime = 'created_at';

    // 更新时间字段
    protected $updateTime = false;

    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'gift' => 'float',
        'fee' => 'float',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['id'];
}
