<?php

namespace app\api\enum;

class User
{
    public const USER_IP_LOCK_KEY = 'user_ip_lock:%s';

    public const EMAIL_VERIFICATION_CODE_KEY = 'email_verification_code:%s';

    public const EMAIL_RATE_LIMIT_KEY = 'email_rate_limit:%s';

    // 设备码相关键
    public const USER_DEVICE_KEY = 'user_device:%s'; // 用户ID -> 设备码

    public const DEVICE_USER_KEY = 'device_user:%s'; // 设备码 -> 用户ID
}
