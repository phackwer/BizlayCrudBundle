<?php

namespace SanSIS\CrudBundle\Service\Exception;

class WrongTypeRootEntityException extends \Exception
{
    protected $message = 'CrudBundle - Service - A entidade informada é de tipo diferente da esperada.';
}
