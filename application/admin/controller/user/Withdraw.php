<?php

namespace app\admin\controller\user;

use addons\epay\library\Service;
use app\common\controller\Backend;
use think\Exception;
use Yansongda\Pay\Pay;

/**
 * 提现管理
 *
 * @icon fa fa-circle-o
 */
class Withdraw extends Backend
{

    /**
     * Withdraw模型对象
     * @var \app\admin\model\user\Withdraw
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Withdraw;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 获取审核中提现记录总数
     */
    public function getCreatedCount()
    {
        try {
            $count = $this->model
                ->where('status', 'created')
                ->count();
            
            return json(['code' => 1, 'count' => $count]);
        } catch (Exception $e) {
            return json(['code' => 0, 'message' => '获取记录数失败：' . $e->getMessage()]);
        }
    }

    /**
     * 导出审核中状态的提现记录
     */
    public function exportCreated()
    {
        // 设置更长的执行时间限制
        set_time_limit(300); // 5分钟
        ini_set('memory_limit', '2048M'); // 2GB内存
        ini_set('max_execution_time', 300);
        
        // 设置文件名
        $filename = '审核中提现记录_' . date('Y-m-d_H-i-s') . '.csv';

        // 设置响应头
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // 输出BOM，解决中文乱码问题
        echo "\xEF\xBB\xBF";

        // 创建输出流
        $output = fopen('php://output', 'w');

        // 写入表头
        $headers = [
            'ID',
            '会员ID', 
            '会员昵称',
            '提现金额',
            '手续费',
            '税费',
            '最终到账',
            '提现账户类型',
            '提现账户',
            '真实姓名',
            '银行名称',
            '银行支行',
            '订单号',
            '申请时间',
            '备注'
        ];
        fputcsv($output, $headers);

        // 分批处理数据，避免内存溢出
        $page = 1;
        $limit = 50; // 减少每批处理的数量
        $totalProcessed = 0;
        
        do {
            // 获取当前页的数据
            $list = $this->model
                ->alias('w')
                ->join('user u', 'w.user_id = u.id', 'LEFT')
                ->field('w.id, w.user_id, w.money, w.handingfee, w.taxes, w.money_type, w.account, w.name, w.bank_name, w.bank_zhi, w.orderid, w.createtime, w.memo, u.nickname as user_nickname')
                ->where('w.status', 'created')
                ->order('w.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            if (empty($list)) {
                break;
            }

            // 写入数据
            foreach ($list as $item) {
                $row = [
                    $item['id'],
                    $item['user_id'],
                    $item['user_nickname'] ?? '',
                    $item['money'],
                    $item['handingfee'],
                    $item['taxes'],
                    $this->getSettledmoney($item['money'], $item['handingfee'], $item['taxes']),
                    $item['money_type'],
                    $item['account'],
                    $item['name'],
                    $item['bank_name'],
                    $item['bank_zhi'],
                    $item['orderid'],
                    date('Y-m-d H:i:s', $item['createtime']),
                    $item['memo']
                ];
                fputcsv($output, $row);
                $totalProcessed++;
            }

            // 立即刷新输出缓冲区
            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            // 清理内存
            unset($list);
            
            $page++;
            
            // 检查是否超时
            if (time() - $_SERVER['REQUEST_TIME'] > 240) { // 4分钟超时
                break;
            }
            
        } while (true);

        fclose($output);
        exit;
    }

    /**
     * 分批导出审核中状态的提现记录
     */
    public function exportCreatedBatch()
    {
        $startDate = $this->request->param('start_date', '');
        $endDate = $this->request->param('end_date', '');
        
        // 设置更长的执行时间限制
        set_time_limit(300); // 5分钟
        ini_set('memory_limit', '2048M'); // 2GB内存
        ini_set('max_execution_time', 300);
        
        // 构建查询条件
        $where = [['w.status', '=', 'created']];
        if ($startDate) {
            $where[] = ['w.createtime', '>=', strtotime($startDate)];
        }
        if ($endDate) {
            $where[] = ['w.createtime', '<=', strtotime($endDate . ' 23:59:59')];
        }
        
        // 设置文件名
        $filename = '审核中提现记录';
        if ($startDate && $endDate) {
            $filename .= '_' . $startDate . '_' . $endDate;
        } elseif ($startDate) {
            $filename .= '_' . $startDate . '_至今';
        } elseif ($endDate) {
            $filename .= '_至今_' . $endDate;
        }
        $filename .= '_' . date('Y-m-d_H-i-s') . '.csv';

        // 设置响应头
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        // 输出BOM，解决中文乱码问题
        echo "\xEF\xBB\xBF";

        // 创建输出流
        $output = fopen('php://output', 'w');

        // 写入表头
        $headers = [
            'ID',
            '会员ID', 
            '会员昵称',
            '提现金额',
            '手续费',
            '税费',
            '最终到账',
            '提现账户类型',
            '提现账户',
            '真实姓名',
            '银行名称',
            '银行支行',
            '订单号',
            '申请时间',
            '备注'
        ];
        fputcsv($output, $headers);

        // 分批处理数据，避免内存溢出
        $page = 1;
        $limit = 50; // 减少每批处理的数量
        $totalProcessed = 0;
        
        do {
            // 获取当前页的数据
            $list = $this->model
                ->alias('w')
                ->join('user u', 'w.user_id = u.id', 'LEFT')
                ->field('w.id, w.user_id, w.money, w.handingfee, w.taxes, w.money_type, w.account, w.name, w.bank_name, w.bank_zhi, w.orderid, w.createtime, w.memo, u.nickname as user_nickname')
                ->where($where)
                ->order('w.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            if (empty($list)) {
                break;
            }

            // 写入数据
            foreach ($list as $item) {
                $row = [
                    $item['id'],
                    $item['user_id'],
                    $item['user_nickname'] ?? '',
                    $item['money'],
                    $item['handingfee'],
                    $item['taxes'],
                    $this->getSettledmoney($item['money'], $item['handingfee'], $item['taxes']),
                    $item['money_type'],
                    $item['account'],
                    $item['name'],
                    $item['bank_name'],
                    $item['bank_zhi'],
                    $item['orderid'],
                    date('Y-m-d H:i:s', $item['createtime']),
                    $item['memo']
                ];
                fputcsv($output, $row);
                $totalProcessed++;
            }

            // 立即刷新输出缓冲区
            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            // 清理内存
            unset($list);
            
            $page++;
            
            // 检查是否超时
            if (time() - $_SERVER['REQUEST_TIME'] > 240) { // 4分钟超时
                break;
            }
            
        } while (true);

        fclose($output);
        exit;
    }

    /**
     * 计算最终到账金额
     */
    private function getSettledmoney($money, $handingfee, $taxes)
    {
        return max(0, sprintf("%.2f", $money - $handingfee - $taxes));
    }

    //查询转账状态
    public function query()
    {
        $ids = $this->request->param('ids', '');
        $model = $this->model->where('id', $ids)->find();
        if (!$model) {
            $this->error(__('No Results were found'));
        }
        $info = get_addon_info('epay');
        if (!$info || !$info['state']) {
            $this->error('请检查微信支付宝整合插件是否正确安装且启用');
        }
        $result = null;
        try {
            $config = Service::getConfig('alipay');
            $pay = Pay::alipay($config);
            $result = $pay->find($model['orderid'], 'transfer');

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        if ($result && isset($result['code']) && $result['code'] == 10000) {
            $this->success("转账成功！");
        } else {
            $this->error('转账失败！');
        }
    }



    public function editss($ids){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=input('row/a');



            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }


    public function editssall($ids=null){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=input('row/a');


            $this->model->where('id','neq',0)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }


    public function ok($ids=null){
        $this->assign('ids',$ids);
        $info = $this->model->where('id', $ids)->find();
        if($info['status'] == 'rejected'){
            $this->error('已拒绝的订单不可以再次通过');
        }
        if(request()->isPost()){
            $data=['status'=>'successed'];

            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }
    public function no($ids=null){
        $this->assign('ids',$ids);
        if(request()->isPost()){
            $data=['status'=>'rejected'];
            $this->model->whereIn('id',$ids)->update($data);
            $this->success('操作成功',request()->url());
        }else{

            return view();
        }
    }

    /**
     * 创建后台导出任务
     */
    public function createExportTask()
    {
        $startDate = $this->request->param('start_date', '');
        $endDate = $this->request->param('end_date', '');
        
        // 生成任务ID
        $taskId = 'export_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
        
        // 构建任务数据
        $taskData = [
            'task_id' => $taskId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'created_at' => time(),
            'total_records' => 0,
            'processed_records' => 0
        ];
        
        // 保存任务信息到数据库或缓存
        cache('export_task_' . $taskId, $taskData, 3600); // 1小时过期
        
        // 异步执行导出任务
        $this->executeExportTask($taskId, $startDate, $endDate);
        
        return json(['code' => 1, 'task_id' => $taskId, 'message' => '导出任务已创建']);
    }
    
    /**
     * 执行导出任务
     */
    private function executeExportTask($taskId, $startDate, $endDate)
    {
        // 更新任务状态为处理中
        $taskData = cache('export_task_' . $taskId);
        $taskData['status'] = 'processing';
        cache('export_task_' . $taskId, $taskData, 3600);
        
        // 构建查询条件
        $where = 'w.status = "created"';
        if ($startDate) {
            $where .= ' AND w.createtime >= ' . strtotime($startDate);
        }
        if ($endDate) {
            $where .= ' AND w.createtime <= ' . strtotime($endDate . ' 23:59:59');
        }
        
        // 获取总记录数
        $totalCount = $this->model
            ->alias('w')
            ->whereRaw($where)
            ->count();
        
        // 更新总记录数
        $taskData['total_records'] = $totalCount;
        cache('export_task_' . $taskId, $taskData, 3600);
        
        // 设置文件名
        $filename = '审核中提现记录';
        if ($startDate && $endDate) {
            $filename .= '_' . $startDate . '_' . $endDate;
        } elseif ($startDate) {
            $filename .= '_' . $startDate . '_至今';
        } elseif ($endDate) {
            $filename .= '_至今_' . $endDate;
        }
        $filename .= '_' . date('Y-m-d_H-i-s') . '.csv';
        
        // 创建文件路径
        $filePath = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'exports' . DS . $filename;
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 创建输出文件
        $output = fopen($filePath, 'w');
        
        // 写入BOM
        fwrite($output, "\xEF\xBB\xBF");
        
        // 写入表头
        $headers = [
            'ID',
            '会员ID', 
            '会员昵称',
            '提现金额',
            '手续费',
            '税费',
            '最终到账',
            '提现账户类型',
            '提现账户',
            '真实姓名',
            '银行名称',
            '银行支行',
            '订单号',
            '申请时间',
            '备注'
        ];
        fputcsv($output, $headers);
        
        // 分批处理数据
        $page = 1;
        $limit = 100;
        $processed = 0;
        
        do {
            // 获取当前页的数据
            $list = $this->model
                ->alias('w')
                ->join('user u', 'w.user_id = u.id', 'LEFT')
                ->field('w.id, w.user_id, w.money, w.handingfee, w.taxes, w.money_type, w.account, w.name, w.bank_name, w.bank_zhi, w.orderid, w.createtime, w.memo, u.nickname as user_nickname')
                ->whereRaw($where)
                ->order('w.createtime', 'desc')
                ->page($page, $limit)
                ->select();
            
            if (empty($list)) {
                break;
            }
            
            // 写入数据
            foreach ($list as $item) {
                $row = [
                    $item['id'],
                    $item['user_id'],
                    $item['user_nickname'] ?? '',
                    $item['money'],
                    $item['handingfee'],
                    $item['taxes'],
                    $this->getSettledmoney($item['money'], $item['handingfee'], $item['taxes']),
                    $item['money_type'],
                    $item['account'],
                    $item['name'],
                    $item['bank_name'],
                    $item['bank_zhi'],
                    $item['orderid'],
                    date('Y-m-d H:i:s', $item['createtime']),
                    $item['memo']
                ];
                fputcsv($output, $row);
                $processed++;
            }
            
            // 更新进度
            $taskData['processed_records'] = $processed;
            cache('export_task_' . $taskId, $taskData, 3600);
            
            // 清理内存
            unset($list);
            
            $page++;
            
        } while (true);
        
        fclose($output);
        
        // 更新任务状态为完成
        $taskData['status'] = 'completed';
        $taskData['file_path'] = '/uploads/exports/' . $filename;
        $taskData['completed_at'] = time();
        cache('export_task_' . $taskId, $taskData, 3600);
    }
    
    /**
     * 获取导出任务状态
     */
    public function getExportTaskStatus()
    {
        $taskId = $this->request->param('task_id', '');
        
        if (!$taskId) {
            return json(['code' => 0, 'message' => '任务ID不能为空']);
        }
        
        $taskData = cache('export_task_' . $taskId);
        
        if (!$taskData) {
            return json(['code' => 0, 'message' => '任务不存在']);
        }
        
        return json([
            'code' => 1,
            'data' => $taskData
        ]);
    }
    
    /**
     * 下载导出文件
     */
    public function downloadExportFile()
    {
        $taskId = $this->request->param('task_id', '');
        
        if (!$taskId) {
            $this->error('任务ID不能为空');
        }
        
        $taskData = cache('export_task_' . $taskId);
        
        if (!$taskData || $taskData['status'] !== 'completed') {
            $this->error('文件未准备好或任务不存在');
        }
        
        $filePath = ROOT_PATH . 'public' . $taskData['file_path'];
        
        if (!file_exists($filePath)) {
            $this->error('文件不存在');
        }
        
        // 输出文件
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

}
