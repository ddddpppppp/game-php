<?php

namespace app\api\enum;

class Imap
{

    const SYNC_EMAIL_TASK_REDIS_KEY = 'sync:email:task';
    const SYNC_EMAIL_LAST_MESSAGE_REDIS_KEY = 'sync:email:lastmessage:%s';
}
