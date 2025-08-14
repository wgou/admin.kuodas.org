<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;
use think\Config;
use think\Exception;

//use think\Config;

/**
 * 自建表管理
 *
 * @icon fa fa-circle-o
 */
class Tablemake extends Backend {

    /**
     * Test模型对象
     * @var \app\admin\model\Test
     */
    protected $ModelOnline  = null;
    protected $ModelFields  = null;
    protected $searchFields = false;//关闭快捷搜索

    protected $db_name   = '';
    protected $db_prefix = '';


    public function _initialize() {
        parent::_initialize();
        $this->ModelOnline = model('TableMakeTables');
        $this->ModelFields = model('TableMakeFields');

        $dictionary_url = url("tablemake/dictionary", [], true, true);
        $this->view->assign("dictionary_url", $dictionary_url);
    }

    /*
     * 数据表列表
     */

    public function index() {

        $this->searchFields = "name,table,desc";
        if ($this->request->isAjax()) {
            $prefix = Config::get('database.prefix');
            $this->model = $this->ModelOnline;
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items(), "prefix" => $prefix);
            return json($result);
        }

        return $this->view->fetch();
    }

    /*
     * 创建数据表
     */

    public function add() {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->ModelOnline));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : true) : $this->modelValidate;
                        $this->ModelOnline->validate($validate);
                    }
                    $params['createtime'] = time();
                    $params['updatetime'] = time();
                    $result = $this->ModelOnline->allowField(true)->save($params);
                    $prefix = Config::get('database.prefix');
                    if ($result !== false) {
                        //在此执行创建表的操作
                        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}" . $params['table'] . "` (
								`id` bigint(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
								PRIMARY KEY (`id`)
							  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='" . $params['name'] . "';";

                        $res = \think\Db::execute($sql);
                        //var_export($res);
                        $this->success();
                    } else {
                        $this->error($this->ModelOnline->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $prefix = Config::get('database.prefix');
        $this->view->assign("prefix", $prefix);
        return $this->view->fetch();
    }

    /*
     * 编辑数据表
     */

    public function edit($ids = NULL) {
        $row = $this->ModelOnline->get($ids);
        if (!$row)
            $this->error(__('No Results were found'));
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->ModelOnline)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $params['updatetime'] = time();
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $prefix = Config::get('database.prefix');
                        $sql = "ALTER TABLE  `{$prefix}" . $row['table'] . "`  COMMENT='" . $row['name'] . "';";
                        $res = \think\Db::execute($sql);
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $prefix = Config::get('database.prefix');
        $this->view->assign("row", $row);
        $this->view->assign("prefix", $prefix);
        return $this->view->fetch();
    }

    /*
     * 删除模块
     */

    public function del($ids = NULL) {
        if ($ids) {
            $pk = $this->ModelOnline->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->ModelOnline->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->ModelOnline->where($pk, 'in', $ids)->select();
            $prefix = Config::get('database.prefix');
            $count = 0;
            foreach ($list as $k => $v) {
                $sql = "DROP TABLE IF EXISTS `{$prefix}" . $v->table . "`;";
                try {
                    $res = \think\Db::execute($sql);
                    $this->ModelFields->where("mid", '=', $v->id)->delete();
                    $count += $v->delete();
                    if ($count) {
                        $this->success(__('删除成功！'), null, __('删除成功！'));
                    } else {
                        $this->error(__('No rows were deleted'));
                    }
                } catch (Exception $ex) {
                    $this->error(__('No rows were deleted'));
                }
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /*
     * 验证重名
     */

    public function check($table = null, $name = null) {
        if ($table == null && $name == null) {
            if ($this->request->isAjax()) {
                $table = $this->request->request('table');
                $name = $this->request->request('name');
            }
        }
        if ($table && $name) {
            $sql = "describe  `{$table}`  `{$name}`";
            $res = \think\Db::query($sql);
            if ($res) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /*
     * 字段列表
     */

    public function fields($ids = NULL) {
        $this->searchFields = "name,title,comment";
        if ($ids == NULL) {
            $ids = intval($this->request->request('ids'));
        }
        $model = $this->ModelOnline->get($ids);
        if (!$model) {
            $this->error(__('No Results were found'));
        }

        if ($this->request->isAjax()) {
            $prefix = Config::get('database.prefix');
            $this->model = $this->ModelFields;
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where("mid", '=', $ids)
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items(), "prefix" => $prefix);
            return json($result);


//            $list = $this->ModelFields->where("mid", '=', $ids)->order('id desc')->select();
//            $total = count($list);
//            $prefix = Config::get('database.prefix');
//            $result = array("total" => $total, "rows" => $list, "prefix" => $prefix);
//            return json($result);
        }
        $this->view->assign("ids", $ids);
        return $this->view->fetch();
    }

    /*
     * 添加字段
     */

    public function field_add($mid = NULL) {
        $mod_table = $this->ModelOnline->get($mid);
        if (!$mod_table)
            $this->error(__('No Results were found'));
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->ModelFields));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : true) : $this->modelValidate;
                        $this->ModelFields->validate($validate);
                    }
                    $prefix = Config::get('database.prefix');
                    $field = array();
                    $fieldData = array();
                    switch ($params['category']) {
                        case "1":
                            $allow = array(
                                "text" => array("suffix" => "text", "type" => "varchar", "length" => 200),
                                "number" => array("suffix" => "number", "type" => "bigint", "length" => 11),
                                "time" => array("suffix" => "time", "type" => "bigint", "length" => 11),
                                "image" => array("suffix" => "image", "type" => "varchar", "length" => 255),
                                "images" => array("suffix" => "images", "type" => "varchar", "length" => 2000),
                                "file" => array("suffix" => "file", "type" => "varchar", "length" => 255),
                                "files" => array("suffix" => "files", "type" => "varchar", "length" => 2000),
                                "avatar" => array("suffix" => "avatar", "type" => "varchar", "length" => 255),
                                "avatars" => array("suffix" => "avatars", "type" => "varchar", "length" => 2000),
                                "content" => array("suffix" => "content", "type" => "text", "length" => 0),
                                "_id" => array("suffix" => "_id", "type" => "bigint", "length" => 11),
                                "_ids" => array("suffix" => "_ids", "type" => "varchar", "length" => 255),
                                "list-enum" => array("suffix" => "list", "type" => "enum", "length" => 0),
                                "list-set" => array("suffix" => "list", "type" => "set", "length" => 0),
                                "data-enum" => array("suffix" => "data", "type" => "enum", "length" => 0),
                                "data-set" => array("suffix" => "data", "type" => "set", "length" => 0),
                                "json" => array("suffix" => "json", "type" => "varchar", "length" => 2000),
                                "switch" => array("suffix" => "switch", "type" => "tinyint", "length" => 1),
                            );
                            if (isset($allow[$params['suffix']]) && is_array($allow[$params['suffix']])) {
                                $fieldData['special'] = "";
                                $fieldData['suffix'] = $params['suffix'];
                                //$field['name'] = $params['name'] . $allow[$params['suffix']]['suffix']; //字段名
                                $field['name'] = $params['name']; //字段名
                                $field['field'] = $params['name'] . $allow[$params['suffix']]['suffix']; //字段名
                                $field['length'] = $field['length2'] = isset($params['length']) && intval($params['length']) ? intval($params['length']) : $allow[$params['suffix']]['length']; //字段长度
                                $field['type'] = $allow[$params['suffix']]['type']; //字段类型
                                $field['default'] = isset($params['default']) ? $params['default'] : ""; //默认值
                                if ($allow[$params['suffix']]['type'] == "enum" || $allow[$params['suffix']]['type'] == "set") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }
                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        case "2":
                            $allow = array(
                                "varchar" => array("type" => "varchar", "length" => 255),
                                "int" => array("type" => "bigint", "length" => 11),
                                "enum" => array("type" => "enum", "length" => 0),
                                "set" => array("type" => "set", "length" => 0),
                                "float" => array("type" => "float", "length" => "10,2"),
                                "text" => array("type" => "text", "length" => 0),
                                "datetime" => array("type" => "datetime", "length" => 11),
                                "date" => array("type" => "date", "length" => 11),
                                "year" => array("type" => "year", "length" => 4),
                                "timestamp" => array("type" => "timestamp", "length" => 11),
                            );
                            if (isset($allow[$params['type']]) && is_array($allow[$params['type']])) {
                                $fieldData['special'] = "";
                                $fieldData['suffix'] = "";
                                $params['suffix'] = "";
                                $field['name'] = $params['name']; //字段名
                                $field['field'] = $params['name']; //字段名
                                if ($params['type'] == "float") {
                                    $field['length'] = $field['length2'] = isset($params['length']) && $params['length'] ? $params['length'] : $allow[$params['type']]['length']; //字段长度
                                } else {
                                    $field['length'] = $field['length2'] = isset($params['length']) && intval($params['length']) ? intval($params['length']) : $allow[$params['type']]['length']; //字段长度
                                }
                                $field['type'] = $allow[$params['type']]['type']; //字段类型
                                $field['default'] = isset($params['default']) ? $params['default'] : ""; //默认值
                                if ($allow[$params['type']]['type'] == "enum" || $allow[$params['type']]['type'] == "set") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }
                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        case "3":
                            $allow = array(
                                "user_id" => array("title" => "会员ID(单选)", "type" => "bigint", "length" => 11),
                                "category_id" => array("title" => "分类ID(单选)", "type" => "bigint", "length" => 11),
                                "category_ids" => array("title" => "分类ID(多选)", "type" => "varchar", "length" => 200),
                                "weigh" => array("title" => "权重", "type" => "bigint", "length" => 11),
                                "status" => array("title" => "状态", "type" => "enum", "length" => 0),
                                "createtime" => array("title" => "创建时间", "type" => "bigint", "length" => 11),
                                "updatetime" => array("title" => "更新时间", "type" => "bigint", "length" => 11),
                                "deletetime" => array("title" => "删除时间", "type" => "bigint", "length" => 11),
                            );
                            if (isset($allow[$params['special']]) && is_array($allow[$params['special']])) {
                                $fieldData['special'] = $params['special'];
                                $fieldData['suffix'] = "";
                                //$params['title'] = $allow[$params['special']]['title'];
//                                $params['comment'] = $params['suffix'] = "";
                                $field['name'] = $params['special']; //字段名
                                $field['field'] = $params['special']; //字段名
                                $field['length'] = $field['length2'] = $allow[$params['special']]['length']; //字段长度
//                                $field['comment'] = $params['title']; //备注
                                $field['type'] = $allow[$params['special']]['type']; //字段类型
                                $field['default'] = $field['type'] == "varchar" ? "" : "0"; //默认值

                                if ($params['special'] == "status") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }
                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        default :
                            $this->error(__('No Results were found'));
                            break;
                    }

                    if ($this->check($prefix . $mod_table['table'], $field['name'])) {
                        $this->error(__('字段已经存在！'));
                    }
                    $fieldData['mid'] = $params['mid'];
                    $fieldData['category'] = $params['category'];
                    $fieldData['title'] = $params['title'];
                    $fieldData['name'] = $field['name'];
                    $fieldData['field'] = $field['field'];
                    $fieldData['type'] = $field['type'];
                    $fieldData['length'] = $field['length'];
                    $fieldData['default'] = $field['default'];
                    $fieldData['comment'] = $field['comment'];
                    $fieldData['desc'] = $params['desc'];
                    $fieldData['createtime'] = time();
                    $fieldData['updatetime'] = time();
                    if ($fieldData['type'] == "text") {
                        $fieldData['default'] = "";
                    }
                    if ($field['type'] == "bigint" || $field['type'] == "int") {
                        $field['default'] = intval($field['default']);
                    } elseif ($field['type'] == "tinyint") {
                        $field['default'] = in_array($field['default'], [0, 1]) ? $field['default'] : 0;
                    } elseif ($field['type'] == "float") {
                        $field['default'] = is_float($field['default']) ? $field['default'] : 0;
                    }

                    \think\Db::startTrans();
                    try {
                        $result = $this->ModelFields->allowField(true)->save($fieldData);
                        if ($result !== false) {
                            //在此执行添加字段的操作
                            if (in_array($field['type'], ["text", "datetime", "date", "year", "timestamp"])) {
                                $sql = "ALTER TABLE `{$prefix}{$mod_table['table']}` ADD COLUMN `{$field['field']}`  {$field['type']}  NOT NULL  COMMENT '{$field['comment']}';";
                            } else {
                                $sql = "ALTER TABLE `{$prefix}{$mod_table['table']}` ADD COLUMN `{$field['field']}`  {$field['type']}({$field['length2']}) NOT NULL DEFAULT '{$field['default']}' COMMENT '{$field['comment']}';";
                            }
                            try {
                                $res = \think\Db::execute($sql);
                            } catch (Exception $ex) {
                                new Exception('参数错误，请检查字段名，字段长度或者默认值等输入参数是否合法');
                            }
                        } else {
                            new Exception($this->ModelFields->getError());
                        }
                        \think\Db::commit();
                    } catch (Exception $e) {
                        \think\Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $prefix = Config::get('database.prefix');
        $this->view->assign("prefix", $prefix);
        $this->view->assign("mid", $mid);
        return $this->view->fetch();
    }

    /*
     * 修改字段
     */

    public function field_edit($ids = NULL) {
        //$oldField_info = $this->ModelFields->get($ids);
        $field_info = $this->ModelFields->get($ids);
        $oldField_info = $field_info->toArray();
        //var_dump($field_info);
        if (!$field_info)
            $this->error(__('No Results were found'));

        $mod_table = $this->ModelOnline->get($field_info['mid']);
        if (!$mod_table)
            $this->error(__('No Results were found'));

        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->ModelFields));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $this->ModelFields->validate($validate);
                    }
                    $prefix = Config::get('database.prefix');
                    $field = array();
                    $fieldData = array();
                    switch ($field_info['category']) {
                        case "1":
                            $allow = array(
                                "text" => array("suffix" => "text", "type" => "varchar", "length" => 200),
                                "number" => array("suffix" => "number", "type" => "bigint", "length" => 11),
                                "time" => array("suffix" => "time", "type" => "bigint", "length" => 11),
                                "image" => array("suffix" => "image", "type" => "varchar", "length" => 255),
                                "images" => array("suffix" => "images", "type" => "varchar", "length" => 2000),
                                "file" => array("suffix" => "file", "type" => "varchar", "length" => 255),
                                "files" => array("suffix" => "files", "type" => "varchar", "length" => 2000),
                                "avatar" => array("suffix" => "avatar", "type" => "varchar", "length" => 255),
                                "avatars" => array("suffix" => "avatars", "type" => "varchar", "length" => 2000),
                                "content" => array("suffix" => "content", "type" => "text", "length" => 0),
                                "_id" => array("suffix" => "_id", "type" => "bigint", "length" => 11),
                                "_ids" => array("suffix" => "_ids", "type" => "varchar", "length" => 255),
                                "list-enum" => array("suffix" => "list", "type" => "enum", "length" => 0),
                                "list-set" => array("suffix" => "list", "type" => "set", "length" => 0),
                                "data-enum" => array("suffix" => "data", "type" => "enum", "length" => 0),
                                "data-set" => array("suffix" => "data", "type" => "set", "length" => 0),
                                "json" => array("suffix" => "json", "type" => "varchar", "length" => 2000),
                                "switch" => array("suffix" => "switch", "type" => "tinyint", "length" => 1),
                            );
                            if (isset($allow[$params['suffix']]) && is_array($allow[$params['suffix']])) {
                                $fieldData['special'] = "";
                                $fieldData['suffix'] = $params['suffix'];
                                $field['name'] = $params['name']; //字段名
                                $field['field'] = $params['name'] . $allow[$params['suffix']]['suffix']; //字段名
                                $field['length'] = $field['length2'] = isset($params['length']) && intval($params['length']) ? intval($params['length']) : $allow[$params['suffix']]['length']; //字段长度
                                $field['type'] = $allow[$params['suffix']]['type']; //字段类型
                                $field['default'] = isset($params['default']) ? $params['default'] : ""; //默认值
                                if ($allow[$params['suffix']]['type'] == "enum" || $allow[$params['suffix']]['type'] == "set") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }
                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        case "2":
                            $allow = array(
                                "varchar" => array("type" => "varchar", "length" => 255),
                                "int" => array("type" => "bigint", "length" => 11),
                                "enum" => array("type" => "enum", "length" => 0),
                                "set" => array("type" => "set", "length" => 0),
                                "float" => array("type" => "float", "length" => "10,2"),
                                "text" => array("type" => "text", "length" => 0),
                                "datetime" => array("type" => "datetime", "length" => 11),
                                "date" => array("type" => "date", "length" => 11),
                                "year" => array("type" => "year", "length" => 4),
                                "timestamp" => array("type" => "timestamp", "length" => 11),
                            );
                            if (isset($allow[$params['type']]) && is_array($allow[$params['type']])) {
                                $fieldData['special'] = "";
                                $fieldData['suffix'] = "";
                                $params['suffix'] = "";
                                $field['name'] = $params['name']; //字段名
                                $field['field'] = $params['name']; //字段名
                                if ($params['type'] == "float") {
                                    $field['length'] = $field['length2'] = isset($params['length']) && $params['length'] ? $params['length'] : $allow[$params['type']]['length']; //字段长度
                                } else {
                                    $field['length'] = $field['length2'] = isset($params['length']) && intval($params['length']) ? intval($params['length']) : $allow[$params['type']]['length']; //字段长度
                                }
                                $field['type'] = $allow[$params['type']]['type']; //字段类型
                                $field['default'] = isset($params['default']) ? $params['default'] : ""; //默认值
                                if ($allow[$params['type']]['type'] == "enum" || $allow[$params['type']]['type'] == "set") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }
                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        case "3":
                            $allow = array(
                                "user_id" => array("title" => "会员ID(单选)", "type" => "bigint", "length" => 11),
                                "category_id" => array("title" => "分类ID(单选)", "type" => "bigint", "length" => 11),
                                "category_ids" => array("title" => "分类ID(多选)", "type" => "varchar", "length" => 200),
                                "weigh" => array("title" => "权重", "type" => "bigint", "length" => 11),
                                "status" => array("title" => "状态", "type" => "enum", "length" => 0),
                                "createtime" => array("title" => "创建时间", "type" => "bigint", "length" => 11),
                                "updatetime" => array("title" => "更新时间", "type" => "bigint", "length" => 11),
                                "deletetime" => array("title" => "删除时间", "type" => "bigint", "length" => 11),
                            );
                            if (isset($allow[$params['special']]) && is_array($allow[$params['special']])) {
                                $fieldData['special'] = $params['special'];
                                $fieldData['suffix'] = "";
//                                $params['title'] = $allow[$params['special']]['title'];
//                                if ($params['special'] != "status") {
//                                    $params['comment'] = "";
//                                }
//                                $params['comment'] =
                                $params['suffix'] = "";
                                $field['name'] = $params['special']; //字段名
                                $field['field'] = $params['special']; //字段名
                                $field['length'] = $field['length2'] = $allow[$params['special']]['length']; //字段长度
//                                $field['comment'] = $params['title']; //备注
                                $field['type'] = $allow[$params['special']]['type']; //字段类型
                                $field['default'] = $field['type'] == "varchar" ? "" : "0"; //默认值

                                if ($params['special'] == "status") {
                                    $comment = \GuzzleHttp\json_decode($params['comment'], true);
                                    $field['comment'] = $params['title'] . ":"; //备注
                                    $field['length2'] = "";
                                    $str = "";
                                    $default_optional = array();
                                    foreach ($comment as $k => $v) {
                                        $default_optional[] = $k;
                                        $field['comment'] .= $str . $k . "=" . $v;
                                        $field['length2'] .= $str . "'" . $k . "'";
                                        $str = ",";
                                    }
                                    if (!in_array($field['default'], $default_optional)) {
                                        $field['default'] = $default_optional[0];
                                    }
                                } else {
                                    $params['comment'] = "";
                                    $field['comment'] = $params['title']; //备注
                                }

                            } else {
                                $this->error(__('特殊字段类型不正确！'));
                            }
                            break;
                        default :
                            $this->error(__('No Results were found'));
                            break;
                    }
                    /*
                      if ($this->check($prefix . $mod_table['table'], $field['name'])) {
                      $this->error(__('字段已经存在！'));
                      } */
                    $fieldData['mid'] = $params['mid'];
                    //$fieldData['category'] = $params['category'];
                    $fieldData['title'] = $params['title'];
                    $fieldData['name'] = $field['name'];
                    $fieldData['field'] = $field['field'];
                    $fieldData['type'] = $field['type'];
                    $fieldData['length'] = $field['length'];
                    $fieldData['default'] = $field['default'];
                    $fieldData['comment'] = $field['comment'];
                    $fieldData['desc'] = $params['desc'];
                    $fieldData['updatetime'] = time();
                    if ($fieldData['type'] == "text") {
                        $fieldData['default'] = "";
                    }


                    if ($field['type'] == "bigint" || $field['type'] == "int") {
                        $field['default'] = intval($field['default']);
                    } elseif ($field['type'] == "tinyint") {
                        $field['default'] = in_array($field['default'], [0, 1]) ? $field['default'] : 0;
                    } elseif ($field['type'] == "float") {
                        $field['default'] = is_float($field['default']) ? $field['default'] : 0;
                    }

                    \think\Db::startTrans();
                    try {
                        $result = $field_info->save($fieldData);
                        if ($result !== false) {
                            //在此执行添加字段的操作
                            if (in_array($field['type'], ["text", "datetime", "date", "year", "timestamp"])) {
                                $sql = "ALTER TABLE `{$prefix}{$mod_table['table']}`  CHANGE COLUMN `{$oldField_info['field']}` `{$field['field']}`  {$field['type']} NOT NULL  COMMENT '{$field['comment']}' ;";
                            } else {
                                $sql = "ALTER TABLE `{$prefix}{$mod_table['table']}`  CHANGE COLUMN `{$oldField_info['field']}` `{$field['field']}`  {$field['type']}({$field['length2']}) NOT NULL DEFAULT '{$field['default']}' COMMENT '{$field['comment']}' ;";
                            }
                            try {
                                $res = \think\Db::execute($sql);
                            } catch (Exception $ex) {
                                // $field_info->save($oldField_info);
                                throw  new Exception('参数错误，请检查字段名，字段长度或者默认值等输入参数是否合法');
                            }
                        } else {
                            throw  new Exception($this->ModelFields->getError());
                        }
                        \think\Db::commit();
                    } catch (Exception $e) {
                        \think\Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                } catch (\think\exception\PDOException $e) {
                    $field_info->save($oldField_info);
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $field_info->save($oldField_info);
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $comment = "";
        if ($field_info['type'] == "enum" || $field_info['type'] == "set") {
            //echo $field_info['comment'];
            $commentStr = substr($field_info['comment'], strpos($field_info['comment'], ":") + 1);

            $commentArr = [];
            foreach (explode(",", $commentStr) as $k => $v) {

                list($key, $val) = explode("=", $v);

                $commentArr[$key] = $val;
            }

            $comment = \json_encode($commentArr);
        }
        $prefix = Config::get('database.prefix');
        $this->view->assign("field_info", $field_info);
        $this->view->assign("row", $field_info);
        $this->view->assign("prefix", $prefix);
        $this->view->assign("comment", $comment);
        $this->view->assign("mid", $field_info['mid']);
        return $this->view->fetch();
    }

    public function field_del($ids = NULL) {
        if ($ids) {
            $pk = $this->ModelFields->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->ModelFields->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->ModelFields->where($pk, 'in', $ids)->select();
            $prefix = Config::get('database.prefix');
            $count = 0;
            foreach ($list as $k => $v) {
                $mod_table = $this->ModelOnline->get($v['mid']);
                $sql = "ALTER TABLE `{$prefix}{$mod_table['table']}` DROP `{$v['field']}` ";
                try {
                    $res = \think\Db::execute($sql);
                    $count += $v->delete();
                    if ($count) {
                        $this->success();
                    } else {
                        $this->error(__('No rows were deleted'));
                    }
                } catch (Exception $ex) {
                    $this->error(__('No rows were deleted'));
                }
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    public function dictionary() {

        $this->db_name = \config("database.database");
        $this->db_prefix = \config("database.prefix");
        $this->view->assign('db_name', $this->db_name);
        $this->view->assign('db_prefix', $this->db_prefix);

        $dictionary = [
            'make' => [],
            'system' => [],
            'other' => [],
        ];

        //读取所有表
        $tables_res = Db::table("information_schema.TABLES")->field("TABLE_NAME")->where(['TABLE_SCHEMA' => $this->db_name,])->select();
        $tables_all = array_column($tables_res, 'TABLE_NAME');

        //已经获取到数据字典的表
        $tables_isset = [];

        //声明系统表 获取系统表的数据字典
        $tables_system = ['admin', 'admin_log', 'attachment', 'area', 'auth_group', 'auth_group_access', 'auth_rule', 'category', 'config', 'ems', 'sms', 'user', 'user_group', 'user_money_log', 'user_rule', 'user_score_log', 'user_token', 'version'];
        foreach ($tables_system as $k => $v) {
            $table_name = $this->db_prefix . $v;
            $dictionary['system'][] = $this->getSystemTableInfo($table_name);
            $tables_isset[] = $table_name;
        }

        //读取自建表 获取自建表数据字典
        $tables_make = \app\admin\model\TableMakeTables::all(function ($query) {
            $query->order("weigh desc,id desc");
        });
        foreach ($tables_make as $k => $v) {
            $table_name = $this->db_prefix . $v['table'];
            $dictionary['make'][] = $this->getMakeTableInfo($table_name, $v);
            $tables_isset[] = $table_name;
        }

        //获取其他表数据字典  求差集，计算出其他表
        $tables_other = array_diff($tables_all, $tables_isset);
        foreach ($tables_other as $k => $v) {
            $table_name = $v;
            $dictionary['other'][] = $this->getOtherTableInfo($table_name);
            $tables_isset[] = $table_name;
        }

        $this->view->assign('dictionary', $dictionary);
        $this->view->engine->layout(false);
        return $this->view->fetch();

    }


    /**
     * 根据表名获取系统表的数据字典数据
     * @param $table_name  表名
     * @return array
     */
    protected function getSystemTableInfo($table_name) {
        return $this->getOtherTableInfo($table_name);
    }

    /**
     * 根据表名获取非自建表的数据字典信息
     * @param $table_name
     * @return array
     */
    protected function getOtherTableInfo($table_name) {
        $table_info = $this->getTableInfo($table_name);
        $result = [
            'name' => $table_info['table_info']['TABLE_COMMENT'] ? $table_info['table_info']['TABLE_COMMENT'] : $table_name,
            'table' => preg_replace('/^(' . $this->db_prefix . ').*?/is', '', $table_name),//表名去前缀
            'table_name' => $table_name,
            'desc' => $table_info['table_info']['TABLE_COMMENT'],
            'engine' => $table_info['table_info']['ENGINE'],
            'table_comment' => $table_info['table_info']['TABLE_COMMENT'],
            'table_collation' => $table_info['table_info']['TABLE_COLLATION'],
            'create_time' => $table_info['table_info']['CREATE_TIME'],
            'update_time' => $table_info['table_info']['UPDATE_TIME'],
            'fields' => $this->getTableFields($table_info['table_fields']),
        ];
        return $result;
    }

    /**
     * 根据表名、自建表信息数据，获取自建表的数据字典信息
     * @param $table_name 表名
     * @param $table
     * @return array
     * @throws \think\exception\DbException
     */
    protected function getMakeTableInfo($table_name, $table) {
        $model_fields = \app\admin\model\TableMakeFields::all(function ($query) use ($table) {
            $query->where(['mid' => $table['id']]);
            $query->order("weigh desc,id desc");
        });
        $result = $this->getOtherTableInfo($table_name);
        $result['name'] = $table['name'];
        $result['table'] = $table['table'];
        $result['desc'] = $table['desc'];
        $result['create_time'] = date("Y-m-d H:i:s", $table['createtime']);
        $result['update_time'] = date("Y-m-d H:i:s", $table['updatetime']);
        $result['fields'] = $this->getTableFieldsByMake($result['fields'], $model_fields);
        return $result;
    }

    /**
     * 结合表的字段备注 自建表存储的信息，生成自建表的字段数据字典
     * @param $fields
     * @param array $make_fields
     * @return array
     */
    protected function getTableFieldsByMake($fields, $make_fields = []) {
        $result = [];
        $field_list = [];
        foreach ($fields as $k => $v) {
            $field_list[$v['field_name']] = $v;
        }
        $result[] = $field_list['id'];
        foreach ($make_fields as $k => $v) {
            $result[] = [
                "field_title" => $v['title'],
                "field_name" => $v['field'],
                "character" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['character'] : NULL,
                "field_collation" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['field_collation'] : NULL,
                "data_type" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['data_type'] : NULL,//字段类型
                "column_type" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['column_type'] : NULL,//列类型
                "is_nullable" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['is_nullable'] : NULL,//是否能为空
                "length" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['length'] : NULL,//字符串长度
                "column_comment" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['column_comment'] : NULL,//字段备注
                "default" => isset($field_list[$v['field']]) ? $field_list[$v['field']]['default'] : NULL,//字段默认值
                "desc" => $v['desc'],
            ];
        }
        return $result;
    }

    /**
     * 根据表的字段备注，生成字段数据字典
     * @param $fields
     * @return array
     */
    protected function getTableFields($fields) {
        $result = [];
        foreach ($fields as $k => $v) {
            $column_type = preg_replace('/(unsigned).*?/is', '(无符号)', $v['COLUMN_TYPE']);
            list($field_name) = explode(":", $v['COLUMN_COMMENT']);
            $result[] = [
                "field_title" => $field_name ? $field_name : $v['COLUMN_NAME'],
                "field_name" => $v['COLUMN_NAME'],
                "character" => $v['CHARACTER_SET_NAME'],
                "field_collation" => $v['COLLATION_NAME'],
                "data_type" => $v['DATA_TYPE'],//字段类型
                "column_type" => $column_type,//列类型
                "is_nullable" => $v['IS_NULLABLE'],//是否能为空
                "length" => $v['CHARACTER_MAXIMUM_LENGTH'],//字符串长度
                "column_comment" => $v['COLUMN_COMMENT'],//字段备注
                "default" => $v['COLUMN_DEFAULT'],//字段默认值
                "desc" => "",//字段默认值
            ];
        }
        return $result;

    }

    /**
     * 获取指定表的基本信息
     * @param $table_name
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function getTableInfo($table_name) {
//        //读取表信息
//        $sql = "SELECT * FROM information_schema.TABLES WHERE table_schema = '{$db_name}' and TABLE_NAME='{$table_name}'";
//        $res = Db::query($sql);
//        //读取表结构
//        $sql = "SELECT * FROM information_schema.COLUMNS where table_schema ='{$db_name}' and  TABLE_NAME  = '{$table_name}'";
//        $res = Db::query($sql);

        //读取表信息
        $table_info = Db::table("information_schema.TABLES")->field("*")->where(['TABLE_SCHEMA' => $this->db_name, 'TABLE_NAME' => $table_name])->find();
        //读取表结构
        $table_fields = Db::table("information_schema.COLUMNS")->field("*")->where(['TABLE_SCHEMA' => $this->db_name, 'TABLE_NAME' => $table_name])->select();
        return [
            'table_info' => $table_info,
            'table_fields' => $table_fields,
        ];
    }

}
