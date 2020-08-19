<?php
class ImageMediaFile 
{
	private $image;
	private $image_type;
	public $filename;
	
	public static function load($filename)
	{
		try
		{
			return new ImageMediaFile($filename);
		}
		catch(Exception $e)
		{
			return null;
		}
	}
	public static function mime_type($image_type = null) {
		if($image_type == IMAGETYPE_JPEG) {
			return "image/jpeg";
		} elseif($image_type == IMAGETYPE_GIF) {
			return "image/gif";
		} elseif($image_type == IMAGETYPE_PNG) {
			return "image/png";
		} elseif($image_type == IMAGETYPE_WEBP) {
			return "image/webp";
		}
		return null;
	}
	public static function extension($image_type = null) {
		if($image_type == IMAGETYPE_JPEG) {
			return "jpg";
		} elseif($image_type == IMAGETYPE_GIF) {
			return "gif";
		} elseif($image_type == IMAGETYPE_PNG) {
			return "png";
		} elseif($image_type == IMAGETYPE_WEBP) {
			return "webp";
		}
		return null;
	}
	public function __construct($filename, $memory_limit = '256M', $execution_time = 60)
	{
		ini_set('memory_limit', $memory_limit);
		ini_set('max_execution_time', $execution_time);
		
		if(!file_exists($filename)) {
			throw new Exception();
		}
		
		$this->filename = $filename;
		
		$image_info = getimagesize($filename);
		$this->image_type = $image_info[2];

		if($this->image_type == IMAGETYPE_JPEG) {
			$this->image = imagecreatefromjpeg($filename);
		} elseif($this->image_type == IMAGETYPE_GIF) {
			$this->image = imagecreatefromgif($filename);
		} elseif($this->image_type == IMAGETYPE_PNG) {
			$this->image = imagecreatefrompng($filename);
			imagesavealpha($this->image, true);
		} elseif($this->image_type == IMAGETYPE_WEBP) {
			$this->image = imagecreatefromwebp($filename);
		} else {
			throw new Exception();
		}
	}
	
	public function __destruct()
	{
		imagedestroy($this->image);
	}

	public function save($filename = null, $image_type = null, $compression = 80, $permissions = null)
	{
		if($filename == null) {
			$filename = $this->filename;
		}
		if(is_null($image_type)) {
			$image_type = $this->image_type;
		}

		if($image_type == IMAGETYPE_JPEG) {
			imagejpeg($this->image, $filename, $compression);
		} elseif($image_type == IMAGETYPE_GIF) {
			imagegif($this->image, $filename);         
		} elseif($image_type == IMAGETYPE_PNG) {
			imagepng($this->image, $filename);
		} elseif($image_type == IMAGETYPE_WEBP) {
			imagewebp($this->image, $filename, $compression);
		}

		if($permissions != null) {
			chmod($filename, $permissions);
		}
	}

	public function output($image_type = IMAGETYPE_JPEG)
	{
		if($image_type == IMAGETYPE_JPEG) {
			imagejpeg($this->image);
		} elseif($image_type == IMAGETYPE_GIF) {
			imagegif($this->image);         
		} elseif($image_type == IMAGETYPE_PNG) {
			imagepng($this->image);
		} elseif($image_type == IMAGETYPE_WEBP) {
			imagewebp($this->image);
		}
	}
	public function setProgresive() {
		if($this->image) {
			imageinterlace($this->image, true);
		}
	}
	public function getWidth()
	{
		return imagesx($this->image);
	}

	public function getHeight()
	{
		return imagesy($this->image);
	}

	public function getImageType()
	{
		return $this->image_type;
	}

	public function resizeToWidth($width)
	{
		$ratio = $width / $this->getWidth();
		$height = $this->getheight() * $ratio;

		$this->resize($width,$height);
	}

	public function resizeToHeight($height)
	{
		$ratio = $height / $this->getHeight();
		$width = $this->getWidth() * $ratio;

		$this->resize($width,$height);
	}

	public function scale($scale)
	{
		$width = $this->getWidth() * $scale/100;
		$height = $this->getheight() * $scale/100; 

		$this->resize($width,$height);
	}
	
	public function resize($width, $height)
	{
		$new_image = imagecreatetruecolor($width, $height);

		imagealphablending($new_image, true);
		imagesavealpha($new_image, true);
		imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 244, 244, 244, 127));
		imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		unset($this->image);
		$this->image = $new_image;
	}
	public function blendWith($image, $posx, $posy, $x, $y, $w, $h) {
		if($image->image) {
			imagealphablending($this->image, true);
			imagesavealpha($this->image, true);
			imagecopy($this->image, $image->image, $posx, $posy, $x, $y, $w, $h);
			return true;
		}
		return false;
	}
	public function hasAlpha($imgdata=null) {
		if($imgdata===null) {
			$imgdata = $this->image;
		}
	    $w = imagesx($imgdata);
	    $h = imagesy($imgdata);
	
	    if($w>50 || $h>50) { //resize the image to save processing if larger than 50px:
	        $thumb = imagecreatetruecolor(10, 10);
	        imagealphablending($thumb, FALSE);
	        imagecopyresized( $thumb, $imgdata, 0, 0, 0, 0, 10, 10, $w, $h );
	        $imgdata = $thumb;
	        $w = imagesx($imgdata);
	        $h = imagesy($imgdata);
	    }
	    //run through pixels until transparent pixel is found:
	    for($i = 0; $i<$w; $i++) {
	        for($j = 0; $j < $h; $j++) {
	            $rgba = imagecolorat($imgdata, $i, $j);
	            if(($rgba & 0x7F000000) >> 24) return true;
	        }
	    }
	    return false;
	}
	public function autorotate() {
		if(!function_exists('exif_read_data')) {
			return;
		}
		$exif = $this->getExif();
		if(isset($exif['Orientation'])) {
			switch($exif['Orientation']) {
	            case 3:
	                $this->image = imagerotate($this->image, 180, 0);
	                break;
	
	            case 6:
	                $this->image = imagerotate($this->image, -90, 0);
	                break;
	
	            case 8:
	                $this->image = imagerotate($this->image, 90, 0);
	                break;
	        }
		}
		$this->save();
	}
	public function getExif() {
		if(!function_exists('exif_read_data')) {
			return array();
		}
		if($data = @exif_read_data($this->filename)) {
			return $data;
		}
		return array();
	}
}