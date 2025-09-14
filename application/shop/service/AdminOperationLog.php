<?php

namespace app\shop\service;

use app\common\helper\ServerHelper;

class AdminOperationLog
{

    public static function saveLog(string $adminId, string $merchantId, string $content) {
        $insert = [];
        $insert['admin_id'] = $adminId;
        $insert['merchant_id'] = $merchantId;
        $insert['content'] = $content;
        \app\shop\model\AdminOperationLog::create($insert);
    }

}