<?php

namespace app\admin\controller\customcharts;

use app\common\controller\Backend;
use think\Config;
use think\Db;
use addons\customcharts\library\Core as Customcharts;

/**
 * 显示统计结果
 *
 * @icon fa fa-circle-o
 */
class Show extends Backend
{
    public function index()
    {
        if ($this->request->isPost()) {
            //选择时间重新查询图表统计
            $date = $this->request->post('date', '');
            $totalChart = Customcharts::totalChart($date);
            $this->success('', '', $totalChart);
        }

        //数量统计
        $totalNumber = Customcharts::totalNumber();
        $this->view->assign("totalNumber", $totalNumber);

        //图表统计
        $totalChart = Customcharts::totalChart();
        $this->view->assign("totalChart", $totalChart);
        $this->assignconfig('totalChart', $totalChart);

        //排行统计
        $totalRanking = Customcharts::totalRanking();
        $this->view->assign("totalRanking", $totalRanking);

        return $this->view->fetch();
    }
}
