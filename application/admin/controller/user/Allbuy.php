<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 购买记录管理
 *
 * @icon fa fa-money
 */
class Allbuy extends Backend
{

    /**
     * 订单模型
     * @var \app\admin\model\user\Allbuy
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Allbuy;
    }

    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        $params = json_decode(input('filter'), true);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->alias('o')
                ->join("user u", "o.user_id = u.id", "left")
                ->field('u.*,o.user_id,sum(o.price) as allbuy')
                ->where($where)
                ->order($sort, $order)
                ->group('user_id')
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

}
