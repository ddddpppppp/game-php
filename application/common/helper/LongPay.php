<?php

namespace app\common\helper;

class LongPay
{

    private $mid = '100000000000000';
    private $tid = '100000000000000';
    private $tidKey = '100000000000000';
    private $version = 'V1.0';
    private $paymentType = 'CASHAPP';
    private $transId = '38';
    private $signType = 'MD5';
    private $billCountryCode = 'US';


    public function __construct($params)
    {
        $this->mid = $params['mid'];
        $this->tid = $params['tid'];
        $this->tidKey = $params['tidKey'];
    }

    public function createOrder($orderNo, $amount, $currency = 'USD', $clientIp = '', $shopUrl = '', $returnUrl = '')
    {
        $url = 'https://qilinpay.us/api/pay/create';
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
        $params['returnUrl'] = $returnUrl ?: url('/api/notify/successCommonReturn', [], false, true);
        $params['shopUrl'] = $shopUrl;
        $params['ipAddress'] = $clientIp;
        $params['billCountryCode'] = $this->billCountryCode;
        $params['remark'] = 'remark';
        $params['signature'] = $this->getSignature($params);
        $reuslt = postData($url, $params, ["Content-Type: application/x-www-form-urlencoded"], true);
        dd($params, $reuslt);
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

    private function getSignature($params)
    {
        $params = array_filter($params, function ($value) {
            return !is_null($value);
        });
        ksort($params);
        $params = http_build_query($params);
        return md5($params . $this->tidKey);
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
