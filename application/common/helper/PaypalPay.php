<?php

namespace app\common\helper;

use app\common\enum\RedisKey;
use GuzzleHttp\Client;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Config;

class PaypalPay
{
    // PayPal API 配置
    private $clientId;
    private $clientSecret;
    private $apiBase = 'https://api-m.sandbox.paypal.com'; // 沙箱环境，生产环境为 https://api-m.paypal.com
    private $tokenExpiry = 3600;

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
        $this->clientId = $params['clientId'] ?: 'AYBRi9HeuCEOJCWu7C4N-VbRkKOonWh4LzEamA1AWvUdB2E4|mu9H3V9uFwfZML4dGXs';
        $this->clientSecret = $params['clientSecret'] ?: 'EP F-ZZ bcuuewEyZr25wTUexjdaxHtI6X$5JLXJ1Z9Mal/ZGWrFSAVdux1mmB8hTgoOpTdxtv3v/zI';
        $this->setSandboxMode($params['sandbox'] ?? true);
    }

    /**
     * 设置API凭证
     * 
     * @param string $clientId PayPal客户端ID
     * @param string $clientSecret PayPal客户端密钥
     * @return $this 方法链式调用
     */
    public function setCredentials($clientId, $clientSecret)
    {
        if (!empty($clientId) && !empty($clientSecret)) {
            // 清除旧令牌
            Cache::store('redis')->rm(sprintf(RedisKey::PAYPAL_TOKEN, $this->clientId));

            $this->clientId = $clientId;
            $this->clientSecret = $clientSecret;
        }

        return $this;
    }

    /**
     * 切换环境（沙箱/生产）
     * 
     * @param bool $sandbox 是否使用沙箱环境
     * @return $this 方法链式调用
     */
    public function setSandboxMode($sandbox = true)
    {
        $this->apiBase = $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        return $this;
    }

    /**
     * 获取 PayPal API 访问令牌
     * 
     * @return string 访问令牌
     */
    public function getAccessToken()
    {
        // 如果已有有效令牌，直接返回
        $token = Cache::store('redis')->get(sprintf(RedisKey::PAYPAL_TOKEN, $this->clientId));
        if (!empty($token)) {
            return $token;
        }

        $client = new Client();
        try {
            $response = $client->request('POST', $this->apiBase . '/v1/oauth2/token', [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $token = $data['access_token'];
            Cache::store('redis')->set(sprintf(RedisKey::PAYPAL_TOKEN, $this->clientId), $token, $this->tokenExpiry);
            return $token;
        } catch (\Exception $e) {
            Log::error('PayPal获取访问令牌失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建 PayPal 直接支付订单
     * @param string $orderNo 本地订单号
     * @param float $amount 支付金额
     * @param string $currency 货币代码（如USD）
     * @param string $returnUrl 支付成功后的回调URL
     * @param string $cancelUrl 取消支付后的回调URL
     * @param string $description 订单描述
     * @return array|null 创建结果
     */
    public function createOrder($orderNo, $amount, $currency = 'USD', $returnUrl = '', $cancelUrl = '', $description = '')
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }
        $returnUrl = $returnUrl ?: url('/api/notify/successCommonReturn', [], false, true);
        $cancelUrl = $cancelUrl ?: url('/api/notify/successCommonReturn', [], false, true);

        // 格式化金额，确保两位小数
        $amount = number_format($amount, 2, '.', '');

        $client = new Client();
        try {
            $response = $client->request('POST', $this->apiBase . '/v2/checkout/orders', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
                'json' => [
                    'intent' => 'CAPTURE', // PayPal v2 API 使用 CAPTURE 表示直接付款
                    'purchase_units' => [
                        [
                            'reference_id' => $orderNo,
                            'description' => $description,
                            'amount' => [
                                'currency_code' => $currency,
                                'value' => $amount
                            ]
                        ]
                    ],
                    'application_context' => [
                        'brand_name' => Config::get('app.name', 'doordash'),
                        'landing_page' => 'NO_PREFERENCE',
                        'user_action' => 'PAY_NOW', // 让用户立即支付
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                        'shipping_preference' => 'NO_SHIPPING'
                    ],
                    'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL' // 确保支付批准后立即完成
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            // 记录日志便于问题排查
            Log::info('PayPal创建订单成功: ' . json_encode($result));

            return $result;
        } catch (\Exception $e) {
            Log::error('PayPal创建订单失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 捕获已批准的支付
     * 
     * @param string $orderId PayPal订单ID
     * @return array|null 捕获结果
     */
    public function capturePayment($orderId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $client = new Client();
        try {
            $response = $client->request('POST', $this->apiBase . "/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Prefer' => 'return=representation'
                ],
                'json' => (object)[]
            ]);

            $result = json_decode($response->getBody(), true);

            // 记录捕获结果
            Log::info('PayPal捕获支付成功: ' . json_encode($result));

            return $result;
        } catch (\Exception $e) {
            Log::error('PayPal捕获支付失败: ' . $e->getMessage());
            return null;
        }
    }

    // 在支付成功回调页面或Webhook处理中
    public function handlePaymentApproved($paypalOrderId)
    {
        // 捕获已批准的支付
        $captureResult = $this->capturePayment($paypalOrderId);

        if ($captureResult && isset($captureResult['status']) && $captureResult['status'] === 'COMPLETED') {
            // 支付已成功捕获，更新订单状态
            // 处理订单完成逻辑...
            return true;
        } else {
            // 捕获失败
            return false;
        }
    }

    /**
     * 生成 PayPal H5 支付链接
     * @param string $orderNo 本地订单号
     * @param float $amount 支付金额
     * @param string $currency 货币代码
     * @param string $returnUrl 支付成功回调
     * @param string $cancelUrl 取消支付回调
     * @param string $description 订单描述
     * @return array 结果数组，包含订单ID和支付链接
     */
    public function generatePaymentLink($orderNo, $amount, $currency = 'USD', $returnUrl = '', $cancelUrl = '', $description = '')
    {
        $orderResult = $this->createOrder($orderNo, $amount, $currency, $returnUrl, $cancelUrl, $description);

        if (empty($orderResult) || empty($orderResult['id']) || !isset($orderResult['links'])) {
            return [null, null];
        }
        $link = '';
        // 查找支付链接
        foreach ($orderResult['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $link = $link['href'];
                break;
            }
        }
        if (empty($link)) {
            return [null, null];
        }

        return [$orderResult['id'], $link];
    }

    /**
     * 验证PayPal回调通知
     * 
     * @param string $orderId PayPal订单ID
     * @return array|null 订单详情
     */
    public function verifyPayment($orderId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $client = new Client();
        try {
            $response = $client->request('GET', $this->apiBase . "/v2/checkout/orders/{$orderId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]);

            $result = json_decode($response->getBody(), true);

            // 记录验证结果
            Log::info('PayPal验证订单: ' . json_encode($result));

            return $result;
        } catch (\Exception $e) {
            Log::error('PayPal验证订单失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 完整处理H5支付流程
     * @param string $orderNo 本地订单号
     * @param float $amount 支付金额
     * @param string $currency 货币代码
     * @param string $returnUrl 支付成功回调
     * @param string $cancelUrl 支付取消回调
     * @param string $description 订单描述
     * @return array 结果数组，包含状态和支付链接或错误信息
     */
    public function h5Pay($orderNo, $amount, $currency = 'USD', $returnUrl = '', $cancelUrl = '', $description = '')
    {
        // 生成支付链接
        list($orderId, $paymentLink) = $this->generatePaymentLink($orderNo, $amount, $currency, $returnUrl, $cancelUrl, $description);

        if ($paymentLink) {
            return [1, $orderId, $paymentLink];
        } else {
            return [0, 'failed to create paypal payment url', null];
        }
    }
}
