<?php

namespace app\api\library\buiapi\traits;

use think\Db;
use think\Loader;
use Exception;
use think\Config;
use app\api\library\buiapi\VerfiyRule;
use think\exception\PDOException;


/**
 * 此基类为公共调用的方法
 */
trait Api
{


    /**
     * 公共方法-列表
     */
    public function index()
    {
        $this->relationSearch = true;
		$this->request->filter('trim,strip_tags,xss_clean');
		list($where, $sort, $order, $offset, $limit) = $this->buildparams();
		$mixWhere = $this->buildwheres(implode(',',$this->_search_field));
		$item = $this->model->where($where)->where($this->_init_where)->where($mixWhere)->order($sort, $order);
        if (!empty($this->_index_field)) {
            $item->field($this->_index_field);
        }
        $list = $this->__handle_index__($item->paginate($limit));
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
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        if ($params) {
            $this->excludeFields = ['s', 'token'];
            $params = $this->preExcludeFields($params);
            Db::startTrans();
            $params = $this->__handle_add_before__($params);
            $this->__handle_filter__('add', $params);
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


    /**
     * 公共方法-编辑
     */
    public function edit($ids = null)
    {

        //1、获取修改的数据
        $this->_init_where[$this->model->getPk()] = $ids;
        $item = $this->model->get($this->_init_where, $this->_edit_with);
        if (empty($item)) {
            $this->error('数据记录不存在');
        }

        //2、获取参数
        $params = $this->request->request('', '', 'trim,xss_clean,strip_tags');
        $params = $this->preExcludeFields($params);

        //3、参数为空 - 获取数据
        if (empty($params)) {
            if (!empty($this->_edit_field)) {
                $item->field($this->_edit_field);
            }
            $row = !empty($item) ? $this->__handle_edit_view__($item->toArray()) : [];
            if (!empty($ids) && !empty($row)) {
                $this->success('修改数据信息', $row);
            }
        }

        //4、参数不为空 - 处理数据更新
        if (!empty($params)) {
            Db::startTrans();
            $params = $this->__handle_edit_before__($params);
            $this->__handle_filter__('edit', $params);
            if ($item->save($params)) {
                $result = $this->__handle_edit_after__($ids, $params);
                if ($result) {
                    Db::commit();
                    $this->_return_data['ids'] = $ids;
                    $this->success('更新数据成功', $this->_return_data);
                } else {
                    Db::rollback();
                    $this->_return_data['ids'] = $ids;
                    $this->error('更新数据失败', $this->_return_data);
                }
            } else {
                Db::rollback();
                $this->error($item->getError());
            }
        }

        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    /**
     * 公共方法-删除
     */
    public function del($ids = "", $delt = 'destroy')
    {
        if ($ids) {
            $count = 0;
            $save = [];
            $del_desc = "删除";
            $item = $this->model->where($this->model->getPk(), 'in', $ids);
            if ($delt != 'restore') {
                $item->where($this->_init_where);
            }
            if ($delt == 'restore') {
                $del_desc = "还原";
            }
            $list = $item->select();
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    switch ($delt) {
                        case 'logic':
                            $save[$this->_del_filed] = 1;
                            $count += $v->isUpdate(true)->save($save);
                            break;
                        case 'destroy':
                            $count += $v->delete(true);
                            break;
                        case 'restore':
                            $save[$this->_del_filed] = 0;
                            $count += $v->isUpdate(true)->save($save);
                            break;
                    }
                }
                $result = $this->__handle_del__($ids, $delt);
                if ($result) {
                    Db::commit();
                } else {
                    Db::rollback();
                    $this->error(sprintf("%s数据失败", $del_desc), ['ids' => $ids]);
                }
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success(sprintf("%s数据成功", $del_desc), ['ids' => $ids]);
            } else {
                $this->error(sprintf("%s数据记录不存在", $del_desc));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    /**
     * 处理过滤模式
     */
    private function __handle_filter__($mode = 'add', $params = [])
    {
        //文件过滤器
        if ($this->_filter_pattern == 'file') {
            $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
            $validate = Loader::validate($name, 'validate', false, 'api');
            if (empty($validate->check($params, [], $mode))) {
                $this->error($validate->getError());
            }
        }
        //数据库规则过滤器
        if ($this->_filter_pattern == 'data') {
            $table_name = str_replace(Config::get('database.prefix'), '', $this->model->getTable());
            list($result, $message) = VerfiyRule::exec($mode, $table_name, $params);
            if ($result) {
                $this->error($message);
            }
        }
    }

    /**
     * 处理编辑是参数处理
     */
    protected function __handle_edit_before__($params)
    {
        return $params;
    }


    /**
     * 处理修改后的逻辑
     * $ids int 修改入库的值 ID
     * $data array 写入数据库的数据
     */
    protected function __handle_edit_after__($ids, $data)
    {
        return true;
    }


    /**
     * 处理编辑显示的逻辑
     * $info array 处理显示的逻辑
     */
    protected function __handle_edit_view__($info)
    {
        return $info;
    }


    /**
     * 处理显示的逻辑
     * $ids int  显示ID
     * $item array 数据库的数据
     */
    protected function __handle_view__($ids, $item)
    {
        return $item->toArray();
    }


    /**
     * 处理删除的逻辑
     * $ids int 删除的值 ID
     * $delt string 删除的类型
     */
    protected function __handle_del__($ids = "", $delt = "")
    {
        return true;
    }


    /**
     * 处理列表的逻辑
     * $list object 列表对象
     */
    protected function __handle_index__($list)
    {
		$list = !empty($list) ? $list->toArray() : [];
		$list = $this->__handle_index_format__($list);
		return $list;
    }
	
	/**
	 * 处理数据格式
	 */
	protected function __handle_index_format__(array $list = []){
		$list_data = [];
		$list_data['total']	= !empty($list['total']) ? $list['total'] : 0;
		$list_data['per_page'] = !empty($list['per_page']) ? $list['per_page'] : 0;
		$list_data['current_page'] = !empty($list['current_page'])  ? $list['current_page'] : 0;
		$list_data['last_page'] = !empty($list['last_page'])  ? $list['last_page'] : 0;
		$list_data['rows'] = !empty($list['data'])  ? $list['data'] : [];
		return $list_data;
	}
	

    /**
     * 处理增加后的逻辑
     * $ids int 最新入库的值 ID
     * $data array 写入数据库的数据
     */
    protected function __handle_add_after__($ids, $data)
    {
        return true;
    }


    /**
     * 处理增加前的逻辑
     * $params array 参数数组
     */
    protected function __handle_add_before__($params)
    {
        return $params;
    }

}
