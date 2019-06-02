<?php

// include composer autoload
require 'vendor/autoload.php';

// import the Intervention Image Manager Class
use Intervention\Image\ImageManager;

// create an image manager instance with favored driver
$manager = new ImageManager(array('driver' => 'imagick'));

/**
 * @param $hexColor
 * @return array
 */
function getContrastColor($hexColor)
{

    $R1 = hexdec(substr($hexColor, 1, 2));
    $G1 = hexdec(substr($hexColor, 3, 2));
    $B1 = hexdec(substr($hexColor, 5, 2));

    $blackColor = "#000000";
    $R2BlackColor = hexdec(substr($blackColor, 1, 2));
    $G2BlackColor = hexdec(substr($blackColor, 3, 2));
    $B2BlackColor = hexdec(substr($blackColor, 5, 2));

    $L1 = 0.2126 * pow($R1 / 255, 2.2) +
        0.7152 * pow($G1 / 255, 2.2) +
        0.0722 * pow($B1 / 255, 2.2);

    $L2 = 0.2126 * pow($R2BlackColor / 255, 2.2) +
        0.7152 * pow($G2BlackColor / 255, 2.2) +
        0.0722 * pow($B2BlackColor / 255, 2.2);

    if ($L1 > $L2) {
        $contrastRatio = (int)(($L1 + 0.05) / ($L2 + 0.05));
    } else {
        $contrastRatio = (int)(($L2 + 0.05) / ($L1 + 0.05));
    }

    if ($contrastRatio > 5) {
        return [0, 0, 0, 0.5];
    }

    return [255, 255, 255, 0.5];
}

/**
 * 92% JPEG quality gives a very high-quality image while gaining a significant reduction on the original 100% file size.
 * 85% JPEG quality gives a greater file size reduction with almost no loss in quality.
 * 75% JPEG quality and lower begins to show obvious differences in the image, which can reduce your website user experience.
 */
$compression = 85;

$watermark = $manager->make(__DIR__ . '/resources/watermark.png')->resize(2730-74, null, function ($constraint) {
    $constraint->aspectRatio();
//    $constraint->upsize(); /** @todo get bigger watermark */
})->opacity(10);

$sizes = [
    [
        'height' => 1200,
    ],
    [
        'height' => 900,
    ],
    [
        'height' => 687,
    ],
    [
        'height' => 537,
    ],
    [
        'height' => 388,
    ],
    [
        'height' => 241,
    ],
    [
        'height' => 147,
    ],
];


$batchStart = microtime(true);

$i = 0;

foreach (glob(__DIR__."/public/in/*.jpg") as $filename) {

    $i++;
    $start = microtime(true);

    $image = $manager->make($filename)->backup();

    $originalFileName = $image->filename;

    list($originalFileMark, $originalFileNumber) = explode('_', $originalFileName);

    $originalFolder = __DIR__.'/public/out/'.$originalFileMark.'/'.$originalFileNumber.'/original';
    $croppedFolder = __DIR__.'/public/out/'.$originalFileMark.'/'.$originalFileNumber.'/cropped';
    $hdFolder = __DIR__.'/public/out/'.$originalFileMark.'/'.$originalFileNumber.'/hd';
    $rozetkaFolder = __DIR__.'/public/out/'.$originalFileMark.'/'.$originalFileNumber.'/rozetka';

    if (!file_exists($originalFolder)) {
        mkdir($originalFolder, 0777, true);
    }

    if (!file_exists($croppedFolder)) {
        mkdir($croppedFolder, 0777, true);
    }

    if (!file_exists($hdFolder)) {
        mkdir($hdFolder, 0777, true);
    }

    if (!file_exists($rozetkaFolder)) {
        mkdir($rozetkaFolder, 0777, true);
    }

    copy($filename, $originalFolder.'/'.$originalFileName.'.jpg');

    $image
        ->resize(2730, 4096, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

   $image->getCore()->stripImage();
   $image->backup('4k');

   //WATERMARKS
   $hex = $image
       ->reset('4k')
       ->crop(400, 100, 40, $image->getHeight()-140)
       ->blur(100)
       ->pickColor(200, 50, 'hex');

   $image->reset('4k')->sharpen(2)
        ->text($originalFileMark, 36, $image->getHeight()-72, function($font) use ($hex) {
            $font->file(__DIR__ . '/resources/roboto-medium.ttf');
            $font->size(122);
            $font->color(getContrastColor($hex));
            $font->align('bottom');
            $font->valign('left');
        })
        ->insert($watermark, 'bottom', 0, 30);

    $image->getCore()->stripImage();
    $image->backup('watermarked');

    //HD
    $image->reset('watermarked')
        ->save($hdFolder.'/4k.jpg', $compression)
        ->reset('watermarked')
        ->save($hdFolder.'/4k.webp', $compression);

    $image->reset('watermarked')->resize(null, 2048, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    })->backup('2k')->save($hdFolder.'/2k.jpg', $compression);

    $image->reset('2k')->save($hdFolder.'/2k.webp', $compression);

    //ROZETKA
    $image->reset('4k')->resize(null, 1200, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    })->save($rozetkaFolder.'/rozetka.jpg', $compression);

    //THUMBS
    foreach ($sizes as $size) {
        $image->reset('watermarked')->resize(null, $size['height'], function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->backup($size['height'])->save($croppedFolder.'/'.$size['height'].'.jpg', $compression);

        $image->reset($size['height'])->save($croppedFolder.'/'.$size['height'].'.webp', $compression);
    }

    $image->destroy();

    $tmeSeconds = microtime(true) - $start;
    echo "Complete process image {$filename} in {$tmeSeconds} sec\n";
}

$batchTimeSeconds = microtime(true) - $batchStart;

echo "Complete process {$i} images in {$batchTimeSeconds} sec\n";

