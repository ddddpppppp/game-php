<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\service\Setting;
use app\common\service\ShopUser;
use app\shop\enum\Menu;
use app\shop\enum\Role as EnumRole;
use app\shop\model\Merchant as ModelMerchant;
use app\shop\service\Admin as ServiceAdmin;
use app\shop\service\Merchant;
use app\shop\service\Role;
use think\Db;
use think\facade\Cache;

class Admin extends Controller
{

    protected $params = [];
    /** @var \app\shop\model\Admin|null $admin */
    protected $admin = null;
    /** @var \app\shop\model\Role|null $role */
    protected $role = null;
    protected $form = [];
    /**
     * 后台初始化
     */
    public function initialize()
    {
        $this->params = request()->param();
        $this->form = isset($this->params['form']) ? $this->params['form'] : [];
        $this->admin = ServiceAdmin::getAdmin();
        if (empty($this->admin)) {
            return $this->renderJson(-2, [], Common::NEED_LOGIN_MSG);
        }
        $this->role = Role::getRole($this->admin->role_id);
    }

    public function getUserInfo()
    {
        $merchant = Merchant::getMerchantInfo($this->admin->merchant_id);
        if ($merchant->status != 1) {
            return $this->error('该服务商已被冻结');
        }
        $role = Role::getRole($this->admin['role_id']);
        $permissions = $role->access;
        return $this->success(['permissions' => $permissions]);
    }

    public function saveInfo()
    {
        $update = [];
        $update['avatar'] = trim($this->params['avatar']);
        $update['nickname'] = trim($this->params['nickname']);
        ServiceAdmin::saveInfo($update, ['id' => $this->admin->id]);
        return $this->success();
    }

    public function passwordEdit()
    {
        $password = trim($this->params['password']);
        $newPassword = trim($this->params['newPassword']);
        if (empty($password) || empty($newPassword)) {
            return $this->error(Common::PARAMS_EMPTY_MSG);
        }
        $token = create_password($this->admin->salt, $password);
        if ($token != $this->admin->password) {
            return $this->error('旧密码不对');
        }
        $update['password'] = create_password($this->admin->salt, $newPassword);
        ServiceAdmin::saveInfo($update, ['id' => $this->admin->id]);
        return $this->success();
    }

    public function getAllRole()
    {
        if (!in_array($this->role->type, [1, 2, 3])) {
            return $this->error('您没有权限');
        }
        $list = Role::getAllRole($this->role->type, $this->admin->merchant_id);
        if (empty($list)) {
            return $this->error('请先添加角色');
        }
        $takeout = false;
        /** @var array $access **/
        $access = $this->role->access;
        foreach ($access as $item) {
            if (in_array($item, Menu::ACCESS_TAKEOUT)) {
                $takeout = true;
                break;
            }
        }
        return $this->success(['list' => $list, 'takeout' => $takeout]);
    }

    public function getAllAdmin()
    {
        $list = \app\shop\model\Admin::where(['uuid' => ServiceAdmin::getMyEmployee($this->role->type, $this->admin->merchant_id, $this->admin->uuid)])->field('uuid,nickname')->select()->toArray();
        if (empty($list)) {
            return $this->error('您没有下属员工');
        }
        return $this->success(['list' => $list]);
    }

    public function getAllMerchant()
    {
        $cond = [];
        if ($this->role->type != 1) {
            $cond['uuid'] = $this->admin->merchant_id;
        }
        $list = ModelMerchant::field('uuid,name')->where($cond)->select()->toArray();
        foreach ($list as &$item) {
            $item['label'] = $item['name'];
            $item['value'] = $item['uuid'];
        }
        return $this->success(['list' => $list]);
    }

    /**
     * 获取仪表盘数据
     * 
     * @return \think\response\Json
     */
    public function getDashboard()
    {
        // 获取请求参数
        $params = $this->request->param();
        $startDateLocal = isset($params['startDate']) ? $params['startDate'] : date('Y-m-d', strtotime('-6 days'));
        $endDateLocal = isset($params['endDate']) ? $params['endDate'] : date('Y-m-d');
        $period = isset($params['period']) ? $params['period'] : 'month';


        // 将本地时间转换为UTC时间用于数据库查询
        $startDateUTC = \app\common\helper\TimeHelper::convertToUTC($startDateLocal . ' 00:00:00');
        $endDateUTC = \app\common\helper\TimeHelper::convertToUTC($endDateLocal . ' 23:59:59');

        // 获取商户ID
        if ($this->role->type == 1) {
            if (isset($params['merchantId'])) {
                $merchantId = $params['merchantId'];
            } else {
                $merchantId = ModelMerchant::field('uuid')->column('uuid');
            }
        } else {
            $merchantId = $this->admin->merchant_id;
        }

        // 获取当前周期的订单数据
        $currentData = $this->getOrderStats($merchantId, $startDateUTC, $endDateUTC, $period);

        // 计算上一个周期的日期范围（本地时间）
        $daysDiff = (strtotime($endDateLocal) - strtotime($startDateLocal)) / 86400;
        $prevStartDateLocal = date('Y-m-d', strtotime($startDateLocal . ' -' . ($daysDiff + 1) . ' days'));
        $prevEndDateLocal = date('Y-m-d', strtotime($startDateLocal . ' -1 day'));

        // 将上一周期的本地时间转换为UTC时间
        $prevStartDateUTC = \app\common\helper\TimeHelper::convertToUTC($prevStartDateLocal . ' 00:00:00');
        $prevEndDateUTC = \app\common\helper\TimeHelper::convertToUTC($prevEndDateLocal . ' 23:59:59');

        // 获取上一个周期的订单数据（用于计算趋势）
        $previousData = $this->getOrderStats($merchantId, $prevStartDateUTC, $prevEndDateUTC, $period);

        // 计算趋势
        $orderTrend = $this->calculateTrend($currentData['orderCount'], $previousData['orderCount']);
        $paidOrderTrend = $this->calculateTrend($currentData['paidOrderCount'], $previousData['paidOrderCount']);
        $paidAmountTrend = $this->calculateTrend($currentData['paidAmount'], $previousData['paidAmount']);
        $conversionRateTrend = $this->calculateTrend($currentData['conversionRate'], $previousData['conversionRate']);

        // 获取图表数据 - 注意这里只传递merchantId和period参数
        $chartData = $this->getChartData($merchantId, $period);

        // 构建返回数据
        $data = [
            'personal' => [
                'orderCount' => $currentData['orderCount'],
                'orderTrend' => $orderTrend,
                'paidOrderCount' => $currentData['paidOrderCount'],
                'paidOrderTrend' => $paidOrderTrend,
                'paidAmount' => $currentData['paidAmount'],
                'paidAmountTrend' => $paidAmountTrend,
                'conversionRate' => $currentData['conversionRate'],
                'conversionRateTrend' => $conversionRateTrend,
            ],
            'chart' => $chartData,
            'teamSales' => [], // 如果需要团队销售数据，可以在这里添加
        ];

        return json(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * 获取订单统计数据
     * 
     * @param string $merchantId 商户ID
     * @param string $startDateUTC 开始日期（UTC时间）
     * @param string $endDateUTC 结束日期（UTC时间）
     * @param string $period 周期类型 (day, week, month)
     * @return array 订单统计数据
     */
    private function getOrderStats($merchantId, $startDateUTC, $endDateUTC, $period)
    {
        // 查询订单总数
        $orderCount = Db::name('payment_order')
            ->where(['merchant_id' => $merchantId])
            ->whereTime('created_at', 'between', [$startDateUTC, $endDateUTC])
            ->count();

        // 查询已支付订单数
        $paidOrderCount = Db::name('payment_order')
            ->where(['merchant_id' => $merchantId])
            ->where('status', 'completed')
            ->whereTime('created_at', 'between', [$startDateUTC, $endDateUTC])
            ->count();

        // 查询已支付订单金额
        $paidAmount = Db::name('payment_order')
            ->where(['merchant_id' => $merchantId])
            ->where('status', 'completed')
            ->whereTime('created_at', 'between', [$startDateUTC, $endDateUTC])
            ->sum('amount');

        // 计算转化率
        $conversionRate = $orderCount > 0 ? round(($paidOrderCount / $orderCount) * 100, 2) : 0;

        return [
            'orderCount' => $orderCount,
            'paidOrderCount' => $paidOrderCount,
            'paidAmount' => $paidAmount,
            'conversionRate' => $conversionRate,
        ];
    }

    /**
     * 计算趋势
     * 
     * @param float $current 当前值
     * @param float $previous 上一个周期的值
     * @return array 趋势数据
     */
    private function calculateTrend($current, $previous)
    {
        if ($previous == 0) {
            return ['value' => 0, 'trend' => 'up'];
        }

        $change = $current - $previous;
        $percentChange = round(($change / $previous) * 100, 2);

        return [
            'value' => abs($percentChange),
            'trend' => $percentChange >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * 获取图表数据
     * 
     * @param string $merchantId 商户ID
     * @param string $period 周期类型 (day, week, month)
     * @return array 图表数据
     */
    private function getChartData($merchantId, $period)
    {
        $labels = [];
        $orderData = [];
        $paidOrderData = [];
        $amountData = [];

        // 根据周期类型确定查询范围和格式
        $today = date('Y-m-d');
        $format = '';
        $dateField = '';
        $limit = 0;

        switch ($period) {
            case 'day':
                // 前7天
                $startDate = date('Y-m-d', strtotime('-6 days'));
                $format = '%Y-%m-%d';
                $dateField = 'DATE(created_at)';
                $limit = 7;
                break;
            case 'week':
                // 前7周
                $startDate = date('Y-m-d', strtotime('-6 weeks'));
                $format = '%x-第%v周'; // ISO周格式：年-周
                $dateField = "CONCAT(YEAR(created_at), '-第', WEEK(created_at, 1), '周')";
                $limit = 7;
                break;
            case 'month':
                // 前12个月
                $startDate = date('Y-m-d', strtotime('-11 months'));
                $format = '%Y-%m';
                $dateField = "DATE_FORMAT(created_at, '%Y-%m')";
                $limit = 12;
                break;
            default:
                // 默认前7天
                $startDate = date('Y-m-d', strtotime('-6 days'));
                $format = '%Y-%m-%d';
                $dateField = 'DATE(created_at)';
                $limit = 7;
        }

        // 转换为UTC时间进行查询
        $startDateUTC = \app\common\helper\TimeHelper::convertToUTC($startDate . ' 00:00:00');
        $endDateUTC = \app\common\helper\TimeHelper::convertToUTC($today . ' 23:59:59');

        // 1. 查询订单总数（按日期分组）
        $orderStats = Db::name('payment_order')
            ->field([
                $dateField . ' as date_group',
                'COUNT(id) as order_count'
            ])
            ->where(['merchant_id' => $merchantId])
            ->whereTime('created_at', 'between', [$startDateUTC, $endDateUTC])
            ->group('date_group')
            ->order('date_group ASC')
            ->select();

        // 2. 查询已支付订单数和金额（按日期分组）
        $paidStats = Db::name('payment_order')
            ->field([
                $dateField . ' as date_group',
                'COUNT(id) as paid_count',
                'SUM(amount) as paid_amount'
            ])
            ->where(['merchant_id' => $merchantId])
            ->where('status', 'completed')
            ->whereTime('created_at', 'between', [$startDateUTC, $endDateUTC])
            ->group('date_group')
            ->order('date_group ASC')
            ->select();

        // 生成日期点并填充数据
        $datePoints = [];

        // 根据周期生成日期点
        if ($period == 'day') {
            // 生成前7天的日期点
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $label = date('m-d', strtotime("-$i days"));
                $datePoints[date('Y-m-d', strtotime("-$i days"))] = [
                    'label' => $label,
                    'order_count' => 0,
                    'paid_count' => 0,
                    'paid_amount' => 0
                ];
            }
            // 反转数组，使日期从旧到新排序
            $datePoints = array_reverse($datePoints, true);
        } elseif ($period == 'week') {
            // 生成前7周的日期点
            for ($i = 0; $i < 7; $i++) {
                $weekDate = date('Y-m-d', strtotime("-$i weeks"));
                $weekYear = date('Y', strtotime($weekDate));
                $weekNum = date('W', strtotime($weekDate));
                $key = "$weekYear-第{$weekNum}周";
                $datePoints[$key] = [
                    'label' => $key,
                    'order_count' => 0,
                    'paid_count' => 0,
                    'paid_amount' => 0
                ];
            }
            // 反转数组，使日期从旧到新排序
            $datePoints = array_reverse($datePoints, true);
        } elseif ($period == 'month') {
            // 生成前12个月的日期点
            for ($i = 0; $i < 12; $i++) {
                $monthDate = date('Y-m', strtotime("-$i months"));
                $datePoints[$monthDate] = [
                    'label' => $monthDate,
                    'order_count' => 0,
                    'paid_count' => 0,
                    'paid_amount' => 0
                ];
            }
            // 反转数组，使日期从旧到新排序
            $datePoints = array_reverse($datePoints, true);
        }

        // 填充订单总数数据
        foreach ($orderStats as $stat) {
            $dateGroup = $stat['date_group'];
            if (isset($datePoints[$dateGroup])) {
                $datePoints[$dateGroup]['order_count'] = $stat['order_count'];
            }
        }

        // 填充已支付订单数和金额数据
        foreach ($paidStats as $stat) {
            $dateGroup = $stat['date_group'];
            if (isset($datePoints[$dateGroup])) {
                $datePoints[$dateGroup]['paid_count'] = $stat['paid_count'];
                $datePoints[$dateGroup]['paid_amount'] = $stat['paid_amount'];
            }
        }

        // 提取数据到数组
        foreach ($datePoints as $point) {
            $labels[] = $point['label'];
            $orderData[] = $point['order_count'];
            $paidOrderData[] = $point['paid_count'];
            $amountData[] = $point['paid_amount'];
        }

        return [
            'labels' => $labels,
            'orderData' => $orderData,
            'paidOrderData' => $paidOrderData,
            'amountData' => $amountData,
        ];
    }
}
