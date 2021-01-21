<?php
namespace Lofi\Ftp;

class Error extends \Exception
{
    private const CNNCTN = 'Connection has failed!';
    private const LOGIN = 'Login name or password is incorrect!';
    private const CHDIR = 'Directory change failed!';
    //private const MKDIR = 'Directory creation failed!';
    private const ISDIR = 'Invalid directory';
    private const ISFILE = 'File doesnt exist!';
    //private const PUT = 'File upload failed!';
    //private const GET = 'File download failed!';
    private const DEL = 'File deletion failed';

    public function __construct($err, ...$helpers)
    {
        parent::__construct();
        $help = "";
        foreach($helpers as $helper) { $help.=($helper." "); }
        echo "error:ftp [", $this->GetFile(), ":", $this->Getline(), "] ";
        echo \constant("self::$err")." ( {$help})", PHP_EOL;
    }
};


?>