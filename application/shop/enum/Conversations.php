<?php

namespace app\shop\enum;

class Conversations
{

    // 对话状态
    public const STATUS_RAW = 'raw';
    public const STATUS_CLEANED = 'cleaned';
    public const STATUS_ANNOTATED = 'annotated';
    public const STATUS_ARCHIVED = 'archived';

    // 对话状态对应的中文
    public static $statusMap = [
        self::STATUS_RAW => '待清洗',
        self::STATUS_CLEANED => '已清洗待标注',
        self::STATUS_ANNOTATED => '已标注',
        self::STATUS_ARCHIVED => '已完成',
    ];

    // 对话状态对应的element-ui的tag颜色
    public static $statusColorMap = [
        self::STATUS_RAW => 'info',
        self::STATUS_CLEANED => 'warning',
        self::STATUS_ANNOTATED => 'primary',
        self::STATUS_ARCHIVED => 'success',
    ];
}