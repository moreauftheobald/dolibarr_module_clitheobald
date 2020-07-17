<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!class_exists('SeedObject'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class DefaultProductWarehouse extends SeedObject
{
	/** @var int $isextrafieldmanaged Enable the fictionalises of extrafields */
    public $isextrafieldmanaged = 0;


    /**
     * CliTheobald constructor.
     * @param DoliDB    $db    Database connector
     */
    public function __construct($db)
    {
        parent::__construct($db);

		$this->init();
    }



	/** @var string $table_element Table name in SQL */
	public $table_element = 'product_default_warehouse';

	/** @var string $element Name of the element (tip for better integration in Dolibarr: this value should be the reflection of the class name with ucfirst() function) */
	public $element = 'productdefaultwarehouse';


	/** @var int $fk_product  */
	public $fk_product;

	/** @var int $entity Object entity */
	public $fk_entity;

	/** @var int $fk_product  */
	public $fk_warehouse;



	/**
	 *  'type' is the field format.
	 *  'label' the translation key.
	 *  'enabled' is a condition when the field must be managed.
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'default' is a default value for creation (can still be replaced by the global setup of default values)
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'position' is the sort order of field.
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
	 *  'css' is the CSS style to use on field. For example: 'maxwidth200'
	 *  'help' is a string visible as a tooltip on field
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
	 */

	public $fields = array(

		'fk_product' => array(
			'type' => 'integer',
			'label' => 'Entity',
			'enabled' => 1,
			'visible' => 0,
			'default' => 1,
			'notnull' => 1,
			'index' => 1,
			'position' => 20
		),

		'fk_entity' => array(
			'type' => 'integer',
			'label' => 'Entity',
			'enabled' => 1,
			'visible' => 0,
			'default' => 1,
			'notnull' => 1,
			'index' => 1,
			'position' => 20
		),

		'fk_warehouse' => array(
			'type' => 'integer',
			'label' => 'DefaultWarehouse',
			'enabled' => 1,
			'visible' => 0,
			'default' => 1,
			'notnull' => 1,
			'index' => 1,
			'position' => 20
		),
	);


	/**
	 * @param User $user User object
	 * @return int
	 */
	public function save($user)
	{
		return $this->create($user);
	}

	/**
	 * @param User $user User object
	 * @return $this[]
	 */
	public function getEachEntityDefaultWarehouse($fk_product)
	{
		$TFilter = array(
			'fk_product' => $fk_product
		);
		$TRes = $this->fetchAll(0, false, $TFilter);
		$return = array();
		if(!empty($TRes)){
			foreach ($TRes as $object){
				$return[$object->fk_entity] = $object;
			}
		}
		return $return;
	}

	/**
	 * @param $fk_product
	 * @param $fk_entity
	 * @return $this
	 */
	public function fetchWarehouse($fk_product, $fk_entity){
		$fk_product = intval($fk_product);
		$fk_entity = intval($fk_entity);

		$sql = 'SELECT '.$this->getFieldList();
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE fk_product = '.$fk_product.' AND  fk_entity='.$fk_entity; // usage with empty id and empty ref is very rare
		$sql .= ' LIMIT 1'; // This is a fetch, to be sure to get only one record

		$res = $this->db->query($sql);
		if ($res)
		{
			$obj = $this->db->fetch_object($res);
			if ($obj)
			{
				$this->setVarsFromFetchObj($obj);
				return $this->id;
			}
			else
			{
				return 0;
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param	int    $id				Id object
	 * @param	string $ref				Ref
	 * @param	string	$morewhere		More SQL filters (' AND ...')
	 * @return 	int         			<0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchCommon($id, $ref = null, $morewhere = '')
	{
		if (empty($id) && empty($ref) && empty($morewhere)) return -1;

		$sql = 'SELECT '.$this->getFieldList();
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;

		if (!empty($id))  $sql .= ' WHERE rowid = '.$id;
		elseif (!empty($ref)) $sql .= " WHERE ref = ".$this->quote($ref, $this->fields['ref']);
		else $sql .= ' WHERE 1 = 1'; // usage with empty id and empty ref is very rare

		if ($morewhere)   $sql .= $morewhere;
		$sql .= ' LIMIT 1'; // This is a fetch, to be sure to get only one record

		$res = $this->db->query($sql);
		if ($res)
		{
			$obj = $this->db->fetch_object($res);
			if ($obj)
			{
				$this->setVarsFromFetchObj($obj);
				return $this->id;
			}
			else
			{
				return 0;
			}
		}
		else
		{
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
	}


	/**
	 * @param $object Product
	 * @param $fk_entity
	 * @return int
	 */
	public function getWarehouseFromProduct($object, $fk_entity){

		if($object->entity == $fk_entity){
			return $object->fk_default_warehouse;
		}

		$fk_product = $object->id;
		$fk_entity = intval($fk_entity);

		$dPWarehouse = $this->fetchWarehouse($fk_product, $fk_entity);
		if($dPWarehouse>0){
			return $this->fk_warehouse;
		}
		return 0;
	}

	/**
	 * @param $user User
	 * @param $fk_product
	 * @param $fk_entity
	 * @param $fk_warehouse
	 * @return int|resource
	 */
	public function updateWarehouse($user, $fk_product, $fk_entity, $fk_warehouse)
	{
		$fk_product = intval($fk_product);
		$fk_entity = intval($fk_entity);
		$fk_warehouse = intval($fk_warehouse);

		$defaultWarehouse = $this->fetchWarehouse($fk_product, $fk_entity);

		if($defaultWarehouse > 0 && !empty($defaultWarehouse->rowid)){
			$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element . ' SET fk_warehouse = '.$fk_warehouse.'  WHERE rowid = '.$defaultWarehouse->rowid;
			if($this->db->query($sql)){
				return true;
			}else{
				$this->errors[] = $this->db->lasterror;
				return -1;
			}
		}
		else{
			$defaultWarehouse = new self($this->db);
			$defaultWarehouse->fk_product = $fk_product;
			$defaultWarehouse->fk_entity = $fk_entity;
			$defaultWarehouse->fk_warehouse = $fk_warehouse;
			return $defaultWarehouse->save($user);
		}
	}
}
