<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\exception\UploadException;
use app\common\library\Upload;
use app\common\model\Area;
use app\common\model\Version;
use fast\Random;
use think\captcha\Captcha;
use think\Config;
use think\Hook;

/**
 * 公共接口
 */
class Common extends Api
{
    protected $noNeedLogin = ['init', 'captcha', 'config','upload'];
    protected $noNeedRight = '*';

    public function _initialize()
    {

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Expose-Headers: __token__');//跨域让客户端获取到
        }
        //跨域检测
        // check_cors_request();

        if (!isset($_COOKIE['PHPSESSID'])) {
            Config::set('session.id', $this->request->server("HTTP_SID"));
        }
        parent::_initialize();
    }

    /**
     * 加载初始化
     *
     * @param string $version 版本号
     * @param string $lng 经度
     * @param string $lat 纬度
     */
    public function init()
    {
        if ($version = $this->request->request('version')) {
            $lng = $this->request->request('lng');
            $lat = $this->request->request('lat');

            //配置信息
            $upload = Config::get('upload');
            //如果非服务端中转模式需要修改为中转
            if ($upload['storage'] != 'local' && isset($upload['uploadmode']) && $upload['uploadmode'] != 'server') {
                //临时修改上传模式为服务端中转
                set_addon_config($upload['storage'], ["uploadmode" => "server"], false);

                $upload = \app\common\model\Config::upload();
                // 上传信息配置后
                Hook::listen("upload_config_init", $upload);

                $upload = Config::set('upload', array_merge(Config::get('upload'), $upload));
            }

            $upload['cdnurl'] = $upload['cdnurl'] ? $upload['cdnurl'] : cdnurl('', true);
            $upload['uploadurl'] = preg_match("/^((?:[a-z]+:)?\/\/)(.*)/i", $upload['uploadurl']) ? $upload['uploadurl'] : url($upload['storage'] == 'local' ? '/api/common/upload' : $upload['uploadurl'], '', false, true);

            $content = [
                'citydata' => Area::getCityFromLngLat($lng, $lat),
                'versiondata' => Version::check($version),
                'uploaddata' => $upload,
                'coverdata' => Config::get("cover"),
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 上传文件
     * @ApiMethod (POST)
     * @param File $file 文件流
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        //必须设定cdnurl为空,否则cdnurl函数计算错误
        Config::set('upload.cdnurl', '');
        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            if (!Config::get('upload.chunking')) {
                $this->error(__('Chunk file disabled'));
            }
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filename = $this->request->post("filename");
            $method = $this->request->method(true);
            if ($action == 'merge') {
                $attachment = null;
                //合并分片文件
                try {
                    $upload = new Upload();
                    $attachment = $upload->merge($chunkid, $chunkcount, $filename);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
            } elseif ($method == 'clean') {
                //删除冗余的分片文件
                try {
                    $upload = new Upload();
                    $upload->clean($chunkid);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            } else {
                //上传分片文件
                //默认普通上传文件
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
        } else {
            $attachment = null;
            //默认普通上传文件
            $file = $this->request->file('file');
            try {
                $upload = new Upload($file);
                $attachment = $upload->upload();
            } catch (UploadException $e) {
                $this->error($e->getMessage());
            }

            $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => $attachment->url]);
        }

    }

    /**
     * 验证码
     * @param $id
     * @return \think\Response
     */
    public function captcha($id = "")
    {
        \think\Config::set([
            'captcha' => array_merge(config('captcha'), [
                'fontSize' => 44,
                'imageH' => 150,
                'imageW' => 350,
            ])
        ]);
        $captcha = new Captcha((array)Config::get('captcha'));
        return $captcha->entry($id);
    }


    /**
     * 系统配置
     * @return void
     */
    public function config()
    {
        $ann_home = config('site.gg_poptext');
        $ann_home = $this->_addWidthToImgTags($ann_home);
        $ann_pro = config('site.gg_poptext2');
        $ann_pro = $this->_addWidthToImgTags($ann_pro);
        
        $data = [
            'gg_video'=>config('site.gg_video'),
            'gg_text'=>config('site.gg_text'),
            'ptzc'=>config('site.ptzc'),
            'yszc'=>config('site.yszc'),
            'qun'=>config('site.qun'),
            'kefu'=>config('site.kefu_url'),
            'tuan'=>config('site.tuan_url'),
            'qrcode_link_h5'=>config('site.qrcode_link_h5'),
            'app_download'=>config('site.add_download'),
            'chat_url'=>config('site.chat_url'),
            'old_web_url'=>config('site.old_web_url'),



            'is_gg'=>config('site.is_gg'),
            'gg_poptext'=>  $ann_home,
            'gg_poptext2'=> $ann_pro,
            'potato_link' =>'https://ptgw.pro/apps',
            'neixujuanfafang'=>config('site.neixujuanfafang'),
            'neixulixi'=>config('site.neixulixi')
//            'qita_share'=>config('site.qita_share'),
//            'qita_love'=>config('site.qita_love'),
        ];
        return $this->success('', $data);
    }
    
    private function _addWidthToImgTags($htmlString) {
        // Regular expression to find img tags
        $pattern = '/<img[^>]*>/';
        // Callback function to add width attribute
        $callback = function($matches) {
            $imgTag = $matches[0];
            // Check if width attribute already exists
            if (preg_match('/width\s*=\s*["\']?[^"\'>]+["\']?/i', $imgTag)) {
                // If width attribute exists, replace its value with 100%
                return preg_replace('/(width\s*=\s*["\']?)([^"\'>]+)(["\']?)/i', '$1100%$3', $imgTag);
            } else {
                // If width attribute doesn't exist, add it
                return preg_replace('/<img/i', '<img width="100%"', $imgTag);
            }
        };
        // Replace img tags in the HTML string
        $modifiedHtmlString = preg_replace_callback($pattern, $callback, $htmlString);
        return $modifiedHtmlString;
    }
}
