<?php
// Simple script to generate a PNG from the SVG (requires GD / Imagick, or just serving a static base64 for testing)
// Since we can't easily rely on server libs, I'll create a simple 512x512 PNG with the app initials.

$width = 512;
$height = 512;
$im = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($im, 79, 70, 229); // Primary Color #4f46e5
$white = imagecolorallocate($im, 255, 255, 255);

// Fill Background
imagefilledrectangle($im, 0, 0, $width, $height, $bg);

// Add Text if Font available, else just a square
$text_color = $white;
// Try to center some text (approximate)
$font_size = 5; // GD built-in font
$text = "POS";
$fw = imagefontwidth($font_size) * strlen($text);
$fh = imagefontheight($font_size);
$x = ($width - $fw) / 2;
$y = ($height - $fh) / 2;
// Use built-in string for fallback
imagestring($im, $font_size, $x, $y, $text, $text_color);

header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);
?>
