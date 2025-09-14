<?php


namespace app\shop\controller;


use app\common\controller\Controller;
use app\common\enum\Common;
use app\common\helper\ArrayHelper;
use app\common\utils\ParamsAesHelper;
use app\shop\enum\Menu;
use app\shop\model\TextTriggers;
use app\shop\service\AdminOperationLog;
use app\shop\service\Trigger;
use app\shop\service\Role;
use think\Env;
use think\facade\Log;

class Config extends Controller
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
        $this->admin = \app\shop\service\Admin::getAdmin();
        if (empty($this->admin)) {
            return $this->error(Common::NEED_LOGIN_MSG);
        }
        $this->role = Role::getRole($this->admin->role_id);
    }



}