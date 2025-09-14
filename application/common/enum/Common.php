<?php

namespace app\common\enum;

class Common
{
    const SUCCESS_CODE = 1;
    const ERROR_CODE = -1;
    const NEED_LOGIN_CODE = -3;

    const SUCCESS_MSG = 'success';
    const ERROR_MSG = 'error';
    const NEED_LOGIN_MSG = 'login failed, please login again';
    const PARAMS_EMPTY_MSG = '必填参数不能为空';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = -1;

    const STATUS_DATA_SET = [
        -1 => [
            'class' => 'danger',
            'name' => '冻结',
        ],
        1 => [
            'class' => 'primary',
            'name' => '正常',
        ],
    ];
}
