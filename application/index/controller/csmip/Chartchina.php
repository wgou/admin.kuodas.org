<?php
namespace app\index\controller\csmip;

use addons\csmip\library\CsmFrontend;
use app\common\library\Auth;
use addons\csmip\library\QRcode;

// http://127.0.0.1/fastadmin_plugin_csmmeet/public/index/csmip.chartchina
class Chartchina extends CsmFrontend
{

    protected $layout = 'default';

    protected $noNeedLogin = [
        "*"
    ];

    protected $noNeedRight = [
        '*'
    ];

    public function index()
    {
        //header("Content-Security-Policy: upgrade-insecure-requests");
        $id = $this->csmreq("id", true);
        $daodata = new \app\admin\model\csmip\Data();
        $row = $daodata->where("id", "=", $id)->find();
        if($row==null){
            $this->error("错误数据");
        }

        $this->_checkUserLogin($row->needuserlogin);

        // 国内数据
        $dao = new \app\admin\model\csmip\Dataline();
        $dataList = $dao->alias("t")
            ->where("t.status", "=", "normal")
            ->where("t.csmip_data_id", "=", $id)
            ->group("t.province")
            ->field("t.province name,count(*) value")
            ->order("value", "desc")
            ->select();
        // $dao = new \app\admin\model\csmip\Chartbind();
        // $dataList = $dao->alias('t')
        // ->join("csmip_dataline a", "a.province=t.ip2regionkey and a.status='normal' and a.csmip_data_id=".$id, "left")
        //     ->where("t.charttype", "=", "echartchina")
        //     ->where("t.status", "=", "normal")
        //     ->group("t.chartkey")
        //     ->field("t.chartkey name,count(a.id) value")
        //     ->order("value", "desc")
        //     ->select();
        
        //$this->tracedao($dao);
        // 按国家统计数据
        $dao2 = new \app\admin\model\csmip\Dataline();
        $datalist2 = $dao2->alias("t")
            ->where("t.status", "=", "normal")
            ->where("t.csmip_data_id", "=", $id)
            ->group("t.country")
            ->field("t.country name,count(*) value")
            ->order("value", "desc")
            ->select();
        // 总人数
        $total = $dao2->alias("t")
            ->where("t.status", "=", "normal")
            ->where("t.csmip_data_id", "=", $id)
            ->count();
            
        $this->view->assign('dataList', $dataList);
        $this->view->assign('dataList2', $datalist2);
        $this->view->assign('total', $total);
        $this->view->assign('row', $row);
        $this->view->assign('chartconfig', json_decode($row->chartconfig));

        return $this->view->fetch();
    }

    public function getMobileUrl()
    {
        $id = $this->csmreq("id", true);
        $mobileUrl = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["SERVER_NAME"] . config('view_replace_str.__PUBLIC__') . "index/csmip.chartchina/index?id=" . $id;
        // echo($mobileUrl);die();
        QRcode::png($mobileUrl, false, "L", 7);
    }

    private function _checkUserLogin($needuserlogin)
    {
        if ($needuserlogin == 'Y') {
            $auth = Auth::instance();
            $auth->isLogin();
            if (! $auth->isLogin()) {
                $this->error("请登录后再操作", 'index/user/login');
            }
        }

    }
}