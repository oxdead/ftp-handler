<?php
namespace Lyo;
require "lyo_funcs_general.php";
use function \Lyo\Funcs\General\{extractFilepath, extractFilename, isValidDir, isStrEndsWith};



//todo: use ob
//https://www.php.net/manual/en/book.outcontrol.php
//https://stackoverflow.com/questions/927341/upload-entire-directory-via-php-ftp
//flush buffers

//make custom exception class and add object insode class
//handle symbolic links in downloadDir()
// do not show message on ftp_mkdir in uploadDir


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
        if($this->checkConnection())
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

        if($this->checkConnection())
        {
            if($this->isFileExist($ftpDir))
            {
                $this->deleteDirAndFiles($ftpDir);
                return true;
            }
        }
        return false;
    }

    // upload content of localDir into ftpDir
    public function uploadDir($localDir, $ftpDir)
    {
        if($this->checkConnection())
        {
            if($this->isFileExist($ftpDir)) // check parent folder existence
            {
                // local dir copied into ftp dir
                $this->uploadDirAndFiles($localDir, $ftpDir);
                return true;
            }
        }
        return false;
    }


    //////////////////////////////////////////////////////////////////////////////////////////////////////

    private function checkConnection()
    {
        if (isset($this->hConnection)) 
        {
            return true;
        }

        throw new \Exception("Error: ftp: Connection has failed!");
        return false;
    }

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

    private function uploadDirAndFiles($localDir, $ftpDir)
    {
        if(isValidDir($ftpDir) && isValidDir($localDir))
        {   
            $errorList = array();
            if (!\is_dir($localDir)) throw new \Exception("Invalid directory: $localDir");
            \chdir($localDir);
            $hDir = \opendir(".");
            while ($file = \readdir($hDir)) 
            {
                if (!\in_array($file, $this->blackList))
                {
                    //workaround for symlinks
                    if(\is_link($file))
                    {
                        $linkPath = \readlink($file);
                        if(isset($linkPath) && !empty($linkPath))
                        {
                            $symlinkName = $file.'.sym.link';
                            $hSymLink = \fopen($symlinkName, "w");
                            if($hSymLink)
                            {
                                \fwrite($hSymLink, $linkPath);
                                \fflush($hSymLink);
                                \fclose($hSymLink);
                                chmod($symlinkName, 0777);
                                $errorList["$ftpDir/$file"] = $this->putFile("$localDir/$symlinkName", "$ftpDir/$symlinkName");
                                \unlink($symlinkName);
                            }
                        }
                    }
                    else if (\is_dir($file)) 
                    {
                        $errorList["$ftpDir/$file"] = $this->makeDir("$ftpDir/$file");
                        $errorList[] = $this->uploadDirAndFiles("$localDir/$file", "$ftpDir/$file");
                        \chdir($localDir);
                    } 
                    else
                    {
                        $errorList["$ftpDir/$file"] = $this->putFile("$localDir/$file", "$ftpDir/$file");
                    }
                        

                }
            }
            return $errorList;
        }
    }


};


?> 