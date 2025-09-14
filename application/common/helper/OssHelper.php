<?php

namespace app\common\helper;

use OSS\OssClient;
use OSS\Core\OssException;
use think\facade\Config;

/**
 * 阿里云OSS辅助类
 */
class OssHelper
{
    /**
     * 获取OSS客户端实例
     *
     * @return OssClient|null
     */
    public static function getOssClient()
    {
        $ossConfig = Config::get('oss.');

        if (
            empty($ossConfig['access_key_id']) ||
            empty($ossConfig['access_key_secret']) ||
            empty($ossConfig['endpoint'])
        ) {
            return null;
        }

        try {
            $ossClient = new OssClient(
                $ossConfig['access_key_id'],
                $ossConfig['access_key_secret'],
                $ossConfig['endpoint']
            );
            return $ossClient;
        } catch (OssException $e) {
            return null;
        }
    }

    /**
     * 判断OSS是否启用
     *
     * @return bool
     */
    public static function isEnabled()
    {
        $ossConfig = Config::get('oss.');
        return isset($ossConfig['enable']) && $ossConfig['enable'] === true;
    }

    public static function buildAvatarUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        return $url . '?x-oss-process=image/resize,h_64,m_lfit,limit_1/quality,Q_70';
    }

    public static function qualityImage($url, $quality = 60)
    {
        if (empty($url)) {
            return '';
        }
        return $url . '?x-oss-process=image/quality,q_' . $quality;
    }


    /**
     * 上传文件到OSS
     *
     * @param string $filePath 本地文件路径
     * @param string $object OSS对象名称
     * @return array|bool 成功返回文件信息数组，失败返回false
     */
    public static function upload($filePath, $object = null)
    {
        if (!self::isEnabled()) {
            return false;
        }

        $ossConfig = Config::get('oss.');
        $ossClient = self::getOssClient();

        if (!$ossClient) {
            return false;
        }

        if (!file_exists($filePath)) {
            return false;
        }

        // 如果没有指定对象名称，自动生成
        if (empty($object)) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $subDir = date('Ymd') . '/';
            $object = 'uploads/' . $subDir . uniqid() . '.' . $extension;
        }

        try {
            // 上传文件到OSS
            $ossClient->uploadFile($ossConfig['bucket'], $object, $filePath);

            // 获取访问URL
            $url = isset($ossConfig['url']) && !empty($ossConfig['url'])
                ? rtrim($ossConfig['url'], '/') . '/' . $object
                : 'https://' . $ossConfig['bucket'] . '.' . $ossConfig['endpoint'] . '/' . $object;

            return [
                'save_path' => $object,
                'url'       => $url,
                'storage'   => 'oss'
            ];
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * 从OSS删除文件
     *
     * @param string $object OSS对象名称
     * @return bool 成功返回true，失败返回false
     */
    public static function delete($object)
    {
        if (!self::isEnabled()) {
            return false;
        }

        $ossConfig = Config::get('oss.');
        $ossClient = self::getOssClient();

        if (!$ossClient) {
            return false;
        }

        try {
            $ossClient->deleteObject($ossConfig['bucket'], $object);
            return true;
        } catch (OssException $e) {
            return false;
        }
    }
}
