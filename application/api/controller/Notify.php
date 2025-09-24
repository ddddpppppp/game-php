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

    public function payReturn()
    {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>Payment Status</title><style>*{box-sizing:border-box}body{font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;background:hsl(0 0% 3.9%);margin:0;padding:0;display:flex;justify-content:center;align-items:center;min-height:100vh;color:hsl(0 0% 98%);-webkit-tap-highlight-color:transparent;font-size:16px}@media(max-width:640px){body{font-size:14px}}.container{background:hsl(0 0% 9%);border:1px solid hsl(0 0% 14.9%);border-radius:12px;width:95%;max-width:400px;padding:40px 20px;text-align:center;margin:20px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05)}@media(max-width:640px){.container{width:90%;padding:32px 16px;margin:16px}}.spinner{display:inline-block;width:32px;height:32px;margin:0 auto 32px}@media(max-width:640px){.spinner{width:28px;height:28px;margin:0 auto 28px}}.spinner:after{content:"";display:block;width:28px;height:28px;margin:2px;border-radius:50%;border:3px solid hsl(0 0% 98%);border-color:hsl(0 0% 98%) transparent hsl(0 0% 98%) transparent;animation:spin 1.2s linear infinite}@media(max-width:640px){.spinner:after{width:24px;height:24px;border-width:2px}}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}h1{font-size:28px;font-weight:600;margin:0 0 20px 0;color:hsl(0 0% 98%);letter-spacing:-0.025em}@media(max-width:640px){h1{font-size:24px;margin:0 0 16px 0}}p{font-size:18px;line-height:1.5;color:hsl(0 0% 63.9%);margin:0}@media(max-width:640px){p{font-size:16px}}</style></head><body><div class="container"><div class="spinner"></div><h1>Checking Payment</h1><p>If you have already paid, please close this page</p></div></body></html>';
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
