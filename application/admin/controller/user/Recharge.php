<?php

namespace app\admin\controller\user;

use addons\epay\library\Service;
use app\common\controller\Backend;
use think\Exception;
use Yansongda\Pay\Pay;
use think\Db;

/**
 * 提现管理
 *
 * @icon fa fa-circle-o
 */
class Recharge extends Backend
{

    /**
     * Withdraw模型对象
     * @var \app\admin\model\user\Recharge
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Recharge;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    //查询转账状态
    public function query()
    {
        $ids = $this->request->param('ids', '');
        $model = $this->model->where('id', $ids)->find();
        if (!$model) {
            $this->error(__('No Results were found'));
        }
        $info = get_addon_info('epay');
        if (!$info || !$info['state']) {
            $this->error('请检查微信支付宝整合插件是否正确安装且启用');
        }
        $result = null;
        try {
            $config = Service::getConfig('alipay');
            $pay = Pay::alipay($config);
            $result = $pay->find($model['orderid'], 'transfer');

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        if ($result && isset($result['code']) && $result['code'] == 10000) {
            $this->success("转账成功！");
        } else {
            $this->error('转账失败！');
        }
    }
    
    
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            // $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                try {
                    $user_curr = Db::name("user")->where("id",$params['user_id'])->find();
                    $check = $this->model->where('id',$ids)->find();
                    if($params['status'] == 1 && $check['status'] != 1){
                        $detailed_data = array(
                            "user_id" => $params['user_id'],
                            "money" => $params['num'],
                            "type" => "recharge",
                            "memo" => $params['notice'],
                            "createtime" => time(),
                            "before" => $user_curr['nomoney'],
                            "after" => $user_curr['nomoney']+$params['num'],
                        );
                        $res1 = Db::name("user_money_log")->insert($detailed_data);
                        $res2 = Db::name("user")->where("id",$params['user_id'])->setInc("nomoney",$params['num']);
                    }
                    $this->model->whereIn('id',$ids)->update($params);
                    $this->success(__('successful'));
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }



    public function editss($ids){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=input('row/a');
            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }


    public function editssall($ids=null){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=input('row/a');


            $this->model->where('id','neq',0)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }


    public function ok($ids=null){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=['status'=>1];
            $params = $this->model->where('id',$ids)->find();
            if($params['status'] != 1){
                $user_curr = Db::name("user")->where("id",$params['user_id'])->find();
                $detailed_data = array(
                    "user_id" => $params['user_id'],
                    "money" => $params['num'],
                    "type" => "recharge",
                    "memo" => $params['notice'],
                    "createtime" => time(),
                    "before" => $user_curr['nomoney'],
                    "after" => $user_curr['nomoney']+$params['num'],
                );
                $res1 = Db::name("user_money_log")->insert($detailed_data);
                $res2 = Db::name("user")->where("id",$params['user_id'])->setInc("nomoney",$params['num']);
            }
            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }
    
    public function no($ids=null){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=['status'=>0];
            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }

}
