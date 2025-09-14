<?php

namespace app\common\utils;

use think\facade\Cache;
use think\facade\Config;

class VerificationCode
{
    // 验证码配置
    protected $config = [
        'length' => 4,           // 验证码长度
        'width' => 120,          // 图片宽度
        'height' => 40,          // 图片高度
        'expire' => 120,         // 过期时间（秒）
        'fontSize' => 40,        // 字体大小
        'useNoise' => false,      // 是否添加干扰
        'useCurve' => true,      // 是否添加曲线
        'fontttf' => '',         // 指定字体文件
        'bg' => [243, 251, 254], // 背景色
        'useImgBg' => false,     // 使用背景图片
        'useZh' => false,        // 使用中文验证码
        'uploadPath' => 'uploads/captcha/', // 上传路径
    ];

    // 验证码字符集合
    protected $codeSet = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY';

    // 验证码图片实例
    protected $image = null;

    // 验证码字体颜色
    protected $color = null;

    /**
     * 构造方法
     * @param array $config 配置参数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);

        // 确保上传目录存在
        if (!is_dir($this->config['uploadPath'])) {
            mkdir($this->config['uploadPath'], 0755, true);
        }
    }

    /**
     * 生成验证码
     * @return array 返回验证码图片路径和UUID
     */
    public function generate()
    {
        // 生成UUID
        $uuid = $this->generateUUID();

        // 生成验证码
        $code = $this->generateCode();

        // 创建图片
        $this->createImage($code);

        // 保存图片
        $imagePath = $this->saveImage($uuid);

        // 存储到Redis
        $this->saveToRedis($uuid, $code);

        return [
            'uuid' => $uuid,
            'image_url' => $imagePath
        ];
    }

    /**
     * 生成UUID
     * @return string
     */
    protected function generateUUID()
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * 生成验证码
     * @return string
     */
    protected function generateCode()
    {
        $code = '';
        $length = $this->config['length'];
        $codeSet = str_split($this->codeSet);

        for ($i = 0; $i < $length; $i++) {
            $code .= $codeSet[array_rand($codeSet)];
        }

        return $code;
    }

    /**
     * 创建验证码图片
     * @param string $code 验证码
     */
    protected function createImage($code)
    {
        // 创建图片
        $this->image = imagecreatetruecolor($this->config['width'], $this->config['height']);

        // 设置背景
        $bg = $this->config['bg'];
        $background = imagecolorallocate($this->image, $bg[0], $bg[1], $bg[2]);
        imagefilledrectangle($this->image, 0, 0, $this->config['width'], $this->config['height'], $background);

        // 设置字体颜色
        $this->color = imagecolorallocate($this->image, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 添加干扰
        if ($this->config['useNoise']) {
            $this->writeNoise();
        }

        // 添加曲线
        if ($this->config['useCurve']) {
            $this->writeCurve();
        }

        // 写入验证码
        $this->writeText($code);
    }

    /**
     * 添加噪点
     */
    protected function writeNoise()
    {
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY';
        for ($i = 0; $i < 2; $i++) {
            $noiseColor = imagecolorallocate($this->image, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                imagestring(
                    $this->image,
                    5,
                    mt_rand(-10, $this->config['width']),
                    mt_rand(-10, $this->config['height']),
                    $codeSet[mt_rand(0, 29)],
                    $noiseColor
                );
            }
        }
    }

    /**
     * 画干扰曲线
     */
    protected function writeCurve()
    {
        // 减少干扰线数量，只画一条曲线
        $A = mt_rand(1, $this->config['height'] / 4); // 减小振幅
        $b = mt_rand(-$this->config['height'] / 6, $this->config['height'] / 6); // 减小Y轴偏移量
        $f = mt_rand(-$this->config['height'] / 6, $this->config['height'] / 6); // 减小X轴偏移量
        $T = mt_rand($this->config['height'] * 2, $this->config['width'] * 3); // 增大周期，让曲线更平滑
        $w = (2 * M_PI) / $T;

        $px1 = 0;
        $px2 = $this->config['width'];

        // 使用更浅的颜色
        $color = imagecolorallocate($this->image, mt_rand(180, 220), mt_rand(180, 220), mt_rand(180, 220));

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->config['height'] / 2;
                // 减小线条粗细
                imagesetpixel($this->image, $px, $py, $color);
            }
        }
    }

    /**
     * 写入验证码文字
     * @param string $code 验证码
     */
    protected function writeText($code)
    {
        $length = strlen($code);

        // 调整文字之间的间距，确保文字在图片内
        $spacing = ($this->config['width'] - 20) / $length; // 两侧各留10px的边距
        $fontSize = min(5, floor($spacing / 8)); // 根据间距动态调整字体大小

        for ($i = 0; $i < $length; $i++) {
            $codeChar = $code[$i];

            // 水平位置：确保字符在图片范围内
            $x = 10 + $i * $spacing + ($spacing - 8) / 2; // 8是字符近似宽度

            // 垂直位置：在垂直中心位置上下浮动，但保持在安全范围内
            $y = $this->config['height'] / 2 + mt_rand(-5, 5);

            // 确保y坐标不会导致字符超出边界
            $y = max(10, min($y, $this->config['height'] - 10));

            imagechar(
                $this->image,
                5, // 字体大小固定为5
                $x,
                $y - 8, // 字符垂直中心位置调整
                $codeChar,
                $this->color
            );
        }
    }

    /**
     * 保存图片
     * @param string $uuid UUID
     * @return string 图片相对路径
     */
    protected function saveImage($uuid)
    {
        $filename = $uuid . '.png';
        $filepath = $this->config['uploadPath'] . $filename;

        // 保存图片
        imagepng($this->image, $filepath);
        imagedestroy($this->image);

        return $filepath;
    }

    /**
     * 保存验证码到Redis
     * @param string $uuid UUID
     * @param string $code 验证码
     */
    protected function saveToRedis($uuid, $code)
    {
        Cache::store('redis')->set('captcha_' . $uuid, $code, $this->config['expire']);
    }

    /**
     * 验证验证码
     * @param string $uuid UUID
     * @param string $code 用户输入的验证码
     * @param bool $removeAfterCheck 验证后是否删除
     * @return bool
     */
    public function check($uuid, $code, $removeAfterCheck = false)
    {
        $key = 'captcha_' . $uuid;
        $savedCode = Cache::store('redis')->get($key);

        if (!$savedCode) {
            return false;
        }

        $result = strtolower($code) === strtolower($savedCode);

        if ($removeAfterCheck && $result) {
            Cache::store('redis')->delete($key);
        }

        return $result;
    }
}
