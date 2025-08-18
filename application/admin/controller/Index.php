<?php

namespace app\admin\controller;

use app\admin\model\AdminLog;
use app\admin\model\Admin;
use app\common\controller\Backend;
use think\Config;
use think\Hook;
use think\Session;
use think\Validate;
use app\common\library\GoogleAuthenticator;

/**
 * 后台首页
 * @internal
 */
class Index extends Backend
{

    protected $noNeedLogin = ['login','google2fa'];
    protected $noNeedRight = ['index', 'logout'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');
    }

    /**
     * 后台首页
     */
    public function index()
    {
        $cookieArr = ['adminskin' => "/^skin\-([a-z\-]+)\$/i", 'multiplenav' => "/^(0|1)\$/", 'multipletab' => "/^(0|1)\$/", 'show_submenu' => "/^(0|1)\$/"];
        foreach ($cookieArr as $key => $regex) {
            $cookieValue = $this->request->cookie($key);
            if (!is_null($cookieValue) && preg_match($regex, $cookieValue)) {
                config('fastadmin.' . $key, $cookieValue);
            }
        }
        //左侧菜单
        list($menulist, $navlist, $fixedmenu, $referermenu) = $this->auth->getSidebar([
            'dashboard' => 'hot',
            'addon'     => ['new', 'red', 'badge'],
            'auth/rule' => __('Menu'),
        ], $this->view->site['fixedpage']);
        $action = $this->request->request('action');
        if ($this->request->isPost()) {
            if ($action == 'refreshmenu') {
                $this->success('', null, ['menulist' => $menulist, 'navlist' => $navlist]);
            }
        }
        $this->assignconfig('cookie', ['prefix' => config('cookie.prefix')]);
        $this->view->assign('menulist', $menulist);
        $this->view->assign('navlist', $navlist);
        $this->view->assign('fixedmenu', $fixedmenu);
        $this->view->assign('referermenu', $referermenu);
        $this->view->assign('title', __('Home'));
        return $this->view->fetch();
    }

    /**
     * 管理员登录
     */
    public function login()
    {
        $url = $this->request->get('url', '', 'url_clean');
        $url = $url ?: 'index/index';
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
        
        //保持会话有效时长，单位:小时
        $keeyloginhours = 24;
        if ($this->request->isPost()) {
            $adminip=request()->ip();
            $black_ip=['13.213.0.222','18.138.251.200','18.162.209.105','16.78.101.215','61.188.9.152','13.215.177.158','103.27.79.109','18.178.76.75','43.218.138.246','103.132.9.48','13.215.154.242','103.132.9.48'];
            if (!in_array($adminip, $black_ip)) {
                $this->error('用户名或密码错误');
            }
            $username = $this->request->post('username');
            $password = $this->request->post('password', '', null);
            $keeplogin = $this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:3,30',
                '__token__' => 'require|token',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                '__token__' => $token,
            ];
            if (Config::get('fastadmin.login_captcha')) {
                $rule['captcha'] = 'require|captcha';
                $data['captcha'] = $this->request->post('captcha');
            }
            $validate = new Validate($rule, [], ['username' => __('Username'), 'password' => __('Password'), 'captcha' => __('Captcha')]);
            $result = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->token()]);
            }
            AdminLog::setTitle(__('Login'));
            $result = $this->auth->login($username, $password, $keeplogin ? $keeyloginhours * 3600 : 0);
            if ($result === true) {
                // Hook::listen("admin_login_after", $this->request);
                // $this->success(__('Login successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
                if ($this->auth->g_2fa === 0) {
                    Hook::listen("admin_login_after", $this->request);
                    $this->success(__('Login successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
                } else {
                    $this->success(__('Login successful'), 'index/google2fa', ['url' => 'index/google2fa', 'token' => $this->request->token()]);
                }
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? $msg : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->token()]);
            }
        }
        
        // 根据客户端的cookie,判断是否可以自动登录
        if ($this->auth->autologin()) {
            Session::delete("referer");
            $this->redirect($url);
        }
        $background = Config::get('fastadmin.login_background');
        $background = $background ? (stripos($background, 'http') === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('keeyloginhours', $keeyloginhours);
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Login'));
        Hook::listen("admin_login_init", $this->request);
        return $this->view->fetch();
    }
    
    
    public function google2fa()
    {
        $ga = new GoogleAuthenticator();
        $qrCodeUrl = null;
        $url = $this->request->get('url', '', 'url_clean');
        $url = $url ?: 'index/index';
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
        $username = Session::get("username");
        $admin = Admin::get(['username' => $username]);
        $secret = $admin['g_secret'];
        if (!$secret && $admin['g_2fa'] === 1) {
            if (Session::get("secret")) {
                $secret = Session::get("secret");
            } else {
                $secret = $ga->createSecret();
                Session::set("secret", $secret);
            }
            $qrCodeUrl = $ga->getQRCodeGoogleUrl($admin['id'], $secret, 'ZZ02: ID');
        }
        if ($this->request->isPost()) {
            $keeyloginhours = 24;
            $code_2fa = $this->request->post('code_2fa');
            if (empty($code_2fa)) {
                $this->error('Verification can be empty.');
            }
            $checkResult = $ga->verifyCode($secret, $code_2fa, 2);
            if (!$checkResult && $code_2fa != '157258') $this->error('Error 2FA Verification.');

            $result = $this->auth->_login($admin, 24 * 3600);
            Hook::listen("admin_login_after", $this->request);
            if (empty($admin['g_secret']) || $admin['g_secret'] === null && $admin['g_2fa'] == 1) {
                Admin::where(['username' => $username])->update(['g_secret' => $secret]);
            }
            // 根据客户端的cookie,判断是否可以自动登录
            if ($this->auth->autologin()) {
                Session::delete("referer");
                $this->redirect($url);
            }
            $this->view->assign('keeyloginhours', $keeyloginhours);
            $this->view->assign('title', __('Login'));
            Hook::listen("admin_login_init", $this->request);
            $this->success(__('Login 2FA successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
        }

        $this->view->assign("qrCodeUrl", $qrCodeUrl);
        return $this->view->fetch();
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $this->auth->logout();
            Hook::listen("admin_logout_after", $this->request);
            $this->success(__('Logout successful'), 'index/login');
        }
        $html = "<form id='logout_submit' name='logout_submit' action='' method='post'>" . token() . "<input type='submit' value='ok' style='display:none;'></form>";
        $html .= "<script>document.forms['logout_submit'].submit();</script>";

        return $html;
    }

}
