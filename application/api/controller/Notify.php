<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\Usdt;
use app\common\controller\Controller;
use app\common\enum\RedisKey;
use app\common\model\Transactions;
use app\common\service\UserBalance;
use think\Db;
use think\facade\Cache;
use think\facade\Log;

class Notify extends Controller
{
    protected $params = [];
    protected $data = [];

    /**
     * Initialize method for Takeout App
     */
    public function initialize()
    {
        $this->params = request()->get();
        $this->data = request()->param();
    }

    public function successCommonReturn()
    {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>Payment Status Checking</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f7f9fc;margin:0;padding:0;display:flex;justify-content:center;align-items:center;min-height:100vh;color:#333;-webkit-tap-highlight-color:transparent}.container{background:#fff;border-radius:16px;box-shadow:0 10px 25px rgba(0,0,0,.06);width:92%;max-width:450px;padding:24px 16px;text-align:center;margin:15px}.spinner{display:inline-block;width:40px;height:40px;margin:0 auto 20px}.spinner:after{content:"";display:block;width:30px;height:30px;margin:5px;border-radius:50%;border:3px solid #4285f4;border-color:#4285f4 transparent #4285f4 transparent;animation:spin 1.2s linear infinite}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}h1{font-size:20px;font-weight:500;margin:15px 0;color:#1a73e8}p{font-size:16px;line-height:1.4;color:#5f6368;margin:12px 0}.notice{color:#d93025;font-weight:500;font-size:18px}.progress-bar{background:#e8f0fe;border-radius:8px;height:6px;overflow:hidden;margin:20px auto;position:relative;width:85%}.progress{position:absolute;height:100%;background:#1a73e8;width:20%;border-radius:8px;animation:progress 2s infinite ease-in-out}@keyframes progress{0%{width:20%;left:0}50%{width:30%;left:70%}100%{width:20%;left:0}}</style></head><body><div class="container"><div class="spinner"></div><h1>Checking Payment Status</h1><div class="progress-bar"><div class="progress"></div></div><p class="notice">IMPORTANT: This page only indicates that we are CHECKING your payment status. It does NOT confirm that your payment was successful.</p><p>Please wait while we verify your payment with the payment provider...</p><p>The page will automatically redirect when payment is confirmed.</p></div></body></html>';
    }

    public function dfpayNotify()
    {
        $paymentNo = $this->data['mchOrderNo'];
        $lock = Cache::store('redis')->setNx(sprintf(RedisKey::PAY_PROCESSING, $paymentNo), '1');
        if (!$lock) {
            return $this->error('payment order is being processed');
        }
        try {
            // 查询订单信息
            $order = Transactions::where('order_no', $paymentNo)
                ->field('id,status,order_no,user_id,amount,gift')
                ->find();

            if (!$order) {
                Log::error("dfPay回调订单不存在: {$paymentNo}");
                throw new \Exception('订单不存在');
            }

            // 检查订单状态，防止重复回调
            if ($order->status == 'completed') {
                Log::info("dfPay回调订单已完成: {$paymentNo}");
                throw new \Exception('订单已回调');
            }

            // 开始数据库事务
            Db::startTrans();
            try {
                // 更新订单状态
                $order->status = 'completed';
                $order->completed_at = date('Y-m-d H:i:s');
                $order->save();

                // 增加用户余额
                if ($order->amount > 0) {
                    UserBalance::addUserBalance($order->user_id, $order->amount, 'deposit', "Cashapp Online Deposit Success, Order No: {$paymentNo}, Amount: {$order->amount}", $order->id);
                }

                // 处理赠送金额
                if (isset($order->gift) && $order->gift > 0) {
                    UserBalance::addUserBalance($order->user_id, $order->gift, 'gift', "Cashapp Online Deposit Gift, Order No: {$paymentNo}, Gift Amount: {$order->gift}", $order->id);
                }

                Db::commit();
                Log::info("Cashapp Online Deposit Success: 用户ID={$order->user_id}, 订单号={$paymentNo}, 金额={$order->amount}");
            } catch (\Exception $e) {
                Db::rollback();
                Log::error("Cashapp Online Deposit Failed: {$e->getMessage()}");
                return $this->error('充值处理失败: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        } finally {
            Cache::store('redis')->del(sprintf(RedisKey::PAY_PROCESSING, $paymentNo));
        }
        echo 'success';
    }


    public function freePayNotify()
    {
        try {
            Log::info('freePay通知开始');
            Log::info('请求参数: ' . json_encode(request()->param()));
            Log::info('POST数据: ' . json_encode(request()->post()));

            // 获取请求参数
            $params = request()->param();
            $mchOrderNo = $params['MchOrderNo'] ?? '';
            $state = $params['State'] ?? '';
            $amount = $params['Amount'] ?? '';
            $currency = $params['Currency'] ?? '';
            $platOrderNo = $params['PlatOrderNo'] ?? '';

            // 参数验证
            if (empty($mchOrderNo) || empty($state) || empty($amount) || empty($currency)) {
                Log::error('freePay回调参数错误: ' . json_encode($params));
                return $this->error('参数错误', 400);
            }

            // 使用Redis锁防止并发处理
            $lockKey = sprintf(RedisKey::PAY_PROCESSING, $mchOrderNo);
            $lockValue = uniqid();
            $lockTtl = 30; // 30秒锁定时间

            // 尝试获取锁
            if (!Cache::store('redis')->set($lockKey, $lockValue, $lockTtl, ['nx'])) {
                Log::warning("freePay回调正在处理中，订单号: {$mchOrderNo}");
                return $this->error('订单正在处理中', 423);
            }

            try {
                // 只处理成功状态的回调
                if ($state == "2") {
                    // 查询订单信息
                    $order = Transactions::where('order_no', $mchOrderNo)
                        ->field('id,status,order_no,user_id,amount,gift')
                        ->find();

                    if (!$order) {
                        Log::error("freePay回调订单不存在: {$mchOrderNo}");
                        throw new \Exception('订单不存在');
                    }

                    // 检查订单状态，防止重复回调
                    if ($order->status == 'completed') {
                        Log::info("freePay回调订单已完成: {$mchOrderNo}");
                        throw new \Exception('订单已回调');
                    }

                    // 验证平台订单号（如果有的话）
                    if (!empty($platOrderNo) && isset($order->platform_order_no) && $order->platform_order_no != $platOrderNo) {
                        Log::error("freePay回调订单号不匹配: 商户订单号={$mchOrderNo}, 平台订单号={$platOrderNo}");
                        throw new \Exception('订单号不符出错');
                    }

                    // 开始数据库事务
                    Db::startTrans();
                    try {
                        // 更新订单状态
                        $order->status = 'completed';
                        $order->completed_at = date('Y-m-d H:i:s');
                        $order->save();

                        // 增加用户余额
                        if ($order->amount > 0) {
                            UserBalance::addUserBalance($order->user_id, $order->amount, 'deposit', "Usdc Online Deposit Success, Order No: {$mchOrderNo}, Amount: {$order->amount}", $order->id);
                        }

                        // 处理赠送金额
                        if (isset($order->gift) && $order->gift > 0) {
                            UserBalance::addUserBalance($order->user_id, $order->gift, 'gift', "Usdc Online Deposit Gift, Order No: {$mchOrderNo}, Gift Amount: {$order->gift}", $order->id);
                        }

                        Db::commit();
                        Log::info("freePay充值成功: 用户ID={$order->user_id}, 订单号={$mchOrderNo}, 金额={$order->amount}");
                    } catch (\Exception $e) {
                        Db::rollback();
                        Log::error("freePay充值处理失败: {$e->getMessage()}");
                        return $this->error('充值处理失败: ' . $e->getMessage(), 500);
                    }
                } else {
                    Log::info("freePay回调状态非成功: 订单号={$mchOrderNo}, 状态={$state}");
                    return $this->success('已接收');
                }
            } finally {
                // 释放锁
                $script = "
                    if redis.call('get', KEYS[1]) == ARGV[1] then
                        return redis.call('del', KEYS[1])
                    else
                        return 0
                    end
                ";
                Cache::store('redis')->eval($script, [$lockKey, $lockValue], 1);
            }
        } catch (\Exception $e) {
            Log::error('freePay回调处理异常: ' . $e->getMessage());
            return $this->error('系统异常', 500);
        }
        echo 'ok';
    }
}
