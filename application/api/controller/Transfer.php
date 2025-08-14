<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;
use think\Config;

class Transfer extends Api
{
    protected $noNeedRight = '*';
    protected $noNeedLogin = '*';
    protected $_allow_func = '*';
    protected $_search_field = ['createtime', 'type'];
    use \app\api\library\buiapi\traits\Api;
    /**
     * 公共方法-列表
     */

    public function _initialize()
    {
        parent::_initialize();
    }

    public function transfer_sub()
    {
        $my_id = $this->auth->id;
        $user_id = $this->request->post('user_id');
        $num = $this->request->post("amount");
        $pay_pwd = $this->request->post("pay_pwd");
        // $this->error(__("维护中")); // 注释掉维护中，启用用户间转账功能
        $this->request->token();
        $symbol = "transfer_sub" . $this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        if (!is_numeric($num) || $num <= 0) {
            $this->error(__("请输入有效数量"));
        }

        //判断交易密码
        if (empty($this->auth->pay_pwd)) {
            $this->error(__("请到安全设置设定交易密码"), null, 2);
        } else {
            if (strtoupper(md5(strtoupper(md5($pay_pwd . 'skund')))) != $this->auth->pay_pwd) {
                $this->error(__('交易密码错误'));
            }
        }

        if ($user_id == $this->auth->getUser()['id']) {
            $this->error(__('不能给自己划转'));
        }

        $user = Db::name("user")->where("id", $user_id)->find();
        if (empty($user)) {
            $this->error(__("用户不存在"));
        }

        $my_curr = Db::name("user")->where("id", $my_id)->find();
        $user_curr = Db::name("user")->where("id", $user['id'])->find();
        
        
        $min_transfer = config('site.min_transfer');
        if ($num < $min_transfer) {
            $this->error(__("最小划转金额为" . $min_transfer));
        }
        
        if ($my_curr['nomoney'] < $min_transfer) {
            $this->error(__("余额不足"));
        }

        if ($my_curr['nomoney'] < $num) {
            $this->error(__("余额不足"));
        }
        if ($my_curr['nomoney'] <= 0) {
            $this->error(__("余额不足"));
        }

        $before_my_num = $my_curr['nomoney'];
        $after_my_num = $before_my_num - $num;

        $before_curr_num = $user_curr['nomoney'];
        $after_curr_num = $before_curr_num + $num;

        $detail[] = [
            'user_id' => $my_id,
            'money' =>  -$num,
            'before' => $before_my_num,
            'after' => $after_my_num,
            'memo' => __('转账') . '-' . $user_curr['nickname'] . '-' . $user_curr['mobile'],
            'createtime' => time(),
            'type' => 'tran',
        ];

        $detail[] = [
            'user_id' => $user['id'],
            'money' =>  +$num,
            'before' => $before_curr_num,
            'after' => $after_curr_num,
            'memo' => __('收账') . '-' . $my_curr['nickname'] . '-' . $my_curr['mobile'],
            'createtime' => time(),
            'type' => 'tran',
        ];

        Db::startTrans();
        try {
            Db::name("user_money_log")->insertAll($detail);
            // $res1 =  Db::name("user")->where("id", $my_id)
            //     ->update(['nomoney' => $my_curr['nomoney'] - $num]);
            // $res2 = Db::name("user")->where("id", $user['id'])
            //     ->update(['nomoney' => $user_curr['nomoney'] + $num]);
                
            $res1=Db::name("user")->where("id", $my_id)->dec('nomoney', $num)->update();
            $res2=Db::name("user")->where("id", $user['id'])->inc('nomoney', $num)->update();
            if (!$res1 || !$res2) {
                Db::rollback();
                $this->error(__('划转失败'));
            }

            Db::commit();
            lopRedis($symbol);
            $this->success(__('划转成功'));
        } catch (Exception $e) {
            Db::rollback();
            lopRedis($symbol);
            $this->error(__('系统繁忙'));
        }
    }

    //推荐奖转帐户余额
    public function transfer_nomoney()
    {
        $filter = json_decode(html_entity_decode($this->request->param('filter', '')), true);
        $type = $filter['type'];
        $my_id = $this->auth->id;
        $this->request->token();
        $symbol = "transfer_nomoney" . $this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        $user_money = Db::name("user u")->field('u.*,um.money_tuijian,um.money_choujiang,um.money_shifang,um.money_bbxjhb,um.money_quanhuan,um.money_qiandao,um.money_neixu,um.money_zhengcejin')->join('fa_user_money um','um.user_id = u.id')->where("u.id", $my_id)->find();
        if (empty($user_money)) {
            $this->error(__("用户不存在"));
        }
        
        $min_transfer = 100;
        if ($user_money["money_".$type] < $min_transfer) {
            $this->error(__("最小划转金额为" . $min_transfer));
        }
        // $this->error(__("维护中")); // 注释掉维护中，启用推荐奖转账户余额功能
        $moneylog = [
            'user_id' => $my_id,
            'money' =>  +$user_money["money_".$type],
            'before' => $user_money['nomoney'],
            'after' => $user_money['nomoney'] + $user_money["money_".$type],
            'memo' => __($this->_get_money_typename($type).'资金转换账户余额'),
            'createtime' => time(),
            'type' => 'tran',
        ];

        $moneyinlog = [
            'user_id' => $my_id,
            'money' =>  -$user_money["money_".$type],
            'before' => $user_money["money_".$type],
            'after' => 0,
            'memo' => __($this->_get_money_typename($type).'资金转换账户余额'),
            'createtime' => time(),
            'type' => 'tuijian',
        ];        

        Db::startTrans();
        try {
            $res1 = Db::name("user_money_log")->insert($moneylog);
            $res2 = Db::name("user_money_in_log")->insert($moneyinlog);
            $res3 = Db::name("user_money")->where("user_id", $my_id)->update(['money' => $user_money['money'] - $user_money["money_".$type],"money_".$type=>0]);
            // $res4 = Db::name("user")->where("id", $my_id)->update(['nomoney' => $user_money['nomoney'] + $user_money["money_".$type],'money' => $user_money['money'] - $user_money["money_".$type]]);
            $res4=Db::name("user")->where("id", $my_id)->inc('nomoney', $user_money["money_".$type])->dec('money', $user_money["money_".$type])->update();
            if (!$res1 || !$res2 || !$res3 || !$res4) {
                Db::rollback();
                $this->error(__('划转失败'));
            }

            Db::commit();
            lopRedis($symbol);
            $this->success(__('划转成功'));
        } catch (Exception $e) {
            Db::rollback();
            lopRedis($symbol);
            $this->error(__('系统繁忙'));
        }
    }    

    public function record_transfer()
    {
        $user_id = $this->auth->id;
        $data = db('user_money_log')
            ->field('id,user_id,money,createtime,memo')
            ->where("user_id", $user_id)
            ->where('type', 'tran')
            ->order('createtime desc')
            ->paginate(10, false, ['query' => request()->param()]);
        if (!$data) {
            $this->error(__('暂无记录'));
        }
        foreach ($data as $key => $value) {
            $value['createtime'] = date('Y-m-d H:i', $value['createtime']);
            $data[$key] = $value;
        }
        $this->success('数据列表', $data);
    }

    public function _get_money_typename($type)
    {
        switch ($type) {
            case "tuijian":
                return "推荐";
                break;
            case "shifang":
                return "释放";
                break;
            case "qiandao":
                return "签到";
                break;
            case "quanhuan":
                return "券换";
                break;
            case "choujiang":
                return "抽奖";
                break;
            case "neixu":
                return "内需奖励";
                break;
            case "bbxjhb":
                return "彩蛋现金红包";
                break;
            case "zhengcejin":
                return "政策金";
                break;
            default:
                return "";
                break;
        }
    }
}
