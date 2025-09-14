<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * Canada28游戏期数模型
 * 
 * @property integer $id 主键ID
 * @property string $period_number 期号，如：3333197
 * @property integer $status 状态：0-等待开奖，1-开奖中，2-已开奖，3-已结算
 * @property \DateTime $start_at 开始投注时间
 * @property \DateTime $end_at 停止投注时间
 * @property \DateTime $draw_at 开奖时间
 * @property array $result_numbers 开奖号码，JSON格式存储三个数字
 * @property integer $result_sum 开奖结果总和(0-27)
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $deleted_at 删除时间
 */
class Canada28Draws extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $connection = 'mysql';
    protected $table = 'game_canada28_draws';

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
        'status' => 'integer',
        'result_numbers' => 'json',
        'result_sum' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'draw_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['id', 'period_number'];

    // 状态常量
    const STATUS_WAITING = 0;   // 等待开奖
    const STATUS_DRAWING = 1;   // 开奖中
    const STATUS_DRAWN = 2;     // 已开奖
    const STATUS_SETTLED = 3;   // 已结算

    /**
     * 状态文本映射
     */
    public static function getStatusText($status)
    {
        $statusMap = [
            self::STATUS_WAITING => '等待开奖',
            self::STATUS_DRAWING => '开奖中',
            self::STATUS_DRAWN => '已开奖',
            self::STATUS_SETTLED => '已结算',
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 状态文本映射
     */
    public static function getStatusCnText($status)
    {
        $statusMap = [
            self::STATUS_WAITING => '等待开奖',
            self::STATUS_DRAWING => '开奖中',
            self::STATUS_DRAWN => '已开奖',
            self::STATUS_SETTLED => '已结算',
        ];
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取当前期游戏
     */
    public static function getCurrentDraw()
    {
        return self::where('status', 'in', [self::STATUS_WAITING, self::STATUS_DRAWING])
            ->order('period_number desc')
            ->find();
    }

    /**
     * 获取最新的已开奖期数
     */
    public static function getLatestDrawn()
    {
        return self::where('status', 'in', [self::STATUS_DRAWN, self::STATUS_SETTLED])
            ->order('period_number desc')
            ->find();
    }

    /**
     * 创建新期游戏
     */
    public static function createNewDraw($periodNumber, $startAt, $endAt, $drawAt)
    {
        return self::create([
            'period_number' => $periodNumber,
            'status' => self::STATUS_WAITING,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'draw_at' => $drawAt,
        ]);
    }

    /**
     * 设置开奖结果
     */
    public function setResult($numbers, $sum)
    {
        return $this->save([
            'result_numbers' => $numbers,
            'result_sum' => $sum,
            'status' => self::STATUS_DRAWN,
        ]);
    }

    /**
     * 关联投注记录
     */
    public function bets()
    {
        return $this->hasMany(Canada28Bets::class, 'period_number', 'period_number');
    }
}
