<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;

class Command extends Api{

    protected $model = null;
	
	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['index','add','edit','view','del'];
	protected $_search_field = ['id','type','params','command','content','executetime','createtime','updatetime','status'];
	
	use \app\api\library\buiapi\traits\Api;
	
    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\common\model\Command;
	}
	
	    /**
     * 列表
     */
    public function index(){
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',',$this->_search_field));
        $list = $this->model->where($where)->where($mixWhere)->order($sort, $order)->paginate($limit);
        foreach ($list as $row) {
            $row->visible(['id','type','params','command','content','executetime','createtime','updatetime','status']);
            
        }
		$list = $this->__handle_index__($list);
        return $this->success('数据列表',$list);
    }
}