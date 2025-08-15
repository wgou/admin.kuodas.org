<?php

namespace app\api\controller;

use app\api\model\Level;
use app\api\model\Loginlog;
use app\api\model\UserMoneyLog;
use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Validate;
use fast\Date;

/**
 * 会员接口
 */
class Borrow extends Api
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    public function index(){
        $user_id = $this->auth->id;
        $timestamp = strtotime('2025-05-08 00:00:00'); // 2025年5月8日0:00:00的时间戳
        $one_user = Db::table('fa_user')->where('upid', $user_id)->where('createtime', '>=', $timestamp)->count();
        $all_user1 = Db::table('fa_user')->where('upid', $user_id)->where('is_buy', 1)->where('createtime', '>=', $timestamp)->count();
        $load_amount = 0.00;
        // if($one_user>=20 && $all_user1>=12){
        //     $load_amount = 896;
        // }
        // if($one_user>=30 && $all_user1>=20){
        //     $load_amount = 1796;
        // }
        // if($one_user>=50 && $all_user1>=35){
        //     $load_amount = 4996;
        
        // }
        if($one_user>=10 && $all_user1>=8){
            $load_amount = 896;
            // $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
        }
        // if($one_user>=30 && $all_user1>=20){
        //     $load_amount = 1796;
        //     // $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
        // }
        if($one_user>=60 && $all_user1>=40){
            $load_amount = 4996;
            // $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
        }
        if($one_user>=110 && $all_user1>=103){
            $load_amount = 7996;
            // $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
        }
        $data = [
            'all_user1' => $all_user1,
            'one_user' =>  $one_user,
            'load_amount' => $load_amount
        ];
        $this->success('ok', $data);
    }
    
    public function add(){
        
        $this->success('借贷申请成功');
    }

   
}
