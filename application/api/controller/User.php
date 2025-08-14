<?php

namespace app\api\controller;

use app\api\model\Level;
use app\api\model\Loginlog;
use app\api\model\UserMoney;
use app\api\model\UserMoneyInLog;
use app\api\model\UserMoneyLog;
use app\common\controller\Api;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\model\MoneyLog;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Validate;
use fast\Date;
use think\Env;

/**
 * 会员接口
 */
class User extends Api
{
    /**
     * 会员中心
     */
    public $game_level_jy = 0;
    public $game_level = 0;
    protected $noNeedLogin = ['login', 'mobilelogin', 'register', 'resetpwd', 'changeemail', 'changemobile', 'third', 'teamlist', 'team','check_click_customer','auth_con','bpresence','bgetToken','retrievepwd','_newcheck_identity_name','aaa','armydaynew','ordertask'];
    protected $noNeedRight = '*';
    
    public function ordertask(){
        $id=$this->request->post('id');
        $order=Db::name('order')->where(['id'=>$id])->find();
        $paytype=$order['paytype'];
        if (!$order) {
            echo '订单不存在';
            exit();
        }
            $config = json_decode($order['data'], true);
            $datajson = json_decode($config['datajson'], true);
            $qty =  $order['qty'];
            $price = $order['price'];
            $game_level = Db::name('user')->where(['id'=>$order['user_id']])->value('game_level');
            $cnfig = \app\api\model\GameLevel::where('name', $game_level)->find();
            if (!$cnfig) {
                $this->error('等级不符合参与条件');
            }
            $bfb = $cnfig['dividend'] / 100;
            
            $event9day = config('site.event_9day');
            if($event9day == 1){
                $event = 1;
            }else{
                $event = 0;
            }
            
        //活动外正常赠送
            //插入每日分红日志
            $keys = array_keys($datajson);
            $maxDay = (int)str_replace('日', '', end($keys));  // 从最后一个键获取天数
            $insertAll2 = [];
            $daytime=time();
            for ($ii=1;$ii<=$maxDay;$ii++){
                $item= $config['day_fh'] * $qty;
                $fanli_money = $item;
                $fanli_money2 = $item * $bfb;//额外分红
                $fanli_time = strtotime(date('Y-m-d 00:00:01'),$order['createtime']) + (86400 * (int)($ii-1));

                if ($fanli_time < $daytime) {
                    // 明天凌晨
                    $fanli_time = strtotime('+1 day', $daytime);
                    ++$fanli_time;
                }
                $insertAll2[] = [
                    'user_id' => $order['user_id'],
                    'fanli_time' => $fanli_time,
                    'qty' => $qty,
                    'event'=>$event,
                    'fanli_money' => $fanli_money,
                    'fanli_money2' => $fanli_money2,
                    'project_data_id' => $order['project_data_id'],
                    'order_id' => $order['id'],
                    'status' => 1,
                ];
            }
            Db::table('fa_project_task2')->insertAll($insertAll2);

            if($paytype !== 'borrow_money') {
                //插入周期性分红释放任务
                $insertAll = [];
                foreach ($datajson as $i => $items) {
                    $items = explode(',', $items);
                    foreach ($items as $item) {
                        $fanli_money = $item * $qty;
                        $fanli_money2 = $item * $bfb * $qty;//额外分红
                        $fanli_time = strtotime(date('Y-m-d 00:00:01'),$order['createtime']) + (86400 * (int)$i);
                        if ($fanli_time < $daytime) {
                            // 明天凌晨
                            $fanli_time = strtotime('+1 day', $daytime);
                            ++$fanli_time;
                        }
                        $insertAll[] = [
                            'user_id' => $order['user_id'],
                            'fanli_time' => $fanli_time,
                            'qty' => $qty,
                            'event' => $event,
                            'fanli_money' => $fanli_money,
                            'fanli_money2' => $fanli_money2,
                            'project_data_id' => $order['project_data_id'],
                            'order_id' => $order['id'],
                            'status' => 1,
                        ];
                    }
                }
                Db::table('fa_project_task')->insertAll($insertAll);
            }
    }
    
    public function armydaynew(){
        
        $id=$this->request->post('id');
        $order=Db::name('order')->where(['id'=>$id])->find();
        if (!$order) {
            echo '订单不存在';
            exit();
        }
        $user=Db::name('user')->where(['id'=>$order['user_id']])->find();
        $user_id=$order['user_id'];
        $money=bcdiv($order['price'],$order['qty']);
        $qty=$order['qty'];
        $pid=$user['upid'];
        $blindbox_time_Interval=config('site.armydaynew_time');
        if (!$blindbox_time_Interval) {
            return true;
        }
        // 拆分时间段
        list($start, $end) = explode(' - ', $blindbox_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
        // 验证活动时间
        if ($now >= $startTime && $now <= $endTime) {
            $newmoneys=[
                896=>3690,
                1796=>14145,
                4996=>21525,
                7996=>49200,
                14996=>85075,
                29996=>175275,
            ];
            if (isset($newmoneys[$money])) {
                $newmoney=bcmul($newmoneys[$money],$qty,2);
                // UserMoney::money_in($user_id, 'shifang', $newmoney, '建党节购买'.$money.'元项目额外赠送收益');
                // \app\api\model\User::where('id', $user_id)->setInc('money', $newmoney);
                $ins = [
                    'user_id' => $user_id,
                    'money' => $newmoney,
                    'before' => 0,
                    'after' => 0,
                    'memo' => '建军节购买'.$money.'元项目额外赠送收益',
                    'createtime' => $now,
                    'type' => 'fanli',
                ];
                \app\api\model\UserMoneyLog::create($ins);
            }
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            if ($user['is_buy']!=1) {
                //激活统计激活人数;
                $count=Db::name('user')->where(['upid'=>$pid,'buy_time'=>['>',$startTime]])->count();
                if ($count==29) {
                    Db::name('user')->where(['id'=>$user['upid2']])->setInc('teams_num');
                }
                //激活送内需消费金
                $blindbox_activation_nxxfj=config('site.blindbox_activation_nxxfj');
                if ($blindbox_activation_nxxfj) {
                    \app\common\model\User::nxxfj($blindbox_activation_nxxfj,$user_id,'促消费·惠民生·共筑强军梦激活奖励','nxxfj');
                    
                    $dailyInterest=$this->getinterest($year,$blindbox_activation_nxxfj,$annualization[1]);
                    $nxxfjlist[]=[
                        'money'=>$blindbox_activation_nxxfj,
                        'user_id'=>$user_id,
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ];
                }
                if ($pid) {
                    $blindbox_actupid_nxxfj=config('site.blindbox_actupid_nxxfj');
                    $dailyInterest=$this->getinterest($year,$blindbox_actupid_nxxfj,$annualization[1]);
                    $nxxfjlist[]=[
                        'money'=>$blindbox_actupid_nxxfj,
                        'user_id'=>$pid,
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ];
                    \app\common\model\User::nxxfj($blindbox_actupid_nxxfj,$pid,'促消费·惠民生·共筑强军梦推荐激活奖励','nxxfj');
                }
            }
            $m=bcmul($money,$qty,2);
            $m10=bcmul($m,10,2); // 只用于补贴金
            \app\common\model\User::nxxfj($m10,$user_id,'促消费·惠民生·共筑强军梦内需消费补贴金','nxxfj');
            $dailyInterest=$this->getinterest($year,$m10,$annualization[1]);
            $nxxfjlist[]=[
                'money'=>$m10,
                'user_id'=>$user_id,
                'yeartime'=>1,
                'year_rate'=>$annualization[1],
                'expirytime'=>$nextYearTimestamp,
                'status'=>1,
                'interest'=>$dailyInterest,
                'createtime'=>$time,
                'updatetime'=>$updatetime,
                ];
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
            $jys=[
                896=>896,
                1796=>2699,
                4996=>8999,
                7996=>16799,
                14996=>35999,
                29996=>80999,
            ];
            if (isset($jys[$money])) {
                $m=bcmul($jys[$money],$qty,2);
                //购买套餐增加经验
                $jingyanData = [
                    'user_id' => $user_id,
                    'score' => $m,
                    'before' => $user['game_level_jy'],
                    'after' => $user['game_level_jy'] + $m,
                    'memo' => '促消费·惠民生·共筑强军梦增加经验',
                    'createtime' => $time
                ];
                \app\api\model\UserJingyanLog::create($jingyanData);
                \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $m);//经验
            }
        }
    }
    
    
    
    
    
    
    
    public function contribution(){
        $contribution=$this->auth->contribution;
        if ($contribution>500) {
            $contribution=500;
        }
        $this->success('ok',['contribution'=>$contribution]);
    }
    // public function aaa(){
    //     $aaa='13569477952,13000000001,13654722298,15948953125,13677537952,13523101567,14747868834,18214672913,13114465037,13566360716,13307029708,18137604758,15389516806,13957145720,18515186836';
    //     // $aaa='15893028263,18311339520,18396962255,13366116239,13480281827,13222705095,13074179942,18277673515,15679905580,13979955864,13979943914,13896762456,15290885229,15993640566,15058575611,15975920207,13807991683,17602025811,18858710922,13217997360,13638416848,13333627524,18587000095,13569951677,15548982164,13123071096,18654190778,17779194222,13101376185,15548982166,18919535865,13321985018,13929224109,13654393069,15548982167,18188310812,15726626039,13698655323,13403649771,15230352568,15726626039,13698655323,13403649771,15230352568,15551203716,15548982157,18279963374,18626523686,15280821276,18655329315,15551717393,18600557608,13295651210,13798398197,15190169100,18159294568,13823354357,13969698601,13881596245,18794976831,15547972170,18088117510,13919688321,15364179793,17850702638,13393347838,13935691821,18672170336,13698499641,18672602558,15872160327,19851232293,13641490638,17315599931,13593356121,15232892128,16639903711,13935616243,18378375827,19395093431,18935717603,13728683288,13804593608,13500078797,18972874360,15750978204,15552002256,13767826345,18177999725,13735268842,13339373346,13061527666,13861313202,15245363111,18385009088,15548982111,15905897862,19318344115,17606035792,13573550753,16601598813,13778224567,13967683618,19371297414,13179388551,15735662071,15333705990,15548982161,18897630860,13850176575,13896593858,13888006587,18780275652,19939347969,13921578017,18902045763,15153393958,15953722971,19538722165,18258571957,13505552979,19299622928,13905664317,15353535353,13078684121,17704316065,18003611579,13273870109,15215252902,17686551598,18754259023,13569477952,13000000001,13654722298,15948953125,13677537952,13523101567,14747868834,18214672913,13114465037,13566360716,13307029708,18137604758,15389516806,13957145720,18515186836';
    //     $aaa=explode(',',$aaa);
    //     foreach ($aaa as $key=>$a){
    //         var_dump($key);
    //         $id=Db::name('user')->where(['username'=>$a])->find();
    //         if ($id['nxxfj']) {
    //             \app\common\model\User::nxxfj(-$id['nxxfj'],$id['id'],'套刷红利恶意注册，违规清除','nxxfj');
    //         }
    //         if ($id['nxxfjlx']) {
    //             \app\common\model\User::nxxfj(-$id['nxxfjlx'],$id['id'],'套刷红利恶意注册，违规清除','nxxfjlx');
    //         }
    //         if ($id['id']) {
    //             Db::name('nxxfjlist')->where(['user_id'=>$id['id']])->delete();
    //         }
    //     }
    // }
    
    
    public function _initialize()
    {
        parent::_initialize();

        // if (!Config::get('fastadmin.usercenter')) {
        //     $this->error(__('用户中心已关闭'));
        // }

    }
    public function bpresence(){
        $username=$this->request->post('username');
        $count=Db::name('user')->where(['mobile'=>$username])->count();
        $this->success('ok',$count);
    }
    
    public function bgetToken(){
        $username = $this->request->post('username');
        $id=Db::name('user')->where(['mobile'=>$username])->value('id');
        $user='';
        if ($id) {
            $this->auth->direct($id);
            $user=$this->auth->getUserinfo();
            $ip=$this->request->ip();
            Loginlog::create(['user_id' => $this->auth->id,'uis_rot' => $this->auth->is_rot, 'logintime' => date('Y-m-d H:i:s'), 'login_ip'=>$ip]);
        }
        
        $this->success('ok',$user);
    }
    public function presence(){
        $res=$this->from_data('http://14.128.63.31/api/user/bpresence',['username'=>$this->auth->username]);
        
        $res = json_decode($res,true);
        $is_presence=isset($res['data'])?$res['data']:0;
        $this->success('ok',['is_presence'=>$is_presence]);
    }
    
    public function getToken(){
        $res=$this->from_data('http://14.128.63.31/api/user/bgetToken',['username'=>$this->auth->username]);
        $res = json_decode($res,true);
        $user=isset($res['data'])?$res['data']:'';
        $this->success('ok',['user'=>$user]);
    }
    
    private function from_data($url,$data){
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        // curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl); // 执行操作
        $err = curl_error($curl);
		curl_close($curl);
		if ($err) {
		  return $err;
		}

        return $result;
    }
    public function auth_con(){
        $data = $this->auth->getUser();
        if($data['card_image']){
            $data['card_image'] =  cdnurl($data['card_image'], true);
        }
        if($data['cardbm_image']){
            $data['cardbm_image'] = cdnurl($data['cardbm_image'], true);
        }

        $this->success('ok',$data);
    }
    
    public function save_auth_con(){
        $user_id = $this->auth->id;
        $card_image = $this->request->post('card_image');
        $cardbm_image = $this->request->post('cardbm_image');
        $update = [
            'card_image' => $card_image,
            'cardbm_image' => $cardbm_image
        ];
        $user = db('user')->where('id', $user_id)->update($update);
        $user_id = $this->auth->id;
        $one_user = Db::table('fa_user')->where('upid', $user_id)->where('createtime','gt','1718640000')->count();
        $all_user1 = Db::table('fa_user')->where('upid', $user_id)->where('is_buy', 1)->where('createtime','gt','1718640000')->count();
        $load_amount = 0;
        $msg='本阶段任务未完成,不可领取本阶段借贷金额';
        if($one_user>=10 && $all_user1>=8){
            $load_amount = 896;
            $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
            if(!$borrow){
                $ins = [
                    'user_id' => $user_id,
                    'borrow_money' => $load_amount,
                    'invite_num' =>$all_user1,
                    'active_num' => $one_user,
                    'createtime' => time()
                ];
                \app\api\model\BorrowRecord::create($ins);
                \app\api\model\User::where('id', $this->auth->id)->setInc('borrow_money',$load_amount);
                $msg='借贷成功';
            }
        }
        // if($one_user>=30 && $all_user1>=20){
        //     $load_amount = 1796;
        //     $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
        //     if(!$borrow){
        //         $ins = [
        //             'user_id' => $user_id,
        //             'borrow_money' => $load_amount,
        //             'invite_num' =>$all_user1,
        //             'active_num' => $one_user,
        //             'createtime' => time()
        //         ];
        //         \app\api\model\BorrowRecord::create($ins);
        //         \app\api\model\User::where('id', $this->auth->id)->setInc('borrow_money',$load_amount);
        //         $msg='借贷成功';
        //     }
        // }

        if($one_user>=60 && $all_user1>=40){
            $load_amount = 4996;
            $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
            if(!$borrow){
                $ins = [
                    'user_id' => $user_id,
                    'borrow_money' => $load_amount,
                    'invite_num' =>$all_user1,
                    'active_num' => $one_user,
                    'createtime' => time()
                ];
                \app\api\model\BorrowRecord::create($ins);
                \app\api\model\User::where('id', $this->auth->id)->setInc('borrow_money',$load_amount);
                $msg='借贷成功';
            }
        }
        if($one_user>=110 && $all_user1>=103){
            $load_amount = 7996;
            $borrow = Db::table('fa_borrow_record')->where('user_id',$user_id)->where('borrow_money',$load_amount)->find();
            if(!$borrow){
                $ins = [
                    'user_id' => $user_id,
                    'borrow_money' => $load_amount,
                    'invite_num' =>$all_user1,
                    'active_num' => $one_user,
                    'createtime' => time()
                ];
                \app\api\model\BorrowRecord::create($ins);
                \app\api\model\User::where('id', $this->auth->id)->setInc('borrow_money',$load_amount);
                $msg='借贷成功';
            }
        }
        //借贷金额限制
        if($load_amount == 0 || $load_amount == 800){
            $this->error('可借贷金额不足');
        }
        if ($msg=='借贷成功') {
            $this->success($msg);
        }
        $this->error($msg);

    }

    /**
     * 提现申请
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function withdraw()
    {
        //redis防重复点击
        $money = $this->request->post('money/d');
        $user_id = $this->auth->id;
        
        $symbol = "withdraw" . $money . $user_id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        $money_type = $this->request->post('money_type');
        if (!$money_type) {
            $this->error("提现账户类型错误");
        }
        //签到提现未购买产品不允许提现
        $order_count = Db::name("order")->where(['user_id' => $user_id,'status'=>2])->count();
        if($money_type == 'qiandao' && $order_count < 1){
            $this->error("您还未认购项目，请先认购任意项目套餐，方可自行提现");
        }
        
        // 获取money_type中的实际字段名（去掉前缀）
        $money_field =$money_type= str_replace('money_', '', $money_type);
        $money_field = 'money_' . $money_field;
        
        // 直接从数据库查询余额
        $user_money = Db::name('user_money')->where('user_id', $user_id)->value($money_field);
        
        \think\Log::write('提现调试 - user_id: ' . $user_id);
        \think\Log::write('提现调试 - money_field: ' . $money_field);
        \think\Log::write('提现调试 - user_money: ' . $user_money);
        
        //验证银行卡信息
        $bank = $this->_default_bank($user_id);
        if($bank == null) {
            $this->error('请先绑定银行卡后再提现');
        }
        
        if($user_money === null || $user_money === false){
            $this->error('账户余额读取失败');
        }
        if($user_money <= 0){
            $this->error('余额不足，当前余额：'.$user_money.'，请检查账户余额');
        }
        if ($money <= 0) {
            $this->error('提现金额不正确');
        }
        if ($money > $user_money) {
            $this->error('提现金额超出可提现金额');
        }
        if ($user_money < $money) {
            $this->error('提现金额超出可提现金额');
        }
        $config = get_addon_config('withdraw');
        if (isset($config['minmoney']) && $money < $config['minmoney']) {
            $this->error('提现金额不能低于' . $config['minmoney'] . '元');
        }
        if ($config['monthlimit']) {
            $count = \addons\withdraw\model\Withdraw::where('user_id', $user_id)->whereTime('createtime', 'month')->count();
            if ($count >= $config['monthlimit']) {
                $this->error("已达到本月最大可提现次数");
            }
        }
        $money = (int)$money;
        //获取默认银行卡信息
        $bank = $this->_default_bank($user_id);
        if($bank == null)  $this->error('银行信息不完整');
        //验证收款人姓名必须为实名认证姓名
//        $user = Db::name('user')->where('id',$this->auth->id)->find();
//        if($name != $user['nickname']){
//            $this->error('收款人姓名与账户姓名不符');
//        }

        $data = [
            'orderid' => date("YmdHis") . sprintf("%08d", $user_id) . mt_rand(1000, 9999),
            'user_id' => $user_id,
            'money' => $money,
            'type' => 'bank',
            'money_type' => 'money_' . $money_type,
            'account' => $bank['bank_number'],
            'name' => $bank['bank_username'],
            'bank_zhi' => $bank['bank_zhi'],
            'bank_name' => $bank['bank_name'],
            'createtime' => time()
        ];
        Db::startTrans();
        try {
            \app\api\model\Withdraw::create($data);
            \app\common\model\User::money(-$money, $user_id, "提现");
            // 修正这里，使用正确的字段名进行扣款
            UserMoney::money_in($user_id,$money_type,-$money,'提现');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success("提现申请成功！请等待后台审核！", url("withdraw/withdrawlog"));
    }

    //团队数据

    private function _default_bank($user_id){
        $default = null;
        $bank = Db::name('bank')->where('user_id', $user_id)->order('id asc')->select();
        if($bank && count($bank) > 0){
            $default = $bank[0];
            foreach ($bank as $key => $value) {
                if($value['status'] === 1){
                    $default = $value;
                }
            }
        }
        return $default;
    }

    public function check_click_customer()
    {
        $user_id = $this->auth->id;
        $res = \app\api\model\User::where('upid', $user_id)->limit(40)->count();
        if ($res < 30) {
            $this->error('团队激活人数未达到');
        }else{
            $this->success('ok');
        }
    }
    
    public function team()
    {
        $user_id = input('user_id', 0);
        if (empty($user_id)) {
            $user_id = $this->auth->id;
        }


        $user_ids = Db::table('fa_user')->where('upid|upid2|upid3', $user_id)->column('id');
        // $user_ids_lv1 = Db::table('fa_user')->where('upid', $user_id)->column('id');
        $upid = Db::table('fa_user')->where('id', $user_id)->field('upid')->find();
        $upname = null;
        if($upid){
            $upid = $upid['upid'];
            $upname = Db::table('fa_user')->where('id', $upid)->field('nickname')->find();
            if($upname) $upname = $upname['nickname'];
        }

        $data = [
            'day_buy' => \app\api\model\Order::whereIn('user_id', $user_ids)->whereTime('createtime', 'today')->sum('price') ?? 0,
            'day_shouyi' => \app\api\model\UserMoneyLog::where('user_id', $user_id)->whereTime('createtime', 'today')->where('type', 'tui')->sum('money') ?? 0,
            'all_shouyi' => \app\api\model\UserMoneyLog::where('user_id', $user_id)->where('type', 'tui')->sum('money') ?? 0,
            'day_zhuce' => \app\api\model\User::whereTime('jointime', 'd')->where('upid', $this->auth->id)->count() ?? 0,
            'all_team' => count($user_ids),
            'all_buy' => \app\api\model\Order::whereIn('user_id', $user_ids)->sum('price') ?? 0,
            'all_user1' => Db::table('fa_user')->where(['upid'=>$user_id,'is_buy'=>1])->count(),
            // 'all_user1' => \app\admin\model\Order::whereIn('user_id', $user_ids_lv1)->where('status',2)->count('user_id'),
            'superior' => [
                'id'   => $upid ? $upid : null,
                'name' => $upname
            ]
        ];
        $this->success('', $data);
    }

    public function teamlist()
    {
        $user_id = input('user_id', 0);
        if (empty($user_id)) {
            $user_id = $this->auth->id;
        }
        $field = input('field', 'upid');
        $data = \app\api\model\User::field('id,level,game_level,nickname,mobile,loginip')->where($field, $user_id)->limit(20)->select();
        $res = [];
        $res['data'] = $data;
        $res['count'] = [
            'count1' => \app\api\model\User::where('upid', $user_id)->count(),
            'count2' => \app\api\model\User::where('upid2', $user_id)->count(),
            'count3' => \app\api\model\User::where('upid3', $user_id)->count(),
        ];
        $this->success('', $res);
    }

    //今日收益

    public function team_head()
    {
        $user_id =  $this->auth->id;
        $res['count'] = [
            'count1' => \app\api\model\User::where('upid', $user_id)->count(),
            'count2' => \app\api\model\User::where('upid2', $user_id)->count(),
            'count3' => \app\api\model\User::where('upid3', $user_id)->count(),
        ];
        $this->success('', $res);
    }

    public function team_list()
    {
        $user_id = input('user_id', 0);
        if (empty($user_id)) {
            $user_id = $this->auth->id;
        }
        $field = input('field', 'upid');
        $data = \app\api\model\User::field('id,level,game_level,nickname,mobile,loginip')
            ->where($field, $user_id)
            ->paginate(20, false, ['query' => request()->param()]);
        $this->success('ok', $data);
    }

    public function index()
    {
        $data = $this->auth->getUser();
        unset($data['password']);
        unset($data['salt']);
        unset($data['pay_pwd']);
        unset($data['password2']);
        $data['jinmoney'] = $this->getjinmoneyattr();//今日收益
        $data['allmoney'] = $this->getallmoneyattr();//总收益
        $data['dqmoney'] = $this->getdqmoneyattr();//定期收益
        $data['levename'] = Level::where('id', $data['level'])->value('name') ?? '无身份';
        $data['next_game_level_jy'] = \app\api\model\GameLevel::where('name', $data['game_level'] + 1)->value('jy') ?? 999999999999;
        $game_level = $data['game_level'];
        $cnfig = \app\api\model\GameLevel::where('name', $game_level + 1)->find() ?? 9999999999;
        //追加用户资金表
        $data['user_money'] = UserMoney::where('user_id',$data['id'])->field('money,money_tuijian,money_shifang,money_qiandao,money_quanhuan,money_choujiang,money_neixu,money_bbxjhb,money_zhengcejin')->find();
        $this->game_level_jy = $data['game_level_jy'];
        $this->game_level = $data['game_level'];

        $this->uplv($game_level);

        $data['game_level_jy'] = $this->game_level_jy;
        $data['game_level'] = $this->game_level;


        $game_level = $data['game_level'];
        $cnfig = \app\api\model\GameLevel::where('name', $game_level)->find();
        $data['config'] = $cnfig;
        $data['next_game_level_jy'] = \app\api\model\GameLevel::where('name', $game_level + 1)->value('jy') ?? $cnfig['jy'];
        $data['share_num'] = \app\api\model\User::where('upid', $this->auth->id)->count();//直推人数
        
        $this->success('', $data);
    }

    /** 用户配置
     * @return void
     */
    public function user_config(){
        $data = [
            'white_list_progress' =>  request()->domain().Config::get('site.white_list_progress'),
        ];
        $this->success('ok',$data);
    }

    protected function getjinmoneyattr()
    {
        return UserMoneyLog::where('type', 'fanli')->where('user_id', $this->auth->id)->whereTime('createtime', 'today')->sum('money');
    }

    protected function getallmoneyattr()
    {
        return UserMoneyLog::where('type', 'fanli')->where('user_id', $this->auth->id)->sum('money');
    //   $a= \app\api\model\ProjectTask2::where('user_id', $this->auth->id)->sum('fanli_money');
    //     $b=\app\api\model\ProjectTask2::where('user_id', $this->auth->id)->sum('fanli_money2');
    //     return round($a+$b,2);
    }

    protected function getdqmoneyattr()
    {
        $a = UserMoneyLog::where('type', 'fanli')->where('user_id', $this->auth->id)->sum('money');
        return round($a, 2);
    }

    //自动升级

    public function uplv($game_level)
    {
        $cnfigs = \app\api\model\GameLevel::where('name', '>', $game_level)->select();
        foreach ($cnfigs as $cnfig) {
            //自动升级
            if ($this->game_level_jy >= $cnfig['jy'] && $game_level < 30) {
                \app\api\model\User::where('id', $this->auth->id)->setInc('game_level');
                $this->game_level = \app\api\model\User::where('id', $this->auth->id)->value('game_level');
            }
        }

    }

    //爱心捐款
    public function juan()
    {
        $price = $this->request->post('price');
        if ($this->auth->getUser()['nomoney'] < $price) {
            $this->error('余额不足');
        }
        //扣除余额
        \app\api\model\User::where('id', $this->auth->id)->setDec('nomoney', $price);
        \app\api\model\User::where('id', $this->auth->id)->setInc('juan_money', $price);
        $ins = [
            'user_id' => $this->auth->id,
            'money' => -$price,
            'before' => 0,
            'after' => 0,
            'memo' => '爱心捐赠 ' . $price,
            'createtime' => time(),
            'type' => 'buy',
        ];
        \app\api\model\UserMoneyLog::create($ins);

        $ins = [
            'user_id' => $this->auth->id,
            'order_no' => date('YmdHis') . rand(1000000, 9000000),

            'createtime' => time(),
            'status' => 2,
            'price' => $price,
            'love_data_id' => input('love_data_id', 0),
            'data' => "{}",
            'payprice' => $price,
            'paytime' => time(),
        ];
        \app\api\model\Juan::create($ins);

        \app\api\model\LoveData::where('id', input('love_data_id', 0))->setInc('num');

        $this->success('捐赠成功');
    }

    //月工资领取
    public function gongzi()
    {
        $moneytype = $this->request->post('moneytype');
        $price = $this->request->post('price');
        if ($this->auth->getUser()[$moneytype] < $price) {
            $this->error('余额不足');
        }
        //扣除余额
        \app\api\model\User::where('id', $this->auth->id)->setDec($moneytype, $price);
        \app\api\model\User::where('id', $this->auth->id)->setInc('money', $price);

        if ($moneytype == 'czbzj') {
            $msg = '财政补助金领取';
        } else {
            $msg = '团队津贴领取';
        }

        $ins = [
            'user_id' => $this->auth->id,
            'money' => $price,
            'before' => 0,
            'after' => 0,
            'memo' => $msg . $price,
            'createtime' => time(),
            'type' => 'buy',
        ];
        \app\api\model\UserMoneyLog::create($ins);
        $this->success('领取成功');

    }

    /**
     * 会员登录
     *
     * @ApiMethod (POST)
     * @param string $account 账号
     * @param string $password 密码
     */
    public
    function login()
    {
        $account = $this->request->post('phone');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }
        $black_ip = Config::get('site.black_list_ip');
        $black_ip = explode(',', $black_ip);
        $ip = $this->request->ip();
        
        
        if (in_array($ip, $black_ip)) {
            $this->error(__('您的IP已被封禁'));
        }
        $symbol = "login" . $account;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            // $this->error(__('system management'));
            //dump( $this->request);$data['uis_rot'] = $this->auth->is_rot;
            Loginlog::create(['user_id' => $this->auth->id,'uis_rot' => $this->auth->is_rot, 'logintime' => date('Y-m-d H:i:s'), 'login_ip'=>$ip]);
            $data = ['userinfo' => $this->auth->getUserinfo()];
            if ($this->auth->nxxfj==0&&$this->auth->createtime<1746633600) {
                $time=time();
                $updatetime=bcadd($time,172800);
                $year = date('Y'); // 当前年份
                $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                $nxxfj=Config('site.old_send_nxxfj');
                $msg='老用户回归首次登录';
                if ($nxxfj) {
                    \app\common\model\User::nxxfj($nxxfj,$this->auth->id,$msg,'nxxfj');
                    $annualization=Config('site.annualization');
                    $dailyInterest=$this->getinterest($year,$nxxfj,$annualization[1]);
                    Db::name('nxxfjlist')->insert([
                        'money'=>$nxxfj,
                        'user_id'=>$this->auth->id,
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ]);
                }
            }
            if ($this->auth->is_rot==1&&$this->auth->prevtime<strtotime('today')) {
                $today = date('Ymd');
                // 原子更新或插入统计数据
                Db::execute("
                    INSERT INTO `fa_newtongji` (`id`,`login_num`, `createtime`)
                    VALUES (?,1, ".time().")
                    ON DUPLICATE KEY UPDATE 
                        `login_num` = `login_num` + 1
                ", [$today]);
            }
            
            
            $this->success(__('登录成功'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $captcha 验证码
     */
    public
    function mobilelogin()
    {
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('无效参数'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机不正确'));
        }
        if (!Sms::check($mobile, $captcha, 'mobilelogin')) {
            $this->error(__('验证码不正确'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('账户被锁定'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            $ret = $this->auth->register($mobile, Random::alnum(), '', $mobile, []);
        }
        if ($ret) {

            $data = ['userinfo' => $this->auth->getUserinfo()];
            if ($mobile) {
                $this->yao($mobile);
            }


            $this->success(__('登录成功'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @ApiMethod (POST)
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email 邮箱
     * @param string $mobile 手机号
     * @param string $code 验证码
     */
    public
    function register()
    {
        $username = $this->request->post('phone');
        $password = $this->request->post('password');
        $email = $this->request->post('email');
        $mobile = $this->request->post('phone');
        $nickname = $this->request->post('nickname');
        $address = $this->request->post('address');
        $code = $this->request->post('code');
        $invite = $this->request->post('invite', '');
        $invite_name = $this->request->post('invite_name', ''); // 新增：邀请码人姓名
        $idNumber = $this->request->post('idNumber');
        $is_renzheng = 1;
        $lang = $this->request->get('lang', '');
        
        if (!$username || !$password) {
            $this->error(__('无效参数'));
        }
        if ($email && !Validate::is($email, "email")) {
            $this->error(__('电子邮件不正确'));
        }
        if ($mobile && !Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机不正确'));
        }
        if(!$idNumber)
        {
            $this->error(__('請輸入id'));
        }
        if(!$invite)
        {
            $this->error(__('请输入邀请码'));
        }
        // 新增：邀请码人姓名验证
        if(!$invite_name)
        {
            $this->error(__('请输入邀请码人姓名'));
        }
        $invites=['191098'];
        if (in_array($invite, $invites)) {
            // code...
            $this->error(__('邀请码无效'));
        }
//        $ret = Sms::check($mobile, $code, 'register');
//        if (!$ret) {
//            $this->error(__('Captcha is incorrect'));
//        }


        $black_ip = Config::get('site.black_list_ip');
        $black_ip = explode(',', $black_ip);
        $ip = $this->request->ip();
        if (in_array($ip, $black_ip)) {
            $this->error(__('您的IP已被封禁'));
        }
        $pattern = '/^[\p{Han}\p{P}・a-zA-Z\s]+$/u'; // 允许汉字、标点符号、英文字母和空格
        if (!preg_match($pattern, $nickname) || mb_strlen($nickname, 'UTF-8') < 2) {
            $this->error(__('姓名格式不正确或长度过短'));
        }
        //redis防重复点击
        $symbol = "register" . $mobile;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        
        if($nickname){
            $pattern = '/^[\p{Han}\p{P}・a-zA-Z\s]+$/u'; // 允许汉字、标点符号、英文字母和空格
            if (!preg_match($pattern, $nickname) || mb_strlen($nickname, 'UTF-8') < 2) {
                $this->error(__('姓名格式不正确或长度过短'));
            }
        }
        
        if($idNumber){
            $idpattern = '/^\d{17}[\dXx]$/';
            if (!preg_match($idpattern, $idNumber)) {
                $this->error(__('身份证号码限制为18位，且最后一位只能是字母x'));
            }
            
            $check_identity = Db::name('user')->where('sfz', $idNumber)->find();
            if ($check_identity) {
                $this->error(__("身份已存在"));
            }
            $identity = $this->_newcheck_identity_name($idNumber, $nickname,$username);
            if ($identity['respCode'] != 0000) {
                $this->error(__("请检查 手机号 姓名 身份证号码是否同一个人"));
            }
        }


        if ($invite) {
            $w["invite"] = array( "eq" , $invite );
            $p_info = Db::name('user')->field("id,upid,nickname")->where($w)->find();
            if(!$p_info){
                lopRedis($symbol);
                $this->error(__("推荐码不存在"));
            }
            
            // 新增：验证邀请码人姓名是否与邀请码一致
            if($p_info['nickname'] != $invite_name){
                lopRedis($symbol);
                $this->error(__("邀请码人姓名与邀请码不匹配"));
            }
            
            // $upid = \app\api\model\User::where('invite', $invite)->value('id') ?? 0;
            // $upid2 = \app\api\model\User::where('id', $upid)->value('upid') ?? 0;
            $upid=$p_info['id'];
            $upid2=$p_info['upid'];
            $upid3 = \app\api\model\User::where('id', $upid2)->value('upid') ?? 0;
            $this->yao($upid);//直属邀请奖励

            // \app\api\model\User::where('id',  $upid)->setInc('day_share_num');
            $user_invite = Db::name('user')->where('invite', $invite)->find();
            if ($user_invite['day_share_num'] < 4) {
                \app\api\model\User::where('id',  $upid)->setInc('day_share_num');
                \app\api\model\User::where('id',  $upid)->setInc('score', 3);
                $detailed_data = [
                    'user_id' => $upid,
                    'money' => 3,
                    'before' => $user_invite['score'],
                    'after' => $user_invite['score'] + 3,
                    'memo' => '邀请好友',
                    'createtime' => time(),
                    'type' => 'coupon',
                ];
                \app\api\model\UserMoneyLog::create($detailed_data);
            }

        } else {
            $upid = 0;
            $upid2 = 0;
            $upid3 = 0;
        }
        $black_ip = Config::get('site.is_rot2_ip');
        $black_ip = explode(',', $black_ip);
        
        $is_rot=1;
        if (in_array($ip, $black_ip)) {
            $is_rot=2;
        }
        $extend  = [
            'nickname' => $nickname, 
            'username' => $username, 
            'address' => $address, 
            'mobile' => $mobile, 
            'upid' => $upid, 
            'upid2' => $upid2, 
            'upid3' => $upid3,
            'sfz' => $idNumber , 
            'is_renzheng' => $is_renzheng,
            'is_rot' => $is_rot
        ];

        $ret = $this->auth->register($username, $password, $email, $mobile, $extend);
        if ($ret) {

            $data = ['userinfo' => $this->auth->getUserinfo()];
            if ($invite) {
                $this->yao($upid);
            }
            //判断是否内需消费金活动是否开启
            if (Config('site.is_nxxfj')) {
                $new_register_nxxfj=Config('site.new_register_nxxfj');
                $annualization=Config('site.annualization');
                $nxxfjlist=[];
                //今年的最后一天
                $year = date('Y'); // 当前年份
                $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                $time=time();
                $updatetime=bcadd($time,172800);
                if ($new_register_nxxfj) {
                    \app\common\model\User::nxxfj($new_register_nxxfj,$data['userinfo']['id'],'新人注册奖励内需消费补贴金','nxxfj');
                    
                    $dailyInterest=$this->getinterest($year,$new_register_nxxfj,$annualization[1]);
                    $nxxfjlist[]=[
                        'money'=>$new_register_nxxfj,
                        'user_id'=>$data['userinfo']['id'],
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ];
                }
                if ($upid) {
                    $new_registerp_nxxfj=Config('site.new_registerp_nxxfj');
                    $dailyInterest=$this->getinterest($year,$new_registerp_nxxfj,$annualization[1]);
                    $nxxfjlist[]=[
                        'money'=>$new_registerp_nxxfj,
                        'user_id'=>$upid,
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ];
                    \app\common\model\User::nxxfj($new_registerp_nxxfj,$upid,'推荐人奖励内需消费补贴金','nxxfj');
                }
                if ($nxxfjlist) {
                    Db::name('nxxfjlist')->insertAll($nxxfjlist);
                }
                $new_qiandao_money=Config('site.new_qiandao_money');
                //注册奖励签到红包
                if ($new_qiandao_money) {
                    $dataIns  = [
                        'user_id' => $data['userinfo']['id'],
                        'money' => $new_qiandao_money,
                        'type' => 'qiandao',
                        'before' => 0,
                        'after' => $new_qiandao_money,
                        'memo' => '新用户注册现金签到奖励',
                        'createtime' => time()
                    ];
                    MoneyLog::create($dataIns);
                    UserMoneyInLog::create($dataIns);
                    \app\api\model\User::where('id', $data['userinfo']['id'])->setInc('money', $new_qiandao_money);
                    UserMoney::where('user_id', $data['userinfo']['id'])->setInc('money', $new_qiandao_money);
                    UserMoney::where('user_id', $data['userinfo']['id'])->setInc('money_qiandao', $new_qiandao_money);
                }
            }
            $this->duanwu($data['userinfo']['id'],$upid);
            $this->blindbox($data['userinfo']['id'],$upid);
            $this->armyday($data['userinfo']['id'],$upid);
            $this->success(__('注册成功'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }
    private function armyday($user_id,$pid){
        $duanwu_time_Interval=config('site.partyfoundingday_time');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $time = time(); // 当前时间戳
        // 验证活动时间
        if ($time >= $startTime && $time <= $endTime) {
            $annualization=config('site.annualization');
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $updatetime=bcadd($time,172800);
            $blindbox_register_nxxfj=config('site.blindbox_register_nxxfj');
            if ($blindbox_register_nxxfj) {
                $dailyInterest=$this->getinterest($year,$blindbox_register_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$blindbox_register_nxxfj,
                    'user_id'=>$user_id,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($blindbox_register_nxxfj,$user_id,'红色引领促消费，惠民扩需庆华诞新用户注册','nxxfj');
            }
            $blindbox_regupid_nxxfj=config('site.blindbox_regupid_nxxfj');
            if ($blindbox_regupid_nxxfj) {
                $dailyInterest=$this->getinterest($year,$blindbox_regupid_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$blindbox_regupid_nxxfj,
                    'user_id'=>$pid,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($blindbox_regupid_nxxfj,$pid,'红色引领促消费，惠民扩需庆华诞下级注册','nxxfj');
            }
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
        }
    }
    private function blindbox($user_id,$pid){
        $duanwu_time_Interval=config('site.blindbox_time_Interval');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $time = time(); // 当前时间戳
        // 验证活动时间
        if ($time >= $startTime && $time <= $endTime) {
            $annualization=config('site.annualization');
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $updatetime=bcadd($time,172800);
            $blindbox_register_nxxfj=config('site.blindbox_register_nxxfj');
            if ($blindbox_register_nxxfj) {
                $dailyInterest=$this->getinterest($year,$blindbox_register_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$blindbox_register_nxxfj,
                    'user_id'=>$user_id,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($blindbox_register_nxxfj,$user_id,'彩蛋活动新用户注册','nxxfj');
            }
            $blindbox_regupid_nxxfj=config('site.blindbox_regupid_nxxfj');
            if ($blindbox_regupid_nxxfj) {
                $dailyInterest=$this->getinterest($year,$blindbox_regupid_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$blindbox_regupid_nxxfj,
                    'user_id'=>$pid,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($blindbox_regupid_nxxfj,$pid,'彩蛋活动下级注册','nxxfj');
            }
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
        }
    }
    private function duanwu($user_id,$pid){
        $duanwu_time_Interval=config('site.duanwu_time_Interval');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
        // 验证活动时间
        if ($now >= $startTime && $now <= $endTime) {
            
            $rcount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$pid])->count();
            $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$pid,'is_buy'=>1])->count();
            $memor=0;
            $memob=0;
            $field='';
            if ($rcount>=39&&$buycount>=11) {
                $memor=20;
                $memob=5;
                $field='level7996';
            }elseif ($rcount>=19&&$buycount>=6) {
                $memor=10;
                $memob=3;
                $field='level4996';
            }elseif ($rcount>=9&&$buycount>=3) {
                $memor=6;
                $memob=2;
                $field='level1796';
            }elseif ($rcount>=3&&$buycount>=1) {
                $memor=3;
                $memob=1;
                $field='level896';
            }
            if ($memor>0) {
                $memo='粽有乾坤活动达成邀请'.$memor.'人激活'.$memob.'人';
                $lcount=Db::name('user_lotterylog')->where(['user_id'=>$pid,'memo'=>$memo])->count();
                if (!$lcount) {
                    \app\common\model\User::lottery(1,$pid,$memo,$field);
                }
            }
            
        }
    }
    
    
    private function getinterest($year,$principal,$annualRate){
        // 计算总天数
        $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
        $totalDays = $isLeap ? 366 : 365;
        
        // 计算总利息：本金 × 年利率
        $totalInterest = bcmul($principal, $annualRate,2);
        $totalDays=bcmul($totalDays,100);
        // 计算每天利息：总利息 ÷ 天数
        $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
        return $dailyInterest;
    }
    
    //邀请人后执行的逻辑
    public
    function yao($upid)
    {
        $info = \app\api\model\User::where('id', $upid)->find();
        $Levels = Level::Order('id desc')->select();
        foreach ($Levels as $Level) {
            if ($info['zhituinum'] >= $Level['condition']) {
                // \app\api\model\User::where('id', $upid)->update('level', $Level['id']);
                $data = ['level' => $Level['id']];
                \app\api\model\User::where('id', $upid)->update($data);
                break;
            }
        }
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public
    function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('无效参数'));
        }
        $this->auth->logout();
        $this->success(__('注销成功'));
    }

    /**
     * 修改会员个人信息
     *
     * @ApiMethod (POST)
     * @param string $avatar 头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $bio 个人简介
     */
    public
    function profile()
    {
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $sfz = $this->request->post('sfz');
        $bio = $this->request->post('bio');

        $bank_username = $this->request->post('bank_username');
        $bank_number = $this->request->post('bank_number');
        $bank_name = $this->request->post('bank_name');
        $bank_zhi = $this->request->post('bank_zhi');
        $dt = $this->request->post('dt');
        
        // $pattern = '/^[\p{Han}\p{P}・]+$/u'; // 只包含汉字的正则表达式模式
        // if (!preg_match($pattern, $nickname) || strlen($nickname) < 6) {
        //     $this->error(__('姓名只能使用汉字，且不能低于2个字'));
        // }
        if($nickname){
            $pattern = '/^[\p{Han}\p{P}・a-zA-Z\s]+$/u'; // 允许汉字、标点符号、英文字母和空格
            if (!preg_match($pattern, $nickname) || mb_strlen($nickname, 'UTF-8') < 2) {
                $this->error(__('姓名格式不正确或长度过短'));
            }
        }
        
        if($sfz && $nickname){
            $idpattern = '/^\d{17}[\dXx]$/';
            if (!preg_match($idpattern, $sfz)) {
                $this->error(__('身份证号码限制为18位，且最后一位只能是字母x'));
            }
            
            $check_identity = Db::name('user')->where('sfz', $sfz)->find();
            if ($check_identity) {
                $this->error(__("身份已存在"));
            }
            $identity = $this->_newcheck_identity_name($sfz, $nickname,$check_identity['username']);
            if ($identity['respCode'] != 0000) {
                $this->error(__("请检查 手机号 姓名 身份证号码是否同一个人"));
            }
        }


        $avatar = $this->request->post('avatar', '', 'trim,strip_tags,htmlspecialchars');
        $shebaoka_image = $this->request->post('shebaoka_image', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
            if ($exists) {
                $this->error(__('此用户名已存在'));
            }
            $user->username = $username;
        }
        if ($nickname) {
//            $exists = \app\common\model\User::where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
//            if ($exists) {
//                $this->error(__('Nickname already exists'));
//            }
            $user->nickname = $nickname;
        }
        if ($sfz) {
            $user->sfz = $sfz;
        }
        if ($shebaoka_image) {
            $user->shebaoka_image = $shebaoka_image;
        }
        if ($dt) {
            $user->dt = $dt;
        }

        if ($bank_username) {
            $user->bank_username = $bank_username;
        }
        if ($bank_number) {
            $user->bank_number = $bank_number;
        }
        if ($bank_name) {
            $user->bank_name = $bank_name;
        }
        if ($bank_zhi) {
            $user->bank_zhi = $bank_zhi;
        }


        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success('操作成功');
    }

    public function _check_identity_name($idNo, $name)
    {
        $host = "https://idcard01.market.alicloudapi.com";
        $path = "/s/api/ocr/cloudCode/IdCard";
        $method = "POST";
        $appcode = "6f9c1e2237be437e85ad11ec8878862c";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $bodys = "idCard=" . $idNo . "&realName=" . $name;
        $url = $host . $path;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //CURLOPT_HEADER设置成true表示输出信息头
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($curl);
        $res = json_decode($body, true);
        
        // 转换新接口的返回格式为旧接口的格式，以保持代码兼容性
        $result = [];
        if (isset($res['code']) && $res['code'] == 10000 && isset($res['status']) && $res['status'] == 200) {
            // 新接口返回code=10000, status=200表示验证通过
            $result['respCode'] = '0000'; // 模拟旧接口的成功返回码
        } else {
            // 接口调用失败或返回错误
            $result['respCode'] = '9999';
            $result['respMessage'] = isset($res['message']) ? $res['message'] : '接口调用失败';
        }
        
        return $result;
    }
    public function _newcheck_identity_name($idNo, $name,$mobile)
    {
        $host = "https://zpc.market.alicloudapi.com";
        $path = "/efficient/cellphone/post";
        $method = "POST";
        $appcode = "641561bbb1b44882aecbaea3344aac6e";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $bodys = "mobile=" . $mobile . "&idCard=" . $idNo . "&realName=" . urlencode($name);
        $url = $host . $path;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //CURLOPT_HEADER设置成true表示输出信息头
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($curl);
        $res = json_decode($body, true);
        
        // 转换新接口的返回格式为旧接口的格式，以保持代码兼容性
        $result = [];
        if (isset($res['error_code']) && $res['error_code'] == 0 && 
            isset($res['result']['VerificationResult']) && $res['result']['VerificationResult'] == "1") {
            // 新接口：error_code=0 且 VerificationResult=1 表示验证通过
            $result['respCode'] = '0000'; // 模拟旧接口的成功返回码
        } else {
            // 接口调用失败或返回错误
            $result['respCode'] = '9999';
            $result['respMessage'] = isset($res['reason']) ? $res['reason'] : '接口调用失败';
        }
        
        return $result;
    }
    // public function _newcheck_identity_name($idNo, $name,$mobile)
    // {
    //     $host = "https://kzmobilev2.market.alicloudapi.com";
    //     $path = "/api/mobile_three/check";
    //     $method = "GET";
    //     $appcode = "6f9c1e2237be437e85ad11ec8878862c";
    //     $headers = array();
    //     array_push($headers, "Authorization:APPCODE " . $appcode);
    //     $querys = "mobile=".$mobile."&name=".urlencode($name)."&idcard=".$idNo;
    //     $bodys = "";
    //     $url = $host . $path . "?" . $querys;
    
    //     $curl = curl_init();
    //     curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //     curl_setopt($curl, CURLOPT_FAILONERROR, false);
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($curl, CURLOPT_HEADER, true);
    //     if (1 == strpos("$".$host, "https://"))
    //     {
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    //         curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //     }
    //     // $body=curl_exec($curl);
    //     $response = curl_exec($curl);
    //     $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    //     $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    //     $header = substr($response, 0, $headerSize);
    //     $body = substr($response, $headerSize);
    //     curl_close($curl);
    //     $res = json_decode($body, true);
    //     // 转换新接口的返回格式为旧接口的格式，以保持代码兼容性
    //     $result = [];
    //     if (isset($res['success']) && $res['success'] && isset($res['data']) && $res['data']['result']==0) {
    //         // 新接口返回code=10000, status=200表示验证通过
    //         $result['respCode'] = '0000'; // 模拟旧接口的成功返回码
    //     } else {
    //         // 接口调用失败或返回错误
    //         $result['respCode'] = '9999';
    //         $result['respMessage'] = '信息不一致';
    //     }
        
    //     return $result;
    // }
    /**
     * 修改邮箱
     *
     * @ApiMethod (POST)
     * @param string $email 邮箱
     * @param string $captcha 验证码
     */
    public
    function changeemail()
    {
        $user = $this->auth->getUser();
        $email = $this->request->post('email');
        $captcha = $this->request->post('captcha');
        if (!$email || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!Validate::is($email, "email")) {
            $this->error(__('Email is incorrect'));
        }
        if (\app\common\model\User::where('email', $email)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Email already exists'));
        }
        $result = Ems::check($email, $captcha, 'changeemail');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }
        $verification = $user->verification;
        $verification->email = 1;
        $user->verification = $verification;
        $user->email = $email;
        $user->save();

        Ems::flush($email, 'changeemail');
        $this->success();
    }

    /**
     * 修改手机号
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $captcha 验证码
     */
    public
    function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = $this->request->post('mobile');
        $captcha = $this->request->post('captcha');
        if (!$mobile || !$captcha) {
            $this->error(__('无效参数'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机不正确'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('手机已经存在'));
        }
        $result = Sms::check($mobile, $captcha, 'changemobile');
        if (!$result) {
            $this->error(__('验证码不正确'));
        }
        $verification = $user->verification;
        $verification->mobile = 1;
        $user->verification = $verification;
        $user->mobile = $mobile;
        $user->save();

        Sms::flush($mobile, 'changemobile');
        $this->success();
    }

    /**
     * 第三方登录
     *
     * @ApiMethod (POST)
     * @param string $platform 平台名称
     * @param string $code Code码
     */
    public
    function third()
    {
        $url = url('user/index');
        $platform = $this->request->post("platform");
        $code = $this->request->post("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo' => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('登录成功'), $data);
            }
        }
        $this->error(__('手术失败'), $url);
    }

    /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $newpassword 新密码
     * @param string $captcha 验证码
     */
    public
    function resetpwd()
    {
//        $type = $this->request->post("type", "mobile");
        $mobile = $this->request->post("mobile");
//        $email = $this->request->post("email");
        $newpassword = $this->request->post("newpassword");
        $oldpassword = $this->request->post("oldpassword");
//        $captcha = $this->request->post("captcha");
//        if (!$newpassword || !$captcha) {
//            $this->error(__('Invalid parameters'));
//        }
        //验证Token
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error(__('密码必须为 6 至 30 个字符'));
        }
//        if ($type == 'mobile') {
//            if (!Validate::regex($mobile, "^1\d{10}$")) {
//                $this->error(__('Mobile is incorrect'));
//            }
        $user = \app\common\model\User::getByMobile($mobile);
//            if (!$user) {
//                $this->error(__('User not found'));
//            }
//            $ret = Sms::check($mobile, $captcha, 'resetpwd');
//            if (!$ret) {
//                $this->error(__('Captcha is incorrect'));
//            }
//            Sms::flush($mobile, 'resetpwd');
//        } else {
//            if (!Validate::is($email, "email")) {
//                $this->error(__('Email is incorrect'));
//            }
//            $user = \app\common\model\User::getByEmail($email);
//            if (!$user) {
//                $this->error(__('User not found'));
//            }
//            $ret = Ems::check($email, $captcha, 'resetpwd');
//            if (!$ret) {
//                $this->error(__('Captcha is incorrect'));
//            }
//            Ems::flush($email, 'resetpwd');
//        }
        if ($user->password != $this->getEncryptPassword($oldpassword, $user->salt)) {
            $this->error('旧密码错误');
        }


        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('重置密码成功'));
        } else {
            $this->error($this->auth->getError());
        }
    }
            /**
     * 重置密码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $newpassword 新密码
     * @param string $captcha 验证码
     */
    public
    function retrievepwd()
    {
        $mobile = $this->request->post("mobile");
        $newpassword = $this->request->post("newpassword");
        //验证Token
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error(__('密码必须为 6 至 30 个字符'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        $nickname = $this->request->post("nickname");
        $sfz = $this->request->post("sfz");
        if ($user->nickname!=$nickname||$user->sfz!=$sfz) {
            $this->error('信息不一致');
        }
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('重置密码成功'));
        } else {
            $this->error($this->auth->getError());
        }
    }
    /**
     * 获取密码加密后的字符串
     * @param string $password 密码
     * @param string $salt 密码盐
     * @return string
     */
    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }
    
    //签到逻辑

    public function sign()
    {
        if (!$this->auth->isLogin()) $this->error(__('請先登入'));
        //redis防重复点击
        $symbol = "sign" . date('Ymd').$this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        $curr = date('Y-m-d', time());
        $ww['uid'] = $this->auth->id;
        $repeat = Db::name('leesign')->where("DATE_FORMAT(sign_time,'%Y-%m-%d') = '$curr'")->where($ww)->find();
        if ($repeat)
        {
            $this->error(__('已签到'));
        }
        
        //签到配置
        $config = get_addon_config('leesign');

        //当月的第一天
        $firstDate = date("Y-m-d H:i:s", Date::unixtime("month", 0, 'begin'));
        //当月的最后一天
        $lastDate = date('Y-m-d H:i:s', Date::unixtime("month", 0, 'end'));

        $w['sign_time'] = ['between', [$firstDate, $lastDate]];
        $w['uid'] = $this->auth->id;

        $signList =  Db::name('leesign')->where($w)->order('sign_time desc')->select();
        $len = count($signList, 0);
        if ($signList && $len >= 1)
        {
            $lianxu = (date("Y-m-d", strtotime($signList[0]['sign_time'] . "+ 1 day")) != date("Y-m-d", time())) ? false : true;
        }
        else
        {
            $lianxu = false;
        }

        //处理逻辑：如果上次签到的日期和这次签到的日期相差不是1天，那么本次签到就不是连续签到。
        $max_sign_num = $lianxu ? $signList[0]['max_sign'] + 1 : 1;

        $score = $config['signnum'];

        //连续签到奖励规则 - 周期奖励
        $zhouqi = $config['types'];

        //当月连续签到所获得的所有额外奖励
        $extra = 0;

        //当天是否触发连续签到的额外奖励
        $extra_reward = 0;

        //开启了连续签到奖励
        if ($config['signstatus'] == 1)
        {
            //计算连续签到带来的额外奖励
            foreach ($zhouqi as $k => $v)
            {
                foreach ($signList as $key => $val)
                {
                    if ($k == $val['max_sign'])
                    {
                        $extra += $v;
                        break;
                    }
                }

                if ($k == $max_sign_num)
                {
                    $extra_reward += $v;
                }
            }
        }

        $checkin = Db::name('leesign')
            ->where('uid', $this->auth->id)
            ->where(
                'sign_time',
                'between',
                [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')]
            )
            ->select();

        if (!empty($checkin)) {
            $this->error(__('今天已经签到了'));
        }
        if (count($checkin) >= 1) {
            $this->error(__('今天已经签到了'));
        }
        
        if (empty($checkin)) {
            //签到积分增加日志
            // $user_log = Db::name('user_money_log')
            //     ->where(['user_id' =>  $this->auth->id, 'type' => 'qiandao'])
            //     ->where(
            //         'createtime',
            //         'between',
            //         [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]
            //     )->find();

            // if (!empty($user_log)) {
            //     $this->error(__('今天已经签到了'));
            // }
            //新的资金记录表
            Db::startTrans();
            try {
                $user_id = $this->auth->id;
                $money = $score;
                $memo = '连续签到奖励';
                $money_type = 'qiandao';
                $dataUser = \app\api\model\UserMoney::where('user_id',$user_id)->find();
                if ($dataUser && $money != 0) {
                    $data = [
                        // 'sign_ip'           => $this->request->ip(),
                        'sign_ip'           => $this->auth->loginip,
                        'uid'               => $this->auth->id,
                        'sign_time'         => date('Y-m-d H:i:s'),
                        'sign_reward'       => $score,
                        'sign_extra_reward' => $extra_reward,
                        'max_sign'          => $max_sign_num
                    ];
                    \app\api\model\Leesign::insert($data);

                    $dataIns  = [
                        'user_id' => $user_id,
                        'money' => $money,
                        'type' => $money_type,
                        'before' => $dataUser['money_'.$money_type],
                        'after' => $dataUser['money_'.$money_type] + $money,
                        'memo' => $memo,
                        'createtime' => time()
                    ];
                    MoneyLog::create($dataIns);
                    UserMoneyInLog::create($dataIns);
                    \app\api\model\User::where('id', $user_id)->setInc('money', $money);
                    UserMoney::where('user_id', $user_id)->setInc('money', $money);
                    UserMoney::where('user_id', $user_id)->setInc('money_'.$money_type, $money);
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('签到失败');
            }
            $this->success('签到成功', $max_sign_num, ['max_sign' => $max_sign_num, 'month_sign_num' => $len, 'reward' => ($score + $extra_reward)]);
        }
    }
    
    public function save_paypwd()
    {
        $user_id = $this->auth->id;
        $pay_pwd = $this->request->post('pay_pwd');
        if (!$pay_pwd) {
            $this->error('请输入交易密码');
        }
        $pay_pwd = strtoupper(md5(strtoupper(md5($pay_pwd . 'skund'))));
        $user = db('user')->where('id', $user_id)->update(['pay_pwd' => $pay_pwd]);
        if ($user) {
            $this->success('设置成功');
        } else {
            $this->error('设置失败');
        }
    }

    public function change_paypwd()
    {
        $user_id = $this->auth->id;
        $old_pay_pwd = $this->request->post('old_pay_pwd');
        $pay_pwd = $this->request->post('pay_pwd');

        if (!$old_pay_pwd) {
            $this->error('请输入旧支付密码');
        }
        if (strtoupper(md5(strtoupper(md5($old_pay_pwd . 'skund')))) != $this->auth->pay_pwd) {
            $this->error('旧交易密码错误');
        }
        if (!$pay_pwd) {
            $this->error('请输入交易密码');
        }

        $pay_pwd = strtoupper(md5(strtoupper(md5($pay_pwd . 'skund'))));
        $user = db('user')->where('id', $user_id)->update(['pay_pwd' => $pay_pwd]);
        if ($user) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }

    /**
     * 检测是否设置了交易密码
     */
    public function check_paypwd()
    {
        $user_id = $this->auth->id;
        if (empty($this->auth->pay_pwd)) {
            // 状态码0表示未设置交易密码
            $this->success("未设置交易密码", ['has_set' => 0]);
        } else {
            // 状态码1表示已设置交易密码
            $this->success("已设置交易密码", ['has_set' => 1]);
        }
    }

    public function getnickname()
    {
        $user_id = $this->auth->id;
        $id = $this->request->post('id');
//redis防重复点击
        $symbol = "getnickname" . $user_id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        if (!$id) {
            $this->error('请输入用户ID');
        }
        $nickname = db('user')->where('id', $id)->value('nickname');
        if (!$nickname) {
            // code...
            $this->error('暂未找到此用户');
        }
        $this->success('success',$nickname);
    }
}
