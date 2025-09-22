<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\Usdt;
use app\common\controller\Controller;
use think\Db;
use think\facade\Log;

class Notify extends Controller
{

    public function freePayNotify()
    {
        Log::info('freePay');
        Log::info(request()->param());
        Log::info(request()->post());
    }
}
