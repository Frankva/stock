<?php

namespace  Stock\Controllers;


/*
if (!defined('BASEPATH'))
    exit('No direct script access allowed');
*/


/**
 * A controller to display and manage items
 *
 * @author      Orif (ViDi)
 * @link        https://github.com/OrifInformatique
 * @copyright   Copyright (c) 2016, Orif <http://www.orif.ch>
 */


use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use PSR\Log\LoggerInterface;
use App\Controllers\BaseController;
use DateInterval;
use DateTime;
use Stock\Models\Inventory_control_model;
use Stock\Models\Item_model;
use Stock\Models\Loan_model;
use Stock\Models\Item_tag_model;
use Stock\Models\Item_condition_model;
use Stock\Models\Item_group_model;
use Stock\Models\Item_tag_link_model;
use Stock\Models\Stocking_place_model;
use User\Models\User_model;

class Item extends BaseController {

    /* MY_Controller variables definition */
    protected $access_level = "*";

    /**
     * Constructor
     */
    public function initController(RequestInterface $request, REsponseInterface $response, LoggerInterface $logger) {
        $this->access_level = "*";
        parent::initController($request, $response, $logger);

        $this->item_model = new Item_model();
        $this->loan_model = new Loan_model();
        $this->item_tag_link_model = new Item_tag_link_model();
        helper('sort');
        helper('form');
    }

    /*{
        parent::__construct();
        $this->load->model('item_model');
        $this->load->model('loan_model');
        $this->load->helper('sort');
    }*/

    /**
     * Display items list, with filtering
     *
     * @param integer $page
     * @return void
     */
    public function index($page = 1) {
        // Load list of elements to display as filters

        $this->item_tag_model = new Item_tag_model();
        //$this->load->model('item_tag_model');
        $output['item_tags'] = $this->item_tag_model->dropdown('name');

        $this->item_condition_model = new Item_condition_model();
        //$this->load->model('item_condition_model');
        $output['item_conditions'] = $this->item_condition_model->dropdown('name');

        $this->item_group_model = new Item_group_model();
        //$this->load->model('item_group_model');
        $output['item_groups'] = $this->item_group_model->dropdown('name');

        $this->stocking_place_model = new Stocking_place_model();
        $output['stocking_places'] = $this->stocking_place_model->dropdown('name');
        $output['sort_order'] = array(lang('MY_application.sort_order_name'),
                                        lang('MY_application.sort_order_stocking_place_id'),
                                        lang('MY_application.sort_order_date'),
                                        lang('MY_application.sort_order_inventory_number'));
        $output['sort_asc_desc'] = array(lang('MY_application.sort_order_asc'),
                                            lang('MY_application.sort_order_des'));
        // Prepare search filters values to send to the view
        if (!isset($output["ts"])) $output["ts"] = '';
        if (!isset($output["c"])) $output["c"] = '';
        if (!isset($output["g"])) $output["g"] = '';
        if (!isset($output["s"])) $output["s"] = '';
        if (!isset($output["t"])) $output["t"] = '';
        if (!isset($output["o"])) $output["o"] = '';
        if (!isset($output["ad"])) $output["ad"] = '';
        // Send the data to the View
       // $this->display_view('item/list', $output);
        return $this->display_view('Stock\Views\item\list', $output);
    }

    private function load_list($page = 1)
    {
        // Store URL to make possible to come back later (from item detail for example)
        $_SESSION['items_list_url'] = base_url('item/index/'.$page);
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $_SESSION['items_list_url'] .= '?'.$_SERVER['QUERY_STRING'];
        }

        // Get user's search filters and add default values
        $filters = $_GET;
        if (!isset($filters['c'])) {
            // No condition selected for filtering, default filtering for "functional" items
            $filters['c'] = array(FUNCTIONAL_ITEM_CONDITION_ID);
        }

        // Sanitize $page parameter
        if (empty($page) || !is_numeric($page) || $page<1) {
            $page = 1;
        }

        // Get item(s) through filtered search on the database
        $output['items'] = $this->item_model->get_filtered($filters);

        // Verify the existence of the sort order key in filters
        if(array_key_exists("o", $filters)){
            switch ($filters['o']) {
                case 1:
                $sortValue = "stocking_place_id";
                break;
                case 2:
                $sortValue = "buying_date";
                break;
                case 3:
                $sortValue = "inventory_number";
                break;
                //In case of problem, it automatically switches to name
                default:
                case 0:
                $sortValue = "name";
                break;
            }
        } else {
            // default sort by name
            $sortValue = "name";
        }

        // If not 1, order will be ascending
        if(array_key_exists("ad", $filters)){
            $asc = $filters['ad'] != 1;
        } else {
            // default sort order is asc
            $asc = true;
        }
        $output['items'] = sortBySubValue($output['items'], $sortValue, $asc);

        // Add page title
        $output['title'] = lang('My_application.page_item_list');

        // Pagination
        $items_count = count($output["items"]);
        //$output['pagination'] =  $this->load_pagination($items_count)->create_links();
        $output['pagination'] = $this->load_pagination($items_count, $page);

        $output['number_page'] = $page;
        if($output['number_page']>ceil($items_count/ITEMS_PER_PAGE)) $output['number_page']=ceil($items_count/ITEMS_PER_PAGE);

        // Keep only the slice of items corresponding to the current page
        $output["items"] = array_slice($output["items"], ($output['number_page']-1)*ITEMS_PER_PAGE, ITEMS_PER_PAGE);

        return $output;
    }

    public function load_list_json($page = 1){
        echo json_encode($this->load_list($page));
    }

    public function load_pagination($nbr_items, $page)
    {
        // Create the pagination
        $pager = \Config\Services::pager();
        /*
        $config['base_url'] = base_url('/item/index/');
        $config['total_rows'] = $nbr_items;
        $config['per_page'] = ITEMS_PER_PAGE;
        $config['use_page_numbers'] = TRUE;
        $config['reuse_query_string'] = TRUE;

        $config['full_tag_open'] = '<ul class="pagination">';
        $config['full_tag_close'] = '</ul>';

        $config['first_link'] = '&laquo;';
        $config['first_tag_open'] = '<li>';
        $config['first_tag_close'] = '</li>';

        $config['last_link'] = '&raquo;';
        $config['last_tag_open'] = '<li>';
        $config['last_tag_close'] = '</li>';

        $config['next_link'] = FALSE;
        $config['prev_link'] = FALSE;

        $config['cur_tag_open'] = '<li class="active"><a>';
        $config['cur_tag_close'] = '</li></a>';
        $config['num_links'] = 5;

        $config['num_tag_open'] = '<li>';
        $config['num_tag_close'] = '</li>';

        return $this->pagination->initialize($config);
        */

        return $pager->makeLinks($page, ITEMS_PER_PAGE, $nbr_items);

    }
    /**
     * Display details of one single item
     *
     * @param $id : the item to display
     * @return void
     */
    public function view($id = NULL) {

        if (is_null($id)) {
            // No item selected, display items list
            redirect('/item');
        }

        // Get item object and related objects
        /*$item = $this->item_model->with('supplier')
                ->with('stocking_place')
                ->with('item_condition')
                ->with('item_group')
                ->get($id);*/

        $item = $this->item_model->asArray()->where(["item_id"=>$id])->first();
        $item['supplier'] = $this->item_model->getSupplier($item);
        $item['stocking_place'] = $this->item_model->getStockingPlace($item);
        $item['item_condition'] = $this->item_model->getItemCondition($item);
        $item['item_group'] = $this->item_model->getItemGroup($item);
        $item['inventory_number'] = $this->item_model->getInventoryNumber($item);
        $item['current_loan'] = $this->item_model->getCurrentLoan($item);
        $item['warranty_status'] = $this->item_model->getWarrantyStatus($item);
        $item['image_path'] = $this->item_model->getImagePath($item);
        $item['tags'] = $this->item_model->getTags($item);

        if (!is_null($item)) {
            $output['item'] = $item;
            $this->display_view('Stock\Views\item\detail', $output);
        } else {
            // $id is not valid, display an error message
            $this->display_view('errors/application/inexistent_item');
        }
    }

    /**
     * Add a new item
     *
     * @return void
     */
    public function create() {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
            // Get new item id and set picture_prefix
            $item_id = $this->item_model->get_future_id();
            $_SESSION['picture_prefix'] = str_pad($item_id, INVENTORY_NUMBER_CHARS, "0", STR_PAD_LEFT);

            // Define image path variables
            $temp_image_name = $_SESSION["picture_prefix"].IMAGE_PICTURE_SUFFIX.IMAGE_TMP_SUFFIX.IMAGE_EXTENSION;
            $new_image_name = $_SESSION["picture_prefix"].IMAGE_PICTURE_SUFFIX.IMAGE_EXTENSION;

            // Check if the user cancelled the form
            if(isset($_POST['submitCancel'])){
                $tmp_image_file = glob(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name)[0];

                // Check if there is a temporary file, if yes then delete it
                if($tmp_image_file != null || $tmp_image_file != false){
                    unlink($tmp_image_file);
                }

                redirect(base_url());
                exit();
            }

            $this->set_validation_rules();

            $data['upload_errors'] = "";

            $upload_failed = false;

            // If the user want to display the image form, we first save fields
            // values in the session, then redirect him to the image form
            if(isset($_POST['photoSubmit'])){
                $this->session->set_userdata("POST", $_POST);
                $this->session->set_userdata("item_id", $item_id);

                redirect(base_url("picture/select_picture"));
            }

            if (isset($_FILES['linked_file']) && $_FILES['linked_file']['name'] != '') {

                // LINKED FILE UPLOADING
                $config['upload_path'] = './uploads/files/';
                $config['allowed_types'] = 'pdf|doc|docx';
                $config['max_size'] = 2048;
                $config['max_width'] = 0;
                $config['max_height'] = 0;

                $this->load->library('upload');
                $this->upload->initialize($config);

                if ($this->upload->do_upload('linked_file')) {
                    $itemArray['linked_file'] = $this->upload->data('file_name');
                } else {
                    $data['upload_errors'] = $this->upload->display_errors();
                    $upload_failed = TRUE;
                }
            }

            if ($this->form_validation->run() == TRUE && $upload_failed != TRUE) {
                // No error, save item

                $linkArray = array();

                $this->load->model('item_tag_link_model');

                foreach ($_POST as $key => $value) {
                    if (substr($key, 0, 3) == "tag") {
                        // Stock links to be created when the item will exist
                        $linkArray[] = $value;
                    } else {
                        $itemArray[$key] = $value;
                    }
                }

                // Turn Temporaty Image into a final one if there is one
                if(file_exists(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name)){
                    rename(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name,config('\Stock\Config\StockConfig')->images_upload_path.$new_image_name);
                    $itemArray['image'] = $new_image_name;
                }

                $itemArray["created_by_user_id"] = $_SESSION['user_id'];

                $this->item_model->insert($itemArray);
                $item_id = $this->db->insert_id();

                foreach ($linkArray as $tag) {
                    $this->item_tag_link_model->insert(array("item_tag_id" => $tag, "item_id" => ($item_id)));
                }

                redirect("item/view/" . $item_id);
            } else {
                // Remember checked tags to display them checked again
                foreach ($_POST as $key => $value) {
                    // If it is a tag
                    if (substr($key, 0, 3) == "tag") {
                        // put it in the data array
                        $tag_link = new stdClass();
                        $tag_link->item_tag_id = substr($key, 3);
                        $data['tag_links'][] = (object) $tag_link;
                    }
                }

                // Load the comboboxes options
                $this->load->model('stocking_place_model');
                $data['stocking_places'] = $this->stocking_place_model->get_all();
                $this->load->model('supplier_model');
                $data['suppliers'] = $this->supplier_model->get_all();
                $this->load->model('item_group_model');
                $data['item_groups_name'] = $this->item_group_model->dropdown('name');

                // Load item groups
                $data['item_groups'] = $this->item_group_model->get_all();

                $this->load->model('item_condition_model');
                $data['condishes'] = $this->item_condition_model->get_all();

                // Load the tags
                $this->load->model('item_tag_model');
                $data['item_tags'] = $this->item_tag_model->get_all();

                $data['item_id'] = $this->item_model->get_future_id();

                // If the user gets back from another view, get the fields values
                // which have been saved in session variable.
                // Then reset this session variable.
                if(isset($_SESSION['POST'])){
                    foreach ($_SESSION['POST'] as $key => $value) {
                        // If it is a tag
                        if (substr($key, 0, 3) == "tag") {
                            // put it in the data array
                            $tag_link = new stdClass();
                            $tag_link->item_tag_id = substr($key, 3);
                            $data['tag_links'][] = (object) $tag_link;
                        }else{
                            $data[$key] = $value;
                        }
                    }
                    unset($_SESSION['POST']);
                }

                $this->display_view('item/form', $data);
            }
        } else {
            // Access is not allowed
            $this->ask_for_login();
        }
    }

    /**
     * Modify an existing item
     *
     * @param integer $id
     * @return void
     */
    public function modify($id) {
        // Check if access is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
            // Define image path variables
            $_SESSION['picture_prefix'] = str_pad($id, INVENTORY_NUMBER_CHARS, "0", STR_PAD_LEFT);
            $temp_image_name = $_SESSION["picture_prefix"].IMAGE_PICTURE_SUFFIX.IMAGE_TMP_SUFFIX.IMAGE_EXTENSION;
            $new_image_name = $_SESSION["picture_prefix"].IMAGE_PICTURE_SUFFIX.IMAGE_EXTENSION;

            // Check if the user cancelled the form
            if(isset($_POST['submitCancel'])){
                $tmp_image_file = glob(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name)[0];

                // Check if there is a temporary image file, if yes then delete it
                if($tmp_image_file != null || $tmp_image_file != false){
                    unlink($tmp_image_file);
                }

                redirect(base_url("item/view/$id"));
                exit();
            }

            $this->load->model('item_tag_link_model');

            // If there is no submit
            if (empty($_POST)) {
                // get the data from the item with this id,
                $data = get_object_vars($this->item_model->get($id));
                // including its tags
                $data['tag_links'] = $this->item_tag_link_model->get_many_by("item_id", $id);

            } else {
                $this->set_validation_rules($id);

                $data['upload_errors'] = "";

                $upload_failed = false;

                // If the user wants to display the image form, we first save fields
                // values in the session, then redirect him to the image form
                if(isset($_POST['photoSubmit'])){
                    $this->session->set_userdata("POST", $_POST);

                    redirect(base_url("picture/select_picture"));
                    exit();
                }

                if (isset($_FILES['linked_file']) && $_FILES['linked_file']['name'] != '') {

                    // LINKED FILE UPLOADING
                    $config['upload_path'] = './uploads/files/';
                    $config['allowed_types'] = 'pdf|doc|docx';
                    $config['max_size'] = 2048;
                    $config['max_width'] = 0;
                    $config['max_height'] = 0;

                    $this->load->library('upload');
                    $this->upload->initialize($config);

                    if ($this->upload->do_upload('linked_file')) {
                        $itemArray['linked_file'] = $this->upload->data('file_name');
                    } else {
                        $data['upload_errors'] = $this->upload->display_errors();
                        $upload_failed = TRUE;
                    }
                }

                if ($this->form_validation->run() == TRUE && $upload_failed != TRUE) {

                    // Delete ALL the tags for this object
                    $this->item_tag_link_model->delete_by(array('item_id' => $id));

                    foreach ($_POST as $key => $value) {
                        // If it is a tag, since their keys are tag1, tag2, …
                        if (substr($key, 0, 3) == "tag") {
                            // put it in the array for tags.
                            $this->item_tag_link_model->insert(array("item_tag_id" => $value, "item_id" => $id));
                        } else {
                            // put it in th array for item properties.
                            $itemArray[$key] = $value;
                        }
                    }

                    // Turn temporary image into a final one if there is one
                    if(file_exists(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name)){
                        rename(config('\Stock\Config\StockConfig')->images_upload_path.$temp_image_name,config('\Stock\Config\StockConfig')->images_upload_path.$new_image_name);
                        $itemArray['image'] = $new_image_name;
                    }

                    // Execute the changes in the item table
                    $this->item_model->update($id, $itemArray);

                    redirect("/item/view/" . $id);
                } else {
                    // Remember checked tags to display them checked again
                    foreach ($_POST as $key => $value) {
                        // If it is a tag, since their keys are tag1, tag2, …
                        if (substr($key, 0, 3) == "tag") {
                            // put it in the data array
                            $tag_link = new stdClass();
                            $tag_link->item_tag_id = substr($key, 3);
                            $data['tag_links'][] = (object) $tag_link;
                        }
                    }
                }
            }

            $data['modify'] = true;
            $data['item_id'] = $id;
            $_SESSION['picture_prefix'] = $data['inventory_id'];

            // Load the options
            $this->load->model('stocking_place_model');
            $data['stocking_places'] = $this->stocking_place_model->get_all();
            $this->load->model('supplier_model');
            $data['suppliers'] = $this->supplier_model->get_all();
            $this->load->model('item_group_model');
            $data['item_groups_name'] = $this->item_group_model->dropdown('name');
            $this->load->model('item_condition_model');
            $data['condishes'] = $this->item_condition_model->get_all();

            // Load item groups
            $data['item_groups'] = $this->item_group_model->get_all();

            // Load the tags
            $this->load->model('item_tag_model');
            $data['item_tags'] = $this->item_tag_model->get_all();

            // If the user gets back from another view, get the fields values
            // which have been saved in session variable.
            // Then reset this session variable.

            if(isset($_SESSION['POST'])) {
                foreach ($_SESSION['POST'] as $key => $value) {
                    // If it is a tag
                    if (substr($key, 0, 3) == "tag") {
                        // put it in the data array
                        $tag_link = new stdClass();
                        $tag_link->item_tag_id = substr($key, 3);
                        $data['tag_links'][] = (object) $tag_link;
                    }else{
                        $data[$key] = $value;
                    }
                }
            }
            unset($_SESSION['POST']);

            $this->display_view('item/form', $data);
        } else {
            // Update is not allowed
            $this->ask_for_login();
        }
    }

    /**
     * Delete an item
     * ACCESS RESTRICTED FOR ADMINISTRATORS ONLY
     *
     * @param integer $id
     * @param [type] $command
     * @return void
     */
    public function delete($id, $command = NULL) {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true && $_SESSION['user_access'] >= ACCESS_LVL_ADMIN) {
            if (empty($command)) {
                $data['db'] = 'item';
                $data['id'] = $id;

                $this->display_view('Stock\Views\item\confirm_delete', $data);
            } else {
                $this->item_model->update($id, array("description" => "FAC"));

                $item = $this->item_model->find($id);
                $items = $this->item_model->asArray()->where('image', $item['image'])->findAll();
                // Change this if soft deleting items is enabled
                // Check if any other item uses this image
                if (count($items) < 2) {
                    unlink(ROOTPATH.config('\Stock\Config\StockConfig')->images_upload_path.$item['image']);
                }

                $this->item_tag_link_model->delete_by(array('item_id' => $id));
                $this->loan_model->delete_by(array('item_id' => $id));
                $this->item_model->delete($id);

                redirect('/item');
            }
        } else {
            // Access is not allowed
            redirect('/item');
        }
    }

    /**
     * Create inventory control for one given item
     *
     * @param integer $id
     * @return void
     */
    public function create_inventory_control($id = NULL) {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
            if (empty($id)) {
                // No item specified, display items list
                redirect('/item');
            }

            $this->user_model = new User_model();
            $this->inventory_control_model = new Inventory_control_model();

            $data['item'] = $this->item_model->find($id);
            $data['item']['inventory_number'] = $this->item_model->getInventoryNumber($data['item']);
            $data['controller'] = $this->user_model->find($_SESSION['user_id']);

            if (isset($_POST['date']) && $_POST['date'] != '') {
                $data['date'] = $_POST['date'];
            } else {
                $data['date'] = date('Y-m-d');
            }

            if (isset($_POST['remarks'])) {
                $data['remarks'] = $_POST['remarks'];
            } else {
                $data['remarks'] = '';
            }

            if (isset($_POST['submit'])) {
                $inventory_control['item_id'] = $id;
                $inventory_control['controller_id'] = $_SESSION['user_id'];
                $inventory_control['date'] = $data['date'];
                $inventory_control['remarks'] = $data['remarks'];

                $this->inventory_control_model->insert($inventory_control);
                return redirect()->to("/item/view/".$id);
            } else {
                $this->display_view('Stock\Views\inventory_control\form', $data);
            }
        } else {
            // Access is not allowed
            redirect('/item');
        }
    }

    /**
     * Display inventory controls list for one given item
     *
     * @param integer $id
     * @return void
     */
    public function inventory_controls($id = NULL) {
        if (empty($id)) {
            // No item specified, display items list
            redirect('/item');
        }
        $this->inventory_control_model = new Inventory_control_model();
        helper('MY_date');

        // Get item object with related inventory controls
        $output['item'] = $this->item_model->find($id);
        $output['inventory_controls'] = $this->inventory_control_model->where('item_id='.$id)->findAll();
        $output['item']['inventory_number'] = $this->item_model->getInventoryNumber($output['item']);
        array_walk($output['inventory_controls'], function(&$control) {
            $control['controller'] = $this->inventory_control_model->getUser($control['controller_id']);
        });

        $this->display_view('Stock\Views\inventory_control\list', $output);
    }

    /**
     * Create loan for one given item
     *
     * @param integer $id
     * @return void
     */
    public function create_loan($id = NULL) {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
            if (empty($id)) {
                // No item specified, display items list
                redirect('/item');
            }

            // Get item object and related loans
            $item = $this->item_model->find($id);

            $data['item'] = $item;
            $data['item_id'] = $id;

            // Test input
            $validation = \Config\Services::validation();

            $validation->setRule("date", "Date du prêt", 'required', array('required' => "La date du prêt doit être fournie"));

            if ($validation->run($_POST) === TRUE) {
                $loanArray = $_POST;

                if ($loanArray["planned_return_date"] == 0 || $loanArray["planned_return_date"] == "0000-00-00" || $loanArray["planned_return_date"] == "") {
                    $loanArray["planned_return_date"] = NULL;
                }

                if ($loanArray["real_return_date"] == 0 || $loanArray["real_return_date"] == "0000-00-00" || $loanArray["real_return_date"] == "") {
                    $loanArray["real_return_date"] = NULL;
                }

                $loanArray["item_id"] = $id;

                $loanArray["loan_by_user_id"] = $this->session->user_id;

                $this->loan_model->insert($loanArray);

                return redirect()->to("/item/loans/".$id);
            } else {
                $this->display_view('Stock\Views\loan\form', $data);
            }
        } else {
            // Access is not allowed
            redirect('/item');
        }
    }

    /**
     * Modify some loan
     *
     * @param integer $id
     * @return void
     */
    public function modify_loan($id = NULL) {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
            // get the data from the loan with this id (to fill the form or to get the concerned item)
            $data = $this->loan_model->find($id);

            if (!empty($_POST)) {
                // test input
                $validation = \Config\Services::validation();

                $validation->setRule("date", "Date du prêt", 'required', array('required' => "La date du prêt doit être fournie"));

                if ($validation->run($_POST) === TRUE) {
                    //Declarations

                    $loanArray = $_POST;

                    if ($loanArray["planned_return_date"] == 0 || $loanArray["planned_return_date"] == "0000-00-00" || $loanArray["planned_return_date"] == "") {
                        $loanArray["planned_return_date"] = NULL;
                    }

                    if ($loanArray["real_return_date"] == 0 || $loanArray["real_return_date"] == "0000-00-00" || $loanArray["real_return_date"] == "") {
                        $loanArray["real_return_date"] = NULL;
                    }

                    // Execute the changes in the item table
                    $this->loan_model->update($id, $loanArray);

                    var_dump($data);
                    return redirect()->to("/item/loans/".$data["item_id"]);
                }
            }
            $this->display_view('Stock\Views\loan\form', $data);
        } else {
            // Access is not allowed
            redirect("/item");
        }
    }


    /**
     *  Display loans list for one given item
     *
     * @param integer $id
     * @return void
     */
    public function loans($id = NULL) {
        if (empty($id)) {
            // No item specified, display items list
            redirect('/item');
        }

        // Get item object and related loans
        $item = $this->item_model->find($id);
        $loans = $this->loan_model->where('item_id', $item['item_id'])->findAll();
        array_walk($loans, function(&$loan) {
            $loan['loan_by_user'] = $this->loan_model->get_loaner($loan);
            if (!is_null($loan['loan_to_user_id'])) {
                $loan['loan_to_user'] = $this->loan_model->get_borrower($loan);
            }
        });

        $output['item'] = $item;
        $output['loans'] = $loans;

        $this->display_view('Stock\Views\loan\list', $output);
    }

    /**
     * Delete a loan
     * ACCESS RESTRICTED FOR ADMINISTRATORS ONLY
     *
     * @param integer $id
     * @param [type] $command
     * @return void
     */
    public function delete_loan($id, $command = NULL) {
        // Check if this is allowed
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true && $_SESSION['user_access'] >= ACCESS_LVL_ADMIN) {
            if (empty($command)) {
                $data['db'] = 'loan';
                $data['id'] = $id;

                $this->display_view('Stock\Views\item\confirm_delete', $data);
            } else {
                // get the data from the loan with this id (to fill the form or to get the concerned item)
                $data = $this->loan_model->find($id);

                $this->loan_model->delete($id);

                return redirect()->to("/item/loans/" . $data["item_id"]);
            }
        } else {
            // Access is not allowed
            redirect('/item');
        }
    }

    /**
     * Displays the list for all active loans
     *
     * @return void
     */
    public function list_loans() {
        $this->display_view('Stock\Views\item\loans_list');
    }

    /**
     * Loads the list of loands
     *
     * @param integer $page
     * @return array
     */
    public function load_list_loans($page = 1) {
        helper('MY_date');

        // Store URL to make possible to come back later (from item detail for example)
        $_SESSION['items_list_url'] = base_url('item/index/'.$page);
        if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $_SESSION['items_list_url'] .= '?'.$_SERVER['QUERY_STRING'];
        }

        // Sanitize $page parameter
        if (empty($page) || !is_numeric($page) || $page<1) {
            $page = 1;
        }

        // Get item(s) with loans
        $loans = $this->loan_model->where('real_return_date', NULL);
        $loans = $loans->findAll();
        $now = new DateTime();
        $late_items = array_filter($loans, function($loan) use ($now) {
            // If possible compare with a planned date, otherwise use +3 months
            if (isset($loan['planned_return_date']) && !is_null($loan['planned_return_date'])) {
                $date = new DateTime($loan['planned_return_date']);
            } else {
                $date = new DateTime($loan['date']);
                $date = $date->add(new DateInterval('P3M'));
            }

            return $date < $now;
        });
        $late_item_ids = array_map(function($loan) { return $loan['item_id']; }, $late_items);
        $items_loans = array_map(function($loan) { return $loan['item_id']; }, $loans);
        $items = $this->item_model->get_filtered(['c' => [10, 30, 40]]);
        $items = array_filter($items, function($item) use ($items_loans) { return in_array($item['item_id'], $items_loans); });

        // Sort items, separate late loans and others, then sort by name
        usort($items, function($a, $b) use ($late_item_ids) {
            $late_a = in_array($a['item_id'], $late_item_ids);
            $late_b = in_array($b['item_id'], $late_item_ids);
            if ($late_a != $late_b) {
                return $late_b <=> $late_a;
            } else {
                return strtolower($a['name']) <=> strtolower($b['name']);
            }
        });

        // Add page title
        $title = lang('My_application.page_item_list');

        // Pagination
        $items_count = count($items);
        $pagination = $this->load_pagination($items_count, $page);

        $number_page = $page;
        if($number_page > ceil($items_count/ITEMS_PER_PAGE)) $number_page = ceil($items_count/ITEMS_PER_PAGE);

        // Keep only the slice of items corresponding to the current page
        $items = array_slice($items, ($number_page-1)*ITEMS_PER_PAGE, ITEMS_PER_PAGE);

        // Add to the item whether it is late, the starting date, and the end date
        array_walk($items, function(&$item) use ($late_item_ids) {
            $item['is_late'] = in_array($item['item_id'], $late_item_ids);
            $loan = $this->loan_model->where('item_id', $item['item_id'])->orderBy('date', 'desc')->first();
            if (!isset($loan['planned_return_date']) || is_null($loan['planned_return_date'])) {
                $loan['planned_return_date'] = '';
            } else {
                $loan['planned_return_date'] = databaseToShortDate($loan['planned_return_date']);
            }
            $loan['date'] = databaseToShortDate($loan['date']);

            $item['loan'] = $loan;
        });

        return [
            'items' => $items,
            'title' => $title,
            'pagination' => $pagination,
            'number_page' => $number_page,
        ];
    }

    /**
     * Displays the JSON version of the result of `load_list_loans`
     *
     * @param integer $page
     * @return void
     */
    public function load_list_loans_json($page = 1) {
        echo json_encode($this->load_list_loans($page));
    }

    /**
     * Set validation rules for create and update form
     *
     *
     * @param integer $id
     * @return void
     */
    private function set_validation_rules($id = NULL) {
        $this->load->library('form_validation');

        $this->form_validation->set_rules("name", $this->lang->line('field_item_name'), 'required');

        $this->form_validation->set_rules("inventory_prefix", lang('field_inventory_number'), 'required');
    }

}
