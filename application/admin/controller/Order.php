<?php

namespace app\admin\controller;

use app\common\controller\Backend;

use think\Db;
/**
 * 项目订单
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Order;
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
                    ->with(['user','projectdata'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

//            foreach ($list as $row) {
//
//                $row->getRelation('user')->visible(['username','nickname']);
//            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }
public function export()
    {
        $this->relationSearch = true;
        if ($this->request->isPost()) {
            // set_time_limit(0);
            ini_set('memory_limit', '512M');
            set_time_limit(0);
            $search = $this->request->post('search');
            $ids = $this->request->post('ids');
            $filter = $this->request->post('filter');
            $op = $this->request->post('op');
            $columns = $this->request->post('columns');

            $whereIds = $ids == 'all' ? '1=1' : ['order.id' => ['in', explode(',', $ids)]];
            $this->request->get(['search' => $search, 'ids' => $ids, 'filter' => $filter, 'op' => $op]);
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            $line = 1;
            
            //设置过滤方法
            $this->request->filter(['strip_tags']);

            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            $sql = $this->model
                ->with(['user','projectdata'])
                ->where($where)
                ->where($whereIds)
                ->field('order.id as id,order.user_id as user_id,order.status as status,order.paytype as paytype,order.qty as qty,order.price as price,order.project_data_id as project_data_id,order.payprice as payprice,order.paytime as paytime,user.mobile as mobile,user.nickname as nickname,user.upid as upid,user.is_rot as is_rot,user.loginip as loginip,projectdata.name as name,projectdata.times as times')
                ->select(false);
            // $sql=Db::getLastSql();
            $time=time();
            $total = $this->model
                ->with(['user','projectdata'])
                ->where($where)
                ->where($whereIds)
                ->count();
            $count=Db::name('exportlist')->where(['sql'=>$sql,'total'=>$total,'createtime'=>['<',$time-3600]])->count();
            
            if ($count) {
                $this->error('短时间内出现相同导出，请查看导出记录！');
            }
            $export=[
                'admin_id'=>$this->auth->id,
                'sql'=>$sql,
                'total'=>$total,
                'beginning'=>0,
                'createtime'=>$time,
                'updatetime'=>$time,
            ];
            Db::name('exportlist')->insert($export);
                $this->success('导出创建成功，请等待！');
            // 创建表格和 Sheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // 写入表头
            $sheet->fromArray(['ID', '用户ID', '手机号', '用户类型', '上级ID', '姓名', '项目名称', '总周期', '释放周期', 'IP', '发放次数', '已领分红', '待领分红', '订单状态', '支付类型', '数量', '支付金额', '项目编号', '购买金额', '购买时间'], NULL, 'A1');
            
            // 每页查询行数
            $pageSize = 1000;
            $page = 0;
            $rowIndex = 2; // 从第2行开始写入数据
            
            $is_rot=[1=>'普通用户','2'=>'机器人'];
            $status=[1=>'待支付','2'=>'已支付'];
            $paytype=['money'=>'可提现余额支付','nomoney'=>'账户余额支付','borrow_money'=>'借贷支付','money_tuijian'=>'推荐支付','money_qiandao'=>'签到支付','money_shifang'=>'释放余额'];
            
            do {
                $offset = $page * $pageSize;
                // $data = $this->model
                // ->with(['user','projectdata'])
                // ->where($where)
                // ->where($whereIds)
                // ->limit($offset, $pageSize)
                // ->select();
                $data = Db::name('order') // 替换为你的 model 表名，例如 'order'
                    ->alias('order') // 主表别名（可选）
                    ->join('user u', 'order.user_id = u.id','left')
                    ->join('project_data p', 'order.project_data_id = p.id','left')
                    ->where($where)
                    ->where($whereIds)
                    ->limit($offset, $pageSize)
                    ->field('order.id as id,order.user_id as user_id,order.status as status,order.paytype as paytype,order.qty as qty,order.price as price,order.project_data_id as project_data_id,order.payprice as payprice,order.paytime as paytime,u.mobile as mobile,u.nickname as nickname,u.upid as upid,u.is_rot as is_rot,u.loginip as loginip,p.name as name,p.times as times')
                    ->select();
                if (empty($data)) break;
            
                foreach ($data as $row) {
//                 //向模板表中写入数据
                    // $sheet->setCellValue('A'.$rowIndex, $row['id']);   //送入A1的内容
                    // $sheet->setCellValue('B'.$rowIndex, $row['user_id']);   //送入A1的内容
                    // $sheet->setCellValue('C'.$rowIndex, $row['user']['mobile']);   //送入A1的内容
                    // $sheet->setCellValue('D'.$rowIndex, $is_rot[$row['user']['is_rot']]);   //送入A1的内容
                    // $sheet->setCellValue('E'.$rowIndex, $row['user']['upid']);   //送入A1的内容
                    // $sheet->setCellValue('F'.$rowIndex, $row['user']['nickname']);   //送入A1的内容
                    // $sheet->setCellValue('G'.$rowIndex, $row['projectdata']['name']);   //送入A1的内容
                    // $sheet->setCellValue('H'.$rowIndex, $row['projectdata']['times']);   //送入A1的内容
                    // $sheet->setCellValue('I'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                    // $sheet->setCellValue('J'.$rowIndex, $row['user']['loginip']);   //送入A1的内容
                    // $sheet->setCellValue('K'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                    // $sheet->setCellValue('L'.$rowIndex, $row['yiling']);   //送入A1的内容
                    // $sheet->setCellValue('M'.$rowIndex, $row['dailing']);   //送入A1的内容
                    // $sheet->setCellValue('N'.$rowIndex, $status[$row['status']]);   //送入A1的内容
                    // $sheet->setCellValue('O'.$rowIndex, $paytype[$row['paytype']]);   //送入A1的内容
                    // $sheet->setCellValue('P'.$rowIndex, $row['qty']);   //送入A1的内容
                    // $sheet->setCellValue('Q'.$rowIndex, $row['price']);   //送入A1的内容
                    // $sheet->setCellValue('R'.$rowIndex, $row['project_data_id']);   //送入A1的内容
                    // $sheet->setCellValue('S'.$rowIndex, $row['payprice']);   //送入A1的内容
                    // $sheet->setCellValue('T'.$rowIndex, date('Y-m-d H:i:s', $row['paytime']));   //送入A1的内容
                    $sheet->setCellValue('A'.$rowIndex, $row['id']);   //送入A1的内容
                    $sheet->setCellValue('B'.$rowIndex, $row['user_id']);   //送入A1的内容
                    $sheet->setCellValue('C'.$rowIndex, $row['mobile']);   //送入A1的内容
                    $sheet->setCellValue('D'.$rowIndex, $is_rot[$row['is_rot']]);   //送入A1的内容
                    $sheet->setCellValue('E'.$rowIndex, $row['upid']);   //送入A1的内容
                    $sheet->setCellValue('F'.$rowIndex, $row['nickname']);   //送入A1的内容
                    $sheet->setCellValue('G'.$rowIndex, $row['name']);   //送入A1的内容
                    $sheet->setCellValue('H'.$rowIndex, $row['times']);   //送入A1的内容
                    $sheet->setCellValue('I'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                    $sheet->setCellValue('J'.$rowIndex, $row['loginip']);   //送入A1的内容
                    $sheet->setCellValue('K'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                    $sheet->setCellValue('L'.$rowIndex, $row['yiling']);   //送入A1的内容
                    $sheet->setCellValue('M'.$rowIndex, $row['dailing']);   //送入A1的内容
                    $sheet->setCellValue('N'.$rowIndex, $status[$row['status']]);   //送入A1的内容
                    $sheet->setCellValue('O'.$rowIndex, $paytype[$row['paytype']]);   //送入A1的内容
                    $sheet->setCellValue('P'.$rowIndex, $row['qty']);   //送入A1的内容
                    $sheet->setCellValue('Q'.$rowIndex, $row['price']);   //送入A1的内容
                    $sheet->setCellValue('R'.$rowIndex, $row['project_data_id']);   //送入A1的内容
                    $sheet->setCellValue('S'.$rowIndex, $row['payprice']);   //送入A1的内容
                    $sheet->setCellValue('T'.$rowIndex, date('Y-m-d H:i:s', $row['paytime']));   //送入A1的内容
                    $rowIndex++;
                }
            
                $page++;
            } while (count($data) == $pageSize);
            
            // 保存到文件
            $filename = ROOT_PATH . 'public/export/export_' . date('Ymd_His') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);

            return;
            
            
            
            
//             $list = $this->model
//                 ->with(['user','projectdata'])
//                 ->where($where)
//                 ->where($whereIds)
//                 ->select();
//             // if (count($list)>500) {
//             //     var_dump($where);exit();
//             // }
//             $list = collection($list)->toArray();
//             $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__ . '/2025-06-05_order.xls');  //读取模板
//             $worksheet = $spreadsheet->getActiveSheet();     //指向激活的工作表
//             // $worksheet->setTitle('订单列表');
//             $is_rot=[1=>'普通用户','2'=>'机器人'];
//             $status=[1=>'待支付','2'=>'已支付'];
//             $paytype=['money'=>'可提现余额支付','nomoney'=>'账户余额支付','borrow_money'=>'借贷支付','money_tuijian'=>'推荐支付','money_qiandao'=>'签到支付'];
//             foreach ($list as $i=>$val){
                
//                 $a=$i+8485;
//                 //向模板表中写入数据
//                 $worksheet->setCellValue('A'.$a, $list[$i]['id']);   //送入A1的内容
//                 $worksheet->setCellValue('B'.$a, $list[$i]['user_id']);   //送入A1的内容
//                 $worksheet->setCellValue('C'.$a, $list[$i]['user']['mobile']);   //送入A1的内容
//                 $worksheet->setCellValue('D'.$a, $is_rot[$list[$i]['user']['is_rot']]);   //送入A1的内容
//                 $worksheet->setCellValue('E'.$a, $list[$i]['user']['upid']);   //送入A1的内容
//                 $worksheet->setCellValue('F'.$a, $list[$i]['user']['nickname']);   //送入A1的内容
//                 $worksheet->setCellValue('G'.$a, $list[$i]['projectdata']['name']);   //送入A1的内容
//                 $worksheet->setCellValue('H'.$a, $list[$i]['projectdata']['times']);   //送入A1的内容
//                 $worksheet->setCellValue('I'.$a, $list[$i]['projecttaskok']);   //送入A1的内容
//                 $worksheet->setCellValue('J'.$a, $list[$i]['user']['loginip']);   //送入A1的内容
//                 $worksheet->setCellValue('K'.$a, $list[$i]['projecttaskok']);   //送入A1的内容
//                 $worksheet->setCellValue('L'.$a, $list[$i]['yiling']);   //送入A1的内容
//                 $worksheet->setCellValue('M'.$a, $list[$i]['dailing']);   //送入A1的内容
//                 $worksheet->setCellValue('N'.$a, $status[$list[$i]['status']]);   //送入A1的内容
//                 $worksheet->setCellValue('O'.$a, $paytype[$list[$i]['paytype']]);   //送入A1的内容
//                 $worksheet->setCellValue('P'.$a, $list[$i]['qty']);   //送入A1的内容
//                 $worksheet->setCellValue('Q'.$a, $list[$i]['price']);   //送入A1的内容
//                 $worksheet->setCellValue('R'.$a, $list[$i]['project_data_id']);   //送入A1的内容
//                 $worksheet->setCellValue('S'.$a, $list[$i]['payprice']);   //送入A1的内容
//                 $worksheet->setCellValue('T'.$a, date('Y-m-d H:i:s', $list[$i]['paytime']));   //送入A1的内容
//             }
//             $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
//                 //下载文档
//             // header('Content-Type: application/vnd.ms-excel');
//             // header('Content-Disposition: attachment;filename="'. date('Y-m-d') . '_order'.'.xls"');
//             // header('Cache-Control: max-age=0');
//             // $writer->save('php://output');
// $savePath = ROOT_PATH . 'public' . DS . 'export' . DS . date('Y-m-d') . '_order.xls';
// $writer->save($savePath);
//             return;
        }
    }
}
