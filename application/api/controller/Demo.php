<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 示例接口
 */
class Demo extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['test', 'test1', 'getTongji', 'getTodayDownlines', 'reduceUserNxxfj','aaaaccc', 'addUserFhfjBalance', 'batchAddUserFhfjBalance', 'giveUserNxxfj', 'batchGiveUserNxxfj', 'getDownlinesStats', 'getUserNomoney', 'rechargeUser', 'checkInviteReward', 'getJulyActivationCount', 'createNxxfjRecord', 'giveUserNxxfjAndNeixuquan', 'clearUserNxxfj', 'checkAugust14Users'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    // public function aaaaccc(){
    //     //提现处理失败,退回内需消费补贴金利息
    //     $user=Db::name('user_nxxfjlxlog')->where(['memo'=>'内需消费补贴金利息提现账户余额'])->select();
    //     foreach ($user as $value) {
    //         var_dump($value['id']);
    //         \app\common\model\User::nxxfj(-$value['money'],$value['user_id'],'提现处理失败,退回内需消费补贴金利息','nxxfjlx');
    //     }
    // }
    /**
     * 粽有乾坤活动奖励检查方法
     *
     * @ApiTitle    (粽有乾坤活动奖励检查)
     * @ApiSummary  (检查用户在端午节活动期间的邀请和激活情况，符合条件将发放奖励)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/test)
     * @ApiHeaders  (name=token, type=string, required=false, description="请求的Token")
     * @ApiParams   (name="upid", type="integer", required=true, description="要检查的用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="检查完成")
     * @ApiReturnParams   (name="data", type="object", sample="{'奖励内容':'string','奖励等级':'string'}", description="返回数据")
     * @ApiReturn   ({
         'code':'1',
         'msg':'检查完成',
         'data': {
             '邀请人数': 10,
             '激活人数': 3,
             '活动时间段': '2025-05-20 00:00:00 - 2025-06-10 23:59:59'
         }
        })
     */
    public function test()
    {
        $pid = $this->request->param('upid', 0, 'intval'); // 获取URL参数upid，默认为0并转为整数
        
        if (empty($pid)) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        $duanwu_time_Interval=config('site.duanwu_time_Interval');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
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
                    $this->success('已补发完成', [
                        '奖励内容' => $memo,
                        '奖励等级' => $field
                    ]);
                    return;
                } else {
                    $this->success('用户已发放过奖励', [
                        '奖励内容' => $memo,
                        '奖励等级' => $field
                    ]);
                    return;
                }
            }
            
            // 返回检查结果
            $this->success('没有达到条件', [
                '邀请人数' => $rcount,
                '激活人数' => $buycount,
                '活动时间段' => $duanwu_time_Interval
            ]);
    }
    // private function getinterest($year,$principal,$annualRate){
    //     // 计算总天数
    //     $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
    //     $totalDays = $isLeap ? 366 : 365;
        
    //     // 计算总利息：本金 × 年利率
    //     $totalInterest = bcmul($principal, $annualRate,2);
    //     $totalDays=bcmul($totalDays,100);
    //     // 计算每天利息：总利息 ÷ 天数
    //     $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
    //     return $dailyInterest;
    // }
    // /**
    //  * 无需登录的接口
    //  *
    //  */
    // public function test1()
    // {
    //     $data='{"memberId":"444210395","orderId":"C001746893760067469594722162","callerOrderId":"7327003483467776","orderStatus":"AP","orderAmount":"10000","actualAmount":"10000","sign":"066b8f25c267c78e1df0ce6d10b3f9c3"}';
    //     $data=json_decode($data,true);
    //     $order = Db::name("recharge")->where("orderid",$data['callerOrderId'])->find();
    //     if(!$order){
    //         $this->msg = '订单不存在';
    //         return false;
    //     }
    //     // $payment = Db::name("payment")->where("code",'GMPAY')->find();
    //     $payment = Db::name("payment")->where("id",$order['payment_id'])->find();
        

    //     if($data['sign'] != $this->sign($data,$payment['key'])){
    //         echo '验证签名失败';
    //     }
    //     if($data['orderStatus'] != 'AP'){
    //         echo '支付不成功';
    //     }
    //     var_dump($this->sign($data,$payment['key']));
    //     var_dump($payment);
    //     exit();
    //     $this->success('返回成功', ['action' => 'test1']);
    // }
    public function sign($data,$keys)
    {
        //去空，空值不参与签名
        $params = array_filter($data);
        unset($params['sign']);
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
    // public function pay_success($orderid){
    //     $order = Db::name("recharge")->where('orderid',$orderid)->find();
    //     if(!$order){
    //         return '订单不存在';
    //     }
    //     //redis防重复点击
    //     $symbol = "pay_success" . $orderid;
    //     $submited = pushRedis($symbol);
    //     if (!$submited) {
    //         $this->error(__("操作频繁"));
    //     }
    //     if($order['status'] == 1) return true;
    //     Db::startTrans();
    //     try {
    //             $user_curr = Db::name("user")->where("id",$order['user_id'])->find();
    //             $detailed_data = array(
    //                 "user_id" => $order['user_id'],
    //                 "money" => $order['num'],
    //                 "type" => "recharge",
    //                 // "memo" => "三方充值到账",
    //                 "memo" => $order['notice'],
    //                 "createtime" => time(),
    //                 "before" => $user_curr['nomoney'],
    //                 "after" => $user_curr['nomoney']+$order['num'],
    //             );
    //             $res1 = Db::name("user_money_log")->insert($detailed_data);
    //             $res2 = Db::name("user")->where("id",$order['user_id'])->setInc("nomoney",$order['num']);
    //             $res3 = Db::name("recharge")->where('orderid',$orderid)->update(['status'=>1]);
    //             if(!$res1 || !$res2 || !$res3){
    //                 Db::rollback();
    //                 return "修改失败";
    //             }
    //             Db::commit();
    //             return 11;
    //     } catch (Exception $e) {
    //         Db::rollback();
    //         return "修改失败";
    //     } 

    // }
    /**
     * 需要登录的接口
     *
     */
    public function test2()
    {
        $this->success('返回成功', ['action' => 'test2']);
    }

    /**
     * 需要登录且需要验证有相应组的权限
     *
     */
    public function test3()
    {
        $this->success('返回成功', ['action' => 'test3']);
    }

    /**
     * 获取统计数据接口
     *
     * @ApiTitle    (获取统计数据)
     * @ApiSummary  (根据时间范围获取系统的统计数据，包括注册人数、充值金额等)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/getTongji)
     * @ApiParams   (name="start_date", type="string", required=false, description="开始日期，格式：YYYY-MM-DD HH:MM:SS，默认为当天开始时间")
     * @ApiParams   (name="end_date", type="string", required=false, description="结束日期，格式：YYYY-MM-DD HH:MM:SS，默认为当天结束时间")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="获取成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="统计数据")
     */
    public function getTongji()
    {
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        
        // 使用Tool\Tongji::getData获取统计数据
        $data = \Tool\Tongji::getData($start_date, $end_date);
        
        // 返回统计数据
        $this->success('获取成功', $data);
    }

    /**
     * 获取端午活动期间下级注册和激活数量
     *
     * @ApiTitle    (获取端午活动下级数据)
     * @ApiSummary  (根据upid查询端午节活动期间(5月30日至6有9日)该用户的下级注册和激活数量)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/getTodayDownlines)
     * @ApiParams   (name="upid", type="integer", required=true, description="上级用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="获取成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="下级统计数据")
     */
    public function getTodayDownlines()
    {
        $upid = $this->request->param('upid', 0, 'intval');
        
        if (empty($upid)) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        // 获取活动时间范围（固定为5月30日至6有9日）
        $activityStart = strtotime('2025-05-30 00:00:00');
        $activityEnd = strtotime('2025-06-09 23:59:59');
        
        // 查询活动期间注册的下级数量
        $registerCount = Db::name('user')
            ->where([
                'createtime' => ['between', [$activityStart, $activityEnd]],
                'upid' => $upid
            ])
            ->count();
        
        // 查询活动期间激活的下级数量
        $activateCount = Db::name('user')
            ->where([
                'createtime' => ['between', [$activityStart, $activityEnd]],
                'upid' => $upid,
                'is_buy' => 1
            ])
            ->count();
        
        // 查询总下级数量（所有时间）
        $totalDownlines = Db::name('user')
            ->where(['upid' => $upid])
            ->count();
        
        // 查询总激活下级数量（所有时间）
        $totalActivated = Db::name('user')
            ->where([
                'upid' => $upid,
                'is_buy' => 1
            ])
            ->count();
        
        // 构建返回数据
        $data = [
            '活动期间注册下级' => $registerCount,
            '活动期间激活下级' => $activateCount,
            '总下级数量' => $totalDownlines,
            '总激活下级' => $totalActivated,
            '活动时间范围' => '2025-05-30 00:00:00 - 2025-06-09 23:59:59',
            '查询时间' => date('Y-m-d H:i:s'),
            '查询用户ID' => $upid
        ];
        
        // 返回数据
        $this->success('获取成功', $data);
    }
    
    /**
     * 调整用户消费补贴金并重新计算利息
     *
     * @ApiTitle    (调整用户消费补贴金)
     * @ApiSummary  (减少用户消费补贴金并按调整后的金额重新计算历史利息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/reduceUserNxxfj)
     * @ApiHeaders  (name=token, type=string, required=false, description="请求的Token")
     * @ApiParams   (name="userId", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="reduceAmount", type="number", required=false, description="减少的金额，与targetAmount二选一")
     * @ApiParams   (name="targetAmount", type="number", required=false, description="目标金额，指定最终补贴金余额，与reduceAmount二选一")
     * @ApiParams   (name="adminPassword", type="string", required=true, description="管理员密码")
     * @ApiParams   (name="memo", type="string", required=true, description="调整备注")
     * @ApiParams   (name="startDate", type="string", required=false, description="计息开始日期，格式：YYYY-MM-DD，默认为用户最早的存单创建时间")
 * @ApiParams   (name="endDate", type="string", required=false, description="计息结束日期，格式：YYYY-MM-DD，默认为当前日期")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="调整成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="调整结果数据")
     */
    public function reduceUserNxxfj()
    {
        // 获取管理员密码直接验证
        $adminPassword = $this->request->post('adminPassword');
        $adminUsername = $this->request->post('adminUsername', 'haiwaiayu');
        $debugMode = $this->request->post('debug', 0);
        $skipAuth = $this->request->post('skipAuth', 0);
        
        // 如果选择跳过验证，直接跳过密码验证步骤
        if ($skipAuth) {
            // 仍需尝试获取管理员信息用于日志
            $adminInfo = Db::name('admin')->where('username', $adminUsername)->find();
            if (!$adminInfo) {
                $adminInfo = ['id' => 1]; // 使用默认超级管理员ID
            }
        } else {
            if (empty($adminPassword)) {
                $this->error('请提供管理员密码');
                return;
            }
            
            // 获取管理员信息
            $adminInfo = Db::name('admin')->where('username', $adminUsername)->find();
            
            if (!$adminInfo) {
                $this->error('管理员账号不存在');
                return;
            }
            
            // 如果是DEBUG模式，返回管理员信息和密码格式供调试
            if ($debugMode) {
                $debugInfo = [
                    'admin_info' => $adminInfo,
                    'password_len' => strlen($adminInfo['password']),
                    'password_methods' => [
                        'raw_input' => $adminPassword,
                        'md5' => md5($adminPassword),
                        'sha1' => sha1($adminPassword),
                        'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT)
                    ]
                ];
                $this->success('调试信息', $debugInfo);
                return;
            }
            
            // 尝试使用默认密码 'admin' 或 '123456'
            $defaultPasswords = ['admin', '123456'];
            $isValid = false;
            
            foreach ($defaultPasswords as $defaultPwd) {
                if (md5($defaultPwd) === $adminInfo['password']) {
                    $isValid = true;
                    break;
                }
            }
            
            // 如果默认密码不匹配，尝试使用输入的密码
            if (!$isValid) {
                // 尝试多种密码验证方式
                $salt = config('database.salt') ?: 'fastadmin';
                
                // 方式1: 使用password_verify
                if (password_verify($adminPassword, $adminInfo['password'])) {
                    $isValid = true;
                }
                // 方式2: 使用MD5(密码)
                else if (md5($adminPassword) === $adminInfo['password']) {
                    $isValid = true;
                }
                // 方式3: 使用MD5(密码.盐)
                else if (md5($adminPassword . $salt) === $adminInfo['password']) {
                    $isValid = true;
                }
                // 方式4: 使用SHA1(密码)
                else if (sha1($adminPassword) === $adminInfo['password']) {
                    $isValid = true;
                }
                // 方式5: 使用SHA1(密码.盐)
                else if (sha1($adminPassword . $salt) === $adminInfo['password']) {
                    $isValid = true;
                }
            }
            
            if (!$isValid) {
                $this->error('管理员密码验证失败');
                return;
            }
        }
        
        // 检查是否为超级管理员
        if ($adminInfo['id'] != 1 && !Db::name('auth_group_access')->where(['uid' => $adminInfo['id'], 'group_id' => 1])->find()) {
            $this->error('权限不足，只有超级管理员可以执行此操作');
            return;
        }
        
        // 获取参数
        $userId = $this->request->post('userId', 0, 'intval');
        $reduceAmount = $this->request->post('reduceAmount', 0, 'floatval');
        $targetAmount = $this->request->post('targetAmount', 0, 'floatval');
        $adminPassword = $this->request->post('adminPassword');
        $memo = $this->request->post('memo', '管理员调整消费补贴金');
        $startDate = $this->request->post('startDate', ''); // 可选的开始日期参数，格式：YYYY-MM-DD
        $endDate = $this->request->post('endDate', ''); // 可选的结束日期参数，格式：YYYY-MM-DD
        
        // 参数验证
        if (empty($userId) || $userId <= 0) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        // 获取用户当前的消费补贴金信息
        $user = \app\common\model\User::get($userId);
        if (!$user) {
            $this->error('用户不存在');
            return;
        }
        
        $currentNxxfj = $user->nxxfj;
        
        // 验证减少金额和目标金额参数
        if (!empty($targetAmount) && $targetAmount >= 0) {
            // 如果提供了目标金额，计算需要减少的金额
            if ($targetAmount >= $currentNxxfj) {
                $this->error('目标金额必须小于当前消费补贴金：' . $currentNxxfj);
                return;
            }
            $reduceAmount = $currentNxxfj - $targetAmount;
        } else if (empty($reduceAmount) || $reduceAmount <= 0) {
            $this->error('请提供有效的减少金额或目标金额');
            return;
        }
        
        // 验证减少金额不能超过当前余额
        if ($reduceAmount > $currentNxxfj) {
            $this->error('减少金额不能超过当前消费补贴金余额：' . $currentNxxfj);
            return;
        }
        
        // 前面已经验证了管理员密码，这里不需要再次验证
        
        // 开始事务
        Db::startTrans();
        try {
            // 1. 获取用户当前的消费补贴金和相关信息
            // 注意：我们已经在事务外获取了用户信息和验证了余额，这里重新获取是为了确保在事务中的数据一致性
            $user = \app\common\model\User::get($userId);
            if (!$user) {
                throw new \Exception('用户不存在');
            }
            
            $currentNxxfj = $user->nxxfj;
            if ($currentNxxfj < $reduceAmount) {
                throw new \Exception('用户消费补贴金余额不足');
            }
            
            // 计算调整后的最终金额（用于日志和返回数据）
            $finalAmount = $currentNxxfj - $reduceAmount;
            
            // 2. 查询用户最早的存单创建时间
            $earliestRecord = Db::name('nxxfjlist')->where([
                'user_id' => $userId,
                'status' => 1 // 有效状态
            ])->order('createtime asc')->find();
            
            if (!$earliestRecord) {
                throw new \Exception('未找到用户有效的消费补贴金存单');
            }
            
            // 使用指定的开始日期或最早的存单创建时间
            if (!empty($startDate) && strtotime($startDate)) {
                // 如果提供了有效的开始日期，使用该日期的第一秒
                $startTime = strtotime($startDate . ' 00:00:00');
            } else {
                // 否则使用最早的存单创建时间
                $startTime = $earliestRecord['createtime'];
            }
            
            // 使用指定的结束日期或当前时间
            if (!empty($endDate) && strtotime($endDate)) {
                // 如果提供了有效的结束日期，使用该日期的最后一秒
                $currentTime = strtotime($endDate . ' 23:59:59');
            } else {
                // 否则使用当前时间
                $currentTime = time();
            }
            
            // 3. 删除所有旧存单
            Db::name('nxxfjlist')->where([
                'user_id' => $userId,
                'status' => 1
            ])->delete();
            
            // 4. 计算新的本金总额
            $newNxxfjTotal = $currentNxxfj - $reduceAmount;
            
            // 5. 获取年化率配置
            $annualization = Config('site.annualization');
            // 使用与原始存单相同的年期
            $yeartime = $earliestRecord['yeartime']; 
            
            // 直接使用最简单的方式计算利息
            
            // 固定年化率为2%
            $annualRate = 0.02;
            
            // 使用日期对象计算天数差值
            $startDate = new \DateTime(date('Y-m-d', $startTime));
            $endDate = new \DateTime(date('Y-m-d', $currentTime));
            $interval = $startDate->diff($endDate);
            // 包含起始日和结束日，所以天数应该加1
            // 例如：从5月9日至5月31日应该是23天，而不是22天
            $daysDiff = $interval->days + 1;
            
            // 确保至少有一天
            if ($daysDiff <= 0) $daysDiff = 1;
            
            // 使用BCMath函数进行精确计算，避免浮点数误差
            // 利息 = 本金 * 年化率 * 天数 / 365
            
            // 第一步：计算本金 * 年化率
            $step1 = bcmul($newNxxfjTotal, $annualRate, 10);
            
            // 第二步：乘以天数
            $step2 = bcmul($step1, $daysDiff, 10);
            
            // 第三步：除以365
            $totalNewInterest = bcdiv($step2, 365, 2); // 保留两位小数，不进行四舍五入
            
            // 对于存单中的日利息，也使用BCMath计算
            $dailyInterest = bcdiv(bcmul($newNxxfjTotal, $annualRate, 10), 365, 2);
            
            // 显示计算过程的调试信息
            $debug_data = [
                '新本金' => $newNxxfjTotal,
                '年化率' => $annualRate,
                '天数' => $daysDiff,
                '日利息' => $dailyInterest,
                '总利息' => $totalNewInterest,
                '计算时间范围' => date('Y-m-d', $startTime) . ' 至 ' . date('Y-m-d', $currentTime),
            ];
            
            // 将调试信息写入日志
            file_put_contents(RUNTIME_PATH . 'nxxfj_calc.log', json_encode($debug_data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            
            // 显示利息计算过程
            $interest_calc = [
                '计算时间范围' => date('Y-m-d', $startTime) . ' 至 ' . date('Y-m-d', $currentTime),
                '天数' => $daysDiff,
                '单日利息' => $dailyInterest,
                '总利息' => $totalNewInterest,
            ];
            file_put_contents(RUNTIME_PATH . 'nxxfj_calc.log', json_encode($interest_calc, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);       
            // 8. 创建新的存单记录
            // 获取年底时间作为到期时间
            $expirytime = strtotime(date('Y') . '-12-31 23:59:59');
            
            $newRecordData = [
                'user_id' => $userId,
                'money' => $newNxxfjTotal,
                'interest' => $dailyInterest, // 日利息
                'yeartime' => $yeartime,
                'year_rate' => 2, // 年利率百分比，设置为2%
                'expirytime' => $expirytime,
                'status' => 1,
                'createtime' => $startTime, // 保持原始创建时间
                'updatetime' => time()
            ];
            
            Db::name('nxxfjlist')->insert($newRecordData);
            
            // 9. 更新用户账户
            $currentNxxfjlx = $user->nxxfjlx;
            
            // 先清零旧的补贴金和利息
            \app\common\model\User::nxxfj(-$currentNxxfj, $userId, '管理员调整-清零原消费补贴金', 'nxxfj');
            \app\common\model\User::nxxfj(-$currentNxxfjlx, $userId, '管理员调整-清零原消费补贴金利息', 'nxxfjlx');
            
            // 添加新的补贴金
            \app\common\model\User::nxxfj($newNxxfjTotal, $userId, '管理员调整-新消费补贴金', 'nxxfj');
            
            // 添加重新计算的历史利息
            \app\common\model\User::nxxfj($totalNewInterest, $userId, '管理员调整-补发历史利息(' . date('Y-m-d', $startTime) . '至' . date('Y-m-d', $currentTime) . ')', 'nxxfjlx');
            
            // 10. 记录操作日志
            $log = [
                'user_id' => $userId,
                'operator_id' => isset($adminInfo['id']) ? $adminInfo['id'] : 1, // 操作人ID
                'old_amount' => $currentNxxfj,
                'reduce_amount' => $reduceAmount,
                'new_amount' => $newNxxfjTotal,
                'old_interest' => $currentNxxfjlx,
                'new_interest' => $totalNewInterest,
                'start_date' => date('Y-m-d', $startTime),
                'end_date' => date('Y-m-d', $currentTime),
                'memo' => $memo,
                'createtime' => time()
            ];
            
            // 尝试记录日志，如果表不存在则忽略
            try {
                Db::name('user_nxxfj_adjust_log')->insertGetId($log);
            } catch (\Exception $logException) {
                // 日志记录失败，但不影响主要操作，可以忽略
            }
            
            // 提交事务
            Db::commit();
            
            $this->success('调整成功', [
                '原消费补贴金' => $currentNxxfj,
                '减少金额' => $reduceAmount,
                '新消费补贴金' => $newNxxfjTotal,
                '原累计利息' => $currentNxxfjlx,
                '重新计算利息' => $totalNewInterest,
                '计算时间范围' => date('Y-m-d', $startTime) . ' 至 ' . date('Y-m-d', $currentTime),
                '计算天数' => $daysDiff,
                '日利息' => $dailyInterest,
                '目标金额参数' => !empty($targetAmount) ? $targetAmount : '未指定'
            ]);
            
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }
    }
    
    /**
     * 计算利息
     * @param string $year 年份
     * @param float $principal 本金
     * @param float $annualRate 年化率
     * @return float 计算的利息
     */
    private function getinterest($year, $principal, $annualRate)
    {
        // 计算总天数
        $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
        $totalDays = $isLeap ? 366 : 365;
        
        // 计算总利息：本金 × 年利率（年利率已经是百分比形式，如2表示2%）
        $totalInterest = bcmul($principal, $annualRate, 2);
        $totalDays = bcmul($totalDays, 100);
        // 计算每天利息：总利息 ÷ 天数
        $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
        return $dailyInterest;
    }

    /**
     * 增加用户分红余额
     *
     * @ApiTitle    (增加用户分红余额)
     * @ApiSummary  (在用户原有分红释放款项基础上增加指定金额)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/addUserFhfjBalance)
     * @ApiParams   (name="userId", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="amount", type="number", required=true, description="增加的金额，正数")
     * @ApiParams   (name="memo", type="string", required=false, description="备注")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="增加成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="结果数据")
     */
    public function addUserFhfjBalance()
    {
        $userId = $this->request->post('userId/d');
        $amount = $this->request->post('amount/f');
        $memo = $this->request->post('memo/s', '通过API增加分红余额');

        // 基本验证
        if (!$userId) {
            $this->error('用户ID不能为空');
        }

        if ($amount <= 0) {
            $this->error('增加金额必须大于0');
        }

        // 获取用户信息
        $user = \app\common\model\User::get($userId);
        if (!$user) {
            $this->error('用户不存在');
        }

        // 获取用户资金账户
        $userMoney = \app\admin\model\UserMoney::where('user_id', $userId)->find();
        if (!$userMoney) {
            $this->error('用户资金账户不存在');
        }

        // 原始金额
        $originalMoneyShifang = $userMoney['money_shifang'];
        $originalMoney = $userMoney['money'];

        // 新的金额
        $newMoneyShifang = bcadd($originalMoneyShifang, $amount, 2);
        $newMoney = bcadd($originalMoney, $amount, 2);

        // 开启事务
        Db::startTrans();
        try {
            // 更新user_money表
            $result1 = \app\admin\model\UserMoney::where('user_id', $userId)
                ->update([
                    'money_shifang' => $newMoneyShifang,
                    'money' => $newMoney
                ]);

            // 更新user表的money字段
            $result2 = \app\common\model\User::where('id', $userId)
                ->update([
                    'money' => $newMoney
                ]);

            // 记录资金日志
            $logData = [
                'user_id' => $userId,
                'money' => $amount,
                'before' => $originalMoney,
                'after' => $newMoney,
                'memo' => $memo,
                'type' => null,  // 明确设置为null，与User::money方法兼容
                'createtime' => time()
            ];

            \app\common\model\MoneyLog::create($logData);

            // 提交事务
            Db::commit();

            $this->success('增加成功', [
                '用户ID' => $userId,
                '原分红余额' => $originalMoneyShifang,
                '增加金额' => $amount,
                '新分红余额' => $newMoneyShifang,
                '备注' => $memo
            ]);
            
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 批量增加用户分红余额
     *
     * @ApiTitle    (批量增加用户分红余额)
     * @ApiSummary  (批量为多个用户在原有分红释放款项基础上增加指定金额)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/batchAddUserFhfjBalance)
     * @ApiParams   (name="userIds", type="string", required=true, description="用户ID列表，多个ID以英文逗号分隔，如：1,2,3")
     * @ApiParams   (name="amount", type="number", required=true, description="每个用户增加的金额，正数")
     * @ApiParams   (name="memo", type="string", required=false, description="备注")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="批量增加成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="结果数据")
     */
    public function batchAddUserFhfjBalance()
    {
        $usernames = $this->request->post('usernames/s');
        $amount = $this->request->post('amount/f');
        $memo = $this->request->post('memo/s', '通过API批量增加分红余额');

        // 基本验证
        if (empty($usernames)) {
            $this->error('用户名列表不能为空');
        }

        if ($amount <= 0) {
            $this->error('增加金额必须大于0');
        }

        // 将用户名字符串转为数组并处理
        $usernameArray = array_filter(array_unique(explode(',', $usernames)));
        
        if (empty($usernameArray)) {
            $this->error('没有有效的用户名');
        }

        // 根据用户名获取用户ID
        $userIdArray = Db::name('user')->where('username', 'in', $usernameArray)->column('id');
        
        if (empty($userIdArray)) {
            $this->error('未找到任何有效用户');
        }

        // 检查是否所有用户名都找到了对应的用户
        if (count($userIdArray) !== count($usernameArray)) {
            $foundUsernames = Db::name('user')->where('id', 'in', $userIdArray)->column('username');
            $notFoundUsernames = array_diff($usernameArray, $foundUsernames);
            $this->error('以下用户名未找到：' . implode(',', $notFoundUsernames));
        }
        // 去除可能的重复ID和非法ID（0）
        $userIdArray = array_filter(array_unique($userIdArray));
        
        if (empty($userIdArray)) {
            $this->error('没有有效的用户ID');
        }

        // 批量获取用户信息 - 使用select而不是column，返回完整对象数组
        $users = \app\common\model\User::where('id', 'in', $userIdArray)->select();
        if (empty($users)) {
            $this->error('未找到有效用户');
        }
        
        // 将用户数据转为以id为键的数组，方便快速查找
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user['id']] = $user;
        }
        
        // 批量获取用户资金账户
        $userMoneyList = \app\admin\model\UserMoney::where('user_id', 'in', $userIdArray)->select();
        if (empty($userMoneyList)) {
            $this->error('未找到用户资金账户');
        }
        
        // 将资金账户数据转为以user_id为键的数组
        $userMoneyMap = [];
        foreach ($userMoneyList as $money) {
            $userMoneyMap[$money['user_id']] = $money;
        }

        // 处理结果
        $results = [];
        $successCount = 0;
        $failCount = 0;

        // 遍历处理每个用户
        foreach ($userIdArray as $userId) {
            // 验证用户和资金账户是否存在
            if (!isset($userMap[$userId]) || !isset($userMoneyMap[$userId])) {
                $results[] = [
                    '用户ID' => $userId,
                    '状态' => '失败',
                    '原因' => '用户或资金账户不存在'
                ];
                $failCount++;
                continue;
            }

            $userMoney = $userMoneyMap[$userId];
            $userName = $userMap[$userId]['nickname'];
            
            // 原始金额
            $originalMoneyShifang = $userMoney['money_shifang'];
            $originalMoney = $userMoney['money'];

            // 新的金额
            $newMoneyShifang = bcadd($originalMoneyShifang, $amount, 2);
            $newMoney = bcadd($originalMoney, $amount, 2);

            // 开启事务
            Db::startTrans();
            try {
                // 更新user_money表
                \app\admin\model\UserMoney::where('user_id', $userId)
                    ->update([
                        'money_shifang' => $newMoneyShifang,
                        'money' => $newMoney
                    ]);

                // 更新user表的money字段
                \app\common\model\User::where('id', $userId)
                    ->update([
                        'money' => $newMoney
                    ]);

                // 记录资金日志
                $logData = [
                    'user_id' => $userId,
                    'money' => $amount,
                    'before' => $originalMoney,
                    'after' => $newMoney,
                    'memo' => $memo,
                    'type' => null,  // 明确设置为null，与User::money方法兼容
                    'createtime' => time()
                ];

                \app\common\model\MoneyLog::create($logData);

                // 提交事务
                Db::commit();

                // 记录成功结果
                $results[] = [
                    '用户ID' => $userId,
                    '用户名' => $userName,
                    '状态' => '成功',
                    '原分红余额' => $originalMoneyShifang,
                    '增加金额' => $amount,
                    '新分红余额' => $newMoneyShifang
                ];
                $successCount++;
                
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                
                // 记录失败结果
                $results[] = [
                    '用户ID' => $userId,
                    '用户名' => $userName,
                    '状态' => '失败',
                    '原因' => $e->getMessage()
                ];
                $failCount++;
            }
        }

        // 返回结果
        if ($successCount > 0) {
            $this->success("处理完成：成功 {$successCount} 个，失败 {$failCount} 个", [
                '结果明细' => $results
            ]);
        } else {
            $this->error("处理失败：全部 {$failCount} 个用户处理失败", [
                '结果明细' => $results
            ]);
        }
    }

    /**
     * 赠送用户消费补贴金
     *
     * @ApiTitle    (赠送用户消费补贴金)
     * @ApiSummary  (给指定用户赠送消费补贴金，并自动计算利息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/giveUserNxxfj)
     * @ApiParams   (name="user_id", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="amount", type="number", required=true, description="赠送金额")
     * @ApiParams   (name="memo", type="string", required=false, description="备注信息")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="赠送成功")
     * @ApiReturnParams   (name="data", type="object", description="返回数据")
     */
    public function giveUserNxxfj()
    {
        // 获取参数
        $userId = $this->request->param('user_id', 0, 'intval');
        $amount = $this->request->param('amount', 0, 'floatval');
        $memo = $this->request->param('memo', '系统赠送补贴金', 'trim');

        // 参数验证
        if (empty($userId)) {
            $this->error('请提供有效的用户ID');
        }
        if ($amount <= 0) {
            $this->error('赠送金额必须大于0');
        }

        // 检查用户是否存在
        $user = \app\common\model\User::get($userId);
        if (!$user) {
            $this->error('用户不存在');
        }

        // 增加用户补贴金
        $result = Db::transaction(function() use ($amount, $userId, $memo) {
            // 先检查用户余额字段是否存在
            $userInfo = Db::name('user')->where('id', $userId)->find();
            if (!isset($userInfo['nxxfj'])) {
                throw new \Exception('用户补贴金字段不存在');
            }
            
            // 增加用户补贴金
            $before = $userInfo['nxxfj'];
            $after = bcadd($before, $amount, 2);
            
            // 更新用户补贴金
            $updateResult = Db::name('user')->where('id', $userId)->update([
                'nxxfj' => $after
            ]);
            if (!$updateResult) {
                throw new \Exception('更新用户补贴金失败');
            }
            
            // 写入日志
            $logResult = Db::name('user_nxxfjlog')->insert([
                'user_id' => $userId, 
                'money' => $amount, 
                'before' => $before, 
                'after' => $after, 
                'memo' => $memo,
                'createtime' => time()
            ]);
            if (!$logResult) {
                throw new \Exception('写入补贴金日志失败');
            }

            // 计算利息并添加补贴金记录
            $year = date('Y');
            $annualization = config('site.annualization');
            if (!$annualization) {
                throw new \Exception('年化率配置不存在');
            }

            // 计算每日利息
            // 计算总天数
            $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
            $totalDays = $isLeap ? 366 : 365;
            
            // 计算总利息：本金 × 年利率
            $totalInterest = bcmul($amount, $annualization[1], 2);
            // 总天数需要乘以100
            $totalDays = bcmul($totalDays, 100, 0);
            // 计算每天利息：总利息 ÷ 天数
            $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
            
            // 计算到期时间（当年12月31日23:59:59）
            $nextYearTimestamp = strtotime(date('Y-12-31 23:59:59'));

            // 添加补贴金记录
            $createtime = time();
            $updatetime = strtotime("+2 days", $createtime); // 更新时间为创建时间+2天
            
            $nxxfjData = [
                'money' => $amount,
                'user_id' => $userId,
                'yeartime' => 1,
                'year_rate' => $annualization[1],
                'expirytime' => $nextYearTimestamp,
                'status' => 1,
                'interest' => $dailyInterest,
                'createtime' => $createtime,
                'updatetime' => $updatetime
            ];

            $insertResult = Db::name('nxxfjlist')->insert($nxxfjData);
            if (!$insertResult) {
                throw new \Exception('添加补贴金记录失败');
            }
            
            return [
                '用户ID' => $userId,
                '赠送金额' => $amount,
                '每日利息' => $dailyInterest,
                '到期时间' => date('Y-m-d H:i:s', $nextYearTimestamp)
            ];
        });
        
        if ($result !== false) {
            $this->success('赠送成功', $result);
        } else {
            $this->error('赠送失败');
        }
    }

    /**
     * 批量赠送用户消费补贴金
     *
     * @ApiTitle    (批量赠送用户消费补贴金)
     * @ApiSummary  (批量给多个用户赠送消费补贴金，并自动计算利息)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/batchGiveUserNxxfj)
     * @ApiParams   (name="userIds", type="string", required=true, description="用户ID列表，多个ID以英文逗号分隔，如：1,2,3")
     * @ApiParams   (name="amount", type="number", required=true, description="赠送金额")
     * @ApiParams   (name="memo", type="string", required=false, description="备注信息")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="批量赠送成功")
     * @ApiReturnParams   (name="data", type="object", description="返回数据")
     */
    public function batchGiveUserNxxfj()
    {
        $usernames = $this->request->post('usernames/s');
        $amount = $this->request->post('amount/f');
        $memo = $this->request->post('memo/s', '系统批量赠送补贴金');

        // 基本验证
        if (empty($usernames)) {
            $this->error('用户名列表不能为空');
        }

        if ($amount <= 0) {
            $this->error('赠送金额必须大于0');
        }

        // 将用户名字符串转为数组并处理
        $usernameArray = array_filter(array_unique(explode(',', $usernames)));
        
        if (empty($usernameArray)) {
            $this->error('没有有效的用户名');
        }

        // 根据用户名获取用户ID
        $userIdArray = Db::name('user')->where('username', 'in', $usernameArray)->column('id');
        
        if (empty($userIdArray)) {
            $this->error('未找到任何有效用户');
        }

        // 检查是否所有用户名都找到了对应的用户
        if (count($userIdArray) !== count($usernameArray)) {
            $foundUsernames = Db::name('user')->where('id', 'in', $userIdArray)->column('username');
            $notFoundUsernames = array_diff($usernameArray, $foundUsernames);
            $this->error('以下用户名未找到：' . implode(',', $notFoundUsernames));
        }

        // 检查用户是否都存在
        $users = Db::name('user')->where('id', 'in', $userIdArray)->column('nxxfj', 'id');
        if (count($users) !== count($userIdArray)) {
            $this->error('存在无效的用户ID');
        }

        // 计算利息相关参数
        $year = date('Y');
        $annualization = config('site.annualization');
        if (!$annualization || !isset($annualization[1])) {
            $this->error('年化率配置不存在或无效');
        }
        
        // 计算每日利息
        // 计算总天数
        $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
        $totalDays = $isLeap ? 366 : 365;
        
        // 计算总利息：本金 × 年利率
        $totalInterest = bcmul($amount, $annualization[1], 2);
        // 总天数需要乘以100
        $totalDays = bcmul($totalDays, 100, 0);
        // 计算每天利息：总利息 ÷ 天数
        $dailyInterest = bcdiv($totalInterest, $totalDays, 2);

        // 计算到期时间（当年12月31日23:59:59）
        $nextYearTimestamp = strtotime(date('Y-12-31 23:59:59'));
        $createtime = time();
        $updatetime = strtotime("+2 days", $createtime);

        // 开始批量处理
        $result = Db::transaction(function() use ($userIdArray, $users, $amount, $memo, $dailyInterest, $nextYearTimestamp, $annualization) {
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $createtime = time();
            $updatetime = strtotime("+2 days", $createtime);
            
            foreach ($userIdArray as $userId) {
                $before = $users[$userId];
                $after = bcadd($before, $amount, 2);

                // 更新用户补贴金
                $updateResult = Db::name('user')->where('id', $userId)->update([
                    'nxxfj' => $after
                ]);
                if (!$updateResult) {
                    throw new \Exception("更新用户 {$userId} 补贴金失败");
                }

                // 写入日志
                $logResult = Db::name('user_nxxfjlog')->insert([
                    'user_id' => $userId,
                    'money' => $amount,
                    'before' => $before,
                    'after' => $after,
                    'memo' => $memo,
                    'createtime' => $createtime
                ]);
                if (!$logResult) {
                    throw new \Exception("写入用户 {$userId} 补贴金日志失败");
                }

                // 添加补贴金记录
                $nxxfjData = [
                    'money' => $amount,
                    'user_id' => $userId,
                    'yeartime' => 1,
                    'year_rate' => $annualization[1],
                    'expirytime' => $nextYearTimestamp,
                    'status' => 1,
                    'interest' => $dailyInterest,
                    'createtime' => $createtime,
                    'updatetime' => $updatetime
                ];

                $insertResult = Db::name('nxxfjlist')->insert($nxxfjData);
                if (!$insertResult) {
                    throw new \Exception("添加用户 {$userId} 补贴金记录失败");
                }

                $successCount++;
                $results[] = [
                    '用户ID' => $userId,
                    '赠送金额' => $amount,
                    '每日利息' => $dailyInterest,
                    '原补贴金' => $before,
                    '现补贴金' => $after,
                    '状态' => '成功'
                ];
            }

            return [
                '成功数量' => $successCount,
                '失败数量' => $failCount,
                '处理结果' => $results
            ];
        });

        if ($result !== false) {
            $this->success('批量赠送成功', $result);
        } else {
            $this->error('批量赠送失败');
        }
    }

    /**
     * 查询用户下级注册、激活、奖励发放和盲盒消耗情况
     *
     * @ApiTitle    (用户下级活动数据统计)
     * @ApiSummary  (查询指定用户在6月10日至6月24日期间的下级注册和激活情况、奖励发放及盲盒消耗情况)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/getDownlinesStats)
     * @ApiParams   (name="uid", type="integer", required=true, description="要查询的用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="获取成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="统计数据")
     */
    public function getDownlinesStats()
    {
        $uid = $this->request->param('uid', 0, 'intval');
        
        if (empty($uid)) {
            $this->error('请提供有效的用户ID');
        }
        
        // 定义查询时间范围：6月10日至6月24日
        $startTime = strtotime('2025-06-10 00:00:00');
        $endTime = strtotime('2025-06-24 23:59:59');
        
        // 查询用户信息
        $user = Db::name('user')->where('id', $uid)->find();
        if (!$user) {
            $this->error('用户不存在');
        }
        
        // 1. 查询该时间段内下级注册情况
        $downlinesRegister = Db::name('user')
            ->where('upid', $uid)
            ->where('createtime', 'between', [$startTime, $endTime])
            ->field('id, username, mobile, nickname, createtime, is_buy')
            ->select();
            
        // 注册和激活人数统计
        $registerCount = count($downlinesRegister);
        $activatedCount = 0;
        $downlinesData = [];
        
        foreach ($downlinesRegister as &$downline) {
            $downline['createtime_text'] = date('Y-m-d H:i:s', $downline['createtime']);
            $downline['is_activated'] = $downline['is_buy'] == 1 ? '已激活' : '未激活';
            
            if ($downline['is_buy'] == 1) {
                $activatedCount++;
            }
            
            // 查询该下级的订单情况
            $orders = Db::name('order')
                ->where('user_id', $downline['id'])
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('status', 2) // 已支付的订单
                ->select();
                
            $downline['orders_count'] = count($orders);
            $downline['total_spent'] = 0;
            
            foreach ($orders as $order) {
                $downline['total_spent'] += $order['price'];
            }
            
            // 查询下级账户当前余额
            $userInfo = Db::name('user')
                ->where('id', $downline['id'])
                ->field('nomoney')
                ->find();
            
            // 添加下级账户余额(使用nomoney字段)
            $downline['account_balance'] = isset($userInfo['nomoney']) ? floatval($userInfo['nomoney']) : 0;
            
            // 查询下级总认购金额（所有有效订单）
            $totalPurchase = Db::name('order')
                ->where('user_id', $downline['id'])
                ->where('status', 2) // 已支付的订单
                ->sum('price');
            
            // 添加下级总认购金额 - 确保这是完全独立的字段
            $downline['total_purchase'] = floatval($totalPurchase);
            
            $downlinesData[] = $downline;
        }
        
        // 2. 查询奖励发放情况
        // 查询用户在该时间段内获得的消费补贴金
        $nxxfjRewards = [];
        try {
            $nxxfjRewards = Db::table('fa_user_nxxfjlog')
                ->where('user_id', $uid)
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('memo', 'like', '%推荐%')
                ->select();
        } catch (\Exception $e) {
            // 记录错误但继续执行
        }
            
        // 分红奖励查询已移除，根据需求
        
        // 3. 查询盲盒情况
        
        // 3.1 查询用户当前盲盒数量
        $currentBlindboxData = [];
        try {
            $currentBlindboxData = Db::table('fa_user_blindbox')
                ->where('user_id', $uid)
                ->find();
        } catch (\Exception $e) {
            // 记录错误但继续执行
        }
        
        // 3.2 查询盲盒使用数量
        $blindboxUsedCount = 0;
        try {
            $blindboxUsedCount = Db::table('fa_user_blindboxlog')
                ->where('user_id', $uid)
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('money', '<', 0) // 负值表示消耗
                ->count();
        } catch (\Exception $e) {
            // 记录错误但继续执行
        }
        
        // 3.2.1 查询盲盒奖品记录
        $blindboxRewards = [];
        try {
            // 根据Blinbox控制器中blindboxlist方法的实现，从正确的表中查询盲盒中奖记录
            $blindboxRewards = Db::table('fa_user_blindboxlist')
                ->alias('u')
                ->join('fa_blindboxlist b', 'u.blindboxlist_id = b.id')
                ->where('u.user_id', $uid)
                ->where('u.createtime', 'between', [$startTime, $endTime])
                ->field('u.*, b.name as prize_name, b.type, b.money, b.money_fields')
                ->select();
            
            // 为奖品添加描述信息
            foreach ($blindboxRewards as &$reward) {
                // 根据奖品类型设置奖品描述
                if (isset($reward['type'])) {
                    switch ($reward['type']) {
                        case 0:
                            $reward['type_desc'] = '未中奖';
                            break;
                        case 1:
                            $reward['type_desc'] = '实物奖品';
                            break;
                        case 2:
                            $reward['type_desc'] = '消费补贴金';
                            break;
                        case 3:
                            $reward['type_desc'] = '内需券';
                            break;
                        case 4:
                            $reward['type_desc'] = '直接现金';
                            break;
                        case 5:
                            $reward['type_desc'] = '经验值';
                            break;
                        default:
                            $reward['type_desc'] = '其他奖品';
                    }
                }
                
                // 如果有金额显示金额
                if (isset($reward['money']) && $reward['money'] > 0) {
                    $money_type = '';
                    if (isset($reward['money_fields'])) {
                        switch($reward['money_fields']) {
                            case 'nxxfj':
                                $money_type = '元消费补贴金';
                                break;
                            case 'bbxjhb':
                                $money_type = '元现金';
                                break;
                            case 'game_level_jy':
                                $money_type = '点经验值';
                                break;
                            default:
                                $money_type = '元内需券';
                                break;  
                        }
                    }
                    $reward['description'] = $reward['prize_name'] . ' (' . $reward['money'] . $money_type . ')';
                } else {
                    $reward['description'] = $reward['prize_name'];
                }
            }
            
        } catch (\Exception $e) {
            // 如果表不存在或者查询失败，返回空数组
            $blindboxRewards = [];
        }
        
        // 3.3 查询盲盒获取记录
        $blindboxGainLogs = [];
        try {
            $blindboxGainLogs = Db::table('fa_user_blindboxlog')
                ->where('user_id', $uid)
                ->where('createtime', 'between', [$startTime, $endTime])
                ->where('money', '>', 0) // 正值表示获得
                ->where('memo', 'like', '%盲盒活动购买产品%')
                ->select();
        } catch (\Exception $e) {
            // 记录错误但继续执行
        }
            
        // 统计盲盒使用和获取情况
        $blindboxUsed = $blindboxUsedCount;
        $blindboxGained = array_sum(array_column($blindboxGainLogs, 'money'));
        
        // 获取当前拥有的盲盒数量
        $currentBlindbox = [];
        if ($currentBlindboxData) {
            foreach ($currentBlindboxData as $key => $value) {
                if (strpos($key, 'level') === 0) {
                    $currentBlindbox[$key] = $value;
                }
            }
        }
        
        // 检查和调试返回数据
        foreach($downlinesData as &$downlineItem) {
            // 确保 account_balance 和 total_purchase 字段存在并转为浮点数
            if(!isset($downlineItem['account_balance'])) {
                $downlineItem['account_balance'] = 0;
            } else {
                $downlineItem['account_balance'] = floatval($downlineItem['account_balance']);
            }
            if(!isset($downlineItem['total_purchase'])) {
                $downlineItem['total_purchase'] = 0;
            } else {
                $downlineItem['total_purchase'] = floatval($downlineItem['total_purchase']);
            }
            
            // 格式化返回值，便于前端显示
            $downlineItem['account_balance_text'] = "账户余额: {$downlineItem['account_balance']} 元";
            $downlineItem['total_purchase_text'] = "总认购金额: {$downlineItem['total_purchase']} 元";
        }
        
        // 组织返回数据
        $data = [
            '用户ID' => $uid,
            '用户名' => $user['username'],
            '查询时间范围' => '2025-06-10至2025-06-24',
            '下级统计' => [
                '注册人数' => $registerCount,
                '激活人数' => $activatedCount,
                '下级详情' => $downlinesData
            ],
            '奖励统计' => [
                '消费补贴金奖励' => [
                    '奖励次数' => count($nxxfjRewards),
                    '奖励金额' => array_sum(array_column($nxxfjRewards, 'money')),
                    '奖励详情' => $nxxfjRewards
                ]
            ],
            '盲盒统计' => [
                '当前盲盒数量' => $currentBlindbox,
                '活动期间获得盲盒次数' => $blindboxGained,
                '活动期间已使用盲盒次数' => $blindboxUsed,
                '盲盒获取记录' => $blindboxGainLogs,
                '盲盒获得奖品记录' => $blindboxRewards
            ]
        ];
        
        $this->success('获取成功', $data);
    }

    /**
     * 通过用户ID查询可用余额
     *
     * @ApiTitle    (查询用户可用余额)
     * @ApiSummary  (通过用户uid查询用户的可用余额nomoney)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/getUserNomoney)
     * @ApiParams   (name="uid", type="integer", required=true, description="用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="查询成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="用户余额信息")
     */
    public function getUserNomoney()
    {
        $uid = $this->request->param('uid', 0, 'intval');
        if (empty($uid)) {
            $this->error('请提供有效的用户ID');
        }
        $user = Db::name('user')->where('id', $uid)->field('id,username,mobile,nomoney')->find();
        if (!$user) {
            $this->error('用户不存在');
        }
        $this->success('查询成功', $user);
    }

    /**
     * 用户充值接口
     *
     * @ApiTitle    (用户充值)
     * @ApiSummary  (通过uid和金额为用户充值，并记录日志)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/rechargeUser)
     * @ApiParams   (name="uid", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="amount", type="number", required=true, description="充值金额，正数")
     * @ApiParams   (name="memo", type="string", required=false, description="充值备注")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="充值成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="充值结果")
     */
    public function rechargeUser()
    {
        $uid = $this->request->post('uid', 0, 'intval');
        $amount = $this->request->post('amount', 0, 'floatval');
        $memo = $this->request->post('memo', 'API充值', 'trim');

        if (empty($uid)) {
            $this->error('请提供有效的用户ID');
        }
        if ($amount <= 0) {
            $this->error('充值金额必须大于0');
        }
        $user = Db::name('user')->where('id', $uid)->find();
        if (!$user) {
            $this->error('用户不存在');
        }
        $before = $user['nomoney'];
        $after = bcadd($before, $amount, 2);

        $result = [];
        $success = false;
        Db::startTrans();
        try {
            // 更新用户账户余额（nomoney）
            $res1 = Db::name('user')->where('id', $uid)->update(['nomoney' => $after]);
            // 写入充值日志
            $log = [
                'user_id' => $uid,
                'money' => $amount,
                'before' => $before,
                'after' => $after,
                'memo' => $memo,
                'type' => 'recharge',
                'createtime' => time()
            ];
            $res2 = Db::name('user_money_log')->insert($log);
            if ($res1 === false || $res2 === false) {
                throw new \Exception('充值失败');
            }
            Db::commit();
            $success = true;
            $result = [
                '用户ID' => $uid,
                '充值前余额' => $before,
                '充值金额' => $amount,
                '充值后余额' => $after,
                '备注' => $memo
            ];
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('充值失败：' . $e->getMessage());
            return;
        }
        if ($success) {
            $this->success('充值成功', $result);
        }
    }

    /**
     * 检测邀请人奖励条件并补发
     *
     * @ApiTitle    (检测邀请人奖励条件)
     * @ApiSummary  (检测邀请人在活动期间是否达到奖励条件，如果没有发放奖励则补发)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/checkInviteReward)
     * @ApiHeaders  (name=token, type=string, required=false, description="请求的Token")
     * @ApiParams   (name="user_id", type="integer", required=true, description="邀请人用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="检查完成")
     * @ApiReturnParams   (name="data", type="object", sample="{'valid_invite_count':10,'rewards_given':true}", description="返回数据")
     * @ApiReturn   ({
         'code':'1',
         'msg':'检查完成',
         'data': {
             'valid_invite_count': 10,
             'rewards_given': true,
             'next_target': 15
         }
        })
     */
    public function checkInviteReward()
    {
        $user_id = $this->request->get('user_id', 0, 'intval');
        
        if (empty($user_id)) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        $now = time();
        
        // 获取邀请人信息
        $inviter = Db::name('user')->where('id', $user_id)->find();
        if (!$inviter) {
            $this->error('用户不存在', [
                '用户ID' => $user_id,
                '失败原因' => '用户ID不存在'
            ]);
        }
        
        // 检查活动时间
        if ($now < strtotime('2025-04-08 00:00:00') || $now > strtotime('2025-08-09 23:59:59')) {
            $this->error('当前不在活动期间', [
                '当前时间' => date('Y-m-d H:i:s', $now),
                '活动开始时间' => '2025-04-08 00:00:00',
                '活动结束时间' => '2025-08-09 23:59:59',
                '失败原因' => '活动时间限制'
            ]);
            return;
        }
        
        // 统计该邀请人在活动期间邀请的下级，且下级第一次提交投资表单的数量
        $valid_invites = 0;
        $invited_users = Db::name('user')
            ->where('upid', $user_id)
            ->where('createtime', '>=', strtotime('2025-04-08 00:00:00'))
            ->where('createtime', '<=', strtotime('2025-08-09 23:59:59'))
            ->select();
            
        $邀请详情 = [];
        $总邀请人数 = count($invited_users);
        $已提交表单人数 = 0;
        $首次提交表单人数 = 0;
        
        foreach ($invited_users as $invited_user) {
            $用户详情 = [
                '用户ID' => $invited_user['id'],
                '用户名' => $invited_user['username'] ?? '',
                '注册时间' => date('Y-m-d H:i:s', $invited_user['createtime']),
                '是否提交表单' => false,
                '是否首次提交' => false,
                '提交时间' => null
            ];
            
            // 检查该用户是否在活动期间提交过投资表单
            $investment_record = Db::name('dream_funding_record')
                ->where('user_id', $invited_user['id'])
                ->where('createtime', '>=', strtotime('2025-04-08 00:00:00'))
                ->where('createtime', '<=', strtotime('2025-08-09 23:59:59'))
                ->order('createtime ASC')
                ->find();
                
            if ($investment_record) {
                $已提交表单人数++;
                $用户详情['是否提交表单'] = true;
                $用户详情['提交时间'] = date('Y-m-d H:i:s', $investment_record['createtime']);
                
                // 检查是否是第一次提交（没有更早的提交记录）
                $previous_submit = Db::name('dream_funding_record')
                    ->where('user_id', $invited_user['id'])
                    ->where('createtime', '<', $investment_record['createtime'])
                    ->find();
                    
                if (!$previous_submit) {
                    $valid_invites++;
                    $首次提交表单人数++;
                    $用户详情['是否首次提交'] = true;
                }
            }
            
            $邀请详情[] = $用户详情;
        }
        
        // 如果统计的有效邀请数与数据库中的不一致，更新数据库
        $数据库中的邀请数 = $inviter['valid_invite_count'];
        if ($valid_invites != $inviter['valid_invite_count']) {
            Db::name('user')->where('id', $user_id)->update(['valid_invite_count' => $valid_invites]);
            $inviter['valid_invite_count'] = $valid_invites;
        }
        
        // 奖励规则
        $rewards = [
            5 => ['inner' => 250, 'coupon' => 500],
            15 => ['inner' => 500, 'coupon' => 1000],
            35 => ['inner' => 1000, 'coupon' => 2000],
            65 => ['inner' => 1500, 'coupon' => 3000]
        ];
        
        // 显示人数映射
        $display_counts = [
            15 => 10,
            35 => 20,
            65 => 30
        ];
        
        // 检查是否达到奖励条件 - 修改逻辑，检查是否达到某个奖励等级但未发放
        $reward_levels = [5, 15, 35, 65];
        $achieved_level = null;
        $display_count = null;
        
        // 找到用户达到的最高奖励等级
        foreach ($reward_levels as $level) {
            if ($valid_invites >= $level) {
                $achieved_level = $level;
                $display_count = isset($display_counts[$level]) ? $display_counts[$level] : $level;
            }
        }
        
        if ($achieved_level) {
            // 检查是否已经发放过这个等级的奖励
            $reward_log = Db::name('user_money_log')
                ->where('user_id', $user_id)
                ->where('memo', 'like', '%邀请'.$display_count.'人完成0元购活动奖励%')
                ->find();

            if (!$reward_log) {
                Db::startTrans();
                try {
                    $reward = $rewards[$achieved_level];
                    
                    // 增加邀请人的提交机会
                    Db::name('user')->where(['id' => $user_id])->setInc('is_submit', 1);
                    
                    // 发放消费券
                    $before_score = $inviter['score'];
                    Db::name('user')->where('id', $user_id)->setInc('score', $reward['coupon']);
                    // 记录消费券日志
                    Db::name('user_money_log')->insert([
                        'user_id' => $user_id,
                        'money' => $reward['coupon'],
                        'before' => $before_score,
                        'after' => $before_score + $reward['coupon'],
                        'memo' => '邀请'.$display_count.'人完成0元购活动奖励-消费券',
                        'createtime' => $now,
                        'type' => 'coupon'
                    ]);

                    // 发放内需券
                    $before_neixu = $inviter['neixuquan'];
                    Db::name('user')->where('id', $user_id)->setInc('neixuquan', $reward['inner']);
                    // 记录内需券日志
                    Db::name('user_money_log')->insert([
                        'user_id' => $user_id,
                        'money' => $reward['inner'],
                        'before' => $before_neixu,
                        'after' => $before_neixu + $reward['inner'],
                        'memo' => '邀请'.$display_count.'人完成0元购活动奖励-内需券',
                        'createtime' => $now,
                        'type' => 'neixuquan'
                    ]);
                    
                    Db::commit();
                    
                    // 计算下一个奖励目标
                    $reward_levels = [5, 15, 35, 65];
                    $next_target = null;
                    foreach ($reward_levels as $level) {
                        if ($valid_invites < $level) {
                            $next_target = $level;
                            break;
                        }
                    }
                    
                    // 构建返回数据
                    $response_data = [
                        '有效邀请数' => $valid_invites,
                        '数据库中的邀请数' => $数据库中的邀请数,
                        '是否发放奖励' => true,
                        '达到的奖励等级' => $achieved_level,
                        '显示人数' => $display_count,
                        '奖励详情' => [
                            '消费券' => $reward['coupon'],
                            '内需券' => $reward['inner']
                        ],
                        '下一个目标' => $next_target,
                        '还需邀请人数' => $next_target ? ($next_target - $valid_invites) : 0,
                        '失败原因' => '达到奖励条件且未发放过',
                        '统计信息' => [
                            '总邀请人数' => $总邀请人数,
                            '已提交表单人数' => $已提交表单人数,
                            '首次提交表单人数' => $首次提交表单人数
                        ],
                        '邀请详情' => $邀请详情
                    ];
                    
                    $this->success('奖励补发成功', $response_data);
                    return; // 添加return确保方法结束
                    
                } catch (\Exception $e) {
                    Db::rollback();
                    \think\Log::write('邀请奖励补发异常: ' . $e->getMessage(), 'error');
                    $this->error('奖励补发失败', [
                        '错误信息' => $e->getMessage(),
                        '有效邀请数' => $valid_invites,
                        '失败原因' => '系统异常'
                    ]);
                }
            } else {
                // 计算下一个奖励目标
                $reward_levels = [5, 15, 35, 65];
                $next_target = null;
                foreach ($reward_levels as $level) {
                    if ($valid_invites < $level) {
                        $next_target = $level;
                        break;
                    }
                }
                
                $this->success('该等级奖励已发放过', [
                    '有效邀请数' => $valid_invites,
                    '数据库中的邀请数' => $数据库中的邀请数,
                    '是否发放奖励' => false,
                    '达到的奖励等级' => $achieved_level,
                    '显示人数' => $display_count,
                    '下一个目标' => $next_target,
                    '还需邀请人数' => $next_target ? ($next_target - $valid_invites) : 0,
                    '失败原因' => '该等级奖励已发放过',
                    '奖励日志ID' => $reward_log['id'] ?? null,
                    '统计信息' => [
                        '总邀请人数' => $总邀请人数,
                        '已提交表单人数' => $已提交表单人数,
                        '首次提交表单人数' => $首次提交表单人数
                    ],
                    '邀请详情' => $邀请详情
                ]);
            }
        } else {
            // 计算下一个奖励目标
            $reward_levels = [5, 15, 35, 65];
            $next_target = null;
            foreach ($reward_levels as $level) {
                if ($valid_invites < $level) {
                    $next_target = $level;
                    break;
                }
            }
            
            $this->success('未达到奖励条件', [
                '有效邀请数' => $valid_invites,
                '数据库中的邀请数' => $数据库中的邀请数,
                '是否发放奖励' => false,
                '下一个目标' => $next_target,
                '还需邀请人数' => $next_target ? ($next_target - $valid_invites) : 0,
                '失败原因' => '邀请人数未达到奖励条件',
                '奖励等级列表' => [5, 15, 35, 65],
                '当前等级' => $valid_invites,
                '统计信息' => [
                    '总邀请人数' => $总邀请人数,
                    '已提交表单人数' => $已提交表单人数,
                    '首次提交表单人数' => $首次提交表单人数
                ],
                '邀请详情' => $邀请详情
            ]);
        }
    }



    /**
     * 查询用户7月份激活数量
     *
     * @ApiTitle    (查询用户7月份激活数量)
     * @ApiSummary  (根据用户ID查询该用户在7月份的激活数量，不管用户是几月份注册的，只要在7月激活的都统计)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/demo/getJulyActivationCount)
     * @ApiParams   (name="id", type="integer", required=true, description="用户ID")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="查询成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="7月份激活统计数据")
     */
    public function getJulyActivationCount()
    {
        $userId = $this->request->param('id', 0, 'intval');
        
        if (empty($userId)) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        // 检查用户是否存在
        $user = Db::name('user')->where('id', $userId)->find();
        if (!$user) {
            $this->error('用户不存在');
            return;
        }
        
        // 固定查询时间：2025年7月1日到2025年7月31日
        $julyStart = strtotime('2025-07-01 00:00:00');
        $julyEnd = strtotime('2025-07-31 23:59:59');
        
        // 查询7月份激活的下级用户数量（不管注册时间，只要在7月激活的）
        $julyActivationCount = Db::name('user')
            ->where([
                'upid' => $userId,
                'is_buy' => 1,
                'buy_time' => ['between', [$julyStart, $julyEnd]]
            ])
            ->count();
        
        // 查询7月份注册的下级用户数量
        $julyRegisterCount = Db::name('user')
            ->where([
                'upid' => $userId,
                'createtime' => ['between', [$julyStart, $julyEnd]]
            ])
            ->count();
        
        // 查询7月份注册且在7月份激活的用户数量
        $julyRegisterAndActivateCount = Db::name('user')
            ->where([
                'upid' => $userId,
                'createtime' => ['between', [$julyStart, $julyEnd]],
                'is_buy' => 1,
                'buy_time' => ['between', [$julyStart, $julyEnd]]
            ])
            ->count();
        
        // 查询7月份激活的详细用户列表
        $julyActivatedUsers = Db::name('user')
            ->where([
                'upid' => $userId,
                'is_buy' => 1,
                'buy_time' => ['between', [$julyStart, $julyEnd]]
            ])
            ->field('id, username, nickname, mobile, createtime, buy_time')
            ->order('buy_time desc')
            ->select();
        
        // 处理用户列表数据
        foreach ($julyActivatedUsers as &$user) {
            $user['createtime_text'] = date('Y-m-d H:i:s', $user['createtime']);
            $user['buy_time_text'] = date('Y-m-d H:i:s', $user['buy_time']);
            $user['注册月份'] = date('Y-m', $user['createtime']);
            $user['激活月份'] = date('Y-m', $user['buy_time']);
        }
        
        // 查询该用户的总下级数量和总激活数量（所有时间）
        $totalDownlines = Db::name('user')
            ->where(['upid' => $userId])
            ->count();
        
        $totalActivated = Db::name('user')
            ->where([
                'upid' => $userId,
                'is_buy' => 1
            ])
            ->count();
        
        // 构建返回数据
        $data = [
            '用户ID' => $userId,
            '用户名' => $user['username'],
            '用户昵称' => $user['nickname'],
            '查询月份' => '2025-07',
            '7月份激活统计' => [
                '激活数量' => $julyActivationCount,
                '注册数量' => $julyRegisterCount,
                '注册且激活数量' => $julyRegisterAndActivateCount,
                '非7月注册但在7月激活数量' => $julyActivationCount - $julyRegisterAndActivateCount
            ],
            '历史统计' => [
                '总下级数量' => $totalDownlines,
                '总激活数量' => $totalActivated,
                '总激活率' => $totalDownlines > 0 ? round(($totalActivated / $totalDownlines) * 100, 2) . '%' : '0%'
            ],
            '7月份激活用户详情' => $julyActivatedUsers,
            '查询时间' => date('Y-m-d H:i:s'),
            '时间范围' => [
                '开始时间' => '2025-07-01 00:00:00',
                '结束时间' => '2025-07-31 23:59:59'
            ],
            '说明' => '激活数量包含所有在7月份激活的下级用户，不管其注册时间'
        ];
        
        $this->success('查询成功', $data);
    }

    /**
     * 给指定ID用户创建储存记录
     *
     * @ApiTitle    (创建储存记录)
     * @ApiSummary  (给指定用户ID创建内需消费补贴金储存记录)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/createNxxfjRecord)
     * @ApiParams   (name="userId", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="amount", type="number", required=true, description="储存金额")
     * @ApiParams   (name="memo", type="string", required=false, description="备注信息")
     * @ApiParams   (name="starttime", type="string", required=false, description="开始时间，格式：YYYY-MM-DD HH:MM:SS，用于计算补发利息")
     * @ApiParams   (name="expirytime", type="string", required=false, description="到期时间，格式：YYYY-MM-DD HH:MM:SS，默认为当年12月31日")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="创建成功")
     * @ApiReturnParams   (name="data", type="object", description="返回数据")
     */
    public function createNxxfjRecord()
    {
        $userId = $this->request->post('userId/d');
        $amount = $this->request->post('amount/f');
        $memo = $this->request->post('memo/s', '系统检测自动');
        $starttime = $this->request->post('starttime/s');
        $expirytime = $this->request->post('expirytime/s');

        // 基本验证
        if (!$userId) {
            $this->error('用户ID不能为空');
        }

        if ($amount <= 0) {
            $this->error('储存金额必须大于0');
        }

        // 检查用户是否存在
        $user = \app\common\model\User::get($userId);
        if (!$user) {
            $this->error('用户不存在');
        }

        // 获取年化率配置
        $annualization = config('site.annualization');
        if (!$annualization || !isset($annualization[1])) {
            $this->error('年化率配置不存在或无效');
        }

        // 处理开始时间
        $starttimeTimestamp = null;
        $backInterest = 0; // 补发利息
        $backInterestDays = 0; // 补发利息天数
        
        if (!empty($starttime)) {
            $starttimeTimestamp = strtotime($starttime);
            if ($starttimeTimestamp === false) {
                $this->error('开始时间格式错误，请使用 YYYY-MM-DD HH:MM:SS 格式');
            }
            
            $currentTime = time();
            if ($starttimeTimestamp > $currentTime) {
                $this->error('开始时间不能晚于当前时间');
            }
            
            // 计算补发利息天数
            $backInterestDays = ceil(($currentTime - $starttimeTimestamp) / 86400); // 向上取整天数
            
            if ($backInterestDays > 0) {
                // 计算每日利息
                $year = date('Y', $starttimeTimestamp);
                $dailyInterest = $this->getinterest($year, $amount, $annualization[1]);
                
                // 计算补发利息
                $backInterest = bcmul($dailyInterest, $backInterestDays, 2);
            }
        }

        // 处理到期时间
        if (empty($expirytime)) {
            // 默认为当年12月31日23:59:59
            $expirytime = date('Y-12-31 23:59:59');
        }

        $expirytimeTimestamp = strtotime($expirytime);
        if ($expirytimeTimestamp === false) {
            $this->error('到期时间格式错误，请使用 YYYY-MM-DD HH:MM:SS 格式');
        }

        // 计算利息（如果未指定开始时间，则使用到期时间计算）
        $year = date('Y', $expirytimeTimestamp);
        $dailyInterest = $this->getinterest($year, $amount, $annualization[1]);

        // 开启事务
        $result = Db::transaction(function() use ($userId, $amount, $memo, $expirytimeTimestamp, $dailyInterest, $annualization, $starttimeTimestamp, $backInterest, $backInterestDays, $user) {
            $createtime = $starttimeTimestamp ?: time();
            $updatetime = time();

            // 创建储存记录
            $nxxfjData = [
                'user_id' => $userId,
                'money' => $amount,
                'interest' => $dailyInterest,
                'yeartime' => 1,
                'year_rate' => $annualization[1],
                'expirytime' => $expirytimeTimestamp,
                'status' => 1,
                'createtime' => $createtime,
                'updatetime' => $updatetime
            ];

            $insertResult = Db::name('nxxfjlist')->insert($nxxfjData);
            if (!$insertResult) {
                throw new \Exception('创建储存记录失败');
            }

            // 如果有补发利息，更新用户的利息余额
            if ($backInterest > 0) {
                $currentNxxfjlx = $user->nxxfjlx;
                $newNxxfjlx = bcadd($currentNxxfjlx, $backInterest, 2);
                
                $updateResult = Db::name('user')->where('id', $userId)->update([
                    'nxxfjlx' => $newNxxfjlx
                ]);
                if (!$updateResult) {
                    throw new \Exception('更新用户利息余额失败');
                }

                // 记录利息补发日志
                $interestLogData = [
                    'user_id' => $userId,
                    'money' => $backInterest,
                    'before' => $currentNxxfjlx,
                    'after' => $newNxxfjlx,
                    'memo' => $memo . ' - 补发利息(' . $backInterestDays . '天)',
                    'createtime' => $createtime
                ];

                $interestLogResult = Db::name('user_nxxfjlxlog')->insert($interestLogData);
                if (!$interestLogResult) {
                    throw new \Exception('记录利息补发日志失败');
                }
            }

            $resultData = [
                '用户ID' => $userId,
                '储存金额' => $amount,
                '每日利息' => $dailyInterest,
                '年化率' => $annualization[1],
                '到期时间' => date('Y-m-d H:i:s', $expirytimeTimestamp),
                '备注' => $memo
            ];

            // 如果有开始时间和补发利息，添加到返回数据中
            if ($starttimeTimestamp) {
                $resultData['开始时间'] = date('Y-m-d H:i:s', $starttimeTimestamp);
                $resultData['补发利息天数'] = $backInterestDays;
                $resultData['补发利息金额'] = $backInterest;
            }

            return $resultData;
        });

        if ($result !== false) {
            $this->success('创建储存记录成功', $result);
        } else {
            $this->error('创建储存记录失败');
        }
    }

    /**
     * 赠送指定用户内需消费补贴金和内需券
     *
     * @ApiTitle    (批量赠送内需消费补贴金和内需券)
     * @ApiSummary  (给指定用户(支持多个)赠送内需消费补贴金和内需券，自动创建储存单并记录日志)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/giveUserNxxfjAndNeixuquan)
     * @ApiParams   (name="usernames", type="string", required=true, description="用户名列表，多个用户名以英文逗号分隔，如：user1,user2,user3 或单个用户名")
     * @ApiParams   (name="nxxfjAmount", type="number", required=false, description="内需消费补贴金金额，默认为0")
     * @ApiParams   (name="neixuquanAmount", type="number", required=false, description="内需券金额，默认为0")
     * @ApiParams   (name="memo", type="string", required=false, description="赠送备注")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="批量赠送成功")
     * @ApiReturnParams   (name="data", type="object", description="批量赠送结果")
     */
    public function giveUserNxxfjAndNeixuquan()
    {
        // 获取参数
        $usernames = $this->request->post('usernames/s');
        $nxxfjAmount = $this->request->post('nxxfjAmount/f', 0);
        $neixuquanAmount = $this->request->post('neixuquanAmount/f', 0);
        $memo = $this->request->post('memo/s', '管理员赠送奖励');

        // 参数验证
        if (empty($usernames)) {
            $this->error('请提供有效的用户名列表');
        }

        if ($nxxfjAmount < 0 || $neixuquanAmount < 0) {
            $this->error('赠送金额不能为负数');
        }

        if ($nxxfjAmount == 0 && $neixuquanAmount == 0) {
            $this->error('至少需要赠送一种奖励（内需消费补贴金或内需券）');
        }

        // 处理用户名列表
        $usernameArray = array_filter(array_unique(explode(',', $usernames)));
        
        if (empty($usernameArray)) {
            $this->error('没有有效的用户名');
        }

        // 批量获取用户信息
        $users = Db::name('user')->where('username', 'in', $usernameArray)->select();
        if (empty($users)) {
            $this->error('未找到任何有效用户');
        }

        // 检查是否所有用户名都存在
        $foundUsernames = array_column($users, 'username');
        $notFoundUsernames = array_diff($usernameArray, $foundUsernames);
        if (!empty($notFoundUsernames)) {
            $this->error('以下用户名不存在：' . implode(',', $notFoundUsernames));
        }

        // 将用户数据转为以username为键的数组
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user['username']] = $user;
        }

        // 获取年化率配置（如果需要赠送内需消费补贴金）
        $annualization = null;
        if ($nxxfjAmount > 0) {
            $annualization = config('site.annualization');
            if (!$annualization || !isset($annualization[1])) {
                $this->error('年化率配置不存在或无效');
            }
        }

        // 处理结果
        $results = [];
        $successCount = 0;
        $failCount = 0;
        $currentTime = time();

        // 遍历处理每个用户
        foreach ($usernameArray as $username) {
            $user = $userMap[$username];
            $userId = $user['id']; // 获取用户ID用于数据库操作
            
            // 为每个用户开启独立事务
            $userResult = Db::transaction(function() use ($userId, $user, $nxxfjAmount, $neixuquanAmount, $memo, $currentTime, $annualization) {
                $userResults = [];

                // 1. 处理内需消费补贴金赠送
                if ($nxxfjAmount > 0) {
                    // 更新用户内需消费补贴金余额
                    $beforeNxxfj = $user['nxxfj'];
                    $afterNxxfj = bcadd($beforeNxxfj, $nxxfjAmount, 2);
                    
                    $updateNxxfjResult = Db::name('user')->where('id', $userId)->update([
                        'nxxfj' => $afterNxxfj
                    ]);
                    if (!$updateNxxfjResult) {
                        throw new \Exception('更新用户内需消费补贴金失败');
                    }

                    // 记录内需消费补贴金变动日志
                    $nxxfjLogData = [
                        'user_id' => $userId,
                        'money' => $nxxfjAmount,
                        'before' => $beforeNxxfj,
                        'after' => $afterNxxfj,
                        'memo' => $memo . ' - 内需消费补贴金',
                        'createtime' => $currentTime
                    ];

                    $nxxfjLogResult = Db::name('user_nxxfjlog')->insert($nxxfjLogData);
                    if (!$nxxfjLogResult) {
                        throw new \Exception('记录内需消费补贴金日志失败');
                    }

                    // 计算利息相关数据
                    $year = date('Y');
                    $dailyInterest = $this->getinterest($year, $nxxfjAmount, $annualization[1]);
                    $expirytime = strtotime(date('Y-12-31 23:59:59')); // 当年12月31日到期
                    $updatetime = bcadd($currentTime, 172800); // 创建时间+2天

                    // 创建储存单记录
                    $nxxfjlistData = [
                        'user_id' => $userId,
                        'money' => $nxxfjAmount,
                        'interest' => $dailyInterest,
                        'yeartime' => 1,
                        'year_rate' => $annualization[1],
                        'expirytime' => $expirytime,
                        'status' => 1,
                        'createtime' => $currentTime,
                        'updatetime' => $updatetime
                    ];

                    $nxxfjlistResult = Db::name('nxxfjlist')->insert($nxxfjlistData);
                    if (!$nxxfjlistResult) {
                        throw new \Exception('创建内需消费补贴金储存单失败');
                    }

                    $userResults['内需消费补贴金'] = [
                        '赠送金额' => $nxxfjAmount,
                        '原余额' => $beforeNxxfj,
                        '新余额' => $afterNxxfj,
                        '每日利息' => $dailyInterest,
                        '年化率' => $annualization[1] . '%',
                        '到期时间' => date('Y-m-d H:i:s', $expirytime)
                    ];
                }

                // 2. 处理内需券赠送
                if ($neixuquanAmount > 0) {
                    // 更新用户内需券余额
                    $beforeNeixuquan = $user['neixuquan'];
                    $afterNeixuquan = bcadd($beforeNeixuquan, $neixuquanAmount, 2);
                    
                    $updateNeixuquanResult = Db::name('user')->where('id', $userId)->update([
                        'neixuquan' => $afterNeixuquan
                    ]);
                    if (!$updateNeixuquanResult) {
                        throw new \Exception('更新用户内需券失败');
                    }

                    // 记录内需券变动日志
                    $neixuquanLogData = [
                        'user_id' => $userId,
                        'money' => $neixuquanAmount,
                        'before' => $beforeNeixuquan,
                        'after' => $afterNeixuquan,
                        'memo' => $memo . ' - 内需券',
                        'createtime' => $currentTime,
                        'type' => 'neixuquan'
                    ];

                    $neixuquanLogResult = Db::name('user_money_log')->insert($neixuquanLogData);
                    if (!$neixuquanLogResult) {
                        throw new \Exception('记录内需券日志失败');
                    }

                    $userResults['内需券'] = [
                        '赠送金额' => $neixuquanAmount,
                        '原余额' => $beforeNeixuquan,
                        '新余额' => $afterNeixuquan
                    ];
                }

                return $userResults;
            });

            if ($userResult !== false) {
                $results[] = [
                    '用户名' => $user['username'],
                    '用户ID' => $userId,
                    '用户昵称' => $user['nickname'],
                    '状态' => '成功',
                    '赠送详情' => $userResult
                ];
                $successCount++;
            } else {
                $results[] = [
                    '用户名' => $user['username'],
                    '用户ID' => $userId,
                    '用户昵称' => $user['nickname'],
                    '状态' => '失败',
                    '失败原因' => '事务处理失败'
                ];
                $failCount++;
            }
        }

        // 返回结果
        if ($successCount > 0) {
            $this->success("批量赠送完成：成功 {$successCount} 个，失败 {$failCount} 个", [
                '赠送时间' => date('Y-m-d H:i:s', $currentTime),
                '备注' => $memo,
                '总数量' => count($usernameArray),
                '成功数量' => $successCount,
                '失败数量' => $failCount,
                '处理结果' => $results
            ]);
        } else {
            $this->error("批量赠送失败：全部 {$failCount} 个用户处理失败", [
                '处理结果' => $results
            ]);
        }
    }

    /**
     * 清除用户内需消费金及相关数据
     *
     * @ApiTitle    (清除用户内需消费金)
     * @ApiSummary  (根据用户ID清除该用户的内需消费补贴金、内需消费补贴金利息和所有存单记录)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/clearUserNxxfj)
     * @ApiParams   (name="userId", type="integer", required=true, description="用户ID")
     * @ApiParams   (name="memo", type="string", required=false, description="清除原因备注")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="清除成功")
     * @ApiReturnParams   (name="data", type="object", sample="{}", description="清除结果数据")
     */
    public function clearUserNxxfj()
    {
        // 获取参数
        $userId = $this->request->post('userId', 0, 'intval');
        $memo = $this->request->post('memo', '清除内需消费补贴金');
        
        // 参数验证
        if (empty($userId) || $userId <= 0) {
            $this->error('请提供有效的用户ID');
            return;
        }
        
        // 获取用户信息
        $user = \app\common\model\User::get($userId);
        if (!$user) {
            $this->error('用户不存在');
            return;
        }
        
        // 获取用户当前的内需消费补贴金和利息余额
        $currentNxxfj = $user->nxxfj;
        $currentNxxfjlx = $user->nxxfjlx;
        
        // 查询用户的有效存单数量
        $validDeposits = Db::name('nxxfjlist')->where([
            'user_id' => $userId,
            'status' => 1
        ])->count();
        
        // 使用Db::transaction()处理事务，与其他方法保持一致
        $result = Db::transaction(function() use ($userId, $currentNxxfj, $currentNxxfjlx, $validDeposits, $memo, $user) {
            // 1. 清零用户的内需消费补贴金
            if ($currentNxxfj > 0) {
                $updateResult = Db::name('user')->where('id', $userId)->update(['nxxfj' => 0]);
                if ($updateResult === false) {
                    throw new \Exception('更新内需消费补贴金失败');
                }
                
                // 记录日志
                $logResult = Db::name('user_nxxfjlog')->insert([
                    'user_id' => $userId,
                    'money' => -$currentNxxfj,
                    'before' => $currentNxxfj,
                    'after' => 0,
                    'memo' => $memo,
                    'createtime' => time()
                ]);
                if ($logResult === false) {
                    throw new \Exception('记录内需消费补贴金日志失败');
                }
            } else {
                // 即使没有内需消费补贴金，也记录清除操作
                $logResult = Db::name('user_nxxfjlog')->insert([
                    'user_id' => $userId,
                    'money' => 0,
                    'before' => 0,
                    'after' => 0,
                    'memo' => $memo,
                    'createtime' => time()
                ]);
                if ($logResult === false) {
                    throw new \Exception('记录内需消费补贴金日志失败');
                }
            }
            
            // 2. 清零用户的内需消费补贴金利息
            if ($currentNxxfjlx > 0) {
                $updateResult = Db::name('user')->where('id', $userId)->update(['nxxfjlx' => 0]);
                if ($updateResult === false) {
                    throw new \Exception('更新内需消费补贴金利息失败');
                }
                
                // 记录日志
                $logResult = Db::name('user_nxxfjlxlog')->insert([
                    'user_id' => $userId,
                    'money' => -$currentNxxfjlx,
                    'before' => $currentNxxfjlx,
                    'after' => 0,
                    'memo' => $memo,
                    'createtime' => time()
                ]);
                if ($logResult === false) {
                    throw new \Exception('记录内需消费补贴金利息日志失败');
                }
            } else {
                // 即使没有利息，也记录清除操作
                $logResult = Db::name('user_nxxfjlxlog')->insert([
                    'user_id' => $userId,
                    'money' => 0,
                    'before' => 0,
                    'after' => 0,
                    'memo' => $memo,
                    'createtime' => time()
                ]);
                if ($logResult === false) {
                    throw new \Exception('记录内需消费补贴金利息日志失败');
                }
            }
            
            // 3. 删除所有有效的存单记录
            if ($validDeposits > 0) {
                $deleteResult = Db::name('nxxfjlist')->where([
                    'user_id' => $userId,
                    'status' => 1
                ])->delete();
                if ($deleteResult === false) {
                    throw new \Exception('删除存单记录失败');
                }
            }
            
            return [
                '用户ID' => $userId,
                '用户名' => $user->username,
                '原内需消费补贴金' => $currentNxxfj,
                '原内需消费补贴金利息' => $currentNxxfjlx,
                '删除存单数量' => $validDeposits,
                '清除原因' => $memo,
                '操作时间' => date('Y-m-d H:i:s')
            ];
        });
        
        if ($result !== false) {
            $this->success('清除成功', $result);
        } else {
            $this->error('清除失败');
        }
    }

    /**
     * 检测8月14日注册账号并自动赠送补贴金
     *
     * @ApiTitle    (检测8月14日注册用户并赠送补贴金)
     * @ApiSummary  (检测2025年8月14日注册的账号，如果有激活的自动赠送15000补贴金，推荐人赠送5000补贴金)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/demo/checkAugust14Users)
     * @ApiParams   (name="dryRun", type="integer", required=false, description="试运行模式，1为是，0为否，默认为1")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="1")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="检测完成")
     * @ApiReturnParams   (name="data", type="object", description="检测结果数据")
     */
    public function checkAugust14Users()
    {
        $dryRun = $this->request->post('dryRun', 1); // 默认试运行模式

        // 定义8月14日的时间范围（精确到分钟）
        $august14Start = strtotime('2025-08-14 00:00:00');
        $august14End = strtotime('2025-08-14 14:48:00');

        // 查询8月14日注册的用户
        $august14Users = Db::name('user')
            ->where('createtime', 'between', [$august14Start, $august14End])
            ->field('id, username, nickname, upid, createtime, is_buy, buy_time')
            ->select();

        if (empty($august14Users)) {
            $this->success('检测完成', [
                '检测时间' => date('Y-m-d H:i:s'),
                '检测日期' => '2025-08-14 00:00:00 - 14:48:00',
                '注册用户数量' => 0,
                '激活用户数量' => 0,
                '赠送记录' => [],
                '备注' => '8月14日00:00:00-14:48:00期间没有用户注册'
            ]);
            return;
        }

        $totalUsers = count($august14Users);
        $activatedUsers = [];
        $nonActivatedUsers = [];
        $rewardRecords = [];

        // 分类用户
        foreach ($august14Users as $user) {
            if ($user['is_buy'] == 1) {
                $activatedUsers[] = $user;
            } else {
                $nonActivatedUsers[] = $user;
            }
        }

        $activatedCount = count($activatedUsers);

        // 如果是试运行模式，只返回检测结果
        if ($dryRun) {
            $this->success('试运行检测完成', [
                '检测时间' => date('Y-m-d H:i:s'),
                '检测日期' => '2025-08-14 00:00:00 - 14:48:00',
                '注册用户数量' => $totalUsers,
                '激活用户数量' => $activatedCount,
                '未激活用户数量' => count($nonActivatedUsers),
                '激活用户详情' => $activatedUsers,
                '未激活用户详情' => $nonActivatedUsers,
                '备注' => '试运行模式，未实际赠送补贴金。如需实际赠送，请设置dryRun=0'
            ]);
            return;
        }

        // 实际执行赠送逻辑
        $successCount = 0;
        $failCount = 0;

        foreach ($activatedUsers as $user) {
            try {
                Db::startTrans();

                $userId = $user['id'];
                $upid = $user['upid'];

                // 1. 给激活用户赠送15000补贴金
                $activationAmount = 15000;
                $beforeNxxfj = Db::name('user')->where('id', $userId)->value('nxxfj') ?: 0;
                $afterNxxfj = bcadd($beforeNxxfj, $activationAmount, 2);

                // 更新用户补贴金
                Db::name('user')->where('id', $userId)->update(['nxxfj' => $afterNxxfj]);

                // 记录补贴金日志
                Db::name('user_nxxfjlog')->insert([
                    'user_id' => $userId,
                    'money' => $activationAmount,
                    'before' => $beforeNxxfj,
                    'after' => $afterNxxfj,
                    'memo' => '提振投资消费首次激活内需消费补贴金',
                    'createtime' => time()
                ]);

                // 创建补贴金存单记录
                $year = date('Y');
                $annualization = config('site.annualization');
                $dailyInterest = $this->getinterest($year, $activationAmount, $annualization[1]);
                $expirytime = strtotime(date('Y-12-31 23:59:59'));

                Db::name('nxxfjlist')->insert([
                    'user_id' => $userId,
                    'money' => $activationAmount,
                    'interest' => $dailyInterest,
                    'yeartime' => 1,
                    'year_rate' => $annualization[1],
                    'expirytime' => $expirytime,
                    'status' => 1,
                    'createtime' => time(),
                    'updatetime' => time()
                ]);

                // 2. 给推荐人赠送5000补贴金（如果存在推荐人）
                if ($upid > 0) {
                    $referralAmount = 5000;
                    $beforeUpNxxfj = Db::name('user')->where('id', $upid)->value('nxxfj') ?: 0;
                    $afterUpNxxfj = bcadd($beforeUpNxxfj, $referralAmount, 2);

                    // 更新推荐人补贴金
                    Db::name('user')->where('id', $upid)->update(['nxxfj' => $afterUpNxxfj]);

                    // 记录推荐人补贴金日志
                    Db::name('user_nxxfjlog')->insert([
                        'user_id' => $upid,
                        'money' => $referralAmount,
                        'before' => $beforeUpNxxfj,
                        'after' => $afterUpNxxfj,
                        'memo' => '提振投资消费下级激活内需消费补贴金',
                        'createtime' => time()
                    ]);

                    // 创建推荐人补贴金存单记录
                    $upDailyInterest = $this->getinterest($year, $referralAmount, $annualization[1]);

                    Db::name('nxxfjlist')->insert([
                        'user_id' => $upid,
                        'money' => $referralAmount,
                        'interest' => $upDailyInterest,
                        'yeartime' => 1,
                        'year_rate' => $annualization[1],
                        'expirytime' => $expirytime,
                        'status' => 1,
                        'createtime' => time(),
                        'updatetime' => time()
                    ]);

                    $rewardRecords[] = [
                        '用户ID' => $userId,
                        '用户名' => $user['username'],
                        '用户昵称' => $user['nickname'],
                        '注册时间' => date('Y-m-d H:i:s', $user['createtime']),
                        '激活时间' => date('Y-m-d H:i:s', $user['buy_time']),
                        '推荐人ID' => $upid,
                        '赠送状态' => '成功',
                        '激活用户补贴金' => $activationAmount,
                        '推荐人补贴金' => $referralAmount,
                        '备注' => '8月14日注册激活奖励'
                    ];
                } else {
                    $rewardRecords[] = [
                        '用户ID' => $userId,
                        '用户名' => $user['username'],
                        '用户昵称' => $user['nickname'],
                        '注册时间' => date('Y-m-d H:i:s', $user['createtime']),
                        '激活时间' => date('Y-m-d H:i:s', $user['buy_time']),
                        '推荐人ID' => '无',
                        '赠送状态' => '成功',
                        '激活用户补贴金' => $activationAmount,
                        '推荐人补贴金' => 0,
                        '备注' => '8月14日注册激活奖励（无推荐人）'
                    ];
                }

                Db::commit();
                $successCount++;

            } catch (\Exception $e) {
                Db::rollback();
                $failCount++;

                $rewardRecords[] = [
                    '用户ID' => $user['id'],
                    '用户名' => $user['username'],
                    '用户昵称' => $user['nickname'],
                    '注册时间' => date('Y-m-d H:i:s', $user['createtime']),
                    '激活时间' => date('Y-m-d H:i:s', $user['buy_time']),
                    '推荐人ID' => $user['upid'] ?: '无',
                    '赠送状态' => '失败',
                    '激活用户补贴金' => 0,
                    '推荐人补贴金' => 0,
                    '备注' => '赠送失败：' . $e->getMessage()
                ];
            }
        }

        // 返回结果
        $this->success('检测完成', [
            '检测时间' => date('Y-m-d H:i:s'),
            '检测日期' => '2025-08-14 00:00:00 - 14:48:00',
            '注册用户数量' => $totalUsers,
            '激活用户数量' => $activatedCount,
            '未激活用户数量' => count($nonActivatedUsers),
            '赠送成功数量' => $successCount,
            '赠送失败数量' => $failCount,
            '赠送记录' => $rewardRecords,
            '备注' => '已为激活用户赠送15000补贴金，为推荐人赠送5000补贴金'
        ]);
    }
}

