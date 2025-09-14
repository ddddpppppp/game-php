<?php

namespace app\common\helper;

use app\common\enum\RedisKey;
use GuzzleHttp\Client;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;

class PinddPay
{
    // PinddPay API 配置
    private $appKey;
    private $secret;
    private $apiBase = 'http://test.pinddpay.com'; // 沙箱环境，生产环境为 https://api-sc.pinddpay.com

    /**
     * 构造函数
     * 
     * @param string $clientId PayPal客户端ID
     * @param string $clientSecret PayPal客户端密钥
     * @param bool $sandbox 是否使用沙箱环境
     */
    public function __construct($params = [])
    {
        // 设置默认值
        $this->appKey = $params['appKey'] ?: '71BOxRixFE';
        $this->secret = $params['appSecret'] ?: 'TQKIoDM4lUySqaKOxYlGIg';
        $this->apiBase =  $params['apiBase'] ?: 'http://test.pinddpay.com';
    }

    /**
     * 构建签名
     * 
     * @param string $method 请求方法（如 POST，需大写）
     * @param string $url    请求路径（如 /api/test/hello，去除协议、域名、参数）
     * @param string $appKey 访问密钥
     * @param string $timestamp 时间戳
     * @param string $nonce    随机字符串
     * @param string $accessSecret 签名密钥（我司提供）
     * @return string 签名字符串（Base64编码）
     */
    public function buildSignature($method, $url, $appKey, $timestamp, $nonce)
    {
        // 按顺序拼接参数
        $data = $method . '&' . $url . '&' . $appKey . '&' . $timestamp . '&' . $nonce;

        // 计算 HMAC-SHA256 签名
        $hash = hash_hmac('sha256', $data, $this->secret, true);

        // Base64 编码
        return base64_encode($hash);
    }

    /**
     * 创建订单-新版API
     * @param string $orderNo 本地订单号
     * @param float $amount 支付金额
     * @param string $currency 货币代码（如USD）
     * @param array $customer 客户信息
     * @param string $notifyUrl 支付通知回调URL
     * @param string $redirectUrl 支付成功后跳转URL
     * @param string $description 商品描述
     * @param array $options 支付选项参数
     * @return array|null 创建结果
     */
    public function createOrder($orderNo, $amount, $currency = 'USD', $customer = [], $notifyUrl = '', $redirectUrl = '', $description = '', $options = [])
    {
        // 生成时间戳（秒级）
        $timestamp = time();

        // 生成随机数
        $nonce = mt_rand(1000, 10000);

        // 请求方法
        $method = 'POST';

        // 请求路径
        $url = '/api/gateway/authorization';

        // 生成签名
        $signature = $this->buildSignature($method, $url, $this->appKey, $timestamp, $nonce);

        // 构建请求数据
        $requestData = [
            'appid' => $this->appKey,
            'order' => [
                'id' => $orderNo,
                'sku' => [
                    'id' => md5($orderNo . time()), // 生成SKU ID
                    'currency' => $currency,
                    'amount' => (float)$amount,
                    'name' => $description ?: 'Product',
                    'quantity' => 1
                ]
            ],
            'customer' => $this->generatePerson($customer),
            'notifyUrl' => $notifyUrl ?: url('/api/notify/ppinddPay', [], false, true),
            'redirectUrl' => $redirectUrl ?: url('/api/notify/successCommonReturn', [], false, true)
        ];

        // 添加支付选项参数
        if (!empty($options)) {
            $requestData['options'] = $options;
        } else {
            $requestData['options'] = [
                'paymentMethod' => 'paypal'
            ];
        }

        // 如果有Paypal特殊配置
        if (isset($options['paypal'])) {
            $requestData['paypal'] = $options['paypal'];
            unset($requestData['options']['paypal']);
        }

        $client = new Client();
        try {
            Log::info('PinddPay创建订单请求数据: url:' . $this->apiBase . $url . ' data:' . json_encode($requestData));
            $response = $client->request($method, $this->apiBase . $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'accessKey' => $this->appKey,
                    'timestamp' => $timestamp,
                    'nonce' => $nonce,
                    'sign' => $signature
                ],
                'json' => $requestData
            ]);

            // 记录日志便于问题排查
            Log::info('PinddPay创建订单成功: ' . $response->getBody());
            $result = json_decode($response->getBody(), true);
            if ($result['code'] != 200) {
                return [-1, $result['message']];
            }
            $result = $result['result'];
            if (empty($result['payments'])) {
                return [-1, '创建订单失败, 原因：' . $result['message']];
            }
            $payments = $result['payments'];
            $payment = $payments[0];
            $paymentId = $payment['id'];
            $paymentUrl = $payment['paylink'];


            return [1, $paymentId, $paymentUrl];
        } catch (\Exception $e) {
            Log::error('PinddPay创建订单失败: ' . $e->getMessage());
            return null;
        }
    }

    private function generatePerson($customer)
    {
        // 随机生成真实的客户姓名
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emma', 'Robert', 'Lisa', 'James', 'Maria', 'William', 'Jennifer', 'Richard', 'Linda', 'Charles', 'Elizabeth', 'Thomas', 'Patricia', 'Christopher', 'Susan'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];

        $randomFirstName = $firstNames[array_rand($firstNames)];
        $randomLastName = $lastNames[array_rand($lastNames)];
        $randomName = $randomFirstName . ' ' . $randomLastName;

        // 随机生成北美真实用户IP地址 (家庭/办公网络)
        $ipRanges = [
            // 美国主要ISP家庭用户IP段
            '73.92.',        // Comcast 家庭用户
            '76.122.',       // Comcast 家庭用户
            '108.28.',       // Comcast 家庭用户
            '67.161.',       // AT&T 家庭用户
            '99.75.',        // AT&T 家庭用户
            '174.94.',       // Verizon FiOS 家庭用户
            '71.178.',       // Verizon 家庭用户
            '98.207.',       // Verizon 家庭用户
            '24.7.',         // Charter Spectrum 家庭用户
            '65.96.',        // Charter Spectrum 家庭用户
            '107.77.',       // Charter Spectrum 家庭用户
            '173.170.',      // Cox Communications 家庭用户
            '68.105.',       // Cox Communications 家庭用户
            '75.149.',       // Time Warner Cable 家庭用户
            '72.229.',       // Time Warner Cable 家庭用户
            '50.47.',        // CenturyLink 家庭用户
            '75.164.',       // CenturyLink 家庭用户
            // 加拿大主要ISP家庭用户IP段
            '142.177.',      // Bell Canada 家庭用户
            '184.147.',      // Bell Canada 家庭用户
            '72.143.',       // Rogers 家庭用户
            '99.244.',       // Rogers 家庭用户
            '24.114.',       // Shaw 家庭用户
            '70.67.',        // Shaw 家庭用户
        ];
        $randomIpPrefix = $ipRanges[array_rand($ipRanges)];
        // 根据IP前缀生成完整IP
        if (substr_count($randomIpPrefix, '.') == 1) {
            // 如果只有一个点，需要生成两段
            $randomIp = $randomIpPrefix . mt_rand(1, 254) . '.' . mt_rand(1, 254);
        } else {
            // 如果有两个点，只需要生成一段
            $randomIp = $randomIpPrefix . mt_rand(1, 254);
        }

        // 随机生成真实的邮箱
        $emailDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'protonmail.com'];
        $randomDomain = $emailDomains[array_rand($emailDomains)];
        $emailPrefix = strtolower(str_replace(' ', '.', $randomName));
        $randomEmail = $emailPrefix . mt_rand(100, 999) . '@' . $randomDomain;

        // 生成随机客户ID
        $randomId = 'customer_' . mt_rand(100000, 999999);

        $person = [
            'country' => 'US',
            'id' => $customer['id'] ?? $randomId,
            'ip' => $customer['ip'] ?? $randomIp,
            'name' => $customer['name'] ?? $randomName,
            'email' => $customer['email'] ?? $randomEmail
        ];

        return $person;
    }
}
