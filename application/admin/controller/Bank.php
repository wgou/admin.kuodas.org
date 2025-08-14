<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use PSpell\Config;
use think\Db;

/**
 * banner图管理
 *
 * @icon fa fa-circle-o
 */
class Bank extends Backend
{

    /**
     * Banner模型对象
     * @var \app\admin\model\Bank
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
       
        $this->model = new \app\admin\model\Bank;
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


      public function index()
     {
 
         //设置过滤方法
         $this->request->filter(['strip_tags']);
         if ($this->request->isAjax()) {
            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $params = json_decode(input('filter'), true);
            $w= [];
            if($params && count($params) > 0){
                foreach ($params as $key => $value) {
                    if(in_array($key,['bank_name','bank_zhi','bank_name','bank_username','user_id'])){
                        $w['b.'.$key] = $value;
                    }else{
                        $w['a.'.$key] = $value;
                    }
                }
                // var_dump($w);
            }
            // var_dump($w);
            $list = Db::name('bank')
                    ->alias('b')
                    ->join('user a', 'b.user_id=a.id', 'left')
                    ->where($w)
                    ->field('a.username ,a.mobile,a.id as user_id,b.id,b.bank_username,b.bank_name,b.bank_number,b.bank_zhi')
                    ->order('b.id desc')
                    ->limit($offset, $limit)
                    ->select();
            $total = Db::name('bank')
                    ->alias('b')
                    ->join('user a', 'b.user_id=a.id', 'left')
                    ->where($w)
                    ->field('b.id')
                    ->count();
            $list = $list;
 
             $result = array("total" => $total, "rows" => $list);
 
             return json($result);
         }
         return $this->view->fetch();
     }

     public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            return parent::edit($ids);
        }
        $this->view->assign('row',$row);
        $this->view->assign('title', __('Edit'));
        return $this->view->fetch();
    }
}
