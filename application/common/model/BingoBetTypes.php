<?php

namespace app\common\model;

use think\Model;

/**
 * Bingo游戏玩法配置模型 (BCLC规则)
 * 
 * @property integer $id 主键ID
 * @property string $merchant_id 商户ID
 * @property string $type_name 玩法名称 (Match 10, Match 9, etc.)
 * @property string $type_key 玩法标识 (match_10, match_9, etc.)
 * @property string $description 玩法描述
 * @property float $odds 赔率倍数 (100000, 2500, 250, etc.)
 * @property integer $status 状态：1启用，0禁用
 * @property integer $sort 排序
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $deleted_at 删除时间
 */
class BingoBetTypes extends BaseModel
{
    protected $name = 'bingo_bet_types';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = 'deleted_at';

    protected $type = [
        'odds' => 'float',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    /**
     * 根据商户ID获取玩法列表
     */
    public static function getBetTypesByMerchantId($merchantId)
    {
        return self::where('merchant_id', $merchantId)
            ->where('deleted_at', null)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();
    }

    /**
     * 更新玩法配置
     */
    public static function updateBetType($id, $data)
    {
        return self::where('id', $id)->update($data);
    }

    /**
     * 批量更新状态
     */
    public static function batchUpdateStatus($ids, $status)
    {
        return self::whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * 根据type_key获取玩法信息
     */
    public static function getBetTypeByKey($merchantId, $typeKey)
    {
        return self::where('merchant_id', $merchantId)
            ->where('type_key', $typeKey)
            ->where('deleted_at', null)
            ->find();
    }

    /**
     * 根据匹配数量获取赔率 (Bingo专用)
     * @param string $merchantId 商户ID
     * @param int $matchCount 匹配数量 (0-10)
     * @return array|null 返回玩法配置，包含赔率
     */
    public static function getOddsByMatchCount($merchantId, $matchCount)
    {
        $typeKey = 'match_' . $matchCount;
        return self::where('merchant_id', $merchantId)
            ->where('type_key', $typeKey)
            ->where('status', 1)
            ->where('deleted_at', null)
            ->find();
    }

    /**
     * 获取所有匹配类型的赔率表 (0-10)
     * @param string $merchantId 商户ID
     * @return array 赔率表数组
     */
    public static function getPayoutTable($merchantId)
    {
        $payouts = [];
        for ($i = 0; $i <= 10; $i++) {
            $betType = self::getOddsByMatchCount($merchantId, $i);
            if ($betType) {
                $payouts[$i] = [
                    'match_count' => $i,
                    'odds' => $betType['odds'],
                    'type_name' => $betType['type_name'],
                ];
            }
        }
        return $payouts;
    }
}
