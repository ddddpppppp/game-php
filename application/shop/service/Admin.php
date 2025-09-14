<?php


namespace app\shop\service;

use app\common\helper\ServerHelper;
use app\common\utils\AesHelper;
use app\shop\model\Merchant;
use app\shop\model\Role;
use think\facade\Cache;

class Admin
{
    public static $status = [
        -1 => '冻结中',
        1 => '正常',
    ];

    public static $statusColor = [
        -1 => 'danger',
        1 => 'success',
    ];

    public static function getAdmin()
    {
        $adminId = self::getAdminId();
        if (empty($adminId)) {
            return false;
        }
        return \app\shop\model\Admin::where(['uuid' => $adminId])->find();
    }

    public static function checkLoginTimes()
    {
        $ip = ServerHelper::getServerIp();
        $redis = Cache::store('redis');
        $key = sprintf(\app\shop\enum\Admin::LOGIN_TIMES_KEY, $ip);
        $times = $redis->get($key);
        if (empty($times)) {
            $todayLeftTime = strtotime(date('Y-m-d', time())) + 86400 - time();
            $redis->set($key, 0, $todayLeftTime);
        }
        return $times;
    }

    public static function getAdminByUsername($username)
    {
        return \app\shop\model\Admin::where(['username' => $username])->find();
    }

    public static function login($adminId)
    {
        $redis = Cache::store('redis');
        $rand = AesHelper::encrypt($adminId . rand(111111, 999999));
        $token = sprintf(\app\shop\enum\Admin::TOKEN_KEY, $rand);
        $redis->set($token, $adminId, 86400);
        return $rand;
    }

    public static function getAdminId()
    {
        $token = request()->header('Token') ?: session('token');
        $redis = Cache::store('redis');
        $rand = sprintf(\app\shop\enum\Admin::TOKEN_KEY, $token);
        $adminId = $redis->get($rand);
        return $adminId ?: 0;
    }

    public static function initSuperAdmin()
    {
        $ret = \app\shop\model\Admin::where(['id' => \app\shop\enum\Admin::SUPER_ADMIN_ID])->count();
        if (!empty($ret)) {
            return false;
        }
        $merchantId = create_uuid();
        $adminId = create_uuid();
        $insert = [];
        $insert['name'] = "超级管理员默认商户角色";
        $insert['uuid'] = $merchantId;
        $insert['admin_id'] = $adminId;
        $insert['balance'] = 9999999;
        $insert['app_key'] = create_token();
        $insert['logo'] = '';
        $insert['status'] = 1;
        $insert['access_list'] = '';
        Merchant::create($insert);
        // 添加角色
        $insert = [];
        $insert['name'] = "管理员角色";
        $insert['type'] = 1;
        $insert['merchant_id'] = $merchantId;
        $insert['access'] = implode(',', \app\shop\enum\Admin::MENU_ACCESS);
        $role = Role::create($insert);

        $insert = [];
        $insert['nickname'] = "超级管理员";
        $insert['uuid'] = $adminId;
        $insert['username'] = "admin";
        $insert['avatar'] = '';
        $insert['merchant_id'] = $merchantId;
        $insert['role_id'] = $role->id;
        $insert['status'] = 1;
        $insert['salt'] = create_token();
        $insert['password'] = create_password($insert['salt'], "admin");
        $insert['parent_id'] = '';
        $insert['path'] = '';
        $insert['depth'] = 0;
        \app\shop\model\Admin::create($insert);
        return $adminId;
    }

    public static function initMerchant($username, $name, $balance, $password, $logo)
    {
        if (empty($username) || empty($name)) {
            return [-1, '名称或用户名不能为空'];
        }
        if (empty($password)) {
            return [-1, '初始密码不能为空'];
        }
        $ret = \app\shop\model\Admin::where(['username' => $username])->count();
        if (!empty($ret)) {
            return [-1, '该用户名已存在'];
        }
        $merchantId = create_uuid();
        $adminId = create_uuid();
        $insert = [];
        $insert['name'] = $name;
        $insert['uuid'] = $merchantId;
        $insert['balance'] = $balance;
        $insert['admin_id'] = $adminId;
        $insert['app_key'] = create_token();
        $insert['logo'] = $logo;
        $insert['status'] = 1;
        $insert['access_list'] = '';
        $merchant = Merchant::create($insert);
        $roleAccess = \app\shop\enum\Merchant::MENU_ACCESS;
        $insert = [];
        $insert['name'] = "商户角色";
        $insert['type'] = 2;
        $insert['merchant_id'] = $merchantId;
        $insert['access'] = implode(',', $roleAccess);
        $role = Role::create($insert);
        $insert = [];
        $insert['name'] = "代理角色";
        $insert['type'] = 3;
        $insert['merchant_id'] = $merchantId;
        $insert['access'] = implode(',', array_diff($roleAccess, \app\shop\enum\Merchant::DONT_COPY_TO_STAFF_ACCESS));
        Role::create($insert);
        $insert = [];
        $insert['nickname'] = $name;
        $insert['uuid'] = $adminId;
        $insert['avatar'] = $logo;
        $insert['username'] = $username;
        $insert['merchant_id'] = $merchantId;
        $insert['role_id'] = $role->id;
        $insert['status'] = 1;
        $insert['salt'] = create_token();
        $insert['password'] = create_password($insert['salt'], $password);
        $insert['parent_id'] = '';
        $insert['path'] = $adminId;
        $insert['depth'] = 0;
        \app\shop\model\Admin::create($insert);
        return [1, $merchant];
    }

    public static function getRoleAccess($accessList)
    {
        $roleAccess = \app\shop\enum\Merchant::MENU_ACCESS;
        // if (!in_array('slot', $accessList)) {
        //     $roleAccess = array_diff($roleAccess, \app\shop\enum\Menu::ACCESS_SLOT);
        // }
        return $roleAccess;
    }

    public static function getAdminByUuid($id)
    {
        return \app\shop\model\Admin::get(['uuid' => $id]);
    }

    public static function getAllAdminId($shopId)
    {
        $list = \app\shop\model\Admin::where(['merchant_id' => $shopId])->select()->toArray();
        return array_column($list, 'id');
    }

    public static function saveInfo($update, $condition)
    {
        if (empty($update) || empty($condition)) {
            return false;
        }
        return \app\shop\model\Admin::update($update, $condition);
    }

    public static function getMyEmployee($roleType, $merchantId, $adminUuid)
    {
        $cond = [];
        if ($roleType == 2) {
            $cond['merchant_id'] = $merchantId;
            $admins = \app\shop\model\Admin::where($cond)->field('uuid')->select()->toArray();
        } else if ($roleType == 3) {
            $cond['merchant_id'] = $merchantId;
            $admins = \app\shop\model\Admin::where($cond)
                ->where(function ($query) use ($adminUuid) {
                    $query->where('path', 'like', $adminUuid . ':%')
                        ->whereOr('uuid', '=', $adminUuid);
                })
                ->field('uuid')->select()->toArray();
        } else if ($roleType == 1) {
            $admins = \app\shop\model\Admin::where($cond)->field('uuid')->select()->toArray();
        } else {
            $cond['uuid'] = $adminUuid;
            $admins = \app\shop\model\Admin::where($cond)->field('uuid')->select()->toArray();
        }
        return array_column($admins, 'uuid');
    }

    public static function getMyTeam($roleType, $merchantId, $adminUuid)
    {
        return array_unique(array_merge(self::getMyEmployee($roleType, $merchantId, $adminUuid), [$adminUuid]));
    }
}
