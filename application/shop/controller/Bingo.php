<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\TimeHelper;
use app\common\model\Bingo28Bets;
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
use app\common\model\Bingo28BetTypes;
use app\shop\service\Bingo as BingoService;

class Bingo extends Controller
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
            $list = Bingo28BetTypes::getBetTypesByMerchantId($merchantId);
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

            $result = Bingo28BetTypes::updateBetType($id, $data);
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

            $result = Bingo28BetTypes::batchUpdateStatus($ids, $status);
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
            $query = \app\common\model\Bingo28Draws::where($where)
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
            $betStats = \think\Db::table('game_bingo28_bets')
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
                    $statsMap[$periodNumber]['total_user_profit'] += floatval($stat['user_profit']);
                }
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
                $platformProfit = $stats['user_bet_amount'] - $stats['total_user_profit'];

                $list[] = [
                    'id' => $draw['id'],
                    'period_number' => $draw['period_number'],
                    'status' => $draw['status'],
                    'status_text' => \app\common\model\Bingo28Draws::getStatusCnText($draw['status']),
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
        $query = \think\Db::table('game_bingo28_bets')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.uuid')
            ->leftJoin('game_bingo28_draws d', 'b.period_number = d.period_number')
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
            ->order('b.id desc');

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
                'status_text' => Bingo28Bets::getStatusCnText($bet['status']),
                'actual_profit' => number_format($actualProfit, 2),
                'result_numbers' => json_decode($bet['result_numbers'], true),
                'result_sum' => $bet['result_sum'],
                'draw_status' => $bet['draw_status'],
                'ip' => $bet['ip'],
                'created_at' => TimeHelper::convertFromUTC($bet['created_at']),
            ];
        }
        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ]);
    }
}
