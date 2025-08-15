<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class LeescoreAddress extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = [];
	protected $_allow_func = ['index','add','edit','view','del'];


	use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\LeescoreAddress;
	}
    /**
     * 公共方法-列表
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',',$this->_search_field));
        $this->_init_where['uid']=$this->auth->id; 
        $this->_init_where['isdel']=0; 
        $item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));
        $this->success('数据列表', $list);
    }


    /**
     * 公共方法-添加
     */
    public function add()
    {
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        $user = Db::name('user')->where('id',$this->auth->id)->find();
        if($params['mobile'] != $user['mobile']){
            $this->error('电话号码与注册电话不一致');
        }
         if($params['truename'] != $user['nickname']){
            $this->error('收款人姓名与账户姓名不符');
        }
        if ($params) {
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            Db::startTrans();
            $params = $this->__handle_add_before__($params);
            $this->__handle_filter__('add', $params);
            $params['uid']=$this->auth->id;
            $this->model->save($params);
            if (isset($this->model->id) && !empty($this->model->id)) {
                $result = $this->__handle_add_after__($this->model->id, $params);
                if ($result) {
                    Db::commit();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->success('添加数据成功', $this->_return_data);
                } else {
                    Db::rollback();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->error('添加数据失败', $this->_return_data);
                }
            } else {
                Db::rollback();
                $this->error($this->model->getError());
            }
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    public function edit()
    {
        $id = $this->request->param('id');
        if(!$id) $this->error(__('field id require.'));
        if(!$this->auth->id)  $this->error(__('error user edit'));
        $info = $this->model->where("id = $id")->find();
        if(!$info)  $this->error(__('record not found'));
        $data = $this->request->param();
        $user = Db::name('user')->where('id',$this->auth->id)->find();
        if($data['mobile'] != $user['mobile']){
            $this->error('电话号码与注册电话不一致');
        }
        if($data['truename'] != $user['nickname']){
            $this->error('收款人姓名与账户姓名不符');
        }
        unset($data['address_id']);
        unset($data['id']);
        $w['uid'] = $this->auth->id;
        $w['id'] = $id;
        $result =$this->model->where($w)->update($data);
        $this->success(__('success edit'),$result);
    }

    public function del()
    {
        // $id = input('post.id');
        // if(!$id)  $this->error(__('field id require.'));
        // $info = $this->model->where("id = $id")->find();
        // if(!$info)  $this->error(__('record not found'));
        // if(!$this->auth->id)  $this->error(__('error user delete'));
        // $w['id'] = $id;
        // $w['uid'] = $this->auth->id;
        // if ($result = $this->model->where($w)->update(['isdel' => 1])) {
        //   $this->success(__('success delete'),$result);
        //     return true;
        // } else {
        //     $this->error(__('error delete.'));
        // }
        
    }

}
