<?php


namespace app\common\helper;


use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use think\facade\Cache;

class Qr
{
    /**
     * 生成二维码并保存到Redis
     * 
     * @param string $url 要编码的URL
     * @param int $size 二维码尺寸（像素）
     * @param int $margin 二维码边距
     * @param int $expireTime Redis缓存过期时间（秒）
     * @return string 生成的二维码图片的base64编码
     */
    public static function generateQrCodeToRedis($url, $size = 300, $margin = 1, $expireTime = 86400 * 30)
    {
        // 生成唯一键名
        $cacheKey = 'qrcode:' . md5($url . $size . $margin . time());

        // 检查Redis中是否已存在该二维码
        $existingQrCode = Cache::store('redis')->get($cacheKey);
        if ($existingQrCode) {
            return $existingQrCode;
        }

        try {
            // 创建渲染器
            $renderer = new ImageRenderer(
                new RendererStyle($size, $margin),
                new SvgImageBackEnd()
            );

            // 创建写入器
            $writer = new Writer($renderer);

            // 生成二维码SVG
            $qrCodeSvg = $writer->writeString($url);

            // 将SVG转换为base64编码
            $base64QrCode = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);

            // 保存到Redis，设置过期时间
            Cache::store('redis')->set($cacheKey, $base64QrCode, $expireTime);

            return $base64QrCode;
        } catch (\Exception $e) {
            // 记录错误日志
            \think\facade\Log::error('QR Code generation error: ' . $e->getMessage());
            return '';
        }
    }
}
