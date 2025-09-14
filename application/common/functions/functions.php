<?php

use think\facade\Log;

/**
 * 下划线转驼峰
 */


if (!function_exists("postData")) {
    function postData($url, $param = array(), $header = array(), $printCurl = false)
    {
        $postUrl = $url;
        $ch = curl_init();                                      //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl);                 //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);                    //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);            //要求结果为字符串且输出到屏幕上

        // 默认表单提交方式
        $isFormData = true;
        $hasContentType = false;

        // 检查header中是否已经设置了Content-Type
        if ($header) {
            foreach ($header as $h) {
                if (stripos($h, 'Content-Type:') !== false) {
                    $hasContentType = true;
                    // 如果明确设置了application/json，则不使用表单形式
                    if (stripos($h, 'application/json') !== false) {
                        $isFormData = false;
                    }
                    break;
                }
            }
        }

        // 设置适当的headers
        if ($param && !$hasContentType && $isFormData) {
            // 添加表单提交的Content-Type
            $header[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);      // 增加 HTTP Header（头）
        }

        if ($param) {
            curl_setopt($ch, CURLOPT_POST, 1);                      //post提交方式

            // 如果是表单形式且参数是数组，转换为查询字符串
            if ($isFormData && is_array($param)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));  // 表单形式提交
            } else {
                // 如果是JSON或已经是字符串，直接提交
                if (is_array($param) && !$isFormData) {
                    $param = json_encode($param);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
            }
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);        // 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        // 生成并记录等效的curl命令
        if ($printCurl) {
            // 判断请求方法
            $method = "GET";
            if ($param) {
                $method = "POST"; // 默认使用POST，如果有参数的话
            }

            // 检查header中是否有指定方法
            if ($header) {
                foreach ($header as $h) {
                    if (preg_match('/^X-HTTP-Method-Override:\s+(\w+)/i', $h, $matches)) {
                        $method = $matches[1];
                        break;
                    }
                }
            }

            $curlCommand = "curl";

            // 如果不是GET请求，添加method
            if ($method !== "GET") {
                $curlCommand .= " -X " . $method;
            }

            // 添加headers
            if ($header) {
                foreach ($header as $h) {
                    $curlCommand .= " -H \"" . $h . "\"";
                }
            }

            // 添加数据
            if ($param) {
                if (is_array($param)) {
                    if ($isFormData) {
                        // 表单形式的curl命令
                        foreach ($param as $key => $value) {
                            $curlCommand .= " --data-urlencode \"" . $key . "=" . $value . "\"";
                        }
                    } else {
                        // JSON形式的curl命令
                        $curlCommand .= " -d '" . json_encode($param) . "'";
                    }
                } else {
                    $curlCommand .= " -d '" . $param . "'";
                }
            }

            // 添加URL
            $curlCommand .= " \"" . $postUrl . "\"";

            // 使用logData函数记录curl命令，而不是echo输出
            logData("CURL Command: " . $curlCommand, 'info');
        }

        $data = curl_exec($ch);                                 //运行curl
        curl_close($ch);
        return $data;
    }
}

if (!function_exists("generate_sign")) {
    function generate_sign($data, $key)
    {
        ksort($data);
        $sign = '';
        foreach ($data as $k => $v) {
            if (empty($v)) {
                continue;
            }
            $sign .= $k . '=' . $v . '&';
        }
        $sign .= 'key=' . $key;
        return md5($sign);
    }
}

if (!function_exists("logData")) {
    function logData($data, $type = 'info')
    {
        Log::init([
            'type' => 'File',
            'path' => ROOT_PATH . '/runtime/logs'
        ]);
        if (is_array($data)) {
            $data = json_encode($data);
        }
        Log::write($data, $type);
        Log::close();
        return '记录成功';
    }
}


if (!function_exists('create_token')) {
    /**
     * 生成token
     *
     * @param  string    $name 配置参数名
     * @param  mixed     $default   默认值
     * @return mixed
     */
    function create_token()
    {
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';

        // 确保至少有一个数字
        $result = $numbers[rand(0, strlen($numbers) - 1)];

        // 确保至少有一个字母
        $result .= $letters[rand(0, strlen($letters) - 1)];

        // 剩余的8个字符从字母和数字中随机选择
        $chars = $letters . $numbers;
        $max = strlen($chars) - 1;

        for ($i = 0; $i < 8; $i++) {
            $result .= $chars[rand(0, $max)];
        }

        // 打乱字符顺序
        return str_shuffle($result);
    }
}


if (!function_exists("create_password")) {
    function create_password($salt, $password)
    {
        return md5(sha1($password) . $salt);
    }
}

/**
 * 下划线转驼峰
 */
if (!function_exists("camelize")) {
    function camelize($uncamelizedWords, $separator = '_')
    {
        $uncamelizedWords = $separator . str_replace($separator, " ", strtolower($uncamelizedWords));
        return ltrim(str_replace(" ", "", ucwords($uncamelizedWords)), $separator);
    }
}

/**
 * 驼峰命名转下划线命名
 **/
if (!function_exists("unCamelize")) {
    function unCamelize($camelCaps, $separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }
}

if (!function_exists("is_true")) {
    function is_true($val, $returnNull = false)
    {
        $boolval = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val);
        return ($boolval === null && !$returnNull ? false : $boolval);
    }
}

if (!function_exists("create_uuid")) {
    function create_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists("dd")) {
    /**
     * 打印变量并终止脚本执行 (dump and die)
     * 
     * @param mixed ...$vars 要打印的变量
     * @return void
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit();
    }
}
