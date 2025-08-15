<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 内容管理
 *
 * @icon fa fa-circle-o
 */
class UserCoupon extends Backend
{

    /**
     * Message模型对象
     * @var \app\admin\model\UserCoupon
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
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
                        $new_params['aa.nickname'] = $value;
                        $new_op['aa.nickname'] = $op[$key];
                    } else {
                        $new_params['aa.' . $key] = $value;
                        $new_op['aa.' . $key] = $op[$key];
                    }
                }
                $w = $this->rewriteQuery($new_params, $new_op);
            } else {
                $w['aa.id'] = array('>', 0);

            }
            $w['aa.is_rot'] =1;
            $total = Db::name('user')
                ->alias('aa')
                ->field('aa.*')
                ->where($w)
                ->count();
            $list = Db::name('user')
                ->alias('aa')
                ->field('aa.*')
                ->where($w)
                ->order('aa.score desc')
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k=>$i){
                $list[$k]['exchange'] = Db::name('user_money')->where('user_id',$i['id'])->find()['money_quanhuan'];
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
}
