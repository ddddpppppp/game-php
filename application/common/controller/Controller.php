<?php

namespace app\common\controller;


use app\common\enum\Common;
use think\Container;
use think\exception\HttpResponseException;
use think\Response;

/**
 * 后台控制器基类
 * Class BaseController
 * @package app\store\controller
 */
class Controller extends \think\Controller
{
    public function _initialize()
    {
        // Add CORS headers to all responses
        $this->addCorsHeaders();
    }

    /**
     * Add CORS headers
     */
    protected function addCorsHeaders()
    {
        header("Access-Control-Allow-Origin: *");
        header('Access-Control-Allow-Credentials: true');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Token, Timezone, sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, referer, user-agent");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
    }

    /**
     * @param $data
     * @param $code
     * @param $msg
     * @return mixed
     */
    protected function success($data = [], $code = Common::SUCCESS_CODE, $msg = Common::SUCCESS_MSG, array $header = [], $url = null, $wait = 3)
    {
        $result = [
            'status' => $code,
            'statusText' => $msg,
            'data' => $data
        ];

        // Add CORS headers to the response
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization, Token, Timezone, sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, referer, user-agent',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS, PUT, DELETE, PATCH'
        ];

        $header = array_merge($corsHeaders, $header);

        $response = Response::create($result, 'json')->header($header)->options(['jump_template' => $this->app['config']->get('dispatch_success_tmpl')]);

        throw new HttpResponseException($response);
    }


    protected function error($msg = Common::ERROR_MSG, $code = Common::ERROR_CODE, $data = [], array $header = [], $url = null, $wait = 3)
    {
        $result = [
            'status' => $code,
            'statusText' => $msg,
            'data' => $data
        ];

        // Add CORS headers to the response
        $corsHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization, Token, Timezone, sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, referer, user-agent',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS, PUT, DELETE, PATCH'
        ];

        $header = array_merge($corsHeaders, $header);

        $response = Response::create($result, 'json')->header($header)->options(['jump_template' => $this->app['config']->get('dispatch_success_tmpl')]);

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @param int $code
     * @param string $msg
     * @param string $url
     * @param array $data
     * @return array
     */
    protected function renderJson($code = 1, $data = [], $msg = '')
    {
        header("content-type:application/json");
        $this->addCorsHeaders();

        $response = Response::create(['status' => $code, 'data' => $data, 'statusText' => $msg], 'json');

        throw new HttpResponseException($response);
    }

    /**
     * 返回操作成功json
     * @param string $msg
     * @param string $url
     * @param array $data
     * @return array
     */
    protected function renderSuccess($msg = 'success', $data = [])
    {
        return $this->renderJson(0, $data, $msg);
    }

    /**
     * 返回操作失败json
     * @param string $msg
     * @param string $url
     * @param array $data
     * @return array
     */
    protected function renderError($msg = 'error', $data = [])
    {
        return $this->renderJson(-1, $data, $msg);
    }
}
