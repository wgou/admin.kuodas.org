<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class DreamFunding extends Api
{
    protected $model = null;
    protected $noNeedRight = '*';
    protected $noNeedLogin = ['index', 'projects', 'detail', 'submit', 'checksubmitted', 'inviteStats', 'manualreward'];
    protected $_allow_func = ['index', 'projects', 'detail', 'submit', 'checksubmitted', 'inviteStats', 'manualreward'];
    protected $_search_field = ['name', 'status'];

    use \app\api\library\buiapi\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\DreamFunding;
    }

    /**
     * 获取项目列表
     */
    public function index()
    {
        $projectModel = new \app\admin\model\dreamfunding\Project;
        $list = $projectModel
            ->where('status', 1)
            ->field('id,name,amount_options,status,createtime,updatetime')
            ->select();
            
        foreach ($list as &$item) {
            if (isset($item['amount_options']) && is_string($item['amount_options'])) {
                $item['amount_options'] = json_decode($item['amount_options'], true);
            }
        }
        
        $this->success('获取成功', $list);
    }

    /**
     * 获取项目列表（兼容旧接口）
     */
    public function projects()
    {
        return $this->index();
    }

    /**
     * 提交投资信息
     * @ApiMethod   (POST)
     * @param string $name 姓名
     * @param string $phone 手机号
     * @param string $id_card 身份证号
     * @param integer $project_id 项目ID
     * @param decimal $amount 投资金额
     */
    public function submit()
    {
        $now = time();
        // 验证活动时间
        if ($now < strtotime('2025-04-08 00:00:00') || $now > strtotime('2025-10-09 23:59:59')) {
            $this->error('活动已结束');
        }
        $user_id=$this->auth->id;
        $redis = new \think\cache\driver\Redis(['length' => 3]);
        if (!$redis->handler()->setnx('submit'.$user_id, 1)) {
            // code...
            $this->error('操作频繁');
        }else{
            $redis->handler()->expire('submit'.$user_id, 3);
        }
        // 获取所有提交的数据
        $params = $this->request->post();
        if ($this->auth->is_submit<=0) {
            $this->error('请完成邀请任务后获得再次提交机会！');
        }
        // 验证必填字段
        $required_fields = ['real_name', 'name', 'phone', 'id_card', 'project_id', 'project_name', 'amount', 'invest_time'];
        foreach ($required_fields as $field) {
            if (!isset($params[$field]) || $params[$field] === '') {
                $this->error($field . '不能为空');
            }
        }
        
        // 添加必要字段
        $params['user_id'] = $this->auth->id;
        $params['createtime'] = $now;
        $params['updatetime'] = $now;
        
        Db::startTrans();
        try {
            // 插入记录
            Db::name('dream_funding_record')->insert($params);
            Db::name('user')->where(['id'=>$user_id])->setDec('is_submit',1);
            
            // 获取当前用户的信息
            $current_user = Db::name('user')->where('id', $this->auth->id)->find();
            
            // 检查：1.有上级 2.活动期间注册 3.现在是活动期间
            // if ($current_user && $current_user['upid'] ) {
            if ($current_user 
                && $current_user['upid'] 
                && $current_user['createtime'] >= strtotime('2025-04-08 00:00:00') 
                && $current_user['createtime'] <= strtotime('2025-08-09 23:59:59')
                && $now >= strtotime('2025-04-08 00:00:00')
                && $now <= strtotime('2025-08-09 23:59:59')) {
                
                // 检查是否已经提交过表单
                $previous_submit = Db::name('dream_funding_record')
                    ->where('user_id', $this->auth->id)
                    ->where('createtime', '<', $now)
                    ->find();
                
                // 只有第一次提交才增加邀请人的有效邀请数
                if (!$previous_submit) {
                    // 增加邀请人的有效邀请数
                    Db::name('user')->where('id', $current_user['upid'])->setInc('valid_invite_count', 1);
                    
                    // 获取更新后的邀请人信息
                    $inviter = Db::name('user')->where('id', $current_user['upid'])->find();
                    
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
                    
                    // 找到邀请人达到的最高奖励等级
                    foreach ($reward_levels as $level) {
                        if ($inviter['valid_invite_count'] >= $level) {
                            $achieved_level = $level;
                            $display_count = isset($display_counts[$level]) ? $display_counts[$level] : $level;
                        }
                    }
                    
                    if ($achieved_level) {
                        Db::name('user')->where(['id'=>$current_user['upid']])->setInc('is_submit',1);
                        
                        // 检查是否已经发放过这个等级的奖励
                        $reward_log = Db::name('user_money_log')
                            ->where('user_id', $current_user['upid'])
                            ->where('memo', 'like', '%邀请'.$display_count.'人完成0元购活动奖励%')
                            ->find();

                        if (!$reward_log) {
                            $reward = $rewards[$achieved_level];
                            
                            // 发放消费券
                            $before_score = $inviter['score'];
                            Db::name('user')->where('id', $current_user['upid'])->setInc('score', $reward['coupon']);
                            // 记录消费券日志
                            Db::name('user_money_log')->insert([
                                'user_id' => $current_user['upid'],
                                'money' => $reward['coupon'],
                                'before' => $before_score,
                                'after' => $before_score + $reward['coupon'],
                                'memo' => '邀请'.$display_count.'人完成0元购活动奖励-消费券',
                                'createtime' => $now,
                                'type' => 'coupon'
                            ]);

                            // 发放内需券
                            $before_neixu = $inviter['neixuquan'];
                            Db::name('user')->where('id', $current_user['upid'])->setInc('neixuquan', $reward['inner']);
                            // 记录内需券日志
                            Db::name('user_money_log')->insert([
                                'user_id' => $current_user['upid'],
                                'money' => $reward['inner'],
                                'before' => $before_neixu,
                                'after' => $before_neixu + $reward['inner'],
                                'memo' => '邀请'.$display_count.'人完成0元购活动奖励-内需券',
                                'createtime' => $now,
                                'type' => 'neixuquan'
                            ]);
                        }
                    }
                }
            }
            
            Db::commit();
            $this->success('提交成功');
            
        } catch (\Exception $e) {
            Db::rollback();
            \think\Log::write('投资提交异常: ' . $e->getMessage() . ' SQL: ' . Db::getLastSql(), 'error');
            $this->error('提交失败');
        }
    }

    /**
     * 
     */
    public function detail()
    {
        // 
    }

    /**
     * 检查用户是否已提交过表单
     * @ApiMethod   (POST)
     * @param integer $project_id 项目ID (可选)
     * @return      {"code":1,"msg":"成功","time":"1626337785","data":{"submitted":true/false,"record":{}}}
     */
    public function checksubmitted()
    {
        $project_id = $this->request->post('project_id', 0);
        $user_id = $this->auth->id;
        
        // 构建查询条件
        $where = ['user_id' => $user_id];
        if ($project_id > 0) {
            $where['project_id'] = $project_id;
        }
        
        // 查询是否存在记录
        $record = Db::name('dream_funding_record')->where($where)->find();
        $submitted = !empty($record);
        
        // 构建返回数据
        $data = [
            'submitted' => $submitted
        ];
        
        // 如果存在记录，则一并返回记录数据
        if ($submitted) {
            // 如果需要关联查询项目名称
            if (!empty($record['project_id'])) {
                $project = Db::name('dream_funding_project')
                    ->where('id', $record['project_id'])
                    ->field('id,name')
                    ->find();
                if ($project) {
                    $record['project_name'] = $project['name'];
                }
            }
            
            // 格式化时间戳
            if (!empty($record['createtime'])) {
                $record['createtime_text'] = date('Y-m-d H:i:s', $record['createtime']);
            }
            if (!empty($record['updatetime'])) {
                $record['updatetime_text'] = date('Y-m-d H:i:s', $record['updatetime']);
            }
            // 处理invest_time字段，直接返回原始值而不做转换
            if (isset($record['invest_time'])) {
                // 不做任何格式化，直接设置原始值
                $record['invest_time_text'] = $record['invest_time'];
            }
            
            $data['record'] = $record;
        }
        
        $this->success('获取成功', $data);
    }

    /**
     * 获取邀请统计信息
     */
    public function inviteStats()
    {
        $user_id = $this->auth->id;
        
        // 获取用户信息
        $user = Db::name('user')->where('id', $user_id)->find();
        
        // 获取活动期间注册的新用户总数
        $total_invites = Db::name('user')
            ->where('upid', $user_id)
            ->where('createtime', '>=', strtotime('2025-03-01 00:00:00'))
            ->where('createtime', '<=', strtotime('2025-08-09 23:59:59'))
            ->count();
            
        // 获取已发放的奖励记录
        $rewards = Db::name('user_money_log')
            ->where('user_id', $user_id)
            ->where('memo', 'like', '%邀请%人完成0元购活动奖励%')
            ->select();
            
        // 计算下一个奖励目标
        $reward_levels = [5, 15, 35, 65];
        $next_target = null;
        foreach ($reward_levels as $level) {
            if ($user['valid_invite_count'] < $level) {
                $next_target = $level;
                break;
            }
        }
            
        $data = [
            'total_invites' => $total_invites,
            'success_invites' => $user['valid_invite_count'], // 这个是已经提交表单的有效邀请人数
            'rewards' => $rewards,
            'next_target' => $next_target,
            'remaining_invites' => $next_target ? ($next_target - $user['valid_invite_count']) : 0
        ];
        
        $this->success('获取成功', $data);
    }

    /**
     * 手动发放奖励
     * @ApiMethod (POST)
     * @param integer $user_id 用户ID
     */
    public function manualreward()
    {
        $user_id = $this->request->post('user_id');
        if (!$user_id) {
            $this->error('用户ID不能为空');
        }

        $now = time();
        
        // 获取用户信息
        $user = Db::name('user')->where('id', $user_id)->find();
        if (!$user) {
            $this->error('用户不存在');
        }

        Db::startTrans();
        try {
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
                if ($user['valid_invite_count'] >= $level) {
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
                    $reward = $rewards[$achieved_level];
                    
                    // 发放消费券
                    $before_score = $user['score'];
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
                    $before_neixu = $user['neixuquan'];
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
                    $this->success('奖励发放成功', [
                        '达到的奖励等级' => $achieved_level,
                        '显示人数' => $display_count,
                        '奖励详情' => [
                            '消费券' => $reward['coupon'],
                            '内需券' => $reward['inner']
                        ]
                    ]);
                } else {
                    $this->error('该用户已经领取过此等级奖励', [
                        '达到的奖励等级' => $achieved_level,
                        '显示人数' => $display_count
                    ]);
                }
            } else {
                $this->error('该用户邀请人数未达到任何奖励等级要求', [
                    '当前邀请人数' => $user['valid_invite_count'],
                    '奖励等级列表' => $reward_levels
                ]);
            }
        } catch (\Exception $e) {
            Db::rollback();
            \think\Log::write('手动发放奖励异常: ' . $e->getMessage(), 'error');
            $this->error('奖励发放失败');
        }
    }
}
