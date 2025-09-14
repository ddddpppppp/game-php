<?php

namespace app\common\model;

use app\common\model\BaseModel;

/**
 * @property integer $id id
 * @property string $username 用户名/手机号/邮箱
 * @property string $type bot/user
 * @property string $password 加密后的密码
 * @property string $balance 余额
 * @property string $balance_frozen 冻结余额
 * @property string $nickname 昵称
 * @property string $avatar 头像URL
 * @property integer $parent_id 邀请人ID
 * @property integer $status 状态 (1:正常, 0:禁用)
 * @property \DateTime $created_at 创建时间
 * @property \DateTime $updated_at 更新时间
 * @property \DateTime $deleted_at 删除时间
 */
class Users extends BaseModel
{
    use \think\model\concern\SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $connection = 'mysql';
    protected $table = 'game_users';

    // 主键字段
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 创建时间字段
    protected $createTime = 'created_at';

    // 更新时间字段
    protected $updateTime = 'updated_at';

    // 时间字段格式
    protected $dateFormat = 'Y-m-d H:i:s';

    // 字段类型转换
    protected $type = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // 只读字段
    protected $readonly = ['id'];

    // 隐藏字段
    protected $hidden = ['password'];
}
