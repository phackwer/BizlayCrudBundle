<?php
namespace SanSIS\CrudBundle\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use Symfony\Component\Config\Definition\Exception\Exception;
use \Doctrine\ORM\Query;
use \SanSIS\BizlayBundle\Entity\AbstractEntity as Entity;
use \SanSIS\BizlayBundle\Service\AbstractService;
use \SanSIS\BizlayBundle\Service\ServiceDto;
use \SanSIS\CrudBundle\Service\Exception\ValidationException;
use \SanSIS\CrudBundle\Service\Exception\WrongTypeRootEntityException;

/**
 * @TODO Tratar Files no upload - deverá ser antes do flush para utilizar a
 * mesma transaction e ter, de alguma forma, a remoção dos arquivos subidos
 * em caso de exceção
 *
 * @author phackwer
 */
abstract class AbstractEntityService extends AbstractService
{
    /**
     * Suspende a persistência e printa a entidade populada na tela.
     * @var bool
     */
    public $debug = false;

    /**
     * @var string - nome da classe da entidade raiz da service
     */
    protected $rootEntityName = null;

    /**
     * @var string - nome da PK da entidade raiz da service
     */
    protected $rootEntityIdName = 'id';

    /**
     * @var \SanSIS\BizlayBundle\Entity\AbstractEntity
     */
    protected $rootEntity = null;

    /**
     * @var \SanSIS\BizlayBundle\Repository\AbstractRepository
     */
    protected $rootRepository = null;

    // public function encryptEntityId($id)
    // {
    //     $id = $this->getRootEntity()->encryptId($id);
    //     return $id;
    // }

    /**
     * Retorna ou cria automaticamente com base no nome da service, o nome da entidade raiz
     *
     * @return string
     */
    public function getRootEntityName()
    {
        if (is_null($this->rootEntityName)) {
            $arr = explode('\\', get_class($this));
            $arr[count($arr) - 2] = 'Entity';
            $arr[count($arr) - 1] = str_replace('Service', '', $arr[count($arr) - 1]);
            $this->rootEntityName = implode('\\', $arr);
            return $this->rootEntityName;
        }
        return $this->rootEntityName;
    }

    /**
     * Obtém o repositório da entidade raiz mapeada para persistência pela
     *
     * @return \SanSIS\BizlayBundle\Repository\AbstractRepository
     */
    protected function getRootRepository()
    {
        if (is_null($this->rootRepository)) {
            $this->rootRepository = $this->getEntityManager()->getRepository($this->getRootEntityName());
        }

        return $this->rootRepository;
    }

    /**
     * Retorna um array com os dados de apoio do formulário
     *
     * @param array $entityData - dados para auxiliar na busca de informações para o formulário
     *                          Ex: lista de UFs e Cidades)
     * @return array
     */
    public function getFormData($entityData = null)
    {
        $formData = array();
        return $formData;
    }

    /**
     * Retorna um array com os dados da entidade raiz
     *
     * @return array
     */
    public function getRootEntityData($id = null)
    {
        return $this->getRootEntity($id)->toArray();
    }

    /**
     * Retorna um array com os dados da entidade raiz vazia para a criação
     * Sobrescreva caso precise da entidade pré-populada
     *
     * @return array
     */
    public function getNewRootEntityData()
    {
        return $this->getRootEntity()->toArray();
    }

    /**
     * Retorna o id da entidade em uso
     *
     * @return array
     */
    public function getRootEntityId()
    {
        return $this->getRootEntity()->getId();
    }

    /**
     * Obtém a entidade raiz da service já mapeada para persistência pelo EntityManager
     *
     * @param string $id
     * @return \SanSIS\BizlayBundle\Entity\AbstractEntity
     */
    protected function getRootEntity($id = null)
    {
        if ($id) {
            $this->rootEntity = $this->getRootRepository()->find($id);
            if (!$this->rootEntity) {
                throw new \Exception('Não existe entidade com este id no banco de dados.');
            }
        }

        if (!$this->rootEntity) {
            $class = $this->getRootEntityName();
            $this->rootEntity = new $class();
            if (!$this->debug && $id) {
                $this->getEntityManager()->persist($this->rootEntity);
            }
        }

        return $this->rootEntity;
    }

    /**
     * @param \SanSIS\BizlayBundle\Entity\AbstractEntity $entity
     * @throws WrongTypeRootEntityException
     * @return \SanSIS\BizlayBundle\Entity\AbstractEntity
     */
    public function setRootEntity(Entity $entity)
    {
        $entType = $this->getRootEntityName();
        if (strpos($entType, '\\') == 0) {
            $entType = substr($entType, 1);
        }

        if (get_class($entity) != $entType) {
            throw new WrongTypeRootEntityException();
        }

        return $this->rootEntity = $entity;
    }

    /**
     * Método para automatizar o mapeamento de uma entidade para o flush
     *
     * @param ServiceDto $dto
     */
    public function setRootEntityForFlush(ServiceDto $dto)
    {
        $id = null;
        if ($this->rootEntityIdName) {
            if ($dto->query->has($this->rootEntityIdName)) {
                $id = $dto->query->get($this->rootEntityIdName);
            } else {
                $id = $dto->request->get($this->rootEntityIdName);
            }
        }
        $this->getRootEntity($id);
    }

    /**
     * Preenche os dados da entidade com os recebidos pela Service de
     * forma genérica.
     * Deve ser reimplementado nos casos em que o
     * objeto for complexo
     *
     * @param ServiceDto $dto
     * @throws NoImplementationException
     */
    public function populateRootEntity(ServiceDto $dto)
    {
        if ($this->debug) {
            echo '<pre>';
            echo "Query (\$_GET): \n";
            echo highlight_string("<?php \n\n\$_GET = " . var_export($dto->query->all(), true));
            echo "\n\n\nPost (\$_POST): \n";
            echo highlight_string("<?php \n\n\$_POST = " . var_export($dto->request->all(), true));
            echo "\n\n\nEstrutura como deveria ser populada: \n";
            $t = $this->getRootEntityName();
            $t = new $t();
            echo highlight_string("<?php \n\n" . var_export($t->buildFullEmptyEntity(), true));
        }

        try {
            $this->log('info', 'Iniciando população da entidade raiz do tipo ' . $this->getRootEntityName());

            $this->getEntityManager()->persist($this->rootEntity);
            $this->populateEntities($dto, $this->rootEntity, null);

            $this->log('info', 'Fim da população da entidade raiz do tipo ' . $this->getRootEntityName());

            if ($this->debug) {
                echo "\n\n\nComo a entidade foi populada: \n";
                echo highlight_string("<?php \n\n" . var_export($this->getRootEntity()->toArray(), true));
                die;
            }
        } catch (\Exception $e) {
            echo 'ERRO:';
            echo $e->getMessage();
            if (!$this->debug) {
                echo '<pre>';
                echo "Query (\$_GET): \n";
                echo highlight_string("<?php \n\n\$_GET = " . var_export($dto->query->all(), true));
                echo "\n\n\nPost (\$_POST): \n";
                echo highlight_string("<?php \n\n\$_POST = " . var_export($dto->request->all(), true));
                echo "\n\n\nEstrutura como deveria ser populada: \n";
                $t = $this->getRootEntityName();
                $t = new $t();
                echo highlight_string("<?php \n\n" . var_export($t->buildFullEmptyEntity(), true));
            }
            echo "\n\n\nERRO Como a entidade foi populada: \n";
            echo highlight_string("<?php \n\n" . var_export($this->getRootEntity()->toArray(), true));
            die;
        }
    }

    /**
     * Método qque popula a entidade com os dados oriundos do formulário
     *
     * @param mixed $values Valores a serem salvos
     * @param mixed $newClass objeto ou nome da classe
     * @param \SanSIS\BizlayBundle\Entity\AbstractEntity $parent Entidade pai da classe criada
     * @param string $arrayClass - innerEntity de uma Collection
     * @return \SanSIS\BizlayBundle\Entity\AbstractEntity
     */
    public function populateEntities($values, $newClass, $parent, $arrayClass = null)
    {
        if ($parent && isset($values['idDel']) && $values['idDel']) {
            $this->log('info', 'Marcando subentidade para exclusão : ' . $newClass);
            $entity = $this->getEntityManager()->getRepository($newClass)->findOneBy(array('id' => $values['idDel']));
            $this->getEntityManager()->remove($entity);
            return null;
        }

        if (!$parent) {
            $entity = $newClass;
            $setParentMethod = null;
        } else {
            if (isset($values['id']) && trim($values['id']) != '') {
                $entity = $this->getEntityManager()->getRepository($newClass)->findOneBy(array('id' => $values['id']));
                if (!$entity) {
                    $this->log('error', 'Entidade inexistente no banco de dados : ' . $newClass . '::id = ' . $values['id']);
                    echo '<pre>';
                    echo ">>>>>Este erro jamais deve acontecer. Revise o mapeamento<<<<\n";
                    echo '>>>>>Entidade não existente no banco de dados!!!!' . $entity . "<<<<\n";
                    echo '>>>>>' . $newClass . "<<<<\n";
                    print_r($values);
                    print_r($_POST);
                    die('Este erro jamais deve acontecer. Revise o mapeamento');
                };
                if (method_exists($entity, 'setTerm')) {
                    $this->log('info', 'Entidade de tabela de apoio : ' . $newClass . '::id = ' . $values['id']);
                    return $entity;
                }
            } else {
                $this->log('info', 'Criando subentidade para ser populada: ' . $newClass);
                $newClass = str_replace("\r", '', $newClass);
                $entity = new $newClass();
            }

            $class = explode('\\', get_class($parent));

            $class = $class[count($class) - 1];

            $setParentMethod = 'setId' . $class;
            try {
                $ref = new \ReflectionClass($entity);
            } catch (\Exception $e) {
                $this->log('error', 'Entidade não existente no modelo: ' . $newClass . ' (Verifique se a reversa do banco tentou mapear uma tabela sem acesso com o login do sistema).');
                echo '<pre>';
                echo ">>>>>Este erro jamais deve acontecer. Revise o mapeamento<<<<\n";
                echo '>>>>>Entidade não existente no modelo!!!!' . $entity . "<<<<\n";
                echo '>>>>>' . $newClass . "<<<<\n";
                print_r($values);
                print_r($_POST);
                die('Este erro jamais deve acontecer. Revise o mapeamento');
            }
            $this->log('info', 'Resolvendo relacionamentos da entidade acima com sua subentidade: ' . $newClass);
            if (!method_exists($entity, $setParentMethod)) {
                $setParentMethod = 'set' . $class;
            }
            $strDoc = '';
            if (method_exists($entity, $setParentMethod)) {
                $strDoc = $ref->getMethod($setParentMethod)->getDocComment();
            }
            if (!strstr($strDoc, 'ArrayCollection')) {
                if (method_exists($entity, $setParentMethod)) {
                    $entity->$setParentMethod($parent);
                }
            }
        }

        try {
            $ref = new \ReflectionClass($entity);
        } catch (\Exception $e) {
            $this->log('error', 'Entidade não existente no modelo: ' . $newClass . ' (Verifique se a reversa do banco tentou mapear uma tabela sem acesso com o login do sistema).');
            echo '<pre>';
            echo ">>>>>Este erro jamais deve acontecer. Revise o mapeamento<<<<\n";
            echo '>>>>>Entidade não existente no modelo!!!!' . $entity . "<<<<\n";
            echo '>>>>>' . $newClass . "<<<<\n";
            print_r($values);
            print_r($_POST);
            die('Este erro jamais deve acontecer. Revise o mapeamento');
        }

        /**
         * Corrige o retorno de uma classe de proxy que impossibilita a leitura dos comentários corretos da classe
         */
        $this->log('info', 'Entidade Proxy da Doctrine - contornando erro de Reflection para obter a entidade correta do modelo.');
        if (strstr($ref, "DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR")) {
            $bpos = strpos($ref, 'extends ') + 8;
            $epos = strpos($ref, ' implements') - $bpos;
            $correctClass = substr($ref, $bpos, $epos);
            $ref = new \ReflectionClass($correctClass);
        }

        $methods = get_class_methods($entity);

        $this->log('info', 'Populando entidade.');
        foreach ($methods as $method) {
            if ((('set' === substr($method, 0, 3) && strlen($method) > 3)) && $method != $setParentMethod) {
                $this->log('info', 'Populando entidade => ' . get_class($entity) . '::' . $method);

                $attr = lcfirst(substr($method, 3));
                try {
                    if (is_array($values)) {
                        if (isset($values[$attr])) {
                            $value = $values[$attr];
                        } else {
                            $value = null;
                            continue;
                        }
                    } else if ($values instanceof \SanSIS\BizlayBundle\Service\ServiceDto) {
                        if ($values->query->has($attr)) {
                            $value = $values->query->get($attr);
                        } else if ($values->request->has($attr)) {
                            $value = $values->request->get($attr);
                        } else {
                            continue;
                        }
                    } else if (is_null($values)) {
                        return null;
                    }
                } catch (\Exception $e) {
                    var_dump($values);
                    $this->log('error', 'Houve algum erro ao tentar lidar com os dados submetidos ' . var_export($values, true));
                    throw $e;
                }

                if (!is_array($value) && !is_object($value) && $value) {
                    $value = trim($value);
                }

                $params = $ref->getMethod($method)->getParameters();
                $strDoc = $ref->getMethod($method)->getDocComment();
                $strAttr = $ref->getProperty($attr)->getDocComment();
                $class = '';

                if ($params[0]->getClass()) {
                    $bpos = strpos($strDoc, '@param ') + 7;
                    $epos = strpos($strDoc, ' $') - $bpos;
                    $class = substr($strDoc, $bpos, $epos);
                }

                if (strstr($strDoc, '\DateTime') || $class == 'DateTime') {
                    $class = '\DateTime';

                    $this->log('info', 'Tratando data para o formato do banco');

                    if ($value) {
                        if (strstr($value, '/')) {
                            if (strstr($value, ':')) {
                                $value = $class::createFromFormat('d/m/Y H:i:s', $value);
                            } else {
                                $value = $class::createFromFormat('d/m/Y', $value);
                            }
                        } else {
                            if (strstr($value, ':')) {
                                $value = $class::createFromFormat('Y-m-d H:i:s', $value);
                            } else {
                                $value = $class::createFromFormat('Y-m-d', $value);
                            }
                        }
                    } else {
                        //corrige casos de strings vazias para datas
                        $value = null;
                    }
                } else if ((strstr($strDoc, 'ArrayCollection') && strstr($strDoc, '@innerEntity')) && 'set' === substr($method, 0, 3) && is_array($value)) {

                    $this->log('info', 'Populando ArrayCollection');

                    $begin = str_replace("\r", '', substr($strDoc, strpos($strDoc, '@innerEntity ') + 13));
                    $class = substr($begin, 0, strpos($begin, "\n"));
                    $method = str_replace('set', 'add', $method);
                    $allInt = true;
                    foreach ($value as $key => $val) {
                        if (!is_int($key)) {
                            $allInt = false;
                        }
                    }
                    if ($allInt) {
                        foreach ($value as $key => $val) {
                            /**
                             * Tratamento para ManyToMany
                             */
                            if (strstr($strAttr, 'ManyToMany')) {

                                $this->log('info', 'Populando ManyToMany');

                                $begin = substr($strDoc, strpos($strDoc, 'inverseJoinColumns={@ORM\JoinColumn(name="') + strlen('inverseJoinColumns={@ORM\JoinColumn(name="'));
                                $almost = explode('_', substr($begin, 0, strpos($begin, "\",")));
                                $attrToId = '';
                                foreach ($almost as $vall) {
                                    $attrToId .= ucfirst($vall);
                                }
                                $attrToId = lcfirst($attrToId);
                                $innerClassAttr = explode('\\', $class);
                                $innerClassAttr = lcfirst($innerClassAttr[count($innerClassAttr) - 1]);

                                if (isset($val[$attrToId])) {
                                    $val['id'] = $val[$attrToId];
                                }
                            }

                            $this->log('info', 'Populando subentities do ArrayCollection');

                            $inner = $this->populateEntities($val, $class, $entity);
                            if ($inner) {
                                if (!$this->debug) {
                                    $this->getEntityManager()->persist($inner);
                                }
                                $entity->$method($inner);
                            }
                        }

                        continue;
                    } else {
                        $this->log('info', 'Atenção: ArrayCollection  não possuia índices');
                    }
                } else if ($class && !(strstr($strDoc, 'ArrayCollection') || $class == 'ArrayCollection') && 'set' === substr($method, 0, 3) && is_array($value) && !strstr($method, 'setId')) {
                    $value = $this->populateEntities($value, $class, $entity);
                } else if ($class && strstr($strAttr, 'OneToOne')) {
                    $this->log('info', 'Populando OneToOne');
                    if (isset($value['id']) || isset($value['idDel'])) {
                        $value = $this->populateEntities($value, $class, $entity);
                        if (is_object($value)) {
                            $this->getEntityManager()->persist($value);
                        }
                    } else {
                        $this->log('info', 'Não foi submetido id para OneToOne: ignorando para evitar updates');
                        $getMethod = str_replace('set', 'get', $method);
                        $value = $entity->$getMethod();
                    }
                } else if ($class && strstr($strAttr, 'ToOne')) {
                    $this->log('info', 'Populando ManyToOne');
                    if (is_array($value) && array_key_exists('id', $value)) {
                        if (isset($value['id']) && !is_null($value['id']) && trim($value['id']) !== '') {
                            $value = $this->getEntityManager()->getRepository($class)->findOneBy(array('id' => $value['id']));
                        } else {
                            $value = null;
                        }

                    } else {
                        if (!is_null($value) && !empty($value)) {
                            $value = $this->getEntityManager()->getRepository($class)->findOneBy(array('id' => $value));
                        } else {
                            $value = null;
                        }

                    }

                } else if (strstr($strDoc, 'float') && $value) {
                    $this->log('info', 'Populando float (tratamento de decimais de money para float)');
                    if (strstr($value, ',')) {
                        $value = (float) str_replace(',', '.', str_replace('.', '', $value));
                    }
                }

                try {
                    $entity->$method($value);
                } catch (\Exception $e) {
                    $this->log('error', 'Tipo inválido para o método, verifique o mapeamento.');
                    echo "<pre>\n\n AbstractEntityService:\n\n";
                    throw $e;
                }

                if (
                    is_object($value) &&
                    !strstr(get_class($value), 'DateTime') &&
                    !strstr(get_class($value), 'Doctrine') &&
                    !strstr($method, 'setId')
                ) {
                    if (!$this->debug) {
                        $this->log('info', 'Persistinto objeto na EntityManager');
                        $this->getEntityManager()->persist($value);
                    }
                }
            }
        }

        return $entity;
    }

    /**
     * Regras de validação de dados da entidade
     * Caso existam erros de validação, registre a mensagem com addError
     *
     * @example Tipos de dados incompatíveis
     */
    public function validateRootEntity(ServiceDto $dto)
    {
    }

    /**
     * Regras de verificação de regras de negócio sobre a entidade
     * Caso existam erros de verificação de regras, registre a mensagem com addError
     *
     * @example Soma de recursos financeiros já persistidos no banco extrapolam o
     *          total permitido quando somados à nova entidade
     */
    public function verifyRootEntity(ServiceDto $dto)
    {
    }

    /**
     * Caso exista upload de arquivos, manipule-os aqui
     * Caso existam erros de validação, registre a mensagem com addError
     */
    public function handleUploads(ServiceDto $dto)
    {
    }

    /**
     * Regras de verificação unique sobre a entidade
     * Caso existam erros de verificação de unique, registre a mensagem com addError
     */
    private function checkUnique(ServiceDto $dto)
    {
        $reflx = new \ReflectionClass($this->getRootEntity());
        $reader = new IndexedReader(new AnnotationReader());
        $props = $reflx->getProperties();

        foreach ($props as $prop) {
            $annons = $reader->getPropertyAnnotations($prop);
            if (isset($annons['Doctrine\ORM\Mapping\Column']->unique) && $annons['Doctrine\ORM\Mapping\Column']->unique) {
                $getMethod = 'get' . ucfirst($prop->getName());
                $uniqueParam = $this->getRootEntity()->$getMethod();
                //verificar e adicionar o erro
                $qb = $this->getRootRepository()->createQueryBuilder('u');
                $qb->select('u.id')
                   ->andWhere(
                       $qb->expr()->eq('u.' . $prop->getName(), ':param')
                   )
                   ->setParameter('param', $uniqueParam);

                $id = $this->getRootEntity()->getId();
                if (is_null($id)) {
                    $qb->andWhere(
                        $qb->expr()->isNotNull('u.id')
                    );
                } else {
                    $qb->andWhere(
                           $qb->expr()->neq('u.id', ':id')
                       )
                       ->setParameter('id', $id);
                }
                if (count($qb->getQuery()->getOneOrNullResult())) {
                    $attrName = $this->getJsonAttrTitle($this->getRootEntityName(), $prop->getName());
                    $this->addError('verificação', 'Já existe o valor informado para ' . $attrName . '.');
                }
            }
        }
    }

    /**
     * Utilizado para pegar o nome traduzido de um atributo a partir do jsonshcema de uma entidade
     * @param $entityName
     * @param $attr
     * @return mixed
     */
    public function getJsonAttrTitle($entityName, $attr)
    {
        $jsonSchema = $this->getJsonSchema($entityName);
        if (isset($jsonSchema['properties'][$attr]['title'])
            && !empty($jsonSchema['properties'][$attr]['title'])) {
            return $jsonSchema['properties'][$attr]['title'];
        }
        return $attr;
    }

    /**
     * Responsável por retornar o jsonschema em array de uma entidade
     * @param $entityName
     * @return mixed
     */
    public function getJsonSchema($entityName)
    {
        $dsp = DIRECTORY_SEPARATOR;
        $arr = explode('\\Entity\\', $entityName);
        $nsp = '@' . str_replace('\\', '', current($arr));

        $nsPath = $this->container->get('kernel')->locateResource($nsp);

        $jschFilePathArray = array(
            $nsPath,
            'Resources',
            'public',
            'schema',
            strtolower($arr[1]) . '.json',
        );
        $jschFilePath = implode($dsp, $jschFilePathArray);

        if (file_exists($jschFilePath)) {
            return json_decode(file_get_contents($jschFilePath), true);
        }
    }

    /**
     * Método que realmente processa a população, validação, verificação,
     * uploads e persistência (nesta exata sequência) da entidade raiz
     *
     * @param  ServiceDto $dto [description]
     * @throws ValidationException
     * @throws VerificationException
     * @throws HandleUploadsException
     * @return bool
     */
    public function flushRootEntity(ServiceDto $dto)
    {
        $this->setRootEntityForFlush($dto);
        $this->populateRootEntity($dto);

        $this->checkUnique($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\UniqueException($this->errors);
        }

        $this->validateRootEntity($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\ValidationException($this->errors);
        }

        $this->verifyRootEntity($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\VerificationException($this->errors);
        }

        $this->handleUploads($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\HandleUploadsException($this->errors);
        }

        $this->handleUploads($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\HandleUploadsException($this->errors);
        }

        try {
            if (!$this->debug) {
                // $this->persistEntity();

                $this->getEntityManager()->flush();
                // die;
            }
        } catch (\SanSIS\BizlayBundle\Entity\Exception\ValidationException $e) {
            $this->errors = array_merge($this->getRootEntity()->getErrors(), $this->errors);
            throw new \SanSIS\CrudBundle\Service\Exception\EntityException();
        }
    }

    // public function persistEntity($entity = null, $parent = null)
    // {
    //     if (is_null($entity)) {
    //         $entity = $this->getRootEntity();
    //     }
    //     $this->getEntityManager()->persist($entity);
    //     $methods = get_class_methods($entity);
    //     foreach ($methods as $method) {
    //         if ('get' === substr($method, 0, 3) && $method != 'getErrors') {
    //             $value = $entity->$method();

    //             if (\is_array($value) || $value instanceof ArrayCollection || $value instanceof PersistentCollection) {
    //                 foreach ($value as $key => $subvalue) {
    //                     if ($subvalue instanceof Entity && $parent != $subvalue) {
    //                         $this->persistEntity($subvalue, $entity);
    //                     }
    //                 }
    //             } else if ($value instanceof Entity && $parent != $value) {
    //                 // if (is_object($value)) {
    //                 //     echo get_class($value) . ' => ' . $method . '<br>';
    //                 // }
    //                 $this->persistEntity($value, $entity);
    //             }
    //         }
    //     }
    // }

    /**
     * Método para filtragem dos dados ainda na DTO (pré-processamento)
     * @example  Retirada de máscaras, ou qualquer limpeza que precise ser realizada
     *
     * @return ServiceDto - Objeto de dados processados (assim pode-se usar as regras de outra service)
     */
    public function preSave(ServiceDto $dto)
    {
    }

    /**
     * Método responsável por executar a sequência de pré-save, flush, e pós-save
     */
    public function save(ServiceDto $dto)
    {
        $this->preSave($dto);
        $this->flushRootEntity($dto);
        $this->postSave($dto);
    }

    /**
     * Método para execução de ações após a persistência do objeto raiz.
     * @example Disparar e-mails, criar arquivos de log, etc
     *
     * @return ServiceDto - Objeto de dados processados (assim pode-se usar as regras de outra service)
     */
    public function postSave(ServiceDto $dto)
    {
    }

    public function checkStatusTuple($entity)
    {
        return method_exists($entity, 'setStatusTuple') ? 'setStatusTuple' :
        method_exists($entity, 'setIsActive') ? 'setIsActive' :
        method_exists($entity, 'setFlActive') ? 'setFlActive' :
        false;
    }

    public function removeEntity($id)
    {

        $this->getRootEntity($id);
        $removeMethod = $this->checkStatusTuple($this->getRootEntity())
        if (!$removeMethod) {
            $this->getEntityManager()->remove($this->rootEntity);
        } else {
            if ($this->rootEntity->getStatusTuple() == 2) {
                throw new \Exception('Este registro não é removível!');
            }
            if ($removeMethod == 'setStatusTuple') {
                $this->rootEntity->$removeMethod(0);
            } else {
                $this->rootEntity->$removeMethod(false);
            }
        }
        $this->getEntityManager()->flush();

        return true;
    }

    public function filterSearchData(ServiceDto $dto)
    {
        if ($dto->query->has('searchData') || $dto->request->has('searchData')) {
            $keys = $dto->request->has('searchData') ? $dto->request->get('searchData') : $dto->query->get('searchData');
            if (is_array($keys)) {
                if (!isset($searchData)) {
                    $searchData = array();
                }
                foreach ($keys as $pair) {
                    $searchData[$pair['name']] = $pair['value'];
                }
            } else {
                $searchData['searchAll'] = $keys;
            }
        } else {
            $keys = $dto->request->all() ? $dto->request->all() : $dto->query->all();
            if (is_array($keys)) {
                $searchData = array();
                foreach ($keys as $k => $v) {
                    $searchData[$k] = $v;
                }
            }
        }

        $columns = $dto->request->has('columns') ? $dto->request->get('columns') : $dto->query->get('columns');

        $searchData['orderby'] = '';

        $order = $dto->request->has('order') ? $dto->request->get('order') : $dto->query->get('order');
        $and = '';
        if ($order) {
            foreach ($order as $k => $vals) {
                if (isset($columns[$vals['column']]) && $columns[$vals['column']]['data'] != 'g.acoes') {
                    $searchData['orderby'] .= $and . ($columns[$vals['column']]['name'] ? $columns[$vals['column']]['name'] : $columns[$vals['column']]['data']) . ' ' . $vals['dir'] . ' ';
                    $and = ', ';
                }
            }
        }

        return $searchData;
    }

    public function searchQuery(ServiceDto $dto)
    {
        $searchData = $this->filterSearchData($dto);

        return $this->getRootRepository()->getSearchQuery($searchData);
    }

    public function getAllSearchData(ServiceDto $dto)
    {
        $searchData = $this->filterSearchData($dto);

        return $this->getRootRepository()->getAllSearchData($searchData);
    }

    /**
     * Permite que outras entidades sejam consultadas para apresentação no grid de resposta
     *
     * @param integer $id
     * @return array
     */
    public function getSearchSubCells($id)
    {
        return array();
    }

    /**
     * Métodos para auxiliar a verificação da permissão de um usuário para determinada ação
     * Devem ser sobrescritos
     *
     * @param  [type] $id   [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserEditPermission($id, $item)
    {
        return true;
    }

    /**
     * @param  [type] $id   [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserViewPermission($id, $item)
    {
        return true;
    }

    /**
     * @param  [type] $id   [description]
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserDeletePermission($id, $item)
    {
        return true;
    }
}
