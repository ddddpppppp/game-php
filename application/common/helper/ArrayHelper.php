<?php


namespace app\common\helper;


use think\facade\Request;

class ArrayHelper
{
    /**
     * 将数据集转为以某个键为索引的数组
     * @param array $data 数据集
     * @param string $key 键名
     * @param int $level 层级(2或3)
     * @return array
     */
    public static function setKey($data, $key, $level = 2)
    {
        if (empty($data)) {
            return [];
        }
        $ret = [];
        foreach ($data as $row) {
            if (!isset($row[$key])) {
                $ret[] = $row;  // 如果不存在$key，直接保留原样
            } else {
                if ($level == 2) {
                    $ret[$row[$key]] = $row;  // 二级结构：[$key值 => 整行数据]
                } else if ($level == 3) {
                    if (empty($ret[$row[$key]])) {
                        $ret[$row[$key]] = [];  // 初始化三级结构的数组
                    }
                    $ret[$row[$key]][] = $row;  // 三级结构：[$key值 => [多行数据]]
                }
            }
        }
        return $ret;
    }


    /**
     * 下划线转驼峰
     */
    public static function camelize($data) {
        if (empty($data) || !is_array($data)) {
            return [];
        }
        $ret = [];
        foreach ($data as $key => $val) {
            $ret[camelize($key)] = $val;
        }
        return $ret;
    }

    /**
     * 数组下划线转驼峰
     */
    public static function camelizeBatch($data) {
        if (empty($data) || !is_array($data)) {
            return [];
        }
        $ret = [];
        foreach ($data as $item) {
            $ret[] = self::camelize($item);
        }
        return $ret;
    }
}