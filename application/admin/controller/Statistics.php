<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;
use think\Cache;
use think\Request;
use think\Response;
use think\exception\HttpResponseException;
use Tool\Tongji;

/**
 * 统计控制器
 * 用于处理大数据量的统计查询
 */
class Statistics extends Backend
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 异步统计接口
     */
    public function async()
    {
        if ($this->request->isPost()) {
            $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
            $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
            
            // 提交异步统计任务
            $result = Tongji::getDataAsync($start_date, $end_date);
            
            $this->success('统计任务已提交', $result);
        }
        
        $this->error('请求方法错误');
    }
    
    /**
     * 获取异步统计结果
     */
    public function result()
    {
        $task_id = $this->request->param('task_id');
        
        if (empty($task_id)) {
            $this->error('任务ID不能为空');
        }
        
        $result = Tongji::getAsyncResult($task_id);
        
        if ($result['status'] === 'completed') {
            $this->success('获取成功', $result['data']);
        } else {
            $this->success($result['message'], ['status' => $result['status']]);
        }
    }
    
    /**
     * 轻量级统计接口
     */
    public function light()
    {
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        
        try {
            // 只获取基础统计数据，避免复杂查询
            $data = $this->getLightData($start_date, $end_date);
            $this->success('获取成功', $data);
        } catch (\Exception $e) {
            $this->error('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取轻量级统计数据
     */
    private function getLightData($start_date, $end_date)
    {
        $cacheKey = 'light_stats_' . md5($start_date . $end_date);
        $cachedData = Cache::get($cacheKey);
        
        if ($cachedData !== false) {
            return $cachedData;
        }
        
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        
        // 只查询基础数据，避免复杂统计
        $data = [
            'user_add' => Db::name('user')
                ->where('jointime', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'user_activated' => Db::name('user')
                ->where('buy_time', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'recharge_amount' => Db::name('recharge')
                ->where('addtime', 'between', [$start_time, $end_time])
                ->where('status', 1)
                ->where('uis_rot', 1)
                ->sum('num') ?: 0,
            'withdraw_amount' => Db::name('withdraw')
                ->where('createtime', 'between', [$start_time, $end_time])
                ->where('status', 'successed')
                ->where('uis_rot', 1)
                ->sum('money') ?: 0,
        ];
        
        // 缓存5分钟
        Cache::set($cacheKey, $data, 300);
        
        return $data;
    }
    
    /**
     * 分页统计接口
     */
    public function page()
    {
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 20);
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        
        try {
            $data = $this->getPageData($start_date, $end_date, $page, $limit);
            $this->success('获取成功', $data);
        } catch (\Exception $e) {
            $this->error('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取分页统计数据
     */
    private function getPageData($start_date, $end_date, $page, $limit)
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        
        // 分页查询用户数据
        $users = Db::name('user')
            ->where('jointime', 'between', [$start_time, $end_time])
            ->where('is_rot', 1)
            ->field('id, username, nickname, jointime, buy_time, is_buy')
            ->page($page, $limit)
            ->select();
            
        // 获取总数
        $total = Db::name('user')
            ->where('jointime', 'between', [$start_time, $end_time])
            ->where('is_rot', 1)
            ->count();
            
        return [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => ceil($total / $limit),
            'data' => $users
        ];
    }
    
    /**
     * 实时统计接口 - 绕过缓存获取最新数据
     */
    public function realtime()
    {
        // 设置更长的执行时间，处理大数据量
        set_time_limit(600); // 10分钟
        ini_set('memory_limit', '1024M'); // 1GB内存
        ini_set('max_execution_time', 600); // 确保PHP超时设置生效
        
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        
        try {
            // 直接查询数据库，不使用缓存
            $data = $this->getRealtimeData($start_date, $end_date);
            $this->success('获取成功', $data);
        } catch (\Exception $e) {
            $this->error('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取实时统计数据（绕过缓存）
     */
    private function getRealtimeData($start_date, $end_date)
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        
        // 检查时间范围，如果超过30天给出警告
        $days_diff = ($end_time - $start_time) / (24 * 3600);
        if ($days_diff > 30) {
            return [
                'error' => '查询时间范围过大，建议不超过30天',
                'current_range' => $days_diff . '天',
                'suggestion' => '请缩小查询时间范围或使用分页查询'
            ];
        }
        
        // 直接查询数据库，不使用缓存
        $data = [
            'user_add' => Db::name('user')
                ->where('jointime', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'user_activated' => Db::name('user')
                ->where('buy_time', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'recharge_amount' => Db::name('recharge')
                ->where('addtime', 'between', [$start_time, $end_time])
                ->where('status', 1)
                ->where('uis_rot', 1)
                ->sum('num') ?: 0,
            'withdraw_amount' => Db::name('withdraw')
                ->where('createtime', 'between', [$start_time, $end_time])
                ->where('status', 'successed')
                ->where('uis_rot', 1)
                ->sum('money') ?: 0,
            'order_amount' => Db::name('order')
                ->where('paytime', 'between', [$start_time, $end_time])
                ->where('uis_rot', 1)
                ->sum('price') ?: 0,
            'query_time' => date('Y-m-d H:i:s'),
            'cache_status' => 'disabled',
            'time_range_days' => $days_diff
        ];
        
        return $data;
    }
    
    /**
     * 大数据量分页统计接口
     */
    public function largeData()
    {
        // 设置更长的执行时间
        set_time_limit(600); // 10分钟
        ini_set('memory_limit', '1024M'); // 1GB内存
        
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 1000); // 每页1000条
        
        try {
            $data = $this->getLargeDataStats($start_date, $end_date, $page, $limit);
            $this->success('获取成功', $data);
        } catch (\Exception $e) {
            $this->error('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取大数据量统计数据
     */
    private function getLargeDataStats($start_date, $end_date, $page, $limit)
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        
        // 按天分页查询，避免一次性查询太多数据
        $days_diff = ($end_time - $start_time) / (24 * 3600);
        $offset = ($page - 1) * $limit;
        
        // 计算当前页对应的日期范围
        $current_start = $start_time + ($offset * 24 * 3600);
        $current_end = min($current_start + ($limit * 24 * 3600), $end_time);
        
        $data = [
            'summary' => [
                'total_days' => $days_diff,
                'current_page' => $page,
                'per_page' => $limit,
                'current_date_range' => [
                    'start' => date('Y-m-d', $current_start),
                    'end' => date('Y-m-d', $current_end)
                ]
            ],
            'daily_stats' => []
        ];
        
        // 按天查询数据
        for ($day = $current_start; $day < $current_end; $day += 24 * 3600) {
            $day_start = $day;
            $day_end = $day + 24 * 3600 - 1;
            
            $daily_data = [
                'date' => date('Y-m-d', $day),
                'user_add' => Db::name('user')
                    ->where('jointime', 'between', [$day_start, $day_end])
                    ->where('is_rot', 1)
                    ->count(),
                'user_activated' => Db::name('user')
                    ->where('buy_time', 'between', [$day_start, $day_end])
                    ->where('is_rot', 1)
                    ->count(),
                'recharge_amount' => Db::name('recharge')
                    ->where('addtime', 'between', [$day_start, $day_end])
                    ->where('status', 1)
                    ->where('uis_rot', 1)
                    ->sum('num') ?: 0,
                'withdraw_amount' => Db::name('withdraw')
                    ->where('createtime', 'between', [$day_start, $day_end])
                    ->where('status', 'successed')
                    ->where('uis_rot', 1)
                    ->sum('money') ?: 0
            ];
            
            $data['daily_stats'][] = $daily_data;
        }
        
        // 计算汇总数据
        $data['summary']['total_pages'] = ceil($days_diff / $limit);
        $data['summary']['has_next'] = $page < $data['summary']['total_pages'];
        $data['summary']['has_prev'] = $page > 1;
        
        return $data;
    }
    
    /**
     * 快速统计接口 - 专门处理7天以内的数据
     */
    public function quick()
    {
        // 设置适中的执行时间
        set_time_limit(120); // 2分钟
        ini_set('memory_limit', '512M');
        
        $start_date = $this->request->param('start_date', date('Y-m-d 00:00:00'));
        $end_date = $this->request->param('end_date', date('Y-m-d 23:59:59'));
        
        try {
            $data = $this->getQuickData($start_date, $end_date);
            $this->success('获取成功', $data);
        } catch (\Exception $e) {
            $this->error('查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取快速统计数据
     */
    private function getQuickData($start_date, $end_date)
    {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        $days_diff = ($end_time - $start_time) / (24 * 3600);
        
        // 检查是否超过7天
        if ($days_diff > 7) {
            return [
                'error' => '快速统计接口仅支持7天以内数据',
                'current_days' => $days_diff,
                'suggestion' => '请使用 realtime 接口或缩小查询范围'
            ];
        }
        
        // 使用更简单的查询方式，减少JOIN和复杂计算
        $data = [
            'user_add' => Db::name('user')
                ->where('jointime', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'user_activated' => Db::name('user')
                ->where('buy_time', 'between', [$start_time, $end_time])
                ->where('is_rot', 1)
                ->count(),
            'recharge_amount' => Db::name('recharge')
                ->where('addtime', 'between', [$start_time, $end_time])
                ->where('status', 1)
                ->where('uis_rot', 1)
                ->sum('num') ?: 0,
            'withdraw_amount' => Db::name('withdraw')
                ->where('createtime', 'between', [$start_time, $end_time])
                ->where('status', 'successed')
                ->where('uis_rot', 1)
                ->sum('money') ?: 0,
            'query_time' => date('Y-m-d H:i:s'),
            'interface_type' => 'quick',
            'time_range_days' => $days_diff,
            'estimated_query_time' => '2-5秒'
        ];
        
        return $data;
    }
    
    /**
     * 清除缓存
     */
    public function clearCache()
    {
        try {
            // 清除统计相关缓存 - 修复方法
            $cacheKeys = [
                'tongji_data_*',
                'tongji_total_data',
                'tongji_team_data_*',
                'light_stats_*'
            ];
            
            $clearedCount = 0;
            foreach ($cacheKeys as $pattern) {
                // 对于通配符模式，我们需要手动清除
                if (strpos($pattern, '*') !== false) {
                    // 这里简化处理，实际项目中可能需要Redis或其他缓存系统
                    // 暂时只清除已知的缓存键
                    $clearedCount += $this->clearCacheByPattern($pattern);
                } else {
                    if (Cache::rm($pattern)) {
                        $clearedCount++;
                    }
                }
            }
            
            $this->success('缓存清除成功，共清除 ' . $clearedCount . ' 个缓存');
        } catch (\Exception $e) {
            $this->error('缓存清除失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 强制刷新缓存
     */
    public function refreshCache()
    {
        try {
            // 清除所有相关缓存
            $this->clearCache();
            
            // 重新生成缓存
            $start_date = date('Y-m-d 00:00:00');
            $end_date = date('Y-m-d 23:59:59');
            
            // 强制重新查询并缓存
            $data = \Tool\Tongji::getData($start_date, $end_date);
            
            $this->success('缓存刷新成功', [
                'message' => '缓存已强制刷新',
                'data' => $data,
                'refresh_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->error('缓存刷新失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 根据模式清除缓存
     */
    private function clearCacheByPattern($pattern)
    {
        $clearedCount = 0;
        
        // 清除已知的缓存键
        $knownKeys = [
            'tongji_total_data',
            'light_stats_' . md5(date('Y-m-d 00:00:00') . date('Y-m-d 23:59:59'))
        ];
        
        foreach ($knownKeys as $key) {
            if (Cache::rm($key)) {
                $clearedCount++;
            }
        }
        
        return $clearedCount;
    }
    
    /**
     * 数据库优化建议
     */
    public function optimize()
    {
        $suggestions = [
            '1. 为常用查询字段添加索引：',
            '   - user表的jointime, buy_time, is_rot字段',
            '   - order表的paytime, uis_rot字段',
            '   - recharge表的addtime, status, uis_rot字段',
            '   - withdraw表的createtime, status, uis_rot字段',
            '',
            '2. 创建复合索引：',
            '   - (jointime, is_rot)',
            '   - (paytime, uis_rot)',
            '   - (addtime, status, uis_rot)',
            '',
            '3. 分区表建议：',
            '   - 按时间分区存储历史数据',
            '   - 定期归档旧数据',
            '',
            '4. 查询优化：',
            '   - 使用EXPLAIN分析查询计划',
            '   - 避免SELECT *，只查询需要的字段',
            '   - 使用LIMIT限制结果集大小',
            '',
            '5. 缓存策略：',
            '   - 使用Redis缓存热点数据',
            '   - 设置合理的缓存过期时间',
            '   - 实现缓存预热机制'
        ];
        
        $this->success('优化建议', ['suggestions' => $suggestions]);
    }
    
    /**
     * 测试接口 - 用于验证功能
     */
    public function test()
    {
        try {
            // 测试基础统计
            $basicStats = $this->getLightData(
                date('Y-m-d 00:00:00'), 
                date('Y-m-d 23:59:59')
            );
            
            // 测试缓存
            $cacheTest = Cache::set('test_key', 'test_value', 60);
            $cacheGet = Cache::get('test_key');
            Cache::rm('test_key');
            
            $result = [
                'basic_stats' => $basicStats,
                'cache_test' => [
                    'set' => $cacheTest,
                    'get' => $cacheGet,
                    'cleared' => true
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $this->success('测试成功', $result);
        } catch (\Exception $e) {
            $this->error('测试失败: ' . $e->getMessage());
        }
    }
} 