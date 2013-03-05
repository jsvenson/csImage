<?php

/**
* Image Management class
*/
class csImage {
    
    var $image     = '';
    var $width     = 0;
    var $height    = 0;
    var $type      = 0;
    var $mime      = '';
    var $size      = 0;
    var $filename  = '';
    var $extension = '';
    var $folder    = '';
    var $basename  = '';
    var $transparent_index = -1;
    
    const JPG_QUALITY_MAX            = 100;
    const JPG_QUALITY_HIGH           = 80;
    const JPG_QUALITY_MEDIUM         = 60;
    const JPG_QUALITY_LOW            = 40;
    const JPG_QUALITY_UNINTELLIGIBLE = 20;
    
    const PNG_QUALITY_MAX            = 0; # no compression
    const PNG_QUALITY_HIGH           = 2;
    const PNG_QUALITY_MEDIUM         = 4;
    const PNG_QUALITY_LOW            = 6;
    const PNG_QUALITY_UNINTELLIGIBLE = 8;
    
    const CROP_MIDDLE        = 0;
    const CROP_TOP_LEFT      = 1;
    const CROP_TOP_MIDDLE    = 2;
    const CROP_TOP_RIGHT     = 3;
    const CROP_MIDDLE_LEFT   = 4;
    const CROP_MIDDLE_RIGHT  = 5;
    const CROP_BOTTOM_LEFT   = 6;
    const CROP_BOTTOM_MIDDLE = 7;
    const CROP_BOTTOM_RIGHT  = 8;
    
    function __construct($file='') {
        if ($file!='') $this->load($file);
    }
    
    public function load($file) {
        if (($this->size = filesize($file)) === false)
            throw new Exception('Could not get the size of the file '.basename($file).'.');
        
        if (($info = getimagesize($file)) === false)
            throw new Exception('Could not recognize the file '.basename($file).'.');
        
        $this->width    = $info[0];
        $this->height   = $info[1];
        $this->type     = $info[2];
        $this->mime     = $info['mime'];
        
        $finfo = pathinfo($file);
        $this->filename  = $finfo['filename'];
        $this->extension = $finfo['extension'];
        $this->folder    = $finfo['dirname'];
        $this->basename  = $finfo['basename'];
        
        switch ($this->type) {
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($file);
                $this->transparent_index = imagecolortransparent($this->image);
                break;
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($file);
                break;
            default:
                throw new Exception('Unknown file type. Please use GIF, PNG, or JPEG (JPG).');
                break;
        }
    }
    
    public function resizeToHeight($height) {
        $ratio = $height / $this->height;
        $width = $this->width * $ratio;
        $this->resize($width, $height);
    }
    
    public function resizeToWidth($width) {
        $ratio  = $width / $this->width;
        $height = $this->height * $ratio;
        $this->resize($width, $height);
    }
    
    public function scale($percent) {
        $width = $this->width * $percent / 100;
        $height = $this->height * $percent / 100;
        $this->resize($width, $height);
    }
    
    # resize image to a maximum length (longest side no longer than x)
    public function resizeToMaximum($max_width, $max_height = 0) {
        if ($max_height == 0) $max_height = $max_width;
        
        $r1 = $this->width / $max_width;
        $r2 = $this->height / $max_height;
        
        if ($r1 > $r2)
            $this->resizeToWidth($max_width);
        else
            $this->resizeToHeight($max_height);
    }
    
    # resize image to a minimum length (shortest side no longer than x)
    public function resizeToMinimum($min_width, $min_height = 0) {
        if ($min_height == 0) $min_height = $min_width;
        
        $r1 = $this->width / $max_width;
        $r2 = $this->height / $max_height;
        
        if ($r1 > $r2)
            $this->resizeToHeight($min_height);
        else
            $this->resizeToWidth($min_width);
    }
    
    # resize image and place centered on a canvas of $width and $height with a background of $fillcolor
    # $fillcolor == null to skip filling the canvas
    public function resizeToFixedWidthHeight($width, $height, $fillcolor = 'ffffff') {
        $this->resizeToMaximum($width, $height);
        
        $im = imagecreatetruecolor($width, $height);
            
        if (!is_null($fillcolor)) {
            imagefill($im, 0, 0, imagecolorallocate($im, 
                hexdec(substr($fillcolor, 0, 2)), 
                hexdec(substr($fillcolor, 2, 2)), 
                hexdec(substr($fillcolor, 4, 2))));
        }
        
        $this->handleTransparency($im);
        imagecopyresampled($im, $this->image, (($width-$this->width)/2), (($height-$this->height)/2), 0, 0, $this->width, $this->height, $this->width, $this->height);
        
        $this->image  = $im;
        $this->width  = $width;
        $this->height = $height;
    }
    
    # fill a box of ($width, $height) with the image, cropping the longer dimension if necessary
    public function fillToFixedWidthHeight($width, $height) {
        $w_ratio = $this->width / $width;
        $h_ratio = $this->height / $height;
        
        if ($w_ratio < $h_ratio)
            $this->resizeToWidth($width);
        else
            $this->resizeToHeight($height);
        
        $this->crop($width, $height);
    }
    
    private function handleTransparency(&$image) {
        # check for png and honor alpha channel
        if ($this->type == IMAGETYPE_PNG) {
            imagealphablending($image, false);
            $color = imagecolorallocatealpha($image, 0, 0, 0, 127); # transparent black
            imagefill($image, 0, 0, $color);
            imagesavealpha($image, true);
        }
        
        # check for gif and honor transparency color
        if ($this->type == IMAGETYPE_GIF && $this->transparent_index > -1) {
            $color = imagecolorsforindex($this->image, $this->transparent_index); # rgb for $this->transparent_index
            $transparent_color = imagecolorallocate($this->image, $color['red'], $color['green'], $color['blue']);
            imagefill($image, 0, 0, $transparent_color);
            imagecolortransparent($image, $transparent_color);
            $this->transparent_index = $transparent_color;
        }
    }
    
    public function resize($width, $height) {
        $im = imagecreatetruecolor($width, $height);
        
        $this->handleTransparency($im);
        
        imagecopyresampled($im, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
        $this->image  = $im;
        $this->width  = $width;
        $this->height = $height;
    }
    
    # crop image to $width and $height starting at a top left corner of $x, $y
    public function cropAtPoint($x, $y, $width, $height) {
        if ($width > $this->width || $height > $this->height)
            throw new Exception('Invalid width or height.');
        
        if ($x < 0 || $y < 0)
            throw new Exception('Invalid top left coordinates.');
        
        $im = imagecreatetruecolor($width, $height);
        
        $this->handleTransparency($im);
        
        imagecopyresampled($im, $this->image, 0, 0, $x, $y, $width, $height, $width, $height);
        $this->image = $im;
        $this->width = $width;
        $this->height = $height;
    }
    
    # crop image to $width and $height starting at a given $anchor
    public function crop($width = 0, $height = 0, $anchor = self::CROP_MIDDLE) {
        if ($width > $this->width || $height > $this->height)
            throw new Exception('Invalid width or height: w='.$width.', h='.$height.'.');
        
        $im = imagecreatetruecolor($width, $height);
        
        switch ($anchor) {
            case self::CROP_MIDDLE:
                $x = ($this->width - $width) / 2;
                $y = ($this->height - $height) / 2;
                break;
            case self::CROP_TOP_LEFT:
                $x = 0;
                $y = 0;
                break;
            case self::CROP_TOP_MIDDLE:
                $x = ($this->width - $width) / 2;
                $y = 0;
                break;
            case self::CROP_TOP_RIGHT:
                $x = $this->width - $width;
                $y = 0;
                break;
            case self::CROP_MIDDLE_LEFT:
                $x = 0;
                $y = ($this->height - $height) / 2;
                break;
            case self::CROP_MIDDLE_RIGHT:
                $x = $this->width - $width;
                $y = ($this->height - $height) / 2;
                break;
            case self::CROP_BOTTOM_LEFT:
                $x = 0;
                $y = $this->height - $height;
                break;
            case self::CROP_BOTTOM_MIDDLE:
                $x = ($this->width - $width) / 2;
                $y = $this->height - $height;
                break;
            case self::CROP_BOTTOM_RIGHT:
                $x = $this->width - $width;
                $y = $this->height - $height;
                break;
        }
        
        $this->cropAtPoint($x, $y, $width, $height);
    }
    
    public function squarecrop($anchor = self::CROP_MIDDLE, $size = -1) {
        if ($size == -1) $size = $this->width >= $this->height ? $this->height : $this->width;
        $this->crop($size, $size, $anchor);
    }
    
    public function output($path = '', $quality = self::JPG_QUALITY_HIGH, $format = IMAGETYPE_JPEG) {
        switch ($format) {
            case IMAGETYPE_PNG: $function = 'imagepng'; break;
            case IMAGETYPE_GIF: $function = 'imagegif'; break;
            default:            $function = 'imagejpeg'; break;
        }
        
        if ($path == '') {
            return $function($this->image);
        } else {
            $result = $function($this->image, $path, $quality);
            if ($result == false)
                throw new Exception('There was a problem saving the image.');
        }
    }
    
    public function saveJPG($path, $quality = self::JPG_QUALITY_HIGH) {
        $this->output($path, $quality, IMAGETYPE_JPEG);
    }
    
    public function savePNG($path, $quality = self::PNG_QUALITY_HIGH) {
        $this->output($path, $quality, IMAGETYPE_PNG);
    }
    
    public function saveGIF($path) {
        $this->output($path, null, IMAGETYPE_GIF);
    }
    
    public function saveToFile($path, $quality = -1) {
        switch ($this->type) {
            case IMAGETYPE_PNG:
                $quality = $quality > -1? $quality : self::PNG_QUALITY_HIGH;
                $this->savePNG($path, $quality);
                break;
            case IMAGETYPE_GIF:
                $this->saveGIF($path);
                break;
            default:
                $quality = $quality > -1? $quality : self::JPG_QUALITY_HIGH;
                $this->saveJPG($path, $quality);
                break;
        }
    }
}

?>
