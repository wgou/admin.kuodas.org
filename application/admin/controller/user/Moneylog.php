<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 余额变动管理
 *
 * @icon fa fa-money
 */
class Moneylog extends Backend
{

    /**
     * Log模型对象
     * @var \app\admin\model\user\money\Log
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\money\Log;
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            
            // 获取type参数
            $type = $this->request->param('type');
            
            // 先返回页面框架，不查询数据
            if ($this->request->param('init') == 1) {
                return json(['total' => 0, 'rows' => []]);
            }
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            // 根据type参数筛选不同类型的记录
            if ($type) {
                $this->model->where('type', $type);
            }
            
            // 限制每次最多查询50条数据
            $limit = min($limit, 50);
            
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $key => $value) {
                $list[$key]['createtime'] = date("Y-m-d H:i:s", $value['createtime']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    
    public function add()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a");
            $user_id = isset($row['user_id']) ? $row['user_id'] : 0;
            $money = isset($row['money']) ? $row['money'] : 0;
            $memo = isset($row['memo']) ? $row['memo'] : '';
            if (!$user_id || !$money) {
                $this->error("金额和会员ID不能为空");
            }
            \app\common\model\User::money($money, $user_id, $memo);
            $this->success("添加成功");
        }
        return parent::add();
    }

}
