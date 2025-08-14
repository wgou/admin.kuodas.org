<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 充值记录控制器 - 简化版
 */
class Rechargelog extends Api
{
    // 权限设置
    protected $noNeedRight = '*';
    protected $noNeedLogin = '*';
    
    public function _initialize()
    {
        parent::_initialize();
        // 不使用模型，直接直接查询
    }

    /**
     * 获取用户充值历史记录
     */
    public function index()
    {
        try {
            // 获取用户ID
            $user_id = $this->auth->id;
            
            // 获取分页参数
            $page = intval($this->request->param('page', 1));
            $limit = intval($this->request->param('limit', 10));
            $offset = ($page - 1) * $limit;
            
            // 获取筛选参数
            $filter_str = $this->request->param('filter', '{}');
            $filter = json_decode($filter_str, true) ?: [];
            
            // 设置默认时间范围
            $startTime = isset($filter['startTime']) ? $filter['startTime'] : 0;
            $endTime = isset($filter['endTime']) ? $filter['endTime'] : time();
            $status = isset($filter['status']) ? $filter['status'] : null;
            
            // 使用原生SQL查询，更加精确控制
            $prefix = config('database.prefix');
            
            // 构建第一部分查询：用户充值记录
            $rechargeSql = "SELECT 
                r.id, 
                r.orderid as order_no, 
                r.num as amount, 
                r.status, 
                r.notice as remark, 
                r.addtime as create_time, 
                r.payment_id, 
                p.name as pay_method,
                1 as source_type
            FROM fa_recharge r
            LEFT JOIN fa_payment p ON r.payment_id = p.id
            WHERE r.user_id = {$user_id}
            AND r.addtime BETWEEN {$startTime} AND {$endTime}";
            
            // 如果有状态筛选
            if ($status !== null) {
                $rechargeSql .= " AND r.status = {$status}";
            }
            
            // 打印表前缀信息，便于调试
            $debug_prefix = $prefix; // 保存表前缀信息
            
            // 确保表前缀正确
            if (!in_array(substr($prefix, -1), ['_'])) {
                $prefix = $prefix . '_'; // 如果前缀没有下划线结尾，添加下划线
            }
            
            // 第二部分查询：查询管理员后台充值记录
            // 更改策略：不再使用排除法，而是直接匹配管理员充值的关键特征
            $logSql = "SELECT 
                l.id, 
                CONCAT('admin_', l.id) as order_no, 
                l.money as amount, 
                1 as status, 
                l.memo as remark, 
                l.createtime as create_time, 
                'admin' as payment_id, 
                '后台充值' as pay_method,
                2 as source_type
            FROM fa_user_money_log l
            WHERE l.user_id = {$user_id}
            AND l.type IS NULL  -- 根据用户的截图，管理员后台充值的type字段值为NULL
            AND l.money > 0
            AND l.createtime BETWEEN {$startTime} AND {$endTime}";
            
            // 合并两个查询
            $unionSql = "({$rechargeSql}) UNION ALL ({$logSql})";
            
            // 查询总记录数
            $countSql = "SELECT COUNT(*) as total FROM ({$unionSql}) as t";
            $totalResult = Db::query($countSql);
            $totalCount = $totalResult[0]['total'] ?? 0;
            
            // 获取分页数据
            $dataSql = "{$unionSql} ORDER BY create_time DESC LIMIT {$offset}, {$limit}";
            $records = Db::query($dataSql);
            
            // 处理数据
            foreach ($records as &$record) {
                $record['create_time'] = date('Y-m-d H:i:s', $record['create_time']);
                
                // 处理状态显示
                if ($record['source_type'] == 1) { // 用户自助充值
                    switch ($record['status']) {
                        case 0:
                            $record['status_text'] = '未付款';
                            break;
                        case 1:
                            $record['status_text'] = '充值成功';
                            break;
                        case 2:
                            $record['status_text'] = '处理中';
                            break;
                        default:
                            $record['status_text'] = '未知状态';
                    }
                } else { // 后台充值
                    $record['status_text'] = '充值成功';
                }
                
                // 为安全考虑，不返回付款方式ID
                unset($record['payment_id']);
            }
            
            // 返回结果
            $result = [
                'total' => $totalCount,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($totalCount / $limit),
                'data' => $records
            ];
            
            $this->success('获取充值记录成功', $result);
        } catch (Exception $e) {
            // 捕获异常
            $this->error('获取充值记录失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取充值记录详情
     */
    public function detail()
    {
        try {
            // 直接返回测试数据
            $detail = [
                'id' => 1,
                'order_no' => 'test-order',
                'amount' => 100,
                'status' => 1,
                'status_text' => '充值成功',
                'create_time' => date('Y-m-d H:i:s'),
                'remark' => '测试充值'
            ];
            
            $this->success('获取充值记录详情成功', $detail);
        } catch (Exception $e) {
            $this->error('获取充值记录详情失败: ' . $e->getMessage());
        }
    }
}