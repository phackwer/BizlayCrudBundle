<?php
namespace SanSIS\CrudBundle\Controller;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use \Doctrine\ORM\Query;
use \Doctrine\ORM\Tools\Pagination\Paginator;
use \FOS\RestBundle\Controller\Annotations as Rest;
use \SanSIS\BizlayBundle\Controller\ControllerAbstract;

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
     * Action que deve ser mapeada para edição de registros
     * @Rest\Get("/{id}", requirements={"id" = "\d+"})
     */
    public function getAction($id)
    {
        // $id = $this->clarifyEntityId($id);

        $entityData = $this->getService()->getRootEntityData($id);

        // $entityData = $this->obfuscateIds($entityData);

        return $this->renderJson($entityData);
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
        $rows = $this->getDto()->query->has('length') ? $this->getDto()->query->get('length', 20) : $this->getDto()->request->get('length', 20);
        $query->setFirstResult($this->getDto()->query->has('start') ? $this->getDto()->query->get('start') : $this->getDto()->request->get('start'))
              ->setMaxResults($rows);

        $pagination = new Paginator($query, true);

        // Objeto de resposta
        $data = new \StdClass();
        $data->draw = (int) $this->getDto()->query->has('draw') ? $this->getDto()->query->get('draw', 1) : $this->getDto()->request->get('draw', 1);
        $data->recordsFiltered = $pagination->count();
        $data->recordsTotal = $pagination->count();
        $data->pageLength = $rows;
        $data->pageCount = ceil($pagination->count() / $rows);

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
            // $item = $this->obfuscateIds($item);
            // Cria um item no array de resposta
            $array[$k]['g'] = $item;
        }

        return $array;
    }

    /**
     * Action que deve ser mapeada para realizar a pesquisa e popular uma grid
     *
     */
    public function getSearchAction()
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
        $id = $this->getService()->getRootEntityId();
        // $id =  $this->obfuscateIds($id);
        return $id;
    }

    /**
     * Action que deve ser mapeada para salvar os registros no banco de dados
     *
     * api_nomedacontroller_post_save
     * /(nome_da_controller)/save.{_format}
     */
    public function postSaveAction()
    {
        $this->getService()->save($this->getDto());
        return $this->renderJson($this->getSavedId());
    }

    /**
     * Action que deve ser mapeada para excluir os registros do banco de dados
     * @Rest\Delete("/{id}", requirements={"id" = "\d+"})
     */
    public function deleteAction($id = null)
    {
        if ($id) {
            $this->getService()->removeEntity($id);
            return $this->renderJson();
        }
        throw new BadRequestHttpException();
    }
}
