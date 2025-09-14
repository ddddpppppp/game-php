<?php

namespace app\api\enum;

class Order
{
    const CHANNEL_DAILY_COUNT_REDIS_KEY = "channel:%s:daily_count:%s";
    const CHANNEL_DAILY_SHOP_COUNT_REDIS_KEY = "channel:%s:daily_shop:%s:count:%s";
    const CHANNEL_RECENT_AMOUNT_REDIS_KEY = "channel:%s:recent_amounts";
    const CHANNEL_LAST_INDEX_REDIS_KEY = "channel:last_index:%s";
}
