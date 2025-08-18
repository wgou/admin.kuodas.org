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
class BankCardInfo extends Backend
{

    /**
     * Banner模型对象
     * @var \app\admin\model\BankCardInfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
       
        $this->model = new \app\admin\model\BankCardInfo;
        $this->view->assign("statusList", $this->model->getStatusList());
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
            $w=$params;
            if(isset($params['id']) && $params['id'] != ''){
                $w['c.id'] = ['eq',$params['id']];
            }
            
            $list = Db::name('payment')
                    ->alias('c')
                    ->where($w)
                    ->field('c.*')
                    ->order('c.id desc')
                    ->limit($offset, $limit)
                    ->select();
            $total = Db::name('payment')
                    ->alias('c')
                    ->where($w)
                    ->field('c.*')
                    ->order('c.id desc')
                    ->count();
            $list = $list;
 
             $result = array("total" => $total, "rows" => $list);
 
             return json($result);
         }
         return $this->view->fetch();
     }
}
