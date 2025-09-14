<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * Canada28投注记录模型
 * 
 * @property integer $id 主键ID
 * @property string $merchant_id 商户ID
 * @property string $user_id 用户ID
 * @property string $period_number 期号
 * @property string $bet_type 投注类型：high/low/odd/even/num_0等
 * @property string $bet_name 投注名称：High/Low/Number 0等
 * @property float $amount 投注金额
 * @property float $multiplier 投注时的赔率
 * @property string $status 状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消
 * @property string $ip 投注IP地址
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $deleted_at 删除时间
 */
class Canada28Bets extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $connection = 'mysql';
    protected $table = 'game_canada28_bets';

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
    protected $type = [
        'id' => 'integer',
        'amount' => 'float',
        'multiplier' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['id', 'merchant_id', 'user_id', 'period_number'];

    // 状态常量
    const STATUS_PENDING = 'pending';  // 等待开奖
    const STATUS_WIN = 'win';          // 已中奖
    const STATUS_LOSE = 'lose';        // 未中奖
    const STATUS_CANCEL = 'cancel';    // 已取消

    /**
     * 状态文本映射
     */
    public static function getStatusCnText($status)
    {
        $statusMap = [
            self::STATUS_PENDING => '等待开奖',
            self::STATUS_WIN => '已中奖',
            self::STATUS_LOSE => '未中奖',
            self::STATUS_CANCEL => '已取消',
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取潜在赢利金额
     */
    public function getPotentialWinAttribute()
    {
        return $this->amount * $this->multiplier;
    }

    /**
     * 获取实际盈亏
     */
    public function getProfitLossAttribute()
    {
        if ($this->status === self::STATUS_WIN) {
            return $this->getPotentialWinAttribute() - $this->amount;
        } elseif ($this->status === self::STATUS_LOSE) {
            return -$this->amount;
        }
        return 0;
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    /**
     * 关联期数
     */
    public function draw()
    {
        return $this->belongsTo(Canada28Draws::class, 'period_number', 'period_number');
    }

    /**
     * 创建投注记录
     */
    public static function createBet($data)
    {
        $betData = [
            'merchant_id' => $data['merchant_id'],
            'user_id' => $data['user_id'],
            'period_number' => $data['period_number'],
            'bet_type' => $data['bet_type'],
            'bet_name' => $data['bet_name'],
            'amount' => $data['amount'],
            'multiplier' => $data['multiplier'],
            'status' => self::STATUS_PENDING,
            'ip' => $data['ip'] ?? request()->ip(),
        ];

        return self::create($betData);
    }

    /**
     * 批量结算投注
     */
    public static function settleBets($periodNumber, $resultSum, $resultNumbers)
    {
        $bets = self::where('period_number', $periodNumber)
            ->where('status', self::STATUS_PENDING)
            ->select();

        foreach ($bets as $bet) {
            $isWin = self::checkWin($bet->bet_type, $resultSum, $resultNumbers);
            $bet->status = $isWin ? self::STATUS_WIN : self::STATUS_LOSE;
            $bet->save();
        }

        return true;
    }

    /**
     * 检查是否中奖
     */
    public static function checkWin($betType, $resultSum, $resultNumbers)
    {
        switch ($betType) {
            case 'high':
                return $resultSum >= 14 && $resultSum <= 27;
            case 'low':
                return $resultSum >= 0 && $resultSum <= 13;
            case 'odd':
                return $resultSum % 2 == 1;
            case 'even':
                return $resultSum % 2 == 0;
            case 'extreme_high':
                return $resultSum >= 22 && $resultSum <= 27;
            case 'extreme_low':
                return $resultSum >= 0 && $resultSum <= 5;
            default:
                // 处理单数字投注
                if (strpos($betType, 'num_') === 0) {
                    $number = (int)str_replace('num_', '', $betType);
                    return $resultSum == $number;
                }
                return false;
        }
    }

    /**
     * 获取用户投注历史
     */
    public static function getUserBetHistory($userId, $limit = 20)
    {
        return self::where('user_id', $userId)
            ->order('created_at desc')
            ->limit($limit)
            ->select();
    }

    /**
     * 获取某期的投注统计
     */
    public static function getPeriodStats($periodNumber)
    {
        return self::where('period_number', $periodNumber)
            ->field([
                'bet_type',
                'COUNT(*) as bet_count',
                'SUM(amount) as total_amount',
                'SUM(CASE WHEN status = "win" THEN amount * multiplier ELSE 0 END) as total_payout'
            ])
            ->group('bet_type')
            ->select();
    }
}
