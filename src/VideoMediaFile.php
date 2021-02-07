<?php
class VideoMediaFile 
{
	private $video_info;
	private $video_type;
	private $tmp;
	public $filename;
	
	public static $mime_types = [
		'flv' 	=> 'video/x-flv',
		'mp4' 	=> 'video/mp4',
		'm3u8' 	=> 'application/x-mpegURL',
		'ts' 	=> 'video/MP2T',
		'3gp' 	=> 'video/3gpp',
		'mov' 	=> 'video/quicktime',
		'avi' 	=> 'video/x-msvideo',
		'wmv' 	=> 'video/x-ms-wmv',
	];
	
	public static function load($filename)
	{
		try
		{
			return new VideoMediaFile($filename);
		}
		catch(Exception $e)
		{
			return null;
		}
	}
	public static function mime_type($video_type = null) {
		if(isset(self::$mime_types[$video_type])) {
			return self::$mime_types[$video_type];
		}
		return null;
	}
	public static function extension($video_type = null) {
		if(isset(self::$mime_types[$video_type])) {
			return $video_type;
		}
		return null;
	}
	public static function ffmpeg($cmd) {
		$output = null;
		$cmd = preg_replace("/(^ffmpeg ?)|2>&1$/", "", $cmd);
		$dir = dirname(__DIR__);
		@exec($dir."/lib/ffmpeg $cmd 2>&1", $output);
		return $output;
	}
	public static function ffprobe($cmd) {
		$output = null;
		$cmd = preg_replace("/(^ffprobe ?)|2>&1$/", "", $cmd);
		$dir = dirname(__DIR__);	
		@exec($dir."/lib/ffprobe $cmd 2>&1", $output);
		return $output;
	}
	public function __construct($filename, $memory_limit = '512M', $execution_time = 90)
	{
		ini_set('memory_limit', $memory_limit);
		ini_set('max_execution_time', $execution_time);
		
		if(!file_exists($filename)) {
			throw new Exception();
		}
		if($this->video_info = $this->getVideoInfo($filename)) {
			if($this->video_info['codec_type'] !== 'video') {
				
				throw new Exception();
			}
			$this->filename = $filename;
			$this->tmp = $this->addPrefix('tmp.', $filename);	
		} else {
			throw new Exception();
		}
	}
	public function __destruct()
	{
		if($this->tmp && file_exists($this->tmp)) {
			unlink($this->tmp);
		}
	}
	private function addPrefix($pref, $filename) {
		return dirname($filename).'/'.$pref.basename($filename);
	}
	public function getVideoInfo($filename) {
		$video_info = [];
		$cmd = "-v error -select_streams v:0 -show_entries stream_tags=rotate:format=size,duration:stream=codec_name,codec_type,bit_rate,width,height -of default=noprint_wrappers=1 {$filename}";
		foreach(self::ffprobe($cmd) as $output) {
			if(strpos($output, '=') > 0) {
				list($k, $v) = explode('=', $output);
				$video_info[$k] = $v;
			}
		}
		return $video_info;
	}
	public function save($filename = null, $video_type = null, $permissions = null)
	{
		if($filename == null) {
			$filename = $this->filename;
		}
		if(is_null($video_type)) {
			$video_type = $this->video_type;
		}

		if(file_exists($this->tmp)) {
			rename($this->tmp, $filename);
		}
		if($permissions != null) {
			chmod($filename, $permissions);
		}
	}
	public function getWidth()
	{
		return (int) $this->video_info['width'];
	}

	public function getHeight()
	{
		return (int) $this->video_info['height'];
	}
	public function getDuration() {
		return (float) $this->video_info['duration'];
	}
	public function resizeToWidth($width)
	{
		$ratio = $width / $this->getWidth();
		$height = $this->getheight() * $ratio;

		$this->resize($width, $height);
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
		$width = (int)$width;
		$height = (int)$height;
		if($width%2 !== 0) {
			$width -= 1;
		}
		if($height%2 !== 0) {
			$height -= 1;
		}
		$in = file_exists($this->tmp) ? $this->tmp:$this->filename;
		$out = $this->addPrefix("resize.", $in);
		$cmd = "ffmpeg -i {$in} -s {$width}x{$height} -c:a copy {$out}";
		self::ffmpeg($cmd);
		if(file_exists($out)) {
			rename($out, $this->tmp);
		}
		$this->video_info = $this->getVideoInfo($this->tmp);
	}
	public function blendWith($image, $x, $y, $w, $h, $time=0, $duration=0) {
		if($duration<=0 || $duration>$this->video_info['duration']) {
			$duration = $this->video_info['duration'];
		}		
		$in = file_exists($this->tmp) ? $this->tmp:$this->filename;
		$out = $this->addPrefix("resize.", $in);
		$cmd = "ffmpeg -i {$in} -i {$image->filename} -filter_complex \"[0:v][1:v] overlay={$x}:{$y}:enable='between(t,{$time},{$duration})'\" -pix_fmt yuv420p -c:a copy {$out}";
		self::ffmpeg($cmd);
		if(file_exists($out)) {
			rename($out, $this->tmp);
		}
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