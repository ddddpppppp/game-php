<?php

namespace app\shop\enum;

class Merchant
{

    public const SUPER_MERCHANT_ID = '1023211';

    public const MENU_ACCESS = [

        // canada28
        'canada28',
        'canada28Dashboard',
        'canada28Dashboard.browse',
        'canada28ProductSetting',
        'canada28ProductSetting.browse',
        'canada28OrderList',
        'canada28OrderList.browse',
        'canada28CrawList',
        'canada28CrawList.browse',

        // user
        'user',
        'userList',
        'userList.browse',
        'userRechargeList',
        'userRechargeList.browse',
        'userWithdrawList',
        'userWithdrawList.browse',

        // 后台管理
        'backendManage',
        'staffManagement',
        'staffManagement.browse',
        'roleManagement',
        'roleManagement.browse',
        'systemLogs',
        'systemLogs.browse',
    ];

    public const MERCHANT_CONFIG_REDIS_KEY = 'merchant:%s:config:%s';
}
