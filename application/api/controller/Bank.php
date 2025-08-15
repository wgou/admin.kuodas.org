<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class Bank extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = [];
	protected $_allow_func = ['index','add','edit','view','del','bank_default','set_default'];


	use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\Bank;
	}
    /**
     * 公共方法-列表
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',',$this->_search_field));
        $this->_init_where['user_id']=$this->auth->id;
        $item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));
        $this->success('数据列表', $list);
    }


    /**
     * 公共方法-添加
     */
    public function add()
    {
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        $user = Db::name('user')->where('id',$this->auth->id)->find();
        if(!isset($params['bank_username'])){
            $this->error('请输入开户人姓名');
        }
        // 姓名比较和调试信息
        $bank_username = trim($params['bank_username']);
        $user_nickname = trim($user['nickname']);
        
        // 更宽松的比较：去除所有空白字符，统一处理全角半角
        $bank_username_clean = preg_replace('/\s+/u', '', $bank_username);
        $user_nickname_clean = preg_replace('/\s+/u', '', $user_nickname);
        
        // 全角半角转换
        $bank_username_clean = mb_convert_kana($bank_username_clean, 'as', 'UTF-8');
        $user_nickname_clean = mb_convert_kana($user_nickname_clean, 'as', 'UTF-8');
        
        if($bank_username_clean != $user_nickname_clean){
            $this->error('收款人姓名与账户姓名不符', [
                '提交的开户人姓名' => $bank_username,
                '账户姓名' => $user_nickname,
                '清理后的开户人姓名' => $bank_username_clean,
                '清理后的账户姓名' => $user_nickname_clean,
                '提交姓名长度' => mb_strlen($bank_username),
                '账户姓名长度' => mb_strlen($user_nickname),
                '提交姓名字节' => bin2hex($bank_username),
                '账户姓名字节' => bin2hex($user_nickname),
                '是否相等' => $bank_username_clean === $user_nickname_clean ? '是' : '否'
            ]);
        }
        if(!isset($params['bank_name'])){
            $this->error('请输入银行卡号');
        }
        if(!isset($params['bank_number'])){
            $this->error('请输入开户银行');
        }
        if(!isset($params['bank_zhi'])){
            $this->error('请输入支行地址');
        }
        
        if ($params) {
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            
            Db::startTrans();
            $params = $this->__handle_add_before__($params);
            
            $this->__handle_filter__('add', $params);
            
            $params['user_id']=$this->auth->id;
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
            } 
            
            else {
                Db::rollback();
                $this->error($this->model->getError());
            }
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    public function edit()
    {
        $params = $this->request->post();
        $user = Db::name('user')->where('id',$this->auth->id)->find();
        if(!isset($params['bank_username'])){
            $this->error('请输入开户人姓名');
        }
        // 姓名比较和调试信息
        $bank_username = trim($params['bank_username']);
        $user_nickname = trim($user['nickname']);
        
        // 更宽松的比较：去除所有空白字符，统一处理全角半角
        $bank_username_clean = preg_replace('/\s+/u', '', $bank_username);
        $user_nickname_clean = preg_replace('/\s+/u', '', $user_nickname);
        
        // 全角半角转换
        $bank_username_clean = mb_convert_kana($bank_username_clean, 'as', 'UTF-8');
        $user_nickname_clean = mb_convert_kana($user_nickname_clean, 'as', 'UTF-8');
        
        if($bank_username_clean != $user_nickname_clean){
            $this->error('开户人姓名与用户姓名不一致', [
                '提交的开户人姓名' => $bank_username,
                '账户姓名' => $user_nickname,
                '清理后的开户人姓名' => $bank_username_clean,
                '清理后的账户姓名' => $user_nickname_clean,
                '提交姓名长度' => mb_strlen($bank_username),
                '账户姓名长度' => mb_strlen($user_nickname),
                '提交姓名字节' => bin2hex($bank_username),
                '账户姓名字节' => bin2hex($user_nickname),
                '是否相等' => $bank_username_clean === $user_nickname_clean ? '是' : '否'
            ]);
        }
        if ($params) {
            $user_id = $this->auth->id;
            $id = $params['id'];
            //check parameters
            if(!$user_id) $this->error(__('user_id 需要'));
            if(!$id) $this->error(__('id 需要'));
            //check record
            $bank = Db::name('bank')->where('id', $id)->where('user_id', $user_id)->find();
            if(!$bank) $this->error(__('找不到记录'));
            $status =  $params['status'] ?? 0;
            $p = [
                'status' => $status,
                'bank_username' => $params['bank_username'] ?? '',
                'bank_name' => $params['bank_name'] ?? '',
                'bank_number' => $params['bank_number'] ?? 0,
                'bank_zhi' => $params['bank_zhi'] ?? '',
            ];
            Db::name('bank')->where('id', $id)->where('user_id', $user_id)->update($p);
            if($status == 1) Db::name('bank')->where('id',"<>",$id )->where('user_id', $user_id)->update(['status' => 0]);
            $this->success('成功的');
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    public function set_default()
    {
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if ($params) {
            $user_id = $this->auth->id;
            $id = $params['id'];
            //check parameters
            if(!$user_id) $this->error(__('user_id 需要'));
            if(!$id) $this->error(__('id 需要'));
            //check record
            $bank = Db::name('bank')->where('id', $id)->where('user_id', $user_id)->find();
            if(!$bank) $this->error(__('找不到记录'));

            Db::name('bank')->where('id', $id)->where('user_id', $user_id)->update(['status' => 1]);
            Db::name('bank')->where('id',"<>",$id )->where('user_id', $user_id)->update(['status' => 0]);
            $this->success('成功的');
        }
        $this->error(__('Parameter %s can not be empty', ''));
    }

    public function bank_default()
    {
        $user_id = $this->auth->id;
        if(!$user_id) $this->error(__('user_id 需要'));
        $bank = Db::name('bank')->where('user_id', $user_id)->order('id asc')->select();
        if(!$bank) $this->error(__('尚未添加银行'));
        if($bank && count($bank) > 0){
            $default = $bank[0];
            foreach ($bank as $key => $value) {
                if($value['status'] === 1){
                    $default = $value;
                }
            }
            $this->success('成功的',$default);
        }
    }
}
