<?php

/**
 * Email configuration for verification codes
 * 邮件验证码配置
 */

return [
    // SMTP服务器配置
    'smtp_host'     => 'mail.game-hub.cc',           // SMTP服务器地址
    'smtp_port'     => 465,                     // SMTP端口号
    'smtp_username' => 'secure@game-hub.cc',    // 发件人邮箱 (需要修改)
    'smtp_password' => 'HsdfhCjsdsd2323',    // 邮箱授权码 (需要修改)
    'smtp_secure'   => 'ssl',                  // 加密方式: tls/ssl
    'from_name'     => 'Canada28',      // 发件人名称
    // 其他常用邮箱配置示例：
    // 
    // QQ邮箱 (推荐):
    // 'smtp_host' => 'smtp.qq.com',
    // 'smtp_port' => 587, (TLS) 或 465 (SSL)
    // 'smtp_secure' => 'tls', 或 'ssl'
    // 
    // 163邮箱:
    // 'smtp_host' => 'smtp.163.com',
    // 'smtp_port' => 25, 或 994 (SSL)
    // 'smtp_secure' => '', 或 'ssl'
    //
    // Gmail:
    // 'smtp_host' => 'smtp.gmail.com',
    // 'smtp_port' => 587, (TLS) 或 465 (SSL)
    // 'smtp_secure' => 'tls', 或 'ssl'
];
