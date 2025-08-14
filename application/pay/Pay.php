<?php

namespace app\pay;

use think\Db;
use think\Config;


class Pay 
{

    function http_post_json($url, $data)
    {
        $data = json_encode($data,512);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data)
            )
        );
     
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
     
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return  $response;
    }

    function httpPosts($url, $paramStr){
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => 1,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => json_encode($paramStr,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
		  CURLOPT_HTTPHEADER => array(
			"cache-control: no-cache",
			"content-type: application/json"
		  ),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if ($err) {
		  return $err;
		}
		return $response;
	}
    function post($url, $data)
    {
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
     
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
     
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return  $response;
    }

}
