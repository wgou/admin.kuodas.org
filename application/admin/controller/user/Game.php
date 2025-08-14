<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 用户摊位
 *
 * @icon fa fa-circle-o
 */
class Game extends Backend
{

    /**
     * Game模型对象
     * @var \app\admin\model\user\Game
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Game;
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['mobile']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }
    
     /**
     * 查看
     */
    public function level()
    {
       
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {

            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $params = json_decode(input('filter'), true);
            $op = json_decode(input('op'), true);
            if (count($params) > 0) {
                $new_params = $new_op = [];
                foreach ($params as $key => $value) {
                    if ($key == 'nickname') {
                        $new_params['aa.nickname'] = $value;
                        $new_op['aa.nickname'] = $op[$key];
                    } else {
                        $new_params['aa.' . $key] = $value;
                        $new_op['aa.' . $key] = $op[$key];
                    }
                }
                $w = $this->rewriteQuery($new_params, $new_op);
            } else {
                $w['aa.id'] = array('>', 0);

            }
            $w['aa.is_rot'] =1;
            $total = Db::name('user')
                ->alias('aa')
                ->field('aa.*')
                ->where($w)
                ->count();
            $list = Db::name('user')
                ->alias('aa')
                ->field('aa.*')
                ->where($w)
                ->order('aa.score desc')
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

}
