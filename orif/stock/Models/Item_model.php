<?php 
namespace  Stock\Models;

//if (!defined('BASEPATH')) exit('No direct script access allowed');


/**
 * The Item model
 *
 * @author      Didier Viret, Simão Romano Schindler
 * @link        https://github.com/OrifInformatique/stock
 * @copyright   Copyright (c) 2016, Orif <http://www.orif.ch>
 */

use Stock\Models\Item_condition_model;
use Stock\Models\Item_group_model;
use Stock\Models\Supplier_model;
use Stock\Models\Stocking_place_model;
use User\Models\User_model;
use Stock\Models\Loan_model;
use Stock\Models\Inventory_control_model;
use \DateTime;

use CodeIgniter\Model;



class Item_model extends Model
{
    protected $table = 'item';
    protected $primaryKey = 'item_id';


    /**
    * Constructor
    */
    public function initialize()
    {
        $this->user_model = new User_model();
        $this->supplier_model = new Supplier_model();
        $this->stocking_place_model = new Stocking_place_model();
        $this->item_condition_model = new Item_condition_model();
        $this->item_group_model = new Item_group_model();
        $this->loan_model = new Loan_model();
        $this->inventory_control_model = new Inventory_control_model();
    }


    /*
     * Returns the id that will receive the next item
     */
    public function get_future_id()
    {
        $query = $this->db->query("SHOW TABLE STATUS LIKE 'item'");

        $row = $query->row(0);
        $value = $row->Auto_increment;

        return $value;
    }

    
    protected function get_item_group_name($item, $short = false){
      if ($this->item_group_model==null){
        $this->item_group_model = new Item_group_model();
      }
      if ($short){
        $item->group = $this->item_group_model->getWhere(["item_group_id"=>$item->item_group_id])->getRow();
      }else{
        $item->group = $this->item_group_model->getWhere(["item_group_id"=>$item->item_group_id])->getRow()->name;
      }
      return $item->group;
    }

    protected function get_item_condition_name($item){

      if($this->item_condition_model==null){
        $this->item_condition_model = new Item_condition_model();
      }
      $item->condition = $this->item_condition_model->getWhere(["item_condition_id"=>$item->item_condition_id])->getRow();
      return $item->condition;
    }


    public function get_current_loan($item) {

        if(is_null($this->loan_model)){
          $this->loan_model = new Loan_model();
        }
        helper('MY_date');
        
        $where = array('item_id'=>$item['item_id'], 'date<='=>mysqlDate('now'), 'real_return_date is NULL');

        $item['current_loan'] = $this->loan_model->asArray()->where($where)->find();

        /*
        if (is_null($item->current_loan)) {
          // ITEM IS NOT LOANED
          $bootstrap_label = '<span class="label label-success">'.html_escape($this->lang->line('lbl_loan_status_not_loaned')).'</span>';
        } else {
          // ITEM IS LOANED
          $bootstrap_label = '<span class="label label-warning">'.html_escape($this->lang->line('lbl_loan_status_loaned')).'</span>';
        } 
      
        $item->loan_bootstrap_label = $bootstrap_label;
      */
      return $item['current_loan'];
      
    }


    protected function get_last_inventory_control($item)
    {
      if (!is_null($item)) 
      {
        if(is_null($item->inventory_control_model)) 
        {
          $this->inventory_control_model = new Inventory_control_model();
        }

        $query = $this->db->query("SELECT * FROM inventory_control WHERE item_id=" . $item->item_id);
        $item->inventory_controls = $query->getResultObject();
        $inventory_controls = $item->invetory_controls;
        /* $inventory_controls->controller = $this->user_model->getResultObject()->where('');
        $inventory_controls = $this->inventory_control_model->with('controller')
                                  ->get_many_by($where); */
        $last_control = NULL;

        if (!is_null($inventory_controls))
        {
          foreach ($inventory_controls as $control) 
          {
            // Select the last control (biggest date)
            if (is_null($last_control)) {
              $last_control = $control;
            } 
            else if ($control->date > $last_control->date) 
            {
              $last_control = $control;
            }
          }
        }

        $item->last_inventory_control = $last_control;
      }
      var_dump($item);
      exit();
      return $item;
    }

      
    /**
    * Calculate a warranty status based on buying date and warranty duration
    *
    * Attribute name : warranty_status
    *
    * Values :
    *           0 : NO WARRANTY STATUS (buying date or warranty duration is not set)
    *           1 : UNDER WARRANTY
    *           2 : WARRANTY EXPIRES SOON (less than 3 months)
    *           3 : WARRANTY EXPIRED
    */
    protected function get_warranty_status($item)
    {
      if (!is_null($item)) 
      {
        if (empty($item->buying_date) || empty($item->warranty_duration))
        {
          $item->warranty_status = 0;
        }
        else
        {
          $buying_date = new DateTime($item->buying_date);
          $current_date = new DateTime("now");

          $time_spent = $buying_date->diff($current_date);
          $months_spent = ($time_spent->y * 12) + $time_spent->m;

          $warranty_left = $item->warranty_duration - $months_spent;

          if ($warranty_left > 3)
          {
            // UNDER WARRANTY
            $item->warranty_status = 1;
          }
          elseif ($warranty_left > 0)
          {
            // WARRANTY EXPIRES SOON
            $item->warranty_status = 2;
          }
          else
          {
            // WARRANTY EXPIRED
            $item->warranty_status = 3;
          }
        }
      }
        return $item;
    }

}
