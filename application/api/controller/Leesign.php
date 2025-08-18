<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class Leesign extends Api
{

    protected $model = null;

    protected $noNeedRight = '*';
    protected $noNeedLogin = [];
    protected $_allow_func = ['index', 'add', 'edit', 'view', 'lis'];


    use \app\api\library\buiapi\traits\Api;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\api\model\Leesign;
    }

    /**
     * 公共方法-列表
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter('trim,strip_tags,xss_clean');
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $mixWhere = $this->buildwheres(implode(',', $this->_search_field));
        $item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));

        $this->success('数据列表', $list);
    }

    public function lis()
    {
        $list = Db::table('fa_leesign')->where('uid', $this->auth->id)->order('id desc')->column('sign_time');
        foreach ($list as $i => $dat) {
            $list[$i] = explode(' ', $dat)[0];
        }

        $allmoney = Db::table('fa_leesign')->where('uid', $this->auth->id)->order('id desc')->sum('sign_reward');;
        $allqd = Db::table('fa_leesign')->where('uid', $this->auth->id)->order('id desc')->count();;
        $this->success('数据列表', compact('list', 'allmoney', 'allqd'));
    }


}
