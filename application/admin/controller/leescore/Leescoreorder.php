<?php

namespace app\admin\controller\leescore;

use app\common\controller\Backend;
use fast\Random;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use think\Db;
use app\common\model\User;
use app\admin\model\LeescoreOrderGoods;
use app\admin\model\LeescoreGoods;
use think\db\exception\BindParamException;
use think\exception\PDOException;

/**
 *
 *
 * @icon fa fa-circle-o
 */
class Leescoreorder extends Backend
{

    /**
     * ScoreOrder模型对象
     */
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('LeescoreOrder');
        $this->view->assign("statusList", $this->model->getStatusList());
    }


    /**
     * 导入
     *
     * @return void
     * @throws PDOException
     * @throws BindParamException
     */
    public function import()
    {
        // echo "<pre>";
        $file = $this->request->request('file');
        if (!$file) {
            $this->error(__('Parameter %s can not be empty', 'file'));
        }
        $filePath = ROOT_PATH . DS . 'public' . DS . $file;
        if (!is_file($filePath)) {
            $this->error(__('No results were found'));
        }
        //实例化reader
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            $this->error(__('Unknown data format'));
        }
        if ($ext === 'csv') {
            $file = fopen($filePath, 'r');
            $filePath = tempnam(sys_get_temp_dir(), 'import_csv');
            $fp = fopen($filePath, 'w');
            $n = 0;
            while ($line = fgets($file)) {
                $line = rtrim($line, "\n\r\0");
                $encoding = mb_detect_encoding($line, ['utf-8', 'gbk', 'latin1', 'big5']);
                if ($encoding !== 'utf-8') {
                    $line = mb_convert_encoding($line, 'utf-8', $encoding);
                }
                if ($n == 0 || preg_match('/^".*"$/', $line)) {
                    fwrite($fp, $line . "\n");
                } else {
                    fwrite($fp, '"' . str_replace(['"', ','], ['""', '","'], $line) . "\"\n");
                }
                $n++;
            }
            fclose($file) || fclose($fp);

            $reader = new Csv();
        } elseif ($ext === 'xls') {
            $reader = new Xls();
        } else {
            $reader = new Xlsx();
        }

        //导入文件首行类型,默认是注释,如果需要使用字段名称请使用name
        $importHeadType = isset($this->importHeadType) ? $this->importHeadType : 'comment';

        $table = $this->model->getQuery()->getTable();
        $database = \think\Config::get('database.database');
        $fieldArr = [];
        $list = db()->query("SELECT COLUMN_NAME,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?", [$table, $database]);
        foreach ($list as $k => $v) {
            if ($importHeadType == 'comment') {
                $v['COLUMN_COMMENT'] = explode(':', $v['COLUMN_COMMENT'])[0]; //字段备注有:时截取
                $fieldArr[$v['COLUMN_COMMENT']] = $v['COLUMN_NAME'];
            } else {
                $fieldArr[$v['COLUMN_NAME']] = $v['COLUMN_NAME'];
            }
        }


        //加载文件
        $insert = [];
        try {
            if (!$PHPExcel = $reader->load($filePath)) {
                $this->error(__('Unknown data format'));
            }
            $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
            $allColumn = $currentSheet->getHighestDataColumn(); //取得最大的列号
            $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
            $maxColumnNumber = Coordinate::columnIndexFromString($allColumn);
            $fields = [];
            for ($currentRow = 1; $currentRow <= 1; $currentRow++) {
                for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                    $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                    $fields[] = $val;
                }
            }

            for ($currentRow = 2; $currentRow <= $allRow; $currentRow++) {
                $values = [];
                for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                    $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                    $values[] = is_null($val) ? '' : $val;
                }



                $dat=[
                    'virtual_sn'=>$values[1],
                    'virtual_name'=>$values[2],
                    'virtual_go_time'=>time(),
                    'other'=>$values[3],
                    'status'=>2,
                ];
                Db::table('fa_leescore_order')->where('order_id',$values[0])->update($dat);




                //  `mobile` varchar(11) DEFAULT '' COMMENT '手机号',
                //  `code` int(255) DEFAULT NULL,
                //  `pass` varchar(255) DEFAULT NULL,
                //  `czbzj` decimal(65,2) DEFAULT '0.00' COMMENT '财政补贴金',
                //  `score` int(10) NOT NULL DEFAULT '0' COMMENT '积分',
                //  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
                //  `fhsfsy` decimal(65,2) DEFAULT '0.00' COMMENT '分红释放收益',
                //  `tdrs` int(255) DEFAULT '0' COMMENT '团队人数',

//                $temp = array_combine($fields, $values);
//                foreach ($temp as $k => $v) {
//                    if (isset($fieldArr[$k]) && $k !== '') {
//                        $row[$fieldArr[$k]] = $v;
//                    }
//                }


            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }



        $this->success('发货成功');
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    //订单审核未通过
    public function faild()
    {
        $id = input('post.ids');
        $other = input('post.result_other');
        $row = $this->model->where("id", $id)->find();

        $order_model = new LeescoreOrderGoods();
        $_order_goods = $order_model->where('order_id', $row['id'])->select();

        // var_dump($_order_goods);
        if ($row['status'] == 0) {
            $data = ['result_other' => $other, 'status' => '-1'];
            Db::name('leescore_order')->where("id", $id)->update($data);
            foreach ($_order_goods as $k => $v) {
                Db::name('leescore_goods')->where('id', $v['goods_id'])->setInc('stock', $v['buy_num']);
                Db::name('leescore_goods')->where('id', $v['goods_id'])->setDec('usenum', $v['buy_num']);
            }

        } else {
            $score_log = new User();
            $data = ['result_other' => $other, 'status' => '-2'];
            // 启动事务
            Db::startTrans();
            try {
                Db::name('leescore_order')->where("id", $row['id'])->update($data);

                //写入积分日志
                $score_log::score($row['score_total'], $row['uid'], '订单驳回返还积分');
                $score_log::score($row['money_total'], $row['uid'], '订单驳回返还金额');
                foreach ($_order_goods as $k => $v) {
                    Db::name('leescore_goods')->where('id', $v['goods_id'])->setInc('stock', $v['buy_num']);
                    Db::name('leescore_goods')->where('id', $v['goods_id'])->setDec('usenum', $v['buy_num']);
                }

                Db::commit();

            } catch (\Exception $e) {
                // 回滚
                Db::rollback();
                $this->error($e);
            }
            $this->success(__('order faild tip success'));
        }
    }


    /*发货*/
    public function send()
    {
        if ($this->request->isPost()) {
            $id = input('post.ids');
            $row = $this->model->find($id);
            $status = 2;

            $data['status'] = $status;
            $data['result_other'] = input('post.virtual_other');
            $data['virtual_go_time'] = time();

            if ($status == 3) {
                $data['virtual_sign_time'] = time();
            }
            $data['virtual_sn'] = input('post.virtual_sn');
            $data['virtual_name'] = input('post.virtual_name');

            $this->model->where("id = $id")->update($data);
            $this->success();
        }

        $param = $this->request->param();
        $row = $this->model->with('getOrderGoods,addressInfo')->find($param['ids']);
        $this->view->assign('vo', $row);
        return $this->view->fetch();
    }


    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $count = $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            foreach ($list as $k => $v) {
                $count += $v->delete();

                Db::name('leescore_order_goods')->where('order_id', $v['id'])->delete();
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams('order_id');
            $total = $this->model
                ->with('user,addressInfo')
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with('user,addressInfo')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = array("total" => $total, "rows" => $list);
            return json($result);
        }
        return $this->view->fetch();
    }

    public function getOrderGoods()
    {
        $id = input('post.id');
        $data = model('leescoreOrderGoods')->getOrderGoods($id);
        return json($data);
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $w['id'] = $ids;
        $row = $this->model->get($ids);
        //$row->user->username;
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
            $params = $this->request->post("row/a");
            if ($params) {
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = basename(str_replace('\\', '/', get_class($this->model)));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : true) : $this->modelValidate;
                        $row->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    } else {
                        $this->error($row->getError());
                    }
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * Selectpage搜索
     *
     * @internal
     */
    public function selectpage()
    {
        return parent::selectpage();
    }
}
