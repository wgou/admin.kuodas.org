<?php

namespace app\admin\controller\customcharts;

use app\common\controller\Backend;
use think\Config;
use think\Db;

/**
 * 总数统计管理
 *
 * @icon fa fa-circle-o
 */
class Totalnumber extends Backend
{
    
    protected $noNeedRight = ['get_field_list'];
    
    /**
     * Totalnumber模型对象
     * @var \app\admin\model\customcharts\Totalnumber
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\customcharts\Totalnumber;
        $this->view->assign("typeTotalList", $this->model->getTypeTotalList());
        $this->view->assign("typeTimeList", $this->model->getTypeTimeList());

        $dbname = Config::get('database.database');
        $list = \think\Db::query(
            "SELECT TABLE_NAME,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=?",
            [$dbname]
        );
        $this->view->assign("tableList", $list);
    }

    /**
     * 获取字段列表
     * @internal
     */
    public function get_field_list()
    {
        $dbname = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $table = $this->request->request('table');
        //从数据库中获取表字段信息
        $sql = "SELECT * FROM `information_schema`.`columns` "
            . "WHERE TABLE_SCHEMA = ? AND table_name = ? "
            . "ORDER BY ORDINAL_POSITION";
        //加载主表的列
        $columnList = Db::query($sql, [$dbname, $table]);
        $fieldlist = [];
        $commentlist = [];
        $typelist = [];
        foreach ($columnList as $index => $item) {
            $fieldlist[] = $item['COLUMN_NAME'];
            $commentlist[] = $item['COLUMN_COMMENT'];
            $typelist[$item['COLUMN_NAME']] = $item['DATA_TYPE'];
        }
        $this->success("", null, ['fieldlist' => $fieldlist, 'commentlist' => $commentlist, 'typelist' => $typelist]);
    }

    /**
     * 添加
     */
    public function add($ids = null)
    {
        if ($ids) {
            $row = $this->model->get($ids);
            $this->view->assign("row", $row);
        }
        return parent::add();
    }
}
