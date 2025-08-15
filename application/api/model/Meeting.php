<?php

namespace app\api\model;

use think\Model;

class Meeting extends Model{

	protected $name = 'meeting';
	
	protected function getucontentattr($value, $data)
    {
        $pattern = '/<img\s+[^>]*src=("|\')(https?:\/\/)[^\/]+/';
        $newImageUrl = request()->domain();
        return preg_replace($pattern, '<img src=$1' . $newImageUrl, $value);
    }
    
    protected function getsighedruleattr($value, $data)
    {
        $pattern = '/<img\s+[^>]*src=("|\')(https?:\/\/)[^\/]+/';
        $newImageUrl = request()->domain();
        return preg_replace($pattern, '<img src=$1' . $newImageUrl, $value);
    }
	
}