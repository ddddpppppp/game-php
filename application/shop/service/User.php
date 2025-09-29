<?php

namespace app\shop\service;

use app\common\helper\ArrayHelper;
use app\common\helper\TimeHelper;
use app\common\model\Canada28Bets;
use app\common\model\Transactions;
use app\common\model\Users;

class User
{
    /**
     * 获取单个用户统计数据
     */
    public static function getUserStats($userId)
    {
        if (empty($userId)) {
            return null;
        }

        // 获取用户信息
        $user = Users::where('id', $userId)->field('id,uuid')->find();
        if (empty($user)) {
            return null;
        }

        $stats = [
            'user_id' => $userId,
            'summary' => [
                'total_recharge_amount' => 0,
                'total_recharge_count' => 0,
                'total_withdraw_amount' => 0,
                'total_withdraw_count' => 0,
                'total_bet_amount' => 0,
                'total_bet_count' => 0
            ],
            'recent_records' => [
                'recharge' => [],
                'withdraw' => [],
                'bet' => []
            ]
        ];

        // 获取充值统计和记录
        $rechargeStats = Transactions::where('user_id', $userId)
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->field('count(*) as total_count, sum(amount) as total_amount')
            ->find();

        if ($rechargeStats) {
            $stats['summary']['total_recharge_count'] = $rechargeStats['total_count'] ?: 0;
            $stats['summary']['total_recharge_amount'] = $rechargeStats['total_amount'] ?: 0;
        }

        // 获取最近20条充值记录
        $rechargeRecords = Transactions::where('user_id', $userId)
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->field('amount, created_at')
            ->order('id desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($rechargeRecords as &$recharge) {
            $recharge['created_at'] = TimeHelper::convertFromUTC($recharge['created_at']);
        }
        unset($recharge);

        $stats['recent_records']['recharge'] = $rechargeRecords;

        // 获取提现统计和记录
        $withdrawStats = Transactions::where('user_id', $userId)
            ->where('type', 'withdraw')
            ->where('status', 'in', ['completed'])
            ->field('count(*) as total_count, sum(amount) as total_amount')
            ->find();

        if ($withdrawStats) {
            $stats['summary']['total_withdraw_count'] = $withdrawStats['total_count'] ?: 0;
            $stats['summary']['total_withdraw_amount'] = $withdrawStats['total_amount'] ?: 0;
        }

        // 获取最近20条提现记录
        $withdrawRecords = Transactions::where('user_id', $userId)
            ->where('type', 'withdraw')
            ->field('amount, created_at, status')
            ->order('id desc')
            ->limit(20)
            ->select()
            ->toArray();
        foreach ($withdrawRecords as &$withdraw) {
            $withdraw['created_at'] = TimeHelper::convertFromUTC($withdraw['created_at']);
            $withdraw['status_text'] = Transactions::getStatusText()[$withdraw['status']];
            $withdraw['status_color'] = Transactions::getStatusColor()[$withdraw['status']];
        }
        unset($withdraw);

        $stats['recent_records']['withdraw'] = $withdrawRecords;

        // 获取下注统计和记录
        $betStats = Canada28Bets::where('user_id', $user['uuid'])
            ->field('count(*) as total_count, sum(amount) as total_amount')
            ->find();

        if ($betStats) {
            $stats['summary']['total_bet_count'] = $betStats['total_count'] ?: 0;
            $stats['summary']['total_bet_amount'] = $betStats['total_amount'] ?: 0;
        }

        // 获取最近100条下注记录
        $betRecords = Canada28Bets::where('user_id', $user['uuid'])
            ->field('amount, status, bet_name, created_at, period_number, multiplier')
            ->order('id desc')
            ->limit(100)
            ->select()
            ->toArray();

        // 计算输赢状态
        foreach ($betRecords as &$bet) {
            $bet['created_at'] = TimeHelper::convertFromUTC($bet['created_at']);
            $bet['win_amount'] = $bet['status'] == 'win' ? $bet['amount'] * $bet['multiplier'] : 0;
        }
        unset($bet);

        $stats['recent_records']['bet'] = $betRecords;

        return $stats;
    }
}
