<?php
namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    public function errorMessage()
    {
        $error = '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT, 'UTF-8') . "</strong><br>\n";
        return $error;
    }
}