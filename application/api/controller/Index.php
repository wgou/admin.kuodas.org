<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }
    
    public function message()
    {
        $user_id = $this->auth->id;
        $list = Db::name("app_message")
            ->field('id,user_id,title,uimage,createtime,json_active')
            ->where("status", 1)
            ->where(function ($query) use ($user_id) {
                $query->whereLike('user_id', '%'.$user_id.'%')
                    ->whereOr('user_id', 0);
            })
            ->order("createtime desc")
            ->paginate(10, false, ['query' => request()->param()]);

        foreach ($list as $key => $value) {
            $value['uimage'] = request()->domain() . $value['uimage'];

            $arr = (array)json_decode($value['json_active'], true);
            $value['active'] = in_array($user_id, $arr) ? 0 : 1;
            unset($value['json_active']);
            $list[$key] = $value;
           
        }
        $this->success("ok", $list);
    }

    public function message_detail()
    {
        $id = $this->request->param("id");
        $user_id = $this->auth->id;

        if (!$id) {
            $this->error(__("需要身分證件"));
        }
        $info = Db::name("app_message")->where("status", 1)->where("id", $id)->find();
        if (!$info) {
            $this->error(__("未找到"));
        }
        $arr = (array)json_decode($info['json_active'], true);
        if ($info['user_id'] == $user_id && !in_array($user_id, $arr)) {
            $arr[] = $user_id;
            $str = json_encode($arr);
            Db::name("app_message")->where("status", 1)->where("id", $id)->update(['json_active' => $str]);
        } else {
            if (!in_array($user_id, $arr)) {
                $arr[] = $user_id;
                $str = json_encode($arr);
                Db::name("app_message")->where("status", 1)->where("id", $id)->update(['json_active' => $str]);
            }
        }
        
        $info['uimage'] = request()->domain() . $info['uimage'];
        $pattern = '/<img\s+[^>]*src=("|\')(https?:\/\/)[^\/]+/';
        $newImageUrl = request()->domain();
        $info['content'] =  preg_replace($pattern, '<img src=$1' . $newImageUrl, $info['content']);
        
        unset($info['json_active']);
        $this->success("ok", $info);
    }


    public function get_active()
    {
        $user_id = $this->auth->id;
        $list = Db::name("app_message")
            ->where("status", 1)
            ->where(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->whereOr('user_id', 0);
            })
            ->select();

        $active = 0;
        foreach ($list as $k => $v) {
            $arr  = (array)json_decode($v['json_active'], true);
            if ($v['user_id'] == $user_id && !in_array($user_id, $arr)) {
                $active++;
            } else {
                if (!in_array($user_id, $arr)) {
                    $active++;
                }
            }
        }
        $this->success("ok", ['active' => $active]);
    }
}
