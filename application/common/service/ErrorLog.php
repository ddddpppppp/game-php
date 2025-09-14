<?php

namespace app\common\service;

use app\common\enum\Bot;
use app\common\helper\TgHelper;

class ErrorLog
{
    public static function create($content, $chatId = null)
    {
        \app\common\model\ErrorLog::create([
            'content' => $content,
        ]);
        if ($chatId) {
            TgHelper::sendMessage(Bot::PAYMENT_BOT_TOKEN, $chatId, $content);
        }
        return true;
    }
}
