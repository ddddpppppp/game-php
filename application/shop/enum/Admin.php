<?php

namespace app\shop\enum;

class Admin
{
    public const TOKEN_KEY = 'token-%s';
    public const SUPER_ADMIN_ID = 1000322;
    public const DEFAULT_MERCHANT_ID = 'ad22ab51-1637-42c5-a82f-4b51382f7bc3';

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
        'systemSetting',
        'systemSetting.browse',
        'merchantManagement',
        'merchantManagement.browse',
        'staffManagement',
        'staffManagement.browse',
        'roleManagement',
        'roleManagement.browse',
        'systemLogs',
        'systemLogs.browse',
    ];

    public const LOGIN_TIMES_KEY = 'login:times:%s';
}
