<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use app\api\model\UserMoney;
use think\Db;
use app\pay\GMPAY;
use BaconQrCode\Common\Mode;

class Order extends Api
{

    protected $model = null;

    protected $noNeedRight = '*';
    protected $noNeedLogin = ['aaa'];
    protected $_allow_func = '*';
    protected $_search_field = ['createtime', 'status'];


    use \app\api\library\buiapi\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\Order;
    }
    public function index()
    {
        // Decode and parse filter parameter
        $filter = json_decode(html_entity_decode($this->request->param('filter', '')), true);
    
        // Retrieve and validate page parameter
        $page = (int)($this->request->param('page') ?? 1);
        $page = max(1, $page);
    
        $userId = $this->auth->id;
        $itemsPerPage = 20;
        $offset = ($page - 1) * $itemsPerPage;
    
        // Build query conditions
        $conditions = [
            'user_id' => $userId,
            'status' => 2,
        ];
    
        if (isset($filter['createtime'])) {
            $dateRange = explode(',', $filter['createtime']);
            if (count($dateRange) === 2) {
                $conditions['createtime'] = ['between', [$dateRange[0], $dateRange[1] + 86400]];
            }
        }
    
        // Get total count and retrieve data
        $total = Db::name('order')->where($conditions)->count('id');
        $orders = Db::name('order')
            ->where($conditions)
            ->order('id desc')
            ->limit($offset, $itemsPerPage)
            ->select();
    
        // Process orders
        foreach ($orders as &$order) {
            $order['data'] = json_decode($order['data'], true);
             if($order['paytype']=='borrow_money'){
                $order['data']['name'] = $order['data']['name']."(借贷支付)";
            }
            $order['createtime'] = date('Y-m-d H:i', $order['createtime']);
        }
    
        // Prepare response data
        $list = [
            'total' => $total,
            'per_page' => $itemsPerPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $itemsPerPage),
            'rows' => $orders,
            'allmmoney' => Db::table('fa_order')
                ->where('user_id', $userId)
                ->where('status', 2)
                ->sum('price'),
        ];
    
        $this->success('数据列表', $list);
    }

    /**
     * 公共方法-详情
     */
    public function view($ids = null)
    {
        $this->_init_where[$this->model->getPk()] = $ids;
        $item = $this->model->get($this->_init_where, $this->_view_with);
        if (empty($item)) {
            $this->error('数据记录不存在');
        }
        if (!empty($this->_view_field)) {
            $item->field($this->_view_field);
        }
        $row = !empty($item) ? $this->__handle_view__($ids, $item) : [];
        $this->success('数据信息', $row);
    }


    /**
     * 公共方法-添加
     */
    public function add()
    {
        $this->check_order_status();
        
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if ($params) {
            $paytype = isset($params['paytype']) ? $params['paytype'] : null;
            if(!$paytype) $this->error('当前购买人数爆满，支付通道占用，请稍后重试');
            $qty = isset($params['qty']) ? $params['qty'] : 1;
            if ($qty < 1) {
                $this->error('数量不能小于1');
            }
            // if ($qty < 0) {
            //     $this->error('数量不能小于0');
            // }
            //redis防重复点击
            $symbol = "order_add" . $this->auth->id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            Db::startTrans();
            $project_data = Db::table('fa_project_data')->where('id', input('project_data_id'))->find();
            if (empty($project_data)) {
                $this->error('项目失效');
            }
            switch ($paytype) {
                case 'money':
                    $user_money = UserMoney::where('user_id', $this->auth->id)->find();
                    if($user_money['money']< $project_data['money'] * $qty){
                        $this->error('可提现余额不足');
                    }
                    break;
                case 'nomoney':
                    if($this->auth->getUser()['nomoney']< $project_data['money'] * $qty){
                        $this->error('账户余额不足');
                    }
                    break;
                case 'borrow_money':
                    if($this->auth->getUser()['borrow_money']< $project_data['money'] * $qty){
                        $this->error('可借余额不足');
                    }
                    break;
                case 'money_tuijian':
                    $user_money = UserMoney::where('user_id', $this->auth->id)->find();
                    if($user_money['money_tuijian']< $project_data['money'] * $qty){
                        $this->error('推荐收益余额不足');
                    }
                    break;
                case 'money_shifang':
                    $user_money = UserMoney::where('user_id', $this->auth->id)->find();
                    if($user_money['money_shifang']< $project_data['money'] * $qty){
                        $this->error('释放收益余额不足');
                    }
                    break;
                case 'money_qiandao':
                    $user_money = UserMoney::where('user_id', $this->auth->id)->find();
                    if($user_money['money_qiandao']< $project_data['money'] * $qty){
                        $this->error('签到收益余额不足');
                    }
                    break;
                case 'money_shouyibao':
                    $user_money = UserMoney::where('user_id', $this->auth->id)->find();
                    if($user_money['money_shouyibao']< $project_data['money'] * $qty){
                        $this->error('收益宝余额不足');
                    }
                    break;
                default :
                    $this->error('支付方式错误-2');
            }
            unset($params['paytype']);
            $params = $this->__handle_add_before__($params);
            $this->__handle_filter__('add', $params);
           
//            if ($this->auth->getUser()['nomoney'] < $project_data['money']) {
//                $this->error('余额不足');
//            }

            $event9day = config('site.event_9day');
            if($event9day == 1){
                $event_state = 1;
            }else{
                $event_state = 0;
            }

            $price = $project_data['money'] * $qty;

            $params['user_id'] = $this->auth->id;
            $params['order_no'] = date('YmdHis') . rand(1000000, 9000000);
            $params['createtime'] = time();
            $params['status'] = 1;
            $params['qty'] = $qty;
            $params['event_state'] = $event_state;
            $params['price'] = $price;
            unset($project_data['filecontent']);
            $params['data'] = json_encode($project_data, 256);
            $params['paytype'] = $paytype;
            $params['uis_rot'] = $this->auth->is_rot;
            //redis防重复点击
            $symbol = "order" . $this->auth->id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            // 检查188型项目购买限制
            if($project_data['id'] == 8) { // 8是188型项目的ID
                $has_bought = Db::name('order')
                    ->where('user_id', $this->auth->id)
                    ->where('project_data_id', 8)
                    ->where('status', 2)  // 2表示支付成功的订单
                    ->find();
                    
                if($has_bought) {
                    $this->error('188型项目每个用户仅限购买一次');
                }
            }
            $this->model->save($params);
            if (isset($this->model->id) && !empty($this->model->id)) {
                $result = $this->__handle_add_after__($this->model->id, $params);
                if ($result) {
                    Db::commit();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->success('添加数据成功', $this->_return_data);
                } else {
                    Db::rollback();
                    $this->_return_data['ids'] = $this->model->id;
                    $this->error('添加数据失败', $this->_return_data);
                }
            } else {
                Db::rollback();
                $this->error($this->model->getError());
            }
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }
    
	//获取用户订单基础数组
    public function getLotterys(){
        //$this->error('抽奖已结束');die();
        $user_id =  $this->auth->id;
        $orders = Db("order")
            ->where('user_id',$user_id)
            ->where('status','2')
            ->where('paytype','<>','borrow_money')
            ->where('createtime','between',[strtotime('2025-03-1 00:00:00'),strtotime('2025-06-30 23:59:59')])
            ->field('sum(qty) qty,project_data_id')
            ->group('project_data_id')
            ->select();
        $lotterys= [];
        foreach($orders as $order){
             $yqty = Db("lottery_record")->where('user_id',$user_id)
                ->where('project_data_id',$order['project_data_id'])
                ->where('createtime','gt',strtotime('2025-03-1 00:00:00'))
                ->sum('num');
             $num = $order['qty']-$yqty;
             if($num<0){
                 $num = 0;
             }
             $lotterys[$order['project_data_id']] = $num;
        }
        //直推型
        $upids = Db("user")->where('upid',$user_id)->whereOr('upid2',$user_id)->whereOr('upid3',$user_id)->field('id')->select();
        $up_orders = Db("order")
            ->where('user_id','in',implode(',', array_column($upids, 'id')))
            ->where('status','2')
            ->where('paytype','<>','borrow_money')
            ->whereTime('createtime', 'today')
            ->field('sum(qty) qty,project_data_id')
            ->sum('qty');
        $up_yqty = Db("lottery_record")->where('user_id',$user_id)
            ->where('project_data_id','888')
            ->whereTime('createtime', 'today')
            ->sum('num');
        $num = ($up_orders >= 5 ? 1 :0 ) - $up_yqty;
        $lotterys['888'] = $num;

        //新人型
        $old_orders = Db("order")->where('user_id',$user_id)->where('status', '2')->where('createtime','lt',strtotime('2025-03-1 00:00:00'))->count();
        if($old_orders == 0) {
            $new_orders = Db("order")
                ->where('user_id', $user_id)
                ->where('status', '2')
                ->where('paytype', '<>', 'borrow_money')
                ->where('createtime', 'between', [strtotime('2025-03-1 00:00:00'), strtotime('2025-06-30 23:59:59')])
                ->field('qty')
                ->find();
            $new_orders = !empty($new_orders) ? $new_orders['qty'] : 0;
            $new_yqty = Db("lottery_record")->where('user_id',$user_id)
                ->where('project_data_id','999')
                ->where('createtime','gt',strtotime('2025-03-1 00:00:00'))
                ->sum('num');
            $num = $new_orders - $new_yqty;
            $num = $num < 0 ? 0 : $num;
            $lotterys['999'] = $num;
        }else{
            $lotterys['999'] = 0;
        }
        $this->success('ok', $lotterys);
    }

    
	//获取真实中奖数据
    public function getWinning($project_data_id){
        $winning = array();
        if ($project_data_id==4) {
			$lottery = array();
			$lottery[1] = array('type'=>1,'desc'=>'消费券288张','num'=>288);
			$lottery[2] = array('type'=>2,'desc'=>'经验值288点','num'=>288);
			$lottery[3] = array('type'=>3,'desc'=>'项目收益3万','num'=>30000);
			$lottery[4] = array('type'=>5,'desc'=>'美的空调一台','num'=>1);
            $lottery[5] = array('type'=>5,'desc'=>'小米电视机（100寸）一台','num'=>1);
            $rand = rand(1,1000);
            if($rand < 300){
                  $index = 1;
            }elseif ($rand < 600) {
                  $index = 2;
            }elseif ($rand < 980) {
                  $index = 3;
            }elseif ($rand <= 1000) {
                  $index = 3;
            }
            $winning['win']  = $lottery[$index];
			unset($lottery[$index]);
			shuffle($lottery);
			$winning['cards'] = $lottery;
        }elseif ($project_data_id==5) {
			$lottery = array();
			$lottery[1] = array('type'=>1,'desc'=>'消费券588张','num'=>588);
			$lottery[2] = array('type'=>2,'desc'=>'经验值588点','num'=>588);
			$lottery[3] = array('type'=>3,'desc'=>'项目收益6万','num'=>60000);
            $lottery[4] = array('type'=>5,'desc'=>'奥克斯按摩椅一台','num'=>1);
			$lottery[5] = array('type'=>5,'desc'=>'小米汽车SU7 Max一辆','num'=>1);
            $rand = rand(1,1000);
            if($rand < 330){
                  $index = 1;
            }elseif ($rand < 660) {
                  $index = 2;
            }elseif ($rand < 980) {
                  $index = 3;
            }elseif ($rand <= 1000) {
                  $index = 3;
            }
            $winning['win']  = $lottery[$index];
			unset($lottery[$index]);
			shuffle($lottery);
			$winning['cards'] = $lottery;
        }elseif ($project_data_id==6) {
			$lottery = array();
			$lottery[1] = array('type'=>1,'desc'=>'消费券1088张','num'=>1088);
			$lottery[2] = array('type'=>2,'desc'=>'经验值1088点','num'=>1088);
			$lottery[3] = array('type'=>3,'desc'=>'项目收益12万','num'=>120000);
			$lottery[4] = array('type'=>4,'desc'=>'现金红包38888元','num'=>38888);
            $lottery[5] = array('type'=>5,'desc'=>'碧桂园房产购房抵用券50万元','num'=>1);
            $rand = rand(1,1000);
            if($rand < 330){
                  $index = 1;
            }elseif ($rand < 660) {
                  $index = 2;
            }elseif ($rand <= 990) {
                  $index = 3;
            }elseif ($rand <= 1000) {
                  $index = 3;
          }            
            $winning['win']  = $lottery[$index];
			unset($lottery[$index]);
            if($index != 4){
                $xianjin = array(array('type'=>4,'desc'=>'现金红包38888元','num'=>38888),array('type'=>4,'desc'=>'现金红包68888元','num'=>68888),array('type'=>4,'desc'=>'现金红包108888元','num'=>108888));
                shuffle($xianjin);
                $lottery[4] = $xianjin[0];
            }
			shuffle($lottery);
			$winning['cards'] = $lottery;
        }elseif ($project_data_id==888) {
            $lottery = array();
            $lottery[1] = array('type'=>1,'desc'=>'消费券188张','num'=>188);
            $lottery[2] = array('type'=>2,'desc'=>'经验值188点','num'=>188);
            $lottery[3] = array('type'=>3,'desc'=>'项目收益5千','num'=>5000);
            $lottery[4] = array('type'=>5,'desc'=>'小米空气净化器一台','num'=>1);
            $lottery[5] = array('type'=>4,'desc'=>'现金红包12888元','num'=>1);
            $rand = rand(1,1000);
            if($rand < 330){
                $index = 1;
            }elseif ($rand < 660) {
                $index = 2;
            }elseif ($rand < 990) {
                $index = 3;
            }elseif ($rand <= 1000) {
                $index = 3;
            }
            $winning['win']  = $lottery[$index];
			unset($lottery[$index]);
			shuffle($lottery);
			$winning['cards'] = $lottery;
        }elseif ($project_data_id==999) {
            $lottery = array();
            $lottery[1] = array('type'=>1,'desc'=>'消费券188张','num'=>188);
            $lottery[2] = array('type'=>2,'desc'=>'经验值188点','num'=>188);
            $lottery[3] = array('type'=>3,'desc'=>'项目收益1万','num'=>10000);
            $lottery[4] = array('type'=>5,'desc'=>'美的取暖器一台','num'=>1);
            $lottery[5] = array('type'=>4,'desc'=>'现金红包26888元','num'=>1);
            $rand = rand(1,1000);
            if($rand < 330){
                $index = 1;
            }elseif ($rand < 660) {
                $index = 2;
            }elseif ($rand < 990) {
                $index = 3;
            }elseif ($rand <= 1000) {
                $index = 3;
            }
            $winning['win']  = $lottery[$index];
			unset($lottery[$index]);
			shuffle($lottery);
			$winning['cards'] = $lottery;
        }
        return $winning;
    }
    	
	//获取用户订单基础数组
    public function getLotteryPros(){
        //$this->error('抽奖已结束');die();
        $filter = $this->request->param('filter');
        $filter = html_entity_decode($filter);
        $filter = json_decode($filter, true);
        $id = $filter['id']?? 0;
        $user_id =  $this->auth->id;
        $countqty=0;
        if (in_array($id,[4,5,6])) {
            $countqty = Db("order")
                ->where('user_id',$user_id)
                ->where('paytype','<>','borrow_money')
                ->where('status','2')
                ->where('createtime','between',[strtotime('2025-03-1 00:00:00'),strtotime('2025-06-30 23:59:59')])
                ->where('project_data_id',$id)
                ->sum('qty');
            $yqty = Db("lottery_record")->where('user_id',$user_id)
                ->where('project_data_id',$id)
                ->where('createtime','gt',strtotime('2025-03-1 00:00:00'))
                ->sum('num');
             $num = $countqty - $yqty;
             if($num<0){
                 $num = 0;
             }
            $this->success('ok', $num);
        }elseif($id == 888){
            //直推型
            $upids = Db("user")->where('upid',$user_id)->whereOr('upid2',$user_id)->whereOr('upid3',$user_id)->field('id')->select();
            $up_orders = Db("order")
                ->where('user_id','in',implode(',', array_column($upids, 'id')))
                ->where('status','2')
                ->where('paytype','<>','borrow_money')
                ->whereTime('createtime', 'today')
                ->field('sum(qty) qty,project_data_id')
                ->sum('qty');
            $up_yqty = Db("lottery_record")->where('user_id',$user_id)
                ->where('project_data_id','888')
                ->whereTime('createtime', 'today')
                ->sum('num');
            $num = ($up_orders >= 5 ? 1 :0 ) - $up_yqty;
            $this->success('ok', $num);
        }elseif($id == 999){
            //新人型
            $old_orders = Db("order")->where('user_id',$user_id)->where('status', '2')->where('createtime','lt',strtotime('2025-03-1 00:00:00'))->count();
            if($old_orders == 0) {
                $new_orders = Db("order")
                    ->where('user_id', $user_id)
                    ->where('status', '2')
                    ->where('paytype', '<>', 'borrow_money')
                    ->where('createtime', 'between', [strtotime('2025-03-1 00:00:00'), strtotime('2025-06-30 23:59:59')])
                    ->field('qty')
                    ->find();
                $new_orders = !empty($new_orders) ? $new_orders['qty'] : 0;
                $new_yqty = Db("lottery_record")->where('user_id',$user_id)
                    ->where('project_data_id','999')
                    ->where('createtime','gt',strtotime('2025-03-1 00:00:00'))
                    ->sum('num');
                $num = $new_orders - $new_yqty;
                $num = $num < 0 ? 0 : $num;
                $this->success('ok', $num);
            }else{
                $this->success('ok', 0);
            }
        }else{
			$this->error('抽奖数据错误');
		}
    }
	
	
    //抽奖主体计算
    public function lottery(){
        //$this->error('抽奖已结束');die();
        $filter = $this->request->param('id');
        $filter = html_entity_decode($filter);
        $filter = json_decode($filter, true);
        $id = $filter['id']?? 0;
        $user_id =  $this->auth->id;
		//redis防重复点击
		$symbol = "lottery" . $this->auth->id;
		$submited = pushRedis($symbol);
		if (!$submited) {
			$this->error(__("操作频繁"));
		}		
        if(in_array($id,[4,5,6,888,999])){
            //判断用户是否有抽奖机会
            $countqty = 0;
            if(in_array($id,[4,5,6])) {
                $countqty = Db("order")
                    ->where('user_id', $user_id)
                    ->where('paytype', '<>', 'borrow_money')
                    ->where('status', '2')
                    ->where('createtime', 'between', [strtotime('2025-03-1 00:00:00'), strtotime('2025-06-30 23:59:59')])
                    ->where('project_data_id', $id)
                    ->sum('qty');
                $yqty = Db("lottery_record")->where('user_id', $user_id)
                    ->where('project_data_id', $id)
                    ->where('createtime', 'gt', strtotime('2025-03-1 00:00:00'))
                    ->sum('num');
            }elseif($id == 888){
                //直推型
                $upids = Db("user")->where('upid',$user_id)->whereOr('upid2',$user_id)->whereOr('upid3',$user_id)->field('id')->select();
                $countqty = Db("order")
                    ->where('user_id','in',implode(',', array_column($upids, 'id')))
                    ->where('status','2')
                    ->where('paytype','<>','borrow_money')
                    ->whereTime('createtime', 'today')
                    ->field('sum(qty) qty,project_data_id')
                    ->sum('qty');
                $countqty = ($countqty - 5) < 0 ? 0 : 1;
                $yqty = Db("lottery_record")->where('user_id',$user_id)
                    ->where('project_data_id','888')
                    ->whereTime('createtime', 'today')
                    ->sum('num');
            }elseif($id == 999){
                //新人型
                $old_orders = Db("order")->where('user_id',$user_id)->where('status', '2')->where('createtime','lt',strtotime('2025-03-1 00:00:00'))->count();
                if($old_orders == 0) {
                    $countqty = Db("order")
                        ->where('user_id', $user_id)
                        ->where('status', '2')
                        ->where('paytype', '<>', 'borrow_money')
                        ->where('createtime', 'between', [strtotime('2025-03-1 00:00:00'), strtotime('2025-06-30 23:59:59')])
                        ->where('project_data_id', $id)
                        ->sum('qty');
                    $yqty = Db("lottery_record")->where('user_id',$user_id)
                        ->where('project_data_id','999')
                        ->where('createtime','gt',strtotime('2025-03-1 00:00:00'))
                        ->sum('num');
                }else{
                    $countqty = $yqty = 0;
                }
            }
            if($yqty>=$countqty){
                $this->error('用户已无抽奖机会');
            }else{
                //$lottery_list = $this->getWinning($id);
                //$win = $lottery_list['win'];
                $win_cash = 0;
                switch ($id) {
                    case 4:
                        $win_cash = mt_rand(100,400);
                        break;
                    case 5:
                        $win_cash = mt_rand(200,700);
                        break;
                    case 6:
                        $win_cash = mt_rand(400,1000);
                        break;
                    case 999:
                        $win_cash = mt_rand(100,200);
                        break;
                    case 888:
                        //抽奖白名单
                        $white_list = config('site.choujiang_white_list');
                        if(in_array($user_id,[$white_list])){
                            $win_cash = mt_rand(200,1000);
                        }else {
                            $win_cash = mt_rand(100, 200);
                        }
                        break;
                    default:
                        $win_cash = 0;
                }
                //增加抽奖现金奖励
                $user_money = UserMoney::where('user_id', $user_id)->find();
                $ins = [
                    'user_id' => $user_id,
                    'money' => $win_cash,
                    'before' => $user_money['money_choujiang'],
                    'after' => $user_money['money_choujiang'] + $win_cash,
                    'memo' => $this->_get_project_typename($id).'抽奖现金红包',
                    'createtime' => time(),
                    'type' => 'buy',
                ];
                $detailed_data = [
                    'user_id' => $user_id,
                    'lottery_type' => 4,
                    'price' => $win_cash,
                    'num' => 1,
                    'project_data_id' => $id,
                    'memo' => '抽奖现金红包'.$win_cash.'元',
                    'createtime' => time()
                ];
                Db::startTrans();
                try {
                    $res1  = \app\api\model\UserMoneyLog::create($ins);
                    $res2 = \app\api\model\User::where('id', $user_id)->setInc('money',$win_cash);
                    $res3 = UserMoney::money_in($user_id,'choujiang',$win_cash,$ins['memo']);
                    $res4 = \app\api\model\LotteryRecord::create($detailed_data);
                    if (!$res1 && !$res2 && !$res3 && !$res4) {
                        Db::rollback();
                        $this->error("失败");
                    }
                    Db::commit();
                    $lottery_list['countqty'] = $countqty-$yqty - 1;
                    $lottery_list['price']  = $win_cash;
                    $this->success('ok',$lottery_list);
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error(__("错误,请稍后再试!".$e->getMessage()));
                }
            }
        }else{
            $this->error('抽奖失败');
        }
    }

    /**
     * 获取奖池信息
     * @return void
     */
    public function get_jackpot (){
        $filter = json_decode(html_entity_decode($this->request->param('filter', '')), true);
        switch ($filter['id']) {
            case "4":
                $jackpot = config('site.choujiang_low_level');
                break;
            case "5":
                $jackpot = config('site.choujiang_middle_level');
                break;
            case "6":
                $jackpot = config('site.choujiang_high_level');
                break;
            case "999":
                $jackpot = config('site.choujiang_new_people');
                break;
            case "888":
                $jackpot = config('site.choujiang_super_level');
                break;
            default:
                $jackpot = [];
                break;
        }
        $this->success('ok', $jackpot);
    }

   
    public function pay()
    {
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if ($params) {
            //redis防重复点击
            $symbol = "order_pay" . $this->auth->id;
            $submited = pushRedis($symbol);
            if (!$submited) {
                $this->error(__("操作频繁"));
            }
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            $paytype = $params['paytype'];
            $project_data = Db::table('fa_order')->where('id', input('ids'))->find();
            if (empty($project_data)) {
                $this->error('订单失效');
            }
            $pdata=Db::name('project_data')->where(['id'=>$project_data['project_data_id']])->find();
            if (empty($pdata)) {
                $this->error('项目失效');
            }
            if (!in_array($paytype, ['money', 'nomoney', 'borrow_money', 'money_tuijian', 'money_shifang', 'money_qiandao', 'money_shouyibao'])) {
                $this->error('支付方式错误');
            }
            $qty =  $project_data['qty'];
            $price = $project_data['price'];
            $config = json_decode($project_data['data'], true);
            $datajson = json_decode($config['datajson'], true);
            $insertAll = [];
            
            $user = Db::name('user')->where('id', $this->auth->id)->find();
            $game_level = $this->auth->getUser()['game_level'];
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
            // 检查188型项目购买限制
            if($project_data['project_data_id'] == 8) { // 8是188型项目的ID
                $has_bought = Db::name('order')
                    ->where('user_id', $this->auth->id)
                    ->where('project_data_id', 8)
                    ->where('status', 2)  // 2表示支付成功的订单
                    ->find();
                    
                if($has_bought) {
                    $this->error('188型项目每个用户仅限购买一次');
                }
            }
            // 
            
            //扣除余额
            switch ($paytype) {
                case 'money':
                    $user_money = UserMoney::where('user_id', $project_data['user_id'])->find();
                    if($user_money['money']< $project_data['price']){
                        $this->error('可提现余额不足');
                    }
                    //券换资金款项不参与购买
                    if( $project_data['price'] - $user_money['money_shifang'] - $user_money['money_tuijian'] - $user_money['money_qiandao'] > 0){
                        $this->error('券换资金款项不可参与认购');
                    }
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('money', $project_data['price']);
                    //动态扣减资金
                    if($user_money['money_shifang'] > $project_data['price']){
                        UserMoney::money_in($project_data['user_id'], 'shifang', -$project_data['price'],'项目认购 ' . $project_data['id']);
                    }else{
                        //释放资金不够支付
                        $next = $project_data['price'] - $user_money['money_shifang'];
                        UserMoney::money_in($project_data['user_id'], 'shifang', -$user_money['money_shifang'],'项目认购 ' . $project_data['id']);
                        //扣除推荐资金
                        if($next > 0 && $user_money['money_tuijian'] > $next){
                            UserMoney::money_in($project_data['user_id'], 'tuijian', -$next ,'项目认购 ' . $project_data['id']);
                        }else if($next > 0 && $user_money['money_tuijian'] < $next){
                            UserMoney::money_in($project_data['user_id'], 'tuijian', -$user_money['money_tuijian'] ,'项目认购 ' . $project_data['id']);
                            $next = $next - $user_money['money_tuijian'];
                        }
                        //扣除签到资金
                        if($next > 0 && $user_money['money_qiandao'] > $next){
                            UserMoney::money_in($project_data['user_id'], 'qiandao', -$next ,'项目认购 ' . $project_data['id']);
                        }else if($next > 0 && $user_money['money_qiandao'] < $next){
                            UserMoney::money_in($project_data['user_id'], 'qiandao', -$user_money['money_qiandao'] ,'项目认购 ' . $project_data['id']);
                            $next = $next - $user_money['money_qiandao'];
                        }
                        //扣除券换资金
//                        if($next > 0 && $user_money['money_quanhuan'] > $next){
//                            UserMoney::money_in($project_data['user_id'], 'quanhuan',$next ,'项目认购 ' . $project_data['id']);
//                        }else if($next > 0 && $user_money['money_quanhuan'] < $next){
//                            UserMoney::money_in($project_data['user_id'], 'quanhuan',$user_money['money_quanhuan'] ,'项目认购 ' . $project_data['id']);
//                            //$next = $next - $user_money['money_qiandao'];
//                        }
                    }
                    break;
                case 'nomoney':
                    if($this->auth->getUser()['nomoney']< $project_data['price']){
                        $this->error('账户余额不足');
                    }
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('nomoney', $project_data['price']);
                    break;
                case 'borrow_money':
                    if($this->auth->getUser()['borrow_money']< $project_data['price']){
                        $this->error('账户余额不足');
                    }
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('borrow_money', $project_data['price']);
                    break;
                case 'money_tuijian':
                    $user_money = UserMoney::where('user_id', $project_data['user_id'])->find();
                    if($user_money['money_tuijian']< $project_data['price']){
                        $this->error('推荐收益余额不足');
                    }
                    UserMoney::money_in($project_data['user_id'], 'tuijian', -$project_data['price'], '项目认购 ' . $project_data['id']);
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('money', $project_data['price']);
                    break;
                case 'money_shifang':
                    $user_money = UserMoney::where('user_id', $project_data['user_id'])->find();
                    if($user_money['money_shifang']< $project_data['price']){
                        $this->error('释放收益余额不足');
                    }
                    UserMoney::money_in($project_data['user_id'], 'shifang', -$project_data['price'], '项目认购 ' . $project_data['id']);
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('money', $project_data['price']);
                    break;
                case 'money_qiandao':
                    $user_money = UserMoney::where('user_id', $project_data['user_id'])->find();
                    if($user_money['money_qiandao']< $project_data['price']){
                        $this->error('签到收益余额不足');
                    }
                    UserMoney::money_in($project_data['user_id'], 'qiandao', -$project_data['price'], '项目认购 ' . $project_data['id']);
                    \app\api\model\User::where('id', $project_data['user_id'])->setDec('money', $project_data['price']);
                    break;
                // case 'money_shouyibao':
                //     $user_money = UserMoney::where('user_id', $project_data['user_id'])->find();
                //     if($user_money['money_shouyibao']< $project_data['price']){
                //         $this->error('收益宝余额不足');
                //     }
                //     UserMoney::money_in($project_data['user_id'], 'shouyibao', -$project_data['price'], '项目认购 ' . $project_data['id']);
                //     break;
                default:
                    $this->error('支付方式错误-2');

            }
            /*************start  活动时间：11月12日-12月5日 ****************************/
            if (time() > strtotime('2025-03-1 00:00:00') && time() < strtotime('2025-12-31 23:59:59') && $paytype !== 'borrow_money') {
                //消费券
                // $xiaofei = [
                //     '188' => ['188',  '188项目扩内消费券'],
                //     '896' => ['896',  '896项目扩内消费券'],
                //     '1796' => ['1796',  '1796项目扩内消费券'],
                //     '4996' => ['4996', '4996项目扩内消费券'],
                //     '7996' => ['7996', '7996项目扩内消费券'],
                //     '14996' => ['14996', '14996项目扩内消费券'],
                //     '29996' => ['29996', '29996项目扩内消费券']
                // ];
                // $xiaofeiData = $xiaofei[($project_data['price'] * 100) / $qty / 100];
                $xiaofei_data = [
                    'user_id' => $user['id'],
                    'money'   => $pdata['jifen'] * $qty,
                    'before'  => $user['score'] ?? 0,
                    'after'   => ($user['score'] ?? 0) + ($pdata['jifen'] * $qty),
                    'memo'    => $project_data['price'].'项目扩内消费券',
                    'createtime' => time(),
                    'type' => 'coupon',
                ];
                //内需券
                // $neixu = [
                //     '188' => ['188', '188项目扩内专属内需券'],
                //     '896' => ['896', '896项目扩内专属内需券'],
                //     '1796' => ['1796', '1796项目扩内专属内需券'],
                //     '4996' => ['4996', '4996项目扩内专属内需券'],
                //     '7996' => ['7996', '7996项目扩内专属内需券'],
                //     '14996' => ['14996', '14996项目扩内专属内需券'],
                //     '29996' => ['29996', '29996项目扩内专属内需券']
                // ];
                // $neixuData = $neixu[($project_data['price'] * 100) / $qty / 100];
                $neixu_data = [
                    'user_id' => $user['id'],
                    'money' => $pdata['neixuquan'] * $qty,
                    'before' => $user['neixuquan'] ?? 0,
                    'after' => ($user['neixuquan'] ?? 0) + ($pdata['neixuquan'] * $qty),
                    'memo' => $project_data['price'].'项目扩内专属内需券',
                    'createtime' => time(),
                    'type' => 'neixuquan',
                ];
                //内需补贴资金
                /*$zijin = [
                    '188' => ['18800', '188项目内需补贴资金'],
                    '896' => ['89600', '896项目内需补贴资金'],
                    '1796' => ['179600', '1796项目内需补贴资金'],
                    '3996' => ['399600', '3996项目内需补贴资金'],
                    '7996' => ['799600', '7996项目内需补贴资金'],
                    '14996' => ['1499600', '14996项目内需补贴资金'],
                    '29996' => ['2999600', '29996项目内需补贴资金']
                ];
                $zijinData = $zijin[($project_data['price'] * 100) / $qty / 100];
                $zijin_data = [
                    'user_id' => $user['id'],
                    'money'   => $zijinData[0] * $qty,
                    'before'  => $user['money'] ?? 0,
                    'after'   => ($user['money'] ?? 0) + ($zijinData[0] * $qty),
                    'memo' => $zijinData[1],
                    'createtime' => time(),
                    'type' => 'neixu',
                ];*/
                Db::startTrans();
                try {
                    //消费券
                    \app\api\model\User::where('id', $user['id'])->setInc('score',$pdata['jifen'] * $qty);
                    \app\api\model\UserMoneyLog::create($xiaofei_data);
                    //内需券
                    \app\api\model\User::where('id', $user['id'])->setInc('neixuquan',$pdata['neixuquan'] * $qty);
                    \app\api\model\UserMoneyLog::create($neixu_data);
                    //内需补贴资金
                    /*\app\api\model\User::where('id', $user['id'])->setInc('money',$zijinData[0] * $qty);
                    \app\api\model\UserMoney::where('user_id', $user['id'])->setInc('money', $zijinData[0] * $qty);
                    \app\api\model\UserMoney::where('user_id', $user['id'])->setInc('money_neixu', $zijinData[0] * $qty);
                    \app\api\model\UserMoneyInLog::create($zijin_data);
                    //$zijin_data['type'] = 'release_divid';
                    \app\api\model\UserMoneyLog::create($zijin_data);*/
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                //上级获得5%扩内专属消费券
                /*$up_user = \app\api\model\User::where('id', $user['upid'])->find(); 
                if(\app\api\model\Order::where('user_id', $up_user['id'])->where('status', 2)->sum('payprice') > 10000){
                    $price_score = ceil(($config['jifen'] * $qty / 100) * 5);
                    $detailed_data = [
                        'user_id' => $up_user['id'],
                        'money' => $price_score,
                        'before' => $up_user['neixuquan'] ?? 0,
                        'after' => ($up_user['neixuquan'] ?? 0) + $price_score,
                        'memo' => '下级认购'.$project_data['price'].'项目专属内需券',
                        'createtime' => time(),
                        'type' => 'neixuquan',
                    ];
                    $zijin_price = ceil(($config['jifen'] * $qty / 100) * 1000);
                    $zijin_data = [
                        'user_id' => $up_user['id'],
                        'money'   => $zijin_price,
                        'before'  => $up_user['money'] ?? 0,
                        'after'   => ($up_user['money'] ?? 0) + $zijin_price,
                        'memo' => '下级认购'.$project_data['price'].'项目内需补贴资金',
                        'createtime' => time(),
                        'type' => 'neixu',
                    ];
                    Db::startTrans();
                    try {
                        // 5%扩内专属消费券
                        \app\api\model\User::where('id', $up_user['id'])->setInc('neixuquan', $price_score);
                        \app\api\model\UserMoneyLog::create($detailed_data);
                        // 10%的内需补贴资金
                        \app\api\model\User::where('id', $up_user['id'])->setInc('money',$zijin_price);
                        \app\api\model\UserMoney::where('user_id', $up_user['id'])->setInc('money', $zijin_price);
                        \app\api\model\UserMoney::where('user_id', $up_user['id'])->setInc('money_neixu', $zijin_price);
                        \app\api\model\UserMoneyInLog::create($zijin_data);
                        //$zijin_data['type'] = 'release_divid';
                        \app\api\model\UserMoneyLog::create($zijin_data);
                        Db::commit();
                    } catch (Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                }

                //下级获得5%扩内专属消费券
                $sub_user = \app\api\model\User::where('upid', $user['id'])->field('id,neixuquan')->select();
                foreach ($sub_user  as  $sub){
                    $payprice = \app\api\model\Order::where('user_id', $sub['id'])->where('status', 2)->sum('payprice');
                    if($payprice > 10000){
                        $price_score = ceil(($config['jifen'] * $qty / 100) * 5);
                        $detailed_data = [
                            'user_id' => $sub['id'],
                            'money' => $price_score,
                            'before' => $sub['neixuquan'] ?? 0,
                            'after' => ($sub['neixuquan'] ?? 0) + $price_score,
                            'memo' => '上级认购'.$project_data['price'].'项目专属内需券',
                            'createtime' => time(),
                            'type' => 'neixuquan',
                        ];
                        $zijin_price = ceil(($config['jifen'] * $qty / 100) * 1000);
                        $zijin_data = [
                            'user_id' => $sub['id'],
                            'money'   => $zijin_price,
                            'before'  => $sub['money'] ?? 0,
                            'after'   => ($sub['money'] ?? 0) + $zijin_price,
                            'memo' => '上级认购'.$project_data['price'].'项目内需补贴资金',
                            'createtime' => time(),
                            'type' => 'neixu',
                        ];
                        Db::startTrans();
                        try {
                            // 5%扩内专属消费券
                            \app\api\model\User::where('id', $sub['id'])->setInc('neixuquan', $price_score);
                            \app\api\model\UserMoneyLog::create($detailed_data);
                            // 10%的内需补贴资金
                            \app\api\model\User::where('id', $sub['id'])->setInc('money',$zijin_price);
                            \app\api\model\UserMoney::where('user_id', $sub['id'])->setInc('money', $zijin_price);
                            \app\api\model\UserMoney::where('user_id', $sub['id'])->setInc('money_neixu', $zijin_price);
                            \app\api\model\UserMoneyInLog::create($zijin_data);
                            //$zijin_data['type'] = 'release_divid';
                            \app\api\model\UserMoneyLog::create($zijin_data);
                            Db::commit();
                        } catch (Exception $e) {
                            Db::rollback();
                            $this->error($e->getMessage());
                        }
                    }
                }*/
            }
            //活动外正常赠送
            //插入每日分红日志
            $keys = array_keys($datajson);
            $maxDay = (int)str_replace('日', '', end($keys));  // 从最后一个键获取天数
            $insertAll2 = [];
            for ($ii=1;$ii<=$maxDay;$ii++){
                $item= $config['day_fh'] * $qty;
                $fanli_money = $item;
                $fanli_money2 = $item * $bfb;//额外分红
                $fanli_time = strtotime(date('Y-m-d 00:00:01')) + (86400 * (int)($ii-1));
                $insertAll2[] = [
                    'user_id' => $this->auth->id,
                    'fanli_time' => $fanli_time,
                    'qty' => $qty,
                    'event'=>$event,
                    'fanli_money' => $fanli_money,
                    'fanli_money2' => $fanli_money2,
                    'project_data_id' => $project_data['project_data_id'],
                    'order_id' => $project_data['id'],
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
                        $fanli_time = strtotime(date('Y-m-d 00:00:01')) + (86400 * (int)$i);
                        $insertAll[] = [
                            'user_id' => $this->auth->id,
                            'fanli_time' => $fanli_time,
                            'qty' => $qty,
                            'event' => $event,
                            'fanli_money' => $fanli_money,
                            'fanli_money2' => $fanli_money2,
                            'project_data_id' => $project_data['project_data_id'],
                            'order_id' => $project_data['id'],
                            'status' => 1,
                        ];
                    }
                }
                Db::table('fa_project_task')->insertAll($insertAll);
                
                //购买套餐增加经验
                $jingyanData = [
                    'user_id' => $user['id'],
                    'score' => $config['jy'] * $qty,
                    'before' => $user['game_level_jy'],
                    'after' => $user['game_level_jy'] + ($config['jy'] * $qty),
                    'memo' => '项目认购增加经验',
                    'createtime' => time()
                ];
                \app\api\model\UserJingyanLog::create($jingyanData);
                \app\api\model\User::where('id', $project_data['user_id'])->setInc('game_level_jy', $config['jy'] * $qty);//经验
                if ($pdata['id']!=8) {
                    $this->duanwupay($project_data['user_id'],$pdata['money'],$qty);
                    $this->blindbox($project_data['user_id'],$pdata['money'],$qty,$this->auth->upid);
                    $this->armyday($project_data['user_id'],$pdata['money'],$qty,$this->auth->upid);
                    $this->armydaynew($project_data['user_id'],$pdata['money'],$qty,$this->auth->upid);
                    $this->numdevelop($project_data['user_id'],$pdata['money'],$qty,$this->auth->upid);
                    $this->new145($project_data['user_id'],$pdata['money'],$qty,$this->auth->upid);
                }
                if($this->auth->is_buy!=1&&$pdata['id']!=8){
                    if (time()<1767196799) {
                        $bay_count=Db::name('user')->where(['upid'=>$project_data['user_id'],'buy_time'=>['>',1754496000]])->count();
                        if ($bay_count==29) {
                            Db::name('user')->where(['id'=>$this->auth->upid2])->setInc('teams_num');
                        }
                    }
                    
                    \app\api\model\User::where('id', $project_data['user_id'])->update(['is_buy' => 1,'buy_time'=>time()]);//直推激活
                    //判断是否内需消费金活动是否开启
                    if (Config('site.is_nxxfj')&&$this->auth->createtime>1746633600) {
                    //七号开始注册的用户是新用户
                        //明年的今天
                        $time=time();
                        $updatetime=bcadd($time,172800);
                        $year = date('Y'); // 当前年份
                        $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
                        // if ($this->auth->createtime>1746633600) {
                            $nxxfj=Config('site.new_send_nxxfj');
                            $msg='新人注册激活认购套餐';
                        // }else{
                        //     $nxxfj=Config('site.old_send_nxxfj');
                        //     $msg='老用户回归首次认购套餐激活';
                        // }
                        if ($nxxfj) {
                            \app\common\model\User::nxxfj($nxxfj,$project_data['user_id'],$msg,'nxxfj');
                            $annualization=Config('site.annualization');
                            $dailyInterest=$this->getinterest($year,$nxxfj,$annualization[1]);
                            Db::name('nxxfjlist')->insert([
                                'money'=>$new_registerp_nxxfj,
                                'user_id'=>$project_data['user_id'],
                                'yeartime'=>1,
                                'year_rate'=>$annualization[1],
                                'expirytime'=>$nextYearTimestamp,
                                'status'=>1,
                                'interest'=>$dailyInterest,
                                'createtime'=>$time,
                                'updatetime'=>$updatetime,
                            ]);
                        }
                        
                        $this->duanwu($project_data['user_id'],$this->auth->upid);
                    }
                }
                $ins = [
                    'user_id' => $project_data['user_id'],
                    'money' => -$project_data['price'],
                    'before' => $user['allmoney'] ?? 0,
                    'after' =>  $user['allmoney'] ?? 0 + $project_data['price'],
                    'memo' => '项目认购 ' . $project_data['id'],
                    'createtime' => time(),
                    'type' => 'buy',
                ];
                \app\api\model\UserMoneyLog::create($ins);
                
                //团队分佣
                Cmd::checkyong($project_data);
            }

            /*************  活动时间：11月12日-12月5日  活动结束****************************/
            //更新订单数据
            \app\api\model\ProjectData::where('id', $project_data['project_data_id'])->setInc('thismoney', $project_data['price']);
            //更新订单状态
            $data = [
                'status' => 2,
                'payprice' => $price,
                'paytime' => time(),
            ];
            Db::table('fa_order')->where('id', $params['ids'])->update($data);
            $this->success('支付成功');

        }
        $this->error(__('Parameter %s can not be empty', ''));
    }
    
    private function new145($user_id,$money,$qty,$pid){
        $blindbox_time_Interval=config('site.145time');
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
            
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);

            // ========== 145活动激活奖励逻辑 ==========
            if ($this->auth->is_buy!=1) {
                // 首次激活送15000补贴金
                $activation_nxxfj = 15000;
                \app\common\model\User::nxxfj($activation_nxxfj,$user_id,'提振投资消费首次激活内需消费补贴金','nxxfj');
                $dailyInterest=$this->getinterest($year,$activation_nxxfj,$annualization[1]);
                $nxxfjlist[]=[
                    'money'=>$activation_nxxfj,
                    'user_id'=>$user_id,
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ];
                
                // 推荐人送5000补贴金
                if ($pid) {
                    $referral_nxxfj = 5000;
                    \app\common\model\User::nxxfj($referral_nxxfj,$pid,'提振投资消费下级激活内需消费补贴金','nxxfj');
                    $dailyInterest=$this->getinterest($year,$referral_nxxfj,$annualization[1]);
                    $nxxfjlist[]=[
                        'money'=>$referral_nxxfj,
                        'user_id'=>$pid,
                        'yeartime'=>1,
                        'year_rate'=>$annualization[1],
                        'expirytime'=>$nextYearTimestamp,
                        'status'=>1,
                        'interest'=>$dailyInterest,
                        'createtime'=>$time,
                        'updatetime'=>$updatetime,
                    ];
                }
            }
            // ========== 147活动激活奖励逻辑结束 ==========

            $contributiondata=[
                4996=>[3,1],
                7996=>[7,2],
                14996=>[15,5],
                29996=>[35,10],
            ];
            if (isset($contributiondata[$money])) {
                \app\common\model\User::contribution(bcmul($contributiondata[$money][0],$qty),$user_id,'购买产品内需贡献值');
                \app\common\model\User::contribution(bcmul($contributiondata[$money][1],$qty),$pid,'下级购买产品内需贡献值');
            }
            
            $m=bcmul($money,$qty,2);
            $m10=bcmul($m,10,2); // 只用于补贴金
            \app\common\model\User::nxxfj($m10,$user_id,'提振投资消费内需消费补贴金','nxxfj');
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
            $nxxfjlxdate=[
                1796=>1,
                4996=>58,
                7996=>128,
                14996=>288,
                29996=>688,
            ];
            if (isset($nxxfjlxdate[$money])) {
                $nxxfjlx=bcmul($nxxfjlxdate[$money],$qty,2);
                \app\common\model\User::nxxfj($nxxfjlx,$user_id,'提振投资消费内需消费补贴金利息','nxxfjlx');
            }

            $nxqdata=[
                '896'=>448,
                '1796'=>2155,
                '4996'=>6995,
                '7996'=>12795,
                '14996'=>26995,
                '29996'=>59995,
            ];
            if (isset($nxqdata[$money])) {
                $neixu_m=bcmul($nxqdata[$money],$qty,2);
                $neixu_data = [
                    'user_id' => $user_id,
                    'money' => $neixu_m,
                    'before' => $this->auth->neixuquan ?? 0,
                    'after' => ($this->auth->neixuquan ?? 0) + $neixu_m,
                    'memo' => '提振投资消费专属内需券',
                    'createtime' => $time,
                    'type' => 'neixuquan',
                ];
                
                \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$neixu_m);
                \app\api\model\UserMoneyLog::create($neixu_data);
            }
        }
    }
    
    private function numdevelop($user_id,$money,$qty,$pid){
        $blindbox_time_Interval=config('site.num_develop_time');
        if (!$blindbox_time_Interval) {
            return true;
        }
        // 拆分时间段
        list($start, $end) = explode(' - ', $blindbox_time_Interval);
        
        // 转换为时间戳
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $now = time(); // 当前时间戳
        // 验证活动时间 - 只有大于896的产品才送白名单次数
        if ($now >= $startTime && $now <= $endTime && $money>896) {
            \app\api\model\User::where('id', $user_id)->setInc('develop',$qty);
        }
        // 获取用户审核进度值
        $user_schedule = config('site.develop'.$money);
        // 获取推荐人审核进度值（key改为英文）
        $pid_schedule = config('site.develop'.$money.'_parent');
        
        if ($user_schedule) {
            $user_total = bcmul($user_schedule, $qty);
            $this->assignSchedule($user_id, $user_total);   // 分配给自己
        }
        
        if ($pid_schedule && $pid) {
            $pid_total = bcmul($pid_schedule, $qty);
            $this->assignSchedule($pid, $pid_total);       // 分配给推荐人
        }
    }
    private function assignSchedule($userId, $total)
    {
        $limit=bcdiv($total,100);
        $limit=bcadd($limit,2);
        $tasks = Db::name('develop')->where([
            'schedule'=> ['<', 100],
            'uid'=> $userId,
            'status'=> 0,
        ])->order('id')->limit($limit)->select();
    
        foreach ($tasks as $task) {
            $canAdd = 100 - $task['schedule'];
            $add = min($total, $canAdd);
            $newSchedule = $task['schedule'] + $add;
            $newStatus = $newSchedule >= 100 ? 1 : 0;
    
            Db::name('develop')->where('id', $task['id'])->update([
                'schedule' => $newSchedule,
                'status' => $newStatus,
            ]);
            $total -= $add;
            if ($total <= 0) break;
        }
    }
    private function armydaynew($user_id,$money,$qty,$pid){
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
            if ($this->auth->is_buy!=1) {
                //激活统计激活人数;
                
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
                    'before' => $this->auth->game_level_jy,
                    'after' => $this->auth->game_level_jy + $m,
                    'memo' => '促消费·惠民生·共筑强军梦增加经验',
                    'createtime' => $time
                ];
                \app\api\model\UserJingyanLog::create($jingyanData);
                \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $m);//经验
            }
        }
    }
    private function armyday($user_id,$money,$qty,$pid){
        $blindbox_time_Interval=config('site.partyfoundingday_time');
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
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            if ($this->auth->is_buy!=1) {
                //激活送内需消费金
                $blindbox_activation_nxxfj=config('site.blindbox_activation_nxxfj');
                if ($blindbox_activation_nxxfj) {
                    \app\common\model\User::nxxfj($blindbox_activation_nxxfj,$user_id,'红色引领促消费，惠民扩需庆华诞激活奖励','nxxfj');
                    
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
                    \app\common\model\User::nxxfj($blindbox_actupid_nxxfj,$pid,'红色引领促消费，惠民扩需庆华诞推荐激活奖励','nxxfj');
                }
            }
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
            }
            
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
            //购买套餐增加经验
            $jingyanData = [
                'user_id' => $user_id,
                'score' => $m,
                'before' => $this->auth->game_level_jy,
                'after' => $this->auth->game_level_jy + $m,
                'memo' => '红色引领促消费庆华诞增加经验',
                'createtime' => $time
            ];
            \app\api\model\UserJingyanLog::create($jingyanData);
            \app\api\model\User::where('id', $user_id)->setInc('game_level_jy', $m);//经验
            $neixu_m=bcdiv($m,2,2);
            $neixu_data = [
                'user_id' => $user_id,
                'money' => $neixu_m,
                'before' => $this->auth->neixuquan ?? 0,
                'after' => ($this->auth->neixuquan ?? 0) + $neixu_m,
                'memo' => '红色引领促消费庆华诞专属内需券',
                'createtime' => $time,
                'type' => 'neixuquan',
            ];
            
            \app\api\model\User::where('id', $user_id)->setInc('neixuquan',$neixu_m);
            \app\api\model\UserMoneyLog::create($neixu_data);
            
            
        }
    }
    private function blindbox($user_id,$money,$qty,$pid){
        $blindbox_time_Interval=config('site.blindbox_time_Interval');
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
            $annualization=Config('site.annualization');
            $nxxfjlist=[];
            //今年的最后一天
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $time=time();
            $updatetime=bcadd($time,172800);
            
            if ($this->auth->is_buy!=1) {
                //激活送内需消费金
                $blindbox_activation_nxxfj=config('site.blindbox_activation_nxxfj');
                if ($blindbox_activation_nxxfj) {
                    \app\common\model\User::nxxfj($blindbox_activation_nxxfj,$user_id,'彩蛋活动激活奖励内需消费补贴金','nxxfj');
                    
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
                    \app\common\model\User::nxxfj($blindbox_actupid_nxxfj,$pid,'彩蛋活动推荐激活奖励内需消费补贴金','nxxfj');
                }
            }
            $m=bcmul($money,$qty,2);
            $m=bcmul($m,10,2);
            \app\common\model\User::nxxfj($m,$user_id,'彩蛋活动购买产品奖励内需消费补贴金','nxxfj');
            $dailyInterest=$this->getinterest($year,$m,$annualization[1]);
            $nxxfjlist[]=[
                'money'=>$m,
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
            if ($money>899) {
                \app\common\model\User::eastereggs($qty,$user_id,'彩蛋活动购买产品','level'.$money);
            }
        }
    }
    private function duanwupay($user_id,$money,$qty){
        $duanwu_time_Interval=config('site.duanwu_time_Interval');
        // 拆分时间段
        list($start, $end) = explode(' - ', $duanwu_time_Interval);
        
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
            \app\common\model\User::nxxfj(bcmul($money,$qty,2),$user_id,'粽有乾坤活动购买产品奖励内需消费补贴金','nxxfj');
            $dailyInterest=$this->getinterest($year,$money,$annualization[1]);
            $nxxfj=[
                'money'=>$money,
                'user_id'=>$user_id,
                'yeartime'=>1,
                'year_rate'=>$annualization[1],
                'expirytime'=>$nextYearTimestamp,
                'status'=>1,
                'interest'=>$dailyInterest,
                'createtime'=>$time,
                'updatetime'=>$updatetime,
            ];
            for ($i = 0; $i < $qty; $i++) {
                $nxxfjlist[]=$nxxfj;
            }
            
            if ($nxxfjlist) {
                Db::name('nxxfjlist')->insertAll($nxxfjlist);
            }
            \app\common\model\User::lottery($qty,$user_id,'粽有乾坤活动购买产品','level'.$money);
            
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
    /**
     * 检查订单状态
     */
    public function check_order_status()
    {
        return true;
    }

    //获取项目类型名称
    public function _get_project_typename($id){
        switch ($id) {
            case 8:
                $typename = "188型";
                break;
            case 9:
                $typename = "896型";
                break;
            case 10:
                $typename = "1796型";
                break;
            case 11:
                $typename = "4996型";
                break;  
            case 12:
                $typename = "7996型";
                break;
            case 13:
                $typename = "14996型";
                break;
            case 14:
                $typename = "29996型";
                break;
            case 999:
                $typename = "新人型";
                break;
            default:
                $typename = "未知类型";
                break;
        }
        return $typename;
    }

}
