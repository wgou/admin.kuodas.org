<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Config;
use think\Db;

class UserGame extends Api
{

    protected $model = null;

    protected $noNeedRight = '*';
    protected $noNeedLogin = [];
    protected $_allow_func = ['index', 'add', 'edit', 'view', 'get_coupon_chart', 'cash_coupon', 'record_cash_coupon', 'record_daily_salary','exchange'];


    use \app\api\library\buiapi\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\UserGame;
    }

    /**
     * 公共方法-添加
     */
    public function add()
    {
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if ($params) {
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            Db::startTrans();
            $params = $this->__handle_add_before__($params);
            $this->__handle_filter__('add', $params);

            $symbol = "open_shop" . $this->auth->id . "-" . $params['game_game_id'];
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }

            $r = \app\api\model\GameGame::where('id', $params['game_game_id'])->find();
            if (empty($r)) {
                $this->error('摊位不存在');
            }
            $game_level = $this->auth->getUser()['game_level'];
            $cnfig = \app\api\model\GameLevel::where('name', $game_level)->find();
            if (!$cnfig) {
                $this->error('等级不符合参与条件');
            }

            $bai_num = $cnfig['bai_num']; //每日白摆摊次数
            $this_bai_num = \app\api\model\UserGame::whereTime('starttime', 'today')->where('user_id', $this->auth->id)->count(); //当前白摆摊次数
            if ($this_bai_num >= $bai_num) {
                $this->error('今日摆摊次数已满，明天再来吧');
            }

            $data = [
                'user_id' => $this->auth->id,
                'game_game_id' => $params['game_game_id'],
                'quan' => $cnfig['bai_quan'],
                'starttime' => time(),
                'endtime' => time() + 60 * 60 * 2,
                'shoutime' => null,
                'status' => 1,
            ];

            $this->model->save($data);
            if (isset($this->model->id) && !empty($this->model->id)) {
                $result = $this->__handle_add_after__($this->model->id, $params);
                if ($result) {
                    Db::commit();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->success('摆摊成功', $this->_return_data);
                } else {
                    Db::rollback();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->error('摆摊失败', $this->_return_data);
                }
            } else {
                Db::rollback();
                $this->error('摆摊失败-1');
            }
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }


    /**
     * 公共方法-编辑
     */
    public function edit($ids = null)
    {
        //1、获取修改的数据
        $this->_init_where[$this->model->getPk()] = $ids;
        $item = $this->model->get($this->_init_where, $this->_edit_with);
        if (empty($item)) {
            $this->error('摊位记录不存在');
        }

        // Check if the operation is too frequent
        $symbol = "clos_shop" . $this->auth->id . "-" . $ids;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        //2、获取参数
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if (time() < strtotime($item['endtime'])) {
            $this->error('未到收摊时间');
        }
        if ($item->status==2) {
            $this->error('已经收过了');
        }
        //4、参数不为空 - 处理数据更新
        if (!empty($params)) {
            Db::startTrans();

            $data = [
                'shoutime' => time(),
                'status' => 2,
            ];
            $item->save($data);

            $result = $this->__handle_edit_after__($ids, $params);
            if ($result) {
                Db::commit();
                $this->_return_data['ids'] = $ids;
                $user = Db::name('user')->where('id', $this->auth->id)->find();

                // $qun = \app\api\model\User::where('upid', $this->auth->id)->count() * 3; //直推人数加2券
                // $qun = \app\api\model\User::where('id', $this->auth->id)->value('day_share_num'); //直推人数加2券

                // \app\api\model\User::where('id', $this->auth->id)->setInc('score', $item['quan'] + $qun2 + $qun); //奖励券
                // \app\api\model\User::where('id', $this->auth->id)->setInc('score', $item['quan'] + $qun2 + ($qun * 3)); //奖励券
                $qun2 = ($this->auth->getUser()['dt'] == 1 ? 2 : 0); //答题加2券
                // \app\api\model\User::where('id', $this->auth->id)->setInc('game_level_jy', 10); //摆摊一次奖励10经验
                $all_coupon = ($item['quan'] ) + $qun2;

                \app\api\model\User::where('id', $this->auth->id)->setInc('score', $all_coupon); //奖励券

                $detailed_data = [
                    'user_id' => $this->auth->id,
                    'money' => $all_coupon,
                    'before' => $user['score'],
                    'after' => $user['score'] + $all_coupon,
                    'memo' => '摆摊收益',
                    'createtime' => time(),
                    'type' => 'coupon',
                ];
                \app\api\model\UserMoneyLog::create($detailed_data);

                $this->success('收摊成功', $this->_return_data);
            } else {
                Db::rollback();
                $this->_return_data['ids'] = $ids;
                $this->error('收摊失败', $this->_return_data);
            }
        }

        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    public function get_coupon_chart()
    {
        $user = $this->auth->getUser();
        $exchage_rate1 = $this->get_today_rate(Config::get('site.offical_test'));
        $exchage_rate2 = $this->get_today_rate(Config::get('site.offical'));
        $game_level = $user['game_level'];
        $data = [
            'offical_test' => $this->get_percentage(Config::get('site.offical_test')),
            'offical' => $this->get_percentage(Config::get('site.offical')),
            //'exchage_rate1' => Config::get('site.exchage_rate1'),
            //'exchage_rate2' => Config::get('site.exchage_rate2'),
            'coupon_purchased' => Config::get('site.coupon_purchased'),
            'coupon_issue' => Config::get('site.coupon_issue'),
            'comtent_cash' => Config::get('site.ex_comtent_cash'),
            'coupon_cash_rate_test' => Config::get('site.coupon_cash_rate_test')[$game_level],
            'coupon_cash_rate' => Config::get('site.coupon_cash_rate')[$game_level],
            'coupon_cash_max_test' =>Config::get('site.coupon_cash_max_test')[$game_level],
            'coupon_cash_max' =>Config::get('site.coupon_cash_max')[$game_level],
            'coupon_cash_user' =>Config::get('site.coupon_cash_user'),
            'coupon_cash_title' =>Config::get('site.coupon_cash_title'),
            'coupon_cash_title_test' =>Config::get('site.coupon_cash_title_test')
        ];
        $this->success('摆摊成功', $data);
    }

    public function get_percentage($data)
    {
        $formatted_data = [];
        $total = 0;
        foreach ($data as $key => $value) {
            list($num) = explode(':', $key);
            $total += (int)$num;
        }
        $date = strtotime(date('Y-m-d'));
        foreach ($data as $key => $value) {
            list($num) = explode(':', $key);
            $percentage = ((int)$num / $total) * 100;
            $formatted_percentage = round($percentage, 2);
            $formatted_data[] = [
                //"value" => strtotime(date('Y-') . $value) <= $date ? $key : '',
                "value" => $value,
                "data" => $key,
                "percentage" => $formatted_percentage . '%',
            ];
        }
        return $formatted_data;
    }
function getNeixuquanByLevel($level) {
        if ($level >= 24 && $level <= 30) {
            return 560;
        } elseif ($level >= 16 && $level <= 23) {
            return 280;
        } elseif ($level >= 9 && $level <= 15) {
            return 140;
        } elseif ($level >= 3 && $level <= 8) {
            return 70;
        }
        return 0;
    }
    public function cash_coupon()
    {
        //if(time()>1720447200){
        //    $this->error("第三次测试兑换已结束，耐心等待下次兑换");
        //}
        $num = $this->getNeixuquanByLevel($this->auth->game_level);
        $type = $this->request->post('type');
        //$this->error("试运营兑换已结束，官方正式兑换将正式上线，敬请期待！");
        //redis防重复点击
        $symbol = "cash_exchange_coupon" . $this->auth->id;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }
        //$rs = Db::name('coupon_exchange_cash')->where('user_id', $user_id)->where('create_time','gt','1720367999')->where('status','<>',0)->find();
        //if($rs){
        //   $this->error("只能兑换一次,请勿重复兑换");
        //}

        if (!$num) {
            $this->error('请输入提现券数量');
        }
        if (!$type) {
            $this->error('请选择提现券类型');
        }
        if ($num <= 0) {
            $this->error('提现券数量不能小于0');
        }
        //$user = $this->auth->getUser();
        //$maxnum= $this->getGameLevel($user['game_level']);
        //if($num>$maxnum){
        //    $this->error('提现券数量不能大于'.$maxnum);
        //}
        $user_id = $this->auth->id;
        $user = \app\api\model\User::where('id', $this->auth->id)->find();
        if ($user['neixuquan'] < $num) {
            $this->error('内需劵数量不足');
        }

        // 检查商城等级限制
        if ($user['game_level'] < 2) {
            $this->error('您的商城等级暂未达到兑换要求，请先提升等级');
        }

        if ($type == 1) {
            //$exchage_rate = Config::get('site.exchage_rate1');
            $rs = Db::name('coupon_exchange_newcash')->where('user_id', $this->auth->id)->where('type','1')->where('create_time','gt',strtotime('2025-08-03 00:00:00'))->where('status','<>',0)->find();
            if($rs){
              $this->error("只能兑换一次,请勿重复兑换");
            }
            
            // 测试版本时间段限制：2025-07-30 08:00:00 到 21:59:59
            $start_time = strtotime('2025-08-04 08:00:00');
            $end_time = strtotime('2025-08-04 21:59:59');
            $current_time = time();
            
            if ($current_time < $start_time || $current_time > $end_time) {
                $this->error('暂未开启兑换 请2025年8月4日上午8点在进行兑换');
            }
            
            // 统一使用固定时间段限制：2025-07-30 08:00:00 到 21:59:59
            //根据用户商城等级获取汇率
            $rates = config::get('site.coupon_cash_rate_test');
            $exchage_rate = $rates[$user['game_level']];
            $ex_num =sprintf("%.2f",$num * $exchage_rate);
        } else {
            //$exchage_rate =  Config::get('site.exchage_rate2');
            //限制人员白名单
            $user_white = explode(",", Config::get('site.coupon_cash_user'));
            if(!in_array($user_id,$user_white)){
                $this->error('官方正式兑换暂未开启');
            }
            //根据用户商城等级获取汇率
            $rates = config::get('site.coupon_cash_rate');
            $exchage_rate = $rates[$user['game_level']];
            $ex_num = sprintf("%.2f",$num * $exchage_rate);
        }

        $data = [
            'user_id' => $user_id,
            'num' => $num,
            'ex_num' => $ex_num,
            'ex_rate' => $exchage_rate,
            'type' => $type,
            'status' => 2,
            'ex_time' => time(),
            'create_time' => time(),
        ];
        $detailed_data = [
            'user_id' => $user_id,
            'money' => -$num,
            'before' => $user['neixuquan'],
            'after' => $user['neixuquan'] - $num,
            'memo' => '内需劵兑换现金',
            'createtime' => time(),
            'type' => 'neixuquan',
        ];
        Db::startTrans();
        try {
            db('coupon_exchange_newcash')->insert($data);
            db("user_money_log")->insert($detailed_data);
            \app\api\model\User::where('id', $user_id)->setDec('neixuquan', $num);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('申请成功');
    }
    // public function cash_coupon()
    // {
    //     //if(time()>1720447200){
    //     //    $this->error("第三次测试兑换已结束，耐心等待下次兑换");
    //     //}
    //     $num = $this->request->post('num');
    //     $type = $this->request->post('type');
    //     //$this->error("试运营兑换已结束，官方正式兑换将正式上线，敬请期待！");
    //     //redis防重复点击
    //     $symbol = "cash_exchange_coupon" . $this->auth->id;
    //     $submited = pushRedis($symbol);
    //     if (!$submited) {
    //         $this->error(__("操作频繁"));
    //     }
    //     //$rs = Db::name('coupon_exchange_cash')->where('user_id', $user_id)->where('create_time','gt','1720367999')->where('status','<>',0)->find();
    //     //if($rs){
    //     //   $this->error("只能兑换一次,请勿重复兑换");
    //     //}

    //     if (!$num) {
    //         $this->error('请输入提现券数量');
    //     }
    //     if (!$type) {
    //         $this->error('请选择提现券类型');
    //     }
    //     if ($num <= 0) {
    //         $this->error('提现券数量不能小于0');
    //     }
    //     //$user = $this->auth->getUser();
    //     //$maxnum= $this->getGameLevel($user['game_level']);
    //     //if($num>$maxnum){
    //     //    $this->error('提现券数量不能大于'.$maxnum);
    //     //}
    //     $user_id = $this->auth->id;
    //     $user = \app\api\model\User::where('id', $this->auth->id)->find();
    //     if ($user['neixuquan'] < $num) {
    //         $this->error('内需劵数量不足');
    //     }

    //     // 检查商城等级限制
    //     if ($user['game_level'] < 2) {
    //         $this->error('您的商城等级暂未达到兑换要求，请先提升等级');
    //     }

    //     if ($type == 1) {
    //         //$exchage_rate = Config::get('site.exchage_rate1');
    //         $rs = Db::name('coupon_exchange_cash')->where('user_id', $this->auth->id)->where('type','1')->where('create_time','gt',strtotime('2025-05-08 00:00:00'))->where('status','<>',0)->find();
    //         if($rs){
    //           $this->error("只能兑换一次,请勿重复兑换");
    //         }
            
    //         // 测试版本时间段限制：2025-07-30 08:00:00 到 21:59:59
    //         $start_time = strtotime('2025-07-30 08:00:00');
    //         $end_time = strtotime('2025-07-30 21:59:59');
    //         $current_time = time();
            
    //         if ($current_time < $start_time || $current_time > $end_time) {
    //             $this->error('暂未开启兑换 请2025年7月30日上午8点在进行兑换');
    //         }
            
    //         // 统一使用固定时间段限制：2025-07-30 08:00:00 到 21:59:59
    //         //根据用户商城等级获取汇率
    //         $rates = config::get('site.coupon_cash_rate_test');
    //         $exchage_rate = $rates[$user['game_level']];
    //         $ex_num =sprintf("%.2f",$num * $exchage_rate);
    //     } else {
    //         //$exchage_rate =  Config::get('site.exchage_rate2');
    //         //限制人员白名单
    //         $user_white = explode(",", Config::get('site.coupon_cash_user'));
    //         if(!in_array($user_id,$user_white)){
    //             $this->error('官方正式兑换暂未开启');
    //         }
    //         //根据用户商城等级获取汇率
    //         $rates = config::get('site.coupon_cash_rate');
    //         $exchage_rate = $rates[$user['game_level']];
    //         $ex_num = sprintf("%.2f",$num * $exchage_rate);
    //     }

    //     $data = [
    //         'user_id' => $user_id,
    //         'num' => $num,
    //         'ex_num' => $ex_num,
    //         'ex_rate' => $exchage_rate,
    //         'type' => $type,
    //         'status' => 2,
    //         'ex_time' => time(),
    //         'create_time' => time(),
    //     ];
    //     $detailed_data = [
    //         'user_id' => $user_id,
    //         'money' => -$num,
    //         'before' => $user['neixuquan'],
    //         'after' => $user['neixuquan'] - $num,
    //         'memo' => '内需劵兑换现金',
    //         'createtime' => time(),
    //         'type' => 'ex_coupon',
    //     ];
    //     Db::startTrans();
    //     try {
    //         db('coupon_exchange_cash')->insert($data);
    //         db("user_money_log")->insertAll($detailed_data);
    //         \app\api\model\User::where('id', $user_id)->setDec('neixuquan', $num);
    //         Db::commit();
    //     } catch (Exception $e) {
    //         Db::rollback();
    //         $this->error($e->getMessage());
    //     }
    //     $this->success('申请成功');
    // }

    // public function record_cash_coupon()
    // {
    //     $user_id = $this->auth->id;
    //     $data = db('coupon_exchange_cash')
    //         ->where('user_id', $user_id)
    //         ->order('id desc')
    //         ->paginate(10, false, ['query' => request()->param()]);
    //     foreach ($data as $k => $v) {
    //         $v['create_time'] = date('Y-m-d H:i', $v['create_time']);
    //         $data[$k] = $v;
    //     }
    //     $this->success('提现券申请记录', $data);
    // }
    public function record_cash_coupon()
    {
        $whereTime = "user_id = ".$this->auth->id;
        $page=$this->request->post('page',0);
        $page = $page<=0?0:--$page;
        $page=$page*10;
        $sql = "
            (SELECT * FROM fa_coupon_exchange_cash WHERE {$whereTime})
            UNION ALL
            (SELECT * FROM fa_coupon_exchange_newcash WHERE {$whereTime})
            ORDER BY create_time DESC LIMIT {$page},10";
        
        $data = Db::query($sql);
        foreach ($data as $k => $v) {
            $v['create_time'] = date('Y-m-d H:i', $v['create_time']);
            $data[$k] = $v;
        }
        $countSql = "SELECT COUNT(*) AS total FROM (
                            (SELECT * FROM fa_coupon_exchange_cash WHERE {$whereTime})
            UNION ALL
            (SELECT * FROM fa_coupon_exchange_newcash WHERE {$whereTime})
                        ) AS total_table
                    ";
                    $total = Db::query($countSql)[0]['total'];
        $r=[
            'current_page'=>$this->request->post('page',0),
            'data'=>$data,
            'last_page'=>ceil($total / 10),
            'per_page'=>10,
            'total'=>ceil($total / 10),
        ];

        $this->success('提现券申请记录', $r);
    }

    public function get_today_rate($exchage_rate)
    {
        // Get the current date in 'm-d' format
        //$today = date('n-j');
        $today = date('m-d');
        // Initialize a variable to hold the key
        $key_for_today = null;
        $rate = null;
        // Loop through the 'offical_test' array to find the matching date
        foreach ($exchage_rate as $key => $date) {
            if ($date === $today) {
                $key_for_today = $key;
                $parts = explode(':', $key_for_today);
                $rate = $parts[0];
                break;
            }
        }
        return $rate;
    }

    public function record_daily_salary()
    {
        $user_id = $this->auth->id;
        $data = db('user_money_log')
            ->field('id,memo,money,createtime')
            ->where('user_id', $user_id)
            ->where('type', 'daily_salary')
            ->order('id desc')
            ->paginate(10, false, ['query' => request()->param()]);
        foreach ($data as $k => $v) {
            $v['createtime'] = date('Y-m-d H:i', $v['createtime']);
            $data[$k] = $v;
        }
        $this->success('ok', $data);
    }
}
