<?php

namespace app\api\model;

use think\Model;

class ProjectType extends Model{

	protected $name = 'project_type';


    protected function getuimageattr($value, $data)
    {
        return request()->domain().$value;
    }
}
