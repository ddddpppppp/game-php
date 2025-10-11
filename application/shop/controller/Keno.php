<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\TimeHelper;
use app\common\model\KenoBets;
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
use app\common\model\KenoBetTypes;
use app\shop\service\Keno as KenoService;

class Keno extends Controller
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
            $list = KenoBetTypes::getBetTypesByMerchantId($merchantId);
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

            $result = KenoBetTypes::updateBetType($id, $data);
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

            $result = KenoBetTypes::batchUpdateStatus($ids, $status);
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
            $query = \app\common\model\KenoDraws::where($where)
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
            $betStats = \think\Db::table('game_keno_bets')
                ->alias('b')
                ->leftJoin('game_users u', 'b.user_id = u.uuid')
                ->field([
                    'b.period_number',
                    'u.type as user_type',
                    'COUNT(DISTINCT b.user_id) as user_count',
                    'COUNT(b.id) as bet_count',
                    'SUM(b.amount) as total_bet_amount',
                    'SUM(CASE WHEN b.status = "win" THEN b.win_amount - b.amount ELSE 0 END) as user_profit',
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
                    'status_text' => \app\common\model\KenoDraws::getStatusCnText($draw['status']),
                    'result_numbers' => $draw['result_numbers'], // 20个号码 (1-80)
                    'start_at' => TimeHelper::convertFromUTC($draw['start_at']),
                    'end_at' => TimeHelper::convertFromUTC($draw['end_at']),
                    'draw_at' => TimeHelper::convertFromUTC($draw['draw_at']),
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
        $matchCount = $this->params['match_count'] ?? '';
        $status = $this->params['status'] ?? '';
        $userType = $this->params['user_type'] ?? '';
        $startDate = $this->params['start_date'] ?? '';

        // 构建查询条件
        $where = [];
        if (!empty($periodNumber)) {
            $where[] = ['b.period_number', 'like', '%' . $periodNumber . '%'];
        }
        if ($matchCount !== '') {
            $where[] = ['b.match_count', '=', $matchCount];
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
        $query = \think\Db::table('game_keno_bets')
            ->alias('b')
            ->leftJoin('game_users u', 'b.user_id = u.uuid')
            ->leftJoin('game_keno_draws d', 'b.period_number = d.period_number')
            ->field([
                'b.*',
                'u.username',
                'u.nickname',
                'u.type as user_type',
                'd.result_numbers as draw_result_numbers',
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
            // 解析JSON字段
            $selectedNumbers = json_decode($bet['selected_numbers'], true) ?: [];
            $drawnNumbers = json_decode($bet['drawn_numbers'], true) ?: [];
            $matchedNumbers = json_decode($bet['matched_numbers'], true) ?: [];

            // 计算实际盈亏
            $actualProfit = 0;
            if ($bet['status'] === 'win') {
                $actualProfit = $bet['win_amount'] - $bet['amount'];
            } elseif ($bet['status'] === 'lose') {
                $actualProfit = -$bet['amount'];
            }

            // 计算潜在赢利
            $potentialWin = $bet['status'] === 'pending' ? $bet['amount'] * 100000 : $bet['win_amount'];

            $list[] = [
                'id' => $bet['id'],
                'period_number' => $bet['period_number'],
                'username' => $bet['username'],
                'nickname' => $bet['nickname'],
                'user_type' => $bet['user_type'],
                'user_type_text' => $bet['user_type'] === 'bot' ? '机器人' : '真实用户',
                'selected_numbers' => $selectedNumbers, // 玩家选择的10个号码
                'drawn_numbers' => $drawnNumbers, // 开出的20个号码
                'matched_numbers' => $matchedNumbers, // 匹配的号码
                'match_count' => intval($bet['match_count']), // 匹配数量
                'amount' => number_format($bet['amount'], 2),
                'multiplier' => floatval($bet['multiplier']),
                'win_amount' => number_format($bet['win_amount'], 2),
                'potential_win' => number_format($potentialWin, 2),
                'status' => $bet['status'],
                'status_text' => KenoBets::getStatusCnText($bet['status']),
                'actual_profit' => number_format($actualProfit, 2),
                'draw_status' => $bet['draw_status'],
                'ip' => $bet['ip'],
                'created_at' => TimeHelper::convertFromUTC($bet['created_at']),
                'settled_at' => $bet['settled_at'] ? TimeHelper::convertFromUTC($bet['settled_at']) : null,
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
