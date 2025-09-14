<?php

namespace app\common\helper;

use app\common\enum\RedisKey;
use app\common\model\EmailAutoAuth;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

use think\facade\Cache;
use think\facade\Log;

/**
 * Microsoft Graph API 辅助类
 * 用于处理 Microsoft Graph API 相关操作
 */
class MicrosoftGraph
{
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $accessToken;
    private $baseUrl = 'https://graph.microsoft.com/v1.0';
    private $redirectUri;

    public function __construct($clientId, $clientSecret, $tenantId, $email = null, $redirectUri = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tenantId = $tenantId;
        $this->redirectUri = $redirectUri ?: 'https://web3-api.doordash.group/api/microsoft/callback';
        if ($email) {
            $this->accessToken = $this->getAccessToken($email);
        }
    }


    public function getAccessToken($email = null)
    {
        $token = Cache::store('redis')->get(sprintf(RedisKey::MICROSOFT_USER_ACCESS_TOKEN, $email));
        if (empty($token)) {
            $tokenData = EmailAutoAuth::where('email', $email)->field("access_token,expires_at")->find();
            if ($tokenData) {
                $token = $tokenData['access_token'];
                $expiresAt = strtotime($tokenData['expires_at']);
                $expiresIn = $expiresAt - time();
                Cache::store('redis')->set(sprintf(RedisKey::MICROSOFT_USER_ACCESS_TOKEN, $email), $token, $expiresIn);
            }
        }
        return $token;
    }


    /**
     * 获取用户授权登录 URL
     * @param string $state 状态参数，用于防止 CSRF 攻击
     * @return string
     */
    public function getAuthorizationUrl($email)
    {
        if ($this->accessToken) {
            return [-1, '该邮箱已经授权过，无需再授权'];
        }
        $state = uniqid('auth_');
        $stateKey =  sprintf(RedisKey::MICROSOFT_LOGIN_STATE, $state);
        Cache::store('redis')->set($stateKey, $email, 600);
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => 'Mail.Read offline_access',
            'state' => $state,
            'response_mode' => 'query'
        ];

        $authUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query($params);

        return [1, $authUrl];
    }

    /**
     * 通过授权码获取访问令牌（用户授权模式）
     * @param string $authorizationCode 授权码
     * @param string $state 状态参数
     * @return array|false
     */
    public function getTokenByAuthorizationCode($authorizationCode, $state)
    {
        // 验证 state 参数
        if (empty($state) || empty($authorizationCode)) {
            return false;
        }
        $stateKey = sprintf(RedisKey::MICROSOFT_LOGIN_STATE, $state);
        $email = Cache::store('redis')->get($stateKey);
        if (!$email) {
            return false;
        }

        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authorizationCode,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => 'Mail.Send Mail.Read offline_access'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $tokenData = json_decode($response, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && !empty($tokenData) && isset($tokenData['access_token'])) {
            // 缓存访问令牌
            $cacheKey = sprintf(RedisKey::MICROSOFT_USER_ACCESS_TOKEN, $email);
            $expiresIn = isset($tokenData['expires_in']) ? $tokenData['expires_in'] - 300 : 3300; // 减去5分钟缓冲
            Cache::store('redis')->set($cacheKey, $tokenData['access_token'], $expiresIn);

            EmailAutoAuth::insert([
                'email' => $email,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 清理 state 缓存
            Cache::store('redis')->delete($stateKey);

            return $tokenData;
        } else {
            Log::error('Failed to get access token from authorization code', ['response' => $response]);
            return false;
        }
    }

    /**
     * 使用 refresh_token 刷新访问令牌
     * @param string $email 用户邮箱
     * @return array|false 返回新的token数据或false
     */
    public function refreshAccessToken($email)
    {
        // 从数据库获取 refresh_token
        $authData = EmailAutoAuth::where('email', $email)->field('refresh_token')->find();
        if (!$authData || empty($authData['refresh_token'])) {
            return false;
        }

        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $authData['refresh_token'],
            'grant_type' => 'refresh_token',
            'scope' => 'Mail.Send Mail.Read offline_access'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $tokenData = json_decode($response, true);
            if (isset($tokenData['access_token'])) {
                // 更新缓存中的访问令牌
                $cacheKey = sprintf(RedisKey::MICROSOFT_USER_ACCESS_TOKEN, $email);
                $expiresIn = isset($tokenData['expires_in']) ? $tokenData['expires_in'] - 300 : 3300; // 减去5分钟缓冲
                Cache::store('redis')->set($cacheKey, $tokenData['access_token'], $expiresIn);

                // 更新数据库中的token信息
                $updateData = [
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn)
                ];

                // 如果返回了新的 refresh_token，也要更新
                if (isset($tokenData['refresh_token'])) {
                    $updateData['refresh_token'] = $tokenData['refresh_token'];
                }

                EmailAutoAuth::where('email', $email)->update($updateData);

                return $tokenData;
            }
        }

        return false;
    }

    /**
     * 发送邮件
     * @param string $email 发送者邮箱
     * @param string|array $to 收件人邮箱地址，可以是单个邮箱字符串或邮箱数组
     * @param string $subject 邮件主题
     * @param string $body 邮件内容（HTML格式）
     * @return array 返回发送结果 [状态码, 消息]
     */
    public function sendEmail($email, $to, $subject, $body)
    {
        // 确保有访问令牌
        if (!$this->accessToken) {
            $this->accessToken = $this->getAccessToken($email);
        }

        if (!$this->accessToken) {
            // 尝试刷新令牌
            $refreshResult = $this->refreshAccessToken($email);
            if ($refreshResult && isset($refreshResult['access_token'])) {
                $this->accessToken = $refreshResult['access_token'];
            } else {
                return [-1, '无法获取访问令牌，请重新授权'];
            }
        }

        // 构建收件人数组
        $recipients = $this->buildRecipients($to);
        if (empty($recipients)) {
            return [-1, '收件人地址无效'];
        }

        // 构建邮件数据
        $messageData = [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $body
            ],
            'toRecipients' => $recipients
        ];
        $requestData = [
            'message' => $messageData,
            'saveToSentItems' => true
        ];

        // 发送邮件请求
        $url = $this->baseUrl . '/me/sendMail';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 202) {
            Log::info('Email sent successfully', ['email' => $email, 'to' => $to, 'subject' => $subject]);
            return [1, '邮件发送成功'];
        } else {
            $errorResponse = json_decode($response, true);
            $errorMessage = isset($errorResponse['error']['message']) ? $errorResponse['error']['message'] : '未知错误';
            Log::error('Failed to send email', [
                'email' => $email,
                'to' => $to,
                'subject' => $subject,
                'http_code' => $httpCode,
                'response' => $response
            ]);
            return [-1, '邮件发送失败: ' . $errorMessage];
        }
    }

    /**
     * 构建收件人数组
     * @param string|array $emails 邮箱地址
     * @return array
     */
    private function buildRecipients($emails)
    {
        if (is_string($emails)) {
            $emails = [$emails];
        }

        $recipients = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = [
                    'emailAddress' => [
                        'address' => $email
                    ]
                ];
            }
        }

        return $recipients;
    }
}
