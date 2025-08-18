<?php

namespace app\admin\controller\customcharts;

use app\common\controller\Backend;
use think\Config;
use think\Db;

/**
 * 排行统计管理
 *
 * @icon fa fa-circle-o
 */
class Ranking extends Backend
{
    
    /**
     * Ranking模型对象
     * @var \app\admin\model\customcharts\Ranking
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\customcharts\Ranking;
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
