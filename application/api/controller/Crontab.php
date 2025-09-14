<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\Usdt;
use app\common\controller\Controller;
use app\common\enum\Bot;
use app\common\helper\ArrayHelper;
use app\common\helper\MicrosoftGraph;
use app\common\helper\TgHelper;
use app\common\model\EmailAutoAuth;
use app\common\model\PaymentChannel;
use app\common\model\Transactions;
use app\common\model\UserBalances;
use app\common\model\Users;
use app\common\model\MiningProducts;
use app\common\model\MiningSubscriptions;
use app\common\model\MiningProfits;
use app\common\model\MiningRedemptions;
use app\common\service\Email;
use think\Db;
use think\facade\Log;

class Crontab extends Controller
{

    /**
     * 检查USDT充值状态，自动处理到账 - 每分钟执行一次
     */
    public function checkDepositStatus()
    {
        $pendingDeposits = Transactions::where('type', 'deposit')
            ->where('status', 'pending')
            ->where('expired_at', '>', date('Y-m-d H:i:s'))
            ->select();

        if (empty($pendingDeposits)) {
            echo date('Y-m-d H:i:s') . ' - No pending deposit orders' . PHP_EOL;
            return;
        }

        $successCount = 0;
        foreach ($pendingDeposits as $deposit) {
            list($code, $message) = Usdt::checkUsdtPayment($deposit);
            if ($code == 1) {
                $successCount++;
                echo date('Y-m-d H:i:s') . " - Deposit completed: {$deposit->order_no}" . PHP_EOL;
            }
        }

        echo date('Y-m-d H:i:s') . " - Checked " . count($pendingDeposits) . " orders, {$successCount} completed" . PHP_EOL;
    }

    /**
     * 自动过期充值订单 - 每分钟执行一次
     */
    public function expireDepositOrders()
    {
        $count = Transactions::where('type', 'deposit')
            ->where('status', 'pending')
            ->where('expired_at', '<', date('Y-m-d H:i:s'))
            ->update(['status' => 'expired']);

        echo date('Y-m-d H:i:s') . " - Expired {$count} deposit orders" . PHP_EOL;
    }
}
