<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\Db;


use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class Exportorder extends Command
{

    protected function configure()
    {
        $this->setName('exportorder')
            ->setDescription('order');
    }

    //定时任务每分钟执行
    protected function execute(Input $input, Output $output)
    {
            $exportlist=Db::name('exportlist')->where(['status'=>0])->find();
            if ($exportlist) {
                
                Db::name('exportlist')->where(['id'=>$exportlist['id']])->update(['status'=>1,'beginning'=>$exportlist['total']]);
                ini_set('memory_limit', '512M');
                set_time_limit(0);
            // 创建表格和 Sheet
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
            
                // 写入表头
                // $sheet->fromArray(['ID', '用户ID', '手机号', '用户类型', '上级ID', '姓名', '项目名称', '总周期', '释放周期', 'IP', '发放次数', '已领分红', '待领分红', '订单状态', '支付类型', '数量', '支付金额', '项目编号', '购买金额', '购买时间'], NULL, 'A1');
                $sheet->fromArray(['ID', '用户ID', '手机号', '用户类型', '上级ID', '姓名', '项目名称', '总周期', 'IP', '订单状态', '支付类型', '数量', '支付金额', '项目编号', '购买金额', '购买时间'], NULL, 'A1');
                
                // 每页查询行数
                $pageSize = 1000;
                $page = 0;
                $rowIndex = 2; // 从第2行开始写入数据
                
                $is_rot=[1=>'普通用户','2'=>'机器人'];
                $status=[1=>'待支付','2'=>'已支付'];
                $paytype=['money'=>'可提现余额支付','nomoney'=>'账户余额支付','borrow_money'=>'借贷支付','money_tuijian'=>'推荐支付','money_qiandao'=>'签到支付','money_shifang'=>'释放余额'];
                
                do {
                    $offset = $page * $pageSize;
                    $data = Db::query($exportlist['sql'].' limit '.$offset.','.$pageSize);
                    if (empty($data)) break;
                
                    foreach ($data as $row) {
    //                 //向模板表中写入数据
                        $sheet->setCellValue('A'.$rowIndex, $row['id']);   //送入A1的内容
                        $sheet->setCellValue('B'.$rowIndex, $row['user_id']);   //送入A1的内容
                        $sheet->setCellValue('C'.$rowIndex, $row['mobile']);   //送入A1的内容
                        $sheet->setCellValue('D'.$rowIndex, $is_rot[$row['is_rot']]);   //送入A1的内容
                        $sheet->setCellValue('E'.$rowIndex, $row['upid']);   //送入A1的内容
                        $sheet->setCellValue('F'.$rowIndex, $row['nickname']);   //送入A1的内容
                        $sheet->setCellValue('G'.$rowIndex, $row['name']);   //送入A1的内容
                        $sheet->setCellValue('H'.$rowIndex, $row['times']);   //送入A1的内容
                        // $sheet->setCellValue('I'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                        $sheet->setCellValue('I'.$rowIndex, $row['loginip']);   //送入A1的内容
                        // $sheet->setCellValue('K'.$rowIndex, $row['projecttaskok']);   //送入A1的内容
                        // $sheet->setCellValue('L'.$rowIndex, $row['yiling']);   //送入A1的内容
                        // $sheet->setCellValue('M'.$rowIndex, $row['dailing']);   //送入A1的内容
                        $sheet->setCellValue('J'.$rowIndex, $status[$row['status']]);   //送入A1的内容
                        $sheet->setCellValue('K'.$rowIndex, $paytype[$row['paytype']]);   //送入A1的内容
                        $sheet->setCellValue('L'.$rowIndex, $row['qty']);   //送入A1的内容
                        $sheet->setCellValue('M'.$rowIndex, $row['price']);   //送入A1的内容
                        $sheet->setCellValue('N'.$rowIndex, $row['project_data_id']);   //送入A1的内容
                        $sheet->setCellValue('O'.$rowIndex, $row['payprice']);   //送入A1的内容
                        $sheet->setCellValue('P'.$rowIndex, date('Y-m-d H:i:s', $row['paytime']));   //送入A1的内容
                        $rowIndex++;
                    }
                
                    $page++;
                } while (count($data) == $pageSize);
                $file_url='/export/export_' . date('Ymd_His') . '.xlsx';
                // 保存到文件
                $filename = ROOT_PATH . 'public'.$file_url;
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                Db::name('exportlist')->where(['id'=>$exportlist['id']])->update(['file_url'=>$file_url]);
            }
            
            return;
    }
    
    public function profit_log($profit_user){
        $log = date('Y-m-d H:i:s') . ' --- ' . json_encode($profit_user) . PHP_EOL;
        $path = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'nxxfj';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . date('Y-m-d') . 'nxxfj.log', $log, FILE_APPEND);
    }

}
