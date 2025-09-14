<?php


namespace app\common\helper;



class TimeHelper
{
    /**
     * 将指定时区的时间转换为UTC时间
     * 
     * @param string $time 时间字符串，例如 '2025-04-24 11:11:10'
     * @param string|null $timezone 时区，如果为null则使用当前请求的时区
     * @return string 转换后的UTC时间
     */
    public static function convertToUTC($time, $format = 'Y-m-d H:i:s', $timezone = null)
    {
        // 如果未指定时区，尝试从请求中获取
        if ($timezone === null) {
            // 这里假设时区信息可能存在于请求头或会话中
            // 根据实际应用程序逻辑调整获取时区的方式
            // 获取请求头中的时区信息
            $timezone = request()->header('Timezone');
            if (empty($timezone)) {
                $timezone = 'Asia/Shanghai';
            }
        }

        // 创建DateTime对象，使用提供的时区
        $dateTime = new \DateTime($time, new \DateTimeZone($timezone));

        // 将时间转换为UTC
        $dateTime->setTimezone(new \DateTimeZone('UTC'));

        // 返回格式化的UTC时间
        return $dateTime->format($format);
    }

    /**
     * 将UTC时间转换为指定时区的时间
     * 
     * @param string $utcTime UTC时间字符串，例如 '2025-04-24 03:11:10'
     * @param string|null $timezone 目标时区，如果为null则使用当前请求的时区
     * @param string $format 返回的时间格式
     * @return string 转换后的目标时区时间
     */
    public static function convertFromUTC($utcTime, $format = 'Y-m-d H:i:s', $timezone = null)
    {
        // 如果未指定时区，尝试从请求中获取
        if ($timezone === null) {
            // 获取请求头中的时区信息
            $timezone = request()->header('Timezone');
            if (empty($timezone)) {
                $timezone = 'Asia/Shanghai';
            }
        }

        // 创建UTC时间的DateTime对象
        $dateTime = new \DateTime($utcTime, new \DateTimeZone('UTC'));

        // 将时间转换为目标时区
        $dateTime->setTimezone(new \DateTimeZone($timezone));

        // 返回格式化的目标时区时间
        return $dateTime->format($format);
    }



    public static function buildSection($beginDate, $endDate)
    {
        $list = [];
        $day = 0;
        while (true) {
            $timeInt = strtotime($beginDate) + $day * 86400;
            $list[] = date('Y-m-d', $timeInt);
            if ($timeInt >= strtotime($endDate)) {
                break;
            }
            $day++;
        }
        return $list;
    }
}
