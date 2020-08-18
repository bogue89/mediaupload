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
		'autorotate' => true,
		'blendWith' => false,
		'optimize_original' => array(
			'max_width' => 2400,
			'compression' => 70,
			'jpg_if_posible' => 0,
		),		
		'webp_duplicate' => 0,
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
			
			if($image = new ImageMediaFile($file_info['dir'].$file_info['filename'])) {
				// Get exif meta data
				$file_info['exif'] = $image->getExif();
				if($this->options['autorotate']) {
					$image->autorotate();
				}
				// BlendImage
				if($this->options['blendWith'] && is_array($this->options['blendWith'])) {
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
						unset($watermark);
					}
				}
				// Optimize original
				if($this->options['optimize_original']) {
					$image->setProgresive();
					if(isset($this->options['optimize_original']['jpg_if_posible']) && $this->options['optimize_original']['jpg_if_posible']) {
						if($image->getImageType() == IMAGETYPE_PNG AND !$image->hasAlpha()) {
							$ext = $image->convert(IMAGETYPE_JPEG);
							$this->delete($file_info['dir'].$file_info['filename']);
							$file_info['type'] = "image/$ext";
							$image->filename = preg_replace("/\.\w+$/", ".{$ext}", $image->filename);
							$file_info['filename'] = basename($image->filename);
						}
					}
					if(isset($this->options['optimize_original']['max_width']) && $image->getWidth() > $this->options['optimize_original']['max_width']) {
						$image->resizeToWidth($this->options['optimize_original']['max_width']);
					}
					if(isset($this->options['optimize_original']['max_height']) && $image->getHeight() > $this->options['optimize_original']['max_height']) {
						$image->resizeToHeight($this->options['optimize_original']['max_height']);
					}
				}
				$file_info['width'] = $image->getWidth();
				$file_info['height'] = $image->getHeight();
				$image->save();
				if($this->options['webp_duplicate']) {
					if($ext = $image->convert(IMAGETYPE_WEBP)) {
						$image->filename = preg_replace("/\.\w+$/", ".{$ext}", $image->filename);
						$image->save();
						unset($image);
					}					
				}
				unset($image);
				// Resize image
				if(isset($this->options['resizes']) && is_array($this->options['resizes'])) {
					foreach($this->options['resizes'] as $prefix => $resize) {
						$image = new ImageMediaFile($file_info['dir'].$file_info['filename']);
						if(isset($resize['width']) && isset($resize['height'])) {
							$image->resize($resize['width'], $resize['height']);
						} else if(isset($resize['width'])) {
							$image->resizeToWidth($resize['width']);
						} else if(isset($resize['height'])) {
							$image->resizeToHeight($resize['height']);
						}
						$image->save($file_info['dir'].$prefix.$file_info['filename']);
						if($this->options['webp_duplicate']) {
							if($ext = $image->convert(IMAGETYPE_WEBP)) {
								$image->save(preg_replace("/\.\w+$/", ".{$ext}", $file_info['dir'].$prefix.$file_info['filename']));
								unset($image);
							}
						}
						unset($image);
					}
				}
			}
		}
	}
	public function deleteMediaFile($filename) {
		$dir = $this->options['dir'];
		$this->delete($dir.$filename);
		if($this->options['webp_duplicate']) {
			$this->delete(preg_replace("/\.\w+$/", ".webp", $dir.$filename));
		}
		$resizes = $this->options['resizes'];
		if(is_array($resizes)){			
			foreach($resizes as $resize => $size) {
				$this->delete($dir.$resize.$filename);
				if($this->options['webp_duplicate']) {
					$this->delete(preg_replace("/\.\w+$/", ".webp", $dir.$resize.$filename));
				}
			}
		}
	}
	public function delete($filename) {
		if(file_exists($filename) && !is_dir($filename)) {
			return unlink($filename);
		}
		return null;
	}
}