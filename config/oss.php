<?php

/**
 * 阿里云OSS配置
 *
 * enable: 是否启用OSS，true为启用，false为禁用
 * access_key_id: 阿里云访问密钥ID
 * access_key_secret: 阿里云访问密钥Secret
 * bucket: OSS存储空间名称
 * endpoint: OSS访问域名（地域节点）
 * timeout: 请求超时时间（秒）
 * url: 自定义访问URL（若配置此项，将优先使用此URL作为文件访问地址）
 */
return [
    // 是否启用OSS
    "enable" => false,

    // 阿里云OSS访问密钥
    "access_key_id" => getenv('ALIYUN_ACCESS_KEY_ID') ?: '',
    "access_key_secret" => getenv('ALIYUN_ACCESS_KEY_SECRET') ?: '',

    // OSS存储空间配置
    "bucket" => getenv('ALIYUN_BUCKET') ?: '',
    "endpoint" => getenv('ALIYUN_ENDPOINT') ?: '',

    // 请求超时时间（秒）
    "timeout" => 60,

    // 自定义访问URL，如不配置则使用默认OSS域名
    // "url" => "https://go.aiservice.work",
];
