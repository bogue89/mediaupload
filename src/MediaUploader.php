<?php
class MediaUploader 
{
  	public $options = array(
    	'media_types' => array('*'),
		'max_filesize' => 20971520,//20MB
		'dir' => 'files/',
		'slug_url' => true,
		'slug_replace' => '-',
		'resizes' => array(),
		'blendWith' => false,
		'optimize_original' => array(
			'max_width' => 2400,
			'compression' => 70,
			'jpg_if_posible' => 0
		)
	);
	
	private $file_keys = array('name','type','tmp_name', 'error', 'size', 'dir');
  
	public function __construct($options=array()) {
		require_once 'MediaFile.php';
		require_once 'ImageMediaFile.php';

		return $this->init($options);
	}
	public function init($options=array()) {
		$this->options = array_merge($this->options, $options);

		if(isset($this->options['blendWith']['filename'])) {
			$this->options['blendWith']['filename'] = $this->options['blendWith']['filename'];
		}
		return $this->options;
	}
	public function save($files){
		$file_infos = array();
    
		if(!is_array($files)) {
			$files = array($files => array());
		}

		foreach($files as $name => $file) {
			$errors = array();
			$file_info = array();

			foreach($this->file_keys as $file_key) {
				$file_info[$file_key]="";
			}
			$file_info['dir'] = $this->options['dir'];

			foreach($file_info as $file_key => $value) {
				if(isset($file[$file_key]) && $file[$file_key]!==null && $file[$file_key]!=="") {
					$file_info[$file_key] = $file[$file_key];
				}
				if($file_info[$file_key]==="") {
					$errors[] = "Missing \"$file_key\"";
				}
			}
			if(empty($errors)) {
				if($file_info['error']>0) {
					$errors[] = MediaFile::$file_errors[$file_info['error']];
				} else {
					if($error = $this->saveMediaFile($file_info)) {
						$errors[] = $error;
					}
	        	}
			}

			$file_info['errors'] = $errors;
			$files[$name]['errors'] = $errors;
			$file_infos[$name] = $file_info;
		}
		return $file_infos;
	}
	public function saveMediaFile(&$file_info) {
		$MediaFile = new MediaFile($file_info['tmp_name']);
    
		$dir = $file_info['dir'];
		$filename = $this->options['slug_url'] ? $this->slugUrl($file_info['name'], $this->options['slug_replace']):$file_info['name'];
    
		if(!in_array($file_info['type'], $this->options['media_types']) && !in_array("*", $this->options['media_types'])) {
			$MediaFile->error = 11;
		}
		if(filesize($file_info['tmp_name']) > $this->options['max_filesize']) {
			$MediaFile->error = 2;
		}
		$MediaFile->move($dir.$filename);
    
		if($MediaFile->error == 0) {
			$file_info['filename'] = $filename;
			$this->afterMediaSave($file_info);
		} else {
			$file_info['error'] = $MediaFile->error;
			return MediaFile::$file_errors[$MediaFile->error];
		}
		return null;
	}
	public function saveImageData($data, &$file_info) {
		$MediaFile = new MediaFile(null);
		$MediaFile->error = 0;
		if($MediaFile->saveImageData($data, $file_info['dir'].$file_info['filename'])) {
			$this->afterMediaSave($file_info);
			return true;
		}
		return false;
	}
	public function slugUrl($filename, $replace) {
		$pathinfo = pathinfo($filename);
	
		$string = $pathinfo['filename'];
		$ext = $pathinfo['extension'];
		
		$string = strtr(utf8_decode($string), utf8_decode('ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'),'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
		$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
		$string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
	
		return strtolower(preg_replace('/-+/', $replace, $string)).'.'.$ext;
	}
	public function changeExtension(&$filename, $extension) {
		$pathinfo = array_merge(['dirname'=>'.','basename'=>'','extension'=>'','filename'=>''],pathinfo($filename));
		$pathinfo['dirname'] = $pathinfo['dirname']=="." ? "":$pathinfo['dirname'].DIRECTORY_SEPARATOR;
		$string = $pathinfo['filename'];
		$ext = $pathinfo['extension'];
		$filename = $pathinfo['dirname'].$pathinfo['filename'].".".$extension;
		return $filename;
	}
	public function afterMediaSave(&$file_info) {
		if(preg_match('/image\/(png|jpg|jpeg)/i', $file_info['type'])) {
			// Optimize original
			if($this->options['optimize_original']) {
				$image = new ImageMediaFile($file_info['dir'].$file_info['filename']);
				if($image) {
					$r_width = 0;
					$r_height = 0;
					$width = $image->getWidth();
					$height = $image->getHeight();
					if(isset($this->options['optimize_original']['max_width'])) {
						$r_width = $width > $this->options['optimize_original']['max_width'] ? $this->options['optimize_original']['max_width']:$width;
					}
					if(isset($this->options['optimize_original']['max_height'])) {
						$r_height = $height > $this->options['optimize_original']['max_height'] ? $this->options['optimize_original']['max_height']:$height;
					}
					if($r_width > 0 && $r_height > 0) {
						$width = $r_width;
						$height = $r_height;
					} else if($r_width > 0) {
						$height = $height * $r_width/$width;
						$width = $r_width;
					} else if($r_height > 0) {
						$width = $width * $r_height/$height;
						$height = $r_height;
					}
					$image->resize($width, $height);
					$image->setProgresive();
					if(isset($this->options['optimize_original']['jpg_if_posible']) && $this->options['optimize_original']['jpg_if_posible']) {
						if($image->getImageType() == IMAGETYPE_PNG AND !$image->hasAlpha()) {
							$ext = $image->convert(IMAGETYPE_JPEG);
							$this->changeExtension($image->filename, $ext);
							$this->changeExtension($file_info['filename'], $ext);
							$file_info['type'] = "image/$ext";
							$this->delete($file_info['dir'].$file_info['filename']);
						}
					}
					$image->save($file_info['dir'].$file_info['filename']);
					unset($image);
				}
			}
			// BlendImage
			if($image = new ImageMediaFile($file_info['dir'].$file_info['filename'])) {
				$file_info['width'] = $image->getWidth();
				$file_info['height'] = $image->getHeight();
				if($this->options['blendWith']) {
					$blendOptions = array_merge(
						array(
							'filename' => '',
							'x' => 'top',
							'y' => 'left'
						),
						$this->options['blendWith']
					);
					if($watermark = ImageMediaFile::load($blendOptions['filename'])) {
						$x = $y = 0;
						$w = $watermark->getWidth();
						$h = $watermark->getHeight();
						if($w > $image->getWidth()) {
							$w = $image->getWidth();
							$h = $w * $watermark->getHeight() / $watermark->getWidth();
						}
						if($h > $image->getHeight()) {
							$h = $image->getHeight();
							$w = $h * $watermark->getWidth() / $watermark->getHeight();
						}
						if($blendOptions['x']=="right") {
							$x = $image->getWidth() - $w;
						} else if($blendOptions['x']=="center") {
							$x = $image->getWidth()/2 - $w/2;
						}
						if($blendOptions['y']=="bottom") {
							$y = $image->getHeight() - $h;
						}
						$watermark->resize($w, $h);
						$image->blendWith($watermark, $x, $y, 0, 0, $w, $h);
						$image->save();
					}
				}
			}
			// Resize image
			foreach($this->options['resizes'] as $prefix => $resize) {
				$image = new ImageMediaFile($file_info['dir'].$file_info['filename']);
				if($image) {
					$r_width = 0;
					$r_height = 0;
					$width = $image->getWidth();
					$height = $image->getHeight();
					if(isset($resize['width'])) {
						$r_width = $resize['width'];
					}
					if(isset($resize['height'])) {
						$r_height = $resize['height'];
					}
					if($r_width > 0 && $r_height > 0) {
						$width = $r_width;
						$height = $r_height;
					} else if($r_width > 0) {
						$height = $height * $r_width/$width;
						$width = $r_width;
					} else if($r_height > 0) {
						$width = $width * $r_height/$height;
						$height = $r_height;
					}
					$image->resize($width, $height);
					$image->save($file_info['dir'].$prefix.$file_info['filename']);
					unset($image);
				}
			}
		}
	}
	public function deleteMediaFile($filename) {
		$dir = $this->options['dir'];
		$resizes = $this->options['resizes'];
		$this->delete($dir.$filename);
		foreach($resizes as $resize => $size) {
			$this->delete($dir.$resize.$filename);
		}
	}
	public function delete($filename) {
		if(file_exists($filename) && !is_dir($filename)) {
			return unlink($filename);	
		}
		return null;
	}
}