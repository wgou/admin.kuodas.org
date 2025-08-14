<?php

namespace Tool;

use app\api\model\Leesign;
use app\api\model\Loginlog;
use app\api\model\Order;
use app\api\model\User;
use think\Db;
use think\Cache;

class Tongji
{

    public static function getDate()
    {
        $date = date('Y-m-d');
        if (!\app\common\model\Tongji::where('date', $date)->find()) {
            \app\common\model\Tongji::create(['date' => $date]);
        }
        return $date;
    }

    //根据时间获取数据 - 优化版本
    public static function getData($strtime, $endtime)
    {
        // 设置更长的执行时间和内存限制
        set_time_limit(600); // 10分钟
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 600);
        
        // 生成缓存键
        $cacheKey = 'tongji_data_' . md5($strtime . $endtime);
        
        // 尝试从缓存获取数据
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        $strtime1 = explode(' ', $strtime)[0];
        $endtime1 = explode(' ', $endtime)[0];
        
        // 检查时间范围
        $start_time = strtotime($strtime);
        $end_time = strtotime($endtime);
        $days_diff = ($end_time - $start_time) / (24 * 3600);
        
        // 如果时间范围超过30天，给出警告
        if ($days_diff > 30) {
            return [
                'warning' => '查询时间范围过大（' . $days_diff . '天），建议使用分页查询',
                'suggestion' => '请使用 /admin/statistics/largeData 接口进行大数据量查询',
                'time_range_days' => $days_diff
            ];
        }
        
        // 设置查询超时时间
        try {
            Db::execute("SET SESSION wait_timeout=600");
            Db::execute("SET SESSION interactive_timeout=600");
        } catch (\Exception $e) {
            // 忽略设置超时时间的错误
        }
        
        try {
            // 分批处理大数据量查询
            $data = self::getDataBatch($strtime, $endtime);
            
            // 缓存结果，设置1分钟过期（更实时）
            Cache::set($cacheKey, $data, 60);
            
            return $data;
        } catch (\Exception $e) {
            // 记录错误日志
            \think\Log::error('统计查询失败: ' . $e->getMessage());
            
            // 返回空数据或默认数据
            return self::getDefaultData();
        }
    }
    
    /**
     * 快速获取数据 - 专门处理7天以内的数据
     */
    public static function getDataQuick($strtime, $endtime)
    {
        // 设置适中的执行时间
        set_time_limit(120); // 2分钟
        ini_set('memory_limit', '512M');
        
        // 生成缓存键
        $cacheKey = 'tongji_quick_' . md5($strtime . $endtime);
        
        // 尝试从缓存获取数据
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        // 检查时间范围
        $start_time = strtotime($strtime);
        $end_time = strtotime($endtime);
        $days_diff = ($end_time - $start_time) / (24 * 3600);
        
        // 快速模式仅支持7天以内
        if ($days_diff > 7) {
            return [
                'error' => '快速模式仅支持7天以内数据',
                'current_days' => $days_diff,
                'suggestion' => '请使用标准模式或缩小查询范围'
            ];
        }
        
        try {
            // 设置数据库超时
            Db::execute("SET SESSION wait_timeout=120");
            Db::execute("SET SESSION interactive_timeout=120");
        } catch (\Exception $e) {
            // 忽略设置超时时间的错误
        }
        
        try {
            // 使用简化的查询方式
            $data = self::getDataQuickBatch($strtime, $endtime);
            
            // 缓存结果，30秒过期
            Cache::set($cacheKey, $data, 30);
            
            return $data;
        } catch (\Exception $e) {
            \think\Log::error('快速统计查询失败: ' . $e->getMessage());
            return self::getDefaultData();
        }
    }
    
    /**
     * 快速数据查询批次处理
     */
    private static function getDataQuickBatch($strtime, $endtime)
    {
        $strtime1 = explode(' ', $strtime)[0];
        $endtime1 = explode(' ', $endtime)[0];
        
        // 使用更简单的查询，减少复杂计算
        $data = [
            'user_add' => self::safeCount('user', [
                'jointime' => ['between', [strtotime($strtime), strtotime($endtime)]],
                'is_rot' => 1
            ]),
            'user_activated' => self::safeCount('user', [
                'buy_time' => ['between', [strtotime($strtime), strtotime($endtime)]],
                'is_rot' => 1
            ]),
            'recharge_amount' => self::safeSum('recharge', 'num', [
                'addtime' => ['between', [strtotime($strtime), strtotime($endtime)]],
                'status' => 1,
                'uis_rot' => 1
            ]),
            'withdraw_amount' => self::safeSum('withdraw', 'money', [
                'createtime' => ['between', [strtotime($strtime), strtotime($endtime)]],
                'status' => 'successed',
                'uis_rot' => 1
            ]),
            'query_time' => date('Y-m-d H:i:s'),
            'mode' => 'quick',
            'cache_status' => 'enabled'
        ];
        
        return $data;
    }
    
    // 分批处理大数据量查询
    private static function getDataBatch($strtime, $endtime)
    {
        $data = [];
        
        try {
            // 1. 用户相关统计 - 使用索引优化
            $data['user_shouchong'] = self::safeCount(
                User::where(['buy_time'=>['between', [strtotime($strtime), strtotime($endtime)]],'is_rot'=>1])
            );
            
            $data['user_add'] = self::safeCount(
                User::where(['jointime'=> ['between', [strtotime($strtime), strtotime($endtime)]],'is_rot'=>1])
            );
            
            // 2. 订单统计 - 使用SUM优化
            $data['user_rengou'] = self::safeSum(
                Order::where('paytime', 'between', [strtotime($strtime), strtotime($endtime)])->where(['uis_rot'=>1]),
                'price'
            );
            
            // 3. 充值统计
            $data['chong_num'] = self::safeCount(
                \app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1])->where('addtime', 'between', [strtotime($strtime), strtotime($endtime)])
            );
            
            $data['chong_user'] = self::safeCount(
                \app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1])->where('addtime', 'between', [strtotime($strtime), strtotime($endtime)])->group('user_id')
            );
            
            $data['chong_money'] = self::safeSum(
                \app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1])->where('addtime', 'between', [strtotime($strtime), strtotime($endtime)]),
                'num'
            );
            
            // 4. 提现统计
            $data['tixian_num'] = self::safeCount(
                \app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1])->where('createtime', 'between', [strtotime($strtime), strtotime($endtime)])
            );
            
            $data['tixian_user'] = self::safeCount(
                \app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1])->where('createtime', 'between', [strtotime($strtime), strtotime($endtime)])->group('user_id')
            );
            
            $data['tixian_money'] = self::safeSum(
                \app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1])->where('createtime', 'between', [strtotime($strtime), strtotime($endtime)]),
                'money'
            );
            
            // 5. 签到统计
            $data['user_qiandao'] = self::safeCount(
                Leesign::Group('uid')->where(['uis_rot'=>1])->where('sign_time', 'between', [$strtime, $endtime])
            );
            
            // 6. 登录统计
            $data['user_login'] = self::safeGetValue(
                Db::name('newtongji')->where('createtime', 'between', [strtotime($strtime), strtotime($endtime)]),
                'login_num'
            );
            $data['user_login'] = $data['user_login'] ? $data['user_login'] : 0;
            
            // 7. 总计数据 - 使用缓存优化
            $data = array_merge($data, self::getTotalData());
            
            return $data;
        } catch (\Exception $e) {
            \think\Log::error('批量数据处理失败: ' . $e->getMessage());
            return self::getDefaultData();
        }
    }
    
    // 安全计数方法
    private static function safeCount($query)
    {
        try {
            return $query->count();
        } catch (\Exception $e) {
            \think\Log::error('计数查询失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    // 安全求和方法
    private static function safeSum($query, $field)
    {
        try {
            $result = $query->sum($field);
            return $result ? $result : 0;
        } catch (\Exception $e) {
            \think\Log::error('求和查询失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    // 安全获取值方法
    private static function safeGetValue($query, $field)
    {
        try {
            return $query->value($field);
        } catch (\Exception $e) {
            \think\Log::error('获取值查询失败: ' . $e->getMessage());
            return 0;
        }
    }
    
    // 获取总计数据 - 使用缓存
    private static function getTotalData()
    {
        $cacheKey = 'tongji_total_data';
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        try {
            $data = [
                'all_jihuo' => self::safeCount(Db::name('user')->where(['is_rot'=>1,'is_buy'=>1])),
                'all_chong_num' => self::safeCount(\app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1])),
                'all_chong_user' => self::safeCount(\app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1])->group('user_id')),
                'all_chong_money' => self::safeSum(\app\admin\model\user\Recharge::where('status',1)->where(['uis_rot'=>1]), 'num'),
                'all_tixian_num' => self::safeCount(\app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1])),
                'all_tixian_user' => self::safeCount(\app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1])->group('user_id')),
                'all_tixian_money' => self::safeSum(\app\admin\model\user\Withdraw::where('status','successed')->where(['uis_rot'=>1]), 'money'),
            ];
            
            // 缓存总计数据，设置2分钟过期（更实时）
            Cache::set($cacheKey, $data, 120);
            
            return $data;
        } catch (\Exception $e) {
            \think\Log::error('总计数据查询失败: ' . $e->getMessage());
            return [
                'all_jihuo' => 0,
                'all_chong_num' => 0,
                'all_chong_user' => 0,
                'all_chong_money' => 0,
                'all_tixian_num' => 0,
                'all_tixian_user' => 0,
                'all_tixian_money' => 0,
            ];
        }
    }
    
    // 获取默认数据
    private static function getDefaultData()
    {
        return [
            'user_shouchong' => 0,
            'user_add' => 0,
            'user_rengou' => 0,
            'chong_num' => 0,
            'chong_user' => 0,
            'chong_money' => 0,
            'tixian_num' => 0,
            'tixian_user' => 0,
            'tixian_money' => 0,
            'user_qiandao' => 0,
            'user_login' => 0,
            'all_jihuo' => 0,
            'all_chong_num' => 0,
            'all_chong_user' => 0,
            'all_chong_money' => 0,
            'all_tixian_num' => 0,
            'all_tixian_user' => 0,
            'all_tixian_money' => 0,
        ];
    }

    // 优化团队数据查询
    public static function getDataTeam($strtime, $endtime, $ids, $id=null)
    {
        // 生成缓存键
        $cacheKey = 'tongji_team_data_' . md5($strtime . $endtime . implode(',', $ids) . $id);
        
        // 尝试从缓存获取数据
        $cachedData = Cache::get($cacheKey);
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        $strtime1 = explode(' ', $strtime)[0];
        $endtime1 = explode(' ', $endtime)[0];
        
        $data = [];
        $data['all_chong_money'] = 0;
        
        try {
            $data['tixian_money'] = self::safeSum(
                \app\admin\model\user\Withdraw::where('createtime', 'between', [strtotime($strtime), strtotime($endtime)])->where(['uis_rot'=>1])->whereIn('user_id', $ids),
                'money'
            );
            
            $data['ctx_money'] = 0;//充提差
            
            $data['rengou_money'] = self::safeSum(
                \app\admin\model\Order::where('createtime', 'between', [strtotime($strtime), strtotime($endtime)])->whereIn('user_id', $ids)->where(['uis_rot'=>1])->where('status',2),
                'price'
            );
            
            $data['rengou_num'] = self::safeCount(
                User::where('upid', $id)->where('createtime', 'between', [strtotime($strtime), strtotime($endtime)])->where('is_buy',1)
            );
            
            $data['team_num'] = count($ids);
            
            // 缓存结果，设置5分钟过期
            Cache::set($cacheKey, $data, 300);
            
            return $data;
        } catch (\Exception $e) {
            \think\Log::error('团队统计查询失败: ' . $e->getMessage());
            return [
                'all_chong_money' => 0,
                'tixian_money' => 0,
                'ctx_money' => 0,
                'rengou_money' => 0,
                'rengou_num' => 0,
                'team_num' => count($ids),
            ];
        }
    }
    
    // 异步统计方法 - 用于大数据量
    public static function getDataAsync($strtime, $endtime)
    {
        // 生成任务ID
        $taskId = uniqid('tongji_');
        
        // 将任务加入队列
        $task = [
            'task_id' => $taskId,
            'strtime' => $strtime,
            'endtime' => $endtime,
            'status' => 'pending',
            'created_at' => time()
        ];
        
        // 这里可以集成队列系统，如Redis队列
        Cache::set('tongji_task_' . $taskId, $task, 3600);
        
        // 启动异步处理（简化版本）
        self::processAsyncTask($taskId, $strtime, $endtime);
        
        return [
            'task_id' => $taskId,
            'status' => 'pending',
            'message' => '统计任务已提交，请稍后查询结果'
        ];
    }
    
    // 处理异步任务
    private static function processAsyncTask($taskId, $strtime, $endtime)
    {
        try {
            // 更新任务状态为处理中
            $task = Cache::get('tongji_task_' . $taskId);
            $task['status'] = 'processing';
            Cache::set('tongji_task_' . $taskId, $task, 3600);
            
            // 执行统计查询
            $data = self::getData($strtime, $endtime);
            
            // 保存结果
            Cache::set('tongji_result_' . $taskId, $data, 3600);
            
            // 更新任务状态为完成
            $task['status'] = 'completed';
            $task['completed_at'] = time();
            Cache::set('tongji_task_' . $taskId, $task, 3600);
            
        } catch (\Exception $e) {
            // 更新任务状态为失败
            $task = Cache::get('tongji_task_' . $taskId);
            $task['status'] = 'failed';
            $task['error'] = $e->getMessage();
            Cache::set('tongji_task_' . $taskId, $task, 3600);
            
            \think\Log::error('异步任务处理失败: ' . $e->getMessage());
        }
    }
    
    // 获取异步任务结果
    public static function getAsyncResult($taskId)
    {
        $task = Cache::get('tongji_task_' . $taskId);
        if (!$task) {
            return ['status' => 'not_found', 'message' => '任务不存在'];
        }
        
        if ($task['status'] === 'completed') {
            $result = Cache::get('tongji_result_' . $taskId);
            return ['status' => 'completed', 'data' => $result];
        } elseif ($task['status'] === 'failed') {
            return ['status' => 'failed', 'message' => '任务处理失败: ' . ($task['error'] ?? '未知错误')];
        }
        
        return ['status' => $task['status'], 'message' => '任务处理中'];
    }
}
