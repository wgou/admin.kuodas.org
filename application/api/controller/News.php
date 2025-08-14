<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;
use think\Db;

class News extends Api{

    protected $model = null;

	protected $noNeedRight = '*';
	protected $noNeedLogin = '*';
	protected $_allow_func = ['index','add','edit','view'];
	protected $_search_field = ['type'];


	use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\News;
	}
	
	public function index()
	{
		$filter = $this->request->param('filter');
		$filter = html_entity_decode($filter);
		$filter = json_decode($filter, true);
		$p = $this->request->param('page') ?? 1;
		$p = $p == '' ? 1 : $p;
		$items_per_page = 20;
		$offset = ($p - 1) * $items_per_page;
		$w['status'] = 1;
		$w['type'] = $filter['type'];
		$total = Db::name('news')
			->where($w)
			->count('id');
		$info = Db::name('news')
			->where($w)
			->order('id desc')
            ->field('ucontent',true)
			->limit($offset, $items_per_page)
			->select();
		foreach ($info as $key => $value) {
			$pattern = '/<img\s+[^>]*src=("|\')(https?:\/\/)[^\/]+/';
// 			$newImageUrl = request()->domain();
// 			$info[$key]['uimage'] = $newImageUrl . $value['uimage'];
// 			$info[$key]['ucontent'] = preg_replace($pattern, '<img src=$1' . $newImageUrl, $value['ucontent']);
            $info[$key]['comment_count'] = rand(50000,80000);
            $info[$key]['like_count'] = rand(20000,30000);
			$info[$key]['createtime'] = date('Y-m-d H:i', $value['createtime']);
		}
		$list = [
			"total" => $total,
			"per_page" => $items_per_page,
			"current_page" => (int) $p,
			"last_page"  => ceil($total / $items_per_page),
			'rows' => $info
		];
		$this->success('数据列表', $list);
	}


    public function view($ids = null)
    {
        $info = $this->model::where('id', $ids)->find();
        $info['comment_count'] = rand(50000,80000);
        $info['like_count'] = rand(20000,30000);
        $this->success('数据列表', $info);
    }


}
