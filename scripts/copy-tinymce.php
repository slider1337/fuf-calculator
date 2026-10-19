<?php
declare(strict_types=1);
$source=__DIR__.'/../vendor/tinymce/tinymce';
$target=__DIR__.'/../public/assets/vendor/tinymce';
if(!is_dir($source))throw new RuntimeException('TinyMCE dependency missing');
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
 if(!$file instanceof SplFileInfo||!$file->isFile())continue;
 $path=$iterator->getSubPathName();
 if(!str_ends_with($path,'.min.js')&&!str_ends_with($path,'.min.css')&&!in_array($path,['license.md','notices.txt'],true))continue;
 $destination=$target.'/'.$path;$directory=dirname($destination);
 if(!is_dir($directory)&&!mkdir($directory,0777,true)&&!is_dir($directory))throw new RuntimeException('Cannot create TinyMCE asset directory');
 if(!copy($file->getPathname(),$destination))throw new RuntimeException('Cannot copy TinyMCE assets');
}
