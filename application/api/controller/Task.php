<?php

namespace app\api\controller;

use app\common\controller\Api;
use fast\Random;
use think\Db;

/**
 * Token接口
 */
class Task extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 检测Token是否过期
     *
     */
    public function adjust_order_old1()
    {
        $Tasks = \app\api\model\Order::Order('id desc')->where('status', 1)->select();
        foreach ($Tasks as $i => $Task) {
            \app\api\model\ProjectTask::where('order_id', $Task['id'])->delete();
            \app\api\model\Order::Order('id desc')->where('id', $Task['id'])->delete();
        }
        echo "total:" .count($Tasks);
    }
    public function adjust_order_old_21()
    {
        $Tasks = \app\api\model\Order::Order('id desc')->where('status', 1)->select();
        foreach ($Tasks as $i => $Task) {
            \app\api\model\ProjectTask2::where('order_id', $Task['id'])->delete();
        }
        echo "total:" .count($Tasks);
    }

    public function adjust_money_log1()
    {
        $Tasks = \app\api\model\Order::Order('id desc')->where('status', 1)->select();
        foreach ($Tasks as $i => $Task) {
            $userOrder = \app\api\model\Order::where('id', $Task['id'])->where('status', 1)->count();
            \app\api\model\UserMoneyLog::where('user_id', $Task['user_id'])->where('type', 'fanli')->limit($userOrder)->delete();
        }
        echo "total:" .count($Tasks);
    }
    
     public function adjust_sign()
    {
        $duplicates = Db::name('leesign')->select();
        $arr = [];
        $user_id = null; 
        $date = null;
        
        foreach ($duplicates as $key => $value){ 
            if($user_id == $value['uid']){
                if($date == date('Y-m-d',strtotime($value['sign_time']))){
                    array_push($arr, $value['id']);
                    // $arr[$value['uid']][$value['sign_time']][] = $value;
                }
               
            }
            $user_id = $value['uid'];
            $date = date('Y-m-d',strtotime($value['sign_time']));
        }
        // var_dump($arr,count($arr)); die();
        Db::name('leesign')->delete($arr);
        echo "total:" .count($arr);
        // var_dump($arr,count($arr)); die();
        // foreach ($arr as $key => $value) {
        //     var_dump($value); die();
        //     # code...
        // }

    }

    public function adjust_sign_money()
    {
        $duplicates = Db::name('user_money_log')->where('type','qiandao')->select();
        $arr = [];
        $user_id = null; 
        $date = null;
        foreach ($duplicates as $key => $value){
            if($user_id == $value['user_id']){
                // var_dump($date == date('Y-m-d',$value['createtime']),$date, date('Y-m-d',$value['createtime']));  die();
                if($date == date('Y-m-d',$value['createtime'])){
                    array_push($arr, $value['id']);
                    // $arr[$value['user_id']][$value['createtime']][] = $value;
                }
               
            }
            $user_id = $value['user_id'];
            $date = date('Y-m-d',$value['createtime']);
        }
        $del = Db::name('user_money_log')->delete($arr);
        echo "total:" .$del;
    }
    
    public function checkReturnProfit(){
        exit();
        
        $startTime = date('2024-05-11 00:00:00');
        $endTime = date('2024-05-11 23:59:59');

        $Tasks2 = Db::name('project_task2')
            ->where('status',1)
            ->where('user_id',100025)
            ->order('id DESC')
            ->select();
        
        // foreach ($Tasks1 as $k =>$v){
        //     //  \app\api\model\User::where('id', $v['user_id'])->setInc('money', $v['fanli_money']);
        //     //  \app\api\model\ProjectTask::where('id', $v['id'])->update(['status' => 2]);
        // }
        
        // $Tasks2 = Db::name('project_task2')
        //     ->where('status',1)
        //     ->where(
        //         'fanli_time',
        //         'between',
        //         [strtotime($startTime), strtotime($endTime)]
        //     )
        //     ->select();
        
        foreach ($Tasks2 as $k =>$v){
            // $ins = [
            //     'user_id' => $v['user_id'],
            //     'money' => $v['fanli_money'] + $v['fanli_money2'],
            //     'money1' => $v['fanli_money'],
            //     'money2' => $v['fanli_money2'],
            //     'before' => 0,
            //     'after' => 0,
            //     'memo' => '每日分红',
            //     'createtime' => strtotime(date('2024-05-11 23:50:00')),
            //     'type' => 'fanli',
            // ];

            // \app\api\model\UserMoneyLog::create($ins);
            // \app\api\model\ProjectTask2::where('id', $v['id'])->update(['status' => 2]);
            $Tasks2[$k]['fanli_time'] = date("m月d日", $v['fanli_time']);
        }
        
        $this->success('摆摊成功', $Tasks2);
        
    }
    
    public function return_commission()
    {
        exit();
        $order = Db::table('fa_order')->where('id', 0)->find();
        $uid = $order['user_id'];
        $user = \app\api\model\User::where('id', $uid)->find();
        $yong = [
            'upid' => [
                'user_id' => $user['upid'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney') / 100),
            ],
            'upid2' => [
                'user_id' => $user['upid2'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney2') / 100),
            ],
            'upid3' => [
                'user_id' => $user['upid3'] ?? 0,
                'money' => $order['price'] * (config('site.upmoney3') / 100),
            ],
        ];

        foreach ($yong as $i => $dat) {
            $ins = [
                'user_id' => $dat['user_id'],
                'money' => $dat['money'],
                'before' => 0,
                'after' => 0,
                'memo' => '团队佣金 ' . $user['nickname'],
                'createtime' => strtotime(date('2024-05-14 15:55:18')),
                'type' => 'tui',
            ];
            \app\api\model\UserMoneyLog::create($ins);
            \app\api\model\User::where('id', $dat['user_id'])->setInc('money', $dat['money']);
            \app\api\model\User::where('id', $dat['user_id'])->setInc('allmoney', $dat['money']);
        }
        
        echo('ok');
    }
    
    public function update_state_order(){
        $startTime = date('2024-05-29 00:00:00');
        $endTime = date('2024-05-29 23:59:59');
        
        $db = db('project_task')
            ->where(
                'fanli_time',
                'between',
                [strtotime($startTime), strtotime($endTime)]
            )
            ->where('event',1)
            ->select();
            
        foreach ($db as $k => $v){
            $db[$k]['fanli_time'] = date('Y-m-d',$v['fanli_time']);
        }
        $this->success('ok',$db);
        
    }
}
