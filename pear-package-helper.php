#!/usr/bin/env php
<?php
//
// Copies all files mentioned in <contents> of package.xml to a temporary dir, enabling commented out
// require_once statements. The result may be packaged with "pear package".
//
// This is needed as <tasks:replace> tags in package.xml do not allow arbitrary substitutions, unfortunately.
//


$translations = array(
    '// pear-package-only ' => ''
);

function handleFile(SimpleXMLElement $file, $dirName)
{
    global $translations;

    if (false === ($pos = strpos($file['name'], '/'))) {
        $targetDir = './.pear-package' . substr($dirName, 1);

    } else {
        $targetDir = './.pear-package' . substr($dirName, 1) . '/'
                     . substr($file['name'], 0, $pos);
        if (!is_dir($targetDir)) {
            echo "Creating {$targetDir}" . PHP_EOL;
            mkdir($targetDir, 0777, true);
        }
    }

    if (!preg_match('/.php$/', $file['name'])) {
        echo "Copying {$dirName}/{$file['name']} to {$targetDir}" . PHP_EOL;
        copy("{$dirName}/{$file['name']}", './.pear-package' . substr($dirName, 1) . '/' . $file['name']);
    } else {
        echo "Mangling {$dirName}/{$file['name']} and saving to {$targetDir}" . PHP_EOL;
        $text = file_get_contents($dirName . '/' . $file['name']);
        file_put_contents('./.pear-package' . substr($dirName, 1) . '/' . $file['name'], strtr($text, $translations));
    }
}

function handleDir(SimpleXMLElement $dir, $dirName = null)
{
    if (null === $dirName) {
        $dirName  = '.';
    } else {
        $dirName .= '/' . $dir['name'];
    }

    $targetDir = './.pear-package' . substr($dirName, 1);
    if (!is_dir($targetDir)) {
        echo "Creating {$targetDir}" . PHP_EOL;
        mkdir($targetDir, 0777, true);
    }

    foreach ($dir->children() as $child) {
        if ('dir' === $child->getName()) {
            handleDir($child, $dirName);
        } else {
            handleFile($child, $dirName);
        }
    }
}

$package = simplexml_load_file('./package.xml');

handleDir($package->contents->dir);

copy('./package.xml', './.pear-package/package.xml');