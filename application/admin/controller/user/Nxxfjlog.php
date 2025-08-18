<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use think\Db;

/**
 * 内需消费金变动管理
 *
 * @icon fa fa-circle-o
 */
class Nxxfjlog extends Backend
{

    /**
     * Nxxfjlog模型对象
     * @var \app\admin\model\user\Nxxfjlog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Nxxfjlog;

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
    public function add()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a");
            $time=time();
            $updatetime=bcadd($time,172800);
            $year = date('Y'); // 当前年份
            $nextYearTimestamp = strtotime("$year-12-31 23:59:59");
            $nxxfj=$row['money'];
            if ($nxxfj==0) {
                $this->success("金额不能等于0");
            }
            \app\common\model\User::nxxfj($row['money'], $row['user_id'], $row['memo']);
            if ($nxxfj>0) {
                $annualization=Config('site.annualization');
                $dailyInterest=$this->getinterest($year,$nxxfj,$annualization[1]);
                Db::name('nxxfjlist')->insert([
                    'money'=>$nxxfj,
                    'user_id'=>$row['user_id'],
                    'yeartime'=>1,
                    'year_rate'=>$annualization[1],
                    'expirytime'=>$nextYearTimestamp,
                    'status'=>1,
                    'interest'=>$dailyInterest,
                    'createtime'=>$time,
                    'updatetime'=>$updatetime,
                ]);
            }
            $this->success("添加成功");
        }
        return parent::add();
    }
    private function getinterest($year,$principal,$annualRate){
        // 计算总天数
        $isLeap = date('L', strtotime("$year-01-01")); // 判断是否闰年
        $totalDays = $isLeap ? 366 : 365;
        
        // 计算总利息：本金 × 年利率
        $totalInterest = bcmul($principal, $annualRate,2);
        $totalDays=bcmul($totalDays,100);
        // 计算每天利息：总利息 ÷ 天数
        $dailyInterest = bcdiv($totalInterest, $totalDays, 2);
        return $dailyInterest;
    }
}
