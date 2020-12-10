<?php

namespace Lyo\Funcs\General;

// todo: mb_funcs

//for ftp, but not needed, when -A flag in raw_list and nlist funcs is set, because then it will return relative path instead of absolute
//returned empty string = root dir
//todo: seems like pathinfo() is not very good, try basename() func
function extractFilename($fullPath)
{
    $len = \strlen($fullPath);
    for ($i = ($len-1) ; $i >= 0 ; --$i)
    {
        if($fullPath[$i] === '/' || $fullPath[$i] === "\\")
        {
            $s = $i + 1; // +1 to exclude '/'
            $l = $len - $s;
            if($s > 0 && $l >= 0)
            {
                return \substr($fullPath, $s, $l);
            }
            break;
        }
    }
    return false;
}


function extractFilepath($fullPath)
{
    $len = \strlen($fullPath);
    for ($i = ($len-1) ; $i >= 0 ; --$i)
    {
        if($fullPath[$i] === '/' || $fullPath[$i] === "\\")
        {
            return \substr($fullPath, 0, $i); // '/' excluded
        }
    }
    return false;
}


function isStrBeginsWith($haystack, $needle) 
{
    $length = \strlen($needle);
    return \substr($haystack, 0, $length) === $needle;
}


function isStrEndsWith($haystack, $needle) 
{
   $length = \strlen($needle);
   if($length < 1) { return true; }
   return \substr($haystack, -$length) === $needle;
}


function isValidStr($str)
{
    return (isset($str) && strlen($str)>0);
}


function isValidDir($dir)
{
    return (isValidStr($dir) && $dir !== "." && $dir !== "..");
}




?>