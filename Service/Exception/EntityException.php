<?php

namespace SanSIS\CrudBundle\Service\Exception;

class EntityException extends \Exception
{
    protected $message = 'CrudBundle - Service - Erros na validação dos dados de entrada';

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
