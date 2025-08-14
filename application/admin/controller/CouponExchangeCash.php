<?php

namespace app\admin\controller;

use app\api\model\UserMoney;
use app\common\controller\Backend;
use think\Db;

/**
 * 内容管理
 *
 * @icon fa fa-circle-o
 */
class CouponExchangeCash extends Backend
{

    /**
     * Message模型对象
     * @var \app\admin\model\CouponExchangeCash
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\CouponExchangeCash;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("approveList", $this->model->approveList());
    }

    public function index()
    {

        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {

            $offset = $this->request->get("offset", 0);
            $limit = $this->request->get("limit", 0);
            $params = json_decode(input('filter'), true);
            $op = json_decode(input('op'), true);
            if (count($params) > 0) {
                $new_params = $new_op = [];
                foreach ($params as $key => $value) {
                    if ($key == 'nickname') {
                        $new_params['b.nickname'] = $value;
                        $new_op['b.nickname'] = $op[$key];
                    } else {
                        $new_params['c.' . $key] = $value;
                        $new_op['c.' . $key] = $op[$key];
                    }
                }
                $w = $this->rewriteQuery($new_params, $new_op);
            } else {
                $w['c.id'] = array('>', 0);
            }
            $list = Db::name('coupon_exchange_cash')
                ->alias('c')
                ->join('user b', 'c.user_id = b.id', 'left')
                ->where($w)
                ->field('c.*,b.nickname,b.id as user_id')
                ->order('c.id desc')
                ->limit($offset, $limit)
                ->select();
            $total = Db::name('coupon_exchange_cash')
                ->alias('c')
                ->join('user b', 'c.user_id = b.id', 'left')
                ->where($w)
                ->field('c.*,b.nickname,b.id as user_id')
                ->order('c.id desc')
                ->count();
            $list = $list;

            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $w1 = [];
            $w1['id'] = array("eq", $ids);
            $r1 = Db::name("coupon_exchange_cash")->where($w1)->find();
            if (!$r1) {
                $this->error("未查询到信息");
            }
            $user = Db::name("user")->where("id", $r1['user_id'])->find();
            //新的资金记录表
            if ($r1['status'] == 2 && $params['status'] == 1){
                UserMoney::money_in($user['id'], 'quanhuan', $params['ex_num'], '内需劵兑换现金');
            }

            Db::startTrans();
            try {
                if ($r1['status'] != 2) {
                    $this->error("已经处理过了");
                }
                if ($r1['status'] == 2 && $params['status'] == 1) {
                    //if ($r1['type'] == 1) {
                        Db::name("user")->where("id", $r1['user_id'])->setInc("money", $params['ex_num']);
                        $detailed_data[] = [
                            'user_id' => $user['id'],
                            'money' => $params['ex_num'],
                            'before' => $user['money'],
                            'after' => $user['money'] + $params['ex_num'],
                            'memo' => '内需劵兑换现金',
                            'createtime' => time(),
                            'type' => 'ex_coupon',
                        ];
                    //}
                }
                //提现失败
                if ($r1['status'] == 2 && $params['status'] == 0) {
                    //if ($r1['type'] == 1) {
                        Db::name("user")->where("id", $r1['user_id'])->setInc("neixuquan", $params['num']);
                        $before_num = $user['neixuquan'];
                        $after_num = $before_num + $params['num'];
                    //}
                    $detailed_data[] = [
                        'user_id' => $user['id'],
                        'money' => $params['num'],
                        'before' => $before_num,
                        'after' => $after_num,
                        'memo' => '内需劵兑换现金，金额返还',
                        'createtime' => time(),
                        'type' => 'ex_coupon',
                    ];
                }
                $res = Db::name("user_money_log")->insertAll($detailed_data);
                if (!$res) {
                    Db::rollback();
                    $this->error("添加失败");
                }

                $row->allowField(true)->save($params);
                Db::commit();
                $this->success("success");
            } catch (Exception $e) {
                Db::rollback();
                $this->error("修改失败");
            }
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    public function approve()
    {
        if (request()->isPost()) {
            $data = $this->request->post();
            $ids = $data['ids'];
            foreach ($ids as $key => $value) {
                $w1['id'] = array("eq", $value);
                $r1 = Db::name("coupon_exchange_cash")->where($w1)->find();
                $user = Db::name("user")->where("id", $r1['user_id'])->find();

                if ($r1['status'] == 2 && $data['status'] == 1) {
                    //if ($r1['type'] == 1) {
                        Db::name("user")->where("id", $r1['user_id'])->setInc("money", $r1['ex_num']);
                        $detailed_data = [
                            'user_id' => $user['id'],
                            'money' => $r1['ex_num'],
                            'before' => $user['money'],
                            'after' => $user['money'] + $r1['ex_num'],
                            'memo' => '内需劵兑换现金',
                            'createtime' => time(),
                            'type' => 'ex_coupon',
                        ];
                    //}
                    //新的资金记录表
                    UserMoney::money_in($user['id'], 'quanhuan', $r1['ex_num'], '内需劵兑换现金');
                    Db::name("user_money_log")->insert($detailed_data);
                    Db::name("coupon_exchange_cash")->where($w1)->update(['status' => $data['status']]);
                }
                if ($r1['status'] == 2 && $data['status'] == 0) {
                    //if ($r1['type'] == 1) {
                        Db::name("user")->where("id", $r1['user_id'])->setInc("neixuquan", $r1['num']);
                        $before_num = $user['neixuquan'];
                        $after_num = $before_num + $r1['num'];
                    //}
                    $detailed_data = [
                        'user_id' => $user['id'],
                        'money' => $r1['num'],
                        'before' => $before_num,
                        'after' => $after_num,
                        'memo' => '内需劵兑换现金，金额返还',
                        'createtime' => time(),
                        'type' => 'ex_coupon',
                    ];
                    Db::name("user_money_log")->insert($detailed_data);
                    Db::name("coupon_exchange_cash")->where($w1)->update(['status' => $data['status']]);
                }
            }
            $this->success('操作成功', request()->url());
        } else {
            return view();
        }
    }

    /**
     * 一键处理所有待审核的兑换申请
     */
    public function autoApproveAll()
    {
        if (request()->isPost()) {
            $data = $this->request->post();
            $status = $data['status'] ?? 1; // 默认通过
            $batch_size = $data['batch_size'] ?? 50; // 可配置的批次大小，默认50条
            $current_batch = $data['current_batch'] ?? 0; // 当前批次
            
            // 获取所有待审核的兑换申请总数
            $totalCount = Db::name("coupon_exchange_cash")
                ->where('status', 2) // 待审核状态
                ->count();
            
            if ($totalCount == 0) {
                $this->error('没有待审核的兑换申请');
            }
            
            // 分批获取数据
            $pendingRecords = Db::name("coupon_exchange_cash")
                ->where('status', 2) // 待审核状态
                ->limit($current_batch * $batch_size, $batch_size)
                ->select();
            
            if (empty($pendingRecords)) {
                $this->success("批量处理完成！所有批次已处理完毕");
                return;
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            Db::startTrans();
            try {
                foreach ($pendingRecords as $record) {
                    $user = Db::name("user")->where("id", $record['user_id'])->find();
                    
                    if ($status == 1) {
                        // 通过申请
                        Db::name("user")->where("id", $record['user_id'])->setInc("money", $record['ex_num']);
                        $detailed_data = [
                            'user_id' => $user['id'],
                            'money' => $record['ex_num'],
                            'before' => $user['money'],
                            'after' => $user['money'] + $record['ex_num'],
                            'memo' => '内需劵兑换现金',
                            'createtime' => time(),
                            'type' => 'ex_coupon',
                        ];
                        //新的资金记录表
                        UserMoney::money_in($user['id'], 'quanhuan', $record['ex_num'], '内需劵兑换现金');
                        Db::name("user_money_log")->insert($detailed_data);
                        Db::name("coupon_exchange_cash")->where('id', $record['id'])->update(['status' => $status]);
                        $successCount++;
                    } else {
                        // 驳回申请
                        Db::name("user")->where("id", $record['user_id'])->setInc("neixuquan", $record['num']);
                        $before_num = $user['neixuquan'];
                        $after_num = $before_num + $record['num'];
                        $detailed_data = [
                            'user_id' => $user['id'],
                            'money' => $record['num'],
                            'before' => $before_num,
                            'after' => $after_num,
                            'memo' => '内需劵兑换现金，金额返还',
                            'createtime' => time(),
                            'type' => 'ex_coupon',
                        ];
                        Db::name("user_money_log")->insert($detailed_data);
                        Db::name("coupon_exchange_cash")->where('id', $record['id'])->update(['status' => $status]);
                        $successCount++;
                    }
                }
                Db::commit();
                
                // 计算进度
                $processedCount = ($current_batch + 1) * $batch_size;
                $progress = min(100, round(($processedCount / $totalCount) * 100, 2));
                
                // 检查是否还有更多数据需要处理
                $remainingCount = $totalCount - $processedCount;
                $hasMore = $remainingCount > 0;
                
                $result = [
                    'code' => 1,
                    'msg' => "第" . ($current_batch + 1) . "批处理完成！本批成功处理 {$successCount} 条记录",
                    'data' => [
                        'current_batch' => $current_batch + 1,
                        'total_count' => $totalCount,
                        'processed_count' => $processedCount,
                        'remaining_count' => $remainingCount,
                        'progress' => $progress,
                        'has_more' => $hasMore,
                        'status' => $status,
                        'batch_size' => $batch_size
                    ]
                ];
                
                $this->success($result['msg'], null, $result['data']);
                
            } catch (Exception $e) {
                Db::rollback();
                $this->error("第" . ($current_batch + 1) . "批处理失败：" . $e->getMessage());
            }
        } else {
            return view();
        }
    }
    
    /**
     * 获取批量处理配置
     */
    public function getBatchConfig()
    {
        $config = [
            'batch_size' => 50, // 默认批次大小
            'max_batch_size' => 200, // 最大批次大小
            'min_batch_size' => 10, // 最小批次大小
        ];
        
        $this->success('获取配置成功', null, $config);
    }
}
