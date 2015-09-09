<?php
namespace SanSIS\CrudBundle\Service;

use Doctrine\Common\Inflector\Inflector;
use \Doctrine\ORM\Query;
use \SanSIS\BizlayBundle\Entity\AbstractEntity as Entity;
use \SanSIS\BizlayBundle\Service\AbstractService;
use \SanSIS\BizlayBundle\Service\ServiceDto;
use \SanSIS\CrudBundle\Service\Exception\ValidationException;
use \SanSIS\CrudBundle\Service\Exception\WrongTypeRootEntityException;

/**
 * Class AbstractEntityService
 *
 * Service genérica para lidar com entidades doctrine
 * Automatiza e padroniza o comportamento e código de
 * quaisquer cruds necessários.
 *
 * @package SanSIS\CrudBundle\Service
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
//        \Doctrine\Common\Util\Debug::dump($this->getRootEntity($id));
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
     * @TODO 0 - Substituir todas as análises de strings possíveis por reflexion e annotation
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
            }
        } catch (\Exception $e) {
            throw $e;
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
            throw $e;
        }
    }

    public function getEntityByIdentifierValue(&$newClass, &$values)
    {
        $this->log('info', 'Obtendo as chaves primárias para ' . $newClass);
        //Colocar a busca da PK no Metadata da entidade
        $metadata = $this->getEntityManager()->getClassMetadata($newClass);
        //alterar verificação da verdade do id, e então processar o load da entidade
        $getIdent = $metadata->getIdentifier();
        $identifier = isset($getIdent[0]) ? $getIdent[0] : 'id';

        $idValue = null;
        if (isset($values[$this->toCamelCase($identifier)]) && trim($values[$this->toCamelCase($identifier)]) != '') {
            $idValue = $values[$this->toCamelCase($identifier)];

        } else if (isset($values[$this->toSnakeCase($identifier)]) && trim($values[$this->toSnakeCase($identifier)]) != '') {
            $idValue = $values[$this->toSnakeCase($identifier)];
        }

//        if ($identifier == 'co_upload_file') {
        //            echo $identifier."\n";
        //            echo $this->toCamelCase($identifier)."\n";
        //            var_dump($values);
        //            echo $idValue;
        //            die (1234);
        //        }

        /**
         * Checa se entidade existe no banco de dados
         */
        if ($idValue) {
            $entity = $this->getEntityManager()->getRepository($newClass)->findOneBy(array($identifier => $idValue));
            if (!$entity) {
                $this->log('error', 'Entidade inexistente no banco de dados : ' . $newClass . '::' . $identifier . ' = ' . $idValue);
                echo '<pre>';
                echo ">>>>>Este erro jamais deve acontecer. Revise o mapeamento<<<<\n";
                echo '>>>>>Entidade não existente no banco de dados!!!!' . $entity . "<<<<\n";
                echo '>>>>>' . $newClass . "<<<<\n";
                print_r($values);
                print_r($_POST);
                die('Este erro jamais deve acontecer. Revise o mapeamento');
            };
            if (method_exists($entity, 'setTerm')) {
                $this->log('info', 'Entidade de tabela de apoio : ' . $newClass . '::' . $identifier . ' = ' . $idValue);
                return $entity;
            }
        }
        /**
         * Cria uma nova se não foi passado um id
         */
        else {
            $this->log('info', 'Criando subentidade para ser populada: ' . $newClass);
            $newClass = str_replace("\r", '', $newClass);
            $entity = new $newClass();
        }
        return $entity;
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
        /**
         * Processa a exclusão de itens vinculados ao root entity
         * @TODO 1 - Converter para método
         */
        if ($parent && isset($values['idDel']) && $values['idDel']) {
            $this->log('info', 'Marcando subentidade para exclusão : ' . $newClass);
            $entity = $this->getEntityManager()->getRepository($newClass)->findOneBy(array('id' => $values['idDel']));
            if ($entity) {
                $this->setEntityForRemoval($entity);
            }
            return null;
        }
        /****** fim @TODO 1 ****/

        /**
         * Assume que está processando a rootEntity
         */
        if (!$parent) {
            $entity = $newClass;
            $setParentMethod = null;
        }
        /**
         * Processa os atributos da entidade
         * @TODO 2 - Converter em método
         */
        else {
            /**
             * @ TODO 3 - Transformar em método
             */
            $entity = $this->getEntityByIdentifierValue($newClass, $values);
            /****** fim @TODO 3 ****/

            /**
             * @TODO 4 - Corrigir a associação com parent para ser mais transparente
             * Criar um método que retorne, na instância atual, o método que tem como
             * parâmetro o objeto pai!
             * @var [type]
             */
            $class = explode('\\', get_class($parent));

            $class = $class[count($class) - 1];

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

            /**
             * Lord of Earth and Heaven, forgive this horse
             */
            $setParentMethod = '';
            $methods = $ref->getMethods();
            foreach ($methods as $method) {
                $param = current($ref->getMethod($method->name)->getParameters());
                if (is_object($param)) {
                    if (is_object($param->getClass())) {
                        $parClass = $param->getClass()->name;
                        if ($parClass) {
                            $parClass = explode('\\', $parClass);
                            $parClass = $parClass[count($parClass) - 1];
                            if ($parClass == $class) {
                                $setParentMethod = $method->name;
                            }
                        }
                    }
                }
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
            /****** fim @TODO 4 ****/
        }
        /****** fim @TODO 1 ****/

        /**
         * @TODO 5 - Converter em método de checagem de existência de classe - class_exists
         */
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
        /****** fim @TODO 5 ****/

        /**
         * Corrige o retorno de uma classe de proxy que impossibilita a leitura dos comentários corretos da classe
         * @TODO 6 - Converter em método fixProxy
         */
        $this->log('info', 'Entidade Proxy da Doctrine - contornando erro de Reflection para obter a entidade correta do modelo.');
        if (strstr($ref, "DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR")) {
            $bpos = strpos($ref, 'extends ') + 8;
            $epos = strpos($ref, ' implements') - $bpos;
            $correctClass = substr($ref, $bpos, $epos);
            $ref = new \ReflectionClass($correctClass);
        }
        /****** fim @TODO 6 ****/

        $methods = get_class_methods($entity);

        /**
         * @TODO 7 - Converter em método populateFromArray
         */
        $this->log('info', 'Populando entidade.');
        foreach ($methods as $method) {
            if ((('set' === substr($method, 0, 3) && strlen($method) > 3)) && $method != $setParentMethod) {
                /**
                 * @TODO 8 - Converter em método, checagem dos dados inseridos contra métodos existentes
                 */
                if ($this->debug) {
                    $this->log('info', 'Populando entidade => ' . get_class($entity) . '::' . $method . ' com os dados ' . var_export($values, true));
                }
                $attr = lcfirst(substr($method, 3));
                $snat = $this->toSnakeCase($attr); //ARGH!!!!! Xys!!!!
                try {
                    if (is_array($values)) {
                        $attr = isset($values[$attr]) ? $attr : $snat; //ARGH!!!!! Xys!!!!
                        if (isset($values[$attr])) {
                            $value = $values[$attr];
                        } else {
                            $value = null;
                            continue;
                        }
                    } else if ($values instanceof \SanSIS\BizlayBundle\Service\ServiceDto) {

                        //ARGH!!!!! Xys!!!!
                        if ($values->query->has($snat)) {
                            $attr = $snat;
                        } else if ($values->request->has($snat)) {
                            $attr = $snat;
                        }
                        //FIMDARGH!

                        if ($values->query->has($attr)) {
                            $value = $values->query->get($attr);
                        } else if ($values->request->has($attr)) {
                            $value = $values->request->get($attr);
                        } else {
                            continue;
                        }
                    } else if (is_null($values)) {
                        $this->log('info', 'Populando entidade => Não há dados para popular a entidade!' . var_export($values, true));
                        return null;
                    }
                } catch (\Exception $e) {
                    $this->log('error', 'Houve algum erro ao tentar lidar com os dados submetidos ' . var_export($values, true));
                    throw $e;
                }

                if (!is_array($value) && !is_object($value) && $value) {
                    $value = trim($value);
                }

                if ($this->debug) {
                    $this->log('info', 'Populando entidade => ' . get_class($entity) . '::' . $method . ' com os dados ' . var_export($value, true));
                }

                $params = $ref->getMethod($method)->getParameters();
                $strDoc = $ref->getMethod($method)->getDocComment();
                try {
                    $strAttr = $ref->getProperty($attr)->getDocComment();
                } catch (\Exception $e) {
                    try {
                        $strAttr = $ref->getProperty($this->toCamelCase($attr))->getDocComment();
                    } catch (\Exception $e) {

                        try {
                            $strAttr = $ref->getProperty($this->toSnakeCase($attr))->getDocComment();
                        } catch (\Exception $e) {
                            throw new \Exception('É, amigo desenvolvedor. Se você chegou a receber esta exception, por favor,
                            corrija seu modelo. Classe e atributo com problemas. ' . get_class($entity) . '->' . $attr);
                        }
                    }
                }
                $class = '';

                if ($params[0]->getClass()) {
                    $this->log('info', get_class($entity) . '::' . $method . '(' . $params[0]->getClass() . ')');
                    $class = $params[0]->getClass()->getName();

//                    $bpos = strpos($strDoc, '@param ') + 7;
                    //                    $epos = strpos($strDoc, ' $') - $bpos;
                    //                    $class = substr($strDoc, $bpos, $epos);
                }

                if (strstr($strDoc, '\DateTime') || $class == 'DateTime') {
                    $class = '\DateTime';

                    $this->log('info', 'Tratando data para o formato do banco');

                    if ($value) {
                        if (!$value instanceof \DateTime) {
                            if (is_array($value)) {
                                if (isset($value['date'])) {
                                    $value = $value['date'];
                                }
                            }
                            //Melhorar isto depois, pelamordedeus
                            if (strstr($value, 'T')) {
                                $value = explode('T', $value);
                                if (strstr($value[1], '.')) {
                                    $time = explode('.', $value[1]);
                                } else {
                                    $time = explode('-', $value[1]);
                                }
                                $value = $value[0] . ' ' . $time[0];
                            }
                            if (strstr($value, '/')) {
                                if (strstr($value, ':')) {
                                    if (strstr($value, '.')) {
                                        $value = $class::createFromFormat('d/m/Y H:i:s.u', $value);
                                    } else {
                                        $value = $class::createFromFormat('d/m/Y H:i:s', $value);
                                    }
                                } else {
                                    $value = $class::createFromFormat('d/m/Y', $value);
                                }
                            } else {
                                if (strstr($value, ':')) {
                                    if (strstr($value, '.')) {
                                        $value = $class::createFromFormat('Y-m-d H:i:s.u', $value);
                                    } else {
                                        $value = $class::createFromFormat('Y-m-d H:i:s', $value);
                                    }
                                } else {
                                    $value = $class::createFromFormat('Y-m-d', $value);
                                }
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
                    if (!method_exists($entity, $method)) {
                        $method = Inflector::singularize($method);
                    }
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
                    if (isset($value[$identifier]) || isset($value['idDel'])) {
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

                    //Colocar a busca da PK no Metadata da entidade
                    $metadata = $this->getEntityManager()->getClassMetadata($class);
                    //alterar verificação da verdade do id, e então processar o load da entidade
                    $getIdent = $metadata->getIdentifier();
                    $classId = isset($getIdent[0]) ? $getIdent[0] : 'id';

                    $this->log('info', 'Populando ManyToOne');
                    if (is_array($value) && array_key_exists($classId, $value)) {
                        if (isset($value[$classId]) && !is_null($value[$classId]) && trim($value[$classId]) !== '') {
                            $value = $this->getEntityManager()->getRepository($class)->findOneBy(array($classId => $value[$classId]));
                        } else {
                            $value = null;
                        }

                    } else {
                        if (!is_null($value) && !empty($value)) {
                            $value = $this->getEntityManager()->getRepository($class)->findOneBy(array($classId => $value));
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
        /****** fim @TODO 7 ****/

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
        $this->getRootRepository()->checkUnique($dto, $this->getRootEntity());
        if ($this->getRootRepository()->hasErrors()) {
            foreach ($this->getRootRepository()->getErrors() as $error) {
                $error['message'] = str_replace($error['attr'], $this->getJsonAttrTitle($this->getRootEntityName(), $error['attr']), $error['message']);
                $this->errors[] = $error;
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
     * @TODO - Filtragem de white list para atributos que podem ser acessados pela Business
     *
     * @param  [type] $dto [description]
     * @return [type]      [description]
     */
    public function filterWhiteList($dto)
    {
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
        $this->filterWhiteList($dto);
        $this->populateRootEntity($dto);

        /**
         * Verifica as annotations da entidade para checar uniques
         */
        $this->checkUnique($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\UniqueException($this->errors);
        }

        /**
         * Valida os dados da entidade
         */
        $this->validateRootEntity($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\ValidationException($this->errors);
        }

        /**
         * Verifica a validade dos dados da entidade com relação a recursos externos à Service
         */
        $this->verifyRootEntity($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\VerificationException($this->errors);
        }

        /**
         * Cuida dos uploads recebidos
         */
        $this->handleUploads($dto);
        if ($this->hasErrors()) {
            throw new \SanSIS\CrudBundle\Service\Exception\HandleUploadsException($this->errors);
        }

        /**
         * Últimos ajustes antes de persistir
         */
        $this->preFlush($dto);

        try {
            if (!$this->debug) {
                // $this->persistEntity();
                $this->getEntityManager()->flush();
            }
        } catch (\Exception $e) {
            $this->errors = array_merge($this->getRootEntity()->getErrors(), $this->errors);
            if (count($this->errors)) {
                throw new \SanSIS\CrudBundle\Service\Exception\EntityException($this->errors);
            }
            throw $e;
        }
    }

    /**
     * Método para lidar com toda e qualquer situação XGH que não se adeque nas anteriores
     *
     * @example  Entidades que não são acessíveis pela estrutura da RootEntity
     */
    public function preFlush(ServiceDto $dto)
    {
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
     *
     * @example  Retirada de máscaras, ou qualquer limpeza que precise ser realizada
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
        if ($this->debug) {
            throw new \Exception("Depuração ativada: processamento finalizado", 1);
        }
    }

    /**
     * Método para execução de ações após a persistência do objeto raiz.
     *
     * @example Disparar e-mails, criar arquivos de log, etc
     */
    public function postSave(ServiceDto $dto)
    {
    }

    public function getRemoveMethod($entity)
    {
        return
        method_exists($entity, 'setStatusTuple') ? 'setStatusTuple' : (
            method_exists($entity, 'setIsActive') ? 'setIsActive' : (
                method_exists($entity, 'setFlActive') ? 'setFlActive' : false
            )
        );
    }

    public function setEntityForRemoval($entity)
    {
        $removeMethod = $this->getRemoveMethod($entity);
        if (!$removeMethod) {
            $this->getEntityManager()->remove($entity);
        } else {
            if ($removeMethod == 'setStatusTuple') {
                if ($entity->getStatusTuple() == 2) {
                    throw new \Exception('Este registro não é removível!');
                }
                $entity->$removeMethod(0);
            } else {
                $entity->$removeMethod(false);
            }
            $this->getEntityManager()->persist($entity);
        }
    }

    public function removeEntity($id)
    {
        $entity = $this->getRootEntity($id);
        $this->setEntityForRemoval($entity);
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

        if ($columns) {
            $order = $dto->request->has('order') ? $dto->request->get('order') : $dto->query->get('order');
            $searchData['orderBy'] = '';
            $searchData['sortOrder'] = '';
            $and = '';
            if ($order) {
                foreach ($order as $k => $vals) {
                    if (isset($columns[$vals['column']]) && $columns[$vals['column']]['data'] != 'g.acoes') {
                        $searchData['orderBy'] .= $and . ($columns[$vals['column']]['name'] ? $columns[$vals['column']]['name'] : $columns[$vals['column']]['data']);
                        $searchData['sortOrder'] = $vals['dir'];
                        $and = ', ';
                    }
                }
            }
        } else {
            $searchData['orderBy'] = $dto->request->has('orderBy') ? $dto->request->get('orderBy') : $dto->query->get('orderBy');
            $searchData['sortOrder'] = $dto->request->has('sortOrder') ? $dto->request->get('sortOrder') : $dto->query->get('sortOrder');
        }

        if (isset($searchData['rows'])) {
            unset($searchData['rows']);
        }
        if (isset($searchData['page'])) {
            unset($searchData['page']);
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

    public function getAllObjSearchData(ServiceDto $dto)
    {
        $searchData = $this->filterSearchData($dto);

        return $this->getRootRepository()->getAllObjSearchData($searchData);
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
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserEditPermission($item)
    {
        return true;
    }

    /**
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserViewPermission($item)
    {
        return true;
    }

    /**
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function checkUserDeletePermission($item)
    {
        return true;
    }
}
