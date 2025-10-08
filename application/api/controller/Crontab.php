<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\Game;
use app\api\service\Usdt;
use app\common\controller\Controller;
use app\common\enum\Bot;
use app\common\enum\RedisKey;
use app\common\helper\ArrayHelper;
use app\common\helper\MicrosoftGraph;
use app\common\helper\TgHelper;
use app\common\model\Bingo28Bets;
use app\common\model\Bingo28Draws;
use app\common\model\Canada28Bets;
use app\common\model\Canada28BetTypes;
use app\common\model\Canada28Draws;
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
use app\common\service\UserBalance;
use think\Db;
use think\facade\Cache;
use think\facade\Log;

class Crontab extends Controller
{

    /**
     * 检查USDT充值状态，自动处理到账 - 每5s钟执行一次
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
            $lock = Cache::store('redis')->setNx(sprintf(RedisKey::PAY_PROCESSING, $deposit->order_no), '1');
            if (!$lock) {
                continue;
            }
            list($code, $message) = Usdt::checkUsdtPayment($deposit);
            Cache::store('redis')->del(sprintf(RedisKey::PAY_PROCESSING, $deposit->order_no));
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

    public function botAutoBetCanada28()
    {
        // 查找当前可投注的期数（status=0）
        $currentDraw = Canada28Draws::where('status', Canada28Draws::STATUS_WAITING)
            ->field('id,period_number,status,end_at,start_at')
            ->where('end_at', '>', date('Y-m-d H:i:s', time() + 30))
            ->order('period_number desc')
            ->find();

        if (!$currentDraw) {
            echo date('Y-m-d H:i:s') . ' - No available bet period' . PHP_EOL;
            return;
        }

        // 检查是否在开始后15秒内（延迟投注）
        $startDelayTime = strtotime($currentDraw['start_at']) + 30; // 开始后15秒
        if (time() < $startDelayTime) {
            echo date('Y-m-d H:i:s') . ' - Waiting 15 seconds after start time before betting' . PHP_EOL;
            return;
        }

        // 检查是否在开奖前30秒内（锁定投注）
        $lockTime = strtotime($currentDraw['end_at']) - 30; // 开奖前30秒
        if (time() >= $lockTime) {
            echo date('Y-m-d H:i:s') . ' - Betting is closed 30 seconds before the draw' . PHP_EOL;
            return;
        }
        $botUser = Users::where('type', 'bot')->orderRaw("rand()")->find();
        if (!$botUser) {
            echo date('Y-m-d H:i:s') . ' - No available bot user' . PHP_EOL;
            return;
        }

        // 获取用户在当前期数的已有投注，避免矛盾投注
        $existingBets = Canada28Bets::where('user_id', $botUser['uuid'])
            ->where('period_number', $currentDraw['period_number'])
            ->column('bet_type');

        // 选择投注类型和金额
        list($betType, $amount) = Game::selectBetTypeAndAmount($existingBets);

        if (!$betType) {
            echo date('Y-m-d H:i:s') . ' - No suitable bet type available for user ' . $botUser['username'] . PHP_EOL;
            return;
        }

        try {
            Db::startTrans();
            // 创建投注记录
            $bet = Canada28Bets::createBet([
                'merchant_id' => $botUser['merchant_id'],
                'user_id' => $botUser['uuid'],
                'period_number' => $currentDraw['period_number'],
                'bet_type' => $betType['type_key'] ?? '1',
                'bet_name' => $betType['type_name'],
                'amount' => $amount,
                'multiplier' => $betType['odds'],
                'status' => 'pending',
                'ip' => '127.0.0.1'
            ]);
            // 扣除用户余额
            UserBalance::subUserBalance(
                $botUser['id'],
                $amount,
                'game_bet',
                'Canada28 bet - ' . $betType['type_name'] . ' - period number:' . $currentDraw['period_number'],
                $bet->id
            );
            postData("http://127.0.0.1:8000/v1/game_api/canada28/bet", ['bet_type' => $betType['type_name'], 'bet_amount' => $amount, 'user_id' => $botUser['uuid'], 'username' => $botUser['nickname'], 'avatar' => $botUser['avatar']]);
            Db::commit();
            echo date('Y-m-d H:i:s') . ' - Bet placed successfully - ' . $botUser['username'] . ' - ' . $betType['type_name'] . ' (' . $amount . ') - period number:' . $currentDraw['period_number'] . PHP_EOL;
        } catch (\Exception $e) {
            Db::rollback();
            echo date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . PHP_EOL;
            return;
        }
    }


    public function botAutoBetBingo28()
    {
        // 查找当前可投注的期数（status=0）
        $currentDraw = Bingo28Draws::where('status', Bingo28Draws::STATUS_WAITING)
            ->field('id,period_number,status,end_at,start_at')
            ->where('end_at', '>', date('Y-m-d H:i:s', time() + 30))
            ->order('period_number desc')
            ->find();

        if (!$currentDraw) {
            echo date('Y-m-d H:i:s') . ' - No available bet period' . PHP_EOL;
            return;
        }

        // 检查是否在开始后15秒内（延迟投注）
        $startDelayTime = strtotime($currentDraw['start_at']) + 30; // 开始后15秒
        if (time() < $startDelayTime) {
            echo date('Y-m-d H:i:s') . ' - Waiting 15 seconds after start time before betting' . PHP_EOL;
            return;
        }

        // 检查是否在开奖前30秒内（锁定投注）
        $lockTime = strtotime($currentDraw['end_at']) - 30; // 开奖前30秒
        if (time() >= $lockTime) {
            echo date('Y-m-d H:i:s') . ' - Betting is closed 30 seconds before the draw' . PHP_EOL;
            return;
        }
        $botUser = Users::where('type', 'bot')->orderRaw("rand()")->find();
        if (!$botUser) {
            echo date('Y-m-d H:i:s') . ' - No available bot user' . PHP_EOL;
            return;
        }

        // 获取用户在当前期数的已有投注，避免矛盾投注
        $existingBets = Bingo28Bets::where('user_id', $botUser['uuid'])
            ->where('period_number', $currentDraw['period_number'])
            ->column('bet_type');

        // 选择投注类型和金额
        list($betType, $amount) = Game::selectBetTypeAndAmount($existingBets);

        if (!$betType) {
            echo date('Y-m-d H:i:s') . ' - No suitable bet type available for user ' . $botUser['username'] . PHP_EOL;
            return;
        }

        try {
            Db::startTrans();
            // 创建投注记录
            $bet = Bingo28Bets::createBet([
                'merchant_id' => $botUser['merchant_id'],
                'user_id' => $botUser['uuid'],
                'period_number' => $currentDraw['period_number'],
                'bet_type' => $betType['type_key'] ?? '1',
                'bet_name' => $betType['type_name'],
                'amount' => $amount,
                'multiplier' => $betType['odds'],
                'status' => 'pending',
                'ip' => '127.0.0.1'
            ]);
            // 扣除用户余额
            UserBalance::subUserBalance(
                $botUser['id'],
                $amount,
                'game_bet',
                'Bingo28 bet - ' . $betType['type_name'] . ' - period number:' . $currentDraw['period_number'],
                $bet->id
            );
            postData("http://127.0.0.1:8000/v1/game_api/bingo28/bet", ['bet_type' => $betType['type_name'], 'bet_amount' => $amount, 'user_id' => $botUser['uuid'], 'username' => $botUser['nickname'], 'avatar' => $botUser['avatar']]);
            Db::commit();
            echo date('Y-m-d H:i:s') . ' - Bet placed successfully - ' . $botUser['username'] . ' - ' . $betType['type_name'] . ' (' . $amount . ') - period number:' . $currentDraw['period_number'] . PHP_EOL;
        } catch (\Exception $e) {
            Db::rollback();
            echo date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . PHP_EOL;
            return;
        }
    }
}
