<?php
namespace SanSIS\CrudBundle\Controller;

use \Doctrine\ORM\Query;
use \Doctrine\ORM\Tools\Pagination\Paginator;
use \SanSIS\BizlayBundle\Controller\ControllerAbstract;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\Config\Definition\Exception\Exception;

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
abstract class ControllerRestCrudAbstract extends ControllerAbstract
{

    /**
     * Action default da controller
     *
     * @Route("/")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->renderJson(array());
    }

    /**
     * Action que deve ser mapeada para edição de registros
     *
     * @Route("/{id}")
     */
    public function getEntityDataAction($id)
    {
        $id = $this->clarifyEntityId($id);

        $entityData = $this->getService()->getRootEntityData($id);

        return $this->renderJson($this->obfuscateIds($entityData));
    }

    /**
     * Realiza a pesquisa paginada
     * @return \StdClass
     */
    public function getGridData($searchQueryMethod = 'searchQuery', $prepareGridRowsMethod = 'prepareGridRows')
    {
        // Busca a query que será utilizada na pesquisa para a grid
        $query = $this->getService()->$searchQueryMethod($this->getDto());

        // pagina a ser retornada
        // quantidade de linhas a serem retornadas por página
        $rows = $this->getDto()->query->has('length') ? $this->getDto()->query->get('length') : $this->getDto()->request->get('length');
        $query->setFirstResult($this->getDto()->query->has('start') ? $this->getDto()->query->get('start') : $this->getDto()->request->get('start'))
              ->setMaxResults($rows);

        $pagination = new Paginator($query, true);

        // Objeto de resposta
        $data = new \StdClass();
        $data->draw = (int) $this->getDto()->query->has('draw') ? $this->getDto()->query->get('draw', 1) : $this->getDto()->request->get('draw', 1);
        $data->recordsFiltered = $pagination->count();
        $data->recordsTotal = $pagination->count();
        // linhas da resposta - o método abaixo pode (e provavelmente deve)
        // ser implantado de acordo com as necessidades da aplicação
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
            // Obscurece os ids dos itens listados:
            $item = $this->obfuscateIds($item);
            // Cria um item no array de resposta
            $array[$k]['g'] = $item;
        }

        return $array;
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
     * Obtém o id do rootEntity que acaba de ser salvo (útil para redirect)
     * @return [type] [description]
     */
    public function getSavedId()
    {
        return $this->obfuscateIds($this->getService()
                                            ->getRootEntityId());
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
            $this->getService()->save($this->getDto());
            return $this->renderJson($this->getSavedId());
        } catch (\Exception $e) {
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
        $id = $this->getDto()->request->get('id');

        if ($id) {
            $this->getService()->removeEntity($id);
            return $this->renderJson();
        }
    }
}
