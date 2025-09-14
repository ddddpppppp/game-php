<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property integer $user_id 用户ID
 * @property string $type 变动类型：deposit-充值, withdraw-提现, game_bet-投注, game_win-收益
 * @property float $amount 变动金额
 * @property float $balance_before 变动前余额
 * @property float $balance_after 变动后余额
 * @property string $description 描述
 * @property string $related_id 关联ID
 * @property \DateTime $updated_at 更新时间
 */
class UserBalances extends BaseModel
{
    protected $connection = 'mysql';
    protected $table = 'game_user_balances';

    // 主键字段
    protected $pk = 'id';

    // 自动时间戳 - 只有更新时间
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = false;
    protected $updateTime = 'updated_at';

    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'balance' => 'float',
    ];

    // 只读字段
    protected $readonly = ['id'];
}
