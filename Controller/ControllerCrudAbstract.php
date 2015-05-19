<?php
namespace SanSIS\CrudBundle\Controller;

use \Doctrine\ORM\Query;
use \Doctrine\ORM\Tools\Pagination\Paginator;
use \SanSIS\BizlayBundle\Controller\ControllerAbstract;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use \Symfony\Component\Config\Definition\Exception\Exception;
use \Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use \Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * Classe que implementa um controller padrão para CRUDs
 * @tutorial - Estenda e roteie as actions no routing do seu bundle.
 *           - Roteie apenas o que for utilizar
 *           - Evite rotear o delete nos casos onde não pode haver
 *             exclusão para evitar que a funcionalidade seja disparada
 *           - Nem sempre o CRUD atenderá de forma completa, sobrescreva
 *             qualquer método que julgar necessário
 *           - Caso precise de mais ações na coluna de ações, sobrescreva
 *             o método getGridActions.
 *
 * @author pablo.sanchez
 *
 */
abstract class ControllerCrudAbstract extends ControllerAbstract
{
    /**
     * Define a view padrão para o create
     *
     * @var string
     */
    protected $createView;

    /**
     * Permite a criação de títulos padronizados para as ações de CRUD
     *
     * @var unknown
     */
    protected $createFormAction = 'Criar';

    /**
     * Define o nome da rota para o create
     *
     * @var string
     */
    protected $createRoute;

    /**
     * Código de mensagem de erro da persistência de edição
     * @var string
     */
    protected $createErrorMsg;

    /**
     * Código de mensagem de sucesso da persistência de edição
     * @var string
     */
    protected $createSuccessMsg;

    /**
     * Define a view padrão para o edit
     *
     * @var string
     */
    protected $editView;

    /**
     * Permite a criação de títulos padronizados para as ações de CRUD
     *
     * @var unknown
     */
    protected $editFormAction = 'Editar';

    /**
     * Define o nome da rota para o edit
     *
     * @var string
     */
    protected $editRoute;

    /**
     * Código de mensagem de erro da persistência de edição
     * @var string
     */
    protected $editErrorMsg;

    /**
     * Código de mensagem de sucesso da persistência de edição
     * @var string
     */
    protected $editSuccessMsg;

    /**
     * Define a view padrão para o delete
     *
     * @var string
     */
    protected $deleteView;

    /**
     * Permite a criação de títulos padronizados para as ações de CRUD
     *
     * @var unknown
     */
    protected $deleteFormAction = 'Excluir';

    /**
     * Define o nome da rota para o delete
     *
     * @var string
     */
    protected $deleteRoute;

    /**
     * Define a view padrão para o view
     *
     * @var string
     */
    protected $viewView;

    /**
     * Permite a criação de títulos padronizados para as ações de CRUD
     *
     * @var unknown
     */
    protected $viewFormAction = 'Visualizar';

    /**
     * Define o nome da rota para o view
     *
     * @var string
     */
    protected $viewRoute;

    /**
     * Define a rota padrão para o save em caso de sucesso
     *
     * @var string
     */
    protected $saveSuccessRoute;

    /**
     * Action default da controller
     *
     * @Route("/")
     * @Template
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (method_exists($this->getService(), 'getFormData')) {
            $params = array(
                'formData' => $this->getService()->getFormData(),
            );
        } else {
            $params = array();
        }

        $params['csrf'] = $this->getToken();
        $params['formTitleAction'] = $this->indexFormAction;
        $params['searchRoute'] = $this->autoRoute('search');
        $params['exportExcelRoute'] = $this->autoRoute('exportexcel');
        $params['exportPdfRoute'] = $this->autoRoute('exportpdf');
        $params['createRoute'] = $this->autoRoute('create');
        $params['deleteRoute'] = $this->autoRoute('delete');

        if ($this->indexView) {
            return $this->render($this->indexView, $params);
        } else {
            return $params;
        }
    }

    /**
     * Realiza a pesquisa paginada
     * @return \StdClass
     */
    public function getGridData($searchQueryMethod = 'searchQuery', $prepareGridRowsMethod = 'prepareGridRows')
    {
        //Busca a query que será utilizada na pesquisa para a grid
        $query = $this->getService()->$searchQueryMethod($this->getDto());

        //pagina a ser retornada
        //quantidade de linhas a serem retornadas por página
        $rows = $this->getDto()->query->has('length') ? $this->getDto()->query->get('length') : $this->getDto()->request->get('length');
        $query->setFirstResult($this->getDto()->query->has('start') ? $this->getDto()->query->get('start') : $this->getDto()->request->get('start'))
              ->setMaxResults($rows);

        $pagination = new Paginator($query, true);

        //Objeto de resposta
        $data = new \StdClass();
        $data->draw = (int) $this->getDto()->query->has('draw') ? $this->getDto()->query->get('draw', 1) : $this->getDto()->request->get('draw', 1);
        $data->recordsFiltered = $pagination->count();
        $data->recordsTotal = $pagination->count();
        //linhas da resposta - o método abaixo pode (e provavelmente deve)
        //ser implantado de acordo com as necessidades da aplicação
        $data->data = $this->$prepareGridRowsMethod($pagination);

        return $data;
    }

    /**
     *  Prepara a resposta para o Grid processando cada uma das linhas retornadas
     *  e acrescentando automaticamente uma coluna de Ação
     *
     * @param \Doctrine\ORM\Tools\Pagination\Paginator  $pagination
     * @return array
     */
    public function prepareGridRows(\Doctrine\ORM\Tools\Pagination\Paginator $pagination)
    {
        $array = array();
        $id = null;

        foreach ($pagination as $k => $item) {
            //Obscurece os ids dos itens listados:
            $item = $this->obfuscateIds($item);
            //Cria um item no array de resposta
            $array[$k]['g'] = $item;
            $array[$k]['g']['acoes'] = $this->getGridActions($item['id'], $item);
        }

        return $array;
    }

    /**
     * Retorna o link para a ação de Edição no Grid
     *
     * @param integer $id
     * @return string
     */
    public function getEditGridAction($id, $item = null)
    {
        if ($this->autoRoute('edit')) {
            return '<a href="' . $this->generateUrl($this->editRoute, array('id' => $id)) . '" class="btn btn-default btn-sm m-r-xs" data-toggle="tooltip" data-placement="top" data-original-title="Editar"><i class="fa fa-pencil"></i></a>';
        } else {
            return '';
        }
    }

    /**
     * Retorna o link para a ação de Visualização no Grid
     *
     * @param integer $id
     * @param string $viewRoute - rota forçada - para relatórios (?!)
     * @return string
     */
    public function getViewGridAction($id, $item = null)
    {
        if ($this->autoRoute('view')) {
            return '<a href="' . $this->generateUrl($this->viewRoute, array('id' => $id)) . '" class="btn btn-default btn-sm m-r-xs" data-toggle="tooltip" data-placement="top" data-original-title="Visualizar"><i class="fa fa-file-text-o"></i></a>';
        } else {
            return '';
        }
    }

    /**
     * Retorna o link para a ação de Remoção no Grid
     *
     * @param integer $id
     * @return string
     */
    public function getDeleteGridAction($id, $item = null)
    {
        if ($this->autoRoute('delete')) {
            return '<a href="#" onclick="confirmarRemocao(\'' . $id . '\');" class="btn btn-danger btn-sm m-r-xs" data-toggle="tooltip" data-placement="top" data-original-title="Deletar"><i class="fa fa-trash-o"></i></a>';
        } else {
            return '';
        }

    }

    /**
     * Retorna os links que populam a coluna de ação no grid
     * - Pode ser sobrescrito para a inclusão de novas ações!!!
     *
     * @param integer $id
     * @return string
     */
    public function getGridActions($id, $item = null)
    {
        $statusTuple = null;
        $actions = '';
        if ($this->autoRoute('edit') || $this->autoRoute('delete') || $this->autoRoute('view')) {
            $actions .= $this->getViewGridAction($id, $item);
            $actions .= $this->getEditGridAction($id, $item);
            $actions .= $this->getDeleteGridAction($id, $item);
        }

        return $actions;
    }

    /**
     * Action que deve ser mapeada para realizar a pesquisa e popular uma grid
     *
     * @Route("/search")
     * @Method({"GET", "POST"})
     */
    public function searchAction()
    {
        $data = $this->getGridData();
        return $this->renderJson($data);
    }

    /**
     * Action que deve ser mapeada para criação de registros
     *
     * @Route("/create")
     * @Template
     */
    public function createAction()
    {
        if ($this->get('session')->has('SaveResult') && is_array($this->get('session')->get('SaveResult'))) {
            $entityData = $this->get('session')->get('SaveResult');
        } else {
            $entityData = $this->getService()->getNewRootEntityData();
        }

        if ($this->get('session')->has('SaveResult')) {
            $this->get('session')->remove('SaveResult');
        }

        $params = array(
            'csrf' => $this->getToken(),
            'formTitleAction' => $this->createFormAction,
            'formData' => $this->obfuscateIds($this->getService()->getFormData($entityData)),
            'indexRoute' => $this->autoRoute('index'),
            'saveRoute' => $this->autoRoute('save'),
            'entityData' => $this->obfuscateIds($entityData),
        );

        if ($this->createView) {
            return $this->render($this->createView, $params);
        } else {
            return $params;
        }
    }

    /**
     * Action que deve ser mapeada para edição de registros
     *
     * @Route("/edit/{id}")
     * @Template
     */
    public function editAction($id)
    {
        $id = $this->clarifyEntityId($id);

        if ($this->get('session')->has('SaveResult') && is_array($this->get('session')->get('SaveResult'))) {
            $entityData = $this->get('session')->get('SaveResult');
        } else {
            $entityData = $this->getService()->getRootEntityData($id);
        }

        if ($this->get('session')->has('SaveResult')) {
            $this->get('session')->remove('SaveResult');
        }

        $params = array(
            'csrf' => $this->getToken(),
            'formTitleAction' => $this->editFormAction,
            'indexRoute' => $this->autoRoute('index'),
            'saveRoute' => $this->autoRoute('save'),
            'formData' => $this->obfuscateIds($this->getService()->getFormData($entityData)),
            'entityData' => $this->obfuscateIds($entityData),
        );

        if ($this->editView) {
            return $this->render($this->editView, $params);
        } else {
            return $params;
        }
    }

    /**
     * Action que deve ser mapeada para visualização de registros
     *
     * @Route("/view/{id}")
     * @Template
     */
    public function viewAction($id)
    {
        $id = $this->clarifyEntityId($id);

        $entityData = $this->getService()->getRootEntityData($id);

        $params = array(
            'csrf' => null,
            'formTitleAction' => $this->viewFormAction,
            'formData' => $this->obfuscateIds($this->getService()->getFormData($entityData)),
            'indexRoute' => $this->autoRoute('index'),
            'editRoute' => $this->autoRoute('edit'),
            'saveRoute' => '',
            'entityData' => $this->obfuscateIds($entityData),
        );

        if ($this->viewView) {
            return $this->render($this->viewView, $params);
        } else {
            return $params;
        }
    }

    /**
     * Sobrescreva caso precise definir um redirecionamento diferente após salvar
     *
     * @return [type] [description]
     */
    public function saveSuccessConditional()
    {
        if ($this->autoSaveSuccessRoute() == $this->editRoute) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Obtém o id do rootEntity que acaba de ser salvo (útil para redirect)
     * @return [type] [description]
     */
    public function getSaveSuccessId()
    {
        return $this->obfuscateIds($this->getService()->getRootEntityId());
    }

    /**
     * Definição padrão do redirect após persistência
     * Sobrescreva caso precise quaisquer parâmetros adicionais
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectAfterSave()
    {
        if ($this->saveSuccessConditional()) {
            $id = $this->obfuscateEntityId($this->getSaveSuccessId());
            return $this->redirectByRouteName($this->autoSaveSuccessRoute(), 302, array('id' => $id));
        } else {
            return $this->redirectByRouteName($this->autoSaveSuccessRoute());
        }
    }

    /**
     * Action que deve ser mapeada para salvar os registros no banco de dados
     *
     * @Route("/save")
     * @Method({"POST"})
     */
    public function saveAction()
    {
        try {
            if (!$this->getDto()->request->has('__csrf')) {
                throw new TokenNotFoundException();
            } else if (!$this->get('form.csrf_provider')->isCsrfTokenValid('validateForm', $this->getDto()->request->get('__csrf'))) {
                throw new InvalidCsrfTokenException();
            }
            $this->getService()->save($this->getDto());

            if ($this->editSuccessMsg && $this->getDto()->request->get('id')) {
                $this->get('core.message')->addMessage('sucesso', $this->editSuccessMsg);
            } else if ($this->createSuccessMsg) {
                $this->get('core.message')->addMessage('sucesso', $this->createSuccessMsg);
            } else {
                $this->get('core.message')->addMessage('sucesso', 'MS000');
            }
            $this->get('session')->set('SaveResult', true);
            return $this->redirectAfterSave();
        } catch (\Exception $e) {
            $this->get('session')->set('SaveResult', $this->getService()->getRootEntityData());

            if ($this->editErrorMsg && $this->getDto()->request->get('id')) {
                $this->get('core.message')->addMessage('erro', $this->editErrorMsg);
            } else if ($this->createErrorMsg) {
                $this->get('core.message')->addMessage('erro', $this->createErrorMsg);
            } else {
                $this->get('core.message')->addMessage('erro', 'ME000');
            }

            if ($this->getService()->hasErrors()) {
                foreach ($this->getService()->getErrors() as $error) {
                    $this->get('core.message')->addMessage('erro', $error['message']);
                }
            }

            if (in_array($this->get('kernel')->getEnvironment(), array('dev'))) {
                $this->get('core.message')->addMessage(
                    'erro',
                    get_class($e) . "\n" .
                    $e->getMessage() . "\n" .
                    $e->getLine() . "\n" .
                    $e->getCode() . "\n" .
                    $e->getTraceAsString() . "\n"
                );
            }

            return $this->redirectByReferer(302);
        }
    }

    /**
     * Action que deve ser mapeada para excluir os registros do banco de dados
     *
     * @Route("/delete")
     * @Method({"POST"})
     */
    public function deleteAction()
    {
        if (!$this->getDto()->request->has('__csrf')) {
            throw new TokenNotFoundException();
        } else if (!$this->get('form.csrf_provider')->isCsrfTokenValid('validateForm', $this->getDto()->request->get('__csrf'))) {
            throw new InvalidCsrfTokenException();
        }

        $id = $this->getDto()->request->get('id');

        if ($id) {
            $this->getService()->removeEntity($id);
            $this->get('core.message')->addMessage('sucesso', 'MS000');
            return $this->redirectByRouteName($this->autoSaveSuccessRoute());
        }
    }

    public function autoSaveSuccessRoute()
    {
        if (is_array($this->saveSuccessRoute)) {
            $refRouteInfo = $this->getRefererRoute();
            if (isset($this->saveSuccessRoute[$refRouteInfo[0]]) && $this->saveSuccessRoute[$refRouteInfo[0]]) {
                return $this->saveSuccessRoute[$refRouteInfo[0]];
            }
            $this->saveSuccessRoute = null;
        }
        if (!$this->saveSuccessRoute) {
            $refRouteInfo = $this->getRefererRoute();
            $this->saveSuccessRoute = str_replace(array('_create', '_edit'), '_index', $refRouteInfo[0]);
        }

        return $this->saveSuccessRoute;
    }

    /**
     * Exporta os dados de uma grid com a pesquisa para um Excel
     *
     * @Route("/export_excel")
     */
    public function exportExcelAction()
    {
        //Obtém lista completa da service
        $arr = $this->getService()->getAllSearchData($this->getDto());
        $this->renderExcel($arr);
    }

    /**
     * Exporta os dados de uma grid com a pesquisa para um PDF
     *
     * @Route("/export_pdf")
     */
    public function exportPdfAction()
    {
        //Obtém lista completa da service
        $arr = $this->getService()->getAllSearchData($this->getDto());
        return $this->renderPdf($arr);
    }
}
