<?php

namespace app\common\helper;

use app\common\enum\Bot;
use app\common\helper\TgHelper;

class LongPay
{

    private $url = 'https://qilinpay.us/api/pay/create';
    private $mid = '100000000000000';
    private $tid = '100000000000000';
    private $appKey = '100000000000000';
    private $version = 'V1.0';
    private $paymentType = 'CASHAPP';
    private $transId = '39';
    private $signType = 'MD5';
    private $billCountryCode = 'US';


    public function __construct($params)
    {
        $this->url = $params['url'] ?? $this->url;
        $this->mid = $params['mid'];
        $this->tid = $params['tid'];
        $this->appKey = $params['appKey'] ?? $params['tidKey']; // 兼容旧参数名
    }

    public function createOrder($orderNo, $amount, $currency = 'USD', $clientIp = '', $shopUrl = '', $returnUrl = '')
    {
        $url = $this->url;
        $params = [];
        $params['reference_id'] = $orderNo;
        $params['version'] = $this->version;
        $params['paymenttype'] = $this->paymentType;
        $params['transId'] = $this->transId;
        $params['signType'] = $this->signType;
        $params['mid'] = intval($this->mid);
        $params['tid'] = intval($this->tid);
        $params['timestamp'] = time() * 1000;
        $params['orderId'] = $orderNo;
        $params['amount'] = strval($amount);
        $params['currencyCode'] = $currency;
        $params['notifyUrl'] = SITE_ROOT . '/api/notify/longpay';
        $params['returnUrl'] = $returnUrl ?: url('/api/notify/payReturn', [], false, true);
        $params['shopUrl'] = $shopUrl;
        $params['ipAddress'] = $clientIp;
        $params['billCountryCode'] = $this->billCountryCode;
        $params['remark'] = 'remark';
        $params['signature'] = $this->getSignature($params);
        $result = postData($url, $params, ["Content-Type: application/x-www-form-urlencoded"], true);
        if (empty($result)) {
            return [-1, '请求失败'];
        }
        $result = json_decode($result, true);
        if ($result['respCode'] != '0000' && $result['respCode'] != 'P000') {
            $respDesc = $result['respDesc'] ?? $this->getErrorDesc($result['respCode']);
            return [-1, $result['respCode'] . ' ' . $respDesc];
        }
        return [1, $result['redirectUrl']];
    }

    /**
     * 创建提现订单
     * @param string $orderNo 订单号
     * @param string $account 账户
     * @param int $amount 金额(分为单位)
     * @param string $ip IP地址
     * @return array [payId, error]
     */
    public function createWithdrawOrder($orderNo, $account, $amount, $ip = '')
    {
        if ($amount < 10) {
            return ['', '金额必须大于10分'];
        }
        if ($ip == '') {
            $ip = ServerHelper::getServerIp();
        }

        $params = [
            'reference_id' => $orderNo,
            'version' => $this->version,
            'amount' => sprintf('%.2f', $amount), // 分转元，保留两位小数
            'paymenttype' => 'CASHAPPOUT',
            'transId' => $this->transId,
            'signType' => $this->signType,
            'mid' => $this->mid,
            'tid' => $this->tid,
            'timestamp' => time() * 1000, // 毫秒时间戳
            'orderId' => $orderNo,
            'buyerTag' => $account,
            'currencyCode' => 'USD',
            'notifyUrl' => 'https://php.game-hub.cc/api/notify/longWithdraw',
            'shopUrl' => 'https://php.game-hub.cc',
            'ipAddress' => $ip,
            'billCountryCode' => $this->billCountryCode,
            'check' => '0',
        ];

        $params['signature'] = $this->getSignature($params);
        $result = postData($this->url, $params, ["Content-Type: application/x-www-form-urlencoded"], true);

        if (empty($result)) {
            return ['', '请求失败'];
        }

        $result = json_decode($result, true);
        if ($result['respCode'] != '0000' && $result['respCode'] != 'P000') {
            $errorMsg = $result['respDesc'] ?? $result['msg'] ?? $this->getErrorDesc($result['respCode']);
            return ['', $errorMsg];
        }

        $payId = $result['reference_id'] ?? '';

        TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, sprintf("⚠️提现订单创建成功 \n💵金额: %s ", $amount));
        return [$payId, ''];
    }

    private function getSignature($params)
    {
        // 过滤空值
        $params = array_filter($params, function ($value) {
            return !is_null($value) && $value !== '';
        });

        // 按键名ASCII顺序排序
        ksort($params);

        // 构建查询字符串，参考Go版本的 JoinStringsInASCII 方法
        $str = $this->joinStringsInASCII($params);

        // 添加appKey并生成MD5签名
        $str .= '&' . $this->appKey;

        return md5($str);
    }

    /**
     * 按ASCII顺序连接参数，模拟Go版本的 utils.JoinStringsInASCII 方法
     * @param array $params
     * @return string
     */
    private function joinStringsInASCII($params)
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $pairs[] = $key . '=' . $value;
            }
        }
        return implode('&', $pairs);
    }

    public function getErrorDesc($code)
    {
        $errorDesc = [
            '0000' => '成功',
            'P000' => '交易处理中',
            '0001' => '请求参数非法',
            '0002' => '商户号不能为空',
            '0003' => '商户订单号不能为空',
            '0004' => '银行预留手机号不能为空',
            '0005' => '交易金额不能为空',
            '0006' => '卡号不能为空',
            '0007' => 'CVN2不能为空',
            '0008' => '信用卡有效期不能为空',
            '0009' => '验签字段不能为空',
            '0010' => '商户信息不存在',
            '0011' => '商户状态异常',
            '0012' => '校验签名失败',
            '0013' => '商户密钥信息不存在',
            '0014' => '商户密钥已失效',
            '0015' => '获取商户密钥ID失败',
            '0016' => '产品信息不存在',
            '0017' => '产品信息状态异常',
            '0018' => '未授权的交易',
            '0019' => '渠道约束信息为空',
            '0020' => '不支持的卡类型',
            '0021' => '不支持借记卡交易',
            '0022' => '不支持贷记卡交易',
            '0023' => '订单号重复',
            '0024' => '订单信息保存失败',
            '0025' => '验证银行结果签名失败',
            '0026' => '支付信息保存失败',
            '0027' => '未知交易类型',
            '0028' => '原交易不存在',
            '0029' => '原交易状态异常',
            '0030' => '交易金额超限',
            '0031' => '路由筛选失败',
            '0032' => '交易金额非法',
            '0033' => '银行卡信息保存失败',
            '0034' => '当天交易不支持退款',
            '0035' => '原交易已撤销',
            '0036' => '非当天交易不支持撤销',
            '0037' => '原交易已发起撤销或退货',
            '0038' => '不支持的银行卡',
            '0039' => '原交易不支持撤销',
            '0040' => '上一笔撤销交易正在处理中',
            '0041' => '上一笔退货交易正在处理中',
            '0042' => '余额不足',
            '0043' => '生成代付单失败',
            '0044' => '商品订单状态异常',
            '0045' => '支付订单状态异常',
            '0046' => '交易限额超限',
            '0047' => '交易金额与原交易不符',
            '0048' => '联行号不存在',
            '0049' => '该时间段不允许交易',
            '0050' => '订单日期无效',
            '9996' => '交易已退货',
            '9997' => '交易结果未知（应当发起交易查询）',
            '9998' => '交易失败（应当发起交易查询）',
            '9999' => '系统异常（应当发起交易查询）',
            '0068' => '明确失败',

        ];
        return $errorDesc[$code] ?? '未知错误';
    }
}
