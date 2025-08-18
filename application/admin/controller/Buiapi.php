<?php

namespace app\admin\controller;

use think\Db;
use think\Config;
use think\Request;
use app\common\controller\Backend;
use app\admin\library\buiapi\BuiApiFunc;
use app\admin\library\buiapi\BuiApiTemp;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 模型管理管理
 */
class Buiapi extends Backend
{

    /**
     * ApiTable模型对象
     */
    protected $model_table = null;
    protected $model_field = null;


    public function _initialize()
    {
        parent::_initialize();
        $this->model_table = new \app\admin\model\BuiapiTable;
        $this->model_field = new \app\admin\model\BuiapiField;
        $actionname = strtolower($this->request->action());
        if (in_array($actionname, ['execcommand']) && !config("app_debug")) {
            $this->error("请先开启DEBUG调试模式");
        }
    }

    /**
     * 01、数据库列表
     */
    public function index()
    {
        $this->request->filter('trim,strip_tags,htmlspecialchars');
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model_table->where($where)->order($sort, $order)->count();
            $list = $this->model_table->where($where)->order($sort, $order)->limit($offset, $limit)->select();
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 02、同步数据库
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a", [], 'trim');
            if ($params) {
                $params = $this->preExcludeFields($params);

                //判断参数
                if (empty($tables = $params['tables'])) {
                    $this->error("参数不能为空");
                }
                //处理数据
                $params = BuiApiFunc::getTableFormatData($tables);
                $fields = BuiApiFunc::getTableFieldFormatData($params);
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model_table->insertAll($params);
                    $resultFields = $this->model_field->insertAll($fields);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false && $resultFields !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $debar = [];
        $field_list = collection($this->model_table->field(['table'])->select())->toArray();
        if (!empty($field_list)) {
            $debar = array_column($field_list, "table");
        }
        $this->view->assign('tableList', BuiApiFunc::getTableList($debar));
        return $this->view->fetch();
    }

    /**
     * 03、 删除数据库
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model_table->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model_table->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model_table->where($pk, 'in', $ids)->select();
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $this->model_field->where(['table' => trim($v['table'])])->delete();
                    $count += $v->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /*
     * 04、规则列表
     */
    public function rulelist()
    {
        $table = trim($this->request->request('table'));
        $list = $this->model_field->where("table", '=', $table)->where(['is_show' => 1])->order('id asc')->select();
        if (!empty($list)) {
            $list = collection($list)->toArray();
        }

        foreach ($list as $key => $data) {
            list($list[$key]['tags'], $list[$key]['slist']) = [[], []];
            $fieldType = (!empty($data['type'])) ? trim($data['type']) : 'none';
            $fieldName = (!empty($data['field'])) ? trim($data['field']) : 'none';
            $tableName = (isset($data['tables']['table']) && !empty($data['tables']['table'])) ? trim($data['tables']['table']) : 'none';
            //处理 单选和多选			
            if (in_array($data['type'], ['enum', 'set'])) {
                if (!empty($data['field_json'])) {
                    $list[$key]['slist'] = json_decode($data['field_json'], true);
                }
            }
            //添加规则
            if (!empty($data['rule_add'])) {
                $rule_data = explode('|', $data['rule_add']);
                foreach ($rule_data as $rk => $rv) {
                    $list[$key]["tags"][] = [
                        'tag' => sprintf("Add-%s", BuiApiFunc::$rules[$rv]),
                        'color' => 'bule',
                        'id' => $data['id'],
                        'type' => 'add',
                        'rule' => $rv
                    ];
                }
            }
            //添加规则
            if (!empty($data['rule_edit'])) {
                $rule_data = explode('|', $data['rule_edit']);
                foreach ($rule_data as $rk => $rv) {
                    $list[$key]["tags"][] = [
                        'tag' => sprintf("Edit-%s", BuiApiFunc::$rules[$rv]),
                        'color' => 'red',
                        'id' => $data['id'],
                        'type' => 'edit',
                        'rule' => $rv
                    ];
                }
            }
        }
        $this->view->assign("list", $list);
        $this->view->assign("table", $table);
        return $this->view->fetch();
    }

    /**
     * 给表字段添加规则
     */
    public function ruleadd($table = null)
    {
        if ($this->request->isPost()) {
            $param = $this->request->request();
            if (empty($param['rule_data'])) {
                $this->error(__('规则数据不能为空'));
            }

            if (empty($param['field_id'])) {
                $this->error(__('规则ID不能为空'));
            }
            if (empty($param['field_type'])) {
                $this->error(__('规则类型不能为空'));
            }
            $fieldInfo = $this->model_field->get(['id' => intval($param['field_id'])]);
            if (empty($fieldInfo)) {
                $this->error(__('规则列表为空'));
            }
            if (!in_array($param['field_type'], ['add', 'edit'])) {
                $this->error(__('规则类型错误'));
            }
            $field_key = sprintf("rule_%s", $param['field_type']);
            $fieldResult = $fieldInfo->save(["{$field_key}" => implode('|', $param['rule_data'])]);
            if (!empty($fieldResult)) {
                $this->success(__('添加规则成功'));
            }
            $this->error(__('添加规则失败'));
        }
        if ($table == NULL) {
            $table = trim($this->request->request('table'));
        }
        if (empty($table)) {
            $this->error('参数不能为空');
        }
        $list = $this->model_field->where("table", '=', $table)->where(['is_show' => 1])->order('id asc')->select();
        if (!empty($list)) {
            $list = collection($list)->toArray();
        }
        if (empty($list)) {
            $this->error('暂无字段列表，不能添加规则');
        }
        $this->view->assign("table_name", $table);
        $this->view->assign("field_list", $list);
        $this->view->assign("rule_list", BuiApiFunc::$rules);
        return $this->view->fetch();
    }

    /**
     * 规则删除
     */
    public function rule_del(int $id = 0, $type = '', $rule = '')
    {
        list($rule_array, $update_value) = [[], ''];
        $fieldInfo = $this->model_field->get(['id' => $id]);
        if (empty($fieldInfo)) {
            return json(['code' => 1, 'msg' => '规则列表为空']);
        }
        if ($type == 'add') {
            $rule_data = explode('|', $fieldInfo['rule_add']);
        }

        if ($type == 'edit') {
            $rule_data = explode('|', $fieldInfo['rule_edit']);
        }
        foreach ($rule_data as $rules) {
            if ($rules == $rule) {
                continue;
            }
            $rule_array[] = $rules;
        }
        if (!empty($rule_array)) {
            $update_value = implode("|", $rule_array);
        }
        $field_key = sprintf("rule_%s", $type);
        $fieldResult = $fieldInfo->save(["{$field_key}" => $update_value]);
        if (!empty($fieldResult)) {
            return json(['code' => 0, 'msg' => '删除规则成功']);
        }
        return json(['code' => 1, 'msg' => '删除规则失败']);
    }

    /**
     * 隐藏字段
     */
    public function field_hidden(int $id = 0)
    {
        $fieldModel = $this->model_field->get(['id' => intval($id)]);
        if (empty($fieldModel)) {
            return json(['code' => 1, 'msg' => '查询记录不存在']);
        }
        if (!empty($fieldModel->save(['is_show' => 0]))) {
            return json(['code' => 0, 'msg' => '隐藏记录成功']);
        }
        return json(['code' => 1, 'msg' => '隐藏记录失败']);
    }

    /**
     * 显示字段
     */
    public function fieldview()
    {
        $type = $this->request->request('type', 'default');
        if ($type == 'default') {
            $table = trim($this->request->request('table'));
            if (empty($table)) {
                $this->error('参数不能为空');
            }
            $list = $this->model_field->field(['field', 'title', 'id'])->where("table", '=', $table)->where(['is_show' => 0])->order('id asc')->select();
            $this->view->assign("table_name", $table);
            $this->view->assign("field_list", $list);
            return $this->view->fetch();
        }
        if ($type == 'save') {
            $ids = $this->request->request('ids/a');
            if (empty($ids)) {
                $this->error('参数不能为空');
            }
            $list = $this->model_field->where('id', "in", $ids)->where(['is_show' => 0])->select();
            $count = 0;
            foreach ($list as $k => $v) {
                $count += $v->isUpdate(true)->save(['is_show' => 1]);
            }
            if ($count) {
                $this->success();
            }
            $this->error('显示记录失败');
        }
    }

    /**
     * 生成模版页面
     */
    public function buildindex($table = null)
    {
        //$result = $this->model_table->get(['table'=>$table]);
        //if(empty($result) || empty($result['name'])){
        //	$this->error('数据不存在');
        //}

        $loginList = ['vlogin' => '接口登录可访问', 'nlogin' => '无需登录可访问'];
        $funList = ['index' => '列表(index)', 'add' => '创建(add)', 'edit' => '修改(edit)', 'view' => '详情(view)', 'del' => '删除(del)'];

        $tableList = $this->model_table->field('table')->order('id asc')->select();
        if (empty($tableList)) {
            $this->error('数据不存在');
        }
        $tableList = collection($tableList)->toArray();
        $tableList = array_column($tableList, 'table', 'table');

        $this->view->assign("table", $table);
        $this->view->assign("tableList", $tableList);
        $this->view->assign("funList", $funList);
        $this->view->assign("loginList", $loginList);
        return $this->view->fetch();
    }

    /**
     * 获取字段列表
     */
    public function get_field_list()
    {
        $dbname = Config::get('database.database');
        $prefix = Config::get('database.prefix');
        $table = $this->request->request('table');
        //从数据库中获取表字段信息
        $sql = "SELECT * FROM `information_schema`.`columns` "
            . "WHERE TABLE_SCHEMA = ? AND table_name = ? "
            . "ORDER BY ORDINAL_POSITION";
        //加载主表的列
        $columnList = Db::query($sql, [$dbname, $prefix . $table]);
        $fieldlist = [];
        foreach ($columnList as $index => $item) {
            $fieldlist[] = $item['COLUMN_NAME'];
        }
        $this->view->assign("table", $table);
        $this->success("", null, ['fieldlist' => $fieldlist]);
    }

    /**
     * 生成命令
     */
    public function buildcommand()
    {
        $argv = [];
        $params = $this->request->request('', '', 'trim,strip_tags');
        if (empty($params['func'])) {
            $this->error("生成的接口方法不能为空");
        }
        if (!in_array($params['login'], ['vlogin', 'nlogin'])) {
            $this->error("验证登录接口方法不能为空");
        }
        $allowfields = ['table', 'controller', 'model', 'fields', 'force', 'local', 'delete', 'menu', 'searchfields'];
        $allowfields = array_filter(array_intersect_key($params, array_flip($allowfields)));
        if (isset($params['local']) && !$params['local']) {
            $allowfields['local'] = $params['local'];
        } else {
            unset($allowfields['local']);
        }
        foreach ($allowfields as $key => $param) {
            $argv[] = "--{$key}=" . (is_array($param) ? implode(',', $param) : $param);
        }
        $isrelation = (int)$this->request->request('isrelation');
        if ($isrelation && isset($params['relation'])) {
            foreach ($params['relation'] as $index => $relation) {
                foreach ($relation as $key => $value) {
                    $argv[] = "--{$key}=" . (is_array($value) ? implode(',', $value) : $value);
                }
            }
        }
        $argv[] = sprintf("--func=%s", implode(",", $params['func']));
        $vlogin = ($params['login'] == 'vlogin') ? 'yes' : 'not';
        $argv[] = sprintf("--vlogin=%s", $vlogin);
        $this->success("", null, ['command' => implode(' ', $argv)]);
    }

    /**
     * 执行命令
     */
    public function execcommand()
    {
        $commond_array = [];
        $commond_string = $this->request->request('commond');
        //判断字符串中是否有关联表
        $commond_string = str_replace("--", "", $commond_string);
        if (strpos($commond_string, 'relation=') !== false) {
            $commond_string = str_replace("relation=", "^_^relation=", $commond_string);
            $commond_array = explode("^_^", $commond_string);
        } else {
            $commond_array[0] = $commond_string;
        }
        $result = BuiApiTemp::handleTemplate($commond_array);
        $this->success("", null, ['result' => $result['msg']]);
    }

}
