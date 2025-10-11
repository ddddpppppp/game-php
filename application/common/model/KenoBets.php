<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * Keno投注记录模型 (BCLC规则: 1-80号码)
 * 
 * @property integer $id 主键ID
 * @property string $merchant_id 商户ID
 * @property string $user_id 用户ID
 * @property string $period_number 期号
 * @property string $selected_numbers 玩家选择的10个号码 (JSON, 1-80)
 * @property string $drawn_numbers 开出的20个号码 (JSON, 1-80)
 * @property string $matched_numbers 匹配的号码 (JSON)
 * @property integer $match_count 匹配数量 (0-10)
 * @property float $amount 投注金额
 * @property float $multiplier 投注时的赔率
 * @property float $win_amount 中奖金额
 * @property string $status 状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消
 * @property string $ip 投注IP地址
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $settled_at 结算时间
 * @property \DateTime $deleted_at 删除时间
 */
class KenoBets extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $connection = 'mysql';
    protected $table = 'game_keno_bets';

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
        'match_count' => 'integer',
        'amount' => 'float',
        'multiplier' => 'float',
        'win_amount' => 'float',
    ];

    // 只读字段
    protected $readonly = ['id', 'merchant_id', 'user_id', 'period_number', 'created_at'];

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
     * 获取玩家选择的号码数组
     */
    public function getSelectedNumbersArrayAttribute()
    {
        return json_decode($this->selected_numbers, true) ?: [];
    }

    /**
     * 获取开出的号码数组
     */
    public function getDrawnNumbersArrayAttribute()
    {
        return json_decode($this->drawn_numbers, true) ?: [];
    }

    /**
     * 获取匹配的号码数组
     */
    public function getMatchedNumbersArrayAttribute()
    {
        return json_decode($this->matched_numbers, true) ?: [];
    }

    /**
     * 获取潜在赢利金额
     */
    public function getPotentialWinAttribute()
    {
        if ($this->status === self::STATUS_PENDING) {
            // 对于pending状态，返回最大可能赢利 (Match 10)
            return $this->amount * 100000;
        }
        return $this->win_amount;
    }

    /**
     * 获取实际盈亏
     */
    public function getProfitLossAttribute()
    {
        if ($this->status === self::STATUS_WIN) {
            return $this->win_amount - $this->amount;
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
        return $this->belongsTo(KenoDraws::class, 'period_number', 'period_number');
    }

    /**
     * 创建投注记录 (Keno规则)
     */
    public static function createBet($data)
    {
        $betData = [
            'merchant_id' => $data['merchant_id'],
            'user_id' => $data['user_id'],
            'period_number' => $data['period_number'],
            'selected_numbers' => $data['selected_numbers'], // JSON string
            'amount' => $data['amount'],
            'multiplier' => $data['multiplier'] ?? 0,
            'match_count' => 0,
            'win_amount' => 0,
            'status' => self::STATUS_PENDING,
            'ip' => $data['ip'] ?? request()->ip(),
        ];

        return self::create($betData);
    }
    /**
     * 计算匹配数量
     * @param array $selectedNumbers 玩家选择的号码
     * @param array $drawnNumbers 开出的号码
     * @return array 返回 [matchedNumbers, matchCount]
     */
    public static function calculateMatches($selectedNumbers, $drawnNumbers)
    {
        $matchedNumbers = array_intersect($selectedNumbers, $drawnNumbers);
        $matchCount = count($matchedNumbers);
        return [
            'matched_numbers' => array_values($matchedNumbers),
            'match_count' => $matchCount
        ];
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
     * 获取某期的投注统计 (Keno规则)
     */
    public static function getPeriodStats($periodNumber)
    {
        return self::where('period_number', $periodNumber)
            ->field([
                'match_count',
                'COUNT(*) as bet_count',
                'SUM(amount) as total_amount',
                'SUM(win_amount) as total_payout',
                'AVG(match_count) as avg_matches'
            ])
            ->group('match_count')
            ->select();
    }
}
