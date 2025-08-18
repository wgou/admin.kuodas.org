<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class UserJingyanlog extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['index','add','edit','view'];
    protected $_search_field = ['createtime'];



    use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\UserJingyanLog();
	}

    /**
     * 公共方法-列表
     */
    public function index()
    {
        $filter = $this->request->param('filter');
        $filter = json_decode(html_entity_decode($filter), true);
    
        $page = $this->request->param('page') ?? 1;
        $page = $page == '' ? 1 : (int)$page;
    
        $userId = $this->auth->id;
        $itemsPerPage = 20;
        $offset = ($page - 1) * $itemsPerPage;
    
        // Prepare conditions
        $conditions = [
            'user_id' => $userId,
        ];
    
        if (!empty($filter['createtime'])) {
            $dateRange = explode(',', $filter['createtime']);
            $conditions['createtime'] = ['between', [$dateRange[0], $dateRange[1] + 86400]];
        }
    
        // Get total count
        $total = Db::name('user_jingyan_log')->where($conditions)->count('id');
    
        // Fetch data
        $info = Db::name('user_jingyan_log')
            ->where($conditions)
            ->field('id, after, before, createtime, score, memo, user_id')
            ->order('createtime desc')
            ->limit($offset, $itemsPerPage)
            ->select();
    
        // Format createtime
        foreach ($info as $key => $value) {
            $info[$key]['createtime'] = date('Y-m-d H:i', $value['createtime']);
        }
    
        // Prepare list
        $list = [
            'total' => $total,
            'per_page' => $itemsPerPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $itemsPerPage),
            'rows' => $info,
        ];
    
        $this->success('数据列表', $list);
    }

}
