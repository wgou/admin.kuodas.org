<?php

namespace app\admin\controller\csmip;

use addons\csmip\library\CsmBackend;
use addons\csmip\library\Csmip;

/**
 * 导入IP包
 *
 * @icon fa fa-circle-o
 */
class Data extends CsmBackend
{
    
    /**
     * Data模型对象
     * @var \app\admin\model\csmip\Data
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\csmip\Data;
        $this->view->assign("needuserloginList", $this->model->getNeeduserloginList());

        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    public function testipform(){
        if ($this->request->isPost()) {
            $name = $this->csmreq("name", true);
            $needuserlogin = $this->csmreq("needuserlogin", true);
            $ips = $this->csmreq("ips", true);
            
            $csmip = Csmip::getInstance();
            $ips = explode("\n",$ips);
            $chart = $csmip->drawchart($name, $ips,$needuserlogin);
 
            $param = [
                "chart"=>$chart->chartchinaurl,
                "code"=>$this->_getcode($name,$ips,$needuserlogin)
            ];
            $this->success("","",$param);
        }
        $this->view->assign("ip", $ip = $this->request->ip());
        return $this->view->fetch();
    }
    
    public function _getcode($name,$ip,$needuserlogin){
        $str = '
//IP地址生成图表
$csmip = \addons\csmip\library\Csmip::getInstance();
$name = "'.$name.'";
$ips = ["114.55.110.91","67.65.62.152"];
$needuserlogin = "'.$needuserlogin.'";

$chart = $csmip->drawchart($name, $ips,$needuserlogin);

echo $chart->chartchinaurl;//图表的访问地址  

        ';
        return $str;
    }
    

}
