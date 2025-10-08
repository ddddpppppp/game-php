<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Bot as EnumBot;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\LongPay;
use app\common\helper\TgHelper;
use app\common\helper\TimeHelper;
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
use app\common\model\Users;
use app\common\model\WithdrawChannel;
use app\common\service\UserBalance;
use app\shop\service\User as ServiceUser;

class User extends Controller
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
     * 获取用户列表
     */
    public function getUserList()
    {
        try {
            $page = $this->params['page'] ?? 1;
            $size = $this->params['size'] ?? 10;
            $username = $this->params['username'] ?? '';
            $nickname = $this->params['nickname'] ?? '';
            $status = $this->params['status'] ?? '';
            $parent_id = $this->params['parent_id'] ?? '';
            $ip = $this->params['ip'] ?? '';
            $device_code = $this->params['device_code'] ?? '';
            $user_type = $this->params['user_type'] ?? '';
            $where = [];
            if (!empty($username)) {
                $where[] = ['username', 'like', '%' . $username . '%'];
            }
            if (!empty($nickname)) {
                $where[] = ['nickname', 'like', '%' . $nickname . '%'];
            }
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }
            if (!empty($parent_id)) {
                $where[] = ['parent_id', '=', $parent_id];
            }
            if (!empty($ip)) {
                $where[] = ['ip', '=', $ip];
            }
            if (!empty($device_code)) {
                $where[] = ['device_code', '=', $device_code];
            }
            if (!empty($user_type)) {
                $where[] = ['type', '=', $user_type];
            }
            $model = new \app\common\model\Users();
            $total = $model->where($where)->count();
            $list = $model->where($where)
                ->field('id,username,nickname,avatar,parent_id,status,created_at,updated_at,ip,device_code,balance,type as user_type')
                ->order('id desc')
                ->page($page, $size)
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }
        $ipTimes = \app\common\model\Users::where('ip', 'in', array_column($list, 'ip'))->field('ip,count(*) as count')->group('ip')->select()->toArray();
        $ipTimes = ArrayHelper::setKey($ipTimes, 'ip');
        $deviceCodeTimes = \app\common\model\Users::where('device_code', 'in', array_column($list, 'device_code'))->field('device_code,count(*) as count')->group('device_code')->select()->toArray();
        $deviceCodeTimes = ArrayHelper::setKey($deviceCodeTimes, 'device_code');
        foreach ($list as &$item) {
            $item['ip_times'] = $ipTimes[$item['ip']]['count'] ?? 0;
            $item['device_code_times'] = $deviceCodeTimes[$item['device_code']]['count'] ?? 0;
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);

        return $this->success([
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取单个用户信息
     */
    public function getUser()
    {
        try {
            $id = $this->params['id'] ?? 0;
            if (empty($id)) {
                return $this->error('参数错误');
            }

            $model = new \app\common\model\Users();
            $user = $model->field('id,username,nickname,avatar,parent_id,status,created_at,updated_at')
                ->with(['parent' => function ($query) {
                    $query->field('id,username,nickname');
                }])
                ->find($id);

            if (!$user) {
                return $this->error('用户不存在');
            }

            $userData = $user->toArray();

            // 获取邀请人数统计
            $inviteCount = $model->where('parent_id', $id)->count();
            $userData['invite_count'] = $inviteCount;
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }

        return $this->success($userData);
    }

    /**
     * 编辑用户
     */
    public function editUser()
    {
        try {
            $form = $this->form;
            if (empty($form)) {
                return $this->error('参数错误');
            }

            $model = new \app\common\model\Users();
            $userId = $form['id'] ?? 0;

            // 更新用户
            $user = $model->find($userId);
            if (!$user) {
                return $this->error('用户不存在');
            }

            // 验证必填字段
            $required_fields = ['nickname'];
            foreach ($required_fields as $field) {
                if (!isset($form[$field]) || $form[$field] === '') {
                    return $this->error("请填写{$field}");
                }
            }

            // 如果要修改用户名，检查是否已存在
            if (!empty($form['username']) && $form['username'] !== $user->username) {
                if (
                    !filter_var($form['username'], FILTER_VALIDATE_EMAIL) &&
                    !preg_match('/^1[3-9]\d{9}$/', $form['username'])
                ) {
                    return $this->error('用户名必须是有效的邮箱或手机号');
                }

                $existUser = $model->where('username', $form['username'])->where('id', '<>', $userId)->find();
                if ($existUser) {
                    return $this->error('用户名已存在');
                }
            }

            $data = [
                'nickname' => $form['nickname'],
                'avatar' => $form['avatar'] ?? $user->avatar,
                'parent_id' => $form['parent_id'] ?? $user->parent_id,
                'status' => $form['status'] ?? $user->status,
            ];

            if (!empty($form['username'])) {
                $data['username'] = $form['username'];
            }

            $result = $model->where('id', $userId)->update($data);
            $message = '更新成功';
            $action = '编辑用户';
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }

        // 记录操作日志
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id ?? '', $action);
        return $this->success([], 1, $message);
    }

    /**
     * 修改用户登录密码
     */
    public function changeUserPassword()
    {
        try {
            $userId = $this->params['user_id'] ?? 0;
            $newPassword = $this->params['new_password'] ?? '';

            if (empty($userId) || empty($newPassword)) {
                return $this->error('参数错误');
            }

            if (strlen($newPassword) < 6) {
                return $this->error('密码长度不能少于6位');
            }

            $model = new \app\common\model\Users();
            $user = $model->find($userId);
            if (!$user) {
                return $this->error('用户不存在');
            }

            // 生成新的盐值和密码
            $salt = create_token();
            $hashedPassword = create_password($salt, $newPassword);

            $result = $model->where('id', $userId)->update([
                'password' => $hashedPassword,
                'salt' => $salt,
            ]);
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }

        // 记录操作日志
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id ?? '', '修改用户登录密码');
        return $this->success([], 1, '密码修改成功');
    }

    /**
     * 切换用户状态
     */
    public function toggleUserStatus()
    {
        try {
            $userId = $this->params['user_id'] ?? 0;
            if (empty($userId)) {
                return $this->error('参数错误');
            }

            $model = new \app\common\model\Users();
            $user = $model->find($userId);
            if (!$user) {
                return $this->error('用户不存在');
            }

            $newStatus = $user->status == 1 ? 0 : 1;
            $result = $model->where('id', $userId)->update(['status' => $newStatus]);

            $statusText = $newStatus == 1 ? '启用' : '禁用';
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }

        // 记录操作日志
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id ?? '', $statusText . '用户');
        return $this->success([], 1, $statusText . '成功');
    }

    /**
     * 批量操作用户状态
     */
    public function batchToggleUserStatus()
    {
        try {
            $userIds = $this->params['user_ids'] ?? [];
            $status = $this->params['status'] ?? 1;

            if (empty($userIds) || !is_array($userIds)) {
                return $this->error('参数错误');
            }

            $model = new \app\common\model\Users();
            $result = $model->where('id', 'in', $userIds)->update(['status' => $status]);

            $statusText = $status == 1 ? '启用' : '禁用';
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }

        // 记录操作日志
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id ?? '', '批量' . $statusText . '用户');
        return $this->success([], 1, '批量' . $statusText . '成功');
    }

    /**
     * 获取充值列表
     */
    public function getRechargeList()
    {
        try {
            $page = $this->params['page'] ?? 1;
            $size = $this->params['size'] ?? 10;
            $username = $this->params['username'] ?? '';
            $order_no = $this->params['order_no'] ?? '';
            $status = $this->params['status'] ?? '';
            $start_date = $this->params['start_date'] ?? '';
            $end_date = $this->params['end_date'] ?? '';

            $where = [['type', '=', 'deposit']];

            // 用户筛选
            if (!empty($username)) {
                $userIds = \app\common\model\Users::where('username', 'like', '%' . $username . '%')
                    ->column('id');
                if (!empty($userIds)) {
                    $where[] = ['user_id', 'in', $userIds];
                } else {
                    // 如果没有找到匹配的用户，返回空结果
                    return $this->success([
                        'list' => [],
                        'total' => 0
                    ]);
                }
            }

            if (!empty($order_no)) {
                $where[] = ['order_no', 'like', '%' . $order_no . '%'];
            }
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }
            if (!empty($start_date)) {
                $where[] = ['created_at', '>=', $start_date . ' 00:00:00'];
            }
            if (!empty($end_date)) {
                $where[] = ['created_at', '<=', $end_date . ' 23:59:59'];
            }

            $model = new Transactions();
            $total = $model->where($where)->count();
            $list = $model->where($where)
                ->field('id,user_id,amount,actual_amount,order_no,fee,account,status,created_at,completed_at,expired_at,channel_id,gift')
                ->order('id desc')
                ->page($page, $size)
                ->select()
                ->toArray();

            // 计算统计数据
            $stats = [
                'total_amount' => 0,
                'pending_amount' => 0,
                'completed_amount' => 0,
                'failed_amount' => 0,
                'pending_count' => 0,
                'completed_count' => 0,
                'failed_count' => 0,
            ];

            if (!empty($list)) {
                // 获取当前筛选条件下的统计数据
                $statsData = $model->where($where)
                    ->field('status,count(*) as count,sum(amount) as total_amount')
                    ->group('status')
                    ->select()
                    ->toArray();

                foreach ($statsData as $stat) {
                    $stats['total_amount'] += $stat['total_amount'];
                    switch ($stat['status']) {
                        case 'pending':
                            $stats['pending_amount'] = $stat['total_amount'];
                            $stats['pending_count'] = $stat['count'];
                            break;
                        case 'completed':
                            $stats['completed_amount'] = $stat['total_amount'];
                            $stats['completed_count'] = $stat['count'];
                            break;
                        case 'failed':
                            $stats['failed_amount'] = $stat['total_amount'];
                            $stats['failed_count'] = $stat['count'];
                            break;
                    }
                }

                $userList = \app\common\model\Users::where('id', 'in', array_column($list, 'user_id'))->field('id,username,nickname')->select()->toArray();
                $userList = ArrayHelper::setKey($userList, 'id');

                $channelList = \app\common\model\PaymentChannel::where('id', 'in', array_column($list, 'channel_id'))->field('id,name')->select()->toArray();
                $channelList = ArrayHelper::setKey($channelList, 'id');
                foreach ($list as &$item) {
                    $item['user'] = $userList[$item['user_id']] ?? [];
                    $item['channel_name'] = $channelList[$item['channel_id']]['name'] ?? '';
                    $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
                    $item['completed_at'] = TimeHelper::convertFromUTC($item['completed_at']);
                }
                unset($item);
            }
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }

        return $this->success([
            'list' => $list,
            'total' => $total,
            'stats' => $stats
        ]);
    }

    /**
     * 获取提现列表
     */
    public function getWithdrawList()
    {
        try {
            $page = $this->params['page'] ?? 1;
            $size = $this->params['size'] ?? 10;
            $username = $this->params['username'] ?? '';
            $order_no = $this->params['order_no'] ?? '';
            $status = $this->params['status'] ?? '';
            $start_date = $this->params['start_date'] ?? '';
            $end_date = $this->params['end_date'] ?? '';
            $device_code = $this->params['device_code'] ?? '';
            $ip = $this->params['ip'] ?? '';

            $where = [['type', '=', 'withdraw']];

            // 用户筛选
            $userWhere = [];
            if (!empty($username)) {
                $userWhere[] = ['username', 'like', '%' . $username . '%'];
            }
            if (!empty($device_code)) {
                $userWhere[] = ['device_code', 'like', '%' . $device_code . '%'];
            }
            if (!empty($ip)) {
                $userWhere[] = ['ip', 'like', '%' . $ip . '%'];
            }

            if (!empty($userWhere)) {
                $userIds = \app\common\model\Users::where($userWhere)->column('id');
                if (!empty($userIds)) {
                    $where[] = ['user_id', 'in', $userIds];
                } else {
                    // 如果没有找到匹配的用户，返回空结果
                    return $this->success([
                        'list' => [],
                        'total' => 0,
                        'stats' => [
                            'total_amount' => 0,
                            'pending_amount' => 0,
                            'completed_amount' => 0,
                            'failed_amount' => 0,
                            'pending_count' => 0,
                            'completed_count' => 0,
                            'failed_count' => 0,
                        ]
                    ]);
                }
            }

            if (!empty($order_no)) {
                $where[] = ['order_no', 'like', '%' . $order_no . '%'];
            }
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }
            if (!empty($start_date)) {
                $where[] = ['created_at', '>=', $start_date . ' 00:00:00'];
            }
            if (!empty($end_date)) {
                $where[] = ['created_at', '<=', $end_date . ' 23:59:59'];
            }

            $model = new Transactions();
            $total = $model->where($where)->count();
            $list = $model->where($where)
                ->field('id,user_id,channel_id,amount,actual_amount,order_no,fee,account,status,created_at,completed_at,expired_at,remark')
                ->order('id desc')
                ->page($page, $size)
                ->select()
                ->toArray();

            // 计算统计数据
            $stats = [
                'total_amount' => 0,
                'total_fee' => 0,
                'pending_amount' => 0,
                'completed_amount' => 0,
                'failed_amount' => 0,
                'pending_count' => 0,
                'completed_count' => 0,
                'failed_count' => 0,
            ];

            if (!empty($list)) {
                // 获取当前筛选条件下的统计数据
                $statsData = $model->where($where)
                    ->field('status,count(*) as count,sum(amount) as total_amount,sum(fee) as total_fee')
                    ->group('status')
                    ->select()
                    ->toArray();

                foreach ($statsData as $stat) {
                    $stats['total_amount'] += $stat['total_amount'];
                    $stats['total_fee'] += $stat['total_fee'];
                    switch ($stat['status']) {
                        case 'pending':
                            $stats['pending_amount'] = $stat['total_amount'];
                            $stats['pending_count'] = $stat['count'];
                            break;
                        case 'completed':
                            $stats['completed_amount'] = $stat['total_amount'];
                            $stats['completed_count'] = $stat['count'];
                            break;
                        case 'failed':
                            $stats['failed_amount'] = $stat['total_amount'];
                            $stats['failed_count'] = $stat['count'];
                            break;
                    }
                }

                $userList = \app\common\model\Users::where('id', 'in', array_column($list, 'user_id'))->field('id,username,nickname,device_code,ip,uuid')->select()->toArray();
                $userList = ArrayHelper::setKey($userList, 'id');

                $channelList = WithdrawChannel::where('id', 'in', array_column($list, 'channel_id'))->field('id,type')->select()->toArray();
                $channelList = ArrayHelper::setKey($channelList, 'id');

                // 获取用户统计数据
                $userIds = array_column($list, 'user_id');

                // 获取设备号和IP的使用次数
                $deviceCodes = array_column($userList, 'device_code');
                $counts = \app\common\model\Users::where('device_code', 'in', $deviceCodes)
                    ->field('device_code, count(*) as count')
                    ->group('device_code')
                    ->select()
                    ->toArray();
                $deviceCodeCounts = ArrayHelper::setKey($counts, 'device_code');

                $ips = array_column($userList, 'ip');
                $counts = \app\common\model\Users::where('ip', 'in', $ips)
                    ->field('ip, count(*) as count')
                    ->group('ip')
                    ->select()
                    ->toArray();
                $ipCounts = ArrayHelper::setKey($counts, 'ip');

                foreach ($list as &$item) {
                    $item['user'] = $userList[$item['user_id']] ?? [];
                    $item['channel_name'] = $channelList[$item['channel_id']]['type'] ?? '';

                    // 添加设备号和IP的使用次数信息
                    if (isset($item['user']['device_code'])) {
                        $item['device_code_count'] = $deviceCodeCounts[$item['user']['device_code']] ?? 0;
                    }
                    if (isset($item['user']['ip'])) {
                        $item['ip_count'] = $ipCounts[$item['user']['ip']] ?? 0;
                    }
                    $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
                    $item['completed_at'] = TimeHelper::convertFromUTC($item['completed_at']);
                }
                unset($item);
            }
        } catch (\Exception $e) {
            return $this->error('获取失败：' . $e->getMessage());
        }

        return $this->success([
            'list' => $list,
            'total' => $total,
            'stats' => $stats
        ]);
    }

    public function getUserStats()
    {
        $userId = $this->params['user_id'] ?? 0;
        $userUuid = trim($this->params['user_uuid'] ?? '');
        $cond = ['merchant_id' => $this->admin->merchant_id];
        if (!empty($userId)) {
            $cond['id'] = $userId;
        } else if (!empty($userUuid)) {
            $cond['uuid'] = $userUuid;
        } else {
            return $this->error('用户ID或用户UUID不能为空');
        }
        $user = Users::where($cond)->field('id,uuid')->find();
        if (!$user) {
            return $this->error('用户不存在');
        }
        return $this->success(ServiceUser::getUserStats($user['id']));
    }

    /**
     * 处理提现申请（通过或拒绝）
     */
    public function processWithdraw()
    {
        try {
            $id = $this->params['id'] ?? 0;
            $action = $this->params['action'] ?? ''; // 'approve' 或 'reject'
            $remark = $this->params['remark'] ?? '';

            if (empty($id) || empty($action)) {
                throw new \Exception('参数错误');
            }

            if (!in_array($action, ['approve', 'reject'])) {
                throw new \Exception('操作类型错误');
            }

            $model = new Transactions();
            $withdraw = $model->find($id);
            if (!$withdraw) {
                throw new \Exception('提现记录不存在');
            }

            if ($withdraw->type !== 'withdraw') {
                throw new \Exception('记录类型错误');
            }

            if ($withdraw->status !== 'pending') {
                throw new \Exception('只能处理待处理的提现申请');
            }

            $user = Users::where(['id' => $withdraw->user_id])->field('id,username,nickname')->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            $updateData = [];
            $actionText = '';

            if ($action === 'approve') {
                $channel = WithdrawChannel::find($withdraw->channel_id);
                if ($channel->name == 'longpay-cashapp') {
                    $longpay = new LongPay($channel->params);
                    list($payId, $message) = $longpay->createWithdrawOrder($withdraw->order_no, $withdraw->account, $withdraw->actual_amount);
                    if ($message) {
                        throw new \Exception($message);
                    }
                }
                $updateData['status'] = 'completed';
                $updateData['completed_at'] = date('Y-m-d H:i:s');
                $actionText = '批准提现';
                TgHelper::sendMessage(EnumBot::PAYMENT_BOT_TOKEN, EnumBot::FINANCE_CHAT_ID, sprintf("✅提现订单审核通过\n💵金额: %s\n👤用户: %s", $withdraw->actual_amount, $user->username));
            } else {
                $updateData['status'] = 'failed';
                $updateData['completed_at'] = date('Y-m-d H:i:s');
                $actionText = '拒绝提现，原因：' . $remark;
                $updateData['remark'] = $remark;
                UserBalance::refundWithdraw($withdraw);
                TgHelper::sendMessage(EnumBot::PAYMENT_BOT_TOKEN, EnumBot::FINANCE_CHAT_ID, sprintf("❌提现订单审核拒绝\n💵金额: %s\n👤用户: %s", $withdraw->actual_amount, $user->username));
            }

            $result = $model->where('id', $id)->update($updateData);
        } catch (\Exception $e) {
            return $this->error('操作失败：' . $e->getMessage());
        }

        // 记录操作日志
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id ?? '', $actionText);
        return $this->success([], 1, $actionText . '成功');
    }
}
