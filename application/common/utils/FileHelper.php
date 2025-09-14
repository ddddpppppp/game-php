<?php


namespace app\common\utils;




use think\facade\Request;

class FileHelper
{
    public static function base64ToImg($base64)
    {
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
            $type = $result[2];
            if (!in_array($type, ['png', 'jpg', 'jpeg', 'gif'])) {
                return [-1, '图片格式不正确'];
            }
            $newFile = __STATIC__ . '/uploads/';
            $fileName = time() . rand(111111, 999999) . ".{$type}";
            $newFile = $newFile . $fileName;
            if (file_put_contents($newFile, base64_decode(str_replace($result[1], '', $base64)))) {
                return [1, Request::domain() . '/static/uploads/' . $fileName];
            } else {
                return [-1, '图片保存失败'];
            }
        } else {
            return false;
        }
    }
    public static function getFileUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        return config('oss.url') . '/' . $url;
    }
}