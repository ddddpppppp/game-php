<?php

namespace app\shop\service;

use app\shop\enum\Role as EnumRole;

class Role
{

    public static function getAllRole($roleType, $merchantId)
    {
        if ($roleType == 3) {
            $list = \app\shop\model\Role::where(['merchant_id' => $merchantId, 'type' => EnumRole::AGENT_ROLE])->field("id,name,access")->select()->toArray();
        } else {
            $list = \app\shop\model\Role::where(['merchant_id' => $merchantId])->field("id,name,access")->select()->toArray();
        }
        return $list;
    }

    public static function getRole($id)
    {
        /** @var \app\shop\model\Role|null $item */
        $item = \app\shop\model\Role::get($id);
        if (empty($item)) {
            return null;
        }
        $item->access = explode(',', $item->access);
        return $item;
    }
}
