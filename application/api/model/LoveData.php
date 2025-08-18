<?php

namespace app\api\model;

use think\Model;

class LoveData extends Model
{
    protected $name = 'love_data';

    protected function getuimageattr($value, $data)
    {
        return request()->domain().$value;
    }
}
