<?php

namespace app\admin\controller\game;

use app\common\controller\Backend;

/**
 * 抽奖记录
 *
 * @icon fa fa-circle-o
 */
class Lottery extends Backend
{

    /**
     * 模型对象
     * @var \app\admin\model\game\Lottery
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\game\Lottery;
    }

//    public function index()
//    {
//        $this->request->filter('trim,strip_tags,htmlspecialchars');
//        if ($this->request->isAjax()) {
//            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
//            $total = $this->model->where($where)->order($sort, $order)->count();
//            $list = $this->model->where($where)->order($sort, $order)->limit($offset, $limit)->select();
//            $list = collection($list)->toArray();
//            foreach ($list as $key=>$item) {
//                    $list[$key]['lottery_type'] = $this->get_lottery_typename($item['lottery_type']);
//                    if(empty($item['memo'])){
//                        $list[$key]['memo'] = $this->get_lottery_typename($item['lottery_type']).$item['price'];
//                    }
//            }
//            $result = array("total" => $total, "rows" => $list);
//            return json($result);
//        }
//        return $this->view->fetch();
//    }

    public function get_lottery_typename($type){
        if ($type == 1){
            return '消费券';
        } elseif ($type == 2){
            return '商城经验';
        }elseif ($type == 3){
            return '项目收益';
        }elseif ($type == 4){
            return "现金奖";
        }elseif ($type == 5){
            return "实物奖";
        }

    }

}
