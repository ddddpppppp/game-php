<?php

namespace app\common\helper;

use think\facade\Log;

/**
 * tg 助手类
 */
class TgHelper
{


    /**
     * 发送消息到指定群组
     *
     * @param string $botToken 机器人的API令牌
     * @param string $chatId 群组ID或频道用户名(带@前缀)
     * @param string $message 要发送的消息内容
     * @param array $options 附加选项
     * @return array|false 请求结果或失败时返回false
     */
    public static function sendMessage($botToken, $chatId, $message, $options = [])
    {
        if (empty($botToken) || empty($chatId) || empty($message)) {
            return false;
        }
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // 构建请求数据
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => isset($options['parse_mode']) ? $options['parse_mode'] : 'HTML',
        ];

        // 添加可选参数
        if (isset($options['disable_web_page_preview'])) {
            $data['disable_web_page_preview'] = $options['disable_web_page_preview'];
        }

        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }

        if (isset($options['reply_to_message_id'])) {
            $data['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = $options['reply_markup'];
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 发送图片到指定群组
     *
     * @param string $botToken 机器人的API令牌
     * @param string $chatId 群组ID或频道用户名
     * @param string $photoUrl 图片URL或文件ID
     * @param string $caption 可选的图片说明
     * @param array $options 附加选项
     * @return array|false 请求结果或失败时返回false
     */
    public static function sendPhoto($botToken, $chatId, $photoUrl, $caption = '', $options = [])
    {
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendPhoto";

        // 构建请求数据
        $data = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
        ];

        if (!empty($caption)) {
            $data['caption'] = $caption;
            $data['parse_mode'] = isset($options['parse_mode']) ? $options['parse_mode'] : 'HTML';
        }

        // 添加可选参数
        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }

        if (isset($options['reply_to_message_id'])) {
            $data['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = $options['reply_markup'];
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 发送文件到指定群组
     *
     * @param string $botToken 机器人的API令牌
     * @param string $chatId 群组ID或频道用户名
     * @param string $fileUrl 文件URL或文件ID
     * @param string $caption 可选的文件说明
     * @param array $options 附加选项
     * @return array|false 请求结果或失败时返回false
     */
    public static function sendDocument($botToken, $chatId, $fileUrl, $caption = '', $options = [])
    {
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendDocument";

        // 构建请求数据
        $data = [
            'chat_id' => $chatId,
            'document' => $fileUrl,
        ];

        if (!empty($caption)) {
            $data['caption'] = $caption;
            $data['parse_mode'] = isset($options['parse_mode']) ? $options['parse_mode'] : 'HTML';
        }

        // 添加可选参数
        if (isset($options['disable_notification'])) {
            $data['disable_notification'] = $options['disable_notification'];
        }

        if (isset($options['reply_to_message_id'])) {
            $data['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = $options['reply_markup'];
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 发送HTTP请求到Telegram API
     *
     * @param string $url API端点URL
     * @param array $data 请求数据
     * @return array|false 响应数据或失败时返回false
     */
    private static function sendRequest($url, $data)
    {
        // 初始化curl
        $ch = curl_init();

        // 设置curl选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        // 执行请求
        $response = curl_exec($ch);
        $error = curl_error($ch);

        // 关闭curl
        curl_close($ch);

        // 检查是否有错误
        if ($error) {
            return false;
        }
        // 解析响应
        $responseData = json_decode($response, true);

        // 检查API响应
        if (isset($responseData['ok']) && $responseData['ok'] === true) {
            return $responseData;
        } else {
            // 记录错误
            if (isset($responseData['description'])) {
                // 可以在这里添加日志记录
                Log::error("Telegram API错误: " . json_encode($responseData));
            }
            return false;
        }
    }

    /**
     * 创建内联键盘标记
     *
     * @param array $buttons 按钮数组，格式: [['text' => '按钮1', 'url' => 'https://example.com'], ['text' => '按钮2', 'callback_data' => 'data']]
     * @param int $columnsPerRow 每行按钮数量
     * @return string JSON编码的内联键盘标记
     */
    public static function createInlineKeyboard($buttons, $columnsPerRow = 1)
    {
        $keyboard = [];
        $row = [];

        foreach ($buttons as $index => $button) {
            $row[] = $button;

            if (($index + 1) % $columnsPerRow === 0 || $index === count($buttons) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        return json_encode(['inline_keyboard' => $keyboard]);
    }

    /**
     * 设置 Webhook 接收更新
     *
     * @param string $botToken 机器人的API令牌
     * @param string $webhookUrl 接收更新的URL (必须使用HTTPS)
     * @param array $options 附加选项
     * @return array|false 请求结果或失败时返回false
     */
    public static function setWebhook($botToken, $webhookUrl, $options = [])
    {
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

        // 构建请求数据
        $data = [
            'url' => $webhookUrl,
        ];

        // 添加可选参数
        if (isset($options['certificate'])) {
            $data['certificate'] = $options['certificate']; // 公钥证书路径
        }

        if (isset($options['max_connections'])) {
            $data['max_connections'] = $options['max_connections']; // 最大连接数 (1-100)
        }

        if (isset($options['allowed_updates'])) {
            $data['allowed_updates'] = $options['allowed_updates']; // 允许的更新类型数组
        }

        if (isset($options['ip_address'])) {
            $data['ip_address'] = $options['ip_address']; // 固定IP地址
        }

        if (isset($options['drop_pending_updates'])) {
            $data['drop_pending_updates'] = $options['drop_pending_updates']; // 是否丢弃待处理的更新
        }

        if (isset($options['secret_token'])) {
            $data['secret_token'] = $options['secret_token']; // 用于验证请求的密钥
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 回应回调查询
     *
     * @param string $botToken 机器人的API令牌
     * @param string $callbackQueryId 回调查询ID
     * @param string $text 要显示的文本（可选）
     * @param bool $showAlert 是否显示为弹窗（可选）
     * @param int $cacheTime 缓存时间（可选）
     * @return array|false 请求结果或失败时返回false
     */
    public static function answerCallbackQuery($botToken, $callbackQueryId, $text = '', $showAlert = false, $cacheTime = 0)
    {
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";

        // 构建请求数据
        $data = [
            'callback_query_id' => $callbackQueryId
        ];

        if (!empty($text)) {
            $data['text'] = $text;
        }

        if ($showAlert) {
            $data['show_alert'] = true;
        }

        if ($cacheTime > 0) {
            $data['cache_time'] = $cacheTime;
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 编辑消息文本
     *
     * @param string $botToken 机器人的API令牌
     * @param string $chatId 聊天ID
     * @param int $messageId 消息ID
     * @param string $text 新的消息文本
     * @param array $options 附加选项
     * @return array|false 请求结果或失败时返回false
     */
    public static function editMessageText($botToken, $chatId, $messageId, $text, $options = [])
    {
        // 构建API URL
        $apiUrl = "https://api.telegram.org/bot{$botToken}/editMessageText";

        // 构建请求数据
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text
        ];

        // 添加可选参数
        foreach ($options as $key => $value) {
            $data[$key] = $value;
        }

        // 发送请求
        return self::sendRequest($apiUrl, $data);
    }

    /**
     * 编辑消息并移除键盘
     *
     * @param string $botToken 机器人的API令牌
     * @param string $chatId 聊天ID
     * @param int $messageId 消息ID
     * @param string $text 新消息文本
     * @param string $parseMode 解析模式 (HTML, Markdown)
     * @return array|false 请求结果或失败时返回false
     */
    public static function editMessageWithoutKeyboard($botToken, $chatId, $messageId, $text, $parseMode = 'HTML')
    {
        return self::editMessageText(
            $botToken,
            $chatId,
            $messageId,
            $text,
            [
                'parse_mode' => $parseMode,
                'reply_markup' => json_encode(['inline_keyboard' => []]) // 空键盘，移除按钮
            ]
        );
    }
}
