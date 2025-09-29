<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;

// Handle CORS for all requests at the earliest point
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Credentials: true');
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Token, Timezone, sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, referer, user-agent");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');

// Handle OPTIONS preflight requests immediately
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

defined('ROOT_PATH') or define('ROOT_PATH', dirname(dirname(__FILE__)));
defined('__ROOT__') or define('__ROOT__', dirname(__FILE__));
defined('__STATIC__') or define('__STATIC__', __ROOT__ . '/static');
defined('CERT_PATH') or define('CERT_PATH', ROOT_PATH . '/cert');
defined('AES_METHOD') or define('AES_METHOD', 'aes-128-cbc');
defined('AES_KEY') or define('AES_KEY', '58d56291a28001a783aea9e00dfcb146');
defined('JWT_KEY') or define('JWT_KEY', '58d56291a28000a783aea8e00dfcb147');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];

defined('SITE_ROOT') or define('SITE_ROOT', $baseUrl);
defined('APP_ROOT') or define('APP_ROOT', 'https://keno28.us');
// 加载基础文件
require __DIR__ . '/../thinkphp/base.php';

// 支持事先使用静态方法设置Request对象和Config对象
// 执行应用并响应
Container::get('app')->run()->send();
