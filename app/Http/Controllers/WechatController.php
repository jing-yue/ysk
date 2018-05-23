<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\Material;

class WechatController extends Controller
{
    public function serve()
    {
        $wechat = app('wechat');

        $userApi = $wechat -> user;

        $options = config('wechat');
        $app = new Application($options);
        $broadcast = $app -> broadcast;
        $wechat->server->setMessageHandler(function($message) use($userApi, $app,$wechat, $broadcast){
           $data = $this -> evenType($message);
           if(!empty($data)) {
		$app->staff->message($data)
                    ->to($message -> FromUserName)
                    ->send();
		$str = "您的新年运势图好了\r\n如果您喜欢长按三秒可保存到手机，也可以发送你的朋友们看看娱乐一下，将截屏发给我们就是活动兑奖凭证哈\r\n祝您新年行大运，狗年旺旺旺。\r\n优私客CAKE，只为您的开心[爱心]\r\n<a href='http://56005858.m.weimob.com/vshop/56005858/index'>点击蓝字查看108元大礼包</a>[礼物][礼物][礼物]";
		$app->staff->message($str)
                    ->to($message -> FromUserName)
                    ->send();
		//return $data;
	   }
	   //$text = new Image();
           //return $text->setAttribute('media_id', 'OfAlJRUGJJlciPwkCML4ZgLk6m85Zm3JJCqK6k-3gMKrJTf6TaPwsSrTY79PnWKI');

            return "";
       });

        return $wechat->server->serve();
    }

    public function evenType($massage)
    {
        switch ($massage -> MsgType)
        {
            case 'event':
                if($massage -> Event == 'subsrcibe') return "亲爱的 你来了\r\n回复你的出生年月日\r\n如：19970701\r\n我来为你看狗年你的运势如何\r\n如果你的属相+生日的最后一位数字与我们公布的幸运号码一样，免费的定制蛋糕就送给你了哦";
                break;
            case 'text':
                $openId = $massage -> FromUserName;
                $keyword = $massage -> Content;
                $content = $this -> getImg($openId,$keyword);
	   	//Log::info($content['massage_type'].'-'.$content['media_id']);
                switch ($content['massage_type']) {
                    case 'text':
                        if(!empty($content['text'])) return $content['text'];
                        break;
                    case 'image':
			Log::info('media_id:'.$content['media_id']);
                        $text = new Image();
                        $text -> setAttribute('media_id', $content['media_id']);
			return $text;
			break;
		    default: return '';break;
                }
        }
    }

    public function arrRand(array $data,array $rand)
    {
        $arr = [];
        for($i=0;$i<count($rand);$i++){
            array_push($arr,$data[$rand[$i]]);
        }
        return $arr;
    }

    public function getImg($openId,$date)
    {
        if(!$date) return false;
	
	if(date('Ymd',strtotime($date)) == $date) {
	    $options = config('wechat');
	    $app = new Application($options);
	    $app->staff->message('收到，马上送到。请稍等......')
                    ->to($openId)
                    ->send();

            $year = date('Y',strtotime($date));

            $sx = $this -> get_animal($year);
            $ys = config('shengxiao')[$sx]['ys'];
            $ji = config('shengxiao')[$sx]['ji'];
            $yi = config('shengxiao')[$sx]['yi'];
            $lv = config('shengxiao')[$sx]['lv'];
            $hong = config('shengxiao')[$sx]['hong'];
            $hongs = array_rand($hong);
            //
            $yss = array_rand($ys,4);
            $lvs = array_rand($lv);
            $jis = array_rand($ji,2);
            $yis = array_rand($yi,2);
            $ysArr = $this -> arrRand($ys,$yss);
            //$lvArr = $this -> arrRand($lv,$lvs);
            $jiArr = $this -> arrRand($ji,$jis);
            $yiArr = $this -> arrRand($yi,$yis);

  	    $month = date('m',strtotime($date));
            $day = date('d',strtotime($date));
            $xz = $this -> get_constellation($month,$day);
            $xys = config('shengxiao')[$xz]['ys'];
            $xyss = array_rand($xys,3);
            $xysArr = $this -> arrRand($xys,$xyss);

	    $options = config('wechat');
            $app = new Application($options);
            $user =  $app -> user -> get($openId);
            $userName = $user->nickname;
            $tx = $user -> headimgurl;
        
            // ....生成图业务逻辑
            $img = $this -> makeImg($sx,$tx,$userName,$yiArr[0],$yiArr[1],$jiArr[0],$jiArr[1],$ysArr,$lv[$lvs],$hong[$hongs],$xysArr,$openId);

            $id = $this -> uploadImg($img);
            Log::info($id);
            if ($id) {
                return ['massage_type' => 'image', 'media_id' => $id];
            }

        }
    }

    public function uploadImg($imgUrl)
    {
        if(empty($imgUrl)) return ['status'=>false,'未发现图片素材'];

        $options = config('wechat');

        $app = new Application($options);

        // 临时素材
        $temporary = $app->material_temporary;

        $result = $temporary->uploadImage($imgUrl);

        if($result -> media_id) return $result->media_id;

        return false;

    }

    public function getUserImg($media_id)
    {
        $options = config('wechat');

        $app = new Application($options);
        // 永久素材
        $material = $app->user;
        $img = $material->get($media_id);
        if($img) return $img;

        return false;
    }

  //  public function makeImg($sx, $tx,$userName, $yi1, $yi2, $ji1, $ji2,$yunshi, $c\yu,$userId)
    public function makeImg($sx, $tx,$userName, $yi1, $yi2, $ji1, $ji2,$ciyu,$lv,$hong,$xz,$userId)
    {
        $yjSize = 15; // 宜忌字体大小
        $zhongSize = 30; //中间运势字体大小
        // 底板生肖图片
        $im = imagecreatefromjpeg (public_path('image/').$sx.'.jpg');
	
        // 用户头像
	$time = time();
        $tx = $this -> getImage($tx,public_path('img/'),$time.'.jpg',1);

        list($width, $height)=getimagesize($tx['save_path']);
        $new=imagecreatetruecolor(100, 100);
        $img=imagecreatefromjpeg($tx['save_path']);
        //copy部分图像并调整
        imagecopyresized($new, $img,0, 0,0, 0,100, 100, $width, $height);
        //图像输出新图片、另存为
        imagejpeg($new, $tx['save_path']);
        $tx = imagecreatefromjpeg ($tx['save_path']);

        $font1 = public_path('font').'/zhong.ttf';
        $font2 = public_path('font').'/yiji.ttf';

        //微信头像
        imagecopymerge($im, $tx,260 , 295, 0, 0, 100, 100, 100);

	$num = 55;
	$nameSize = 10;
	if(mb_strlen($userName)==2) {
	        $num = 30;
        }else if(mb_strlen($userName)==3){
	        $num = 23;
        }else if(mb_strlen($userName)==4){
	        $num = 19;
        }else if(mb_strlen($userName)==5){
	        $num = 16;
        }else if(mb_strlen($userName)==6){
	        $num = 15;
        }else if(mb_strlen($userName)==7){
	        $num = 13.8;
        }else if(mb_strlen($userName)>=8){
            $num = 12.5;
            $nameSize = 9;
        }
        //微信名
        $userx = 260 + ((100 - mb_strlen($userName)*$num));
	imagefttext($im, $nameSize, 0, $userx, 410, imagecolorallocate($im, 0,0,0), $font1, $userName);
	// 宜
        imagefttext($im, $yjSize, 0, 95, 223, imagecolorallocate($im, 244,24,24), $font1, $yi1);
        imagefttext($im, $yjSize, 0, 95, 255, imagecolorallocate($im, 244,24,24), $font1, $yi2);
        // 忌
        imagefttext($im, $yjSize, 0, 95, 315, imagecolorallocate($im, 0,0,0), $font1, $ji1);
        imagefttext($im, $yjSize, 0, 95, 345, imagecolorallocate($im, 0,0,0), $font1, $ji2);

	$x = (600 - mb_strlen($ciyu[0]) * 30-50) / 2; // 计算中间字的x坐标

        //  中间大字
        imagefttext($im,$zhongSize , 0, $x, 480, imagecolorallocate($im, 244,24,24), $font1, $ciyu[0]);

        //性格中间红字
        $image = imagecreatetruecolor(mb_strlen($hong)*40, 50); //创建图像
        $red = imagecolorallocate ( $image ,  240,70,80 ); // 设置红色
        imagefill ( $image ,  0 ,   0 ,  $red ); // 填充颜色
        // 写入文字
        imagefttext($image, 25, 0,14,35, imagecolorallocate($image, 255, 251, 240),$font1,$hong);
        // 合并图像
        imagecopymerge($im, $image,190 , 630, 0, 0, mb_strlen($hong)*40, 50, 100);
	if(mb_strlen($lv)<=6) {
            $ysize = 20;
        }else{
            $ysize = 16;
        }
	imagefttext($im, 14, 0,140,530, imagecolorallocate($im, 0,0,0),$font1,$ciyu[1]);
        imagefttext($im, 19, 0,240,585, imagecolorallocate($im, 0,0,0),$font1,$ciyu[2]);
        imagefttext($im, 26, 0,100,730, imagecolorallocate($im, 0,0,0),$font1,$xz[2]);
        imagefttext($im, $ysize, 0,60,615, imagecolorallocate($im, 0,0,0),$font1,$lv);
        imagefttext($im, 12, 0,380,535, imagecolorallocate($im, 0,0,0),$font1,$xz[0]);
        imagefttext($im, 14, 0,360,640, imagecolorallocate($im, 0,0,0),$font1,$xz[1]);
	
        header('Content-type:image/png');
        $fileName = public_path('img/').$userId.'.jpeg';
        imagejpeg($im,$fileName,100);
        imagedestroy($im);
        return $fileName;
    }

    /**
     * 根据生日中的月份和日期来计算所属星座
     *
     * @param int $birth_month
     * @param int $birth_date
     * @return string
     */

    function get_constellation($birth_month,$birth_date)
    {

        //判断的时候，为避免出现1和true的疑惑，或是判断语句始终为真的问题，这里统一处理成字符串形式

        $birth_month = strval($birth_month);

        $constellation_name = array(
            'shuiping','shuangyu','baiyang','jinniu','shuangzi','juxie',

            'shizi','chunv','tianping','tianxie','sheshou','moxie'
        );

        if ($birth_date <= 22) {
            if ('01' !== $birth_month) {
                $constellation = $constellation_name[$birth_month-2];
            } else {
                $constellation = $constellation_name[11];
            }

        } else {
            $constellation = $constellation_name[$birth_month-1];
        }

        return $constellation;
    }

    /**

     * 根据生日中的年份来计算所属生肖
     * @param int $birth_year
     * @return string
     */

    function get_animal($birth_year)

    {
        //1900年是子鼠年
        $animal = array(
            'shu','niu','hu','tu','long','she',
	    
            'ma','yang','hou','ji','gou','zhu'
        );

        $my_animal = ($birth_year-1900)%12;
        return $animal[$my_animal];
    }
	
    /* 
*功能：php完美实现下载远程图片保存到本地 
*参数：文件url,保存文件目录,保存文件名称，使用的下载方式 
*当保存文件名称为空时则使用远程文件原来的名称 
*/ 
function getImage($url,$save_dir='',$filename='',$type=0){ 
    if(trim($url)==''){ 
        return array('file_name'=>'','save_path'=>'','error'=>1); 
    } 
    if(trim($save_dir)==''){ 
        $save_dir='./'; 
    } 
    if(trim($filename)==''){//保存文件名 
        $ext=strrchr($url,'.'); 
        if($ext!='.gif'&&$ext!='.jpg'){ 
            return array('file_name'=>'','save_path'=>'','error'=>3); 
        } 
        $filename=time().$ext; 
    } 
    if(0!==strrpos($save_dir,'/')){ 
        $save_dir.='/'; 
    } 

    //创建保存目录 
    if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){ 
        return array('file_name'=>'','save_path'=>'','error'=>5); 
    } 

    //获取远程文件所采用的方法  
    if($type){ 
        $ch=curl_init(); 
        $timeout=5; 
        curl_setopt($ch,CURLOPT_URL,$url); 
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); 
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout); 
        $img=curl_exec($ch); 
        curl_close($ch); 
    }else{ 
        ob_start();  
        readfile($url); 
        $img=ob_get_contents();  
        ob_end_clean();  
    } 
    //$size=strlen($img); 
    //文件大小  
    $fp2=@fopen($save_dir.$filename,'a'); 
    fwrite($fp2,$img); 
    fclose($fp2); 
    unset($img,$url); 

    return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
    } 

}

