<?php

namespace app\api\validate;

use think\Validate;

class PaymentRequest extends Validate
{
    // 验证规则
    protected $rule = [
        'pay_way'     => 'require',
        'app_key'     => 'require',
        'amount'      => 'require|float|gt:0',
        'mch_order_no' => 'require|length:10,100',
        'notify_url'  => 'require|url',
        'return_url'  => 'require|url',
        'ip'          => 'require|ip',
        'sign'        => 'require',
    ];

    // 错误信息
    protected $message = [
        'pay_way.require'     => 'pay way is required',
        'app_key.require'     => 'app key is required',
        'amount.require'      => 'amount is required',
        'amount.float'        => 'amount must be a number',
        'amount.gt'           => 'amount must be greater than 0',
        'mch_order_no.require' => 'mch order no is required',
        'mch_order_no.length'  => 'mch order no length must be between 10 and 100 characters',
        'notify_url.require'  => 'notify url is required',
        'notify_url.url'      => 'notify url is invalid',
        'return_url.require'  => 'return url is required',
        'return_url.url'      => 'return url is invalid',
        'ip.require'          => 'ip is required',
        'ip.ip'               => 'ip is invalid',
        'sign.require'        => 'sign is required',
    ];

    // 验证场景
    protected $scene = [
        'create' => ['pay_way', 'app_key', 'amount', 'mch_order_no', 'notify_url', 'return_url', 'ip', 'user_id', 'sign'],
    ];
}
