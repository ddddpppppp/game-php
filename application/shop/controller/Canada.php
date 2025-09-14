<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\TimeHelper;
use app\common\model\Canada28Bets;
use app\common\service\AdminBalance;
use app\common\service\Bot;
use app\common\utils\ParamsAesHelper;
use app\shop\enum\Menu;
use app\shop\model\Petter;
use app\shop\service\Admin;
use app\shop\service\AdminOperationLog;
use app\shop\service\Merchant;
use app\shop\service\MiningService;
use app\shop\service\Role;
use app\shop\service\Takeout;
use think\Env;
use think\facade\Cache;
use think\facade\Log;
use app\common\model\MiningProducts;
use app\common\model\MiningProductDailyApy;
use app\common\model\Transactions;
use app\common\model\Canada28BetTypes;

class Canada extends Controller
{
    protected $params = [];
    /** @var \app\shop\model\Admin|null $admin */
    protected $admin = [];
    /** @var \app\shop\model\Role|null $role */
    protected $role = null;
    protected $form = [];

    /**
     * 后台初始化
     */
    public function initialize()
    {
        $this->params = request()->param();
        $this->form = $this->params['form'] ?: [];
        $this->admin = Admin::getAdmin();
        if (empty($this->admin)) {
            return $this->error(Common::NEED_LOGIN_MSG);
        }
        $this->role = Role::getRole($this->admin->role_id);
    }

    /**
     * 获取玩法列表
     */
    public function getBetTypesList()
    {
        try {
            $merchantId = $this->admin->merchant_id ?: 'default';
            $list = Canada28BetTypes::getBetTypesByMerchantId($merchantId);
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        return $this->success([
            'list' => $list,
            'total' => count($list)
        ]);
    }

    /**
     * 更新玩法配置
     */
    public function updateBetType()
    {
        try {
            $id = $this->form['id'] ?? 0;
            $odds = $this->form['odds'] ?? 0;
            $status = $this->form['status'] ?? 1;

            if (!$id) {
                return $this->error('参数错误');
            }

            if ($odds <= 0) {
                return $this->error('赔率必须大于0');
            }

            $data = [
                'odds' => $odds,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $result = Canada28BetTypes::updateBetType($id, $data);
        } catch (\Exception $e) {
            return $this->error('更新失败：' . $e->getMessage());
        }
        if ($result) {
            return $this->success();
        } else {
            return $this->error('更新失败');
        }
    }

    /**
     * 批量更新状态
     */
    public function batchUpdateStatus()
    {
        try {
            $ids = $this->form['ids'] ?? [];
            $status = $this->form['status'] ?? 1;

            if (empty($ids)) {
                return $this->error('请选择要操作的记录');
            }

            $result = Canada28BetTypes::batchUpdateStatus($ids, $status);
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }
        if ($result) {
            // $statusText = $status == 1 ? '启用' : '禁用';
            return $this->success();
        } else {
            return $this->error('操作失败');
        }
    }

    /**
     * 获取开奖记录列表
     */
    public function getDrawRecordsList()
    {
        try {
            $page = intval($this->params['page'] ?? 1);
            $size = intval($this->params['size'] ?? 20);
            $offset = ($page - 1) * $size;

            // 搜索条件
            $periodNumber = $this->params['period_number'] ?? '';
            $status = $this->params['status'] ?? '';
            $startDate = $this->params['start_date'] ?? '';

            // 构建查询条件
            $where = [];
            if (!empty($periodNumber)) {
                $where[] = ['period_number', 'like', '%' . $periodNumber . '%'];
            }
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }
            if (!empty($startDate)) {
                $where[] = ['draw_at', '>=', TimeHelper::convertToUTC($startDate[0])];
                $where[] = ['draw_at', '<=', TimeHelper::convertToUTC($startDate[1])];
            }
            // 获取开奖记录
            $query = \app\common\model\Canada28Draws::where($where)
                ->order('period_number desc');

            $total = $query->count();
            $draws = $query->limit($offset, $size)->select();

            if (empty($draws)) {
                return $this->success([
                    'list' => [],
                    'total' => $total,
                    'page' => $page,
                    'size' => $size
                ]);
            }

            // 提取期号用于批量查询投注统计
            $periodNumbers = array_column($draws->toArray(), 'period_number');

            // 批量获取投注统计数据 - 按期号和用户类型分组
            $betStats = \think\Db::table('game_canada28_bets')
                ->alias('b')
                ->leftJoin('game_users u', 'b.user_id = u.uuid')
                ->field([
                    'b.period_number',
                    'u.type as user_type',
                    'COUNT(DISTINCT b.user_id) as user_count',
                    'COUNT(b.id) as bet_count',
                    'SUM(b.amount) as total_bet_amount',
                    'SUM(CASE WHEN b.status = "win" THEN b.amount * b.multiplier - b.amount ELSE 0 END) as user_profit',
                    'SUM(CASE WHEN b.status = "lose" THEN b.amount ELSE 0 END) as platform_profit'
                ])
                ->where('b.period_number', 'in', $periodNumbers)
                ->where('b.deleted_at', null)
                ->group('b.period_number, u.type')
                ->select();

            // 组织统计数据 - 按期号分组
            $statsMap = [];
            foreach ($betStats as $stat) {
                $periodNumber = $stat['period_number'];
                $userType = $stat['user_type'] ?: 'user'; // 默认为user类型

                if (!isset($statsMap[$periodNumber])) {
                    $statsMap[$periodNumber] = [
                        'user_count' => 0,
                        'bot_count' => 0,
                        'user_bet_count' => 0,
                        'bot_bet_count' => 0,
                        'user_bet_amount' => 0,
                        'bot_bet_amount' => 0,
                        'total_user_profit' => 0,
                    ];
                }

                if ($userType === 'bot') {
                    $statsMap[$periodNumber]['bot_count'] = intval($stat['user_count']);
                    $statsMap[$periodNumber]['bot_bet_count'] = intval($stat['bet_count']);
                    $statsMap[$periodNumber]['bot_bet_amount'] = floatval($stat['total_bet_amount']);
                } else {
                    $statsMap[$periodNumber]['user_count'] = intval($stat['user_count']);
                    $statsMap[$periodNumber]['user_bet_count'] = intval($stat['bet_count']);
                    $statsMap[$periodNumber]['user_bet_amount'] = floatval($stat['total_bet_amount']);
                }

                $statsMap[$periodNumber]['total_user_profit'] += floatval($stat['user_profit']);
            }

            // 构建返回数据
            $list = [];
            foreach ($draws as $draw) {
                $periodNumber = $draw['period_number'];
                $stats = $statsMap[$periodNumber] ?? [
                    'user_count' => 0,
                    'bot_count' => 0,
                    'user_bet_count' => 0,
                    'bot_bet_count' => 0,
                    'user_bet_amount' => 0,
                    'bot_bet_amount' => 0,
                    'total_user_profit' => 0,
                ];

                // 平台盈利 = 用户投注金额 - 用户盈利
                $totalBetAmount = $stats['user_bet_amount'] + $stats['bot_bet_amount'];
                $platformProfit = $totalBetAmount - $stats['total_user_profit'];

                $list[] = [
                    'id' => $draw['id'],
                    'period_number' => $draw['period_number'],
                    'status' => $draw['status'],
                    'status_text' => \app\common\model\Canada28Draws::getStatusCnText($draw['status']),
                    'result_numbers' => $draw['result_numbers'],
                    'result_sum' => $draw['result_sum'],
                    'start_at' => TimeHelper::convertFromUTC($draw['start_at']),
                    'end_at' => TimeHelper::convertFromUTC($draw['end_at']),
                    // 统计数据
                    'user_count' => $stats['user_count'],
                    'bot_count' => $stats['bot_count'],
                    'user_bet_count' => $stats['user_bet_count'],
                    'bot_bet_count' => $stats['bot_bet_count'],
                    'user_bet_amount' => number_format($stats['user_bet_amount'], 2),
                    'bot_bet_amount' => number_format($stats['bot_bet_amount'], 2),
                    'total_user_profit' => number_format($stats['total_user_profit'], 2),
                    'platform_profit' => number_format($platformProfit, 2),
                ];
            }
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ]);
    }

    /**
     * 获取投注记录列表
     */
    public function getBetRecordsList()
    {
        try {
            $page = intval($this->params['page'] ?? 1);
            $size = intval($this->params['size'] ?? 20);
            $offset = ($page - 1) * $size;

            // 搜索条件
            $periodNumber = $this->params['period_number'] ?? '';
            $username = $this->params['username'] ?? '';
            $betType = $this->params['bet_type'] ?? '';
            $status = $this->params['status'] ?? '';
            $userType = $this->params['user_type'] ?? '';
            $startDate = $this->params['start_date'] ?? '';

            // 构建查询条件
            $where = [];
            if (!empty($periodNumber)) {
                $where[] = ['b.period_number', 'like', '%' . $periodNumber . '%'];
            }
            if (!empty($betType)) {
                $where[] = ['b.bet_type', '=', $betType];
            }
            if ($status !== '') {
                $where[] = ['b.status', '=', $status];
            }
            if (!empty($userType)) {
                $where[] = ['u.type', '=', $userType];
            }
            if (!empty($username)) {
                $where[] = ['u.username', 'like', '%' . $username . '%'];
            }
            if (!empty($startDate)) {
                $where[] = ['b.created_at', '>=', TimeHelper::convertToUTC($startDate[0])];
                $where[] = ['b.created_at', '<=', TimeHelper::convertToUTC($startDate[1])];
            }

            // 获取投注记录 - 使用JOIN避免循环查询
            $query = \think\Db::table('game_canada28_bets')
                ->alias('b')
                ->leftJoin('game_users u', 'b.user_id = u.uuid')
                ->leftJoin('game_canada28_draws d', 'b.period_number = d.period_number')
                ->field([
                    'b.*',
                    'u.username',
                    'u.nickname',
                    'u.type as user_type',
                    'd.result_numbers',
                    'd.result_sum',
                    'd.status as draw_status'
                ])
                ->where($where)
                ->where('b.deleted_at', null)
                ->order('b.created_at desc');

            $total = $query->count();
            $bets = $query->limit($offset, $size)->select();

            if (empty($bets)) {
                return $this->success([
                    'list' => [],
                    'total' => $total,
                    'page' => $page,
                    'size' => $size
                ]);
            }

            // 构建返回数据
            $list = [];
            foreach ($bets as $bet) {
                // 计算实际盈亏
                $actualProfit = 0;
                if ($bet['status'] === 'win') {
                    $actualProfit = ($bet['amount'] * $bet['multiplier']) - $bet['amount'];
                } elseif ($bet['status'] === 'lose') {
                    $actualProfit = -$bet['amount'];
                }

                // 计算潜在赢利
                $potentialWin = $bet['amount'] * $bet['multiplier'];

                $list[] = [
                    'id' => $bet['id'],
                    'period_number' => $bet['period_number'],
                    'username' => $bet['username'],
                    'nickname' => $bet['nickname'],
                    'user_type' => $bet['user_type'],
                    'user_type_text' => $bet['user_type'] === 'bot' ? '机器人' : '真实用户',
                    'bet_type' => $bet['bet_type'],
                    'bet_name' => $bet['bet_name'],
                    'amount' => number_format($bet['amount'], 2),
                    'multiplier' => $bet['multiplier'],
                    'potential_win' => number_format($potentialWin, 2),
                    'status' => $bet['status'],
                    'status_text' => Canada28Bets::getStatusCnText($bet['status']),
                    'actual_profit' => number_format($actualProfit, 2),
                    'result_numbers' => json_decode($bet['result_numbers'], true),
                    'result_sum' => $bet['result_sum'],
                    'draw_status' => $bet['draw_status'],
                    'ip' => $bet['ip'],
                    'created_at' => TimeHelper::convertFromUTC($bet['created_at']),
                ];
            }
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ]);
    }


    /**
     * 获取仪表盘统计数据
     */
    public function getDashboardStats()
    {
        try {
            // 获取搜索参数
            $startDate = $this->params['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
            $endDate = $this->params['endDate'] ?? date('Y-m-d');
            $period = $this->params['period'] ?? 'day';

            // 将本地时间转换为UTC时间用于数据库查询
            $startDateUTC = TimeHelper::convertToUTC($startDate . ' 00:00:00');
            $endDateUTC = TimeHelper::convertToUTC($endDate . ' 23:59:59');

            // 计算上一个周期的日期范围（用于对比）
            $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
            $prevStartDate = date('Y-m-d', strtotime($startDate . ' -' . ($daysDiff + 1) . ' days'));
            $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
            $prevStartDateUTC = TimeHelper::convertToUTC($prevStartDate . ' 00:00:00');
            $prevEndDateUTC = TimeHelper::convertToUTC($prevEndDate . ' 23:59:59');

            // 1. 获取当前周期统计数据
            $currentStats = $this->getStatsForPeriod($startDateUTC, $endDateUTC);

            // 2. 获取上一周期统计数据（用于计算趋势）
            $prevStats = $this->getStatsForPeriod($prevStartDateUTC, $prevEndDateUTC);

            // 3. 计算趋势
            $trends = $this->calculateTrends($currentStats, $prevStats);

            // 4. 获取图表数据
            $chartData = $this->getChartData($startDateUTC, $endDateUTC, $period);
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        return $this->success([
            'personal' => [
                'depositAmount' => $currentStats['depositAmount'],
                'depositAmountTrend' => $trends['depositAmount'],
                'withdrawAmount' => $currentStats['withdrawAmount'],
                'withdrawAmountTrend' => $trends['withdrawAmount'],
                'betAmount' => $currentStats['betAmount'],
                'betAmountTrend' => $trends['betAmount'],
                'platformProfit' => $currentStats['platformProfit'],
                'platformProfitTrend' => $trends['platformProfit'],
            ],
            'chart' => $chartData
        ]);
    }

    /**
     * 获取指定时间段的统计数据
     */
    private function getStatsForPeriod($startDate, $endDate)
    {
        // 充值统计
        $depositStats = \think\Db::table('game_transactions')
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->field([
                'SUM(amount) as total_amount',
                'COUNT(*) as total_count'
            ])
            ->find();

        // 提现统计
        $withdrawStats = \think\Db::table('game_transactions')
            ->where('type', 'withdraw')
            ->where('status', 'completed')
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->field([
                'SUM(amount) as total_amount',
                'COUNT(*) as total_count'
            ])
            ->find();

        // 投注统计
        $betStats = \think\Db::table('game_canada28_bets')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.uuid')
            ->where('b.created_at', 'between', [$startDate, $endDate])
            ->where('b.deleted_at', null)
            ->field([
                'SUM(b.amount) as total_bet_amount',
                'COUNT(b.id) as total_bet_count',
                'SUM(CASE WHEN b.status = "win" THEN b.amount * b.multiplier - b.amount ELSE 0 END) as total_user_profit',
                'COUNT(DISTINCT b.user_id) as unique_users'
            ])
            ->find();

        // 计算平台盈利 = 投注金额 - 用户盈利
        $platformProfit = ($betStats['total_bet_amount'] ?? 0) - ($betStats['total_user_profit'] ?? 0);

        return [
            'depositAmount' => floatval($depositStats['total_amount'] ?? 0),
            'depositCount' => intval($depositStats['total_count'] ?? 0),
            'withdrawAmount' => floatval($withdrawStats['total_amount'] ?? 0),
            'withdrawCount' => intval($withdrawStats['total_count'] ?? 0),
            'betAmount' => floatval($betStats['total_bet_amount'] ?? 0),
            'betCount' => intval($betStats['total_bet_count'] ?? 0),
            'platformProfit' => $platformProfit,
            'uniqueUsers' => intval($betStats['unique_users'] ?? 0),
        ];
    }

    /**
     * 计算趋势百分比
     */
    private function calculateTrends($current, $prev)
    {
        $trends = [];
        $fields = ['depositAmount', 'withdrawAmount', 'betAmount', 'platformProfit'];

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
     * 获取图表数据
     */
    private function getChartData($startDate, $endDate, $period)
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
        $depositData = \think\Db::table('game_transactions')
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
        $withdrawData = \think\Db::table('game_transactions')
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

        // 投注数据
        $betData = \think\Db::table('game_canada28_bets')
            ->field([
                $dateField . ' as date_group',
                'SUM(amount) as bet_amount',
                'SUM(CASE WHEN status = "win" THEN amount * multiplier - amount ELSE 0 END) as user_profit'
            ])
            ->where('created_at', 'between', [$startDate, $endDate])
            ->where('deleted_at', null)
            ->group('date_group')
            ->order('date_group ASC')
            ->select();

        // 生成完整的日期点
        $datePoints = $this->generateDatePoints($startDate, $endDate, $period);

        // 填充数据
        $labels = [];
        $depositAmountData = [];
        $withdrawAmountData = [];
        $betAmountData = [];
        $platformProfitData = [];

        // 将数据转换为以日期为key的数组便于查找
        $depositMap = [];
        foreach ($depositData as $item) {
            $depositMap[$item['date_group']] = $item['amount'];
        }

        $withdrawMap = [];
        foreach ($withdrawData as $item) {
            $withdrawMap[$item['date_group']] = $item['amount'];
        }

        $betMap = [];
        foreach ($betData as $item) {
            $betMap[$item['date_group']] = [
                'bet_amount' => $item['bet_amount'],
                'user_profit' => $item['user_profit']
            ];
        }

        foreach ($datePoints as $dateKey => $label) {
            $labels[] = $label;
            $depositAmountData[] = floatval($depositMap[$dateKey] ?? 0);
            $withdrawAmountData[] = floatval($withdrawMap[$dateKey] ?? 0);

            $betInfo = $betMap[$dateKey] ?? ['bet_amount' => 0, 'user_profit' => 0];
            $betAmountData[] = floatval($betInfo['bet_amount']);
            $platformProfitData[] = floatval($betInfo['bet_amount']) - floatval($betInfo['user_profit']);
        }

        return [
            'labels' => $labels,
            'depositData' => $depositAmountData,
            'withdrawData' => $withdrawAmountData,
            'betData' => $betAmountData,
            'profitData' => $platformProfitData,
        ];
    }

    /**
     * 生成日期点
     */
    private function generateDatePoints($startDate, $endDate, $period)
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
}
