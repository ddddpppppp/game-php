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
('recharge_setting', '充值设置', '配置充值相关参数', '{"min_amount":10,"max_amount":10000,"usdt_gift_rate":2,"cashapp_gift_rate":0}', 1, 1, NOW(), NOW()),
('withdraw_setting', '提现设置', '配置提现相关参数', '{"min_amount":50,"max_amount":50000,"usdt_fee_rate":2, "cashapp_fee_rate":0, "daily_limit":3}', 1, 4, NOW(), NOW());

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
  `type` varchar(20) NOT NULL COMMENT '变动类型：gift-赠送, deposit-充值, withdraw-提现, game_bet-投注, game_win-收益',
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

-- DROP TABLE IF EXISTS `game_payment_channel`;
CREATE TABLE
    IF NOT EXISTS `game_payment_channel` (
        `id` int (11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL COMMENT '名称',
        `belong_admin_id` char(36) NOT NULL COMMENT '所属管理者id',
        `type` Enum ('paypal', 'cashapp', 'usdt') NOT NULL COMMENT '类型',
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
INSERT INTO `game_canada28_bet_types` (`merchant_id`, `type_name`, `type_key`, `description`, `odds`, `status`, `sort`, `created_at`, `updated_at`) VALUES
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Big', 'big', '大：14-27', 3.00, 1, 1, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Small', 'small', '小：0-13', 3.00, 1, 2, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Odd', 'odd', '单', 3.00, 1, 3, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Even', 'even', '双', 3.00, 1, 4, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Triple', 'triple', '豹子', 50.00, 1, 5, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Double', 'double', '对子', 3.00, 1, 6, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Straight', 'straight', '顺子', 10.00, 1, 7, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Big Odd', 'big_odd', '大单', 6.50, 1, 8, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Small Odd', 'small_odd', '小单', 6.50, 1, 9, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Big Even', 'big_even', '大双', 6.50, 1, 10, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Small Even', 'small_even', '小双', 6.50, 1, 11, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Extreme Small', 'extreme_small', '极小：0-5', 10.00, 1, 12, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'Extreme Big', 'extreme_big', '极大：22-27', 10.00, 1, 13, NOW(), NOW()),

-- 特码投注 (0-27)
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 0', 'sum_0', '特码0', 280.00, 1, 14, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 1', 'sum_1', '特码1', 280.00, 1, 15, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 2', 'sum_2', '特码2', 60.00, 1, 16, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 3', 'sum_3', '特码3', 40.00, 1, 17, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 4', 'sum_4', '特码4', 30.00, 1, 18, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 5', 'sum_5', '特码5', 25.00, 1, 19, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 6', 'sum_6', '特码6', 22.00, 1, 20, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 7', 'sum_7', '特码7', 20.00, 1, 21, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 8', 'sum_8', '特码8', 18.00, 1, 22, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 9', 'sum_9', '特码9', 16.00, 1, 23, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 10', 'sum_10', '特码10', 15.00, 1, 24, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 11', 'sum_11', '特码11', 14.00, 1, 25, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 12', 'sum_12', '特码12', 13.00, 1, 26, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 13', 'sum_13', '特码13', 12.00, 1, 27, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 14', 'sum_14', '特码14', 12.00, 1, 28, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 15', 'sum_15', '特码15', 13.00, 1, 29, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 16', 'sum_16', '特码16', 14.00, 1, 30, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 17', 'sum_17', '特码17', 15.00, 1, 31, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 18', 'sum_18', '特码18', 16.00, 1, 32, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 19', 'sum_19', '特码19', 18.00, 1, 33, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 20', 'sum_20', '特码20', 20.00, 1, 34, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 21', 'sum_21', '特码21', 22.00, 1, 35, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 22', 'sum_22', '特码22', 25.00, 1, 36, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 23', 'sum_23', '特码23', 30.00, 1, 37, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 24', 'sum_24', '特码24', 40.00, 1, 38, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 25', 'sum_25', '特码25', 60.00, 1, 39, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 26', 'sum_26', '特码26', 280.00, 1, 40, NOW(), NOW()),
('ad22ab51-1637-42c5-a82f-4b51382f7bc3', 'The Sum 27', 'sum_27', '特码27', 280.00, 1, 41, NOW(), NOW());

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

-- 插入示例群组消息
INSERT INTO `game_group_message` (`user_id`, `group_id`, `message`, `type`, `created_at`, `updated_at`) VALUES
('bot', 'canada28_game_group', 'Welcome to Canada 28! Place your bets and good luck! 🍀', 'text', NOW(), NOW()),
('bot', 'canada28_game_group', 'Remember to place your bets before the timer runs out! ⏰', 'text', DATE_ADD(NOW(), INTERVAL 2 MINUTE), DATE_ADD(NOW(), INTERVAL 2 MINUTE));
