<?php

namespace app\shop\service;

use app\common\helper\TimeHelper;
use think\Db;

class Canada
{
    /**
     * 获取指定时间段的统计数据
     */
    public static function getStatsForPeriod($startDate, $endDate)
    {
        // 充值统计（包含通道费计算）
        $depositStats = Db::table('game_transactions')
            ->alias('t')
            ->leftJoin('game_payment_channel c', 't.channel_id = c.id')
            ->where('t.type', 'deposit')
            ->where('t.status', 'completed')
            ->where('t.created_at', 'between', [$startDate, $endDate])
            ->where('t.deleted_at', null)
            ->field([
                'SUM(t.amount) as total_amount',
                'COUNT(*) as total_count',
                'SUM(t.amount * (c.rate / 100) + c.charge_fee) as total_channel_fee'
            ])
            ->find();

        // 提现统计（包含手续费）
        $withdrawStats = Db::table('game_transactions')
            ->where('type', 'withdraw')
            ->where('status', 'completed')
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->field([
                'SUM(amount) as total_amount',
                'SUM(fee) as total_fee',
                'COUNT(*) as total_count'
            ])
            ->find();

        $depositAmount = floatval($depositStats['total_amount'] ?? 0);
        $withdrawAmount = floatval($withdrawStats['total_amount'] ?? 0);
        $depositChannelFee = floatval($depositStats['total_channel_fee'] ?? 0);
        $withdrawFee = floatval($withdrawStats['total_fee'] ?? 0);

        // 毛利润 = 充值金额 - 提现金额 + 手续费
        $grossProfit = $depositAmount - $withdrawAmount + $withdrawFee;

        // 实际利润 = 充值金额 - 提现金额 + 手续费 - 充值通道费
        $realProfit = $depositAmount - $withdrawAmount + $withdrawFee - $depositChannelFee;

        return [
            'depositAmount' => number_format($depositAmount, 2),
            'depositCount' => intval($depositStats['total_count'] ?? 0),
            'withdrawAmount' => number_format($withdrawAmount, 2),
            'withdrawCount' => intval($withdrawStats['total_count'] ?? 0),
            'depositChannelFee' => number_format($depositChannelFee, 2),
            'withdrawFee' => number_format($withdrawFee, 2),
            'grossProfit' => number_format($grossProfit, 2),
            'realProfit' => number_format($realProfit, 2),
        ];
    }

    /**
     * 计算趋势百分比
     */
    public static function calculateTrends($current, $prev)
    {
        $trends = [];
        $fields = ['depositAmount', 'withdrawAmount', 'depositChannelFee', 'withdrawFee', 'grossProfit', 'realProfit'];

        foreach ($fields as $field) {
            $currentValue = $current[$field] ?? 0;
            $prevValue = $prev[$field] ?? 0;

            if ($prevValue == 0) {
                $percentage = $currentValue > 0 ? 100 : 0;
                $trend = $currentValue >= 0 ? 'up' : 'down';
            } else {
                $percentage = abs(($currentValue - $prevValue) / $prevValue * 100);
                $trend = $currentValue >= $prevValue ? 'up' : 'down';
            }

            $trends[$field] = [
                'value' => round($percentage, 1),
                'trend' => $trend
            ];
        }

        return $trends;
    }

    /**
     * 获取简化的图表数据（仅充值和提现）
     */
    public static function getSimpleChartData($startDate, $endDate, $period)
    {
        // 根据周期设置日期格式和分组
        switch ($period) {
            case 'day':
                $dateField = 'DATE(created_at)';
                break;
            case 'week':
                $dateField = 'YEARWEEK(created_at, 1)';
                break;
            case 'month':
                $dateField = 'DATE_FORMAT(created_at, "%Y-%m")';
                break;
            default:
                $dateField = 'DATE(created_at)';
        }

        // 充值数据
        $depositData = Db::table('game_transactions')
            ->field([
                $dateField . ' as date_group',
                'SUM(amount) as amount'
            ])
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->group('date_group')
            ->order('date_group ASC')
            ->select();

        // 提现数据
        $withdrawData = Db::table('game_transactions')
            ->field([
                $dateField . ' as date_group',
                'SUM(amount) as amount'
            ])
            ->where('type', 'withdraw')
            ->where('status', 'completed')
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->group('date_group')
            ->order('date_group ASC')
            ->select();

        // 生成完整的日期点
        $datePoints = self::generateDatePoints($startDate, $endDate, $period);

        // 填充数据
        $labels = [];
        $depositAmountData = [];
        $withdrawAmountData = [];

        // 将数据转换为以日期为key的数组便于查找
        $depositMap = [];
        foreach ($depositData as $item) {
            $depositMap[$item['date_group']] = $item['amount'];
        }

        $withdrawMap = [];
        foreach ($withdrawData as $item) {
            $withdrawMap[$item['date_group']] = $item['amount'];
        }

        foreach ($datePoints as $dateKey => $label) {
            $labels[] = $label;
            $depositAmountData[] = floatval($depositMap[$dateKey] ?? 0);
            $withdrawAmountData[] = floatval($withdrawMap[$dateKey] ?? 0);
        }

        return [
            'labels' => $labels,
            'depositData' => $depositAmountData,
            'withdrawData' => $withdrawAmountData,
        ];
    }

    /**
     * 生成日期点
     */
    public static function generateDatePoints($startDate, $endDate, $period)
    {
        $datePoints = [];
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        if ($period == 'day') {
            // 生成天级别的日期点
            while ($start <= $end) {
                $dateKey = $start->format('Y-m-d');
                $label = $start->format('m-d');
                $datePoints[$dateKey] = $label;
                $start->add(new \DateInterval('P1D'));
            }
        } elseif ($period == 'week') {
            // 生成周级别的日期点
            $start->modify('monday this week'); // 调整到周一
            while ($start <= $end) {
                $year = $start->format('Y');
                $week = $start->format('W');
                $dateKey = $year . str_pad($week, 2, '0', STR_PAD_LEFT); // YYYYWW格式
                $label = $start->format('Y年第W周');
                $datePoints[$dateKey] = $label;
                $start->add(new \DateInterval('P1W'));
            }
        } elseif ($period == 'month') {
            // 生成月级别的日期点
            while ($start <= $end) {
                $dateKey = $start->format('Y-m');
                $label = $start->format('Y-m');
                $datePoints[$dateKey] = $label;
                $start->add(new \DateInterval('P1M'));
            }
        }

        return $datePoints;
    }

    /**
     * 获取按渠道分组的财务统计数据
     */
    public static function getFinanceStatsByChannel($startDate, $endDate)
    {
        // 获取所有渠道类型（从充值渠道表获取）
        $channelTypes = Db::table('game_payment_channel')
            ->where('deleted_at', null)
            ->column('type');

        $financeStats = [];
        $totalStats = [
            'register_count' => 0,
            'deposit_count' => 0,
            'deposit_amount' => 0,
            'deposit_user_count' => 0,
            'gift_amount' => 0,
            'withdraw_count' => 0,
            'withdraw_amount' => 0,
            'withdraw_user_count' => 0,
            'channel_fee_rate' => 0,
            'gateway_fee' => 0,
            'total_channel_fee' => 0,
            'withdraw_fee' => 0,
            'user_balances' => 0,
            'profit' => 0
        ];

        // 计算总注册人数（只统计真实用户，不按渠道分组）
        $totalRegisterCount = Db::table('game_users')
            ->where('type', 'user') // 只统计真实用户
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->count();

        foreach ($channelTypes as $channelType) {
            // 获取该类型的充值渠道信息
            $paymentChannel = Db::table('game_payment_channel')
                ->field(['id', 'type'])
                ->where('type', $channelType)
                ->where('deleted_at', null)
                ->find();

            // 获取该类型的提现渠道信息
            $withdrawChannel = Db::table('game_withdraw_channel')
                ->field(['id', 'type'])
                ->where('type', $channelType)
                ->where('deleted_at', null)
                ->find();

            if (!$paymentChannel && !$withdrawChannel) {
                continue;
            }

            // 充值统计 - 关联充值渠道表，只统计真实用户
            $depositStats = Db::table('game_transactions')
                ->alias('t')
                ->leftJoin('game_payment_channel c', 't.channel_id = c.id')
                ->leftJoin('game_users u', 't.user_id = u.id')
                ->where('t.type', 'deposit')
                ->where('t.status', 'completed')
                ->where('c.type', $channelType)
                ->where('u.type', 'user') // 只统计真实用户
                ->where('t.created_at', 'between', [$startDate, $endDate])
                ->where('t.deleted_at', null)
                ->where('u.deleted_at', null)
                ->field([
                    'COUNT(*) as count',
                    'COUNT(DISTINCT t.user_id) as user_count',
                    'SUM(t.actual_amount) as total_amount',
                    'SUM(t.gift) as total_gift',
                    'SUM(t.actual_amount * (c.rate / 100)) as total_rate_fee',
                    'COUNT(*) * AVG(c.charge_fee) as total_charge_fee'
                ])
                ->find();

            // 提现统计 - 关联提现渠道表，只统计真实用户
            $withdrawStats = Db::table('game_transactions')
                ->alias('t')
                ->leftJoin('game_withdraw_channel c', 't.channel_id = c.id')
                ->leftJoin('game_users u', 't.user_id = u.id')
                ->where('t.type', 'withdraw')
                ->where('c.type', $channelType)
                ->where('u.type', 'user') // 只统计真实用户
                ->where('t.created_at', 'between', [$startDate, $endDate])
                ->where('t.deleted_at', null)
                ->where('u.deleted_at', null)
                ->field([
                    'COUNT(*) as count',
                    'COUNT(DISTINCT t.user_id) as user_count',
                    'SUM(t.amount) as total_amount',
                    'SUM(t.fee) as total_fee'
                ])
                ->find();

            // 计算各项数据
            $depositCount = intval($depositStats['count'] ?? 0);
            $depositUserCount = intval($depositStats['user_count'] ?? 0);
            $depositAmount = floatval($depositStats['total_amount'] ?? 0);
            $giftAmount = floatval($depositStats['total_gift'] ?? 0);
            $withdrawCount = intval($withdrawStats['count'] ?? 0);
            $withdrawUserCount = intval($withdrawStats['user_count'] ?? 0);
            $withdrawAmount = floatval($withdrawStats['total_amount'] ?? 0);
            $withdrawFee = floatval($withdrawStats['total_fee'] ?? 0);

            // 计算通道费用（费率）：根据实际交易计算出的费用
            $channelFeeRate = floatval($depositStats['total_rate_fee'] ?? 0);
            // 计算网关费用（单笔手续费）：根据实际交易计算出的费用
            $gatewayFee = floatval($depositStats['total_charge_fee'] ?? 0);
            // 总渠道费用
            $totalChannelFee = $channelFeeRate + $gatewayFee;

            // 计算利润：充值金额 - 提现金额 + 提现手续费 - 渠道费用
            $profit = $depositAmount - $withdrawAmount + $withdrawFee - $totalChannelFee;

            $stats = [
                'channel_name' => strtoupper($channelType),
                'channel_type' => $channelType,
                'register_count' => '--', // 渠道行不显示注册人数
                'deposit_count' => $depositCount,
                'deposit_user_count' => $depositUserCount,
                'deposit_amount' => number_format($depositAmount, 2),
                'gift_amount' => number_format($giftAmount, 2),
                'withdraw_count' => $withdrawCount,
                'withdraw_user_count' => $withdrawUserCount,
                'withdraw_amount' => number_format($withdrawAmount, 2),
                'channel_fee_rate' => number_format($channelFeeRate, 2),
                'gateway_fee' => number_format($gatewayFee, 2),
                'total_channel_fee' => number_format($totalChannelFee, 2),
                'withdraw_fee' => number_format($withdrawFee, 2),
                'user_balances' => '--', // 渠道行不显示未下分
                'profit' => number_format($profit, 2),
                'profit_color' => $profit >= 0 ? 'green' : 'red'
            ];

            $financeStats[] = $stats;

            // 累计总计
            $totalStats['deposit_count'] += $depositCount;
            $totalStats['deposit_user_count'] += $depositUserCount;
            $totalStats['deposit_amount'] += $depositAmount;
            $totalStats['gift_amount'] += $giftAmount;
            $totalStats['withdraw_count'] += $withdrawCount;
            $totalStats['withdraw_user_count'] += $withdrawUserCount;
            $totalStats['withdraw_amount'] += $withdrawAmount;
            $totalStats['channel_fee_rate'] += $channelFeeRate;
            $totalStats['gateway_fee'] += $gatewayFee;
            $totalStats['total_channel_fee'] += $totalChannelFee;
            $totalStats['withdraw_fee'] += $withdrawFee;
            $totalStats['profit'] += $profit;
        }

        // 计算总的未下分金额：充值+充值赠送+赠送+赢钱-提现-下注（只统计真实用户）
        $totalUserBalances = self::calculateTotalUserBalances($startDate, $endDate);

        // 格式化总计数据
        $totalFormatted = [
            'channel_name' => '合计',
            'channel_type' => '-',
            'rate' => '-',
            'charge_fee' => '-',
            'register_count' => $totalRegisterCount,
            'deposit_count' => $totalStats['deposit_count'],
            'deposit_user_count' => $totalStats['deposit_user_count'],
            'deposit_amount' => number_format($totalStats['deposit_amount'], 2),
            'gift_amount' => number_format($totalStats['gift_amount'], 2),
            'withdraw_count' => $totalStats['withdraw_count'],
            'withdraw_user_count' => $totalStats['withdraw_user_count'],
            'withdraw_amount' => number_format($totalStats['withdraw_amount'], 2),
            'channel_fee_rate' => number_format($totalStats['channel_fee_rate'], 2),
            'gateway_fee' => number_format($totalStats['gateway_fee'], 2),
            'total_channel_fee' => number_format($totalStats['total_channel_fee'], 2),
            'withdraw_fee' => number_format($totalStats['withdraw_fee'], 2),
            'user_balances' => number_format($totalUserBalances, 2),
            'profit' => number_format($totalStats['profit'], 2),
            'profit_color' => $totalStats['profit'] >= 0 ? 'green' : 'red'
        ];

        return [
            'list' => $financeStats,
            'total' => $totalFormatted
        ];
    }

    /**
     * 计算总的未下分金额：充值+充值赠送+赠送+赢钱-提现-下注（只统计真实用户）
     */
    private static function calculateTotalUserBalances($startDate, $endDate)
    {
        // 计算充值金额（只统计真实用户）
        $depositAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'deposit')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 计算充值赠送金额（只统计真实用户）
        $depositGiftAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'deposit_gift')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 计算赠送金额（只统计真实用户）
        $giftAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'gift')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 计算赢钱金额（游戏收益）（只统计真实用户）
        $winAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'game_win')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 计算投注金额（负数）（只统计真实用户）
        $betAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'game_bet')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 计算提现金额（负数）（只统计真实用户）
        $withdrawAmount = Db::table('game_user_balances')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.id')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.type', 'withdraw')
            ->where('u.type', 'user') // 只统计真实用户
            ->where('u.deleted_at', null)
            ->sum('b.amount');

        // 未下分 = 充值 + 充值赠送 + 赠送 + 赢钱 - 提现 - 下注
        // 注意：投注和提现在余额表中通常是负数，所以这里用加法（因为已经是负数）
        $unallocated = floatval($depositAmount ?? 0) + floatval($depositGiftAmount ?? 0) + floatval($giftAmount ?? 0) + floatval($winAmount ?? 0) + floatval($betAmount ?? 0) + floatval($withdrawAmount ?? 0);

        return $unallocated;
    }
}
