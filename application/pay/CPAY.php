<?php

namespace app\pay;

use think\Db;
use think\Config;
use think\Env;

class CPAY extends Pay
{

    public $msg = 'success';
    public $pay_url = 'http://8.222.155.101:212/gateway/index/';

    public function pay($data)
    {

        $post['account_id'] = $data['account'];
        $post['thoroughfare'] = $data['channel_code'];
        $post['out_trade_no'] = $data['orderid'];
        $post['amount'] = sprintf('%.2f', $data['money']);
        $post['callback_url'] = Env::get('app.apiurl').'/api/payment/pay_callback_CPAY';
        $post['success_url'] = Env::get('app.apiurl').'/api/payment/pay_callback_CPAY';
        $post['error_url'] = Env::get('app.apiurl').'/api/payment/pay_callback_CPAY';
        $post['timestamp'] = time();
        $post['ip'] = $_SERVER['SERVER_ADDR'];
        $post['payer_ip'] = $data['user_id'];
        $post['content_type'] = 'json';
        $post['deviceos'] = $this->detect_os();
        $post['sign'] = $this->sign($post,$data['key']);
        $data = [
            'msg' => '三方支付通道暂时不可用',
            'status' => 0,
            'sign'=> $post['sign'],
            'payOrderId'=> $post['out_trade_no'],
        ];
        $res = $this->post($this->pay_url.'checkpoint.do',$post);
        //成功则跳转链接，失败则返回提示
        $res = json_decode($res,true);
        if(isset($res['data'])) $res = $res['data'];
        pay_log("CPAY",$res);
        if(!empty($res['pay_url'])){
            $data['pay_url'] = $res['pay_url'];
            $data['msg'] = '成功';
            $data['status'] = 1;
        }
        // $this->msg = '三方支付通道暂时不可用';
        return $data;

    }

 
    public function callback($data)
    {
        
        $order = Db::name("recharge")->where("orderid",$data['out_trade_no'])->find();

        if(!$order){
            $this->msg = '订单不存在';
            return false;
        }
        $payment = Db::name("payment")->where("code",'CPAY')->find();
        // if($data['sign'] != $this->sign($data,$payment['key'])){
        //     $this->msg = '验证签名失败';
        //     return false;
        // }
        if($data['pay_status'] != 4){
            $this->msg = '支付不成功';
            return false;
        }
      
        return true;

    }

    public function check_pay($data)
    {
        $paymentId = $data['paycode'];
        $payment = Db::name("payment")->where("id",$paymentId)->find();
        if(!$payment) return  $this->error(__("Payment not found"));;
        $key = $payment['key'];
        $mchId = $payment['account'];
        $paramData = [
            'account_id' => $mchId,
            'out_trade_no' => $data['payOrderId'],
            'timestamp' => time(),
        ];
        $paramData['sign'] = $this->sign($paramData, $key);
        $result = $this->post($this->pay_url.'queryorder.do', $paramData);
        $result = json_decode($result,true);
        return $result ;
             
    }

    public function sign($data,$keys)
    {
        $data = array_filter($data);
        ksort($data);

        $origin_str = '';
        foreach ($data as $key => $value) {
            $origin_str .= $key . '=' . $value . '&';
        }
        $origin_str = $origin_str . 'key=' . $keys;

        $sign = md5($origin_str);

        return $sign;
    }

    private function detect_os() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
        $os_platforms = array(
            'windows' => 'Windows',
            'ios' => 'iPhone|iPod|iPad',
            'mac os x' => 'Mac OS X',
            'android' => 'Android',
            'linux' => 'Linux|X11'
        );
    
        foreach ($os_platforms as $os => $regex) {
            if (preg_match('/' . $regex . '/i', $user_agent)) {
                return $os;
            }
        }
    
        return 'ios';
    }

}
