<?php

namespace app\shop\enum;

class Menu
{

    public const MenuList = [
        'user' => [
            'name' => '用户管理',
            'children' => [
                'userList' => ['name' => '用户列表', 'children' => ['userList.browse' => ['name' => '浏览']]],
                'userRechargeList' => ['name' => '充值列表', 'children' => ['userRechargeList.browse' => ['name' => '浏览']]],
                'userWithdrawList' => ['name' => '提现列表', 'children' => ['userWithdrawList.browse' => ['name' => '浏览']]],
                'customerServiceList' => ['name' => '客服中心', 'children' => ['customerServiceList.browse' => ['name' => '浏览']]],
            ]
        ],
        'canada28' => [
            'name' => '加拿大28',
            'children' => [
                'canada28ProductSetting' => ['name' => '玩法设置', 'children' => ['canada28ProductSetting.browse' => ['name' => '浏览']]],
                'canada28OrderList' => ['name' => '投注记录', 'children' => ['canada28OrderList.browse' => ['name' => '浏览']]],
                'canada28CrawList' => ['name' => '开奖记录', 'children' => ['canada28CrawList.browse' => ['name' => '浏览']]],
            ]
        ],
        'canada28' => [
            'name' => 'Bingo28',
            'children' => [
                'bingo28ProductSetting' => ['name' => '玩法设置', 'children' => ['bingo28ProductSetting.browse' => ['name' => '浏览']]],
                'bingo28OrderList' => ['name' => '投注记录', 'children' => ['bingo28OrderList.browse' => ['name' => '浏览']]],
                'bingo28CrawList' => ['name' => '开奖记录', 'children' => ['bingo28CrawList.browse' => ['name' => '浏览']]],
            ]
        ],
        'keno' => [
            'name' => 'Keno',
            'children' => [
                'kenoProductSetting' => ['name' => '玩法设置', 'children' => ['kenoProductSetting.browse' => ['name' => '浏览']]],
                'kenoOrderList' => ['name' => '投注记录', 'children' => ['kenoOrderList.browse' => ['name' => '浏览']]],
                'kenoCrawList' => ['name' => '开奖记录', 'children' => ['kenoCrawList.browse' => ['name' => '浏览']]],
            ]
        ],
        'finance' => [
            'name' => '财务统计',
            'children' => [
                'financeDashboard' => ['name' => '仪表盘', 'children' => ['financeDashboard.browse' => ['name' => '浏览']]],
                'financeSum' => ['name' => '财务统计', 'children' => ['financeSum.browse' => ['name' => '浏览']]],
            ]
        ],
        'backendManage' => [
            'name' => '后台管理',
            'children' => [
                'systemSetting' => ['name' => '系统设置', 'children' => ['systemSetting.browse' => ['name' => '浏览']]],
                'merchantManagement' => ['name' => '商户管理', 'children' => ['merchantManagement.browse' => ['name' => '浏览']]],
                'staffManagement' => ['name' => '员工管理', 'children' => ['staffManagement.browse' => ['name' => '浏览']]],
                'roleManagement' => ['name' => '权限与角色管理', 'children' => ['roleManagement.browse' => ['name' => '浏览']]],
                'paymentChannel' => ['name' => '支付渠道管理', 'children' => ['paymentChannel.browse' => ['name' => '浏览']]],
                'systemLogs' => ['name' => '系统日志', 'children' => ['systemLogs.browse' => ['name' => '浏览']]],
            ]
        ],
        'config' => [
            'name' => '配置管理',
            'children' => []
        ],
    ];
}
