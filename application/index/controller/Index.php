<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        header('Location: http://127.0.0.1');
        // return $this->view->fetch();
    }

}
