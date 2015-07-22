<?php

namespace SanSIS\CrudBundle\Service\Exception;

class UniqueException extends \Exception
{
    protected $message = '';

    private $errors = array();

    public function __construct($errors = array(), $message = "", $code = 0, Exception $previous = null)
    {
        $pipe = '';
        foreach ($errors as $error) {
            $this->message .= $pipe.$error['message'] ;
            $pipe = '|';
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
