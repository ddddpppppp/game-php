<?php

namespace app\common\service;

use app\common\model\UserBalances;
use app\common\model\Users;
use think\Db;
use think\facade\Log;

class UserBalance
{
    /**
     * 增加用户余额并记录日志
     * @param int $userId 用户ID
     * @param float $amount 金额
     * @param string $type 变动类型
     * @param string $description 描述
     * @param int $relatedId 关联ID
     * @return bool
     * @throws \Exception
     */
    public static function addUserBalance($userId, $amount, $type, $description, $relatedId = '')
    {
        \think\Db::startTrans();
        try {
            // 获取或创建用户余额记录
            $user = Users::where('id', $userId)->field("balance,balance_frozen,id")->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $user->balance += $amount;

            // 如果是gift相关类型，同时更新gift_balance字段
            if (in_array($type, ['gift'])) {
                UserFrozenBalance::addUserBalance($userId, $amount, $type, $description, $relatedId);
            }

            $user->save();

            UserBalances::create([
                'user_id' => $userId,
                'balance_before' => $user->balance - $amount,
                'balance_after' => $user->balance,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'related_id' => $relatedId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            \think\Db::commit();
            return true;
        } catch (\Exception $e) {
            \think\Db::rollback();
            throw $e;
        }
    }

    /**
     * 减少用户余额并记录日志
     * @param int $userId 用户ID
     * @param float $amount 金额
     * @param string $type 变动类型
     * @param string $description 描述
     * @param int $relatedId 关联ID
     * @return bool
     * @throws \Exception
     */
    public static function subUserBalance($userId, $amount, $type, $description, $relatedId = '')
    {
        \think\Db::startTrans();
        try {
            // 获取或创建用户余额记录
            $user = Users::where('id', $userId)->field("balance,balance_frozen,id")->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $user->balance -= $amount;

            // 如果是gift相关类型，同时更新gift_balance字段
            if (in_array($type, ['game_bet', 'withdraw']) && $user->balance_frozen > 0) {
                UserFrozenBalance::subUserBalance($userId, $user->balance_frozen > $amount ? $amount : $user->balance_frozen, $type, $description, $relatedId);
            }

            $user->save();

            UserBalances::create([
                'user_id' => $userId,
                'balance_before' => $user->balance + $amount,
                'balance_after' => $user->balance,
                'amount' => -$amount,
                'type' => $type,
                'description' => $description,
                'related_id' => $relatedId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            \think\Db::commit();
            return true;
        } catch (\Exception $e) {
            \think\Db::rollback();
            throw $e;
        }
    }

    /**
     * 用户清分 - 将用户余额重置为0
     * @param int $userId 用户ID
     * @param string $description 描述
     * @return bool
     * @throws \Exception
     */
    public static function clearUserBalance($userId, $description = '管理员清分')
    {
        \think\Db::startTrans();
        try {
            // 获取用户信息
            $user = Users::where('id', $userId)->field("balance,balance_frozen,id")->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            // 如果余额已经为0，直接返回
            if ($user->balance <= 0 && $user->balance_frozen <= 0) {
                \think\Db::commit();
                return true;
            }

            $balanceBefore = $user->balance;
            $frozenBalanceBefore = $user->balance_frozen;

            // 清空普通余额
            if ($user->balance > 0) {
                $user->balance = 0;
                UserBalances::create([
                    'user_id' => $userId,
                    'balance_before' => $balanceBefore,
                    'balance_after' => 0,
                    'amount' => -$balanceBefore,
                    'type' => 'clear_score',
                    'description' => $description,
                    'related_id' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // 清空冻结余额
            if ($user->balance_frozen > 0) {
                UserFrozenBalance::clearUserBalance($userId, $frozenBalanceBefore, $description);
                $user->balance_frozen = 0;
            }

            $user->save();

            \think\Db::commit();
            return true;
        } catch (\Exception $e) {
            \think\Db::rollback();
            throw $e;
        }
    }

    /**
     * 完成充值
     */
    public static function completeDeposit($deposit)
    {
        try {
            Db::startTrans();

            // 更新充值状态
            $deposit->status = 'completed';
            $deposit->completed_at = date('Y-m-d H:i:s');
            $deposit->save();

            // 增加用户余额
            self::addUserBalance($deposit->user_id, $deposit->amount, 'deposit', "USDT recharge received, amount: {$deposit->amount}", $deposit->id);
            $deposit->gift > 0 && self::addUserBalance($deposit->user_id, $deposit->gift, 'deposit_gift', "USDT recharge received, gift amount: {$deposit->gift}", $deposit->id);
            Db::commit();
            Log::info("充值成功: 用户ID={$deposit->user_id}, 订单号={$deposit->order_no}, 金额={$deposit->amount}");
            return [1, '充值成功'];
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('完成充值失败: ' . $e->getMessage());
            return [0, '充值处理失败: ' . $e->getMessage()];
        }
    }


    /**
     * 提现退款
     */
    public static function refundWithdraw($withdraw)
    {
        // 增加用户余额
        self::addUserBalance($withdraw->user_id, $withdraw->amount, 'withdraw_failed_refund', "withdraw failed refund, amount: {$withdraw->amount}", $withdraw->id);
    }
}
