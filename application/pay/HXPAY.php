<?php

namespace app\pay;

use think\Db;
use think\Config;
use think\Env;

class HXPAY extends Pay
{

    public $msg = 'success';
    public $pay_url = 'http://gming.xyz:56700/api/pay/';
    public $mchId = '20000034';
    public $key = 'D9WH78RYPJVJMI5YUGKFBD8N9OUV83SYCXHUTCBRDFVDXC5IDACPIBVT9FQ0VJYMXULOKXHI06S7DBKUTZEWBDQKUZ5EOACEHZY11W15LL6BDF8BY50FZYV6JDRNPNER';

    public function pay($data)
    {

        $post['mchId'] = $data['account'];
        $post['productId'] = $data['channel_code'];
        $post['mchOrderNo'] = $data['orderid'];
        $post['amount'] = $data['money']*100;
        $post['currency'] = 'cny';
        $post['returnUrl'] = Env::get('app.apiurl').'/api/payment/pay_callback_HXPAY';
        $post['notifyUrl'] = Env::get('app.apiurl').'/api/payment/pay_callback_HXPAY';
        $post['subject'] = 'tsl';
        $post['body'] = 'tslls';
        $post['reqTime'] = date('YmdHis');
        $post['version'] = '1.0';
        $post['sign'] = $this->sign($post,$data['key']);
        // var_dump($post['sign']);
       
        $data = [
            'msg' => '三方支付通道暂时不可用',
            'status' => 0,
            'sign'=> null,
            'payOrderId'=> null,
        ];
        $res = $this->post($this->pay_url.'create_order',$post);
        //成功则跳转链接，失败则返回提示
        $res = json_decode($res,true);
        pay_log("HXPAY",$res);
        if(!empty($res['sign'])) $data['sign'] = $res['sign'];
        if(!empty($res['payOrderId'])) $data['payOrderId'] = $res['payOrderId'];
        if(!empty($res['payUrl'])){
            $data['pay_url'] = $res['payUrl'];
            $data['msg'] = '成功';
            $data['status'] = 1;
        }
        // $this->msg = '三方支付通道暂时不可用';
        return $data;

    }

 
    public function callback($data)
    {
        
        $order = Db::name("recharge")->where("orderid",$data['mchOrderNo'])->find();

        if(!$order){
            $this->msg = '订单不存在';
            return false;
        }
        $payment = Db::name("payment")->where("code",'HXPAY')->find();
        if($data['sign'] != $this->sign($data,$payment['key'])){
            $this->msg = '验证签名失败';
            return false;
        }
        if($data['status'] != 2){
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
            'mchId' => $mchId,
            'payOrderId' => $data['payOrderId'],
            'sign' => $data['sign'],
            'executeNotify' => true, // whether to execute callback
            'reqTime' => date('YmdHis'),
            'version' => '1.0',
        ];
        $paramData['sign'] = $this->sign($paramData, $key);
        $result = $this->post($this->pay_url.'query_order', $paramData);
        $result = json_decode($result,true);
        return $result ;
             
    }

    public function sign($data,$keys)
    {
        ksort($data);
        $sign = ''; 
        foreach($data as $key=>$value)
        {
            if($key != 'sign' && $value){
                $sign .= $key."=". $value.'&';
            }
            
        }
        $sign  = $sign . 'key='.$keys ;

        return strtoupper(md5($sign));
    }

}
