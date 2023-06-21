<?php

/**
 * Model Item_common_model this represents the item_common table
 *
 * @author      Orif (ViDi,AeDa)
 * @link        https://github.com/OrifInformatique
 * @copyright   Copyright (c), Orif (https://www.orif.ch)
 */

namespace  Stock\Models;

use Stock\Models\MyModel;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Validation\ValidationInterface;

class Item_common_model extends MyModel
{
    protected $table = 'item_common';
    protected $primaryKey = 'item_common_id';
    protected $allowedFields = ['name', 'description', 'image', 'linked_file', 'item_group_id'];

    protected Item_group_model $item_group_model;
    protected Item_tag_link_model $item_tag_link_model;

    public function __construct(ConnectionInterface &$db = null, ValidationInterface $validation = null)
    {
        $this->validationRules = [];

        $this->validationMessages = [];

        parent::__construct($db, $validation);
    }

    public function initialize()
    {
        $this->item_group_model = new Item_group_model();
        $this->item_tag_link_model = new Item_tag_link_model();
    }

    public function getItemGroup($item_common)
    {
        $itemGroup = $this->item_group_model->asArray()->where(["item_group_id" => $item_common['item_group_id']])->first();
        return $itemGroup;
    }

    public function getTags($item_common)
    {
        $tags = $this->item_tag_link_model->getTags($item_common);

        return $tags;
    }

    public function getImage($item_common)
    {
        if (!is_null($item_common) && is_null($item_common['image'])) {
            $item_common['image'] = config('\Stock\Config\StockConfig')->item_no_image;
        }

        return $item_common['image'];
    }

    public function getImagePath($item_common)
    {
        if (!is_null($item_common) && ($item_common['image'] == config('\Stock\Config\StockConfig')->item_no_image || is_null($item_common['image']))) {
            return config('\Stock\Config\StockConfig')->item_no_image_path . config('\Stock\Config\StockConfig')->item_no_image;
        } else {
            return config('\Stock\Config\StockConfig')->images_upload_path . $item_common['image'];
        }
    }
}
