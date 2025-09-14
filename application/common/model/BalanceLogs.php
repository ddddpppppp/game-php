<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property integer $user_id 用户ID
 * @property string $coin_type 币种
 * @property string $type 变动类型
 * @property float $amount 变动金额
 * @property float $balance_before 变动前余额
 * @property float $balance_after 变动后余额
 * @property string $description 描述
 * @property integer $related_id 关联ID
 * @property \DateTime $created_at 创建时间
 */
class BalanceLogs extends BaseModel
{
    protected $connection = 'mysql';
    protected $table = 'game_balance_logs';

    // 主键字段
    protected $pk = 'id';

    // 自动时间戳 - 只有创建时间
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'related_id' => 'integer',
        'created_at' => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['id'];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }
}
