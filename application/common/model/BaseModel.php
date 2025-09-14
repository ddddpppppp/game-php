<?php

namespace app\common\model;

use think\Model;

class BaseModel extends Model
{
    public static function update($data = [], $where = [], $allowField = false)
    {
        return parent::update($data, $where, $allowField);
    }
}
