<?php

namespace common\exception;

use think\exception\Handle;
use think\facade\Request;
use think\Response;
use Throwable;

class Handler extends Handle
{
    public function render(Throwable $e): Response
    {
        // 设置 CORS 头（关键！）
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');
        // 针对不同异常类型返回不同格式
        if ($e instanceof \think\exception\HttpException) {
            $code = $e->getStatusCode();
            $msg = $e->getMessage() ?: Response::$statusTexts[$code] ?? 'Server Error';
        } elseif ($e instanceof \think\exception\ValidateException) {
            $code = 400;
            $msg = $e->getError();
        } else {
            $code = $e->getCode() ?: 500;
            $msg = config('app_debug') ? $e->getMessage() : 'Server Error';
        }

        // 统一返回格式
        $result = [
            'code' => $code,
            'msg' => $msg,
            'data' => []
        ];

        // 强制返回200状态码
        return json($result, 200);
    }
}
