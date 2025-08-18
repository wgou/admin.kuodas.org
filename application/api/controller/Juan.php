<?php

namespace app\api\controller;

use app\common\controller\Api;

class Juan extends Api
{
    protected $model = null;
    protected $noNeedRight = '*';
    protected $noNeedLogin = ['*'];
    
    // 定义允许的搜索字段
    protected $_search_field = ['user_id', 'order_no', 'status', 'love_data_id'];
    
    // 定义初始化的查询条件
    protected $_init_where = [];
    
    // 定义关联查询
    protected $_with = ['love_data'];
    
    // 定义视图显示的字段
    protected $_view_field = '*';
    
    // 定义列表显示的字段
    protected $_index_field = '*';
    
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\Juan;
        // 只显示当前用户的记录
        $this->_init_where['user_id'] = $this->auth->id;
    }

    /**
     * 获取用户捐献记录列表
     */
    public function index()
    {
        $where = [];
        // 只查询当前用户的记录
        $where['user_id'] = $this->auth->id;
        
        // 可选的时间范围过滤
        $start_time = $this->request->get('start_time', '');
        $end_time = $this->request->get('end_time', '');
        if ($start_time) {
            $where['createtime'] = ['>=', strtotime($start_time)];
        }
        if ($end_time) {
            $where['createtime'] = ['<=', strtotime($end_time . ' 23:59:59')];
        }
        
        // 可选的项目ID过滤
        $love_data_id = $this->request->get('love_data_id', '');
        if ($love_data_id) {
            $where['love_data_id'] = $love_data_id;
        }

        $list = $this->model
            ->where($where)
            ->with(['love_data']) // 关联爱心项目信息
            ->order('createtime desc')
            ->paginate();

        $this->success('获取成功', $list);
    }
}
