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
use app\shop\service\Canada as CanadaService;

class Finance extends Controller
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
     * 获取仪表盘统计概览数据
     */
    public function getDashboardStats()
    {
        try {
            // 获取搜索参数
            $startDate = $this->params['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
            $endDate = $this->params['endDate'] ?? date('Y-m-d');

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
            $currentStats = CanadaService::getStatsForPeriod($startDateUTC, $endDateUTC);

            // 2. 获取上一周期统计数据（用于计算趋势）
            $prevStats = CanadaService::getStatsForPeriod($prevStartDateUTC, $prevEndDateUTC);

            // 3. 计算趋势
            $trends = CanadaService::calculateTrends($currentStats, $prevStats);
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        return $this->success([
            'depositAmount' => $currentStats['depositAmount'],
            'depositAmountTrend' => $trends['depositAmount'],
            'withdrawAmount' => $currentStats['withdrawAmount'],
            'withdrawAmountTrend' => $trends['withdrawAmount'],
            'depositChannelFee' => $currentStats['depositChannelFee'],
            'depositChannelFeeTrend' => $trends['depositChannelFee'],
            'withdrawFee' => $currentStats['withdrawFee'],
            'withdrawFeeTrend' => $trends['withdrawFee'],
            'grossProfit' => $currentStats['grossProfit'],
            'grossProfitTrend' => $trends['grossProfit'],
            'realProfit' => $currentStats['realProfit'],
            'realProfitTrend' => $trends['realProfit'],
        ]);
    }

    /**
     * 获取图表数据
     */
    public function getChartData()
    {
        try {
            // 获取搜索参数
            $startDate = $this->params['startDate'] ?? date('Y-m-d', strtotime('-6 days'));
            $endDate = $this->params['endDate'] ?? date('Y-m-d');
            $period = $this->params['period'] ?? 'day';

            // 将本地时间转换为UTC时间用于数据库查询
            $startDateUTC = TimeHelper::convertToUTC($startDate . ' 00:00:00');
            $endDateUTC = TimeHelper::convertToUTC($endDate . ' 23:59:59');

            // 获取图表数据
            $chartData = CanadaService::getSimpleChartData($startDateUTC, $endDateUTC, $period);
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        return $this->success($chartData);
    }

    /**
     * 获取财务统计数据
     */
    public function getFinanceStats()
    {
        try {
            // 获取搜索参数
            $startDate = $this->params['start_date'] ?? date('Y-m-d', strtotime('-6 days'));
            $endDate = $this->params['end_date'] ?? date('Y-m-d');

            // 将本地时间转换为UTC时间用于数据库查询
            $startDateUTC = TimeHelper::convertToUTC($startDate . ' 00:00:00');
            $endDateUTC = TimeHelper::convertToUTC($endDate . ' 23:59:59');

            // 获取按渠道分组的财务统计数据
            $financeData = CanadaService::getFinanceStatsByChannel($startDateUTC, $endDateUTC);
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        return $this->success($financeData);
    }
}
