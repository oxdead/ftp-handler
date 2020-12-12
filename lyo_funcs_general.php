<?php

namespace Lyo\Funcs\General;

// todo: mb_funcs

//for ftp, but not needed, when -A flag in raw_list and nlist funcs is set, because then it will return relative path instead of absolute
//returned empty string = root dir
//todo: seems like pathinfo() is not very good, try basename() func
function extractFilename($fullPath)
{
    $len = \mb_strlen($fullPath, "UTF-8");
    for ($i = ($len-1) ; $i >= 0 ; --$i)
    {
        if($fullPath[$i] === '/' || $fullPath[$i] === "\\")
        {
            $s = $i + 1; // +1 to exclude '/'
            $l = $len - $s;
            if($s > 0 && $l >= 0)
            {
                return \mb_substr($fullPath, $s, $l, "UTF-8");
            }
            break;
        }
    }
    return false;
}


function extractFilepath($fullPath)
{
    $len = \mb_strlen($fullPath, "UTF-8");
    for ($i = ($len-1) ; $i >= 0 ; --$i)
    {
        if($fullPath[$i] === '/' || $fullPath[$i] === "\\")
        {
            return \mb_substr($fullPath, 0, $i, "UTF-8"); // '/' excluded
        }
    }
    return false;
}


function isStrBeginsWith($haystack, $needle) 
{
    $length = \mb_strlen($needle, "UTF-8");
    return \mb_substr($haystack, 0, $length, "UTF-8") === $needle;
}


function isStrEndsWith($haystack, $needle) 
{
   $length = \mb_strlen($needle, "UTF-8");
   if($length < 1) { return true; }
   return \mb_substr($haystack, -$length, null, "UTF-8") === $needle;
}


function isValidStr($str)
{
    return (isset($str) && mb_strlen($str, "UTF-8")>0);
}


function isValidDir($dir)
{
    return (isValidStr($dir) && $dir !== "." && $dir !== "..");
}




?>