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
use think\Db;
use think\facade\Cache;
use think\facade\Log;

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
            return $this->error('Token required', 401);
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
            // return $this->error('Please wait before re   questing another code');
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

        // Verify email code
        $cacheKey = sprintf(EnumUser::EMAIL_VERIFICATION_CODE_KEY, $email);
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $code) {
            return $this->error('Invalid or expired verification code');
        }

        // Check if email already exists
        if (Users::where('username', $email)->find()) {
            return $this->error('Email already registered');
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
            $user->avatar = '';
            $user->ip = $userIp;
            $user->device_code = $deviceCode;
            $user->save();

            $systemConfig = SystemSetting::where('name', 'new_user_gift')->field('config')->find();
            if ($systemConfig) {
                $gift = $systemConfig->config['gift_amount'];
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
                'avatar' => $user->avatar ?: ''
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
            // return $this->error('Invalid email or password');
        }

        // Generate token
        $token = ServiceUser::generateToken($user->uuid);

        return $this->success([
            'token' => $token,
            'user' => [
                'uuid' => $user->uuid,
                'email' => $user->username,
                'name' => $user->nickname,
                'avatar' => $user->avatar ?: '',
                'balance' => floatval(number_format($user->balance, 2, '.', '')),
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
        return $this->success([
            'user' => [
                'uuid' => $this->user->uuid,
                'email' => $this->user->username,
                'name' => $this->user->nickname,
                'avatar' => $this->user->avatar ?: '',
                'balance' => floatval(number_format($this->user->balance, 2, '.', '')),
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
        $minAmount = $rechargeConfig['min_amount'] ?? 10;
        $maxAmount = $rechargeConfig['max_amount'] ?? 10000;

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

            $dfpayHelper = new \app\common\helper\DfpayHelper($channel->params);
            list($code, $message, $payData) = $dfpayHelper->createOrder($orderNo, $amount);

            if ($code !== 1) {
                Db::rollback();
                return $this->error($message);
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
            $transaction->gift = $rechargeConfig['usdt_online_gift_rate'] ? $amount * $rechargeConfig['usdt_online_gift_rate'] / 100 : 0;
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

        // 检查余额
        if ($this->user->balance < $amount) {
            return $this->error('Insufficient balance');
        }

        // 检查今日提现次数
        $todayWithdrawCount = Transactions::where('user_id', $this->user->id)
            ->where('type', 'withdraw')
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->count();

        if ($todayWithdrawCount >= $dailyLimit) {
            return $this->error("Daily withdraw limit exceeded ({$dailyLimit} times per day)");
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
            $transaction->channel_id = $method;
            $transaction->amount = $amount;
            $transaction->actual_amount = $actualAmount;
            $transaction->account = $account;
            $transaction->order_no = $orderNo;
            $transaction->fee = $fee;
            $transaction->status = 'pending';
            $transaction->save();

            // 扣除余额
            UserBalance::subUserBalance($this->user->id, $amount, 'withdraw', "withdraw deducted, fee: {$fee}", $transaction->id);
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
}
