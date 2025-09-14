<?php
namespace app\common\utils;

/**
 * SQL注入过滤工具类
 */
class SqlFilterHelper
{
    /**
     * 过滤SQL注入字符
     * 
     * @param string $str 需要过滤的字符串
     * @return string 过滤后的字符串
     */
    public static function filter($str)
    {
        if (!$str) {
            return '';
        }

        // 移除反斜杠转义
        $str = stripslashes($str);

        // 特殊字符转义
        $str = addslashes($str);

        // 过滤SQL关键字和常见注入模式
        $sqlKeywords = [
            'SELECT',
            'UPDATE',
            'DELETE',
            'INSERT',
            'DROP',
            'CREATE',
            'ALTER',
            'TRUNCATE',
            'UNION',
            'JOIN',
            'OR',
            'AND',
            '--',
            '/*',
            '*/',
            '#',
            ';',
            '='
        ];

        $pattern = '/\b(' . implode('|', array_map(function ($keyword) {
            return preg_quote($keyword, '/');
        }, $sqlKeywords)) . ')\b/i';
        $str = preg_replace($pattern, '', $str);

        // 移除多余空格
        $str = preg_replace('/\s+/', ' ', $str);

        return $str;
    }

    /**
     * 安全地过滤整个数组
     * 
     * @param array $array 需要过滤的数组
     * @return array 过滤后的数组
     */
    public static function filterArray($array)
    {
        if (!is_array($array)) {
            return [self::filter($array)];
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::filterArray($value);
            } else {
                $array[$key] = self::filter($value);
            }
        }

        return $array;
    }

    /**
     * 使用PDO参数绑定方式来防止SQL注入
     * 推荐在查询时使用此方法代替字符串拼接
     * 
     * @param string $param 原始参数值
     * @return string 可安全用于PDO参数绑定的值
     */
    public static function preparePdoParam($param)
    {
        // PDO参数绑定会自动处理转义
        // 此方法仅做基本清理，确保返回的是可以安全绑定的值
        return trim($param);
    }
}