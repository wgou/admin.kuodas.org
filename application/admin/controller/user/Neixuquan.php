<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use think\Db;

/**
 * 内容管理
 *
 * @icon fa fa-circle-o
 */
class Neixuquan extends Backend
{
    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname,mobile,neixuquan';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;


    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
    }

    public function index(){
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }
}
