<?php

namespace app\api\controller;

use think\Db;
use think\Exception;
use think\Validate;
use app\api\library\buiapi\Api;

/**
 *
 */
class Withdraw extends Api
{

    protected $noNeedRight = '*';
    protected $noNeedLogin = '*';
    protected $_allow_func = '*';
    protected $_search_field = ['createtime','type'];
    use \app\api\library\buiapi\traits\Api;


    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\Withdraw();
    }


    /**
     * 公共方法-列表
     */
     
    public function index()
    {
        $filter = api_params($this->request->param('filter'));
        $p = $this->request->param('page') ?? 1;
        //condition where
        $date = explode(',', $filter['createtime']);
        $w = ['user_id' => $this->auth->id, 'createtime' => ["between", [$date[0], $date[1] + 86400]]];
        $list = $this->paginate_with('withdraw',$w,$p);
        if(count($list['rows']) > 0) {
            foreach ($list['rows'] as $key => $value) {
                $list['rows'][$key]['createtime'] = date('Y-m-d H:i', $value['createtime']);
            }
        }
        $list['alltixian'] = Db::table('fa_withdraw')->where('user_id', $this->auth->id)->where('status', 'successed')->sum('money');
        $this->success('数据列表', $list);
    }
    
    public function paginate_with($table, $w, $p, $per_page = 20)
    {
        $p = $p == '' ? 1 : $p;
        $items_per_page = $per_page;
        $offset = ($p - 1) * $items_per_page;
        $total = Db::name($table)->where($w)->count('id');
        $info = Db::name($table)
            ->field('id,money,status,createtime,memo')
            ->where($w)
            ->order('id desc')
            ->limit($offset, $items_per_page)
            ->select();
    
        return [
            "total" => $total,
            "per_page" => $items_per_page,
            "current_page" => (int) $p,
            "last_page"  => ceil($total / $items_per_page),
            'rows' => $info
        ];
    }
    
    public function index1()
    {
        $this->relationSearch = true;
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',', $this->_search_field));
        $this->_init_where['user_id'] = $this->auth->id;
        $item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));
//        foreach ($list['rows'] as $k => $y) {
//            $list['rows'][$k]['idd'] = 1;
//        }
        $list['alltixian'] = Db::table('fa_withdraw')->where('user_id', $this->auth->id)->where('status', 'successed')->sum('money');
        $this->success('数据列表', $list);
    }

//    /**
//     * 余额提现
//     * @return string
//     */
//    public function withdraw()
//    {
//        $config = get_addon_config('withdraw');
//        $this->view->assign('addonConfig', $config);
//        $this->view->assign('title', __('Withdraw'));
//        return $this->view->fetch();
//    }
//
//    /**
//     * 余额日志
//     * @return string
//     */
//    public function withdrawlog()
//    {
//        $withdrawloglist = \addons\withdraw\model\Withdraw::where(['user_id' => $this->auth->id])
//            ->order('id desc')
//            ->paginate(10);
//
//        $this->view->assign('title', __('Withdraw log'));
//        $this->view->assign('withdrawloglist', $withdrawloglist);
//        return $this->view->fetch();
//    }

    /**
     * 创建订单并发起支付请求
     * @throws \think\exception\DbException
     */
    public function submit1()
    {
        $money = $this->request->request('money');
        $account = $this->request->request('account');
        $name = $this->request->request('name');
        $type = 'alipay';

//        $token = $this->request->post('__token__');
        //验证Token
//        if (!Validate::is($token, "token", ['__token__' => $token])) {
//            $this->error("Token验证错误，请重试！", '', ['__token__' => $this->request->token()]);
//        }

        //刷新Token
        $this->request->token();

        if ($money <= 0) {
            $this->error('提现金额不正确');
        }
        if ($money > $this->auth->money) {
            $this->error('提现金额超出可提现金额');
        }
        if (!$account) {
            $this->error("提现账户不能为空");
        }
        if (!$name) {
            $this->error("真实姓名不能为空");
        }
        if (!Validate::is($account, "email") && !Validate::is($account, "/^1\d{10}$/")) {
            $this->error("提现账户只能是手机号或Email");
        }

        $config = get_addon_config('withdraw');
        if (isset($config['minmoney']) && $money < $config['minmoney']) {
            $this->error('提现金额不能低于' . $config['minmoney'] . '元');
        }
        if ($config['monthlimit']) {
            $count = \addons\withdraw\model\Withdraw::where('user_id', $this->auth->id)->whereTime('createtime', 'month')->count();
            if ($count >= $config['monthlimit']) {
                $this->error("已达到本月最大可提现次数");
            }
        }
        //redis防重复点击
        $symbol = "withdraw" . $this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        Db::startTrans();
        try {
            $data = [
                'orderid' => date("YmdHis") . sprintf("%08d", $this->auth->id) . mt_rand(1000, 9999),
                'user_id' => $this->auth->id,
                'money' => $money,
                'type' => $type,
                'account' => $account,
                'name' => $name,
                'uis_rot'=>$this->auth->is_rot
            ];
            \addons\withdraw\model\Withdraw::create($data);
            \app\common\model\User::money(-$money, $this->auth->id, "提现1");
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }


        $this->success("提现申请成功！请等待后台审核！", url("withdraw/withdrawlog"));
        return;
    }

    /**
     * 企业支付通知和回调
     * @throws \think\exception\DbException
     */
    public function epay()
    {
        $type = $this->request->param('type');
        $paytype = $this->request->param('paytype');
        if ($type == 'notify') {
            $pay = \addons\epay\library\Service::checkNotify($paytype);
            if (!$pay) {
                echo '签名错误';
                return;
            }
            $data = $pay->verify();
            try {
                $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
                \addons\recharge\model\Order::settle($data['out_trade_no'], $payamount);
            } catch (Exception $e) {
            }
            echo $pay->success();
        } else {
            $pay = \addons\epay\library\Service::checkReturn($paytype);
            if (!$pay) {
                $this->error('签名错误');
            }
            //微信支付没有返回链接
            if ($pay === true) {
                $this->success("请返回网站查看支付状态!", "");
            }

            //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
            $this->success("恭喜你！充值成功!", url("user/index"));
        }
        return;
    }
}
