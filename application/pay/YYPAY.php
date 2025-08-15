<?php

namespace app\pay;

use think\Db;
use think\Config;
use think\Env;

class YYPAY extends Pay
{

    public $msg = 'OK';
    public $pay_url = 'http://mi.feicuipay.com/optimus/collect/placeOrder';
    public $mchId = '20000045';
    public $key = 'K8QE5GSAUGKVF0OUGHB7NAXW9I41EIOCYP6RQQLOIBUKA7JZACG9G0PS0VTXFBPQJ5P6UGMJHNGFRPKTTID7BVVJKH9A5Q2VDYHSMMMOH18TQ8TUJMUNQR7WW7ZNXND0';

    public function pay($data)
    {
        $post['method'] = 'placeOrder';
        $post['memberId'] = $data['account'];
        $post['channelCode'] = $data['channel_code'];
        $post['callerOrderId'] = $data['orderid'];
        $post['amount'] = $data['money']*100;
        // $post['currency'] = 'cny';
        // $post['redirectUrl'] = Env::get('app.apiurl').'/api/payment/pay_callback_YYPAY';
        $post['merchantCallbackUrl'] = Env::get('app.apiurl').'/api/payment/pay_callback_YYPAY';
        // $post['subject'] = 'tsl';
        // $post['body'] = 'tslls';
        $post['timestamp'] = date('Y-m-d H:i:s');
        $post['sign'] = $this->sign($post,$data['key']);
        $data = [
            'msg' => '三方支付通道暂时不可用',
            'status' => 0,
            'sign'=> null,
            'payOrderId'=> null,
        ];
        $res = $this->httpPosts($this->pay_url,$post);
        $res = json_decode($res,true);
        if(isset($res['data'])) $res = $res['data'];
        // pay_log("CPAY",$res);
        if(!empty($res['message']['url'])){
            $data['pay_url'] = $res['message']['url'];
            $data['msg'] = '成功';
            $data['status'] = 1;
        }
        return $data;

    }

 
    public function callback($data)
    {
        $order = Db::name("recharge")->where("orderid",$data['callerOrderId'])->find();

        if(!$order){
            $this->msg = '订单不存在';
            return false;
        }
        // $payment = Db::name("payment")->where("code",'GMPAY')->find();
        $payment = Db::name("payment")->where("id",$order['payment_id'])->find();
        
        if($data['sign'] != $this->sign($data,$payment['key'])){
            $this->msg = '验证签名失败';
            return false;
        }
        if($data['orderStatus'] != 'AP'){
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
        unset($data['sign']);
        //去空，空值不参与签名
        $params = array_filter($data);
        //参数排序
        ksort($params);
        $md5str = '';
        foreach ($params as $key => $val) {
            $md5str = $md5str . $key .  $val;
        }
        $md5str = $keys.$md5str.$keys;
        //获取sign
        return md5($md5str);
    }

}
