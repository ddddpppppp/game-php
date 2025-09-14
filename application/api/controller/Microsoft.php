<?php

namespace app\api\controller;

use app\api\enum\Imap;
use app\common\controller\Controller;
use app\common\enum\RedisKey;
use app\common\helper\MicrosoftGraph;
use app\common\helper\PaypalPay;
use app\common\model\PaymentOrder;
use app\common\service\TakeoutOrder as ServiceTakeoutOrder;
use app\takeout\model\TakeoutOrder;
use think\facade\Cache;
use think\facade\Log;

class Microsoft extends Controller
{
    protected $params = [];
    protected $data = [];

    /**
     * Initialize method for Takeout App
     */
    public function initialize()
    {
        $this->params = request()->get();
        $this->data = request()->param();
    }


    /**
     * 获取用户授权 URL
     */
    public function authorize()
    {
        $email = trim(request()->get('email'));
        if (empty($email)) {
            return $this->error('邮箱不能为空');
        }
        $microsoftGraph = new MicrosoftGraph(Imap::MICROSOFT_MAIN_CLIENT_ID, Imap::MICROSOFT_MAIN_CLIENT_SECRET, Imap::MICROSOFT_MAIN_TENANT_ID, $email);
        list($code, $authUrl) = $microsoftGraph->getAuthorizationUrl($email);
        if ($code == 1) {
            // 重定向到 Microsoft 登录页面
            return redirect($authUrl);
        } else {
            return $this->error($authUrl);
        }
    }

    /**
     * 处理 Microsoft OAuth 回调
     */
    public function callback()
    {
        $code = request()->get('code');
        $state = request()->get('state');
        $error = request()->get('error');

        if ($error) {
            Log::error('Microsoft OAuth Error:', ['error' => $error, 'error_description' => request()->get('error_description')]);
            return $this->error('授权失败');
        }

        if (!$code || !$state) {
            return $this->error('授权失败，code或state为空');
        }

        $clientId = Imap::MICROSOFT_MAIN_CLIENT_ID;
        $clientSecret = Imap::MICROSOFT_MAIN_CLIENT_SECRET;
        $tenantId = Imap::MICROSOFT_MAIN_TENANT_ID;

        $microsoftGraph = new MicrosoftGraph($clientId, $clientSecret, $tenantId);
        $tokenData = $microsoftGraph->getTokenByAuthorizationCode($code, $state);

        if ($tokenData) {
            echo '授权成功';
        } else {
            echo '授权失败';
        }
    }
}
