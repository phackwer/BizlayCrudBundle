<?php

namespace SanSIS\CrudBundle\Service\Exception;

class UniqueException extends \Exception
{
    protected $message = '';

    private $errors = array();

    public function __construct($errors = array(), $message = "", $code = 0, Exception $previous = null)
    {
        foreach($errors as $error){
            $this->message .= $error['message']."\n";
        }
        $this->setErrors($errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
        return $this;
    }
}
