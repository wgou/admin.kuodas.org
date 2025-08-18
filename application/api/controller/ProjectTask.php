<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;

class ProjectTask extends Api{

    protected $model = null;
	
	protected $noNeedRight = '*';
	protected $noNeedLogin = [];
	protected $_allow_func = ['index','add','edit','view'];
	
	
	use \app\api\library\buiapi\traits\Api;
	
    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\ProjectTask;
	}
	
	
}