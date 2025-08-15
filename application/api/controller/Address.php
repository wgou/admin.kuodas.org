<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;

class Address extends Api{

    protected $model = null;
	
	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['view'];
	
	
	use \app\api\library\buiapi\traits\Api;
	
    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\common\model\Address;
	}
	
	
}