<?php

namespace app\admin\controller\customcharts;

use app\common\controller\Backend;
use think\Config;
use think\Db;

/**
 * 图表统计管理
 *
 * @icon fa fa-circle-o
 */
class Chart extends Backend
{
    
    /**
     * Chart模型对象
     * @var \app\admin\model\customcharts\Chart
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\customcharts\Chart;
        $this->view->assign("typeTotalList", $this->model->getTypeTotalList());
        $this->view->assign("chartTypeList", $this->model->getChartTypeList());

        $dbname = Config::get('database.database');
        $list = \think\Db::query(
            "SELECT TABLE_NAME,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=?",
            [$dbname]
        );
        $this->view->assign("tableList", $list);
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
