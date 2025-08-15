<?php

namespace app\admin\model;

use think\Model;

class TableMakeTables extends Model {

	// 表名
	protected $name = 'tablemake_tables';

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

	protected static function _initialize() {
		parent::_initialize();
	}

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $row->save(['weigh' => $row['id']]);
        });
    }

}
