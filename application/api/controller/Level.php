<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;

class Level extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['index','add','edit','view'];
	protected $_search_field = [];


	use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\Level;
	}


}
