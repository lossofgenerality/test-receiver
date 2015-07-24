<?php
//this script will iterate though a directory full of result files and convert them to a CSV
$dir = new DirectoryIterator(dirname(__FILE__));
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'txt') {
        //var_dump($fileinfo->getFilename());
        $c = trim(file_get_contents($fileinfo->getFilename()));
        $c = str_replace("\\\r\n", '', $c);
        $c = str_replace("\\\"", '"', $c);
        $c = substr($c, 1, strlen($c) - 2);

        $s = strpos($c, '{{');
        $e = strpos($c, '}} ,');
        if ($s && $e) {
            $c = substr($c, 0, $s) . substr($c, $e + 5);
            echo $c . "\n";
        }
    }
}