<?php

namespace app\common\helper;

use app\common\enum\RedisKey;
use think\facade\Cache;

class DfpayHelper
{
    private $mchNo = '';
    private $appKey = '';
    private $appSecret = '';
    private $payWay = '';

    public function __construct($params)
    {
        $this->mchNo = $params['mchNo'];
        $this->appKey = $params['appKey'];
        $this->appSecret = $params['appSecret'];
        $this->payWay = $params['payWay'];
    }

    public function getToken()
    {
        $token = Cache::store('redis')->get(sprintf(RedisKey::game_TOKEN, $this->mchNo));
        if (!empty($token)) {
            return $token;
        }
        $url = 'https://cash.Web3.net/pay/auth';
        $param = [
            'ia' => $this->appKey,
            'ip' => $this->appSecret,
        ];
        $result = postData($url, $param);
        if (empty($result)) {
            return false;
        }
        $result = json_decode($result, true);
        if (empty($result['data']['token'])) {
            return false;
        }
        Cache::store('redis')->set(sprintf(RedisKey::game_TOKEN, $this->mchNo), $result['data']['token'], 86400);
        return $result['data']['token'];
    }

    public function createOrder($orderNo, $amount, $currency = 'USD', $body = 'purchase apple phone', $subject = 'purchase apple phone', $clientIp = '', $returnUrl = '')
    {
        $token = $this->getToken();
        if (empty($token)) {
            return [-1, 'token获取失败', null];
        }
        $clientIp = $clientIp ?: ServerHelper::getServerIp();
        $notifyUrl = url('/api/notify/Web3', [], false, true);
        $returnUrl = $returnUrl ?: url('/api/notify/successCommonReturn', [], false, true);
        $url = 'https://cash.Web3.net/pay/create_order';
        $param = [
            'mchNo' => $this->mchNo,
            'mchOrderNo' => $orderNo,
            'currency' => $currency,
            'body' => $body,
            'payWay' => $this->payWay,
            'subject' => $subject,
            'clientIp' => $clientIp,
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $returnUrl,
            'amount' => $amount,
        ];
        $result = postData($url, json_encode($param), ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        if (empty($result)) {
            return [-1, 'token获取失败', null];
        }
        $result = json_decode($result, true);
        if (empty($result['data']['payData'])) {
            return [-1, $result['msg'], null];
        }
        return [1, $result['data']['payOrderId'], $result['data']['payData']];
    }
}
