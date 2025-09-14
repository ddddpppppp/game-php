<?php


namespace app\common\utils;


class ParamsAesHelper
{
    private static $key = '1634430227QSDWZH';
    private static $iv = 'ZZWBKJ_ZHIHUAWEI';

    /**
     * 解密字符串
     * @param string $data 字符串
     * @param string $key 加密key
     * @param string $iv 加密向量
     * @return false|object|string
     */
    public static function decrypt($data)
    {
        $ret = openssl_decrypt($data, 'AES-128-CBC', self::$key, 0, self::$iv);
        return json_decode($ret, 1);
    }

}