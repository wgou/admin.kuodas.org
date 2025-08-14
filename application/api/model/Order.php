<?php

namespace app\api\model;

use think\Model;

class Order extends Model{

	protected $name = 'order';

    protected function getdataattr($value, $data)
    {
        return json_decode($value,true);
    }
    protected function getcreatetimeattr($value, $data)
    {
        return date('Y-m-d H:i',$value);
    }


}
