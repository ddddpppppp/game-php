<?php

namespace app\common\model;

use think\Model;

/**
 * Canada28游戏期数模型
 * 
 * @property integer $id 主键ID
 * @property string $merchant_id 商户ID
 * @property string $type_name 玩法名称
 * @property string $type_key 玩法标识
 * @property string $description 玩法描述
 * @property float $odds 赔率倍数
 * @property integer $status 状态：1启用，0禁用
 * @property integer $sort 排序
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $deleted_at 删除时间
 */
class Canada28BetTypes extends Model
{
    protected $name = 'canada28_bet_types';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = 'deleted_at';

    protected $type = [
        'odds' => 'decimal:2',
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
}
