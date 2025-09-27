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

    // Etherscan API 配置
    const ETHERSCAN_API_URL = 'https://api.etherscan.io/api';
    const USDT_CONTRACT_ADDRESS = '0xdAC17F958D2ee523a2206206994597C13D831ec7'; // 以太坊主网 USDT 合约地址
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

            // 调用 Etherscan API 检查交易
            list($found, $txHash, $actualAmount) = self::checkUsdtTransaction($receivingAddress, $deposit->usdt_amount, 1800);

            if ($found) {
                return ServiceUserBalance::completeDeposit($deposit);
            }

            return [0, '等待支付确认'];
        } catch (\Exception $e) {
            log_data('usdt', '检查充值状态失败: ' . $e->getMessage());
            return [0, '检查失败: ' . $e->getMessage()];
        }
    }

    /**
     * 使用Etherscan API检查USDT交易
     * @param string $address 收款地址
     * @param float $expectedAmount 期望金额
     * @param int $timeWindow 时间窗口(秒)
     * @return array [found, txHash, actualAmount]
     */
    public static function checkUsdtTransaction($address, $expectedAmount, $timeWindow = 1800)
    {
        try {
            // 获取当前时间和起始时间
            $endTime = time();
            $startTime = $endTime - $timeWindow;

            // 获取最新的区块号
            $latestBlock = self::getLatestBlockNumber();
            if (!$latestBlock) {
                log_data('usdt', '无法获取最新区块号', 'warning');
                return [false, '', 0];
            }

            // 估算起始区块号 (以太坊平均13秒一个块)
            $blocksBack = ceil($timeWindow / 13);
            $startBlock = max(1, $latestBlock - $blocksBack);

            // 构建API请求URL - 获取ERC20代币转账记录（使用免费API，无需key）
            $url = self::ETHERSCAN_API_URL . '?' . http_build_query([
                'module' => 'account',
                'action' => 'tokentx',
                'contractaddress' => self::USDT_CONTRACT_ADDRESS,
                'address' => $address,
                'startblock' => $startBlock,
                'endblock' => 'latest',
                'page' => 1,
                'offset' => 100, // 免费API限制更少的记录数
                'sort' => 'desc'
            ]);

            // 发送HTTP请求
            $response = self::sendRequest($url);

            if (!$response || $response['status'] !== '1' || !isset($response['result'])) {
                log_data('usdt', 'Etherscan API返回异常: ' . json_encode($response), 'warning');
                return [false, '', 0];
            }

            // 检查交易记录
            foreach ($response['result'] as $transaction) {
                // 检查时间范围
                $txTime = intval($transaction['timeStamp']);
                if ($txTime < $startTime) {
                    continue;
                }

                // 检查是否是转入交易
                if (strtolower($transaction['to']) !== strtolower($address)) {
                    continue;
                }

                // 将USDT金额从最小单位转换为USDT(6位小数)
                $actualAmount = floatval($transaction['value']) / 1000000;

                // 检查金额是否匹配(允许0.0001的误差)
                if (abs($actualAmount - $expectedAmount) <= 0.0001) {
                    return [true, $transaction['hash'], $actualAmount];
                }
            }

            return [false, '', 0];
        } catch (\Exception $e) {
            log_data('usdt', '检查USDT支付失败: ' . $e->getMessage(), 'error');
            return [false, '', 0];
        }
    }

    /**
     * 获取最新区块号
     */
    private static function getLatestBlockNumber()
    {
        try {
            $url = self::ETHERSCAN_API_URL . '?' . http_build_query([
                'module' => 'proxy',
                'action' => 'eth_blockNumber'
            ]);

            $response = self::sendRequest($url);

            if (!$response || !isset($response['result'])) {
                return false;
            }

            // 将十六进制转换为十进制
            return hexdec($response['result']);
        } catch (\Exception $e) {
            log_data('usdt', '获取最新区块号失败: ' . $e->getMessage(), 'error');
            return false;
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Ethereum-Deposit-System/1.0');
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
            log_data('usdt', 'CURL请求失败: ' . $error, 'error');
            return false;
        }

        if ($httpCode !== 200) {
            log_data('usdt', 'HTTP请求失败，状态码: ' . $httpCode, 'error');
            return false;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_data('usdt', 'JSON解析失败: ' . json_last_error_msg(), 'error');
            return false;
        }

        return $data;
    }
}
