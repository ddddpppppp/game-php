<?php

namespace app\common\service;

use app\common\helper\ArrayHelper;
use app\shop\enum\Merchant;
use think\Db;
use think\Exception;
use app\shop\model\Admin;
use app\shop\model\AdminBalance as AdminBalanceModel;
use app\shop\model\AdminBalanceFrozen;

class AdminBalance
{
    /**
     * 批量增加管理员余额并记录历史
     * 
     * @param array $balanceData 格式为 [['admin_id' => 'xxx', 'money' => 100, 'merchant_id' => 'yyy', 'remark' => '备注', 'relate_id' => 'zzz'], [...]]
     * @param string $type 变动类型
     * @return array [status, message]
     */
    public static function batchIncreaseBalance(array $balanceData, string $type = 'takeout_bonus', bool $isFrozen = false)
    {
        if (empty($balanceData)) {
            return [0, '数据为空'];
        }

        // 提取所有管理员ID
        $adminIds = array_column($balanceData, 'admin_id');

        // 查询这些管理员的当前余额
        $admins = Admin::where('uuid', 'in', $adminIds)
            ->field('id,uuid,balance')
            ->select()
            ->toArray();

        if (empty($admins)) {
            return [0, '未找到有效的管理员'];
        }
        $adminsMap = ArrayHelper::setKey($admins, 'uuid');

        // 开始事务
        Db::connect('mysql')->startTrans();
        try {
            $balanceLogs = [];
            $balanceFrozenLogs = [];
            $currentTime = date('Y-m-d H:i:s');

            foreach ($balanceData as $item) {
                if (!isset($item['admin_id']) || !isset($item['money'])) {
                    continue;
                }

                $adminId = $item['admin_id'];
                $money = floatval($item['money']);

                // 跳过金额为0的记录
                if ($money == 0) {
                    continue;
                }

                // 检查管理员是否存在
                if (!isset($adminsMap[$adminId])) {
                    continue;
                }

                $admin = $adminsMap[$adminId];
                $oldBalance = floatval($admin['balance'] ?? 0);
                $newBalance = bcadd($oldBalance, $money, 2);

                // 更新管理员余额 - 确保使用正确的字段名和ID
                if ($isFrozen) {
                    // 准备余额记录数据
                    $balanceLog = [
                        'admin_id' => $adminId,
                        'merchant_id' => $item['merchant_id'] ?? '',
                        'level' => $item['level'] ?? 0,
                        'relate_id' => $item['relate_id'] ?? '',
                        'type' => $type,
                        'money' => $money,
                        'remark' => $item['remark'] ?? '',
                        'created_at' => $currentTime,
                    ];
                    $balanceFrozenLogs[] = $balanceLog;
                } else {
                    Admin::update([
                        'balance' => $newBalance
                    ], ['uuid' => $adminId]);
                    // 准备余额记录数据
                    $balanceLog = [
                        'admin_id' => $adminId,
                        'merchant_id' => $item['merchant_id'] ?? '',
                        'level' => $item['level'] ?? 0,
                        'relate_id' => $item['relate_id'] ?? '',
                        'type' => $type,
                        'money' => $money,
                        'history_money' => $oldBalance,
                        'remark' => $item['remark'] ?? '',
                        'created_at' => $currentTime,
                    ];
                    $balanceLogs[] = $balanceLog;

                    // 更新缓存中的余额
                    $adminsMap[$adminId]['balance'] = $newBalance;
                }
            }
            if (!empty($balanceLogs)) {
                AdminBalanceModel::insertAll($balanceLogs);
            }
            if (!empty($balanceFrozenLogs)) {
                AdminBalanceFrozen::insertAll($balanceFrozenLogs);
            }
            // 提交事务
            Db::connect('mysql')->commit();
            return [1, '余额更新成功'];
        } catch (Exception $e) {
            // 回滚事务
            Db::connect('mysql')->rollback();
            return [0, '余额更新失败: ' . $e->getMessage()];
        }
    }

    /**
     * 增加单个管理员余额并记录历史
     * 
     * @param array $balanceData 格式为 ['admin_id' => 'xxx', 'money' => 100, 'merchant_id' => 'yyy', 'remark' => '备注', 'relate_id' => 'zzz']
     * @param string $type 变动类型
     * @return array [status, message]
     */
    public static function increaseBalance(array $balanceData, string $type = 'takeout_bonus', bool $isFrozen = false)
    {
        if (empty($balanceData) || !isset($balanceData['admin_id']) || !isset($balanceData['money'])) {
            return [0, '参数错误'];
        }

        // 将单个数据转换为批量方法所需的数组格式
        $batchData = [$balanceData];

        // 复用批量处理方法
        return self::batchIncreaseBalance($batchData, $type, $isFrozen);
    }

    /**
     * 批量减少管理员余额并记录历史
     * 
     * @param array $balanceData 格式为 [['admin_id' => 'xxx', 'money' => 100, 'merchant_id' => 'yyy', 'remark' => '备注', 'relate_id' => 'zzz'], [...]]
     * @param string $type 变动类型
     * @param bool $allowNegative 是否允许余额为负数
     * @return array [status, message]
     */
    public static function batchDecreaseBalance(array $balanceData, string $type = 'withdraw', bool $allowNegative = false)
    {
        if (empty($balanceData)) {
            return [0, '数据为空'];
        }

        // 提取所有管理员ID
        $adminIds = array_column($balanceData, 'admin_id');

        // 查询这些管理员的当前余额
        $admins = Admin::where('uuid', 'in', $adminIds)
            ->field('id,uuid,balance')
            ->select()
            ->toArray();

        if (empty($admins)) {
            return [0, '未找到有效的管理员'];
        }
        $adminsMap = ArrayHelper::setKey($admins, 'uuid');

        // 开始事务
        Db::connect('mysql')->startTrans();
        try {
            $balanceLogs = [];
            $currentTime = date('Y-m-d H:i:s');
            $insufficientBalance = false;
            $insufficientAdmin = '';

            foreach ($balanceData as $item) {
                if (!isset($item['admin_id']) || !isset($item['money'])) {
                    continue;
                }

                $adminId = $item['admin_id'];
                $money = floatval($item['money']);

                // 确保金额为正数
                if ($money <= 0) {
                    continue;
                }

                // 检查管理员是否存在
                if (!isset($adminsMap[$adminId])) {
                    continue;
                }

                $admin = $adminsMap[$adminId];
                $oldBalance = floatval($admin['balance'] ?? 0);

                // 检查余额是否足够
                if (!$allowNegative && $oldBalance < $money) {
                    $insufficientBalance = true;
                    $insufficientAdmin = $adminId;
                    break;
                }

                // 减去余额
                $newBalance = bcsub($oldBalance, $money, 2);

                // 更新管理员余额
                Admin::update([
                    'balance' => $newBalance
                ], ['uuid' => $adminId]);

                // 准备余额记录数据 (注意这里money是负数)
                $balanceLog = [
                    'admin_id' => $adminId,
                    'merchant_id' => $item['merchant_id'] ?? '',
                    'level' => $item['level'] ?? 0,
                    'relate_id' => $item['relate_id'] ?? '',
                    'type' => $type,
                    'money' => -$money, // 记录为负数表示减少
                    'history_money' => $oldBalance,
                    'remark' => $item['remark'] ?? '',
                    'created_at' => $currentTime,
                ];
                $balanceLogs[] = $balanceLog;

                // 更新缓存中的余额
                $adminsMap[$adminId]['balance'] = $newBalance;
            }

            // 如果有管理员余额不足
            if ($insufficientBalance) {
                Db::connect('mysql')->rollback();
                return [0, "管理员 {$insufficientAdmin} 余额不足"];
            }

            // 批量插入余额记录
            if (!empty($balanceLogs)) {
                AdminBalanceModel::insertAll($balanceLogs);
            }

            // 提交事务
            Db::connect('mysql')->commit();
            return [1, '余额更新成功'];
        } catch (Exception $e) {
            // 回滚事务
            Db::connect('mysql')->rollback();
            return [0, '余额更新失败: ' . $e->getMessage()];
        }
    }

    /**
     * 减少单个管理员余额并记录历史
     * 
     * @param array $balanceData 格式为 ['admin_id' => 'xxx', 'money' => 100, 'merchant_id' => 'yyy', 'remark' => '备注', 'relate_id' => 'zzz']
     * @param string $type 变动类型
     * @param bool $allowNegative 是否允许余额为负数
     * @return array [status, message]
     */
    public static function decreaseBalance(array $balanceData, string $type = 'withdraw', bool $allowNegative = false)
    {
        if (empty($balanceData) || !isset($balanceData['admin_id']) || !isset($balanceData['money'])) {
            return [0, '参数错误'];
        }

        // 将单个数据转换为批量方法所需的数组格式
        $batchData = [$balanceData];

        // 复用批量处理方法
        return self::batchDecreaseBalance($batchData, $type, $allowNegative);
    }
}
