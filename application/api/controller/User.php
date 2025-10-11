<?php

namespace app\api\controller;

use app\api\enum\User as EnumUser;
use app\api\enum\Imap;
use app\api\service\Usdt;
use app\api\service\User as ServiceUser;
use app\common\controller\Controller;
use app\common\helper\MicrosoftGraph;
use app\common\helper\ServerHelper;
use app\common\helper\TimeHelper;
use app\common\model\PaymentChannel;
use app\common\model\Users;
use app\common\model\UserAddresses;
use app\common\model\UserBalances;
use app\common\model\Transactions;
use app\common\service\Email;
use app\common\service\UserBalance;
use app\shop\enum\Admin;
use app\shop\model\SystemSetting;
use app\common\helper\EmailServer;
use app\common\model\WithdrawChannel;
use think\Db;
use think\facade\Cache;
use think\facade\Log;
use app\common\helper\TgHelper;
use app\common\enum\Bot as EnumBot;

class User extends Controller
{
    protected $params = [];
    /**
     * @var Users
     */
    protected $user;

    public function initialize()
    {
        $this->params = request()->param();
        if (in_array(request()->action(), ['sendverificationcode', 'register', 'login', 'resetpassword'])) {
            return;
        }
        $token = request()->header('Authorization') ?: request()->header('Token');
        if (empty($token)) {
            return $this->error('Please sign in', 401);
        }

        $userId = ServiceUser::getUserIdByToken($token);

        if (!$userId) {
            return $this->error('Invalid token', 401);
        }

        $user = Users::where('uuid', $userId)->where('status', 1)->find();

        if (!$user) {
            return $this->error('User not found', 401);
        }

        $this->user = $user;
    }

    /**
     * Send email verification code
     */
    public function sendVerificationCode()
    {
        $email = trim($this->params['email']);
        $type = trim($this->params['type']); // register, reset_password

        if (empty($email)) {
            return $this->error('Email is required', 0, 'Email is required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format');
        }

        // Check if email exists for different scenarios
        $userExists = Users::where('username', $email)->where('status', 1)->find();

        if ($type === 'register' && $userExists) {
            return $this->error('Email already registered');
        }

        if ($type === 'reset_password' && !$userExists) {
            return $this->error('Email not found');
        }

        // Check rate limiting (1 code per minute)
        $rateLimitKey = sprintf(EnumUser::EMAIL_RATE_LIMIT_KEY, $email);
        if (Cache::get($rateLimitKey)) {
            return $this->error('Please wait before requesting another code');
        }

        // Generate 6-digit verification code
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code in cache for 10 minutes
        $cacheKey = sprintf(EnumUser::EMAIL_VERIFICATION_CODE_KEY, $email);
        Cache::set($cacheKey, $code, 600);

        // Set rate limiting for 60 seconds
        Cache::set($rateLimitKey, true, 60);

        // Send verification code email
        try {
            // Load email configuration from config file
            $emailConfig = [
                'smtp_host'     => config('email.smtp_host'),
                'smtp_port'     => config('email.smtp_port'),
                'smtp_username' => config('email.smtp_username'),
                'smtp_password' => config('email.smtp_password'),
                'smtp_secure'   => config('email.smtp_secure'),
                'from_name'     => config('email.from_name'),
            ];
            $emailServer = new EmailServer($emailConfig);
            $result = $emailServer->sendVerificationCode($email, $code, $type);

            if (!$result) {
                throw new \Exception($emailServer->getError());
            }

            Log::info("Verification code sent to {$email}: {$code}");
        } catch (\Exception $e) {
            return $this->error('Failed to send verification code: ' . $e->getMessage());
        }
        return $this->success(['message' => 'Verification code sent successfully']);
    }

    /**
     * User registration
     */
    public function register()
    {
        $email = trim($this->params['email']);
        $password = trim($this->params['password']);
        $name = trim($this->params['name']);
        $code = trim($this->params['code']);
        $deviceCode = trim($this->params['device_code'] ?? '');
        $parentId = isset($this->params['parent_id']) ? (int)$this->params['parent_id'] : '';
        $userIp = request()->ip();

        if (empty($email) || empty($password) || empty($name) || empty($code)) {
            return $this->error('All fields are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email format');
        }

        if (strlen($password) < 8) {
            return $this->error('Password must be at least 8 characters');
        }

        $retryTimes = Cache::get(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()));
        if ($retryTimes >= 10) {
            return $this->error('Too many login attempts, please try again later');
        }

        // Verify email code
        $cacheKey = sprintf(EnumUser::EMAIL_VERIFICATION_CODE_KEY, $email);
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $code) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Invalid or expired verification code');
        }

        // Check if email already exists
        if (Users::where('username', $email)->count()) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Email already registered');
        }

        if (Users::where('device_code', $deviceCode)->count()) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Currently unable to sign up');
        }

        if (Users::where('ip', $userIp)->count()) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Currently unable to sign up');
        }


        try {
            Db::startTrans();

            // Create user
            $salt = create_token(); // Use create_token as salt generator
            $user = new Users();
            $user->username = $email;
            $user->uuid = create_uuid();
            $user->type = 'user';
            $user->password = create_password($salt, $password);
            $user->nickname = $name;
            $user->parent_id = $parentId;
            $user->merchant_id = Admin::DEFAULT_MERCHANT_ID;
            $user->status = 1;
            $user->salt = $salt;
            $user->avatar = 'https://keno28.us/keno_logo.png';
            $user->ip = $userIp;
            $user->balance = 0;
            $user->balance_frozen = 0;
            $user->device_code = $deviceCode;
            $user->save();

            $systemConfig = SystemSetting::where('name', 'new_user_gift')->field('config')->find();
            if ($systemConfig) {
                $gift = $systemConfig->config['gift_amount'];
                $user->balance = $gift;
                UserBalance::addUserBalance($user->id, $gift, 'gift', "Bonus for new user, gift amount: {$gift}", $user->id);
            }

            // Clear verification code
            Cache::rm($cacheKey);

            // Generate token
            $token = ServiceUser::generateToken($user->uuid);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('Registration failed: ' . $e->getMessage());
        }

        return $this->success([
            'token' => $token,
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->username,
                'name' => $user->nickname,
                'avatar' => $user->avatar ?: '',
                'balance' => floatval($user->balance),
                'balance_detail' => $this->getUserBalanceDetail($this->user)
            ]
        ]);
    }

    /**
     * User login
     */
    public function login()
    {
        $email = trim($this->params['email']);
        $password = trim($this->params['password']);
        $isApp = $this->params['isApp'] ? 1 : -1;
        if (empty($email) || empty($password)) {
            return $this->error('Email and password are required');
        }

        $retryTimes = Cache::get(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()));
        if ($retryTimes >= 10) {
            return $this->error('Too many login attempts, please try again later');
        }

        $user = Users::where('username', $email)->where('type', 'user')->where('status', 1)->find();

        if (!$user) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Invalid email or password');
        }

        if (!hash_equals($user->password, create_password($user->salt, $password))) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Invalid email or password');
        }

        // Generate token
        $token = ServiceUser::generateToken($user->uuid);
        Users::where('id', $user->id)->update(['is_app' => $isApp]);

        return $this->success([
            'token' => $token,
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->username,
                'name' => $user->nickname,
                'avatar' => $user->avatar ?: '',
                'balance' => floatval($user->balance),
                'balance_detail' => $this->getUserBalanceDetail($this->user)
            ]
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword()
    {
        $email = trim($this->params['email']);
        $code = trim($this->params['code']);
        $newPassword = trim($this->params['new_password']);

        if (empty($email) || empty($code) || empty($newPassword)) {
            return $this->error('All fields are required');
        }

        if (strlen($newPassword) < 8) {
            return $this->error('Password must be at least 8 characters');
        }

        $retryTimes = Cache::get(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()));
        if ($retryTimes >= 10) {
            return $this->error('Too many login attempts, please try again later');
        }

        // Verify email code
        $cacheKey = sprintf(EnumUser::EMAIL_VERIFICATION_CODE_KEY, $email);
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $code) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('Invalid or expired verification code');
        }

        $user = Users::where('username', $email)->where('status', 1)->find();

        if (!$user) {
            Cache::set(sprintf(EnumUser::USER_IP_LOCK_KEY, request()->ip()), $retryTimes + 1, 3600);
            return $this->error('User not found');
        }

        try {
            Db::startTrans();

            // Update password
            $salt = create_token(); // Use create_token as salt generator
            $user->password = create_password($salt, $newPassword);
            $user->salt = $salt;
            $user->save();

            // Clear verification code
            Cache::rm($cacheKey);

            Db::commit();

            return $this->success(['message' => 'Password reset successfully']);
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('Failed to reset password: ' . $e->getMessage());
        }
    }

    /**
     * Get user info by token
     */
    public function getUserInfo()
    {
        if ($this->user->status != 1) {
            return $this->error('User is not active');
        }

        // 获取余额详情
        $balanceDetail = $this->getUserBalanceDetail($this->user);

        return $this->success([
            'user' => [
                'uuid' => $this->user->uuid,
                'email' => $this->user->username,
                'name' => $this->user->nickname,
                'avatar' => $this->user->avatar ?: '',
                'balance' => floatval($this->user->balance),
                'balance_frozen' => floatval($this->user->balance_frozen),
                'balance_detail' => $balanceDetail,
                'join_date' => TimeHelper::convertFromUTC($this->user->created_at, 'Y'),
            ]
        ]);
    }

    /**
     * Edit user info by token
     */
    public function editUserInfo()
    {
        $avatar = $this->params['avatar'];
        $nickname = $this->params['nickname'];


        $this->user->avatar = $avatar ?: $this->user->avatar;
        $this->user->nickname = $nickname ?: $this->user->nickname;
        $this->user->save();


        return $this->success([
            'user' => [
                'uuid' => $this->user->uuid,
                'email' => $this->user->username,
                'name' => $this->user->nickname,
                'avatar' => $this->user->avatar ?: '',
            ]
        ]);
    }

    /**
     * Change password
     */
    public function changePassword()
    {
        $currentPassword = trim($this->params['current_password']);
        $newPassword = trim($this->params['new_password']);

        if (empty($currentPassword) || empty($newPassword)) {
            return $this->error('Current password and new password are required');
        }

        if (strlen($newPassword) < 8) {
            return $this->error('New password must be at least 8 characters');
        }


        // Verify current password
        if (!hash_equals($this->user->password, create_password($this->user->salt, $currentPassword))) {
            return $this->error('Current password is incorrect');
        }

        try {
            Db::startTrans();

            // Update password
            $salt = create_token();
            $this->user->password = create_password($salt, $newPassword);
            $this->user->salt = $salt;
            $this->user->save();

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('Failed to change password: ' . $e->getMessage());
        }
        return $this->success(['message' => 'Password changed successfully']);
    }

    /**
     * Logout user (placeholder - token invalidation could be implemented)
     */
    public function logout()
    {
        // In a full implementation, you would invalidate the token here
        // For now, just return success as token expiration is handled client-side
        return $this->success(['message' => 'Logged out successfully']);
    }

    /**
     * Get balance history
     */
    public function getBalanceHistory()
    {
        $page = (int)($this->params['page'] ?? 1);
        $limit = (int)($this->params['limit'] ?? 20);

        $query = UserBalances::where('user_id', $this->user->id);

        $list = $query->order('created_at', 'desc')
            ->paginate($limit, false, ['page' => $page]);

        $balanceList = [];
        foreach ($list->items() as $item) {
            $balanceList[] = [
                'id' => $item->id,
                'type' => str_replace('_', ' ', $item->type),
                'amount' => floatval($item->amount),
                'description' => $item->description,
                'created_at' => TimeHelper::convertFromUTC($item->created_at, 'Y-m-d H:i:s'),
            ];
        }

        return $this->success([
            'balance_list' => $balanceList,
            'total' => $list->total(),
            'current_page' => $list->currentPage(),
            'last_page' => $list->lastPage()
        ]);
    }

    /**
     * Get daily balance change
     */
    public function getDailyChange()
    {
        try {
            $today = TimeHelper::convertToUTC(date('Y-m-d H:i:s'));

            // 获取昨天结束时的余额（昨天最后一条记录的balance_after）
            $yesterdayEndBalance = UserBalances::where('user_id', $this->user->id)
                ->where('created_at', '<', $today)
                ->order('created_at', 'desc')
                ->value('balance_after');

            // 如果没有昨天的记录，使用当前余额作为基准
            if ($yesterdayEndBalance === null) {
                $yesterdayEndBalance = $this->user->balance;
            }

            // 获取今天的余额变化总和
            $todayChanges = UserBalances::where('user_id', $this->user->id)
                ->where('created_at', '>=', $today)
                ->sum('amount');

            // 今天的余额 = 昨天结束余额 + 今天变化
            $todayBalance = $yesterdayEndBalance + $todayChanges;

            // 计算变化金额和百分比
            $changeAmount = $todayChanges;
            $changePercent = $yesterdayEndBalance > 0 ?
                round(($changeAmount / $yesterdayEndBalance) * 100, 2) : 0;
        } catch (\Exception $e) {
            return $this->error('Failed to get daily change: ' . $e->getMessage());
        }
        return $this->success([
            'today_change' => floatval($changeAmount),
            'today_change_percent' => floatval($changePercent),
            'yesterday_balance' => floatval($yesterdayEndBalance),
            'today_balance' => floatval($todayBalance)
        ]);
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory()
    {
        $page = (int)($this->params['page'] ?? 1);
        $limit = (int)($this->params['limit'] ?? 20);
        $type = $this->params['type'] ?? ''; // deposit, withdrawal, all
        $status = $this->params['status'] ?? ''; // pending, completed, failed, rejected

        $query = Transactions::where('user_id', $this->user->id);

        if (!empty($type) && in_array($type, ['deposit', 'withdraw'])) {
            $query->where('type', $type);
        }

        if (!empty($status) && in_array($status, ['pending', 'completed', 'failed', 'expired'])) {
            $query->where('status', $status);
        }

        $transactions = $query->order('created_at', 'desc')
            ->paginate($limit, false, ['page' => $page]);

        $transactionList = [];
        foreach ($transactions->items() as $transaction) {
            $transactionList[] = [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => floatval(number_format($transaction->amount, 2, '.', '')),
                'gift' => floatval(number_format($transaction->gift, 2, '.', '')),
                'fee' => floatval(number_format($transaction->fee, 2, '.', '')),
                'remark' => $transaction->remark ?? '',
                'status' => $transaction->status,
                'created_at' => TimeHelper::convertFromUTC($transaction->created_at, 'Y-m-d H:i:s'),
            ];
        }

        return $this->success([
            'transactions' => $transactionList,
            'total' => $transactions->total(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage()
        ]);
    }

    /**
     * Create deposit transaction
     */
    public function createDeposit()
    {
        $amount = floatval($this->params['amount'] ?? 0);
        $method = $this->params['method'] ?? ''; // cashapp 或 usdt

        if ($amount <= 0) {
            return $this->error('Deposit amount must be greater than 0');
        }

        if (!in_array($method, ['cashapp', 'usdt', 'usdc_online'])) {
            return $this->error('Invalid payment method');
        }

        // 获取系统配置
        $config = Db::name('system_setting')
            ->where('name', 'recharge_setting')
            ->where('status', 1)
            ->value('config');

        if (!$config) {
            return $this->error('Deposit function is temporarily disabled');
        }

        $rechargeConfig = json_decode($config, true);
        if ($method === 'usdt') {
            $minAmount = $rechargeConfig['usdt_min_amount'] ?? 10;
            $maxAmount = $rechargeConfig['usdt_max_amount'] ?? 10000;
        } else if ($method === 'cashapp') {
            $minAmount = $rechargeConfig['cashapp_min_amount'] ?? 10;
            $maxAmount = $rechargeConfig['cashapp_max_amount'] ?? 10000;
        } else if ($method === 'usdc_online') {
            $minAmount = $rechargeConfig['usdc_online_min_amount'] ?? 10;
            $maxAmount = $rechargeConfig['usdc_online_max_amount'] ?? 10000;
        }

        if ($amount < $minAmount) {
            return $this->error("Minimum deposit amount is {$minAmount}");
        }

        if ($amount > $maxAmount) {
            return $this->error("Maximum deposit amount is {$maxAmount}");
        }


        // 生成订单号
        $orderNo = 'D' . date('YmdHis') . rand(1000, 9999);


        if ($method === 'cashapp') {
            // CashApp 支付 
            $channel = PaymentChannel::where('type', 'cashapp')
                ->where('status', 1)
                ->find();

            if (!$channel) {
                return $this->error('CashApp payment channel not available');
            }

            $dailyDepositAmount = Transactions::where('user_id', $this->user->id)
                ->where('type', 'deposit')
                ->where('status', 'completed')
                ->where('created_at', '>=', date('Y-m-d'))
                ->sum('amount');

            if ($dailyDepositAmount >= $maxAmount) {
                return $this->error('Cashapp daily deposit limit exceeded ({$maxAmount}), try switch to other method');
            }
            if ($channel->name == 'freepay-cashapp') {
                $freePayHelper = new \app\common\helper\FreePay($channel->params);
                list($code, $message, $payData) = $freePayHelper->freePayOrder($orderNo, $amount * 100, APP_ROOT, ServerHelper::getServerIp());
                if ($code !== 1) {
                    Db::rollback();
                    return $this->error($message);
                }
            } else if ($channel->name == 'dfpay-cashapp') {
                $dfpayHelper = new \app\common\helper\DfpayHelper($channel->params);
                list($code, $message, $payData) = $dfpayHelper->createOrder($orderNo, $amount * 100, APP_ROOT, ServerHelper::getServerIp());
                if ($code !== 1) {
                    Db::rollback();
                    return $this->error($message);
                }
            }

            // 创建充值记录
            $transaction = new Transactions();
            $transaction->user_id = $this->user->id;
            $transaction->type = 'deposit';
            $transaction->channel_id = $channel->id;
            $transaction->amount = $amount;
            $transaction->actual_amount = $amount;
            $transaction->account = '';
            $transaction->order_no = $orderNo;
            $transaction->fee = 0;
            $transaction->gift = $rechargeConfig['cashapp_gift_rate'] ? $amount * $rechargeConfig['cashapp_gift_rate'] / 100 : 0;
            $transaction->status = 'pending';
            $transaction->expired_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $transaction->save();


            return $this->success([
                'transaction_id' => $transaction->id,
                'order_no' => $orderNo,
                'payment_url' => $payData,
                'method' => 'cashapp',
                'amount' => $amount,
                'expired_at' => $transaction->expired_at
            ]);
        } else if ($method === 'usdt') {
            // USDT 支付 - 从payment_channel获取地址
            $channel = PaymentChannel::where('type', 'usdt')
                ->where('status', 1)
                ->find();

            if (!$channel || empty($channel->params['address'])) {
                return $this->error('USDT payment channel not available');
            }

            $usdtAmount = \app\api\service\Usdt::generateUniqueUsdtAmount($amount);

            $transaction = new Transactions();
            $transaction->user_id = $this->user->id;
            $transaction->type = 'deposit';
            $transaction->channel_id = $channel->id;
            $transaction->amount = $amount;
            $transaction->actual_amount = $usdtAmount;
            $transaction->account = $channel->params['address'];
            $transaction->order_no = $orderNo;
            $transaction->fee = 0;
            $transaction->gift = $rechargeConfig['usdt_gift_rate'] ? $amount * $rechargeConfig['usdt_gift_rate'] / 100 : 0;
            $transaction->status = 'pending';
            $transaction->expired_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $transaction->save();

            return $this->success([
                'transaction_id' => $transaction->id,
                'order_no' => $orderNo,
                'deposit_address' => $channel->params['address'],
                'method' => 'usdt',
                'amount' => $amount,
                'usdt_amount' => $usdtAmount,
                'expired_at' => $transaction->expired_at
            ]);
        } else if ($method === 'usdc_online') {
            // USDC 支付 - 从payment_channel获取地址
            $channel = PaymentChannel::where('type', 'usdc_online')
                ->where('status', 1)
                ->find();

            if (!$channel) {
                return $this->error('USDC payment channel not available');
            }

            $freePayHelper = new \app\common\helper\FreePay($channel->params);
            list($code, $message, $payData) = $freePayHelper->freePayOrder($orderNo, $amount * 100, url('/api/notify/payReturn', [], false, true), ServerHelper::getServerIp());

            if ($code !== 1) {
                Db::rollback();
                return $this->error($message);
            }
            $transaction = new Transactions();
            $transaction->user_id = $this->user->id;
            $transaction->type = 'deposit';
            $transaction->channel_id = $channel->id;
            $transaction->amount = $amount;
            $transaction->actual_amount = $amount;
            $transaction->account = '';
            $transaction->order_no = $orderNo;
            $transaction->fee = 0;
            $transaction->gift = $rechargeConfig['usdc_online_gift_rate'] ? $amount * $rechargeConfig['usdc_online_gift_rate'] / 100 : 0;
            $transaction->status = 'pending';
            $transaction->expired_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $transaction->save();

            return $this->success([
                'transaction_id' => $transaction->id,
                'order_no' => $orderNo,
                'deposit_address' => $channel->params['address'],
                'method' => 'usdc',
                'amount' => $amount,
                'payment_url' => $payData,
                'expired_at' => $transaction->expired_at
            ]);
        }
    }


    /**
     * 查询充值订单状态
     */
    public function getDepositStatus()
    {
        $orderNo = trim($this->params['order_no'] ?? '');

        if (empty($orderNo)) {
            return $this->error('Invalid order No');
        }

        $deposit = Transactions::where([
            'order_no' => $orderNo,
            'user_id' => $this->user->id,
            'type' => 'deposit'
        ])->find();

        if (!$deposit) {
            return $this->error('Deposit order not found');
        }

        $statusMap = [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'expired' => 'Expired'
        ];

        return $this->success([
            'order_no' => $deposit->order_no,
            'status' => $deposit->status,
            'status_text' => $statusMap[$deposit->status] ?? 'Unknown',
            'amount' => $deposit->amount,
            'actual_amount' => $deposit->actual_amount,
            'created_at' => $deposit->created_at,
            'expired_at' => $deposit->expired_at,
            'is_expired' => $deposit->expired_at && strtotime($deposit->expired_at) < time()
        ]);
    }

    /**
     * Create withdraw transaction
     */
    public function createWithdraw()
    {
        $amount = floatval($this->params['amount'] ?? 0);
        $method = $this->params['method'] ?? ''; // cashapp 或 usdt
        $account = $this->params['account'] ?? '';
        if ($amount <= 0) {
            return $this->error('Withdraw amount must be greater than 0');
        }

        if (!in_array($method, ['cashapp', 'usdt', 'usdc'])) {
            return $this->error('Invalid withdraw method');
        }

        if (empty($account)) {
            return $this->error('Withdraw information is required');
        }

        // 获取提现配置
        $config = Db::name('system_setting')
            ->where('name', 'withdraw_setting')
            ->where('status', 1)
            ->value('config');

        if (!$config) {
            return $this->error('Withdraw function is temporarily disabled');
        }

        $withdrawConfig = json_decode($config, true);
        $minAmount = $withdrawConfig['min_amount'] ?? 50;
        $maxAmount = $withdrawConfig['max_amount'] ?? 50000;
        $feeRate = $method === 'cashapp' ? $withdrawConfig['cashapp_fee_rate'] : $withdrawConfig['usdt_fee_rate'];
        $dailyLimit = $withdrawConfig['daily_limit'] ?? 3;

        if ($amount < $minAmount) {
            return $this->error("Minimum withdraw amount is {$minAmount}");
        }

        if ($amount > $maxAmount) {
            return $this->error("Maximum withdraw amount is {$maxAmount}");
        }

        // 获取用户余额详情
        $balanceDetail = $this->getUserBalanceDetail($this->user);

        // 检查余额 - 使用可提现余额而不是总余额
        if ($balanceDetail['withdrawable_balance'] < $amount) {
            $giftBalance = $balanceDetail['gift_balance'];
            $otherBalance = $balanceDetail['other_balance'];
            $totalBetAmount = $balanceDetail['total_bet_amount'];
            $requiredBetAmount = $balanceDetail['required_bet_amount'];

            if ($giftBalance > 0 && !$balanceDetail['gift_withdrawable']) {
                return $this->error("Insufficient withdrawable balance. You have {$giftBalance} in gift balance that requires {$requiredBetAmount} total bets (currently {$totalBetAmount}) to unlock for withdrawal.");
            } else {
                return $this->error('Insufficient withdrawable balance');
            }
        }

        // 检查今日提现次数
        $todayWithdrawCount = Transactions::where('user_id', $this->user->id)
            ->where('type', 'withdraw')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();

        if ($todayWithdrawCount >= $dailyLimit) {
            return $this->error("Daily withdraw limit exceeded ({$dailyLimit} times per day)");
        }
        $channel = WithdrawChannel::where('type', $method)
            ->where('status', 1)
            ->find();
        if (!$channel) {
            return $this->error('Withdraw channel not available');
        }

        // 计算手续费
        $fee = $amount * $feeRate / 100;
        $actualAmount = $amount - $fee;

        // 生成订单号
        $orderNo = 'W' . date('YmdHis') . rand(1000, 9999);

        try {
            Db::startTrans();
            // 创建提现记录
            $transaction = new Transactions();
            $transaction->user_id = $this->user->id;
            $transaction->type = 'withdraw';
            $transaction->channel_id = $channel->id;
            $transaction->amount = $amount;
            $transaction->actual_amount = $actualAmount;
            $transaction->account = $account;
            $transaction->order_no = $orderNo;
            $transaction->fee = $fee;
            $transaction->status = 'pending';
            $transaction->save();
            // 扣除余额
            UserBalance::subUserBalance($this->user->id, $amount, 'withdraw', "withdraw deducted, fee: {$fee}", $transaction->id);
            TgHelper::sendMessage(EnumBot::PAYMENT_BOT_TOKEN, EnumBot::FINANCE_CHAT_ID, sprintf("⚠️提现申请提醒\n💵金额: %s\n👤用户: %s", $amount, $this->user->username));
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('Failed to create withdraw order: ' . $e->getMessage());
        }
        return $this->success([
            'transaction_id' => $transaction->id,
            'order_no' => $orderNo,
            'amount' => $amount,
            'fee' => $fee,
            'actual_amount' => $actualAmount,
            'status' => 'pending',
            'created_at' => $transaction->created_at
        ]);
    }

    /**
     * 获取用户余额详情，区分gift余额和可提现余额
     */
    private function getUserBalanceDetail($user)
    {
        // 直接从users表获取gift余额（使用balance_frozen字段）
        $giftBalance = $user->balance_frozen;

        // 获取系统配置中的gift_transaction_times
        $config = Db::name('system_setting')
            ->where('name', 'withdraw_setting')
            ->where('status', 1)
            ->value('config');

        $withdrawConfig = json_decode($config, true);
        $giftTransactionTimes = $withdrawConfig['gift_transaction_times'] ?? 3;

        $totalBetAmount = 0;
        $requiredBetAmount = 0;
        $giftWithdrawable = true; // 如果没有gift余额，默认可提现

        if ($giftBalance > 0) {
            // 获取最近的一条gift记录
            $latestGiftRecord = Db::name('user_balances')
                ->field('amount, created_at')
                ->where('user_id', $user->id)
                ->where('type', 'gift')
                ->where('amount', '>', 0)
                ->order('created_at', 'desc')
                ->find();

            if ($latestGiftRecord) {
                // 计算自最近gift记录时间之后的投注金额
                $totalBetAmount = Db::name('canada28_bets')
                    ->where('user_id', $user->uuid)
                    ->where('created_at', '>=', $latestGiftRecord['created_at'])
                    ->sum('amount');

                // 基于最近gift记录的金额计算需要的投注金额
                $requiredBetAmount = $latestGiftRecord['amount'] * $giftTransactionTimes;

                // 判断是否满足提现条件
                $giftWithdrawable = $totalBetAmount >= $requiredBetAmount;
            }
        }

        // 可提现的gift余额（如果满足条件则为全部gift余额，否则为0）
        $withdrawableGiftBalance = $giftWithdrawable ? $giftBalance : 0;

        // 其他类型余额（总余额 - gift余额）
        $otherBalance = $user->balance - $giftBalance;

        // 总可提现余额
        $totalWithdrawableBalance = $otherBalance + $withdrawableGiftBalance;

        return [
            'total_balance' => floatval($user->balance), // 总余额
            'gift_balance' => floatval($giftBalance), // gift余额
            'other_balance' => floatval($otherBalance), // 其他余额
            'withdrawable_balance' => floatval($totalWithdrawableBalance), // 可提现余额
            'gift_withdrawable' => $giftWithdrawable, // gift是否可提现
            'total_bet_amount' => floatval($totalBetAmount), // 自最近gift记录以来的投注金额
            'required_bet_amount' => floatval($requiredBetAmount), // 需要的投注金额（基于最近gift记录）
            'gift_transaction_times' => $giftTransactionTimes, // 倍数要求
        ];
    }

    /**
     * Get customer service chat history
     */
    public function getChatHistory()
    {
        $page = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['limit']) ? intval($this->params['limit']) : 50;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 50;

        $offset = ($page - 1) * $limit;

        // Get messages for current user
        $messages = Db::table('game_customer_service_message')
            ->where('user_id', $this->user->uuid)
            ->whereNull('deleted_at')
            ->order('created_at DESC')
            ->limit($offset, $limit)
            ->select();

        // Get total count to determine if there are more
        $total = Db::table('game_customer_service_message')
            ->where('user_id', $this->user->uuid)
            ->whereNull('deleted_at')
            ->count();

        // Format messages and get admin info
        $formattedMessages = [];
        foreach ($messages as $message) {
            $adminName = 'Support Team';

            $formattedMessages[] = [
                'id' => $message['id'],
                'user_id' => $message['user_id'],
                'admin_id' => $message['admin_id'],
                'user_name' => empty($message['admin_id']) ? $this->user->nickname : $adminName,
                'message' => $message['message'],
                'type' => $message['type'],
                'is_read' => $message['is_read'],
                'is_admin' => !empty($message['admin_id']),
                'created_at' => $message['created_at'],
            ];
        }

        // Reverse to show oldest first
        $formattedMessages = array_reverse($formattedMessages);

        return $this->success([
            'messages' => $formattedMessages,
            'has_more' => ($offset + $limit) < $total,
            'total' => $total,
        ]);
    }

    /**
     * Mark messages as read (for user)
     */
    public function markMessagesAsRead()
    {
        // Mark all unread messages from admin as read
        Db::table('game_customer_service_message')
            ->where('user_id', $this->user->uuid)
            ->where('is_read', 0)
            ->whereNotNull('admin_id')
            ->update(['is_read' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        // Update session unread count
        Db::table('game_customer_service_session')
            ->where('user_id', $this->user->uuid)
            ->update(['unread_count' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->success('Messages marked as read');
    }
}
