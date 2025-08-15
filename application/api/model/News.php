<?php

namespace app\api\model;

use think\Model;

class News extends Model{

	protected $name = 'news';

    protected function getcreatetimeattr($value, $data)
    {
        return date('Y-m-d',$value);
    }
    // protected function getuimageattr($value, $data)
    // {
    //     return request()->domain().$value;
    // }
    
    // protected function getucontentattr($value, $data)
    // {
    //     $pattern = '/<img\s+[^>]*src=("|\')(https?:\/\/)[^\/]+/';
    //     $newImageUrl = request()->domain();
    //     return preg_replace($pattern, '<img src=$1' . $newImageUrl, $value);
    // }
    
    // protected function getucontentattr($value, $data)
    // {
    //     $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';
    //     $newImageUrl = request()->domain();

    //     $replacedHtml = preg_replace_callback($pattern, function ($matches) use ($newImageUrl) {
    //         $oldSrc = $matches[1];
    //         if (strpos($oldSrc, '/uploads') !== false) {
    //             $parsedUrl = parse_url($oldSrc);
    //             $newSrc = $newImageUrl . $parsedUrl['path'];
    //             if (isset($parsedUrl['query'])) {
    //                 $newSrc .= '?' . $parsedUrl['query'];
    //             }
    //             return str_replace($oldSrc, $newSrc, $matches[0]);
    //         }
    //         return $matches[0];
    //     }, $value);

    //     return $replacedHtml;
    // }
}
