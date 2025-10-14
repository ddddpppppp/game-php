-- DROP TABLE IF EXISTS `game_merchant`;
CREATE TABLE
    IF NOT EXISTS `game_merchant` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `admin_id` char(36) NOT NULL COMMENT 'admin表的id',
        `balance` decimal(10, 2) NOT NULL COMMENT '余额',
        `uuid` char(36) NOT NULL,
        `name` varchar(50) NOT NULL,
        `logo` varchar(250) NOT NULL,
        `app_key` char(10) NOT NULL,
        `type` tinyint (2) DEFAULT 1 NOT NULL,
        `status` tinyint (2) DEFAULT 1 NOT NULL,
        `created_at` datetime,
        `updated_at` datetime,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uuid` (`uuid`),
        KEY `admin_id` (`admin_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1023211 COMMENT '商户列表';

-- DROP TABLE IF EXISTS `game_merchant_config`;
CREATE TABLE
    IF NOT EXISTS `game_merchant_config` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `merchant_id` char(36) NOT NULL COMMENT '商户id',
        `name` varchar(50) NOT NULL,
        `value` varchar(500) NOT NULL,
        `created_at` datetime,
        `updated_at` datetime,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        UNIQUE KEY `merchant_id_name` (`merchant_id`, `name`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '商户配置';

-- DROP TABLE IF EXISTS `game_admin`;
CREATE TABLE
    IF NOT EXISTS `game_admin` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `uuid` char(36) NOT NULL,
        `nickname` varchar(50) NOT NULL,
        `avatar` varchar(250) NOT NULL,
        `username` varchar(30) NOT NULL,
        `balance` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '余额',
        `password` char(32) NOT NULL,
        `salt` char(10) NOT NULL,
        `merchant_id` char(36) NOT NULL COMMENT '对应merchant表',
        `role_id` int (10) NOT NULL COMMENT '对应role表',
        `parent_id` char(36) NOT NULL COMMENT '推荐人ID',
        `path` varchar(500) NOT NULL COMMENT '路径（记录所有上级ID包括自己，如0:1:2:3）',
        `depth` tinyint (3) DEFAULT 0 COMMENT '层级深度',
        `status` tinyint (2) NOT NULL DEFAULT 1 COMMENT '-1冻结，1开启',
        `created_at` datetime DEFAULT null,
        `updated_at` datetime DEFAULT null,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        KEY `username` (`username`),
        KEY `merchant_id` (`merchant_id`),
        KEY `parent_id` (`parent_id`),
        KEY `idx_path` (`path` (255)),
        UNIQUE KEY `uuid` (`uuid`),
        KEY `role_id` (`role_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1000322;

-- DROP TABLE IF EXISTS `game_role`;
CREATE TABLE
    IF NOT EXISTS `game_role` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `type` tinyint (2) NOT NULL COMMENT '1:管理员,2:商户,3:代理,4:个码管理者',
        `merchant_id` char(36) NOT NULL COMMENT '商户id',
        `access` text NOT NULL,
        `created_at` datetime DEFAULT null,
        `updated_at` datetime DEFAULT null,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        KEY `merchant_id` (`merchant_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;

-- DROP TABLE IF EXISTS `game_admin_operation_log`;
CREATE TABLE
    IF NOT EXISTS `game_admin_operation_log` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `admin_id` char(36) NOT NULL COMMENT '管理员id',
        `merchant_id` char(36) NOT NULL COMMENT '商户id',
        `content` varchar(300) NOT NULL,
        `created_at` datetime,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        KEY `admin_id` (`admin_id`),
        KEY `merchant_id` (`merchant_id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '登录日志';

-- DROP TABLE IF EXISTS `game_error_log`;
CREATE TABLE
    IF NOT EXISTS `game_error_log` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `content` varchar(300) NOT NULL,
        `created_at` datetime,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '错误日志';

-- 系统设置表
-- DROP TABLE IF EXISTS `game_system_setting`;
CREATE TABLE IF NOT EXISTS `game_system_setting` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT '设置名称，如recharge_setting、withdraw_setting等',
    `title` varchar(100) NOT NULL COMMENT '设置标题',
    `description` varchar(500) DEFAULT NULL COMMENT '设置描述',
    `config` text NOT NULL COMMENT '设置配置JSON数据',
    `status` tinyint(2) DEFAULT 1 NOT NULL COMMENT '状态：1启用，0禁用',
    `sort` int(11) DEFAULT 0 COMMENT '排序',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime DEFAULT null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    KEY `status` (`status`),
    KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 插入默认系统设置数据
INSERT INTO `game_system_setting` (`name`, `title`, `description`, `config`, `status`, `sort`, `created_at`, `updated_at`) VALUES
('recharge_setting', '充值设置', '配置充值相关参数', '{"usdt_min_amount":10,"usdt_max_amount":10000,"cashapp_min_amount":10,"cashapp_max_amount":10000,"usdc_online_min_amount":10,"usdc_online_max_amount":10000,"usdt_gift_rate":2,"cashapp_gift_rate":0,"usdc_online_gift_rate":2}', 1, 1, NOW(), NOW()),
('withdraw_setting', '提现设置', '配置提现相关参数', '{"min_amount":50,"max_amount":50000,"usdt_fee_rate":2, "cashapp_fee_rate":0, "usdc_online_fee_rate":0, "daily_limit":3, "gift_transaction_times": 3}', 1, 4, NOW(), NOW());
('new_user_gift', '新用户注册赠送', '配置新用户注册赠送相关参数', '{"gift_amount":20}', 1, 5, NOW(), NOW());
-- DROP TABLE IF EXISTS `game_users`;
CREATE TABLE
    IF NOT EXISTS `game_users` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `uuid` char(36) NOT NULL COMMENT '用户名/手机号/邮箱',
        `username` varchar(50) NOT NULL COMMENT '用户名/手机号/邮箱',
        `type` varchar(50) NOT NULL COMMENT 'bot/user',
        `password` varchar(255) NOT NULL COMMENT '加密后的密码',
        `balance` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '余额',
        `balance_frozen` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '冻结余额',
        `nickname` varchar(50) NOT NULL COMMENT '昵称',
        `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL',
        `merchant_id` char (36) DEFAULT 0 COMMENT '商户ID',
        `parent_id` int (36) DEFAULT 0 COMMENT '邀请人ID',
        `status` tinyint (1) NOT NULL DEFAULT 1 COMMENT '状态 (1:正常, 0:禁用)',
        `salt` varchar(32) DEFAULT NULL COMMENT '密码盐值',
        `ip` varchar(50) DEFAULT NULL COMMENT 'ip',
        `device_code` varchar(32) DEFAULT NULL COMMENT '设备码',
        `is_app` tinyint(1) NOT NULL DEFAULT -1 COMMENT '是否是app',
        `created_at` datetime DEFAULT null,
        `updated_at` datetime DEFAULT null,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        KEY `parent_id` (`parent_id`),
        KEY `merchant_id` (`merchant_id`),
        KEY `ip` (`ip`),
        KEY `device_code` (`device_code`),
        KEY `status` (`status`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '用户表';

-- DROP TABLE IF EXISTS `game_transactions`;
CREATE TABLE IF NOT EXISTS `game_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(20) NOT NULL COMMENT '交易类型: deposit-充值, withdraw-提现',
  `channel_id` varchar(20) NOT NULL COMMENT '支付渠道ID',
  `amount` decimal(15,2) NOT NULL COMMENT '交易金额',
  `actual_amount` decimal(15,4) NOT NULL COMMENT '实际金额',
  `account` varchar(250) NOT NULL COMMENT '账户',
  `order_no` varchar(32) NULL COMMENT '订单号',
  `fee` decimal(15,4) NOT NULL DEFAULT '0.00' COMMENT '手续费',
  `gift` decimal(15,4) NOT NULL DEFAULT '0.00' COMMENT '赠送金额',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending-待处理, completed-已完成, failed-失败, expired-已过期',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `expired_at` datetime DEFAULT NULL COMMENT '过期时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='交易记录表';

-- 余额变动日志表
-- DROP TABLE IF EXISTS `game_user_balances`;
CREATE TABLE IF NOT EXISTS `game_user_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) NOT NULL COMMENT '变动类型：gift-赠送, deposit-充值, deposit_gift-充值赠送, withdraw-提现, game_bet-投注, game_win-收益, withdraw_failed_refund-提现失败退款',
  `amount` decimal(15,2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(15,4) NOT NULL COMMENT '变动前余额',
  `balance_after` decimal(15,4) NOT NULL COMMENT '变动后余额',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `related_id` varchar(200)  DEFAULT NULL COMMENT '关联ID',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_related_id` (`related_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='余额变动日志';

-- DROP TABLE IF EXISTS `game_user_frozen_balances`;
CREATE TABLE IF NOT EXISTS `game_user_frozen_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` bigint(20) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(30) NOT NULL COMMENT '变动类型：game_bet-投注, gift-赠送',
  `amount` decimal(15,2) NOT NULL COMMENT '变动金额',
  `balance_before` decimal(15,4) NOT NULL COMMENT '变动前余额',
  `balance_after` decimal(15,4) NOT NULL COMMENT '变动后余额',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `related_id` varchar(200)  DEFAULT NULL COMMENT '关联ID',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_related_id` (`related_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='冻结余额变动日志';

-- DROP TABLE IF EXISTS `game_payment_channel`;
CREATE TABLE
    IF NOT EXISTS `game_payment_channel` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL COMMENT '名称',
        `belong_admin_id` char(36) NOT NULL COMMENT '所属管理者id',
        `type` Enum ('paypal', 'cashapp', 'usdt', 'usdc_online') NOT NULL COMMENT '类型',
        `rate` decimal(3, 1) NOT NULL DEFAULT 0 COMMENT '费率',
        `charge_fee` decimal(5, 2) NOT NULL DEFAULT 0 COMMENT '单笔手续费',
        `count_time` varchar(30) NOT NULL COMMENT '结算时间',
        `guarantee` varchar(30) NOT NULL COMMENT '保证金',
        `freeze_time` varchar(30) NOT NULL COMMENT '冻结时间',
        `day_limit_money` decimal(10, 2) NOT NULL COMMENT '每日限额',
        `day_limit_count` int (11) NOT NULL COMMENT '每日限额次数',
        `remark` varchar(50) NOT NULL COMMENT '备注',
        `status` tinyint (2) NOT NULL DEFAULT 1 COMMENT '-1停用，1开启',
        `is_backup` tinyint (2) NOT NULL DEFAULT -1 COMMENT '是否备用渠道',
        `params` json NOT NULL COMMENT '渠道参数',
        `sort` int (11) NOT NULL,
        `created_at` datetime DEFAULT null,
        `updated_at` datetime DEFAULT null,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        KEY `belong_admin_id` (`belong_admin_id`),
        KEY `type` (`type`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '代收渠道';
INSERT INTO `game_payment_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `count_time`, `guarantee`, `freeze_time`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-usdt', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'usdt', '0.0', '0.00', '3', '0', '0', '9999999.00', '1000', '', '1', '-1', '{\"address\": \"xxxxxxxxxxssssssss\"}', '1', '2024-04-27 14:04:54', '2025-09-23 14:39:21', NULL);
INSERT INTO `game_payment_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `count_time`, `guarantee`, `freeze_time`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-cashapp', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'cashapp', '10.0', '0.30', '3', '0', '0', '10000.00', '1000', '', '1', '-1', '{\"mchNo\": \"xxxxxxxxxxssssssss\", \"appKey\": \"appSecret\", \"payWay\": \"cashapp-PROD\", \"appSecret\": \"appSecret\"}', '1', '2024-04-27 14:04:54', '2025-09-23 14:38:48', NULL);
INSERT INTO `game_payment_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `count_time`, `guarantee`, `freeze_time`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-usdc', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'usdc_online', '1.0', '0.30', '3', '0', '0', '9999999.00', '1000', '', '1', '-1', '{\"key\": \"sk_oCV5tfyvul7gOJ2Vye1Au\", \"url\": \"https://gateway.sparkham.com\", \"appId\": \"AP1969009033495056384\", \"mchNo\": \"MID1969007979084779520\"}', '1', '2024-04-27 14:04:54', '2025-09-23 14:39:27', NULL);

-- DROP TABLE IF EXISTS `game_withdraw_channel`;
CREATE TABLE
    IF NOT EXISTS `game_withdraw_channel` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL COMMENT '名称',
        `belong_admin_id` char(36) NOT NULL COMMENT '所属管理者id',
        `type` Enum ('paypal', 'cashapp', 'usdt', 'usdc_online') NOT NULL COMMENT '类型',
        `rate` decimal(3, 1) NOT NULL DEFAULT 0 COMMENT '费率',
        `charge_fee` decimal(5, 2) NOT NULL DEFAULT 0 COMMENT '单笔手续费',
        `day_limit_money` decimal(10, 2) NOT NULL COMMENT '每日限额',
        `day_limit_count` int (11) NOT NULL COMMENT '每日限额次数',
        `remark` varchar(50) NOT NULL COMMENT '备注',
        `status` tinyint (2) NOT NULL DEFAULT 1 COMMENT '-1停用，1开启',
        `is_backup` tinyint (2) NOT NULL DEFAULT -1 COMMENT '是否备用渠道',
        `params` json NOT NULL COMMENT '渠道参数',
        `sort` int (11) NOT NULL,
        `created_at` datetime DEFAULT null,
        `updated_at` datetime DEFAULT null,
        `deleted_at` datetime DEFAULT null,
        PRIMARY KEY (`id`),
        KEY `belong_admin_id` (`belong_admin_id`),
        KEY `type` (`type`)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '代收渠道';
INSERT INTO `game_withdraw_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-usdt', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'usdt', '1.0', '0.00', '9999999.00', '1000', '', '1', '-1', '{}', '1', '2024-04-27 14:04:54', '2025-09-23 14:39:21', NULL);
INSERT INTO `game_withdraw_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-cashapp', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'cashapp', '1.0', '0.00', '9999999.00', '1000', '', '1', '-1', '{}', '1', '2024-04-27 14:04:54', '2025-09-23 14:38:48', NULL);
INSERT INTO `game_withdraw_channel` (`id`, `name`, `belong_admin_id`, `type`, `rate`, `charge_fee`, `day_limit_money`, `day_limit_count`, `remark`, `status`, `is_backup`, `params`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES (NULL, 'dfpay-usdc', '4c7c0273-3382-45bd-9b6d-d31efe4da389', 'usdc_online', '1.0', '0.00', '9999999.00', '1000', '', '1', '-1', '{}', '1', '2024-04-27 14:04:54', '2025-09-23 14:39:27', NULL);

-- DROP TABLE IF EXISTS `game_email_auto_auth`;
CREATE TABLE IF NOT EXISTS `game_email_auto_auth` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `email` varchar(100) NOT NULL COMMENT '邮箱',
    `access_token` text NOT NULL COMMENT 'access_token',
    `refresh_token` text NOT NULL COMMENT 'refresh_token',
    `expires_at` datetime NOT NULL COMMENT '过期时间',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime,
    PRIMARY KEY (`id`),
    KEY `email` (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1 COMMENT '邮箱自动授权表';

-- 加拿大28游戏玩法配置表
-- DROP TABLE IF EXISTS `game_canada28_bet_types`;
CREATE TABLE IF NOT EXISTS `game_canada28_bet_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `type_name` varchar(50) NOT NULL COMMENT '玩法名称',
    `type_key` varchar(50) NOT NULL COMMENT '玩法标识',
    `description` varchar(200) DEFAULT NULL COMMENT '玩法描述',
    `odds` decimal(8,2) NOT NULL COMMENT '赔率倍数',
    `status` tinyint(2) DEFAULT 1 NOT NULL COMMENT '状态：1启用，0禁用',
    `sort` int(11) DEFAULT 0 COMMENT '排序',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime DEFAULT null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `merchant_type` (`merchant_id`, `type_key`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`),
    KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='加拿大28游戏玩法配置表';

-- 插入默认玩法数据

INSERT INTO `game_canada28_bet_types` (`id`, `merchant_id`, `type_name`, `type_key`, `description`, `odds`, `status`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES
(124, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG', 'high', '大：14-27', 2.00, 1, 1, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(125, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL', 'low', '小：0-13', 2.00, 1, 2, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(126, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'ODD', 'odd', '单', 2.00, 1, 3, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(127, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'EVEN', 'even', '双', 2.00, 1, 4, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(128, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'TRIPES', 'triple', '豹子', 50.00, 1, 5, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(129, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'DOUBLES', 'pair', '对子', 3.00, 1, 3, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(130, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'STRAIGHT', 'straight', '顺子', 10.00, 1, 4, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(131, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG & ODD', 'high_odd', '大单', 4.20, 1, 8, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(132, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL & ODD', 'low_odd', '小单', 4.50, 1, 9, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(133, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG & EVEN', 'high_even', '大双', 4.50, 1, 10, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(134, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL & EVEN', 'low_even', '小双', 4.20, 1, 11, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(135, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'MINIMUM', 'extreme_low', '极小：0-5', 10.00, 1, 1, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(136, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'MAXIMUM', 'extreme_high', '极大：22-27', 10.00, 1, 2, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(137, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 0', 'sum_0', '特码0', 500.00, 1, 14, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(138, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 1', 'sum_1', '特码1', 100.00, 1, 15, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(139, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 2', 'sum_2', '特码2', 70.00, 1, 16, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(140, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 3', 'sum_3', '特码3', 50.00, 1, 17, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(141, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 4', 'sum_4', '特码4', 30.00, 1, 18, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(142, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 5', 'sum_5', '特码5', 20.00, 1, 19, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(143, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 6', 'sum_6', '特码6', 17.00, 1, 20, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(144, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 7', 'sum_7', '特码7', 16.00, 1, 21, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(145, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 8', 'sum_8', '特码8', 15.00, 1, 22, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(146, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 9', 'sum_9', '特码9', 14.00, 1, 23, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(147, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 10', 'sum_10', '特码10', 13.00, 1, 24, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(148, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 11', 'sum_11', '特码11', 12.00, 1, 25, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(149, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 12', 'sum_12', '特码12', 12.00, 1, 26, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(150, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 13', 'sum_13', '特码13', 12.00, 1, 27, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(151, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 14', 'sum_14', '特码14', 12.00, 1, 28, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(152, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 15', 'sum_15', '特码15', 12.00, 1, 29, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(153, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 16', 'sum_16', '特码16', 12.00, 1, 30, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(154, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 17', 'sum_17', '特码17', 13.00, 1, 31, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(155, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 18', 'sum_18', '特码18', 14.00, 1, 32, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(156, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 19', 'sum_19', '特码19', 15.00, 1, 33, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(157, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 20', 'sum_20', '特码20', 16.00, 1, 34, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(158, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 21', 'sum_21', '特码21', 17.00, 1, 35, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(159, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 22', 'sum_22', '特码22', 20.00, 1, 36, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(160, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 23', 'sum_23', '特码23', 30.00, 1, 37, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(161, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 24', 'sum_24', '特码24', 50.00, 1, 38, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(162, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 25', 'sum_25', '特码25', 70.00, 1, 39, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(163, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 26', 'sum_26', '特码26', 100.00, 1, 40, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(164, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 27', 'sum_27', '特码27', 500.00, 1, 41, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL);
-- 动态赔率规则表 - 根据特殊条件调整赔率
-- DROP TABLE IF EXISTS `game_canada28_dynamic_odds`;
CREATE TABLE IF NOT EXISTS `game_canada28_dynamic_odds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `rule_name` varchar(100) NOT NULL COMMENT '规则名称',
    `trigger_condition` varchar(50) NOT NULL COMMENT '触发条件：sum_range, sum_exact, sum_in',
    `trigger_values` text COMMENT '触发条件值（JSON格式）',
    `bet_type_adjustments` text COMMENT '投注类型赔率调整（JSON格式）',
    `status` tinyint(2) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `priority` int(11) DEFAULT 0 COMMENT '优先级',
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='动态赔率规则表';

-- 插入13-14特殊赔率规则
INSERT INTO `game_canada28_dynamic_odds` (`merchant_id`, `rule_name`, `trigger_condition`, `trigger_values`, `bet_type_adjustments`, `status`, `priority`, `created_at`, `updated_at`) VALUES
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', '13-14特殊赔率', 'sum_in', '[13, 14]', '{"high": 1.6, "low": 1.6, "odd": 1.6, "even": 1.6, "high_odd": 1.0, "low_odd": 1.0, "high_even": 1.0, "low_even": 1.0}', 1, 100, NOW(), NOW());

-- 游戏期数表 - 记录每期游戏的状态和结果
-- DROP TABLE IF EXISTS `game_canada28_draws`;
CREATE TABLE `game_canada28_draws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号，如：3333197',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-等待开奖，1-开奖中，2-已开奖，3-已结算',
  `start_at` datetime NOT NULL COMMENT '开始投注时间',
  `end_at` datetime NOT NULL COMMENT '停止投注时间',
  `draw_at` datetime NOT NULL COMMENT '开奖时间',
  `result_numbers` json DEFAULT NULL COMMENT '开奖号码，JSON格式存储三个数字',
  `result_sum` int(3) DEFAULT NULL COMMENT '开奖结果总和(0-27)',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_period_number` (`period_number`),
  KEY `idx_status` (`status`),
  KEY `idx_draw_at` (`draw_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Canada28游戏期数表';

-- 玩家投注记录表 - 记录每个玩家的投注和结果
-- DROP TABLE IF EXISTS `game_canada28_bets`;
CREATE TABLE `game_canada28_bets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `merchant_id` varchar(36) NOT NULL COMMENT '商户ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号',
  `bet_type` varchar(50) NOT NULL COMMENT '投注类型：high/low/odd/even/num_0等',
  `bet_name` varchar(100) NOT NULL COMMENT '投注名称：High/Low/Number 0等',
  `amount` decimal(15,2) NOT NULL COMMENT '投注金额',
  `multiplier` decimal(8,2) NOT NULL COMMENT '投注时的赔率',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消',
  `ip` varchar(45) DEFAULT NULL COMMENT '投注IP地址',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_period_number` (`period_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_bet_type` (`bet_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Canada28玩家投注记录表';

-- 群组消息表 - 记录每个群组的消息
-- DROP TABLE IF EXISTS `game_group_message`;
CREATE TABLE `game_group_message` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` varchar(36) NOT NULL COMMENT '发送者ID',
  `group_id` varchar(36) NOT NULL COMMENT '群组ID',
  `message` text NOT NULL COMMENT '消息内容',
  `type` varchar(20) NOT NULL COMMENT '消息类型：text-文本，image-图片',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群组消息表';

-- 客服聊天消息表
CREATE TABLE `game_customer_service_message` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `admin_id` varchar(36) DEFAULT NULL COMMENT '管理员ID（空表示用户发送）',
  `message` text NOT NULL COMMENT '消息内容',
  `type` varchar(20) NOT NULL DEFAULT 'text' COMMENT '消息类型：text-文本，image-图片',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已读：0-未读，1-已读',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服聊天消息表';

-- 客服会话表
CREATE TABLE `game_customer_service_session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `admin_id` varchar(36) DEFAULT NULL COMMENT '当前服务的管理员ID',
  `last_message` text COMMENT '最后一条消息',
  `last_message_at` datetime COMMENT '最后消息时间',
  `unread_count` int(11) NOT NULL DEFAULT 0 COMMENT '未读消息数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '会话状态：1-活跃，2-已关闭',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服会话表';

-- 插入示例群组消息
INSERT INTO `game_group_message` (`user_id`, `group_id`, `message`, `type`, `created_at`, `updated_at`) VALUES
('bot', 'canada28_game_group', 'Welcome to Canada 28! Place your bets and good luck! 🍀', 'text', NOW(), NOW()),
('bot', 'canada28_game_group', 'Remember to place your bets before the timer runs out! ⏰', 'text', DATE_ADD(NOW(), INTERVAL 2 MINUTE), DATE_ADD(NOW(), INTERVAL 2 MINUTE));


-- 加拿大28游戏玩法配置表
-- DROP TABLE IF EXISTS `game_bingo28_bet_types`;
CREATE TABLE IF NOT EXISTS `game_bingo28_bet_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `type_name` varchar(50) NOT NULL COMMENT '玩法名称',
    `type_key` varchar(50) NOT NULL COMMENT '玩法标识',
    `description` varchar(200) DEFAULT NULL COMMENT '玩法描述',
    `odds` decimal(8,2) NOT NULL COMMENT '赔率倍数',
    `status` tinyint(2) DEFAULT 1 NOT NULL COMMENT '状态：1启用，0禁用',
    `sort` int(11) DEFAULT 0 COMMENT '排序',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime DEFAULT null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `merchant_type` (`merchant_id`, `type_key`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`),
    KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='加拿大28游戏玩法配置表';

-- 插入默认玩法数据
INSERT INTO `game_bingo28_bet_types` (`id`, `merchant_id`, `type_name`, `type_key`, `description`, `odds`, `status`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES
(124, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG', 'high', '大：14-27', 2.00, 1, 1, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(125, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL', 'low', '小：0-13', 2.00, 1, 2, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(126, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'ODD', 'odd', '单', 2.00, 1, 3, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(127, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'EVEN', 'even', '双', 2.00, 1, 4, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(128, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'TRIPES', 'triple', '豹子', 50.00, 1, 5, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(129, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'DOUBLES', 'pair', '对子', 3.00, 1, 3, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(130, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'STRAIGHT', 'straight', '顺子', 10.00, 1, 4, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(131, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG & ODD', 'high_odd', '大单', 4.20, 1, 8, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(132, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL & ODD', 'low_odd', '小单', 4.50, 1, 9, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(133, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'BIG & EVEN', 'high_even', '大双', 4.50, 1, 10, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(134, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'SMALL & EVEN', 'low_even', '小双', 4.20, 1, 11, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(135, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'MINIMUM', 'extreme_low', '极小：0-5', 10.00, 1, 1, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(136, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'MAXIMUM', 'extreme_high', '极大：22-27', 10.00, 1, 2, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(137, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 0', 'sum_0', '特码0', 500.00, 1, 14, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(138, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 1', 'sum_1', '特码1', 100.00, 1, 15, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(139, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 2', 'sum_2', '特码2', 70.00, 1, 16, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(140, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 3', 'sum_3', '特码3', 50.00, 1, 17, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(141, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 4', 'sum_4', '特码4', 30.00, 1, 18, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(142, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 5', 'sum_5', '特码5', 20.00, 1, 19, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(143, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 6', 'sum_6', '特码6', 17.00, 1, 20, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(144, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 7', 'sum_7', '特码7', 16.00, 1, 21, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(145, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 8', 'sum_8', '特码8', 15.00, 1, 22, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(146, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 9', 'sum_9', '特码9', 14.00, 1, 23, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(147, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 10', 'sum_10', '特码10', 13.00, 1, 24, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(148, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 11', 'sum_11', '特码11', 12.00, 1, 25, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(149, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 12', 'sum_12', '特码12', 12.00, 1, 26, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(150, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 13', 'sum_13', '特码13', 12.00, 1, 27, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(151, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 14', 'sum_14', '特码14', 12.00, 1, 28, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(152, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 15', 'sum_15', '特码15', 12.00, 1, 29, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(153, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 16', 'sum_16', '特码16', 12.00, 1, 30, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(154, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 17', 'sum_17', '特码17', 13.00, 1, 31, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(155, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 18', 'sum_18', '特码18', 14.00, 1, 32, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(156, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 19', 'sum_19', '特码19', 15.00, 1, 33, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(157, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 20', 'sum_20', '特码20', 16.00, 1, 34, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(158, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 21', 'sum_21', '特码21', 17.00, 1, 35, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(159, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 22', 'sum_22', '特码22', 20.00, 1, 36, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(160, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 23', 'sum_23', '特码23', 30.00, 1, 37, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(161, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 24', 'sum_24', '特码24', 50.00, 1, 38, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(162, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 25', 'sum_25', '特码25', 70.00, 1, 39, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(163, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 26', 'sum_26', '特码26', 100.00, 1, 40, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL),
(164, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'THE SUM 27', 'sum_27', '特码27', 500.00, 1, 41, '2025-09-21 00:00:54', '2025-09-21 00:00:54', NULL);

-- 动态赔率规则表 - 根据特殊条件调整赔率
-- DROP TABLE IF EXISTS `game_bingo28_dynamic_odds`;
CREATE TABLE IF NOT EXISTS `game_bingo28_dynamic_odds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `rule_name` varchar(100) NOT NULL COMMENT '规则名称',
    `trigger_condition` varchar(50) NOT NULL COMMENT '触发条件：sum_range, sum_exact, sum_in',
    `trigger_values` text COMMENT '触发条件值（JSON格式）',
    `bet_type_adjustments` text COMMENT '投注类型赔率调整（JSON格式）',
    `status` tinyint(2) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `priority` int(11) DEFAULT 0 COMMENT '优先级',
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='动态赔率规则表';

-- 插入13-14特殊赔率规则
INSERT INTO `game_bingo28_dynamic_odds` (`merchant_id`, `rule_name`, `trigger_condition`, `trigger_values`, `bet_type_adjustments`, `status`, `priority`, `created_at`, `updated_at`) VALUES
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', '13-14特殊赔率', 'sum_in', '[13, 14]', '{"high": 1.6, "low": 1.6, "odd": 1.6, "even": 1.6, "high_odd": 1.0, "low_odd": 1.0, "high_even": 1.0, "low_even": 1.0}', 1, 100, NOW(), NOW());

-- 游戏期数表 - 记录每期游戏的状态和结果
-- DROP TABLE IF EXISTS `game_bingo28_draws`;
CREATE TABLE `game_bingo28_draws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号，如：3333197',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-等待开奖，1-开奖中，2-已开奖，3-已结算',
  `start_at` datetime NOT NULL COMMENT '开始投注时间',
  `end_at` datetime NOT NULL COMMENT '停止投注时间',
  `draw_at` datetime NOT NULL COMMENT '开奖时间',
  `result_numbers` json DEFAULT NULL COMMENT '开奖号码，JSON格式存储三个数字',
  `result_sum` int(3) DEFAULT NULL COMMENT '开奖结果总和(0-27)',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_period_number` (`period_number`),
  KEY `idx_status` (`status`),
  KEY `idx_draw_at` (`draw_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bingo28游戏期数表';

-- 玩家投注记录表 - 记录每个玩家的投注和结果
-- DROP TABLE IF EXISTS `game_bingo28_bets`;
CREATE TABLE `game_bingo28_bets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `merchant_id` varchar(36) NOT NULL COMMENT '商户ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号',
  `bet_type` varchar(50) NOT NULL COMMENT '投注类型：high/low/odd/even/num_0等',
  `bet_name` varchar(100) NOT NULL COMMENT '投注名称：High/Low/Number 0等',
  `amount` decimal(15,2) NOT NULL COMMENT '投注金额',
  `multiplier` decimal(8,2) NOT NULL COMMENT '投注时的赔率',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消',
  `ip` varchar(45) DEFAULT NULL COMMENT '投注IP地址',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_period_number` (`period_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_bet_type` (`bet_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=100000086 COLLATE=utf8mb4_unicode_ci COMMENT='Bingo28玩家投注记录表';

-- Keno游戏玩法配置表 (BCLC Keno规则: 选10个号码1-80，开20个号码)
-- DROP TABLE IF EXISTS `game_keno_bet_types`;
CREATE TABLE IF NOT EXISTS `game_keno_bet_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `type_name` varchar(50) NOT NULL COMMENT '玩法名称',
    `type_key` varchar(50) NOT NULL COMMENT '玩法标识',
    `description` varchar(200) DEFAULT NULL COMMENT '玩法描述',
    `odds` decimal(10,2) NOT NULL COMMENT '赔率倍数',
    `status` tinyint(2) DEFAULT 1 NOT NULL COMMENT '状态：1启用，0禁用',
    `sort` int(11) DEFAULT 0 COMMENT '排序',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime DEFAULT null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `merchant_type` (`merchant_id`, `type_key`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`),
    KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Keno游戏玩法配置表 - BCLC规则 (1-80)';

-- BCLC Keno 赔率表 (选10个号码，基于匹配数量)
INSERT INTO `game_keno_bet_types` (`id`, `merchant_id`, `type_name`, `type_key`, `description`, `odds`, `status`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 10', 'match_10', 'Match 10 numbers - Jackpot', 100000.00, 1, 1, NOW(), NOW(), NULL),
(2, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 9', 'match_9', 'Match 9 numbers', 2500.00, 1, 2, NOW(), NOW(), NULL),
(3, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 8', 'match_8', 'Match 8 numbers', 250.00, 1, 3, NOW(), NOW(), NULL),
(4, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 7', 'match_7', 'Match 7 numbers', 25.00, 1, 4, NOW(), NOW(), NULL),
(5, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 6', 'match_6', 'Match 6 numbers', 5.00, 1, 5, NOW(), NOW(), NULL),
(6, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 5', 'match_5', 'Match 5 numbers', 1.00, 1, 6, NOW(), NOW(), NULL),
(7, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 0', 'match_0', 'Match 0 numbers - Special payout', 1.00, 1, 11, NOW(), NOW(), NULL);

-- 动态赔率规则表 - 根据特殊条件调整赔率
-- DROP TABLE IF EXISTS `game_keno_dynamic_odds`;
CREATE TABLE IF NOT EXISTS `game_keno_dynamic_odds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `rule_name` varchar(100) NOT NULL COMMENT '规则名称',
    `trigger_condition` varchar(50) NOT NULL COMMENT '触发条件：sum_range, sum_exact, sum_in',
    `trigger_values` text COMMENT '触发条件值（JSON格式）',
    `bet_type_adjustments` text COMMENT '投注类型赔率调整（JSON格式）',
    `status` tinyint(2) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `priority` int(11) DEFAULT 0 COMMENT '优先级',
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='动态赔率规则表';

-- 游戏期数表 - 记录每期游戏的状态和结果
-- DROP TABLE IF EXISTS `game_keno_draws`;
CREATE TABLE `game_keno_draws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号，如：3333197',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-等待开奖，1-开奖中，2-已开奖，3-已结算',
  `start_at` datetime NOT NULL COMMENT '开始投注时间',
  `end_at` datetime NOT NULL COMMENT '停止投注时间',
  `draw_at` datetime NOT NULL COMMENT '开奖时间',
  `result_numbers` json DEFAULT NULL COMMENT '开奖号码，JSON格式存储三个数字',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_period_number` (`period_number`),
  KEY `idx_status` (`status`),
  KEY `idx_draw_at` (`draw_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Keno游戏期数表';

-- Keno玩家投注记录表 - 记录每个玩家的投注和结果 (BCLC规则: 1-80号码)
-- DROP TABLE IF EXISTS `game_keno_bets`;
CREATE TABLE `game_keno_bets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `merchant_id` varchar(36) NOT NULL COMMENT '商户ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号',
  `selected_numbers` varchar(200) NOT NULL COMMENT '玩家选择的10个号码(1-80)，JSON格式: [1,5,12,...]',
  `drawn_numbers` varchar(200) DEFAULT NULL COMMENT '开出的20个号码(1-80)，JSON格式: [3,7,12,...]',
  `matched_numbers` varchar(200) DEFAULT NULL COMMENT '匹配的号码，JSON格式: [12,...]',
  `match_count` tinyint(2) DEFAULT 0 COMMENT '匹配数量：0-10',
  `amount` decimal(15,2) NOT NULL COMMENT '投注金额',
  `multiplier` decimal(10,2) NOT NULL COMMENT '投注时的赔率（基于匹配数量）',
  `win_amount` decimal(15,2) DEFAULT 0.00 COMMENT '中奖金额',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消',
  `ip` varchar(45) DEFAULT NULL COMMENT '投注IP地址',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `settled_at` datetime DEFAULT NULL COMMENT '结算时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_period_number` (`period_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_match_count` (`match_count`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=200000086 COLLATE=utf8mb4_unicode_ci COMMENT='Keno玩家投注记录表 - BCLC规则 (1-80)';


-- Bingo游戏玩法配置表 (BCLC Bingo规则: 选10个号码1-80，开20个号码)
-- DROP TABLE IF EXISTS `game_bingo_bet_types`;
CREATE TABLE IF NOT EXISTS `game_bingo_bet_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `type_name` varchar(50) NOT NULL COMMENT '玩法名称',
    `type_key` varchar(50) NOT NULL COMMENT '玩法标识',
    `description` varchar(200) DEFAULT NULL COMMENT '玩法描述',
    `odds` decimal(10,2) NOT NULL COMMENT '赔率倍数',
    `status` tinyint(2) DEFAULT 1 NOT NULL COMMENT '状态：1启用，0禁用',
    `sort` int(11) DEFAULT 0 COMMENT '排序',
    `created_at` datetime,
    `updated_at` datetime,
    `deleted_at` datetime DEFAULT null,
    PRIMARY KEY (`id`),
    UNIQUE KEY `merchant_type` (`merchant_id`, `type_key`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`),
    KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bingo游戏玩法配置表 - BCLC规则 (1-80)';

-- BCLC Bingo 赔率表 (选10个号码，基于匹配数量)
INSERT INTO `game_bingo_bet_types` (`id`, `merchant_id`, `type_name`, `type_key`, `description`, `odds`, `status`, `sort`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 10', 'match_10', 'Match 10 numbers - Jackpot', 100000.00, 1, 1, NOW(), NOW(), NULL),
(2, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 9', 'match_9', 'Match 9 numbers', 2500.00, 1, 2, NOW(), NOW(), NULL),
(3, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 8', 'match_8', 'Match 8 numbers', 250.00, 1, 3, NOW(), NOW(), NULL),
(4, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 7', 'match_7', 'Match 7 numbers', 25.00, 1, 4, NOW(), NOW(), NULL),
(5, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 6', 'match_6', 'Match 6 numbers', 5.00, 1, 5, NOW(), NOW(), NULL),
(6, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 5', 'match_5', 'Match 5 numbers', 1.00, 1, 6, NOW(), NOW(), NULL),
(7, 'ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Match 0', 'match_0', 'Match 0 numbers - Special payout', 1.00, 1, 11, NOW(), NOW(), NULL);

-- 动态赔率规则表 - 根据特殊条件调整赔率
-- DROP TABLE IF EXISTS `game_bingo_dynamic_odds`;
CREATE TABLE IF NOT EXISTS `game_bingo_dynamic_odds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `merchant_id` char(36) NOT NULL COMMENT '商户ID',
    `rule_name` varchar(100) NOT NULL COMMENT '规则名称',
    `trigger_condition` varchar(50) NOT NULL COMMENT '触发条件：sum_range, sum_exact, sum_in',
    `trigger_values` text COMMENT '触发条件值（JSON格式）',
    `bet_type_adjustments` text COMMENT '投注类型赔率调整（JSON格式）',
    `status` tinyint(2) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `priority` int(11) DEFAULT 0 COMMENT '优先级',
    `created_at` datetime,
    `updated_at` datetime,
    PRIMARY KEY (`id`),
    KEY `merchant_id` (`merchant_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='动态赔率规则表';

-- 游戏期数表 - 记录每期游戏的状态和结果
-- DROP TABLE IF EXISTS `game_bingo_draws`;
CREATE TABLE `game_bingo_draws` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号，如：3333197',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0-等待开奖，1-开奖中，2-已开奖，3-已结算',
  `start_at` datetime NOT NULL COMMENT '开始投注时间',
  `end_at` datetime NOT NULL COMMENT '停止投注时间',
  `draw_at` datetime NOT NULL COMMENT '开奖时间',
  `result_numbers` json DEFAULT NULL COMMENT '开奖号码，JSON格式存储三个数字',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_period_number` (`period_number`),
  KEY `idx_status` (`status`),
  KEY `idx_draw_at` (`draw_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bingo游戏期数表';

-- Bingo玩家投注记录表 - 记录每个玩家的投注和结果 (BCLC规则: 1-80号码)
-- DROP TABLE IF EXISTS `game_bingo_bets`;
CREATE TABLE `game_bingo_bets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `merchant_id` varchar(36) NOT NULL COMMENT '商户ID',
  `user_id` varchar(36) NOT NULL COMMENT '用户ID',
  `period_number` varchar(20) NOT NULL COMMENT '期号',
  `selected_numbers` varchar(200) NOT NULL COMMENT '玩家选择的10个号码(1-80)，JSON格式: [1,5,12,...]',
  `drawn_numbers` varchar(200) DEFAULT NULL COMMENT '开出的20个号码(1-80)，JSON格式: [3,7,12,...]',
  `matched_numbers` varchar(200) DEFAULT NULL COMMENT '匹配的号码，JSON格式: [12,...]',
  `match_count` tinyint(2) DEFAULT 0 COMMENT '匹配数量：0-10',
  `amount` decimal(15,2) NOT NULL COMMENT '投注金额',
  `multiplier` decimal(10,2) NOT NULL COMMENT '投注时的赔率（基于匹配数量）',
  `win_amount` decimal(15,2) DEFAULT 0.00 COMMENT '中奖金额',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending-等待开奖，win-已中奖，lose-未中奖，cancel-已取消',
  `ip` varchar(45) DEFAULT NULL COMMENT '投注IP地址',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `settled_at` datetime DEFAULT NULL COMMENT '结算时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_period_number` (`period_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_match_count` (`match_count`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=300000086 COLLATE=utf8mb4_unicode_ci COMMENT='Bingo玩家投注记录表 - BCLC规则 (1-80)';
