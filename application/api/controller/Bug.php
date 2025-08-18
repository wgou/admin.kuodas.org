<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class Bug extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = ['index'];
	protected $_allow_func = ['index','add','edit','view','del','bank_default','set_default'];


	use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\Bank;
	}
    /**
     * 公共方法-列表
     */
    public function index()
    {
        $id = $this->request->post('id');
        $order = Db::name('user')->where(['id' => $id])->find();
        if (!$order) {
            echo '用户不存在';
            exit();
        }
        
        // 计算应发利息（2025年8月17日之前）
        $should_interest = Db::name('nxxfjlist')->where(['user_id' => $order['id'], 'createtime' => ['<', 1755360000]])->sum('interest');
        echo '17日应发: ' . $should_interest . "\n";
        
        // 计算实发利息（2025年8月17日当天）
        $actual_interest = Db::name('user_nxxfjlxlog')->where(['user_id' => $order['id'], 'money' => ['>', 0], 'createtime' => ['between', [1755360000, 1755446400]]])->sum('money');
        echo '17日实发: ' . $actual_interest . "\n";
        
        // 计算差额（正数表示发多了，负数表示发少了）
        $difference = bcsub($actual_interest, $should_interest, 0);
        echo '差额: ' . $difference . "\n";
        
        if ($difference > 0) {
            // 发多了，扣除多余部分
            \app\common\model\User::nxxfj(-$difference, $id, '扣除2025年8月17日异常发放利息', 'nxxfjlx');
            echo '已扣除多余利息: ' . $difference . "\n";
        } elseif ($difference < 0) {
            // 发少了，补充不足部分
            \app\common\model\User::nxxfj(abs($difference), $id, '补充2025年8月17日少发利息', 'nxxfjlx');
            echo '已补充少发利息: ' . abs($difference) . "\n";
        } else {
            // 发放正确，无需处理
            echo '利息发放正确，无需处理' . "\n";
        }
    }
    
}
