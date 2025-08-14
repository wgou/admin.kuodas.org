<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 内容管理
 *
 * @icon fa fa-circle-o
 */
class Message extends Backend
{

    /**
     * Message模型对象
     * @var \app\admin\model\Message
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Message;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("typeList", $this->model->getMsgList());
    }

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
            $list = Db::name('app_message')
                    ->alias('c')
                    ->where($w)
                    ->field('c.*')
                    ->order('c.id desc')
                    ->limit($offset, $limit)
                    ->select();
            $total = Db::name('app_message')
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
     
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params['user_id'] = empty($params['user_id']) ? 0 : $params['user_id'];
                $params['json_active'] = '[]';
                $params['createtime'] = time();
                $data = Db::name('app_message')
                    ->insert($params);
                if ($data) {
                    $this->success();
                } else {
                    $this->error();
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    


}
