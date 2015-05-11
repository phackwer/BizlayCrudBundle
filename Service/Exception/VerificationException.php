<?php

namespace SanSIS\CrudBundle\Service\Exception;

class VerificationException extends \Exception
{
    protected $message = 'CrudBundle - Service - Erros na verificação das regras de negócio';

    private $errors = array();

    public function __construct($errors = array(), $message = "", $code = 0, Exception $previous = null)
    {
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
