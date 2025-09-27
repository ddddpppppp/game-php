<?php

namespace app\api\controller;

use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\Usdt;
use app\common\controller\Controller;
use app\common\enum\Bot;
use app\common\enum\RedisKey;
use app\common\helper\ArrayHelper;
use app\common\helper\MicrosoftGraph;
use app\common\helper\TgHelper;
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

    public function botAutoBet()
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
        $startDelayTime = strtotime($currentDraw['start_at']) + 15; // 开始后15秒
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
        list($betType, $amount) = $this->selectBetTypeAndAmount($existingBets);

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

    /**
     * 根据已有投注选择合适的投注类型和金额，避免矛盾投注
     * @param array $existingBets 用户已有的投注类型
     * @return array [betType, amount]
     */
    private function selectBetTypeAndAmount($existingBets = [])
    {
        // 定义投注类型分类
        $betCategories = [
            // 大小单双类 - 70% 概率，金额10-1000（10的倍数）
            'basic' => [
                'probability' => 70,
                'types' => ['high', 'low', 'odd', 'even'],
                'min_amount' => 10,
                'max_amount' => 1000,
                'multiple' => 10
            ],
            // 组合玩法类 - 20% 概率，金额5-500（5的倍数）
            'combination' => [
                'probability' => 20,
                'types' => ['high_odd', 'low_odd', 'high_even', 'low_even'],
                'min_amount' => 5,
                'max_amount' => 500,
                'multiple' => 5
            ],
            // 特殊玩法类 - 7% 概率，金额1-200随机
            'special' => [
                'probability' => 77,
                'types' => ['triple', 'pair', 'straight', 'extreme_low', 'extreme_high'],
                'min_amount' => 1,
                'max_amount' => 200,
                'multiple' => 1
            ],
            // 数字玩法类 - 3% 概率，金额0.5-50
            'number' => [
                'probability' => 3,
                'types' => ['sum_0', 'sum_1', 'sum_2', 'sum_3', 'sum_4', 'sum_5', 'sum_6', 'sum_7', 'sum_8', 'sum_9', 'sum_10', 'sum_11', 'sum_12', 'sum_13', 'sum_14', 'sum_15', 'sum_16', 'sum_17', 'sum_18', 'sum_19', 'sum_20', 'sum_21', 'sum_22', 'sum_23', 'sum_24', 'sum_25', 'sum_26', 'sum_27'],
                'min_amount' => 0.5,
                'max_amount' => 50,
                'multiple' => 0.5
            ]
        ];
        // 定义矛盾投注规则
        $conflictRules = [
            'high' => ['low'], // 大和小矛盾
            'low' => ['high'], // 小和大矛盾
            'odd' => ['even'], // 单和双矛盾
            'even' => ['odd'], // 双和单矛盾
            'high_odd' => ['low', 'even', 'low_even', 'high_even'], // 大单和小、双、小双、大双矛盾
            'low_odd' => ['high', 'even', 'high_even', 'low_even'], // 小单和大、双、大双、小双矛盾
            'high_even' => ['low', 'odd', 'low_odd', 'high_odd'], // 大双和小、单、小单、大单矛盾
            'low_even' => ['high', 'odd', 'high_odd', 'low_odd'], // 小双和大、单、大单、小单矛盾
            'extreme_low' => ['high', 'extreme_high'], // 极小和大、极大矛盾
            'extreme_high' => ['low', 'extreme_low'], // 极大和小、极小矛盾
        ];

        // 按概率选择投注分类
        $rand = mt_rand(1, 100);
        $selectedCategory = null;
        $cumulative = 0;

        foreach ($betCategories as $category => $config) {
            $cumulative += $config['probability'];
            if ($rand <= $cumulative) {
                $selectedCategory = $category;
                break;
            }
        }

        if (!$selectedCategory) {
            return [null, 0];
        }

        $categoryConfig = $betCategories[$selectedCategory];

        // 过滤掉矛盾的投注类型
        $availableTypes = $categoryConfig['types'];
        foreach ($existingBets as $existingBetType) {
            if (isset($conflictRules[$existingBetType])) {
                $conflicts = $conflictRules[$existingBetType];
                $availableTypes = array_diff($availableTypes, $conflicts);
            }
        }

        // 移除已经投注过的类型（避免重复投注）
        $availableTypes = array_diff($availableTypes, $existingBets);

        if (empty($availableTypes)) {
            return [null, 0];
        }

        // 随机选择一个可用的投注类型
        $selectedTypeKey = $availableTypes[array_rand($availableTypes)];

        // 从数据库获取投注类型信息
        $betType = Canada28BetTypes::where('type_key', $selectedTypeKey)
            ->where('status', 1)
            ->find();

        if (!$betType) {
            return [null, 0];
        }

        // 生成对应的金额
        $amount = $this->generateAmount(
            $categoryConfig['min_amount'],
            $categoryConfig['max_amount'],
            $categoryConfig['multiple']
        );

        return [$betType, $amount];
    }

    /**
     * 根据范围和倍数生成随机金额
     * @param float $min 最小金额
     * @param float $max 最大金额
     * @param float $multiple 倍数
     * @return float
     */
    private function generateAmount($min, $max, $multiple)
    {
        $minSteps = (int)($min / $multiple);
        $maxSteps = (int)($max / $multiple);
        $randomSteps = mt_rand($minSteps, $maxSteps);
        return $randomSteps * $multiple;
    }
}
