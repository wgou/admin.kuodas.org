<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 会员经验变动管理
 *
 * @icon fa fa-circle-o
 */
class Jingyanlog extends Backend
{
    /**
     * Message模型对象
     * @var \app\admin\model\UserJingyanLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('UserJingyanLog');
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $row = $this->request->post("row/a");
            $user_id = isset($row['user_id']) ? $row['user_id'] : 0;
            $score = isset($row['score']) ? $row['score'] : 0;
            $memo = isset($row['memo']) ? $row['memo'] : '';
            if (!$user_id || !$score) {
                $this->error("经验值和会员ID不能为空");
            }
            $user = \app\admin\model\User::where('id',$user_id)->find();
            $logData = [
                'user_id' => $user_id,
                'score' => $score,
                'before' => $user['game_level_jy'],
                'after' => $user['game_level_jy'] + $score,
                'memo' => $memo,
                'createtime' => time()
            ];
            \app\common\model\UserJingyanLog::create($logData);
            \app\admin\model\User::where('id', $user_id)->setInc('game_level_jy', $score);
            $this->success("添加成功");
        }
        return parent::add();
    }
}
