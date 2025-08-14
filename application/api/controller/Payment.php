<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;
use think\Config;

class Payment extends Api
{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = '*';
	protected $_search_field = ['createtime', 'status'];

    use \app\api\library\buiapi\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\Payment;
    }
    /**
     * 三方支付
     */
    // public function aaaa()
    // {
    //     $user_id = 343423;
    //     $paycode = $this->request->post("paycode",17);
    //     $money = $this->request->post("num",100);

    //     if($money<=0){
    //         $this->error(__("金额数错误"));
    //     }
        
    //     $payment = Db::name("payment")->where("id",$paycode)->find();
         
    //     if(!$payment){
    //         $this->error(__("没有可用的支付通道"));
    //     }
    
    //     $payName = "\app\pay\\".$payment['code'];
    //     $payService = new $payName() ;
     
    //     //添加order data
    //     $orderId = $this->getOrderId();

    //     //redis防重复点击
    //     $symbol = "thrid_pay" . $user_id;
    //     $submited = pushRedis($symbol);
    //     if (!$submited) {
    //         $this->error(__("操作频繁"));
    //     }
    //     //code ....
    //     $data = [
    //         'user_id' => $user_id,
    //         'paycode' => $paycode,
    //         'channel_code' => $payment['channel_code'],
    //         'money' => $money,
    //         'account' => $payment['account'],
    //         'key' => $payment['key'],
    //         'orderid' => $orderId,
    //     ];
    //     $data_log = [
    //         'user_id' => $user_id,
    //         'payment_id' => $paycode,
    //         'num' => $money,
    //         'status' => 2,
    //         'orderid' => $orderId,
    //         'addtime' => time(),
    //         'notice' => $payment['name'] .' - '. $payment['remark'],
    //         'uis_rot'=>2
    //     ];
    //     Db::name("recharge")->insert($data_log);
    //     $res = $payService->pay($data);
    //     $this->success("ok",$res);
    //     // if($res){
    //     //     $this->success("ok",$res);
    //     // }else{
    //     //     $this->success($payService->msg);
    //     // }
        

    // }
    public function list_payment(){
        $data = Db::name("payment")
            ->field("id,name,remark,image")
            ->order("sort desc")
            ->where("state",1)
            ->select();
        foreach ($data as $key => $value) {
            $value['image'] = $value['image'];
            // $value['name'] = '三方支付';
            $data[$key] = $value;
        }
        $this->success("ok",$data);
    }



    /**
     * 三方支付
     */
    public function thrid_pay()
    {
        $user_id = $this->auth->id;
        $paycode = $this->request->post("paycode",1);
        $money = $this->request->post("num",0);

        if($money<=0){
            $this->error(__("金额数错误"));
        }
        
        $payment = Db::name("payment")->where("id",$paycode)->find();
         
        if(!$payment){
            $this->error(__("没有可用的支付通道"));
        }
    
        $payName = "\app\pay\\".$payment['code'];
        $payService = new $payName() ;
     
        //添加order data
        $orderId = $this->getOrderId();

        //redis防重复点击
        $symbol = "thrid_pay" . $user_id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        //code ....
        $data = [
            'user_id' => $user_id,
            'paycode' => $paycode,
            'channel_code' => $payment['channel_code'],
            'money' => $money,
            'account' => $payment['account'],
            'key' => $payment['key'],
            'orderid' => $orderId,
        ];
        $data_log = [
            'user_id' => $user_id,
            'payment_id' => $paycode,
            'num' => $money,
            'status' => 2,
            'orderid' => $orderId,
            'addtime' => time(),
            'notice' => $payment['name'] .' - '. $payment['remark'],
            'uis_rot'=>$this->auth->is_rot
        ];
        Db::name("recharge")->insert($data_log);
        $res = $payService->pay($data);
        $this->success("ok",$res);
        // if($res){
        //     $this->success("ok",$res);
        // }else{
        //     $this->success($payService->msg);
        // }
        

    }

    public function getOrderId()
    {
        $timestamp = intval(microtime(true) * 1000); // 13 位时间戳
        $datacenterId = mt_rand(0, 31); // 数据中心ID（5位）
        $workerId = mt_rand(0, 31); // Worker ID（5位）
        static $sequence = 0;
        $sequence = ($sequence + 1) % 4096; // 保持在 0 ~ 4095 循环
        return ($timestamp << 22) | ($datacenterId << 17) | ($workerId << 12) | $sequence;
    }



    /**
     * 三方支付 回调
     */
    public function pay_callback_HXPAY()
    {
        $data = $this->request->param();
        // \think\Log::record('HXPAY回调数据:' . json_encode($data,512));
        payment_log("HXPAY",$data);
        $payName = "\app\pay\HXPAY";
        $payService = new $payName() ;
        
        if(!$payService->callback($data)){
            echo $payService->msg; exit;
        }

       // 执行支付成功的逻辑，比如给用户增加余额
       $res = $this->pay_success($data['mchOrderNo']);
       if($res){
           //返回三方需要的数据
           echo $payService->msg;exit;
       }else{
            
           echo '回调失败';exit;
       }
    }
    
    public function pay_callback_GMPAY()
    {
        $data = $this->request->param();
        payment_log("GMPAY",$data);
        $payName = "\app\pay\GMPAY";
        $payService = new $payName() ;
        
        if(!$payService->callback($data)){
            echo $payService->msg; exit;
        }

       // 执行支付成功的逻辑，比如给用户增加余额
       $res = $this->pay_success($data['mchOrderNo']);
       if($res){
           //返回三方需要的数据
           echo $payService->msg;exit;
       }else{
            
           echo '回调失败';exit;
       }
    }
    public function pay_callback_YYPAY()
    {
        $data = $this->request->param();
        payment_log("YYPAY",$data);
        $payName = "\app\pay\YYPAY";
        $payService = new $payName() ;
        
        if(!$payService->callback($data)){
            echo $payService->msg; exit;
        }

       // 执行支付成功的逻辑，比如给用户增加余额
       $res = $this->pay_success($data['callerOrderId']);
       if($res){
           //返回三方需要的数据
           echo $payService->msg;exit;
       }else{
            
           echo '回调失败';exit;
       }
    }
    public function pay_callback_CPAY()
    {
        $data = $this->request->param();
        payment_log("CPAY",$data);
        $payName = "\app\pay\CPAY";
        $payService = new $payName() ;
        
        if(!$payService->callback($data)){
            echo $payService->msg; exit;
        }

       // 执行支付成功的逻辑，比如给用户增加余额
       $res = $this->pay_success($data['out_trade_no']);
       if($res){
           //返回三方需要的数据
           echo $payService->msg;exit;
       }else{
            
           echo '回调失败';exit;
       }
    }

    public function pay_checking()
    {
        $data = $this->request->param();
        if(!isset($data['paycode'])){
            $this->error(__("paycode required"));
        }
        if(!isset($data['sign'])){
            $this->error(__("sign required"));
        }
        if(!isset($data['payOrderId'])){
            $this->error(__("payOrderId required"));
        }
        $payment = Db::name("payment")->where("id",$data['paycode'])->find();
         
        if(!$payment){
            $this->error(__("没有可用的支付通道"));
        }
    
        $payName = "\app\pay\\".$payment['code'];
        $payService = new $payName() ;
        $check = $payService->check_pay($data);
        // var_dump($check);
        switch ($payment['code']) {
            case 'CPAY':
                if(isset($check['data'])) $check = $check['data'];
                if(!isset($check['status'])) $this->error(__("Payment not found."));
                $status = $this->_status_cpay($check['pay_status']);
                $data_ret = [
                    'payOrderId' =>  $check['trade_no'],
                    'mchOrderNo' =>  $check['out_trade_no'],
                    'status' =>  $status['status'],
                    'status_txt' =>  $status['status_txt'],
                    'amount' =>  $check['amount'],
                    'paySuccTime' =>  $check['pay_time'],
                ];
                break;
            
            default:
                if(!isset($check['status'])) $this->error(__("Payment not found."));
                $status = $this->_status($check['status']);
                $data_ret = [
                    'payOrderId' =>  $check['payOrderId'],
                    'mchOrderNo' =>  $check['mchOrderNo'],
                    'status' =>  $status['status'],
                    'status_txt' =>  $status['status_txt'],
                    'amount' =>  $check['amount'],
                    'paySuccTime' =>  $check['paySuccTime'],
                ];
                break;
        }
        
        $this->success("ok", $data_ret);
             
    }

    public function pay_success($orderid){
        $order = Db::name("recharge")->where('orderid',$orderid)->find();
        if(!$order){
            return '订单不存在';
        }
        //redis防重复点击
        $symbol = "pay_success" . $orderid;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        if($order['status'] == 1) return true;
        Db::startTrans();
        try {
                $user_curr = Db::name("user")->where("id",$order['user_id'])->find();
                $detailed_data = array(
                    "user_id" => $order['user_id'],
                    "money" => $order['num'],
                    "type" => "recharge",
                    // "memo" => "三方充值到账",
                    "memo" => $order['notice'],
                    "createtime" => time(),
                    "before" => $user_curr['nomoney'],
                    "after" => $user_curr['nomoney']+$order['num'],
                );
                $res1 = Db::name("user_money_log")->insert($detailed_data);
                $res2 = Db::name("user")->where("id",$order['user_id'])->setInc("nomoney",$order['num']);
                $res3 = Db::name("recharge")->where('orderid',$orderid)->update(['status'=>1]);
                if(!$res1 || !$res2 || !$res3){
                    Db::rollback();
                    return "修改失败";
                }
                Db::commit();
                return 11;
        } catch (Exception $e) {
            Db::rollback();
            return "修改失败";
        } 

    }
    
    public function recharge_log()
    {
        $user_id = $this->auth->id;
        $detail = Db::name("recharge a")
            ->field("a.id,a.num,a.status,a.notice,a.addtime")
            ->join("user b", "a.user_id=b.id", "left")
            ->where("a.user_id", $user_id)
            ->order("a.id desc")
            ->paginate(10, false, ['query' => request()->param()]);
        foreach ($detail as $key => $value) {
            $value['addtime'] = date("Y-m-d H:i:s", $value['addtime']);
            $detail[$key] = $value;
        }
        $this->success("ok", $detail);
    }

    private function _status($status){
        $data = ['status' => '1','status_txt' => 'Pending payment'];
        switch ($status) {
            case '0':
            case '1':
            case '4':    
                $data;
                break;
            case '-2':
                $data['status'] = '3';
                $data['status_txt'] = 'Timed out/Cancelled payment';
                break;
            case '2':
                $data['status'] = '2';
                $data['status_txt'] = 'Payment successful';
                break;
            default:
                # code...
                break;
        }
        return $data;
    }
    private function _status_cpay($status){
        $data = ['status' => '1','status_txt' => 'Pending payment'];
        switch ($status) {
            case '2':
                $data;
                break;
            case '3':
                $data['status'] = '3';
                $data['status_txt'] = 'Timed out/Cancelled payment';
                break;
            case '4':
                $data['status'] = '2';
                $data['status_txt'] = 'Payment successful';
                break;
            default:
                # code...
                break;
        }
        return $data;
    }
}
