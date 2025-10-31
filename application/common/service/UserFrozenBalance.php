<?php

namespace app\common\service;

use app\common\model\UserFrozenBalances;
use app\common\model\Users;
use think\Db;
use think\facade\Log;

class UserFrozenBalance
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
            $user = Users::where('id', $userId)->field("balance_frozen,id")->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $user->balance_frozen += $amount;
            $user->save();

            UserFrozenBalances::create([
                'user_id' => $userId,
                'balance_before' => $user->balance_frozen - $amount,
                'balance_after' => $user->balance_frozen,
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
            $user = Users::where('id', $userId)->field("balance_frozen,id")->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $user->balance_frozen -= $amount;
            $user->save();

            UserFrozenBalances::create([
                'user_id' => $userId,
                'balance_before' => $user->balance_frozen + $amount,
                'balance_after' => $user->balance_frozen,
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
     * 清空用户冻结余额 - 用于清分操作
     * @param int $userId 用户ID
     * @param float $amount 冻结余额金额
     * @param string $description 描述
     * @return bool
     * @throws \Exception
     */
    public static function clearUserBalance($userId, $amount, $description = '管理员清分')
    {
        // 如果冻结余额为0，直接返回
        if ($amount <= 0) {
            return true;
        }

        UserFrozenBalances::create([
            'user_id' => $userId,
            'balance_before' => $amount,
            'balance_after' => 0,
            'amount' => -$amount,
            'type' => 'clear_score',
            'description' => $description,
            'related_id' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
