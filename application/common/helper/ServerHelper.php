<?php


namespace app\common\helper;


class ServerHelper
{
    public static function getServerIp()
    {
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (!empty($_SERVER["REMOTE_ADDR"])) {
            $cip = $_SERVER["REMOTE_ADDR"];
        } else {
            $cip = 0;
        }
        return $cip;
    }

    public static function getDevice()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (stripos($userAgent, "iPhone") !== false) {
            $brand = 'iPhone';
        } else if (stripos($userAgent, "SAMSUNG") !== false || stripos($userAgent, "Galaxy") !== false || strpos($userAgent, "GT-") !== false || strpos($userAgent, "SCH-") !== false || strpos($userAgent, "SM-") !== false) {
            $brand = '三星';
        } else if (stripos($userAgent, "Huawei") !== false || stripos($userAgent, "Honor") !== false || stripos($userAgent, "H60-") !== false || stripos($userAgent, "H30-") !== false) {
            $brand = '华为';
        } else if (stripos($userAgent, "Lenovo") !== false) {
            $brand = '联想';
        } else if (strpos($userAgent, "MI-ONE") !== false || strpos($userAgent, "MI 1S") !== false || strpos($userAgent, "MI 2") !== false || strpos($userAgent, "MI 3") !== false || strpos($userAgent, "MI 4") !== false || strpos($userAgent, "MI-4") !== false) {
            $brand = '小米';
        } else if (strpos($userAgent, "HM NOTE") !== false || strpos($userAgent, "HM201") !== false) {
            $brand = '红米';
        } else if (stripos($userAgent, "Coolpad") !== false || strpos($userAgent, "8190Q") !== false || strpos($userAgent, "5910") !== false) {
            $brand = '酷派';
        } else if (stripos($userAgent, "ZTE") !== false || stripos($userAgent, "X9180") !== false || stripos($userAgent, "N9180") !== false || stripos($userAgent, "U9180") !== false) {
            $brand = '中兴';
        } else if (stripos($userAgent, "OPPO") !== false || strpos($userAgent, "X9007") !== false || strpos($userAgent, "X907") !== false || strpos($userAgent, "X909") !== false || strpos($userAgent, "R831S") !== false || strpos($userAgent, "R827T") !== false || strpos($userAgent, "R821T") !== false || strpos($userAgent, "R811") !== false || strpos($userAgent, "R2017") !== false) {
            $brand = 'OPPO';
        } else if (strpos($userAgent, "HTC") !== false || stripos($userAgent, "Desire") !== false) {
            $brand = 'HTC';
        } else if (stripos($userAgent, "vivo") !== false) {
            $brand = 'vivo';
        } else if (stripos($userAgent, "K-Touch") !== false) {
            $brand = '天语';
        } else if (stripos($userAgent, "Nubia") !== false || stripos($userAgent, "NX50") !== false || stripos($userAgent, "NX40") !== false) {
            $brand = '努比亚';
        } else if (strpos($userAgent, "M045") !== false || strpos($userAgent, "M032") !== false || strpos($userAgent, "M355") !== false) {
            $brand = '魅族';
        } else if (stripos($userAgent, "DOOV") !== false) {
            $brand = '朵唯';
        } else if (stripos($userAgent, "GFIVE") !== false) {
            $brand = '基伍';
        } else if (stripos($userAgent, "Gionee") !== false || strpos($userAgent, "GN") !== false) {
            $brand = '金立';
        } else if (stripos($userAgent, "HS-U") !== false || stripos($userAgent, "HS-E") !== false) {
            $brand = '海信';
        } else if (stripos($userAgent, "Nokia") !== false) {
            $brand = '诺基亚';
        } else {
            $brand = '其他手机';
        }
        return $brand;
    }
    
    public static function createUrl($url)
    {
        return SITE_ROOT . url($url);
    }
}