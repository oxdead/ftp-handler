<?php
namespace Lyo;
require "lyo_funcs_general.php";
use function \Lyo\Funcs\General\{extractFilepath, extractFilename, isValidDir, isStrEndsWith};


//todo: mb_stuff

//todo: asyncronous copying?

//todo: use ob
//https://www.php.net/manual/en/book.outcontrol.php
//https://stackoverflow.com/questions/927341/upload-entire-directory-via-php-ftp

// todo: use exception class for errors to be able to reference file and line of code, also use set_exception_handler for custom formatting

class FtpHandler
{
    private $hConnection = null;
    private $blackList = array('.', '..', 'Thumbs.db'); // ignore included files on upload/download

    //errors
    private const CNNCTN = 'Connection has failed!';
    private const LOGIN = 'Login name or password is incorrect!';
    private const CHDIR = 'Directory change failed!';
    //private const MKDIR = 'Directory creation failed!';
    private const ISDIR = 'Invalid directory';
    private const ISFILE = 'File doesnt exist!';
    //private const PUT = 'File upload failed!';
    //private const GET = 'File download failed!';
    private const DEL = 'File deletion failed';
    
    
    


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
                    \ftp_close($this->hConnection);
                    unset($this->hConnection);
                    $this->err('CNNCTN', $ftpUser);
                }
            }
            else
            {
                $this->err('CNNCTN', $ftpHost);
            }
        }
    }


    public function __destruct() 
    {
        $this->close();
    }


    /**
     * Close connection manually. Can be useful when dealing with multiple connections
     */
    public function close()
    {
        if (isset($this->hConnection)) 
        {
            \ftp_close($this->hConnection);
            unset($this->hConnection);
        }
    }


    /**
     * get list of file names (relative) inside directory. Changes current dir.
     * @param string $fullPathDir
     * @param string $option -la, -a..
     * @return array|false
     */
    public function getFilesList($fullPathDir, $option = '-a')
    {
        if ($this->checkConnection()) 
        {
            $fullPathDir = \rtrim($fullPathDir, "/\\");
            if (isset($fullPathDir) && \strlen($fullPathDir) > 0) 
            {
                if (\ftp_chdir($this->hConnection, $fullPathDir)) 
                {
                    return \ftp_nlist($this->hConnection, "{$option} .");
                }
                else
                {
                    $this->err('CHDIR', $fullPathDir);
                }
            }
        }
        return false;
    }


    /**
     * for folders and files, except root folder '/'. Changes current dir
     * @param string $fullPath 
     * @return bool
     */
    public function isFileExist($fullPath)
    {
        if($this->checkConnection())
        {
            $fullPath = \rtrim($fullPath, "/\\");
            $parentPath = extractFilepath($fullPath);

            //todo: windows
            if(isset($parentPath) && \strlen($parentPath) == 0) { $parentPath = '/'; }
            $fileName = extractFilename($fullPath);

            if(isValidDir($parentPath))
            {
                if (\ftp_chdir($this->hConnection, $parentPath))
                {
                    $files = \ftp_nlist($this->hConnection, "-a .");
                    
                    foreach($files as $file)
                    {
                        if($file === $fileName)
                        {
                            return true;
                        }
                    }
                }
                else
                {
                    $this->err('CHDIR', $parentPath);
                    return false;
                }
            }
        }
        $this->err('ISFILE', $fullPath);
        return false;
    }


    /**
     * @param string $localPath 
     * @param string $ftpPath
     */
    public function getFile($localPath, $ftpPath) 
    {
        \ftp_get($this->hConnection, $localPath, $ftpPath, FTP_BINARY);
    }
    

    /**
     * @param string $localPath 
     * @param string $ftpPath
     */
    public function putFile($localPath, $ftpPath) 
    {
        \ftp_put($this->hConnection, $ftpPath, $localPath, FTP_BINARY);
    }


    /**
     * @param string $ftpDir
     */
    public function makeDir($ftpDir) 
    {
        // notice: stupid function returns false and creates warning even on successful dir creation
        \ftp_mkdir($this->hConnection, $ftpDir); 
    }


    /**
     * upload content of localDir into ftpDir
     * @param string $localDir 
     * @param string $ftpDir
     * @return bool
     */
    public function uploadDir($localDir, $ftpDir)
    {
        if($this->isFileExist($ftpDir)) // check parent folder existence
        {
            // local dir copied into ftp dir
            $this->uploadDirAndFiles($localDir, $ftpDir);
            return true;
        }
        return false;
    }


    /**
     * download content of ftpDir into localDir
     * @param string $localDir 
     * @param string $ftpDir
     * @return bool 
     */
    public function downloadDir($localDir, $ftpDir)
    {
        if($this->isFileExist($ftpDir)) // check parent folder existence
        {
            $this->downloadDirAndFiles($localDir, $ftpDir);
            return true;
        }
        return false;
    }


    /**
     * remove ftpDir and it's content
     * @param string $ftpDir
     * @return bool 
     */
    public function removeDir($ftpDir)
    {
        if($ftpDir === '\\' || $ftpDir === '/') 
        { 
            $this->err('DEL', $ftpDir);
            return false; 
        }

        if($this->isFileExist($ftpDir))
        {
            $this->deleteDirAndFiles($ftpDir);
            return true;
        }
        return false;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////

    private function err($err, ...$helpers)
    {
        $help = "";
        foreach($helpers as $helper) { $help.=($helper." "); }
        echo "error:ftp: ".constant("self::$err")." ( {$help})", PHP_EOL;
    }


    private function checkConnection()
    {
        if (isset($this->hConnection)) 
        {
            return true;
        }

        $this->err('CNNCTN');
        return false;
    }


    private function isDirChanged($fullPath)
    {
        $fullPath = \rtrim($fullPath, "/\\");
        if(isset($fullPath) && \strlen($fullPath) == 0) 
        { 
            //todo: windows
            $fullPath = '/'; 
        }

        if(isValidDir($fullPath))
        {
            if (\ftp_chdir($this->hConnection, $fullPath))
            {
                if ($fullPath === \rtrim(\ftp_pwd($this->hConnection), "/\\")) 
                {
                    return true;
                }
            }
        }
        $this->err('CHDIR', $fullPath);
        return false;
    }


    private function uploadDirAndFiles($localDir, $ftpDir)
    {
        if(isValidDir($localDir) && isValidDir($ftpDir))
        {   
            if (!\is_dir($localDir)) 
            {
                $this->err('ISDIR', $localDir);
                return;
            }
            \chdir($localDir);
            $hDir = \opendir(".");
            while ($file = \readdir($hDir)) 
            {
                if (!\in_array($file, $this->blackList))
                {
                    $s = DIRECTORY_SEPARATOR;
                    //workaround for symlinks
                    if(\is_link($file))
                    {
                        $linkPath = \readlink($file);
                        if(isset($linkPath) && strlen($linkPath)>0)
                        {
                            $symLinkName = $file.'.slnkstore';
                            $hSymLink = \fopen($symLinkName, "w");
                            if($hSymLink)
                            {
                                \fwrite($hSymLink, $linkPath);
                                \fclose($hSymLink);
                                \chmod($symLinkName, 0777);
                                \ftp_put($this->hConnection, "$ftpDir{$s}$symLinkName", "$localDir{$s}$symLinkName", FTP_BINARY);
                                \unlink($symLinkName);
                            }
                        }
                    }
                    else if (\is_dir($file)) 
                    {
                        \ftp_mkdir($this->hConnection, "$ftpDir{$s}$file");
                        $this->uploadDirAndFiles("$localDir{$s}$file", "$ftpDir{$s}$file");
                        \chdir($localDir);
                    } 
                    else
                    {
                        \ftp_put($this->hConnection, "$ftpDir{$s}$file", "$localDir{$s}$file", FTP_BINARY);
                    }
                        

                }
            }

            if (isset($hDir)) 
            {
                \closedir($hDir);
            }
        }
    }


    private function downloadDirAndFiles($localDir, $ftpDir)
    {
        if(isValidDir($localDir) && isValidDir($ftpDir))
        {   
            if($this->isDirChanged($ftpDir)) 
            {
                $curDirRestore = \ftp_pwd($this->hConnection); // store here, otherwise error, because current dir changes via ftp_chdir

                //-a is not supported by all ftp apps
                //$fileInfo = \ftp_rawlist($this->hConnection, "-la .");
                $fileInfo = \ftp_nlist($this->hConnection, "-la .");
                $filePaths = \ftp_nlist($this->hConnection, "-a .");
            
                if(\count($fileInfo) == \count($filePaths))
                {
                    for($i = 0; $i < \count($fileInfo); ++$i)
                    {
                        if(isStrEndsWith($fileInfo[$i], $filePaths[$i]))
                        {
                            if (!\in_array($filePaths[$i], $this->blackList))
                            {
                                $innerLocalPath = $localDir.DIRECTORY_SEPARATOR.$filePaths[$i];
                                $innerFtpPath = $curDirRestore.DIRECTORY_SEPARATOR.$filePaths[$i];
                                    
                                //if ($fileInfo[$i][0] === 'd') 
                                if(\mb_substr($fileInfo[$i], 0, 1, 'utf-8') === 'd')
                                {
                                    \mkdir($innerLocalPath);
                                    $this->downloadDirAndFiles($innerLocalPath, $innerFtpPath);
                                } 
                                else 
                                {
                                    \ftp_get($this->hConnection, $innerLocalPath, $innerFtpPath, FTP_BINARY);

                                    // workaround for symlinks
                                    if (isStrEndsWith($innerLocalPath, '.slnkstore')) 
                                    {
                                        $hSymLink = \fopen($innerLocalPath, "r"); // read only
                                        if($hSymLink)
                                        {
                                            \fseek($hSymLink, 0, SEEK_END);
                                            $fileSize = \ftell($hSymLink);

                                            \fseek($hSymLink, 0, SEEK_SET); 
                                            $linkTarget = \fread($hSymLink, $fileSize);

                                            \fclose($hSymLink);
                                            \unlink($innerLocalPath);
                                            
                                            if(isset($linkTarget) && \strlen($linkTarget)>0)
                                            {
                                                \symlink($linkTarget, \substr($innerLocalPath, 0, -(\strlen('.slnkstore'))));
                                            }
                                        }
                                    }

                                }
                            }
                        }
                    }
                }
            } 
        }
    }


    private function deleteDirAndFiles($ftpDir)
    {
        if(isValidDir($ftpDir))
        {   
            if (\ftp_chdir($this->hConnection, $ftpDir)) 
            {
                $curDirRestore = \ftp_pwd($this->hConnection); // store here, current dir changes in inner folders via ftp_chdir

                $fileInfo = \ftp_nlist($this->hConnection, "-la .");
                $filePaths = \ftp_nlist($this->hConnection, "-a .");

                if(\count($fileInfo) == \count($filePaths))
                {
                    for($i = 0; $i < \count($fileInfo); ++$i)
                    {
                        if(isStrEndsWith($fileInfo[$i], $filePaths[$i]))
                        {
                            if(isValidDir($filePaths[$i]))
                            {
                                if(mb_substr($fileInfo[$i], 0, 1, 'utf-8') === 'd')
                                {
                                    $this->deleteDirAndFiles($curDirRestore.DIRECTORY_SEPARATOR.$filePaths[$i]);
                                }
                                else
                                {
                                    //$isfilechmo = ftp_chmod($this->hConnection, 0777, $filePaths[$i]); // doesn't work? check again with pasv=true
                                    if(!\ftp_delete($this->hConnection, $curDirRestore.DIRECTORY_SEPARATOR.$filePaths[$i]))
                                    {
                                        $this->err('DEL', $filePaths[$i]);
                                    }
                                }
                            }
                        }
                    }

                    if(!\ftp_rmdir($this->hConnection , $ftpDir))
                    {
                        $this->err('DEL', $ftpDir);
                    }
                }
            } 
            else 
            { 
                $this->err('CHDIR', $ftpDir);
            }
        }
    }


};


?> 