<?php
/**
 * File Image.php
 *
 * PHP version 5.3+
 *
 * @author    Philippe Gaultier <pgaultier@sweelix.net>
 * @copyright 2010-2014 Sweelix
 * @license   http://www.sweelix.net/license license
 * @version   1.0.0
 * @link      http://www.sweelix.net
 * @category  image
 * @package   sweelix.image
 */

namespace sweelix\image;

/**
 * Class Image
 *
 * This class is a simple wrapper to resample and
 * cache images
 * <code>
 * <?php
 *   // sample 1, resize image with size
 *   $var = 'source_image.jpg';
 *   $cacheImage = new Image($var);
 * ?>
 * <img src="<?php echo $cacheImage->resize(120, 120)->getUrl() ?>" />;
 *
 * <?php
 *   // sample 2, resize image with width
 *   $var = 'source_image.jpg';
 *   $cacheImage = new Image($var);
 * ?>
 * <img src="<?php echo $cacheImage->resizeWidth(120)->getUrl() ?>" />;
 *
 * <?php
 *   // sample 3, resize image with height
 *   $var = 'source_image.jpg';
 *   $cacheImage = new Image($var);
 * ?>
 * <img src="<?php echo $cacheImage->resizeHeight(120)->getUrl() ?>" />;
 *
 * <?php
 *   // sample 4, resize image with size and apply watermark
 *   $var = 'source_image.jpg';
 *   $wm = 'watermark.jpg';
 *   $cacheImage = new Image($var);
 *   $cacheImage->setMask($wm);
 * ?>
 * <img src="<?php echo $cacheImage->resize(120, 120)->getUrl() ?>" />;
 *
 * As class support chaining and automatic stringification,
 * we can have simplier calls
 * <?php
 *   // sample 5, resize image with size and apply watermark
 *   $var = 'source_image.jpg';
 *   $wm = 'watermark.jpg';
 *   $cacheImage = new Image($var);
 * ?>
 * <img src="<?php echo $cacheImage->setMask($wm)->resize(120, 120) ?>" />;
 *
 * Or better, everything in one line
 * <img src="<?php echo Image::create('source_image.jpg')->setMask('watermark.jpg')->resize(120, 120) ?>" />;
 *
 * </code>
 *
 * @author    Philippe Gaultier <pgaultier@sweelix.net>
 * @copyright 2010-2014 Sweelix
 * @license   http://www.sweelix.net/license license
 * @version   1.0.0
 * @link      http://www.sweelix.net
 * @category  image
 * @package   sweelix.image
 * @since     1.0.0
 *
 * @property integer $quality target quality
 * @property boolean $ratio   target ratio
 */
class Image {
	/**
	 * Image is always recomputed
	 */
	const MODE_DEBUG=0;
	/**
	 * Image timestamp is checked before recomputing it
	 */
	const MODE_NORMAL=1;
	/**
	 * Image existence only is checked
	 */
	const MODE_PERFORMANCE=2;

	/**
	 * @var string urlSeparator is used in getUrl to fetch correct information
	 */
	public static $urlSeparator='/';
	/**
	 * @var string errorImage is used to define the url of default image
	 */
	public static $errorImage='error.jpg';
	/**
	 * @var string cache path
	 */
	public static $cachePath='cache';
	/**
	 * @var string cache url
	 */
	public static $cacheUrl='cache';
	/**
	 * Caching mode can be :
	 *  - debug : resample image each time,
	 *  - normal : resample image if original filetime > cache filetime
	 *  - performance (default) : resample image if cached file does not exists
	 *
	 * @var string mode
	 */
	public $cachingMode;
	/**
	 * @var string mask image if needed
	 */
	private $_maskFileName=null;
	/**
	 * @var integer mask image transparency
	 */
	private $_maskTransparency=100;
	/**
	 * @var integer targeted width
	 */
	protected $_targetWidth=null;
	/**
	 * @var integer targeted height
	 */
	protected $_targetHeight=null;
	/**
	 * @var integer source image type
	 */
	private $_imageType;
	/**
	 * @var integer source image width
	 */
	private $_originalWidth;
	/**
	 * @var integer source image height
	 */
	private $_originalHeight;
	/**
	 * @var integer computed image width
	 */
	private $_finalWidth;
	/**
	 * @var integer computed image height
	 */
	private $_finalHeight;
	/**
	 * @var string target file name
	 */
	private $_targetName=null;
	/**
	 * @var boolean is image already in cache
	 */
	private $_isCached=null;
	/**
	 * @var integer quality, default is 90%
	 */
	private $_quality=90;
	/**
	 * @var boolean ratio, default is true (keep aspect ratio)
	 */
	private $_ratio=true;
	/**
	 * @var boolean fit, default is false (do not crop image)
	 */
	private $_fit=false;
	/**
	 * @var integer x offset (used when image should fit)
	 */
	private $_fitOffsetX=0;
	/**
	 * @var integer y offset (used when image should fit)
	 */
	private $_fitOffsetY=0;
	/**
	 * @var integer x offset (used to move image around)
	 */
	private $_offsetX=0;
	/**
	 * @var integer y offset (used to move image around)
	 */
	private $_offsetY=0;

	/**
	 * @var boolean check if image has been precalculated
	 */
	protected $_resized = false;
	/**
	 * @var string source file name
	 */
	private $_fileImage;
	/**
	 * @var string file name when we are using php://memory
	 */
	private $_fileImageName;

	/**
	 * Constructor, create an image object. This object will
	 * allow basic image manipulation
	 *
	 * @param string  $fileImage  image name with path
	 * @param integer $quality    quality, default is @see Image::_quality
	 * @param integer $ratio      ratio, default is @see Image::_ratio
	 * @param string  $base64Data image data base64 encoded
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function __construct($fileImage, $quality=null, $ratio=null, $base64Data = null) {
		$this->setQuality($quality);
		$this->setRatio($ratio);
		$this->_fileImage = static::$errorImage;
		if((strncmp('tmp://', $fileImage, 6)!== 0) && (file_exists($fileImage)== true) && (is_file($fileImage) == true)) {
			$this->_fileImage = $fileImage;
		} elseif($base64Data !== null) {
			$this->_fileImage = sys_get_temp_dir().DIRECTORY_SEPARATOR.$fileImage;
			file_put_contents($this->_fileImage, base64_decode($base64Data));
		}
	}

	/**
	 * Stringify the object, this method only wrap
	 * method @see Image::getUrl()
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function __toString() {
		try{
			return $this->getUrl();
		} catch(\Exception $e) {
			return static::$errorImage;
		}
	}

	/**
	 * Create an instance of Image with correct parameters
	 * calls original constructor @see Image::__construct()
	 *
	 * @param string  $fileImage image name with path
	 * @param integer $quality   quality, default is @see Image::_quality
	 * @param integer $ratio     ratio, default is @see Image::_ratio
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public static function create($fileImage, $quality=null, $ratio=null) {
		return new static($fileImage, $quality, $ratio);
	}

	/**
	 * Compute cached name. need as less action as possible on
	 * file to avoid unneeded overhead
	 *
	 * @param boolean $fullPath if true, return full path to image
	 *
	 * @return string
	 * @since  1.0.0
	 */
	private function getCachedName($fullPath=false) {
		if($this->_targetName === null) {
			$fileData = pathinfo($this->_fileImage);
			$extend = '';
			if($this->_ratio === false) {
				$extend = '-stretched';
			}
			if($this->_fit === true) {
				$extend = '-scale';
			}
			if($this->_maskFileName !== null) {
				$maskData = pathinfo($this->_maskFileName);
				$extend .= '-'.$maskData['filename'].'-'.$this->_maskTransparency;
			}
			$pathCrc = crc32($fileData['dirname']);
			$this->_targetName = sprintf('%x-%s-%dx%d-%d%s.%s', $pathCrc, $fileData['filename'], $this->_targetWidth, $this->_targetHeight, $this->_quality, $extend, $fileData['extension']);
		}
		if($fullPath === false) {
			return $this->_targetName;
		} else {
			return static::$cachePath.DIRECTORY_SEPARATOR.$this->_targetName;
		}
	}

	/**
	 * Check if the file has already been cached
	 *
	 * @return string
	 * @since  1.0.0
	 */
	private function getIsCached() {
		switch($this->cachingMode) {
			case static::MODE_DEBUG :
				$this->_isCached = false;
				break;
			case static::MODE_NORMAL :
				if(($this->_isCached === null) && (file_exists($this->getCachedName(true)) === true)) {
					$cacheTime = filemtime($this->getCachedName(true));
					$originalTime = filemtime($this->_fileImage);
					if($originalTime < $cacheTime) {
						$this->_isCached = true;
					} else {
						$this->_isCached = false;
					}
				} else {
					$this->_isCached = false;
				}
				break;
			case static::MODE_PERFORMANCE :
			default :
				if($this->_isCached === null) {
					$this->_isCached = file_exists($this->getCachedName(true));
				}
				break;
		}
		return $this->_isCached;
	}

	/**
	 * Quality setter.
	 *
	 * @param integer $value quality value should be between 0 and 100
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setQuality($value) {
		if(($value !== null) && ($value>=0) && ($value <= 100)) {
			$this->_quality = $value;
		}
		$this->_resized = false;
		return $this;
	}

	/**
	 * Ratio setter.
	 *
	 * @param boolean $value ratio value should be true or false
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setRatio($value) {
		if(($value === false) || ($value === true)) {
			$this->_ratio = $value;
		}
		$this->_resized = false;
		return $this;
	}

	/**
	 * Fit setter.
	 *
	 * @param boolean $value fit value should be true or false
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setFit($value) {
		if(($value === false) || ($value === true)) {
			$this->_fit = $value;
		}
		$this->_resized = false;
		return $this;
	}

	/**
	 * Define offset X of the image
	 *
	 * @param integer $value
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setOffsetX($value) {
		$this->_offsetX = intval($value);
		$this->_resized = false;
		return $this;
	}

	/**
	 * Define offset Y of the image
	 *
	 * @param integer $value
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setOffsetY($value) {
		$this->_offsetY = intval($value);
		$this->_resized = false;
		return $this;
	}

	/**
	 * Define the mask we will use during
	 * image resizing. Beware, transparency parameter is not used
	 * when merging png masks (transparency is already in the mask).
	 *
	 * @param string  $filename     filename of the mask (with path)
	 * @param integer $transparency transparency to apply during merge
	 * @param string  $position     position of the watermark - unused yet
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function setMask($filename, $transparency=null, $position=null) {
		if(file_exists($filename) === true) {
			$this->_maskFileName = $filename;
		}
		if(($transparency>0) && ($transparency<=100)) {
			$this->_maskTransparency = $transparency;
		}
		$this->_resized = false;
		return $this;
	}

	/**
	 * Get url of resized image. If image
	 * is not cached, image will be resized and cached
	 *
	 * @param integer $width  target width
	 * @param integer $height target height
	 *
	 * @return Image
	 * @since  1.0.0
	 */
	public function resize($width, $height) {
		//XXX:check if we have to reset it
		$this->_targetName = null;
		$this->_isCached = null;
		if($width===null) {
			$this->_targetWidth = 0;
		} else {
			$this->_targetWidth = $width;
		}
		if($height===null) {
			$this->_targetHeight = 0;
		} else {
			$this->_targetHeight = $height;
		}
		// return $this->getCachedName();
		$this->_resized = true;
		return $this;
	}

	/**
	 * get relative url to access the image.
	 *
	 * @param boolean $fullpath if true return fullpath else only the filename
	 * @param integer $imageType image type ex:IMAGETYPE_PNG
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function getUrl($fullpath = true, $imageType = null) {
		if($this->_resized === false) {
			$this->resize($this->_targetWidth, $this->_targetHeight);
		}
		if($this->getIsCached() === false) {
			//be sure to resample once everything is done and not before
			$this->resample(false, $imageType);
		}
		$cachedName = $this->getCachedName($fullpath);
		$cachedName = str_replace(self::$cachePath, self::$cacheUrl, $cachedName);
		return str_replace(DIRECTORY_SEPARATOR, static::$urlSeparator, $cachedName);
	}

	/**
	 * get path to access the image.
	 *
	 * @param integer $imageType image type ex:IMAGETYPE_PNG
	 *
	 * @return string
	 * @since  XXX
	 */
	public function getPath($imageType = null) {
	    if($this->_resized === false) {
	        $this->resize($this->_targetWidth, $this->_targetHeight);
	    }
	    if($this->getIsCached() === false) {
	        //be sure to resample once everything is done and not before
	        $this->resample(false, $imageType);
	    }
	    return $this->getCachedName(true);
	}

	/**
	 * Render the image and output it.
	 * if $return is true, the method return the binary data else
	 * the method return the size of the data.
	 *
	 * @param boolean $return if true return the data else the size of the data and data to stdout
	 * @param integer $imageType image type ex:IMAGETYPE_PNG
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 * @since  1.0.0
	 */
	public function render($return=false, $imageType = null) {
		$data = false;
		if($this->_resized === false) {
			$this->resize($this->_targetWidth, $this->_targetHeight);
		}
		if($this->getIsCached() === false) {
			//be sure to resample once everything is done and not before
			$this->resample(false, $imageType);
		}
		if(($f = fopen($this->getCachedName(true), 'rb')) !== false) {
			if($return === false) {
				$data = fpassthru($f);
			} else {
				$data = fread($f, filesize($this->getCachedName(true)));
			}
			fclose($f);
		} else {
			throw new \Exception('File '.$this->getCachedName(true).' cannot be opened');
		}
		return $data;
	}

	/**
	 * Retrieve content type of current image.
	 *
	 * @throws \Exception
	 * @return string
	 * @since  1.0.0
	 */
	public function getContentType() {
		if($this->_imageType === null) {
			list($this->_originalWidth, $this->_originalHeight, $this->_imageType) = getimagesize($this->_fileImage);
		}
		$contentType = 'application/octet-stream';
		switch($this->_imageType) {
			case IMAGETYPE_PNG:
				$contentType = 'image/png';
				break;
			case IMAGETYPE_GIF:
				$contentType = 'image/gif';
				break;
			case IMAGETYPE_JPEG:
				$contentType = 'image/jpeg';
				break;
			default:
				throw new \Exception('imagetype unknown');
				break;
		}
		return $contentType;
	}

	/**
	 * Render the image directly withou using local files.
	 * Usefull for previewing images
	 *
	 * @param integer $imageType image type ex:IMAGETYPE_PNG
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function liveRender($imageType = null) {
		if($this->_resized === false) {
			$this->resize($this->_targetWidth, $this->_targetHeight);
		}
		return $this->resample(true, $imageType);
	}

	/**
	 * Save computed image to specific place
	 *
	 * @param string $targetFile full pathname to save the file
	 *
	 * @return boolean
	 * @since  1.0.0
	 */
	public function save($targetFile) {
		if($this->_resized === false) {
			$this->resize($this->_targetWidth, $this->_targetHeight);
		}
		return copy($this->getCachedName(true), $targetFile);
	}

	/**
	 * Create a GD object
	 *
	 * @param string  $filename filename to the image (with path)
	 * @param integer $type     type of the image @see getimagesize()
	 *
	 * @throws \Exception
	 * @return GdImage
	 * @since  1.0.0
	 */
	private function loadImage($filename, $type) {
		$gdImage = null;
		switch($type) {
			case IMAGETYPE_PNG:
				$gdImage = imagecreatefrompng($filename);
				imagealphablending($gdImage, false);
				imagesavealpha($gdImage, true);
				break;
			case IMAGETYPE_GIF:
				$gdImage = imagecreatefromgif($filename);
				break;
			case IMAGETYPE_JPEG:
				$gdImage = imagecreatefromjpeg($filename);
				break;
			default:
				throw new \Exception('imagetype unknown');
				break;
		}
		return $gdImage;
	}

	/**
	 * Resample the image using GD, if the parameters $return is set to
	 * true, the bytes are returned instead of saving them to a specific
	 * file
	 *
	 * @param $return    boolean true to return the data instead of writing it
	 * @param $imageType integer Image type ex: IMAGETYPE_PNG
	 *
	 * @throws \Exception
	 * @return mixed
	 * @since  1.0.0
	 */
	private function resample($return = false, $imageType = null) {
		$imageType = ($imageType !== null) ? $imageType : $this->_imageType;
		$this->computeSize();
		$originalImage = $this->loadImage($this->_fileImage, $this->_imageType);
		if($this->_fit === false) {
			$targetImage = imagecreatetruecolor($this->_finalWidth, $this->_finalHeight);
		} else {
			$targetImage = imagecreatetruecolor($this->_targetWidth, $this->_targetHeight);
		}
		if($this->_imageType === IMAGETYPE_PNG) {
			imagealphablending($targetImage, false);
			imagesavealpha($targetImage, true);
		}
		if($this->_fit === false) {
			imagecopyresampled($targetImage, $originalImage, 0, 0, 0, 0, $this->_finalWidth, $this->_finalHeight, $this->_originalWidth, $this->_originalHeight);
		} else {
			imagecopyresampled($targetImage, $originalImage, ($this->_targetWidth - $this->_finalWidth)/2+$this->_offsetX,  ($this->_targetHeight - $this->_finalHeight)/2+$this->_offsetY, 0, 0, $this->_finalWidth, $this->_finalHeight, $this->_originalWidth, $this->_originalHeight);

		}
		if($this->_maskFileName !== null) {
			list($maskWidth, $maskHeight, $maskType) = getimagesize($this->_maskFileName);
			$maskImage = $this->loadImage($this->_maskFileName, $maskType);
			if($this->_fit === false) {
				$destX = $this->_finalWidth - $maskWidth;
				$destY = $this->_finalHeight - $maskHeight;
			} else {
				$destX = $this->_targetWidth - $maskWidth;
				$destY = $this->_targetHeight - $maskHeight;
			}
			if($maskType === IMAGETYPE_PNG) {
        		// remove the alpha blending
        		imagealphablending($targetImage, false);
        		$transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
        		imagefill($targetImage, 0, 0, $transparent);
        		imagesavealpha($targetImage, true);
        		imagealphablending($targetImage, true);
        		imagecopyresampled($targetImage, $maskImage, $destX, $destY, 0, 0, $maskWidth, $maskHeight, $maskWidth, $maskHeight);
			} else {
				imagecopymerge($targetImage, $maskImage, $destX, $destY, 0, 0, $maskWidth, $maskHeight, $this->_maskTransparency);
			}

		}
		$cachedName = ($return === true)?null:$this->getCachedName(true);
		$rawData = null;
		switch($imageType) {
			case IMAGETYPE_PNG:
				$imageQuality = (int) ($this->_quality/10);
				if($return === true) {
					ob_start();
				}
				imagepng($targetImage, $cachedName, (($imageQuality<10)?$imageQuality:9) );
				if($return === true) {
					$rawData = ob_get_clean();
				}
				break;
			case IMAGETYPE_GIF:
				if($return === true) {
					ob_start();
				}
				imagegif( $targetImage, $cachedName );
				if($return === true) {
					$rawData = ob_get_clean();
				}
				break;
			case IMAGETYPE_JPEG:
				if($return === true) {
					ob_start();
				}
				imagejpeg( $targetImage, $cachedName, $this->_quality );
				if($return === true) {
					$rawData = ob_get_clean();
				}
				break;
			default:
				throw new \Exception('imagetype unknown');
				break;
		}
		return $rawData;
	}

	/**
	 * Compute the target size.
	 * Only used when the image must be resampled
	 *
	 * @return void
	 * @since  1.0.0
	 */
	private function computeSize() {
		list($this->_originalWidth, $this->_originalHeight, $this->_imageType) = getimagesize($this->_fileImage);
		if($this->_ratio === false) {
			$this->_finalWidth = $this->_targetWidth;
			$this->_finalHeight = $this->_targetHeight;
			// $this->_targetSize = array( $targetWidth, $targetHeight );
		} elseif($this->_fit === false) {
			$xRatio = (float) ($this->_targetWidth / $this->_originalWidth);
			$yRatio = (float) ($this->_targetHeight / $this->_originalHeight);
			if(($xRatio > 0.0) && ($yRatio > 0.0)) {
				$ratio = min($xRatio, $yRatio);
			} else {
				if(($xRatio === 0.0) && ($yRatio === 0.0)) {
					$ratio = 1;
				} else {
					$ratio = max($xRatio, $yRatio);
				}
			}
			$this->_finalWidth = round($ratio * $this->_originalWidth);
			$this->_finalHeight = round($ratio * $this->_originalHeight);
		} else {
			$xRatio = (float) ($this->_targetWidth / $this->_originalWidth);
			$yRatio = (float) ($this->_targetHeight / $this->_originalHeight);
			if(($xRatio === 0.0) && ($yRatio === 0.0)) {
				$ratio = 1;
			} else {
				$ratio = max($xRatio, $yRatio);
			}
			$this->_finalWidth = round($ratio * $this->_originalWidth);
			$this->_finalHeight = round($ratio * $this->_originalHeight);
		}
	}

	/**
	 * Get url of resized image. If image
	 * is not cached, image will be resized and cached
	 *
	 * @param integer $width target width
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function resizeWidth($width) {
		$this->_ratio = true;
		$this->_fit = false;
		return $this->resize($width, null);
	}

	/**
	 * Get url of resized image. If image
	 * is not cached, image will be resized and cached
	 *
	 * @param integer $height target height
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function resizeHeight($height) {
		$this->_ratio = true;
		$this->_fit = false;
		return $this->resize(null, $height);
	}
}
