<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\TimeHelper;
use app\common\service\AdminBalance;
use app\common\service\Bot;
use app\common\utils\ParamsAesHelper;
use app\shop\enum\Menu;
use app\shop\model\Petter;
use app\shop\model\SystemSetting;
use app\shop\service\Admin;
use app\shop\service\AdminOperationLog;
use app\shop\service\Merchant;
use app\shop\service\Role;
use app\shop\service\Takeout;
use think\Env;
use think\facade\Cache;
use think\facade\Log;

class Setting extends Controller
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


    public function getMerchantList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;
        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }
        $cond = [];
        $ret = \app\shop\model\Merchant::where($cond);
        if (!empty($this->params['name'])) {
            $ret->where('name', 'like', '%' . $this->params['name'] . '%');
        }
        $count = $ret->count();
        $list = $ret->order('id desc')->limit($limit)->select()->toArray();
        $adminList = \app\shop\model\Admin::where(['uuid' => array_column($list, 'admin_id')])->field('uuid,username')->select()->toArray();
        $adminList = ArrayHelper::setKey($adminList, 'uuid');

        foreach ($list as &$item) {
            $item['status_class'] = Common::STATUS_DATA_SET[$item['status']]['class'];
            $item['status_name'] = Common::STATUS_DATA_SET[$item['status']]['name'];
            $item['username'] = $adminList[$item['admin_id']]['username'] ?? '--';
            $item['access_list'] = explode(',', $item['access_list']);
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);
        return $this->success(['list' => ArrayHelper::camelizeBatch($list), 'total' => (int) $count]);
    }

    public function getMerchant()
    {
        $id = trim($this->params['id']);
        if (empty($id)) {
            return $this->error('id不能为空');
        }
        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }
        /** @var \app\shop\model\Merchant|null $merchant */
        $merchant = Merchant::getMerchantInfo($id);
        if (empty($merchant)) {
            return $this->error('不存在');
        }
        /** @var \app\shop\model\Admin|null $admin */
        $admin = Admin::getAdminByUuid($merchant->admin_id);
        if (empty($admin)) {
            return $this->error('不存在');
        }
        $merchantData = $merchant->toArray();
        $merchantData['avatar'] = $admin->avatar;
        $merchantData['username'] = $admin->username;
        $merchantConfigList = \app\shop\model\MerchantConfig::where(['merchant_id' => $merchantData['uuid']])->select();
        if (!empty($merchantConfigList)) {
            foreach ($merchantConfigList as $item) {
                $merchantData[$item->name] = $item->value;
            }
        }
        unset($merchantData['admin_id']);
        return $this->success(['merchant' => ArrayHelper::camelize($merchantData)]);
    }

    public function editMerchant()
    {
        $id = trim($this->form['uuid']);
        $this->form['name'] = trim($this->form['name']);
        $this->form['username'] = trim($this->form['username']);
        $this->form['nickname'] = trim($this->form['nickname']);
        $this->form['avatar'] = trim($this->form['avatar']);
        $this->form['whiteIpList'] = trim($this->form['whiteIpList']);
        if (empty($this->form['username']) || empty($this->form['name']) || empty($this->form['avatar'])) {
            return $this->error(Common::PARAMS_EMPTY_MSG);
        }
        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }
        $data = ['name' => $this->form['name']];
        if ($id) {
            /** @var \app\shop\model\Merchant|null $merchant */
            $merchant = Merchant::getMerchantInfo($id);
            if (empty($merchant)) {
                return $this->error('不存在');
            }
            /** @var \app\shop\model\Admin|null $admin */
            $admin = Admin::getAdminByUuid($merchant->admin_id);
            $adminUpdate = [];
            if (!empty($this->form['password'])) {
                $adminUpdate['password'] = create_password($admin->salt, $this->form['password']);
            }
            if (!empty($this->form['name'])) {
                $adminUpdate['nickname'] = $this->form['name'];
            }
            if (!empty($this->form['avatar'])) {
                $adminUpdate['avatar'] = $this->form['avatar'];
                $data['logo'] = $this->form['avatar'];
            }
            $adminUpdate && \app\shop\model\Admin::update($adminUpdate, ['uuid' => $merchant->admin_id]);
            $data && \app\shop\model\Merchant::update($data, ['uuid' => $id]);
        } else {
            if (empty($this->form['password'])) {
                return $this->error(Common::PARAMS_EMPTY_MSG);
            }
            list($code, $merchant) = Admin::initMerchant($this->form['username'], $this->form['name'], 0, $this->form['password'], $this->form['avatar']);
            $id = $merchant->uuid;
            if ($code < 0) {
                return $this->error($merchant);
            }
        }
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('编辑了id为%s的商户', $id));
        if (!empty($this->form['ipWhiteList'])) {
            Merchant::saveMerchantConfig($id, 'ip_white_list', $this->form['ipWhiteList']);
        }
        return $this->success();
    }

    public function delMerchant()
    {
        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        foreach ($ids as $id) {
            $merchant = Merchant::getMerchantInfo($id);
            if (empty($merchant)) {
                return $this->error('不存在');
            }
        }
        \app\shop\model\Merchant::update(['status' => -1], ['uuid' => $ids]);
        return $this->success();
    }

    public function recoveryMerchant()
    {
        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        foreach ($ids as $id) {
            $merchant = Merchant::getMerchantInfo($id);
            if (empty($merchant)) {
                return $this->error('不存在');
            }
        }
        \app\shop\model\Merchant::update(['status' => 1], ['uuid' => $ids]);
        return $this->success();
    }

    public function getAdminList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;
        if (!in_array($this->role->type, [3, 2, 1])) {
            $this->error('您没有权限');
        }
        $cond = ['uuid' => Admin::getMyEmployee($this->role->type, $this->admin->merchant_id, $this->admin->uuid)];
        $ret = \app\shop\model\Admin::where($cond)->where('merchant_id', '=', $this->admin->merchant_id);
        if (!empty($this->params['name'])) {
            $ret->where('nickname', 'like', '%' . $this->params['name'] . '%');
        }
        $count = $ret->count();
        $list = $ret->order('id desc')->limit($limit)->select()->toArray();
        $roleList = \app\shop\model\Role::where(['id' => array_column($list, 'role_id')])->select()->toArray();
        $roleList = ArrayHelper::setKey($roleList, 'id');

        foreach ($list as &$item) {
            unset($item['password'], $item['salt']);
            $item['status_class'] = Common::STATUS_DATA_SET[$item['status']]['class'];
            $item['status_name'] = Common::STATUS_DATA_SET[$item['status']]['name'];
            $item['role_name'] = $roleList[$item['role_id']]['name'] ?? '--';
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);
        return $this->success(['list' => ArrayHelper::camelizeBatch($list), 'total' => (int) $count]);
    }

    public function getAdmin()
    {
        $id = trim($this->params['id']);
        if (empty($id)) {
            return $this->error('id不能为空');
        }
        $admin = Admin::getAdminByUuid($id);
        if (empty($admin) || $admin->merchant_id != $this->admin->merchant_id) {
            return $this->error('不存在或没权限');
        }
        unset($admin->password, $admin->salt);
        $admin = $admin->toArray();
        return $this->success(['admin' => ArrayHelper::camelize($admin)]);
    }

    public function editAdmin()
    {
        $id = trim($this->form['uuid']);
        $this->form['name'] = trim($this->form['name']);
        $this->form['username'] = trim($this->form['username']);
        $this->form['nickname'] = trim($this->form['nickname']);
        $this->form['avatar'] = trim($this->form['avatar']);
        $this->form['takeoutRate'] = trim($this->form['takeoutRate']);
        if (empty($this->form['roleId']) || empty($this->form['nickname']) || empty($this->form['username']) || empty($this->form['avatar'])) {
            return $this->error(Common::PARAMS_EMPTY_MSG);
        }
        $data = ['nickname' => $this->form['nickname'], 'role_id' => $this->form['roleId'], 'avatar' => $this->form['avatar']];
        $setRole = Role::getRole($this->form['roleId']);
        if ($setRole->merchant_id != $this->admin->merchant_id) {
            return $this->error('您没有权限');
        }
        if ($id) {
            /** @var \app\shop\model\Admin|null $admin */
            $admin = Admin::getAdminByUuid($id);
            if (empty($admin) || $admin->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
            if (!empty($this->form['password'])) {
                $data['password'] = create_password($admin->salt, $this->form['password']);
            }

            \app\shop\model\Admin::update($data, ['uuid' => $id]);
        } else {
            if (empty($this->form['password'])) {
                return $this->error(Common::PARAMS_EMPTY_MSG);
            }
            if ($this->admin->depth >= 2) {
                return $this->error('您的级别已经无法再添加代理');
            }
            $count = \app\shop\model\Admin::where('username', $this->form['username'])->count();
            if ($count > 0) {
                return $this->error('该用户名已存在');
            }
            $id = create_uuid();
            $data['uuid'] = $id;
            $data['salt'] = create_token();
            $data['password'] = create_password($data['salt'], $this->form['password']);
            $data['merchant_id'] = $this->admin->merchant_id;
            $data['username'] = $this->form['username'];
            $data['nickname'] = $this->form['nickname'];
            $data['role_id'] = $this->form['roleId'];
            $data['parent_id'] = $this->admin->uuid;
            $data['path'] = $this->admin->path ? $this->admin->path . ':' . $id : $id;
            $data['depth'] = $this->admin->depth + 1;
            $admin = \app\shop\model\Admin::create($data);
        }
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('编辑了id为%s的管理员', $id));
        return $this->success();
    }

    public function delAdmin()
    {
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        foreach ($ids as $id) {
            /** @var \app\shop\model\Admin|null $admin */
            $admin = Admin::getAdminByUuid($id);
            if (empty($admin) || $admin->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
        }
        \app\shop\model\Admin::update(['status' => -1], ['uuid' => $ids]);
        return $this->success();
    }

    public function recoveryAdmin()
    {
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        foreach ($ids as $id) {
            /** @var \app\shop\model\Admin|null $admin */
            $admin = Admin::getAdminByUuid($id);
            if (empty($admin) || $admin->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
        }
        \app\shop\model\Admin::update(['status' => 1], ['uuid' => $ids]);
        return $this->success();
    }

    public function getRoleList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;
        if (!in_array($this->role->type, [3, 2, 1])) {
            $this->error('您没有权限');
        }
        $cond = ['merchant_id' => $this->admin->merchant_id];
        $ret = \app\shop\model\Role::where($cond);
        if (!empty($this->params['name'])) {
            $ret->where('name', 'like', '%' . $this->params['name'] . '%');
        }
        $count = $ret->count();
        $list = $ret->order('id desc')->limit($limit)->select()->toArray();
        foreach ($list as &$item) {
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);
        return $this->success(['list' => ArrayHelper::camelizeBatch($list), 'total' => (int) $count]);
    }

    public function getRole()
    {
        $id = intval($this->params['id']);
        $role = null;
        $accessArr = [];
        if ($id) {
            $role = Role::getRole($id);
            if (empty($role) || $role->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
            $accessArr = $role->access;
        }
        $list = Menu::MenuList;
        $ret = [];
        foreach ($list as $key => $item) {
            /** @var array $roleAccess */
            $roleAccess = $this->role->access;
            if (!in_array($key, $roleAccess)) {
                continue;
            }
            $temp = ['key' => $key, 'name' => $item['name'], 'children' => [], 'checked' => false];
            if (in_array($key, $accessArr)) {
                $temp['checked'] = true;
            }
            if (empty($item['children'])) {
                $ret[] = $temp;
                continue;
            }
            foreach ($item['children'] as $k => $v) {
                if (!in_array($k, $roleAccess)) {
                    continue;
                }
                $tempArr = ['key' => $k, 'name' => $v['name'], 'children' => [], 'checked' => false];
                if (in_array($k, $accessArr)) {
                    $tempArr['checked'] = true;
                }
                foreach ($v['children'] as $kk => $vv) {
                    $vv['key'] = $kk;
                    $vv['checked'] = false;
                    if (in_array($kk, $accessArr)) {
                        $vv['checked'] = true;
                    }
                    $tempArr['children'][] = $vv;
                }
                $temp['children'][] = $tempArr;
            }
            $ret[] = $temp;
        }
        return $this->success(['role' => $role, 'routes' => $ret]);
    }

    public function editRole()
    {
        $id = intval($this->form['id']);
        $routes = $this->params['routes'];
        $accessArr = [];
        foreach ($routes as $route) {
            if ($route['checked']) {
                $accessArr[] = $route['key'];
            }
            foreach ($route['children'] as $child) {
                if ($child['checked']) {
                    $accessArr[] = $child['key'];
                    $accessArr[] = $route['key'];
                }
                foreach ($child['children'] as $ch) {
                    if ($ch['checked']) {
                        $accessArr[] = $ch['key'];
                        $accessArr[] = $child['key'];
                        $accessArr[] = $route['key'];
                    }
                }
            }
        }
        $accessArr = array_unique($accessArr);
        $data = ['access' => implode(',', $accessArr), 'name' => $this->form['name']];
        if ($id) {
            $role = Role::getRole($id);
            if (empty($role) || $role->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
            if ($role->type == \app\shop\enum\Role::ADMIN_ROLE || $role->type == \app\shop\enum\Role::MERCHANT_ROLE) {
                return $this->error('初始角色不能更改');
            }
            \app\shop\model\Role::update($data, ['id' => $id]);
        } else {
            $data['merchant_id'] = $this->admin->merchant_id;
            $data['type'] = \app\shop\enum\Role::OTHER_ROLE;
            $id = \app\shop\model\Role::create($data)->id;
        }
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('编辑了id为%d的角色', $id));
        return $this->success();
    }

    public function delRole()
    {
        $id = intval($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        foreach ($ids as $id) {
            $role = Role::getRole($id);
            if (empty($role) || $role->merchant_id != $this->admin->merchant_id) {
                return $this->error('不存在或没权限');
            }
            if ($role->type != \app\shop\enum\Role::OTHER_ROLE) {
                return $this->error('初始角色不能更改');
            }
        }
        \app\shop\model\Role::destroy(['id' => $ids]);
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('删除了id为%s的角色', implode(',', $ids)));
        return $this->success();
    }

    public function getPaymentChannelList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;
        $cond = [];
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        if ($this->role->type == 4) {
            $cond['belong_admin_id'] = $this->admin->uuid;
        }
        $ret = \app\common\model\PaymentChannel::where($cond);
        if (!empty($this->params['name'])) {
            $ret->where('name', 'like', '%' . $this->params['name'] . '%');
        }
        $count = $ret->count();
        $list = $ret->order('id desc')->limit($limit)->select()->toArray();
        $channelIdList = array_column($list, 'id');
        $orderCountList = \app\common\model\Transactions::where(['channel_id' => $channelIdList])->field('channel_id,count(1) as count')->group('channel_id')->select()->toArray();
        $orderCountList = ArrayHelper::setKey($orderCountList, 'channel_id');
        $orderCountSuccList = \app\common\model\Transactions::where(['channel_id' => $channelIdList, 'status' => 'completed'])->field('channel_id,count(1) as count')->group('channel_id')->select()->toArray();
        $orderCountSuccList = ArrayHelper::setKey($orderCountSuccList, 'channel_id');
        $orderAmountList = \app\common\model\Transactions::where(['channel_id' => $channelIdList])->field('channel_id,sum(amount) as amount')->group('channel_id')->select()->toArray();
        $orderAmountList = ArrayHelper::setKey($orderAmountList, 'channel_id');
        $orderAmountSuccList = \app\common\model\Transactions::where(['channel_id' => $channelIdList, 'status' => 'completed'])->field('channel_id,sum(amount) as amount')->group('channel_id')->select()->toArray();
        $orderAmountSuccList = ArrayHelper::setKey($orderAmountSuccList, 'channel_id');

        // 计算合计数据
        $totalOrderCount = 0;
        $totalOrderSuccessCount = 0;
        $totalOrderAmount = 0;
        $totalOrderSuccessAmount = 0;

        foreach ($list as &$item) {
            $item['order_count'] = $orderCountList[$item['id']]['count'] ?? 0;
            $item['order_success_count'] = $orderCountSuccList[$item['id']]['count'] ?? 0;
            $item['order_success_rate'] = $item['order_count'] > 0 ? sprintf('%.1f%%', $item['order_success_count'] / $item['order_count'] * 100) : 0;
            $item['order_amount'] = $orderAmountList[$item['id']]['amount'] ? $orderAmountList[$item['id']]['amount'] : 0;
            $item['order_amount'] = sprintf('$%.1f', $item['order_amount']);
            $item['order_success_amount'] = $orderAmountSuccList[$item['id']]['amount'] ? $orderAmountSuccList[$item['id']]['amount'] : 0;
            $item['order_success_amount'] = sprintf('$%.1f', $item['order_success_amount']);
            $item['status_class'] = Common::STATUS_DATA_SET[$item['status']]['class'];
            $item['status_name'] = Common::STATUS_DATA_SET[$item['status']]['name'];

            // 累加合计
            $totalOrderCount += $orderCountList[$item['id']]['count'] ?? 0;
            $totalOrderSuccessCount += $orderCountSuccList[$item['id']]['count'] ?? 0;
            $totalOrderAmount += $orderAmountList[$item['id']]['amount'] ?? 0;
            $totalOrderSuccessAmount += $orderAmountSuccList[$item['id']]['amount'] ?? 0;
        }
        unset($item);

        // 添加合计行
        $total = [
            'id' => 0,
            'name' => '合计',
            'order_count' => $totalOrderCount,
            'order_success_count' => $totalOrderSuccessCount,
            'order_success_rate' => $totalOrderCount > 0 ? sprintf('%.1f%%', $totalOrderSuccessCount / $totalOrderCount * 100) : 0,
            'order_amount' => sprintf('$%.1f', $totalOrderAmount),
            'order_success_amount' => sprintf('$%.1f', $totalOrderSuccessAmount),
            'type' => '--',
        ];
        $list = array_merge([$total], $list);

        return $this->success([
            'list' => ArrayHelper::camelizeBatch($list),
            'total' => (int) $count
        ]);
    }

    public function getPaymentChannel()
    {
        $id = trim($this->params['id']);
        $cond = ['id' => $id];
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        if ($this->role->type == 4) {
            $cond['belong_admin_id'] = $this->admin->uuid;
        }
        $channel = \app\common\model\PaymentChannel::where($cond)->find();
        if (empty($channel)) {
            return $this->error('不存在或没权限');
        }
        $channel = $channel->toArray();
        $channel['params'] = json_encode($channel['params']);
        return $this->success(['payment' => ArrayHelper::camelize($channel)]);
    }

    public function setPaymentChannel()
    {
        $key = trim($this->params['key']);
        $id = trim($this->params['id']);
        $val = trim($this->params['value']);
        $cond = ['id' => $id];
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        if ($this->role->type == 4) {
            $cond['belong_admin_id'] = $this->admin->uuid;
        }
        /** @var \app\common\model\PaymentChannel|null $channel */
        $channel = \app\common\model\PaymentChannel::where($cond)->find();
        if (empty($channel)) {
            return $this->error('不存在或没权限');
        }
        if ($key == 'rate') {
            $key = 'rate';
        } else if ($key == 'chargeFee') {
            $key = 'charge_fee';
        } else if ($key == 'guarantee') {
            $key = 'guarantee';
        } else if ($key == 'freezeTime') {
            $key = 'freeze_time';
        } else if ($key == 'countTime') {
            $key = 'count_time';
        } else if ($key == 'sort') {
            $key = 'sort';
        } else if ($key == 'remark') {
            $key = 'remark';
        } else if ($key == 'dayLimitMoney') {
            $key = 'day_limit_money';
        } else if ($key == 'dayLimitCount') {
            $key = 'day_limit_count';
        } else {
            return $this->error('参数错误');
        }
        \app\common\model\PaymentChannel::update([$key => $val], ['id' => $channel->id]);
        return $this->success();
    }


    public function editPaymentChannel()
    {
        $id = trim($this->form['id']);
        $this->form['name'] = trim($this->form['name']);
        $this->form['type'] = trim($this->form['type']);
        $this->form['isBackup'] = trim($this->form['isBackup']);
        $this->form['params'] = json_decode($this->form['params'], true);
        $cond = ['id' => $id];
        if (empty($this->form['name']) || empty($this->form['type'])) {
            return $this->error(Common::PARAMS_EMPTY_MSG);
        }
        if (empty($this->form['params']) || !is_array($this->form['params'])) {
            return $this->error('参数要为json格式');
        }
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        if ($this->role->type == 4) {
            $cond['belong_admin_id'] = $this->admin->uuid;
        }
        $data = ['name' => $this->form['name'], 'type' => $this->form['type'], 'params' => $this->form['params'], 'is_backup' => $this->form['isBackup']];
        if ($id) {
            \app\common\model\PaymentChannel::where($cond)->update($data);
        } else {
            $data['status'] = 1;
            $data['rate'] = 0;
            $data['charge_fee'] = 0;
            $data['guarantee'] = 0;
            $data['freeze_time'] = 0;
            $data['count_time'] = 0;
            $data['sort'] = 0;
            $data['belong_admin_id'] = $this->admin->uuid;
            $data['remark'] = '';
            \app\common\model\PaymentChannel::create($data);
        }
        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('编辑了id为%s的支付渠道', $id));
        return $this->success();
    }

    public function disablePaymentChannel()
    {
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        foreach ($ids as $id) {
            $cond = ['id' => $id];
            if ($this->role->type == 4) {
                $cond['belong_admin_id'] = $this->admin->uuid;
            }
            /** @var \app\common\model\PaymentChannel|null $channel */
            $channel = \app\common\model\PaymentChannel::where($cond)->find();
            if (empty($channel)) {
                return $this->error('不存在或没权限');
            }
            \app\common\model\PaymentChannel::where($cond)->update(['status' => -1]);
        }
        return $this->success();
    }

    public function enablePaymentChannel()
    {
        $id = trim($this->params['id']);
        $ids = $id ? [$id] : $this->params['ids'];
        if ($this->role->type != 1 && $this->role->type != 4) {
            return $this->error('您没有权限');
        }
        foreach ($ids as $id) {
            $cond = ['id' => $id];
            if ($this->role->type == 4) {
                $cond['belong_admin_id'] = $this->admin->uuid;
            }
            /** @var \app\common\model\PaymentChannel|null $channel */
            $channel = \app\common\model\PaymentChannel::where($cond)->find();
            if (empty($channel)) {
                return $this->error('不存在或没权限');
            }
            \app\common\model\PaymentChannel::where($cond)->update(['status' => 1]);
        }
        return $this->success();
    }

    public function getOperationLogList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;
        $cond = [];
        if ($this->role->type == 2) {
            $cond = ['merchant_id' => $this->admin->merchant_id];
        } else if ($this->role->type != 1) {
            $cond = ['admin_id' => $this->admin->uuid];
        }
        $ret = \app\shop\model\AdminOperationLog::where($cond);
        if (!empty($this->params['content'])) {
            $ret->where('content', 'like', '%' . $this->params['content'] . '%');
        }
        $count = $ret->count();
        $list = $ret->order('id desc')->limit($limit)->select()->toArray();
        $merList = \app\shop\model\Merchant::where(['uuid' => array_column($list, 'merchant_id')])->field('uuid,name')->select()->toArray();
        $merList = ArrayHelper::setKey($merList, 'uuid');
        $adminList = \app\shop\model\Admin::where(['uuid' => array_column($list, 'admin_id')])->field('uuid,nickname')->select()->toArray();
        $adminList = ArrayHelper::setKey($adminList, 'uuid');
        foreach ($list as &$item) {
            $item['admin_name'] = $adminList[$item['admin_id']]['nickname'] ?? '--';
            $item['mer_name'] = $merList[$item['merchant_id']]['name'] ?? '--';
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);
        return $this->success(['list' => ArrayHelper::camelizeBatch($list), 'total' => (int) $count]);
    }

    /**
     * 获取系统设置列表
     */
    public function getSystemSettingList()
    {
        $psize = $this->params['size'] ?: 20;
        $pindex = $this->params['page'] ?: 1;
        $limit = ($pindex - 1) * $psize . ',' . $psize;

        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }

        $cond = [];
        $ret = SystemSetting::where($cond);

        if (!empty($this->params['name'])) {
            $ret->where('title', 'like', '%' . $this->params['name'] . '%');
        }

        $count = $ret->count();
        $list = $ret->order('sort asc, id asc')->limit($limit)->select()->toArray();

        foreach ($list as &$item) {
            $item['created_at'] = TimeHelper::convertFromUTC($item['created_at']);
            $item['updated_at'] = TimeHelper::convertFromUTC($item['updated_at']);
        }
        unset($item);

        return $this->success(['list' => ArrayHelper::camelizeBatch($list), 'total' => (int) $count]);
    }

    /**
     * 获取单个系统设置
     */
    public function getSystemSetting()
    {
        $name = trim($this->params['name']);
        if (empty($name)) {
            return $this->error('设置名称不能为空');
        }

        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }

        $setting = SystemSetting::getByName($name);
        if (empty($setting)) {
            return $this->error('设置不存在');
        }

        $settingData = $setting->toArray();
        return $this->success(['setting' => ArrayHelper::camelize($settingData)]);
    }

    /**
     * 编辑系统设置
     */
    public function editSystemSetting()
    {
        $name = trim($this->form['name']);
        $config = $this->form['config'] ?: [];

        if (empty($name)) {
            return $this->error('设置名称不能为空');
        }

        if (!in_array($this->role->type, [1])) {
            $this->error('您没有权限');
        }

        $setting = SystemSetting::getByName($name);
        if (empty($setting)) {
            return $this->error('设置不存在');
        }

        // 根据不同的设置类型处理配置数据
        $config = $this->processSettingConfig($name, $config);

        SystemSetting::updateConfig($name, $config);

        AdminOperationLog::saveLog($this->admin->uuid, $this->admin->merchant_id, sprintf('编辑了系统设置：%s', $setting->title));

        return $this->success();
    }

    /**
     * 处理不同类型的设置配置
     */
    private function processSettingConfig($name, $config)
    {
        switch ($name) {
            case 'usdt_recharge':
                // 充值设置：验证地址和二维码
                return [
                    'min_amount' => floatval($config['min_amount'] ?? 0),
                    'max_amount' => floatval($config['max_amount'] ?? 0),
                    'usdt_gift_rate' => floatval($config['usdt_gift_rate'] ?? 0),
                    'cashapp_gift_rate' => floatval($config['cashapp_gift_rate'] ?? 0),
                ];

            case 'withdraw_setting':
                // 提现设置
                return [
                    'min_amount' => floatval($config['min_amount'] ?? 0),
                    'max_amount' => floatval($config['max_amount'] ?? 0),
                    'usdt_fee_rate' => floatval($config['usdt_fee_rate'] ?? 0),
                    'cashapp_fee_rate' => floatval($config['cashapp_fee_rate'] ?? 0),
                    'daily_limit' => intval($config['daily_limit'] ?? 0),
                ];

            default:
                return $config;
        }
    }
}
