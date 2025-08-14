<?php

namespace app\api\library\buiapi;

use app\common\library\Auth;
use think\Config;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Hook;
use think\Lang;
use think\Loader;
use think\Request;
use think\Response;
use think\Route;
use think\Validate;

/**
 * API控制器基类
 */
class Api extends \app\common\controller\Api
{

    /**
     * 临时变量
     */
    protected $_interim_data = [];

    /**
     * 附件链接地址
     */
    protected $_attachment_url = null;

    /**
     * 首页需要显示的字段
     */
    protected $_index_field = [];

    /**
     * 首页需要搜索的字段
     */
    protected $_search_field = [];

    /**
     * 修改需要显示的字段
     */
    protected $_edit_field = [];

    /**
     * 详情需要显示的字段
     */
    protected $_view_field = [];

    /**
     * 详情需要显示关联的模型
     */
    protected $_view_with = [];

    /**
     * 修改需要显示关联的模型
     */
    protected $_edit_with = [];

    /**
     * 初始化条件模
     */
    protected $_init_where = [];

    /**
     * 过滤模式
     */
    protected $_filter_pattern = 'data';

    /**
     * 自定义标记删除字段
     */
    protected $_del_filed = 'is_del';

    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';

    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    /**
     * 前台提交过来,需要排除的字段数据
     */
    protected $excludeFields = ['s', 'ids', 'token'];

    /**
     * 添加和修改返回自定义数据
     * add or edit func
     */
    protected $_return_data = [];

    /**
     * 接口可以通过访问的方法
     */
    protected $_allow_func = [];

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        parent::_initialize();
        if (!$this->valifunc(strtolower($this->request->action()))) {
            $this->error(__('您访问的接口不存在'));
        }
    }

	/**
     * 构造方法,处理JSON格式提交数据
     */
    public function __construct(Request $request = null)
    {
		$this->__param_json_data__();
		parent::__construct($request);
    }

	/**
	 * 处理json 和 header token 数据
	 */
	protected function __param_json_data__(){
		//header token
		$headers = getallheaders();
		//json data
        $content_type = isset($headers['Content-Type']) ? trim($headers['Content-Type']) : '';
        if (strpos($content_type, 'application/json') !== false) {
            $json_data = file_get_contents("php://input");
            if (!empty($json_data)) {
                if (!$this->isVerifyJson($json_data)) {
                    exit(json_encode(['code'=>0,'msg'=>'数据JSON参数格式不正确','time'=>time(),'data'=>null],true));
                }
                $_REQUEST = $_POST = json_decode($json_data,true);
            }
        }
        
		if(isset($headers['token']) && !empty($headers['token'])) {
			$_POST['token'] = $_REQUEST['token'] = trim($headers['token']);
		}
	}

    /**
     * 验证当前的请求方法
     */
    protected function valifunc($func)
    {
        $allow_func = is_array($this->_allow_func) ? $this->_allow_func : explode(',', $this->_allow_func);
        if ($allow_func == "*" || in_array("*", $allow_func) || in_array($func, $allow_func)) {
            return true;
        }
        return false;
    }

    /**
     * 排除前台提交过来的字段
     */
    protected function preExcludeFields($params)
    {
        if (is_array($this->excludeFields)) {
            foreach ($this->excludeFields as $field) {
                if (key_exists($field, $params)) {
                    unset($params[$field]);
                }
            }
        } else {
            if (key_exists($this->excludeFields, $params)) {
                unset($params[$this->excludeFields]);
            }
        }
        return $params;
    }

	/**
     * 生成查询所需要的条件
     * @return array
     */
    protected function buildwheres($field=""){
        $rows  = input("param.");
        $row   = [];
		$where = [];
        foreach ($rows as $k => $v) {
            if (strpos($k, '-') === false || (trim($v) == "" && $v != '0')) {
                if (!empty(trim($v))) {
                    $row[$k] = $v;
                }
                continue;
            }
            $info = explode('-', $k);
			if(empty($field)){
				continue;
			}
			//限制字段搜索
			if(!empty($field) && !in_array($info[0],explode(',',$field))){
				continue;
			}
            $row[$info[0]] = $v;
            $k = trim(str_replace('/', '.', $info[0]));
            $sym = strtoupper(empty($info[1]) ? '=' : $info[1]);
            switch ($sym) {
                case 'EQ':
                case '=':
                case '<>':
                    $where[] = [$k, $sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace('%...%', '', $sym)), "%{$v}%"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FINDINSET':
                case 'FIND_IN_SET':
                    $where[] = "FIND_IN_SET('{$v}', " . ($relationSearch ? $k : '`' . str_replace('.', '`.`', $k) . '`') . ")";
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(' ~ ', $v), 0, 2);
                    if (stripos($v, ' ~ ') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, [strtotime($arr[0]), strtotime($arr[1])]];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' time', $arr];
                    break;
                case 'LIKE':
                case 'LIKE %...%':
                    $where[] = [$k, 'LIKE', "%{$v}%"];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                case 'OR':
                    $where[] = [str_replace(',', '|', $k), 'EQ', $v];
                    break;
                default:
                    break;
            }
        }
        $where = function ($query) use ($where) {
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };

        return $where;
    }


    /**
     * 生成查询所需要的条件,排序方式
     * @param mixed $searchfields 快速查询的字段
     * @param boolean $relationSearch 是否关联查询
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->request("search", '');
        $filter = $this->request->request("filter", '');
        $op = $this->request->request("op", '', 'trim');
        $sort = $this->request->request("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
        $order = $this->request->request("order", "DESC");
        $offset = $this->request->request("offset/d", 0);
        $limit = $this->request->request("limit/d", 20);
        //新增自动计算页码
        $page = $limit ? intval($offset / $limit) + 1 : 1;
        if ($this->request->has("page")) {
            $page = $this->request->request("page/d", 1);
        }
        $this->request->request([config('paginate.var_page') => $page]);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        $where = [];
        $alias = [];
        $bind = [];
        $name = '';
        $aliasName = '';
        if (!empty($this->model) && $this->relationSearch) {
            $name = $this->model->getTable();
            $alias[$name] = Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
            $aliasName = $alias[$name] . '.';
        }
        $sortArr = explode(',', $sort);
        foreach ($sortArr as $index => & $item) {
            $item = stripos($item, ".") === false ? $aliasName . trim($item) : $item;
        }
        unset($item);
        $sort = implode(',', $sortArr);
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $aliasName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        $index = 0;
        foreach ($filter as $k => $v) {
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $k) || !in_array($k, $this->_search_field)) {
                continue;
            }
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $aliasName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            //null和空字符串特殊处理
            if (!is_array($v)) {
                if (in_array(strtoupper($v), ['NULL', 'NOT NULL'])) {
                    $sym = strtoupper($v);
                }
                if (in_array($v, ['""', "''"])) {
                    $v = '';
                    $sym = '=';
                }
            }

            switch ($sym) {
                case '=':
                case '<>':
                    $where[] = [$k, $sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace('%...%', '', $sym)), "%{$v}%"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FINDINSET':
                case 'FIND_IN_SET':
                    $v = is_array($v) ? $v : explode(',', str_replace(' ', ',', $v));
                    $findArr = array_values($v);
                    foreach ($findArr as $idx => $item) {
                        $bindName = "item_" . $index . "_" . $idx;
                        $bind[$bindName] = $item;
                        $where[] = "FIND_IN_SET(:{$bindName}, `" . str_replace('.', '`.`', $k) . "`)";
                    }
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':

                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr, function ($v) {
                            return $v != '' && $v !== false && $v !== null;
                        })) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $tableArr = explode('.', $k);
                    if (count($tableArr) > 1 && $tableArr[0] != $name && !in_array($tableArr[0], $alias) && !empty($this->model)) {
                        //修复关联模型下时间无法搜索的BUG
                        $relation = Loader::parseName($tableArr[0], 1, false);
                        $alias[$this->model->$relation()->getTable()] = $tableArr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' TIME', $arr];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
            $index++;
        }
        if (!empty($this->model)) {
            $this->model->alias($alias);
        }
        $model = $this->model;
        $where = function ($query) use ($where, $alias, $bind, &$model) {
            if (!empty($model)) {
                $model->alias($alias);
                $model->bind($bind);
            }
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };
        return [$where, $sort, $order, $offset, $limit, $page, $alias, $bind];
    }

	/**
     * 判断是否是JOSN格式数据
     */
    protected function isVerifyJson($string) {
        json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }

}
