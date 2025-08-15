<?php

namespace app\admin\controller\user;

use app\admin\model\UserMoney;
use app\common\controller\Backend;
use app\common\library\Auth;
use Exception;
use fast\Random;
use think\Db;
use think\exception\HttpResponseException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Request;
use think\Response;
use app\common\model\MoneyLog;
use app\api\model\UserMoneyInLog;
use app\api\model\UserMoneyLog;
use app\api\model\Level;
use think\Validate;
use think\Config;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
        $this->view->assign("getAccType", $this->model->getAccType());
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            // list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $params = json_decode(input('filter'), true);
            if (isset($params['ipname']) && $params['ipname'] != '') {
                list($where, $sort, $order, $offset, $limit) = $this->buildparams();
                $where = [];
                if (isset($params['ipname']) && $params['ipname'] != '') {
                    $where['user.loginip'] = ['like', '%' . $params['ipname'] . '%'];
                }
            }elseif (isset($params['uusername']) && $params['uusername'] != '') {
                list($where, $sort, $order, $offset, $limit) = $this->buildparams();
                $where = [];
                if (isset($params['uusername']) && $params['uusername'] != '') {
                    $u=Db::name('user')->where(['username'=>$params['uusername']])->field('id,upid,upid2,upid3')->find();
                    $ids=[];
                    foreach ($u as $v1=>$v2){
                        if ($v2) {
                            $ids[]=$v2;
                        }
                    }
                    if ($u['upid3']) {
                        $ids=$this->getAllParents($u['upid3'],$ids);
                    }
                    $where['user.id'] = ['in', $ids];
                }
            } else {
                list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            }
        
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    function getAllParents($id, &$result = []) {
        // 查询当前节点的信息
        
//   `upid` bigint(255) DEFAULT '0' COMMENT '上级ID',
//   `upid2` bigint(255) DEFAULT '0' COMMENT '上级ID',
//   `upid3` bigint(255) DEFAULT '0' COMMENT '上级ID',
        $user = Db::name('user')->where('id', $id)->field('upid,upid2,upid3')->find();
        if ($user) {
            $result[] = $user['upid'];
            $result[] = $user['upid2'];
            $result[] = $user['upid3'];
            if ($user['upid3']) {
                $this->getAllParents($user['upid3'], $result); // 递归查找父级
            }
        }
        return $result;
    }
    public function indexcard()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function indexshebao()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function indexshiming()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
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

    /**
     * 添加
     */
    // public function add()
    // {
    //     if ($this->request->isPost()) {
    //         $this->token();
    //     }
    //     if (false === $this->request->isPost()) {
    //         return $this->view->fetch();
    //     }
    //     $params = $this->request->post('row/a');
    //     if (empty($params)) {
    //         $this->error(__('Parameter %s can not be empty', ''));
    //     }
    //     $params = $this->preExcludeFields($params);

    //     if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
    //         $params[$this->dataLimitField] = $this->auth->id;
    //     }
    //     $result = false;


    //     $data = array_merge($params, [
    //         'salt' => Random::alnum(),
    //         'invite' => 10000000 + \app\common\model\User::Order('id desc')->value('id') + 1,
    //         'jointime' => time(),
    //         'joinip' => '127.0.0.1',
    //         'logintime' => time(),
    //         'loginip' => '127.0.0.1',
    //         'prevtime' => time(),
    //         'status' => 'normal'
    //     ]);
    //     if ($params['password']) {
    //         $data['password'] = $this->getEncryptPassword($params['password'], $data['salt']);
    //     }

    //     Db::startTrans();
    //     try {
    //         //是否采用模型验证
    //         if ($this->modelValidate) {
    //             $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
    //             $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
    //             $this->model->validateFailException()->validate($validate);
    //         }
    //         $result = $this->model->allowField(true)->save($data);
    //         Db::commit();
    //     } catch (ValidateException|PDOException|Exception $e) {
    //         Db::rollback();
    //         $this->error($e->getMessage());
    //     }
    //     if ($result === false) {
    //         $this->error(__('No rows were inserted'));
    //     }
    //     $this->success();
    // }

    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $username = $params['username'];
        $password = $params['password'];
        $email = $this->request->post('email');
        $mobile = $params['username'];
        $nickname = $params['nickname'];
        $address = $this->request->post('address');
        $code = $this->request->post('code');
        $invite = $params['invite'];
        $idNumber = $params['idNumber'];
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
        $is_user=Db::name('user')->where(['mobile'=>$mobile])->count();
        if ($is_user) {
            $this->error(__('用户已经存在'));
        }
        if(!$idNumber)
        {
            $this->error(__('请输入身份证'));
        }
        if(!$invite)
        {
            $this->error(__('请输入邀请码'));
        }
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
        
        if($nickname){
            $pattern = '/^[\p{Han}\p{P}・a-zA-Z\s]+$/u'; // 允许汉字、标点符号、英文字母和空格
            if (!preg_match($pattern, $nickname) || mb_strlen($nickname, 'UTF-8') < 2) {
                $this->error(__('姓名格式不正确或长度过短'));
            }
        }
        
        //redis防重复点击
        $symbol = "register" . $mobile;
        $submited = pushRedis($symbol);
        if (!$submited) {
            $this->error(__("操作频繁"));
        }

        if ($invite) {
            $w["invite"] = array( "eq" , $invite );
            $p_info = Db::name('user')->field("id,upid")->where($w)->find();
            if(!$p_info){
                lopRedis($symbol);
                $this->error(__("推荐码不存在"));
            }
            $upid=$p_info['id'];
            $upid2=$p_info['upid'];
            $upid3 = \app\api\model\User::where('id', $upid2)->value('upid') ?? 0;
            $this->yao($upid);//直属邀请奖励

            $user_invite = Db::name('user')->where('invite', $invite)->find();
            if ($user_invite['day_share_num'] < 4) {
                // \app\api\model\User::where('id',  $upid)->setInc('day_share_num');
                // \app\api\model\User::where('id',  $upid)->setInc('score', 3);
                $detailed_data = [
                    'user_id' => $upid,
                    'money' => 3,
                    'before' => $user_invite['score'],
                    'after' => $user_invite['score'] + 3,
                    'memo' => '邀请好友',
                    'createtime' => time(),
                    'type' => 'coupon',
                ];
                // \app\api\model\UserMoneyLog::create($detailed_data);
            }

        } else {
            $upid = 0;
            $upid2 = 0;
            $upid3 = 0;
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
            'is_renzheng' => $is_renzheng
        ];
        $userAuth=new \app\common\library\Auth;
        $ret = $userAuth->register($username, $password, $email, $mobile, $extend);
        if ($ret) {
            $data = ['userinfo' => Db::name('user')->where(['mobile'=>$mobile])->find()];
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

            $this->success(__('注册成功'));
        } else {
            $this->error(__($userAuth->getError()));
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
     * 编辑
     */
    public function edit($ids = null)
    {
        
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $w1 = [];
            $w1['id'] = array("eq", $ids);
            $r1 = Db::name("user")->where($w1)->find();
            if (!$r1) {
                $this->error("未查询到信息");
            }
            $params['prevtime'] = strtotime($params['prevtime']);
            $params['logintime'] = strtotime($params['logintime']);
            $params['jointime'] = strtotime($params['jointime']);
            $this->model->whereIn('id', $ids)->update($params);
            $this->success('操作成功', request()->url());
        }

        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }


    /**
     * 编辑
     */
    // public function gaimi($ids = null)
    // {
    //     if ($this->request->isPost()) {
    //         $this->token();
    //     }
    //     $row = $this->model->get($ids);
    //     $this->modelValidate = true;
    //     if (!$row) {
    //         $this->error(__('No Results were found'));
    //     }
    //     $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
    //     return parent::edit($ids);
    // }
    
    public function gaimi($ids = null)
    {
        $row = $this->model->get($ids);
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params['password']) {
                $params['salt'] = Random::alnum();
                $params['password'] = $this->getEncryptPassword($params['password'], $params['salt']);
            } else {
                unset($params['password']);
            }
            if ($params['password2']) {
                $params['pay_pwd'] = strtoupper(md5(strtoupper(md5($params['password2'] . 'skund'))));
                unset($params['password2']);
            } else {
                unset($params['pay_pwd']);
            }
            $this->model->whereIn('id', $ids)->update($params);
            $this->success('操作成功', request()->url());
        } else {
            $this->modelValidate = true;
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $this->view->assign('row', $row);
            return view();
        }
    }


    /**
     * 编辑
     */
    public function chong($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $user_money = UserMoney::where('user_id',$row['id'])->field(['money','money_tuijian','money_shifang','money_qiandao','money_quanhuan','money_choujiang','money_neixu','money_zhengcejin'])->find();
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        $this->view->assign("userMoney",$user_money);
        return parent::edit($ids);
    }


    /**
     * 编辑
     */
    public function shiming($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }
    public function shiming2($ids){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=input('row/a');

            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }

    public function shebao($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    public function card($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    public function team($ids)
    {
        $this->assign('uid', $ids);


        $ids1=\app\admin\model\User::where('upid',$ids)->column('id')??[];
        $ids2=\app\admin\model\User::where('upid2',$ids)->column('id')??[];
        $ids3=\app\admin\model\User::where('upid3',$ids)->column('id')??[];

        $data1 = \Tool\Tongji::getDataTeam((date('Y-m-d 00:00:00')), (date('Y-m-d 23:59:59')),$ids1,$ids);
        $data2 = \Tool\Tongji::getDataTeam((date('Y-m-d 00:00:00')), (date('Y-m-d 23:59:59')),$ids2);
        $data3 = \Tool\Tongji::getDataTeam((date('Y-m-d 00:00:00')), (date('Y-m-d 23:59:59')),$ids3);
        $this->assign('data1', $data1);
        $this->assign('data2', $data2);
        $this->assign('data3', $data3);


        return view();
    }
    public function teamtongji($ids)
    {


        $date = input('date');
        $date = explode(' - ', $date);
        if (!empty($date) && count($date)==2) {
            $strtime = $date[0];
            $endtime = $date[1];
        } else {
            $strtime = date('Y-m-d 00:00:00');
            $endtime = date('Y-m-d 23:59:59');
        }



        $ids1=\app\admin\model\User::where('upid',$ids)->column('id')??[];
        $ids2=\app\admin\model\User::where('upid2',$ids)->column('id')??[];
        $ids3=\app\admin\model\User::where('upid3',$ids)->column('id')??[];

        $data1 = \Tool\Tongji::getDataTeam($strtime,$endtime,$ids1,$ids);
        $data2 = \Tool\Tongji::getDataTeam($strtime,$endtime,$ids2);
        $data3 = \Tool\Tongji::getDataTeam($strtime,$endtime,$ids3);


        return $this->result2('有新提现消息', compact('data1','data2','data3'));

    }

    protected $responseType = 'json';

    protected function result2($msg, $data = null, $code = 1, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }



    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }
    
    public function update_user_type()
    {
        if (request()->isPost()) {
            $data = $this->request->post();
            $ids = $data['ids'];
            $is_rot = $data['is_rot'];
            foreach ($ids as $key => $value) {
                $w1['id'] = array("eq", $value);
                $r1 = Db::name("user")->where($w1)->find();
                if ($r1) {
                    Db::name("user")->where($w1)->update(['is_rot' => $is_rot]);
                }
            }
            $this->success('操作成功', request()->url());
        } else {
            return view();
        }
    }
    public function nxxfjlxlist(){
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->where('is_rot', 1) // 只显示正常用户，排除机器人
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }
    public function nxxfjlist(){
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            // 只显示正常用户，排除机器人
            $list = $this->model
                ->where($where)
                ->where('is_rot', 1)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }
}
