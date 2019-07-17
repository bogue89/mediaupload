<?php
class MediaFile 
{
	public $filename = null;
	public $error = 0;
	public static $file_errors = array(
		0	=> "There is no error, the file uploaded with success",
		1	 => "The uploaded file exceeds the upload max filesize",
		2	 => "The uploaded file exceeds the upload max filesize allowed",
		3	 => "The uploaded file was only partially uploaded",
		4	 => "No file was uploaded",
		5	 => "File don't exist",
		6	 => "Missing a temporary folder",
		7	 => "Failed to write file to disk",
		8	 => "A PHP extension stopped the file upload",
		9	 => "Directory don't exist",
		10	 => "Directory is not writable",
		11	 => "File mime-type is not allowed"
	);
	public function __construct($filename) {
		$this->init($filename);
	}
	public function init($filename) {
		$this->filename = $filename;
		if(!file_exists($this->filename)) {
			$this->error = 5;
		}
	}
	public function move($filename, $create_directory=true, $change_permissions=false, $permissions=0755) {
		if($this->error > 0)
			return false;
		if(!file_exists(dirname($filename))) {
			if($create_directory) {
				if(!mkdir(dirname($filename), $permissions, true)) {
					$this->error = 9;
					return false;	 
				}
			} else {
				$this->error = 9;
				return false; 
			}
		}
		if(!is_writable(dirname($filename))) {
			if($change_permissions) {
				if(!chmod(dirname($filename), $permissions)) {
					$this->error = 10;
					return false;	
				}
			} else {
				$this->error = 10;
				return false;
			}	
		}
		if(rename($this->filename, $filename)) {
		chmod($filename, $permissions);
			return true;
		}
		$this->error = 7;
		return false;
	}
	public function saveImageData($data, $filename, $create_directory=true, $change_permissions=false, $permissions=0755) {	
		if($this->error > 0)
			return false;
		if(!file_exists(dirname($filename))) {
			if($create_directory) {
				if(!mkdir(dirname($filename), $permissions, true)) {
					$this->error = 9;
					return false;	 
				}
			} else {
				$this->error = 9;
				return false; 
			}
		}
		if(!is_writable(dirname($filename))) {
			if($change_permissions) {
				if(!chmod(dirname($filename), $permissions)) {
					$this->error = 10;
					return false;	
				}
			} else {
				$this->error = 10;
				return false;
			}	
		}
		$file = fopen($filename, 'w+');
		if(fwrite($file, $data)) {
			chmod($filename, $permissions);
			fclose($file);
			return $filename;
		}
		fclose($file);
		$this->error = 7;
		return false;
	}
}