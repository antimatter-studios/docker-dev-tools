<?php declare(strict_types=1);

namespace DDT\Model\Tree;

use DDT\Model\ListModel;
use DDT\Model\Model;

class TreeListModel extends ListModel
{
    private $parent;

    // a tree list model contains a list of tree list models
    // allowing you to make a tree like structure   
    public function __construct(iterable $data)
    {
        // allowed types Model, TreeListModel
        parent::__construct($data, [Model::class, TreeListModel::class]);
    }

    public function append($data)
    {
        if($data instanceof Model) {}
        if($data instanceof TreeListModel) {}
    }
}

class TreeModel extends Model implements TreeItemInterface {
    public function __construct(TreeItemInterface $item)
    {
        
    }

    public function append()
    {

    }

    public function isBranch(): bool
    {
        return false;
    }

    public function isLeaf(): bool
    {
        return true;
    }

    public function getData()
    {
        return [];
    }
}

/**
 * TreeListModel
 *      -> TreeModel (Model | TreeListModel)
 *      -> TreeModel
 *      -> TreeModel
 *      -> TreeModel
 */

/**
 * TreeListModel
 *      -> TreeListModel
 *              -> item
 *              -> item
 *              -> item
 */