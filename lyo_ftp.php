<?php
namespace Lyo;
require "lyo_funcs_general.php";
use function \Lyo\Funcs\General\{extractFilepath, extractFilename, isValidDir, isStrEndsWith};



//todo: use ob
//https://www.php.net/manual/en/book.outcontrol.php
//https://stackoverflow.com/questions/927341/upload-entire-directory-via-php-ftp

//make custom exception interface (inherit from Exception ??) and assign to ftphandler


class FtpHandler
{
    private $hConnection = null;
    private $blackList = array('.', '..', 'Thumbs.db');

    public function __construct($ftpHost = "", $ftpUser = "", $ftpPass = "") 
    {
        if ($ftpHost != "") 
        {
            $this->hConnection = \ftp_connect($ftpHost);
            if (isset($this->hConnection)) 
            {
                if (\ftp_login($this->hConnection, $ftpUser, $ftpPass)) 
                {
                    //nlist, rawlist, get, put do not work in active mode
                    \ftp_pasv($this->hConnection, true);
                } 
                else 
                {
                    ftp_close($this->hConnection);
                    unset($this->hConnection);
                    //todo: handle Exception properly
                    echo("Error: ftp: $ftpUser: Login name or password is incorrect!");
                }
            }
            else
            {
                //todo: handle Exception properly
                echo ("Error: ftp: $ftpHost: Connection has failed!");
            }
        }
    }

    public function __destruct() 
    {
        if (isset($this->hConnection)) 
        {
            ftp_close($this->hConnection);
            unset($this->hConnection);
        }
    }


    // works for folders and files, doesn't work for root folder '/'
    public function isFileExist($fullPath)
    {
        $fullPath = \rtrim($fullPath, "/\\");
        $parentPath = extractFilepath($fullPath);
        if(isset($parentPath) && \strlen($parentPath) == 0) { $parentPath = '/'; }
        $fileName = extractFilename($fullPath);

        if(isValidDir($parentPath))
        {
            if (\ftp_chdir($this->hConnection, $parentPath))
            {
                $files = \ftp_nlist($this->hConnection, "-A .");

                foreach($files as $file)
                {
                    if($file === $fileName)
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function makeDir($ftpFullPathDir) 
    {
        $error = "";
        try 
        {
            \ftp_mkdir($this->hConnection, $ftpFullPathDir);
        } 
        catch (\Exception $e) 
        {
            if ($e->getCode() == 2) $error = $e->getMessage(); 
        }
        return $error;
    }

    
    public function putFile($localPath, $remotePath) 
    {
        $error = "";
        try 
        {
            \ftp_put($this->hConnection, $remotePath, $localPath, FTP_BINARY); 
        } 
        catch (\Exception $e) 
        {
            if ($e->getCode() == 2) $error = $e->getMessage(); 
        }
        return $error;
    }


    public function removeDir($ftpDir)
    {
        if($ftpDir === '\\' || $ftpDir === '/') 
        { 
            echo("Cannot delete root directory"); 
            return false; 
        }
        if (isset($this->hConnection))
        {  
            if($this->isFileExist($ftpDir))
            {
                $this->deleteDirAndFiles($ftpDir);
            }
        }
    }

    // public function uploadDir($localDir, $ftpDir)
    // {
    //     if ($this->hConnection) 
    //     {  
    //         if($this->isFileExist($ftpDir)) // ?? parent maybe
    //         {
    //             $this->uploadDirAndFiles($localDir, $ftpDir);
    //         }
    //     }

    // }


    private function deleteDirAndFiles($fullPathDir)
    {
        if(isValidDir($fullPathDir))
        {   
            if (\ftp_chdir($this->hConnection, $fullPathDir)) 
            {
                $curDirRestore = \ftp_pwd($this->hConnection); // store here, otherwise error will occur during deleting folders and files, because current dir changes

                //-A is not supported by all ftp apps
                $rawPaths = \ftp_rawlist($this->hConnection, "-A .");
                $filePaths = \ftp_nlist($this->hConnection, "-A .");
            
                if(\count($rawPaths) == \count($filePaths))
                {
                    for($i = 0; $i < \count($rawPaths); ++$i)
                    {
                        if(isStrEndsWith($rawPaths[$i], $filePaths[$i]))
                        {
                            if(isValidDir($filePaths[$i]))
                            {
                                if($rawPaths[$i][0] === 'd')
                                {
                                    $this->deleteDirAndFiles($curDirRestore.'/'.$filePaths[$i]);
                                }
                                else
                                {
                                    //$isfilechmo = ftp_chmod($this->hConnection, 0777, $filePaths[$i]); // doesn't work? check again with pasv=true
                                    if(!\ftp_delete($this->hConnection, $curDirRestore.'/'.$filePaths[$i]))
                                    {
                                        echo "failed to delete file ".$filePaths[$i].PHP_EOL;
                                    }
                                }
                            }
                        }
                    }

                    if(!\ftp_rmdir($this->hConnection , $fullPathDir))
                    {
                        echo "failed to delete folder ".$fullPathDir.PHP_EOL;
                    }
                }
            } 
            else 
            { 
                echo "Couldn't change directory {$fullPathDir}\n";
                return;
            }
        }
    }








};


?> 