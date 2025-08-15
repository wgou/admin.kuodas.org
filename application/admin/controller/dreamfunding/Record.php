<?php

namespace app\admin\controller\dreamfunding;

use app\common\controller\Backend;

/**
 * 圆梦募资统计信息记录
 *
 * @icon fa fa-circle-o
 */
class Record extends Backend
{
    /**
     * Record模型对象
     * @var \app\admin\model\dreamfunding\Record
     */
    protected $model = null;
    
    // 无需鉴权的方法
    protected $noNeedRight = ['index'];
    
    // 禁用的方法
    protected $noNeedLogin = [];
    
    protected $dataLimit = false;
    protected $dataLimitField = 'admin_id';
    
    // 禁用增删改查
    protected $allowAdd = false;
    protected $allowEdit = false;
    protected $allowDel = false;
    protected $allowMulti = false;
    
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\dreamfunding\Record;
        
        // 禁用所有权限按钮
        $this->view->assign([
            'auth_add' => false,
            'auth_edit' => false,
            'auth_del' => false,
            'auth_multi' => false
        ]);
    }
    
    /**
     * 查看列表
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                    ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['username','is_rot']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }
}
