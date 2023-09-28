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

        helper('form');

        $this->access_level=config('\Stock\Config\StockConfig')->access_lvl_manager;
        parent::initController($request, $response, $logger); // TODO: Change the autogenerated stub
    }

    public function index() {
        // make exportation
        if (count($_POST) > 0) {
            $items=[];
            // filter by entity
            if (!is_null($this->request->getPost('entity_id')) && !is_null($this->request->getPost('group_id')) && !is_null($this->request->getPost('group_by'))) {
                $entity_id = $this->request->getPost('entity_id');
                $group_id = $this->request->getPost('group_id');
                $group_by = $this->request->getPost('group_by');

                if ($group_by == config('\Stock\config\StockConfig')->group_by_item_common) {
                    // Group by item_common
                    $builder = $this->db->table('item_common');
                    $countItemsQuery = $this->db->table('item')
                        ->select('item_common_id, supplier_id, COUNT(item_id) as item_count')
                        ->groupBy('item_common_id, supplier_id')
                        ->getCompiledSelect();

                    $lastCreatedDateQuery = $this->db->table('item')
                        ->select('item_common_id, group_concat(buying_price) as unit_price, MAX(created_date) as last_created_date')
                        ->groupBy('item_common_id')
                        ->getCompiledSelect();

                    $builder->select('item_common.*, item_group.name AS "item_group_name", items.item_count, items.supplier_id, last_item.*')
                        ->join('(' . $countItemsQuery . ') items', 'item_common.item_common_id = items.item_common_id', 'inner')
                        ->join('(' . $lastCreatedDateQuery . ') last_item', 'item_common.item_common_id = last_item.item_common_id', 'inner')
                        ->join('item_group', 'item_group.item_group_id = item_common.item_group_id', 'inner');

                    !is_null($entity_id) ? $builder->where('fk_entity_id', $entity_id) : $builder;
                } else {
                    // Group by item
                    $builder = $this->db->table('item')
                        ->join('item_common', 'item_common.item_common_id = item.item_common_id', 'inner')
                        ->join('item_group', 'item_group.item_group_id = item_common.item_group_id', 'inner')
                        ->where('item_group.fk_entity_id', $entity_id)
                        ->select('item.*, item_common.*');
                }

                if ($group_id != 0) {
                    // Get items by group
                    $builder->where('item_common.item_group_id', $group_id);
                }

                $items = $builder->get()->getResultArray();

                //prepare items to add in excel sheet
                foreach ($items as $idx => $item) {
                    if ($group_by == config('\Stock\config\StockConfig')->group_by_item) {
                        $stockingPlace = $this->stocking_place_model->withDeleted()->find($item['stocking_place_id']);
                        $item['entity_name'] = '';

                        if ($stockingPlace !== null) {
                            $entityId = $stockingPlace['fk_entity_id'];

                            if ($entityId !== null) {
                                $entity = $this->entity_model->find($entityId);

                                if ($entity !== null) {
                                    $item['entity_name'] = $entity['name'];
                                }
                            }
                        }
                        $item['stock_place'] = $this->stocking_place_model->withDeleted()->find($item['stocking_place_id'])['name'];
                    } else {
                        $entityId = null;
                        $itemGroup = $this->item_group_model->withDeleted()->find($item['item_group_id']);
                        $item['entity_name'] = '';

                        if ($itemGroup !== null) {
                            $entityId = $itemGroup['fk_entity_id'];

                            if ($entityId !== null) {
                                $entity = $this->entity_model->find($entityId);

                                if ($entity !== null) {
                                    $item['entity_name'] = $entity['name'];
                                }
                            }
                        }

                        $item_entity = $this->entity_model->where('name', $item['entity_name'])->first();
                        $item['entity_address'] = !is_null($item_entity) ? "{$item_entity['address']} {$item_entity['zip']}, {$item_entity['locality']}" : '';
                        $unitPrices = explode(',', $item['unit_price']);
                        $item['unit_price'] = $unitPrices ? 0 : $item['unit_price'];

                        // Get last value above zero
                        if (is_array($unitPrices)) {
                            $unitPrices = array_reverse($unitPrices);
                            foreach ($unitPrices as $unitPrice) {
                                if ($unitPrice > 0) {
                                    $item['unit_price'] = $unitPrice;
                                    break;
                                }
                            }
                        }
                        $item['total_price'] = floatval($item['unit_price']) * intval($item['item_count']);
                    }
                    
                    $tag_ids = $this->item_tag_link_model->where('item_common_id', $item['item_common_id'])->findColumn('item_tag_id');
                    is_array($tag_ids) ? $item['tags'] = $this->item_tag_model->whereIn('item_tag_id', $tag_ids)->findColumn('name') : $item['tags'] = [];
                    $item['tags'] = (is_array($item['tags']) ? implode(';', $item['tags']) : '');
                    isset($item['item_group_id']) ? $item['group_name'] = $this->item_group_model->withDeleted()->find($item['item_group_id'])['name'] : $item['group_name'] = '';
                    
                    $supplier = null;
                    !isset($item['supplier_id']) ?: $supplier = $this->supplier_model->find($item['supplier_id']);
                    if ($supplier != null) {
                        $supplier = [
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

                if ($group_by == config('\Stock\config\StockConfig')->group_by_item_common) {
                    $sheet->setCellValue('A1', lang('stock_lang.export_excel_site'));
                    $sheet->setCellValue('B1', lang('stock_lang.field_address'));
                    $sheet->setCellValue('C1', lang('stock_lang.export_excel_service'));
                    $sheet->setCellValue('D1', lang('stock_lang.export_excel_section'));
                    $sheet->setCellValue('E1', lang('stock_lang.export_excel_piece'));
                    $sheet->setCellValue('F1', lang('MY_application.text_item_tags'));
                    $sheet->setCellValue('G1', lang('stock_lang.export_excel_quantity'));
                    $sheet->setCellValue('H1', lang('stock_lang.export_excel_designation'));
                    $sheet->setCellValue('I1', lang('stock_lang.export_excel_acquisition_date'));
                    $sheet->setCellValue('J1', lang('stock_lang.export_excel_unit_price'));
                    $sheet->setCellValue('K1', lang('stock_lang.export_excel_total_price'));
                    $sheet->setCellValue('L1', lang('stock_lang.export_excel_supplier'));
                    $sheet->setCellValue('M1', lang('stock_lang.export_excel_item_responsible'));
                    $sheet->setCellValue('N1', lang('stock_lang.export_excel_lifespan'));
                    $sheet->setCellValue('O1', lang('stock_lang.export_excel_replacement_date'));
                    $sheet->setCellValue('P1', lang('stock_lang.export_excel_pick_up_date'));
                    $sheet->setCellValue('Q1', lang('stock_lang.export_excel_pick_up_reason'));
                    $sheet->setCellValue('R1', lang('MY_application.field_remarks'));
                    $sheet->setCellValue('S1', lang('stock_lang.export_excel_type'));
                    $sheet->setCellValue('T1', lang('stock_lang.export_excel_path'));
                    $cellIt = $sheet->getRowIterator(1)->current()->getCellIterator("A", "T");

                    foreach ($items as $idx => $item) {
                        $sheet->getStyle('T' . strval($idx+2))->getAlignment()->setWrapText(true);
                        $sheet->setCellValue('A'.strval($idx+2), $item['entity_name']);
                        $sheet->setCellValue('B'.strval($idx+2), $item['entity_address']);
                        $sheet->setCellValue('C'.strval($idx+2), lang('stock_lang.export_excel_default_service'));
                        $sheet->setCellValue('D'.strval($idx+2), lang('stock_lang.export_excel_default_section'));
                        $sheet->setCellValue('E'.strval($idx+2), $item['group_name']);
                        $sheet->setCellValue('F'.strval($idx+2), $item['tags']);
                        $sheet->setCellValue('G'.strval($idx+2), $item['item_count']);
                        $sheet->setCellValue('H'.strval($idx+2), $item['name']);
                        $sheet->setCellValue('I'.strval($idx+2), !is_null($item['last_created_date']) ? date(config('\Stock\config\StockConfig')->database_date_format, strtotime($item['last_created_date'])) : '');
                        $sheet->setCellValue('J'.strval($idx+2), $item['unit_price']);
                        $sheet->setCellValue('K'.strval($idx+2), $item['total_price']);
                        $sheet->setCellValue('L'.strval($idx+2), $item['supplier']);
                        $sheet->setCellValue('M'.strval($idx+2), '');
                        $sheet->setCellValue('N'.strval($idx+2), '');
                        $sheet->setCellValue('O'.strval($idx+2), '');
                        $sheet->setCellValue('P'.strval($idx+2), '');
                        $sheet->setCellValue('Q'.strval($idx+2), '');
                        $sheet->setCellValue('R'.strval($idx+2), '');
                        $sheet->setCellValue('S'.strval($idx+2), lang('stock_lang.export_excel_default_type'));
                        $sheet->setCellValue('T'.strval($idx+2), lang('stock_lang.export_excel_default_path'));
                    }
        
                    $sheet->getColumnDimension('A')->setAutoSize(true);
                    $sheet->getColumnDimension('B')->setAutoSize(true);
                    $sheet->getColumnDimension('C')->setAutoSize(true);
                    $sheet->getColumnDimension('D')->setAutoSize(true);
                    $sheet->getColumnDimension('E')->setAutoSize(true);
                    $sheet->getColumnDimension('F')->setAutoSize(true);
                    $sheet->getColumnDimension('G')->setAutoSize(true);
                    $sheet->getColumnDimension('H')->setAutoSize(true);
                    $sheet->getColumnDimension('I')->setAutoSize(true);
                    $sheet->getColumnDimension('J')->setAutoSize(true);
                    $sheet->getColumnDimension('K')->setAutoSize(true);
                    $sheet->getColumnDimension('L')->setAutoSize(true);
                    $sheet->getColumnDimension('M')->setAutoSize(true);
                    $sheet->getColumnDimension('N')->setAutoSize(true);
                    $sheet->getColumnDimension('O')->setAutoSize(true);
                    $sheet->getColumnDimension('P')->setAutoSize(true);
                    $sheet->getColumnDimension('Q')->setAutoSize(true);
                    $sheet->getColumnDimension('R')->setAutoSize(true);
                    $sheet->getColumnDimension('S')->setAutoSize(true);
                    $sheet->getColumnDimension('T')->setAutoSize(true);
                } else {
                    $sheet->setCellValue('A1', lang('stock_lang.export_excel_site'));
                    $sheet->setCellValue('B1', lang('stock_lang.stocking_place'));
                    $sheet->setCellValue('C1', lang('MY_application.text_item_tags'));
                    $sheet->setCellValue('D1', lang('MY_application.field_item_name'));
                    $sheet->setCellValue('E1', lang('MY_application.field_group'));
                    $sheet->setCellValue('F1', lang('stock_lang.export_excel_acquisition_date'));
                    $sheet->setCellValue('G1', lang('stock_lang.export_excel_unit_price'));
                    $sheet->setCellValue('H1', lang('stock_lang.export_excel_supplier'));
                    $cellIt = $sheet->getRowIterator(1)->current()->getCellIterator("A", "H");
        
                    foreach ($items as $idx => $item) {
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
                }

                foreach ($cellIt as $value){
                    ($value->getAppliedStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00B0FF'));
                    $value->getAppliedStyle()->getFont()->getColor()->setRGB('FFFFFF');
                }
    
                $writer = new Xlsx($spreadsheet);
                $fileTag = "";
    
                while (strlen($fileTag) < 10) {
                    $fileTag .= strval(rand(0, 9));
                }
    
                $writer->save(ROOTPATH . 'public/' . $fileTag . '.xlsx');
                $excelFile = fopen(ROOTPATH . 'public/' . $fileTag . '.xlsx', 'r+');
                $excelDatas = fread($excelFile,filesize(ROOTPATH . 'public/' . $fileTag.'.xlsx'));
                $excelDatas = base64_encode($excelDatas);
                unlink(ROOTPATH . 'public/' . $fileTag . '.xlsx');
    
                $this->response->setContentType('Content-Type: application/json');
                return $this->response->setStatusCode(201)->setContentType('application/json')->setBody(json_encode(['excel_datas' => $excelDatas]));
            }

        }

        $datas['entities'] = null;
        $datas['item_groups'] = null;

        if (isset($_SESSION['user_access']) && $_SESSION['user_access'] > config('\Stock\Config\StockConfig')->access_lvl_manager) {
            $datas['entities'] = $this->dropdown($this->entity_model->findAll(), 'entity_id');
            
        } else if (isset($_SESSION['user_id'])) {
            if ($this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->countAllResults() > 0) {
                $datas['entities'] = $this->dropdown($this->entity_model->whereIn('entity_id', $this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->findColumn('fk_entity_id'))->findAll(), 'entity_id');
            }
        } else {
            return;
        }

        return $this->display_view('\Stock\excel_export\index', $datas);
    }

    /**
     * Generate item_groups dropdown for the excel_export view
     * 
     * @param mixed $entity_id = entity id to select groups from
     */
    public function get_item_groups_div($entity_id = null) {
        $data['item_groups'] = [];
        if (isset($_SESSION['user_id']) && $this->user_entity_model->where('fk_user_id', $_SESSION['user_id'])->countAllResults() > 0 && !is_null($entity_id)) {
            $data['item_groups'] = $this->dropdown($this->item_group_model->where('fk_entity_id', $entity_id)->findAll(), 'item_group_id');
        }

        if (!empty($data['item_groups'])) {
            // Add a default value to select all groups
            array_unshift($data['item_groups'], lang('stock_lang.all_item_groups'));
        }

        $item_groups_div = form_label(lang('stock_lang.btn_item_groups'), 'item_group_id').form_dropdown('item_group_id', $data['item_groups'], [], [
            'id' => 'item_group_id',
            'class' => 'form-control pl-2 item_groups_selector'
        ]);

        return json_encode($item_groups_div);
    }
}