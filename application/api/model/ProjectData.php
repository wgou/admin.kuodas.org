<?php

namespace app\api\model;

use think\Model;

class ProjectData extends Model
{

    protected $name = 'project_data';

    protected $append = [
        'schedule'
    ];

    // protected function getuimageattr($value, $data)
    // {
    //     return request()->domain() . $value;
    // }
    // protected function getucontentattr($value, $data)
    // {
    //     return request()->domain() . $value;
    // }

    protected function getscheduleattr($value, $data)
    {
        if($data['id']==1){
            return 100;
        }
        if ($data['thismoney'] > 0) {
            $a = round(($data['thismoney'] / $data['topmoney'])*100, 2);
            if ($a >= 100) {
                $a = 100;
            }
            if ($a <=99) {
                $a = 99;
            }
            return $a;
        } else {
            return 99;
        }
    }

}
