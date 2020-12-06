<?php
namespace Lyo;

require "lyo_funcs_general.php";
use function \Lyo\Funcs\General\{extractFilepath, extractFilename, isValidDir, isStrEndsWith};

class FtpHandler
{
    // works for folders and files, doesn't work for root folder '/'
    public function isFileExist($ftpConn, $fullPath)
    {
        $fullPath = \rtrim($fullPath, "/\\");
        $parentPath = extractFilepath($fullPath);
        if(isset($parentPath) && \strlen($parentPath) == 0) { $parentPath = '/'; }
        $fileName = extractFilename($fullPath);

        

        if(isValidDir($parentPath))
        {
            if (\ftp_chdir($ftpConn, $parentPath))
            {
                $files = \ftp_nlist($ftpConn, "-A .");

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

    
    
    public function deleteDirAndFiles($ftpConn, $fullPathDir)
    {
        if(isValidDir($fullPathDir))
        {   
            if (\ftp_chdir($ftpConn, $fullPathDir)) 
            {
                $curDirRestore = \ftp_pwd($ftpConn); // store here, otherwise error will occur during deleting folders and files, because current dir changes

                //-A parameter is not supported by all ftp apps
                $rawPaths = \ftp_rawlist($ftpConn, "-A .");
                $filePaths = \ftp_nlist($ftpConn, "-A .");
            
                if(\count($rawPaths) == \count($filePaths))
                {
                    for($i = 0; $i < \count($rawPaths); ++$i)
                    {
                        if(isStrEndsWith($rawPaths[$i], $filePaths[$i]))
                        {
                            // is dir
                            if(isValidDir($filePaths[$i]))
                            {
                                if($rawPaths[$i][0] === 'd')
                                {
                                    $this->deleteDirAndFiles($ftpConn, $curDirRestore.'/'.$filePaths[$i]);
                                }
                                else
                                {
                                    //$isfilechmo = ftp_chmod($ftpConn, 0777, $filePaths[$i]); // seems like doesn't work remotely, only locally??
                                    if(!\ftp_delete($ftpConn, $curDirRestore.'/'.$filePaths[$i]))
                                    {
                                        echo "failed to delete file ".$filePaths[$i].PHP_EOL;
                                    }
                                }
                            }
                        }
                    }

                    if(!\ftp_rmdir($ftpConn , $fullPathDir))
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


    public function removeDir($ftpServer, $ftpUser, $ftpPass, $ftpDir)
    {
        if(\strlen($ftpDir) < 2) { die("Cannot delete root directory"); }
        $ftpConn = \ftp_connect($ftpServer) or die("Error: ftp: $ftpServer: Connection has failed!");
        $login = \ftp_login($ftpConn, $ftpUser, $ftpPass);
        if ($login) 
        {  
            //nlist, rawlist, get, put do not work in active mode
            \ftp_pasv($ftpConn, true);
            
            if($this->isFileExist($ftpConn, $ftpDir))
            {
                $this->deleteDirAndFiles($ftpConn, $ftpDir);
            }
            else
            {
                echo "Error: ftp: $ftpDir: File doesn't exist";
            }
        }
        else
        {
            echo ("Error: ftp: $ftpUser: Login name or password is incorrect!");
        }

        // close connection
        \ftp_close($ftpConn);
    }

};


?> 