<?php

namespace app\shop\service;

use app\shop\enum\Merchant as EnumMerchant;

class Merchant
{

    /**
     * @param string $uuid
     * @return \app\shop\model\Merchant|null
     */
    public static function getMerchantInfo($uuid)
    {
        $mer = \app\shop\model\Merchant::where(['uuid' => $uuid])->find();
        return $mer ?: null;
    }

    public static function saveMerchantConfig($merchantId, $name, $value)
    {
        $mer = \app\shop\model\MerchantConfig::where(['merchant_id' => $merchantId, 'name' => $name])->value('id');
        if ($mer) {
            \app\shop\model\MerchantConfig::update(['value' => $value], ['id' => $mer]);
        } else {
            \app\shop\model\MerchantConfig::create(['merchant_id' => $merchantId, 'name' => $name, 'value' => $value]);
        }
        self::clearMerchantConfigCache($merchantId, $name);
    }

    public static function getMerchantConfig($merchantId, $name)
    {
        // 生成Redis缓存键
        $cacheKey = sprintf(EnumMerchant::MERCHANT_CONFIG_REDIS_KEY, $merchantId, $name);

        // 尝试从Redis读取
        $redis = \think\facade\Cache::store('redis')->handler();
        $cachedValue = $redis->get($cacheKey);

        if ($cachedValue !== false) {
            // Redis中存在数据，尝试解析JSON
            $data = json_decode($cachedValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // 成功解析为数组
                return $data;
            }
            // 不是JSON或解析失败，直接返回原始值
            return $cachedValue;
        }

        // Redis中不存在，从数据库读取
        $mer = \app\shop\model\MerchantConfig::where(['merchant_id' => $merchantId, 'name' => $name])->value('value');

        if ($mer) {
            // 将结果存入Redis缓存
            $redis->set($cacheKey, $mer);
            $redis->expire($cacheKey, 86400 * 10);

            // 尝试解析JSON
            $data = json_decode($mer, true);
            if (is_array($data)) {
                return $data;
            }
            return $mer;
        }

        // 数据库中也不存在，缓存null值，避免频繁查询数据库
        $redis->set($cacheKey, 'null');
        $redis->expire($cacheKey, 600); // 空值缓存时间较短，10分钟

        return null;
    }

    /**
     * 清除商户配置缓存
     * 
     * @param string $merchantId 商户ID
     * @param string|null $name 配置名称，为null时清除该商户所有配置缓存
     * @return bool 是否成功
     */
    public static function clearMerchantConfigCache($merchantId, $name = null)
    {
        $redis = \think\facade\Cache::store('redis')->handler();

        if ($name === null) {
            // 清除该商户所有配置缓存
            $pattern = "merchant:{$merchantId}:config:*";
            $keys = $redis->keys($pattern);

            if (!empty($keys)) {
                return $redis->del($keys) > 0;
            }

            return true;
        } else {
            // 清除指定配置缓存
            $cacheKey = sprintf(EnumMerchant::MERCHANT_CONFIG_REDIS_KEY, $merchantId, $name);
            return $redis->del($cacheKey) > 0;
        }
    }
}
