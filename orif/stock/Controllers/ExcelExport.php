<?php

namespace Stock\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use Stock\Models\Entity_model;
use Stock\Models\Item_group_model;
use Stock\Models\Item_model;
use Stock\Models\Item_tag_link_model;
use Stock\Models\Item_tag_model;
use Stock\Models\Stocking_place_model;
use Stock\Models\Supplier_model;
use Stock\Models\User_entity_model;
use CodeIgniter\Database\BaseConnection;

class ExcelExport extends \App\Controllers\BaseController
{
    private Entity_model $entity_model;
    private Item_group_model $item_group_model;
    private Stocking_place_model $stocking_place_model;
    private Item_tag_link_model $item_tag_link_model;
    private Item_tag_model $item_tag_model;
    private User_entity_model $user_entity_model;
    private Supplier_model $supplier_model;
    private Item_model $item_model;
    private BaseConnection $db;

    /**
     * Constructor
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        $this->entity_model = new Entity_model();
        $this->item_group_model = new Item_group_model();
        $this->stocking_place_model = new Stocking_place_model();
        $this->item_tag_link_model = new Item_tag_link_model();
        $this->item_tag_model = new Item_tag_model();
        $this->user_entity_model = new User_entity_model();
        $this->supplier_model = new Supplier_model();
        $this->item_model = new Item_model();

        $this->db = \Config\Database::connect();

        $this->access_level=config('\Stock\Config\StockConfig')->access_lvl_manager;
        parent::initController($request, $response, $logger); // TODO: Change the autogenerated stub
    }

    public function index() 
    {
        //make exportation
        if (count($_POST) > 0) {
            $items=[];
            //filter by entity
            if ($this->request->getPost('id_entity') != null) {
                $entity_id = $this->request->getPost('id_entity');
                $stock_places = $this->stocking_place_model->where('fk_entity_id', $entity_id)->findAll();

                foreach ($stock_places as $stock_place) {
                        $items=array_merge($items, $this->item_model->where('stocking_place_id', $stock_place['stocking_place_id'])->findAll());
                }

                //remove duplicated elements
                $items = array_values(array_unique(($items), SORT_REGULAR));
            } else if ($this->request->getPost('group_id') != null) {
                //filter by group id
                $group_id = $this->request->getPost('group_id');
                $items = $this->item_model->where('item_group_id', $group_id)->findAll();
            }

            //prepare items to add in excel sheet
            foreach ($items as $idx => $item) {
                (($this->stocking_place_model->find($item['stocking_place_id']))['fk_entity_id']) == null ? $item['entity_name'] = '' : ($item['entity_name'] = $this->entity_model->find($this->stocking_place_model->find($item['stocking_place_id'])['fk_entity_id']) != null ? $this->entity_model->find($this->stocking_place_model->find($item['stocking_place_id'])['fk_entity_id'])['name'] : '');
                $item['stock_place'] = $this->stocking_place_model->find($item['stocking_place_id'])['name'];
                $tag_ids = $this->item_tag_link_model->where('item_id', $item['item_id'])->findColumn('item_tag_id');
                is_array($tag_ids) ? $item['tags'] = $this->item_tag_model->whereIn('item_tag_id', $tag_ids)->findColumn('name') : $item['tags'] = [];
                $item['tags'] = (is_array($item['tags'])?implode(';',$item['tags']):'');
                isset($item['item_group_id']) ? $item['group_name'] = $this->item_group_model->find($item['item_group_id'])['name'] : $item['group_name'] = '';
                $supplier = null;
                !isset($item['supplier_id']) ? : $supplier = $this->supplier_model->find($item['supplier_id']);
                if ($supplier != null) {
                    $supplier=[
                        $supplier['name'], 
                        $supplier['address_line1'], 
                        $supplier['address_line2'], 
                        $supplier['zip'], 
                        $supplier['city'], 
                        $supplier['country'], 
                        $supplier['tel'], 
                        $supplier['email']
                    ];
                    $supplier = array_filter($supplier);
                }

                $item['supplier'] = $supplier == null ? '' : implode("\n", $supplier);
                $items[$idx] = $item;
            }

            $spreadsheet = new Spreadsheet();
            Cell::setValueBinder(new AdvancedValueBinder());
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Site');
            $sheet->setCellValue('B1', 'Place de stockage');
            $sheet->setCellValue('C1', 'Type d\'objet');
            $sheet->setCellValue('D1', 'Nom de l\'objet');
            $sheet->setCellValue('E1', 'Groupe');
            $sheet->setCellValue('F1', 'Date acquisition');
            $sheet->setCellValue('G1', 'Prix unitaire');
            $sheet->setCellValue('H1', 'Fournisseur');
            $cellIt=$sheet->getRowIterator(1)->current()->getCellIterator("A", "H");

            foreach ($cellIt as $value){
                ($value->getAppliedStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00B0FF'));
                $value->getAppliedStyle()->getFont()->getColor()->setRGB('FFFFFF');
            }

            foreach ($items as $idx => $item) {
                $sheet->getStyle('H' . strval($idx+2))->getAlignment()->setWrapText(true);
                $sheet->setCellValue('A'.strval($idx+2), $item['entity_name']);
                $sheet->setCellValue('B'.strval($idx+2), $item['stock_place']);
                $sheet->setCellValue('C'.strval($idx+2), $item['tags']);
                $sheet->setCellValue('D'.strval($idx+2), $item['name']);
                $sheet->setCellValue('E'.strval($idx+2), $item['group_name']);
                $sheet->setCellValue('F'.strval($idx+2), $item['buying_date']);
                $sheet->setCellValue('G'.strval($idx+2), $item['buying_price'] . 'chf');
                $sheet->setCellValue('H'.strval($idx+2), $item['supplier']);
            }

            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->getColumnDimension('E')->setAutoSize(true);
            $sheet->getColumnDimension('F')->setAutoSize(true);
            $sheet->getColumnDimension('G')->setAutoSize(true);
            $sheet->getColumnDimension('H')->setAutoSize(true);

            $writer= new Xlsx($spreadsheet);
            $fileTag = "";

            while(strlen($fileTag) < 10) {
                $fileTag .= strval(rand(0, 9));
            }

            $writer->save(ROOTPATH . 'public/' . $fileTag . '.xlsx');
            $excelFile=fopen(ROOTPATH . 'public/' . $fileTag . '.xlsx', 'r+');
            $excelDatas=fread($excelFile,filesize(ROOTPATH . 'public/' . $fileTag.'.xlsx'));
            $excelDatas=base64_encode($excelDatas);
            unlink(ROOTPATH . 'public/' . $fileTag . '.xlsx');

            return $this->response->setStatusCode(201)->setContentType('application/json')->setBody(json_encode(['excel_datas' => $excelDatas]));
        }

        $datas['entities'] = null;
        $datas['item_groups'] = null;

        if (isset($_SESSION['user_access']) && $_SESSION['user_access'] > config('\Stock\Config\StockConfig')->access_lvl_manager) {
            $datas['entities'] = $this->entity_model->findAll();
            $datas['item_groups'] = $this->item_group_model->findAll();

            if (count($datas['entities']) > 0) {
                $datas['filters'][] = ['name' => lang('stock_lang.entity_name'),'value' => 1];
            }

            if (count($datas['item_groups']) > 0) {
                $datas['filters'][] = ['name' => lang('stock_lang.btn_item_groups'),'value' => 2];
            }
        } elseif(isset($_SESSION['user_id'])) {
            if ($this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->countAllResults() > 0) {
                $datas['filters'][] = ['name' => lang('stock_lang.entity_name'),'value' => 1];
                $datas['filters'][] = ['name' => lang('stock_lang.btn_item_groups'),'value' => 2];
                $datas['entities'] = $this->entity_model->whereIn('entity_id', $this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->findColumn('fk_entity_id'))->findAll();
                $datas['item_groups'] = $this->item_group_model->whereIn('fk_entity_id', $this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->findColumn('fk_entity_id'))->findAll();
            }
        } else {
            return;
        }

        return $this->display_view('\Stock\excel_export\index', $datas);
    }
}