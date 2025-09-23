<?php

namespace app\common\helper;

use think\facade\Log;

/**
 * FreePay 支付服务类
 * 
 * 基于Go版本的FreePay服务转换而来
 * 提供创建支付订单的功能
 */
class FreePay
{
    private $freePayUrl;
    private $mchNo;
    private $key;
    private $appId;

    /**
     * 构造函数
     *
     * @param array $params 参数配置
     *                      - url: FreePay API地址
     *                      - mchNo: 商户号
     *                      - key: 签名密钥
     *                      - appId: 应用ID
     */
    public function __construct($params)
    {
        $this->freePayUrl = $params['url'] ?? '';
        $this->mchNo = $params['mchNo'] ?? '';
        $this->key = $params['key'] ?? '';
        $this->appId = $params['appId'] ?? '';
    }

    /**
     * 账单信息数据结构
     */
    public static function createBillingAddress($data = [])
    {
        return [
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? '',
            'email' => $data['email'] ?? '',
            'firstName' => $data['firstName'] ?? '',
            'lastName' => $data['lastName'] ?? '',
            'phone' => $data['phone'] ?? '',
            'state' => $data['state'] ?? '',
            'zipCode' => $data['zipCode'] ?? ''
        ];
    }

    /**
     * 邮寄信息数据结构
     */
    public static function createShippingAddress($data = [])
    {
        return [
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? '',
            'email' => $data['email'] ?? '',
            'firstName' => $data['firstName'] ?? '',
            'lastName' => $data['lastName'] ?? '',
            'phone' => $data['phone'] ?? '',
            'state' => $data['state'] ?? '',
            'zipCode' => $data['zipCode'] ?? ''
        ];
    }

    /**
     * 交易客户端信息数据结构
     */
    public static function createBrowser($data = [])
    {
        return [
            'accept' => $data['accept'] ?? '',
            'acceptLanguage' => $data['acceptLanguage'] ?? '',
            'colorDepth' => $data['colorDepth'] ?? '',
            'javaEnabled' => $data['javaEnabled'] ?? false,
            'referer' => $data['referer'] ?? '',
            'screenHeight' => $data['screenHeight'] ?? '',
            'screenWidth' => $data['screenWidth'] ?? '',
            'timeZoneOffset' => $data['timeZoneOffset'] ?? '',
            'userAgent' => $data['userAgent'] ?? '',
            'visitorId' => $data['visitorId'] ?? ''
        ];
    }

    /**
     * 付款卡信息数据结构
     */
    public static function createCard($data = [])
    {
        return [
            'bankName' => $data['bankName'] ?? '',
            'cardNumber' => $data['cardNumber'] ?? '',
            'cvv' => $data['cvv'] ?? '',
            'expiredMonth' => $data['expiredMonth'] ?? '',
            'expiredYear' => $data['expiredYear'] ?? '',
            'holderEmail' => $data['holderEmail'] ?? '',
            'holderFirstName' => $data['holderFirstName'] ?? '',
            'holderLastName' => $data['holderLastName'] ?? '',
            'holderPhone' => $data['holderPhone'] ?? ''
        ];
    }

    /**
     * 商品信息数据结构
     */
    public static function createProduct($data = [])
    {
        return [
            'currency' => $data['currency'] ?? '',
            'price' => $data['price'] ?? '',
            'productName' => $data['productName'] ?? '',
            'productNo' => $data['productNo'] ?? '',
            'productUrl' => $data['productUrl'] ?? '',
            'quantity' => $data['quantity'] ?? '',
            'sku' => $data['sku'] ?? ''
        ];
    }

    /**
     * 完整的支付请求结构
     */
    public static function createFreePayRequest($data = [])
    {
        return [
            'amount' => $data['amount'] ?? 0,
            'appId' => $data['appId'] ?? '',
            'browser' => $data['browser'] ?? [],
            'clientIp' => $data['clientIp'] ?? '',
            'currency' => $data['currency'] ?? '',
            'mchNo' => $data['mchNo'] ?? '',
            'mchOrderNo' => $data['mchOrderNo'] ?? '',
            'mode' => $data['mode'] ?? 1,
            'notifyUrl' => $data['notifyUrl'] ?? '',
            'products' => $data['products'] ?? [],
            'returnUrl' => $data['returnUrl'] ?? '',
            'wayCode' => $data['wayCode'] ?? ''
        ];
    }

    /**
     * 生成随机浏览器信息
     *
     * @return array
     */
    private function generateRandomBrowser()
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15"
        ];

        $screenWidths = ["1920", "1440", "1366", "1536", "2560"];
        $screenHeights = ["1080", "900", "768", "864", "1440"];
        $colorDepths = ["24", "32"];
        $timeZoneOffsets = ["-480", "-420", "-360", "-300", "-240", "-180", "-120", "-60", "0", "60", "120", "180", "240", "300", "360", "420", "480"];

        return self::createBrowser([
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'acceptLanguage' => 'en-US,en;q=0.5',
            'colorDepth' => $colorDepths[array_rand($colorDepths)],
            'javaEnabled' => (bool)rand(0, 1),
            'referer' => 'https://www.google.com/',
            'screenHeight' => $screenHeights[array_rand($screenHeights)],
            'screenWidth' => $screenWidths[array_rand($screenWidths)],
            'timeZoneOffset' => $timeZoneOffsets[array_rand($timeZoneOffsets)],
            'userAgent' => $userAgents[array_rand($userAgents)],
            'visitorId' => $this->createUuid()
        ]);
    }

    /**
     * 生成随机商品信息
     *
     * @param int $amount 金额（分）
     * @param string $currency 货币
     * @return array
     */
    private function generateRandomProducts($amount, $currency)
    {
        $products = [
            self::createProduct([
                'currency' => $currency,
                'price' => number_format($amount / 100, 2, '.', ''),
                'productName' => 'Game Credits',
                'productNo' => 'GAME-' . (rand(100000, 999999)),
                'productUrl' => 'https://www.example.com/game-credits',
                'quantity' => '1',
                'sku' => 'SKU-' . (rand(100000, 999999))
            ])
        ];
        return $products;
    }

    /**
     * 计算SHA256哈希值
     *
     * @param string $data 要计算哈希的数据
     * @return string
     */
    private function calculateSHA256($data)
    {
        return hash('sha256', $data);
    }

    /**
     * 计算MD5哈希值
     *
     * @param string $data 要计算哈希的数据
     * @return string
     */
    private function calculateMD5($data)
    {
        return md5($data);
    }

    /**
     * 根据PHP demo的签名算法创建签名
     *
     * @param array $headers 请求头
     * @param string $postdata 请求体
     * @return string
     */
    private function makeSign($headers, $postdata)
    {
        // 获取所有请求头的键并排序
        $keys = array_keys($headers);
        sort($keys);

        // 按键值升序排序并拼接
        $unsign = '';
        foreach ($keys as $key) {
            $value = $headers[$key];
            if ($value !== '') {
                $unsign .= strtolower($key) . $value;
            }
        }

        // 加上请求体
        if (!empty($postdata)) {
            $unsign .= $postdata;
        }

        // 加上密钥
        $unsign .= $this->key;

        // 计算MD5签名
        $sign = $this->calculateMD5($unsign);

        return $sign;
    }

    /**
     * 获取签名的请求头列表
     *
     * @param array $headers 请求头
     * @return string
     */
    private function getSignedHeaders($headers)
    {
        $keys = array_keys($headers);
        $keys = array_map('strtolower', $keys);
        sort($keys);
        return implode(';', $keys);
    }

    /**
     * 构建Authorization头
     *
     * @param string $signedHeaders 签名的请求头列表
     * @param string $signature 签名
     * @return string
     */
    private function getAuthorization($signedHeaders, $signature)
    {
        return sprintf(
            "Credential=%s,SignedHeaders=%s,Signature=%s",
            $this->mchNo,
            $signedHeaders,
            $signature
        );
    }

    /**
     * 获取UTC时间
     *
     * @return string
     */
    private function getUTCTime()
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * 生成UUID
     *
     * @return string
     */
    private function createUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * 创建FreePay订单
     *
     * @param string $orderNo 订单号
     * @param int $amount 金额（分）
     * @param string $body 商品描述
     * @param string $subject 商品标题
     * @param string $returnUrl 返回地址
     * @param string $ip 客户端IP
     * @return array [payUrl, payId, error]
     */
    public function freePayOrder($orderNo, $amount, $returnUrl, $ip)
    {
        // 验证金额
        if ($amount < 449) {
            return ['', '', '金额必须大于449分'];
        }

        // 构建请求数据
        $request = self::createFreePayRequest([
            'amount' => $amount,
            'appId' => $this->appId,
            'browser' => $this->generateRandomBrowser(),
            'clientIp' => $ip,
            'currency' => 'USD',
            'mchNo' => $this->mchNo,
            'mchOrderNo' => $orderNo,
            'mode' => 1, // 收银台模式
            'notifyUrl' => 'https://php.game-hub.cc/api/notify/freePayNotify',
            'products' => $this->generateRandomProducts($amount, 'USD'),
            'returnUrl' => $returnUrl,
            'wayCode' => 'A'
        ]);

        // 序列化请求体
        $postdata = json_encode($request, JSON_UNESCAPED_SLASHES);

        // 构建请求头
        $headers = [
            'Version' => 'v2',
            'Date' => $this->getUTCTime(),
            'Accept-Language' => 'en-US',
            'Nonce' => time() . sprintf('%04d', rand(1000, 9999)),
            'Content-Type' => 'application/json',
            'Content-Sha256' => $this->calculateSHA256($postdata)
        ];

        // 创建签名
        $signature = $this->makeSign($headers, $postdata);
        $signedHeaders = $this->getSignedHeaders($headers);
        $authorization = $this->getAuthorization($signedHeaders, $signature);

        // 设置完整的请求头
        $headers['Authorization'] = $authorization;

        // 发送请求
        $url = $this->freePayUrl . '/api/v2/pay/transactions/create';

        // 记录日志
        Log::info('FreePay Request URL: ' . $url);
        Log::info('FreePay Request Headers: ' . json_encode($headers));
        Log::info('FreePay Request Body: ' . $postdata);

        // 构建curl请求头格式
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        // 发送POST请求
        $response = postData($url, $postdata, $curlHeaders);

        Log::info('FreePay Response: ' . $response);

        // 解析响应
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['', '', '解析响应失败: ' . json_last_error_msg()];
        }

        // 检查响应状态
        if (!isset($result['code']) || (string)$result['code'] !== '200') {
            $errorMsg = isset($result['msg']) ? $result['msg'] : '未知错误';
            return ['', '', $errorMsg];
        }

        // 提取支付信息
        $data = $result['data'] ?? [];
        $payId = $data['platOrderNo'] ?? '';
        $payUrl = $data['payData'] ?? '';

        return [1, $payId, $payUrl];
    }
}
