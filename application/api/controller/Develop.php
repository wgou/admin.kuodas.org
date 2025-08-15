<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use app\api\model\UserMoney;

/**
 * 示例接口
 */
class Develop extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [''];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    /**中奖记录接口
     */
    public function indexlist()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['uid'=>$user_id];
            $data['list']=Db::name('develop')->where($where)->order('id','desc')->limit($page*10,10)->select();
            
            $data['total']=Db::name('develop')->where($where)->count();
            
            $this->success('success',$data);
        }
    }
    /**中奖记录接口
     */
    public function adddevelop()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "adddevelop" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            if ($this->auth->develop<=0) {
                $this->error(__("操作频繁"));
            }
            $data['name'] = $this->request->post('name','');
            $data['gender'] = $this->request->post('gender','');
            $data['date_of_birth'] = $this->request->post('date_of_birth','');
            $data['place_of_origin'] = $this->request->post('place_of_origin','');
            $data['ethnicity'] = $this->request->post('ethnicity','');
            $data['political_status'] = $this->request->post('political_status','');
            $data['id_number'] = $this->request->post('id_number','');
            $data['home_address'] = $this->request->post('home_address','');
            $data['registered_name'] = $this->request->post('registered_name','');
            $data['user_id'] = $this->request->post('user_id','');
            $data['contact_number'] = $this->request->post('contact_number','');
            $data['payee_name'] = $this->request->post('payee_name','');
            $data['bank_account_opening_bank'] = $this->request->post('bank_account_opening_bank','');
            $data['bank_card_number'] = $this->request->post('bank_card_number','');
            $data['project_to_be_reported'] = $this->request->post('project_to_be_reported','');
            $data['investment_time'] = $this->request->post('investment_time','');
            $data['investment_principal'] = $this->request->post('investment_principal','');
            $data['signature_of_the_applicant'] = $this->request->post('signature_of_the_applicant','');
            $data['uid']=$user_id;
            $data['createtime']=time();
            $data['updatetime']=$data['createtime'];
            if (in_array('', $data, true)) {
                $this->error(__("请填写完整"));
            }
            Db::name('develop')->insert($data);
            
            \app\api\model\User::where('id', $user_id)->setDec('develop',1);
            $this->success('success');
        }
    }
    /**中奖记录接口
     */
    public function editdevelop()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "editdevelop" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $data['image'] = $this->request->post('image','');
            $id = $this->request->post('id','');
            $data['updatetime']=time();
            if (in_array('', $data, true)) {
                $this->error(__("请填写完整"));
            }
            Db::name('develop')->where(['uid'=>$user_id,'id'=>$id])->update($data);
            
            $this->success('success');
        }
    }
}
