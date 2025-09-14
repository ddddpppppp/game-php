<?php
namespace app\index\controller;

use app\common\model\MultiDbExample;
use think\Controller;
use think\Db;

/**
 * 多数据库连接示例控制器
 */
class MultiDbController extends Controller
{
    /**
     * 默认数据库示例
     */
    public function defaultDb()
    {
        $model = new MultiDbExample();
        $data = $model->queryDefault();

        return json($data);
    }

    /**
     * 第二个数据库示例
     */
    public function secondDb()
    {
        $model = new MultiDbExample();
        $data = $model->querySecondDb();

        return json($data);
    }

    /**
     * 多数据库操作示例
     */
    public function multiDb()
    {
        $model = new MultiDbExample();
        $data = $model->multiDbOperation();

        return json($data);
    }

    /**
     * 动态选择数据库示例
     * 
     * @param string $db 数据库连接名称
     * @param string $table 表名
     */
    public function dynamicDb($db = 'mysql', $table = 'users')
    {
        $model = new MultiDbExample();
        $data = $model->dynamicSwitchDb($db, $table);

        return json($data);
    }

    /**
     * 在控制器中直接使用多数据库
     */
    public function directMultiDb()
    {
        // 默认数据库查询
        $defaultData = Db::table('intelligent_users')->select();

        // 第二个数据库查询
        $secondData = Db::connect('gf')->table('gf_users')->select();

        return json([
            'default_db' => $defaultData,
            'second_db' => $secondData
        ]);
    }

    /**
     * 事务示例
     */
    public function transaction()
    {
        $model = new MultiDbExample();
        $result = $model->transactionExample();

        return json(['success' => $result]);
    }

    /**
     * 跨数据库事务示例
     */
    public function multiDbTransaction()
    {
        $model = new MultiDbExample();
        $result = $model->multiDbTransaction();

        return json(['success' => $result]);
    }
}