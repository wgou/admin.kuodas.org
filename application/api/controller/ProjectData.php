<?php

namespace app\api\controller;

use app\api\library\buiapi\Api;

class ProjectData extends Api{

    protected $model = null;

    protected $noNeedRight = '*';
    protected $noNeedLogin = '*';
    protected $_allow_func = ['index','add','edit','view','checkSubmitted'];
    protected $_search_field = ['project_type_id','status','id_card','phone','user_id'];

    use \app\api\library\buiapi\traits\Api;

    public function _initialize(){
        parent::_initialize();
        $this->model = new \app\api\model\ProjectData;
    }

    /**
     * 检查用户是否已提交过表单
     * @param int $user_id 用户ID
     * @param string $id_card 身份证号
     * @param string $phone 手机号
     */
    public function checkSubmitted()
    {
        $user_id = $this->request->param('user_id', 0, 'intval');
        $id_card = $this->request->param('id_card', '', 'trim');
        $phone = $this->request->param('phone', '', 'trim');

        if (!$user_id && !$id_card && !$phone) {
            $this->error('请提供用户ID、身份证号或手机号');
        }

        $where = [];
        
        // 优先使用用户ID查询
        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        // 其次使用身份证号查询
        else if ($id_card) {
            $where['id_card'] = $id_card;
        }
        // 最后使用手机号查询
        else if ($phone) {
            $where['phone'] = $phone;
        }

        $exists = $this->model->where($where)->count() > 0;

        $this->success('', [
            'submitted' => $exists,
            'message' => $exists ? '您已参与过圆梦计划，每位用户仅限参与一次' : '您可以参与圆梦计划'
        ]);
    }
}
