<?php

namespace SanSIS\CrudBundle\Service\Exception;

abstract class AbstractException extends \Exception
{
    protected $message = '';

    private $errors = array();

    public function __construct($errors = array(), $message = "", $code = 0, Exception $previous = null)
    {
        if (strlen(trim($message)) > 0) {
            $this->message = $message;
        }
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
