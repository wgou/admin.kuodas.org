<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use app\common\model\Tongji;
use fast\Date;
use think\Db;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }

        $datsa = \Tool\Tongji::getData((date('Y-m-d 00:00:00')), (date('Y-m-d 23:59:59')));
        $tongji = $datsa;
        $this->view->assign([
            'tongji' => $tongji,
            // 'totaluser' => User::count(),
            // 'totaladdon' => $totaladdon,
            // 'totaladmin' => Admin::count(),
            // 'totalcategory' => \app\common\model\Category::count(),
            // 'todayusersignup' => User::whereTime('jointime', 'today')->count(),
            // 'todayuserlogin' => User::whereTime('logintime', 'today')->count(),
            // 'sevendau' => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            // 'thirtydau' => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            // 'threednu' => User::whereTime('jointime', '-3 days')->count(),
            // 'sevendnu' => User::whereTime('jointime', '-7 days')->count(),
            // 'dbtablenums' => count($dbTableList),
            // 'dbsize' => array_sum(array_map(function ($item) {
            //     return $item['Data_length'] + $item['Index_length'];
            // }, $dbTableList)),
            // 'totalworkingaddon' => $totalworkingaddon,
            // 'attachmentnums' => Attachment::count(),
            // 'attachmentsize' => Attachment::sum('filesize'),
            // 'picturenums' => Attachment::where('mimetype', 'like', 'image/%')->count(),
            // 'picturesize' => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));

        return $this->view->fetch();
    }


    public function tongji()
    {
        $date = input('date');
        $date = explode(' - ', $date);
        if (!empty($date) && count($date)==2) {
            $strtime = $date[0];
            $endtime = $date[1];
        } else {
            $strtime = date('Y-m-d 00:00:00');
            $endtime = date('Y-m-d 23:59:59');
        }

        $data = \Tool\Tongji::getData($strtime, $endtime);


        return $this->result2('有新提现消息', $data);
    }
    
     /**
     * 查看
     */
    public function newindex()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        $strtime = strtotime(date('Y-m-d 00:00:00'));
        $endtime = strtotime(date('Y-m-d 23:59:59'));
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun=$sifun['coun'];
        $sifun=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        $sifun188=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>8])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun188=$sifun188['coun'];
        $sifun188=bcadd($sifun188['fanli_money'],$sifun188['fanli_money2'],2);
        $sifun898=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>9])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun898=$sifun898['coun'];
        $sifun898=bcadd($sifun898['fanli_money'],$sifun898['fanli_money2'],2);
        $sifun1796=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>10])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun1796=$sifun1796['coun'];
        $sifun1796=bcadd($sifun1796['fanli_money'],$sifun1796['fanli_money2'],2);
        $sifun4996=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>11])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun4996=$sifun4996['coun'];
        $sifun4996=bcadd($sifun4996['fanli_money'],$sifun4996['fanli_money2'],2);
        $sifun7996=Db::name('project_task p')->join('user u','p.user_id = u.id')->where('u.is_rot',1)->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>12])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun7996=$sifun7996['coun'];
        $sifun7996=bcadd($sifun7996['fanli_money'],$sifun7996['fanli_money2'],2);
        $where=['uis_rot'=>1,'status'=>2,'createtime'=>['between', [$strtime, $endtime]]];
        $this->view->assign([
            'sifun' => $sifun,
            'sifun188' => $sifun188,
            'sifun898' => $sifun898,
            'sifun1796' => $sifun1796,
            'sifun4996' => $sifun4996,
            'sifun7996' => $sifun7996,
            'sifuncoun' => $sifuncoun,
            'sifuncoun188' => $sifuncoun188,
            'sifuncoun898' => $sifuncoun898,
            'sifuncoun1796' => $sifuncoun1796,
            'sifuncoun4996' => $sifuncoun4996,
            'sifuncoun7996' => $sifuncoun7996,
            'withdrawcreated' => Db::name('withdraw')->where('uis_rot',1)->where('status','created')->sum('money'),
            'withdrawrejected' => Db::name('withdraw')->where('uis_rot',1)->where('status','rejected')->sum('money'),
            'withdrawlocked' => Db::name('withdraw')->where('uis_rot',1)->where('status','locked')->sum('money'),
            'withdrawsuccessed' => Db::name('withdraw')->where('uis_rot',1)->where('status','successed')->sum('money'),
            'ordercount' => Db::name('order')->where($where)->count(),
            'orderusercount' => Db::name('order')->where($where)->group('user_id')->count(),
            'ordermoney' => Db::name('order')->where($where)->sum('price'),
            
            'ordercount188' => Db::name('order')->where($where)->where('project_data_id',8)->count(),
            'orderusercount188' => Db::name('order')->where($where)->where('project_data_id',8)->group('user_id')->count(),
            'ordermoney188' => Db::name('order')->where($where)->where('project_data_id',8)->sum('price'),
            
            'ordercount898' => Db::name('order')->where($where)->where('project_data_id',9)->count(),
            'orderusercount898' => Db::name('order')->where($where)->where('project_data_id',9)->group('user_id')->count(),
            'ordermoney898' => Db::name('order')->where($where)->where('project_data_id',9)->sum('price'),
            
            'ordercount1796' => Db::name('order')->where($where)->where('project_data_id',10)->count(),
            'orderusercount1796' => Db::name('order')->where($where)->where('project_data_id',10)->group('user_id')->count(),
            'ordermoney1796' => Db::name('order')->where($where)->where('project_data_id',10)->sum('price'),
            
            'ordercount4996' => Db::name('order')->where($where)->where('project_data_id',11)->count(),
            'orderusercount4996' => Db::name('order')->where($where)->where('project_data_id',11)->group('user_id')->count(),
            'ordermoney4996' => Db::name('order')->where($where)->where('project_data_id',11)->sum('price'),
            
            'ordercount7996' => Db::name('order')->where($where)->where('project_data_id',12)->count(),
            'orderusercount7996' => Db::name('order')->where($where)->where('project_data_id',12)->group('user_id')->count(),
            'ordermoney7996' => Db::name('order')->where($where)->where('project_data_id',12)->sum('price'),
            
            'usernomoney' => User::where('is_rot', 1)->sum('nomoney'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));

        return $this->view->fetch();
    }
    
    public function newtongji()
    {
        $date = input('date');
        $date = explode(' - ', $date);
        if (!empty($date) && count($date)==2) {
            $strtime = $date[0];
            $strtime = strtotime($strtime);
            $endtime = $date[1];
            $endtime = strtotime($endtime);
        } else {
            $strtime = strtotime(date('Y-m-d 00:00:00'));
            $endtime = strtotime(date('Y-m-d 23:59:59'));
        }
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun=$sifun['coun'];
        $sifuna=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>8])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*)  as coun')->find();
        $sifuncoun188=$sifun['coun'];
        $sifun188=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>9])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun898=$sifun['coun'];
        $sifun898=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>10])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun1796=$sifun['coun'];
        $sifun1796=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>11])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun4996=$sifun['coun'];
        $sifun4996=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        $sifun=Db::name('project_task p')->join('user u','p.user_id = u.id')->where(['p.fanli_time'=>['between', [$strtime, $endtime]],'p.status'=>1,'u.is_rot'=>1,'p.project_data_id'=>12])->field('sum(fanli_money) as fanli_money,sum(fanli_money2) as fanli_money2,count(*) as coun')->find();
        $sifuncoun7996=$sifun['coun'];
        $sifun7996=bcadd($sifun['fanli_money'],$sifun['fanli_money2'],2);
        
        $where=['uis_rot'=>1,'status'=>2,'createtime'=>['between', [$strtime, $endtime]]];
        $data = [
            'sifun' => $sifuna,
            'sifun188' => $sifun188,
            'sifun898' => $sifun898,
            'sifun1796' => $sifun1796,
            'sifun4996' => $sifun4996,
            'sifun7996' => $sifun7996,
            'sifuncoun' => $sifuncoun,
            'sifuncoun188' => $sifuncoun188,
            'sifuncoun898' => $sifuncoun898,
            'sifuncoun1796' => $sifuncoun1796,
            'sifuncoun4996' => $sifuncoun4996,
            'sifuncoun7996' => $sifuncoun7996,
            'withdrawcreated' => Db::name('withdraw')->where(['createtime'=>['between', [$strtime, $endtime]],'status'=>'created','uis_rot'=>1])->sum('money'),
            'withdrawrejected' => Db::name('withdraw')->where(['createtime'=>['between', [$strtime, $endtime]],'status'=>'rejected','uis_rot'=>1])->sum('money'),
            'withdrawlocked' => Db::name('withdraw')->where(['createtime'=>['between', [$strtime, $endtime]],'status'=>'locked','uis_rot'=>1])->sum('money'),
            'withdrawsuccessed' => Db::name('withdraw')->where(['createtime'=>['between', [$strtime, $endtime]],'status'=>'successed','uis_rot'=>1])->sum('money'),
            'ordercount' => Db::name('order')->where($where)->count(),
            'orderusercount' => Db::name('order')->where($where)->group('user_id')->count(),
            'ordermoney' => Db::name('order')->where($where)->sum('price'),
            
            'ordercount188' => Db::name('order')->where($where)->where('project_data_id',8)->count(),
            'orderusercount188' => Db::name('order')->where($where)->where('project_data_id',8)->group('user_id')->count(),
            'ordermoney188' => Db::name('order')->where($where)->where('project_data_id',8)->sum('price'),
            
            'ordercount898' => Db::name('order')->where($where)->where('project_data_id',9)->count(),
            'orderusercount898' => Db::name('order')->where($where)->where('project_data_id',9)->group('user_id')->count(),
            'ordermoney898' => Db::name('order')->where($where)->where('project_data_id',9)->sum('price'),
            
            'ordercount1796' => Db::name('order')->where($where)->where('project_data_id',10)->count(),
            'orderusercount1796' => Db::name('order')->where($where)->where('project_data_id',10)->group('user_id')->count(),
            'ordermoney1796' => Db::name('order')->where($where)->where('project_data_id',10)->sum('price'),
            
            'ordercount4996' => Db::name('order')->where($where)->where('project_data_id',11)->count(),
            'orderusercount4996' => Db::name('order')->where($where)->where('project_data_id',11)->group('user_id')->count(),
            'ordermoney4996' => Db::name('order')->where($where)->where('project_data_id',11)->sum('price'),
            
            'ordercount7996' => Db::name('order')->where($where)->where('project_data_id',12)->count(),
            'orderusercount7996' => Db::name('order')->where($where)->where('project_data_id',12)->group('user_id')->count(),
            'ordermoney7996' => Db::name('order')->where($where)->where('project_data_id',12)->sum('price'),
        ];


        return $this->result2('有新提现消息', $data);
    }
    protected $responseType = 'json';

    protected function result2($msg, $data = null, $code = 1, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

}
