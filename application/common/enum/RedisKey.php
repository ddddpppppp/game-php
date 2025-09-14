<?php

namespace app\common\enum;

class RedisKey
{
    const game_TOKEN = 'game_token_%s';
    const PAY_PROCESSING = 'pay_processing_%s';
    const PAYPAL_TOKEN = 'paypal_token_%s';

    const MICROSOFT_USER_ACCESS_TOKEN = 'microsoft:user:access_token:%s';
    const MICROSOFT_LOGIN_STATE = 'microsoft:login:state:%s';
    const MICROSOFT_EMAIL_LAST_MESSAGE_ID = 'microsoft:email:last_message_id:%s';
}
