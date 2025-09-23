<?php

namespace app\api\service;

use app\common\model\Transactions;
use app\common\model\UserBalance;
use app\common\model\UserBalances;
use app\common\service\UserBalance as ServiceUserBalance;
use think\Db;
use think\facade\Log;

class Usdt
{

    // TronGrid API 配置
    const TRON_API_URL = 'https://api.trongrid.io';
    const USDT_CONTRACT_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    /**
     * 生成唯一的USDT金额(添加随机三位小数)
     */
    public static function generateUniqueUsdtAmount($baseAmount)
    {
        $maxAttempts = 100;
        $attempts = 0;

        do {
            // 生成随机三位小数 (0.0001 - 0.9999)
            $randomDecimal = rand(1, 9999) / 10000;
            $usdtAmount = $baseAmount + $randomDecimal;

            // 检查这个金额是否已经被使用(最近30分钟内)
            $exists = Transactions::where('actual_amount', $usdtAmount)
                ->where('type', 'deposit')
                ->where('status', 'pending')
                ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-30 minutes')))
                ->find();

            if (!$exists) {
                return round($usdtAmount, 4);
            }

            $attempts++;
        } while ($attempts < $maxAttempts);

        // 如果尝试次数过多，使用时间戳作为后缀
        $timeDecimal = (time() % 1000) / 1000;
        return round($baseAmount + $timeDecimal, 3);
    }



    /**
     * 检查USDT支付状态 - 使用TronGrid API
     */
    public static function checkUsdtPayment($deposit)
    {
        try {
            // 获取系统配置中的收款地址
            $config = Db::name('system_setting')
                ->where('name', 'usdt_recharge')
                ->where('status', 1)
                ->value('config');

            if (!$config) {
                return [0, '充值配置不存在'];
            }

            $rechargeConfig = json_decode($config, true);
            $receivingAddress = $rechargeConfig['address'] ?? '';

            if (empty($receivingAddress)) {
                return [0, '收款地址未配置'];
            }

            // 调用真实的区块链API检查交易
            list($found, $txHash, $actualAmount) = self::checkUsdtTransaction($receivingAddress, $deposit->usdt_amount, 1800);

            if ($found) {
                return ServiceUserBalance::completeDeposit($deposit);
            }

            return [0, '等待支付确认'];
        } catch (\Exception $e) {
            Log::error('检查充值状态失败: ' . $e->getMessage());
            return [0, '检查失败: ' . $e->getMessage()];
        }
    }

    /**
     * 使用TronGrid API检查USDT交易
     * @param string $address 收款地址
     * @param float $expectedAmount 期望金额
     * @param int $timeWindow 时间窗口(秒)
     * @return array [found, txHash, actualAmount]
     */
    public static function checkUsdtTransaction($address, $expectedAmount, $timeWindow = 1800)
    {
        try {
            // 获取当前时间戳(毫秒)
            $endTime = time() * 1000;
            $startTime = $endTime - ($timeWindow * 1000);

            // 构建API请求URL
            $url = self::TRON_API_URL . '/v1/accounts/' . $address . '/transactions/trc20?limit=200&contract_address=' . self::USDT_CONTRACT_ADDRESS;

            // 发送HTTP请求
            $response = self::sendRequest($url);

            if (!$response || !isset($response['data'])) {
                Log::warning('TronGrid API返回异常: ' . json_encode($response));
                return [false, '', 0];
            }

            // 检查交易记录
            foreach ($response['data'] as $transaction) {
                // 检查时间范围
                if ($transaction['block_timestamp'] < $startTime) {
                    continue;
                }

                // 检查是否是转入交易
                if ($transaction['to'] !== $address || $transaction['type'] !== 'Transfer') {
                    continue;
                }

                // 将USDT金额从最小单位转换为USDT(6位小数)
                $actualAmount = floatval($transaction['value']) / 1000000;

                // 检查金额是否匹配(允许0.0001的误差)
                if (abs($actualAmount - $expectedAmount) <= 0.0001) {
                    return [true, $transaction['transaction_id'], $actualAmount];
                }
            }

            return [false, '', 0];
        } catch (\Exception $e) {
            Log::error('检查USDT支付失败: ' . $e->getMessage());
            return [false, '', 0];
        }
    }

    /**
     * 发送HTTP请求
     */
    private static function sendRequest($url, $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Web3-Deposit-System/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('CURL请求失败: ' . $error);
            return false;
        }

        if ($httpCode !== 200) {
            Log::error('HTTP请求失败，状态码: ' . $httpCode);
            return false;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON解析失败: ' . json_last_error_msg());
            return false;
        }

        return $data;
    }
}
