<?php

namespace app\admin\controller\dreamfunding;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 圆梦募资项目管理
 *
 * @icon fa fa-circle-o
 */
class Project extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,name';
    protected $orderby = 'id,desc';
    
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\dreamfunding\Project;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                // 处理金额选项
                if (isset($params['amount_options']) && is_array($params['amount_options'])) {
                    $options = array_values(array_filter($params['amount_options'], function($value) {
                        return $value !== '';
                    }));
                    $params['amount_options'] = $options;
                }

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException()->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($this->model->getError());
                    }
                } catch (ValidateException|PDOException|Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                // 处理金额选项
                if (isset($params['amount_options']) && is_array($params['amount_options'])) {
                    $options = array_values(array_filter($params['amount_options'], function($value) {
                        return $value !== '';
                    }));
                    $params['amount_options'] = $options;
                }

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $this->model->validateFailException()->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success('', null, ['id' => $row->id]);
                    } else {
                        $this->error($row->getError());
                    }
                } catch (ValidateException|PDOException|Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        
        // 确保 amount_options 是数组
        if (is_string($row['amount_options'])) {
            $row['amount_options'] = json_decode($row['amount_options'], true) ?: [];
        }
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
