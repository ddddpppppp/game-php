<?php

namespace app\common\service;

use app\common\enum\Bot;
use app\common\helper\TgHelper;
use app\common\helper\TimeHelper;
use app\common\model\PaymentChannel;
use think\facade\Log;

class Email
{
    public static function syncOrderFromEmail($email, $subject, $body, $from, $dateTime, $cashParams)
    {

        // 获取发件人
        Log::info('发件人: ' . $from);
        if ($from != 'cash@square.com') {
            return;
        }
        // 获取主题
        // 获取日期
        Log::info('主题: ' . $subject);
        if (strpos($subject, 'you paid') !== false || strpos($subject, 'you sent') !== false || strpos($subject, '无主题') !== false) {
            return;
        }
        // 获取邮件正文 - 优先使用HTML格式
        Log::info('正文: ' . $body);
        $amount = 0;
        $platformOrderNo = self::extractOrderNumber($body);
        $senderUsername = '';
        $status = '';
        $amountMatch = preg_match('/\$\s*((?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?)/', $body, $matches);
        if ($amountMatch) {
            // 移除可能的千位分隔符
            $amount = str_replace(',', '', trim($matches[1]));
        }
        if (strpos($subject, 'sent you') !== false) {
            // body 匹配 Payment from是老版本 匹配 Payment between 是新版本
            if (strpos($body, 'Payment from') !== false) {
                // 老版本
                $senderUsernameMatch = preg_match('/Payment from\s+(\$?\w+)/', $body, $matches);
                if ($senderUsernameMatch) {
                    $senderUsername = trim($matches[1]);
                }
                $body = strtolower($body);
                if (strpos($body, 'continue') !== false || strpos($body, 'waiting for you') !== false || strpos($body, 'not receiving') !== false) {
                    $status = 'pending_capture';
                } elseif (strpos($body, 'received') !== false) {
                    $status = 'completed';
                }
                // if (!empty($amount) && empty($platformOrderNo)) {
                //     // 无订单号但是成功的场景
                //     $status = 'completed';
                //     $platformOrderNo = create_token();
                // }
            } elseif (strpos($body, 'Payment between') !== false) {
                // 新版本
                $senderUsernameMatch = preg_match('/Sender:\s+(\w+)/', $body, $matches);
                if ($senderUsernameMatch) {
                    $senderUsername = trim($matches[1]);
                }
                $body = strtolower($body);
                if (strpos($body, 'pending') !== false) {
                    $status = 'pending_capture';
                } elseif (strpos($body, 'completed') !== false) {
                    $status = 'completed';
                }
            }
            if (empty($amount) || empty($platformOrderNo) || empty($status)) {
                $content = sprintf(
                    "⚠️ 邮件匹配异常提醒\n" .
                        "💵 金额: %s\n" .
                        "🏷️ 订单号: %s\n" .
                        "� 状态: %s\n" .
                        "🏷️ <b>Cash Tag:</b> %s\n" .
                        "📱 <b>手机编号:</b> %s\n" .
                        "🕒 时间: %s",
                    $amount ? $amount : '未知',
                    $platformOrderNo ? $platformOrderNo : '未知',
                    $status ? $status : '未知',
                    $cashParams['cashapp_tag'],
                    $cashParams['phone_no'],
                    $dateTime
                );
                TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
                Log::error("匹配失败, 邮箱: %s, 主题: %s, 内容: %s", $email, $subject, $body);
                return;
            }
            list($code, $message) = Payment::notifyOrderByCashPerson($cashParams['channel_id'], $status, $amount, $platformOrderNo, $senderUsername);
            if ($code != 1) {
                $content = sprintf(
                    "🔔 订单匹配异常提醒\n" .
                        "📝 提醒: %s\n" .
                        "📱 <b>手机编号:</b> %s\n" .
                        "🕒 时间: %s",
                    $message,
                    $cashParams['phone_no'],
                    $dateTime
                );
                TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
                return;
            }
            if ($status == 'pending_capture') {
                $content = sprintf(
                    "🔔 订单待捕获提醒\n" .
                        "💵 金额: %s\n" .
                        "📝 订单号: %s\n" .
                        "🧑 交易用户: %s\n" .
                        "🏷️ <b>Cash Tag:</b> %s\n" .
                        "📱 <b>手机编号:</b> %s\n" .
                        "🕒 时间: %s",
                    $amount,
                    $platformOrderNo,
                    $senderUsername,
                    $cashParams['cashapp_tag'],
                    $cashParams['phone_no'],
                    $dateTime
                );
                TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
            } else if ($status == 'completed') {
                $content = sprintf(
                    "✅ 订单完成提醒\n" .
                        "💵 金额: %s\n" .
                        "📝 订单号: %s\n" .
                        "🧑 交易用户: %s\n" .
                        "🏷️ <b>Cash Tag:</b> %s\n" .
                        "📱 <b>手机编号:</b> %s\n" .
                        "🕒 时间: %s",
                    $amount,
                    $platformOrderNo,
                    $senderUsername,
                    $cashParams['cashapp_tag'],
                    $cashParams['phone_no'],
                    $dateTime
                );
                TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
            }
        } else if (strpos($subject, 'failed') !== false || strpos($subject, 'cancel') !== false) {
            // 发送失败通知
            $content = sprintf(
                "🔔 付款失败/取消提醒\n" .
                    "💵 邮箱: %s\n" .
                    "📝 主题: %s\n" .
                    "📱 <b>手机编号:</b> %s\n" .
                    "🕒 时间: %s",
                $email,
                $subject,
                $cashParams['phone_no'],
                $dateTime
            );
            Payment::notifyOrderByCashPerson($cashParams['channel_id'], 'failed', $amount, $platformOrderNo);
            TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
        } else {
            // 发送异常通知
            // $content = sprintf(
            //     "🔔 其他邮件提醒\n" .
            //         "💵 邮箱: %s\n" .
            //         "📝 主题: %s\n" .
            //         "📱 <b>手机编号:</b> %s\n" .
            //         "🕒 时间: %s",
            //     $email,
            //     $subject,
            //     $cashParams['phone_no'],
            //     $dateTime
            // );
            // TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, Bot::LONG_CHAT_ID, $content);
        }
    }



    /**
     * 从文本中提取Cash App订单号
     * 
     * @param string $text 要搜索的文本
     * @return string|null 提取的订单号或null（如果未找到）
     */
    private static function extractOrderNumber($text)
    {
        // 尝试匹配格式 #D-XXXXX
        if (preg_match('/#D-\s*([A-Z0-9]+)/', $text, $matches)) {
            return "#D-" . trim($matches[1]);
        }

        // 尝试匹配格式 #XXXXX
        if (preg_match('/#([A-Z0-9]{8,})(?!\s*[A-Z]-|\s*D-)/', $text, $matches)) {
            return "#" . trim($matches[1]);
        }

        return null;
    }
}
