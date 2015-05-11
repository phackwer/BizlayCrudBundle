<?php

namespace SanSIS\CrudBundle\Service\Exception;

class NoRootEntityException extends \Exception
{
    protected $message = 'CrudBundle - Service - Não há entidade raiz definida na declaração da Service';
}
