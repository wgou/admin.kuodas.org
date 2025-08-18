<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class UserMoneylog extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['index','add','edit','view'];
    protected $_search_field = ['createtime','type'];



    use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\UserMoneyLog();
	}

    /**
     * 公共方法-列表
     */
    // public function index()
    // {
    //     $filter = $this->request->param('filter');
    //     $filter = html_entity_decode($filter);
    //     $filter = json_decode($filter, true);
    //     $p = $this->request->param('page') ?? 1;
    //     $p = $p == '' ? 1 : $p;
    //     $user_id =$this->auth->id;
    //     $items_per_page = 20;
    //     $offset = ($p - 1) * $items_per_page;
        
    //      //condition where
    //     $w['user_id'] = $user_id;
    //     $w['type'] = $filter['type'];
    //     if(isset($filter['createtime'])){
    //         // $w['createtime'] = ["between",$filter['createtime']];
    //         $date = explode(',',  $filter['createtime']);
    //         $w['createtime'] = ["between", [$date[0], $date[1] + 86400]];
    //     }
    //     $total = Db::name('user_money_log')->where($w)->count('id');
    //     $info = Db::name('user_money_log')
    //         ->where($w)
    //         ->field('id,after,before,createtime,memo,money,money1,money2,type,user_id')
    //         ->order('id desc')
    //         ->limit($offset,$items_per_page)
    //         ->select();
    //     foreach ($info as $key => $value) {
    //         $info[$key]['createtime'] = date('Y-m-d H:i', $value['createtime']);
    //     }
    //     $list = [
    //         "total" => $total,
    //         "per_page" => $items_per_page,
    //         "current_page" => (int) $p,
    //         "last_page"  => ceil($total / $items_per_page),
    //         'rows' => $info
    //     ];
    //     $list['allfanli'] = Db::table('fa_user_money_log')->where('user_id', $this->auth->id)->where('type', 'fanli')->where('money', '>',0)->sum('money');
    //     $list['alltui'] = Db::table('fa_user_money_log')->where('user_id', $this->auth->id)->where('type', 'tui')->where('money', '>',0)->sum('money');
    //     $this->success('数据列表', $list);
    // }
    
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
            'type' => $filter['type'] ?? null,
        ];
        // if ($conditions['type']=='fanli') {
        //     $conditions['money2']=['>',0];
        // }
        if (!empty($filter['createtime'])) {
            $dateRange = explode(',', $filter['createtime']);
            $conditions['createtime'] = ['between', [$dateRange[0], $dateRange[1] + 86400]];
        }
    
        // Get total count
        $total = Db::name('user_money_log')->where($conditions)->count('id');
    
        // Fetch data
        $info = Db::name('user_money_log')
            ->where($conditions)
            ->field('id, after, before, createtime, memo, money, money1, money2, type, user_id')
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
    
        // Additional sums
        $list['allfanli'] = Db::table('fa_user_money_log')
            ->where('user_id', $userId)
            ->where('type',  $filter['type'])
            ->where('money', '>', 0)
            ->sum('money');
    
        $list['alltui'] = Db::table('fa_user_money_log')
            ->where('user_id', $userId)
            ->where('type', 'tui')
            ->where('money', '>', 0)
            ->sum('money');
    
        $this->success('数据列表', $list);
    }

    public function index_old()
    {
        $this->relationSearch = true;
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',', $this->_search_field));


        $this->_init_where['user_id'] = $this->auth->id;
        $item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));
//        foreach ($list['rows'] as $k => $y) {
//            $list['rows'][$k]['idd'] = 1;
//        }
        $list['allfanli'] = Db::table('fa_user_money_log')->where('user_id', $this->auth->id)->where('type', 'fanli')->where('money', '>',0)->sum('money');
        $list['alltui'] = Db::table('fa_user_money_log')->where('user_id', $this->auth->id)->where('type', 'tui')->where('money', '>',0)->sum('money');
        $this->success('数据列表', $list);
    }

}
