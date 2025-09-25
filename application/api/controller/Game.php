<?php

namespace app\api\controller;

use app\api\enum\Canada28;
use app\api\enum\Order;
use app\api\enum\Imap;
use app\api\service\User;
use app\common\controller\Controller;
use app\common\enum\Bot;
use app\common\helper\ArrayHelper;
use app\common\helper\MicrosoftGraph;
use app\common\helper\TgHelper;
use app\common\model\EmailAutoAuth;
use app\common\model\PaymentChannel;
use app\common\model\Users;
use app\common\model\Canada28BetTypes;
use app\common\model\Canada28Draws;
use app\common\model\Canada28Bets;
use app\common\service\Email;
use app\common\service\UserBalance;
use think\Db;
use think\facade\Cache;

class Game extends Controller
{


    protected $params = [];
    /**
     * @var Users
     */
    protected $user;

    public function initialize()
    {
        $this->params = request()->param();
        $token = request()->header('Authorization') ?: request()->header('Token');
        if (in_array(request()->action(), ['getcanada28game', 'getcanada28messages', 'getcanada28gamecurrentdraw', 'getcanada28drawhistory', 'getcanada28bethistory']) && empty($token)) {
            return;
        }
        if (empty($token)) {
            return $this->error('Token required', 401);
        }

        $userId = User::getUserIdByToken($token);

        if (!$userId) {
            return $this->error('Invalid token', 401);
        }

        $user = Users::where('uuid', $userId)->where('status', 1)->find();

        if (!$user) {
            return $this->error('User not found', 401);
        }

        $this->user = $user;
    }

    public function getCanada28GameCurrentDraw()
    {
        // 获取当前期数信息
        $currentDraw = Canada28Draws::getCurrentDraw();
        return $this->success([
            'current_draw' => $currentDraw
        ]);
    }

    /**
     * 获取Canada28游戏数据
     * 包括玩法配置和当前期数信息
     */
    public function getCanada28Game()
    {
        try {
            // 获取所有玩法配置
            $betTypes = Canada28BetTypes::where('merchant_id', $this->user['merchant_id'])
                ->order("sort asc")
                ->select();

            // 转换为数组格式并按分类分组
            $betTypesArray = [];
            foreach ($betTypes as $betType) {
                $betTypesArray[] = [
                    'id' => $betType['id'],
                    'type_key' => $betType['type_key'],
                    'type_name' => $betType['type_name'],
                    'description' => $betType['description'],
                    'odds' => floatval($betType['odds']),
                    'status' => intval($betType['status']),
                    'sort' => intval($betType['sort']),
                    'enabled' => $betType['status'] == 1
                ];
            }

            // 获取当前期数信息
            $currentDraw = Canada28Draws::getCurrentDraw();

            if (!$currentDraw) {
                return $this->error('something went wrong');
            }

            // 格式化当前期数据
            $drawData = [
                'period_number' => $currentDraw['period_number'],
                'status' => intval($currentDraw['status']),
                'status_text' => Canada28Draws::getStatusText($currentDraw['status']),
                'start_at' => $currentDraw['start_at'],
                'end_at' => $currentDraw['end_at'],
                'draw_at' => $currentDraw['draw_at'],
                'result_numbers' => $currentDraw['result_numbers'],
                'result_sum' => $currentDraw['result_sum'],
                'time_left' => max(0, strtotime($currentDraw['end_at']) - time()) // 剩余秒数
            ];

            // 获取动态赔率规则
            $dynamicOddsRules = Db::table('game_canada28_dynamic_odds')
                ->where('merchant_id', $this->user['merchant_id'])
                ->where('status', 1)
                ->order('priority desc')
                ->select();

            // 转换动态赔率规则为数组格式
            $dynamicOddsArray = [];
            foreach ($dynamicOddsRules as $rule) {
                $dynamicOddsArray[] = [
                    'id' => $rule['id'],
                    'rule_name' => $rule['rule_name'],
                    'trigger_condition' => $rule['trigger_condition'],
                    'trigger_values' => json_decode($rule['trigger_values'], true),
                    'bet_type_adjustments' => json_decode($rule['bet_type_adjustments'], true),
                    'status' => intval($rule['status']),
                    'priority' => intval($rule['priority'])
                ];
            }
        } catch (\Exception $e) {
            return $this->error('fetch game data error: ' . $e->getMessage(), 500);
        }
        return $this->success([
            'bet_types' => $betTypesArray,
            'current_draw' => $drawData,
            'dynamic_odds_rules' => $dynamicOddsArray
        ]);
    }

    /**
     * 获取Canada28群组消息
     * 获取最近50条消息
     */
    public function getCanada28Messages()
    {
        try {
            // Canada28游戏群组ID (可以根据实际需求配置)
            $groupId = 'canada28';

            // 获取最近50条消息
            $messages = Db::table('game_group_message')
                ->where('group_id', $groupId)
                ->where('deleted_at', null)
                ->order('created_at desc')
                ->limit(50)
                ->select();

            // 反转数组，让最新消息在后面
            $messages = array_reverse($messages);

            $messageArray = [];
            // 用户消息，获取用户信息
            $userList = Users::where('uuid', 'in', array_column($messages, 'user_id'))->field('uuid,nickname,avatar')->select()->toArray();
            $userMap = ArrayHelper::setKey($userList, 'uuid');
            foreach ($messages as $message) {
                // 根据user_id判断消息类型和显示方式
                if ($message['user_id'] === 'bot') {
                    // 机器人消息
                    $messageData = [
                        'id' => (string)$message['id'],
                        'nickname' => 'Bot',
                        'avatar' => '', // 前端会使用机器人图标
                        'user_id' => 'bot',
                        'type' => $message['type'],
                        'message' => $message['message'],
                        'created_at' => $message['created_at']
                    ];
                } else {
                    $messageData = [
                        'id' => (string)$message['id'],
                        'nickname' => $userMap[$message['user_id']]['nickname'] ?? 'User',
                        'avatar' => $userMap[$message['user_id']]['avatar'] ?? '',
                        'user_id' => $message['user_id'],
                        'type' => $message['type'],
                        'message' => $message['message'],
                        'created_at' => $message['created_at']
                    ];
                }

                $messageArray[] = $messageData;
            }
            if (empty($this->user)) {
                $messageArray[] = [
                    'id' => '',
                    'nickname' => 'Bot',
                    'avatar' => '',
                    'user_id' => 'bot',
                    'type' => 'text',
                    'message' => 'Welcome to Keno! New users will receive a free $20 upon sign up, with a maximum win of 500x bonus!',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        } catch (\Exception $e) {
            return $this->error('fetch messages error: ' . $e->getMessage(), 500);
        }
        return $this->success([
            'messages' => $messageArray,
            'total' => count($messageArray)
        ]);
    }

    /**
     * 获取Canada28开奖历史
     * 分页获取已开奖的期数记录
     */
    public function getCanada28DrawHistory()
    {
        try {
            $page = intval($this->params['page'] ?? 1);
            $limit = 10; // 每页10条
            $offset = ($page - 1) * $limit;

            // 获取已开奖的期数记录
            $draws = Canada28Draws::where('status', 'in', [Canada28Draws::STATUS_DRAWN, Canada28Draws::STATUS_SETTLED])
                ->where('result_numbers', '<>', null)
                ->order('period_number desc')
                ->limit($offset, $limit)
                ->select();

            $drawArray = [];
            foreach ($draws as $draw) {
                $drawArray[] = [
                    'id' => $draw['id'],
                    'period_number' => $draw['period_number'],
                    'result_numbers' => $draw['result_numbers'],
                    'result_sum' => intval($draw['result_sum']),
                    'draw_at' => $draw['draw_at'],
                    'status' => intval($draw['status']),
                    'status_text' => Canada28Draws::getStatusText($draw['status'])
                ];
            }

            // 检查是否还有更多数据
            $totalCount = Canada28Draws::where('status', 'in', [Canada28Draws::STATUS_DRAWN, Canada28Draws::STATUS_SETTLED])
                ->where('result_numbers', '<>', null)
                ->count();

            $hasMore = ($offset + $limit) < $totalCount;
        } catch (\Exception $e) {
            return $this->error('fetch draw history error: ' . $e->getMessage(), 500);
        }
        return $this->success([
            'draws' => $drawArray,
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'has_more' => $hasMore
        ]);
    }

    /**
     * 获取Canada28投注历史
     * 分页获取用户的投注记录
     */
    public function getCanada28BetHistory()
    {
        try {
            $page = intval($this->params['page'] ?? 1);
            $limit = 10; // 每页10条
            $offset = ($page - 1) * $limit;

            // 获取用户的投注记录
            $bets = Canada28Bets::where('user_id', $this->user['uuid'])
                ->order('created_at desc')
                ->limit($offset, $limit)
                ->select()
                ->toArray();

            $betArray = [];

            $drawList = Canada28Draws::where('period_number', 'in', array_column($bets, 'period_number'))
                ->field('period_number,status,result_numbers,result_sum,draw_at')
                ->select()
                ->toArray();
            $drawMap = ArrayHelper::setKey($drawList, 'period_number');

            foreach ($bets as $bet) {
                // 获取投注类型信息 (从bet_type和bet_name字段获取)
                $betTypeName = $bet['bet_name'];
                $betTypeKey = $bet['bet_type'];

                // 获取期号信息
                $draw = $drawMap[$bet['period_number']];

                $betArray[] = [
                    'id' => $bet['id'],
                    'period_number' => $bet['period_number'],
                    'bet_type_name' => $betTypeName,
                    'bet_type_key' => $betTypeKey,
                    'amount' => floatval($bet['amount']),
                    'multiplier' => floatval($bet['multiplier']),
                    'potential_win' => floatval($bet['amount']) * floatval($bet['multiplier']),
                    'status' => $bet['status'],
                    'status_text' => $bet['status'],
                    'result_numbers' => $draw['result_numbers'] ?? null,
                    'result_sum' => $draw['result_sum'] ? intval($draw['result_sum']) : null,
                    'draw_status' => $draw['status'] ?? null,
                    'draw_at' => $draw['draw_at'] ?? null,
                    'created_at' => $bet['created_at']
                ];
            }

            // 检查是否还有更多数据
            $totalCount = Canada28Bets::where('user_id', $this->user['uuid'])
                ->where('merchant_id', $this->user['merchant_id'])
                ->count();

            $hasMore = ($offset + $limit) < $totalCount;
        } catch (\Exception $e) {
            return $this->error('fetch bet history error: ' . $e->getMessage(), 500);
        }
        return $this->success([
            'bets' => $betArray,
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'has_more' => $hasMore
        ]);
    }

    /**
     * Canada28投注
     * 使用Redis锁防止重复提交
     */
    public function placeCanada28Bet()
    {
        // Redis锁的key
        $lockKey = sprintf(Canada28::BET_LOCK_KEY, $this->user['uuid']);
        $lockExpire = 3; // 锁定3秒

        // 尝试获取Redis锁
        if (!Cache::store('redis')->handler()->set($lockKey, 1, ['NX', 'EX' => $lockExpire])) {
            return $this->error('please do not submit repeatedly, please try again later');
        }

        try {
            // 获取参数
            $betTypeId = intval($this->params['bet_type_id'] ?? 0);
            $amount = floatval($this->params['amount'] ?? 0);

            // 参数验证
            if ($betTypeId <= 0) {
                throw new \Exception('please select bet type');
            }

            if ($amount <= 0) {
                throw new \Exception('bet amount must be greater than 0');
            }

            // 获取投注类型配置
            $betType = Canada28BetTypes::where('id', $betTypeId)
                ->field('id,type_key,type_name,odds,status')
                ->where('merchant_id', $this->user['merchant_id'])
                ->where('status', 1)
                ->find();

            if (!$betType) {
                throw new \Exception('bet type not found or disabled');
            }

            // 查找当前可投注的期数（status=0）
            $currentDraw = Canada28Draws::where('status', Canada28Draws::STATUS_WAITING)
                ->field('id,period_number,status,end_at')
                ->where('end_at', '>', date('Y-m-d H:i:s', time() + 30))
                ->order('period_number desc')
                ->find();

            if (!$currentDraw) {
                throw new \Exception('no available bet period');
            }

            // 检查是否在开奖前30秒内（锁定投注）
            $lockTime = strtotime($currentDraw['end_at']) - 30; // 开奖前30秒
            if (time() >= $lockTime) {
                throw new \Exception('betting is closed 30 seconds before the draw');
            }

            // 检查用户余额
            if ($this->user['balance'] < $amount) {
                throw new \Exception('insufficient balance');
            }

            Db::startTrans();
            try {
                // 扣除用户余额
                UserBalance::subUserBalance(
                    $this->user['id'],
                    $amount,
                    'game_bet',
                    'Canada28 bet - ' . $betType['type_name'] . ' - period number:' . $currentDraw['period_number'],
                    $currentDraw['period_number']
                );

                // 创建投注记录
                $bet = Canada28Bets::createBet([
                    'merchant_id' => $this->user['merchant_id'],
                    'user_id' => $this->user['uuid'],
                    'period_number' => $currentDraw['period_number'],
                    'bet_type' => $betType['type_key'],
                    'bet_name' => $betType['type_name'],
                    'amount' => $amount,
                    'multiplier' => $betType['odds'],
                    'status' => 'pending',
                    'ip' => request()->ip()
                ]);

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return $this->error('bet failed: ' . $e->getMessage());
        } finally {
            // 释放Redis锁
            Cache::store('redis')->handler()->del($lockKey);
        }
        // 发送信息
        postData("http://127.0.0.1:8000/v1/game_api/canada28/bet", ['bet_type' => $betType['type_name'], 'bet_amount' => $amount, 'user_id' => $this->user['uuid'], 'username' => $this->user['nickname'], 'avatar' => $this->user['avatar']]);
        // 返回投注成功信息
        return $this->success([
            'bet_id' => $bet['id'],
            'period_number' => $currentDraw['period_number'],
            'bet_type' => $betType['type_name'],
            'amount' => $amount,
            'multiplier' => $betType['odds'],
            'potential_win' => $amount * $betType['odds'],
            'message' => 'bet success! period number: ' . $currentDraw['period_number']
        ]);
    }
}
