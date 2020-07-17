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

/**
 * 	\file		core/triggers/interface_99_modMyodule_CliTheobaldtrigger.class.php
 * 	\ingroup	clitheobald
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modClitheobald_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceCliTheobaldtrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'clitheobald@clitheobald';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
	 *
	 * @param string $action code
	 * @param Object $object
	 * @param User $user user
	 * @param Translate $langs langs
	 * @param conf $conf conf
	 * @return int <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	function runTrigger($action, $object, $user, $langs, $conf) {
		//For 8.0 remove warning
		$result=$this->run_trigger($action, $object, $user, $langs, $conf);
		return $result;
	}


    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users
        if ($action == 'USER_LOGIN') {
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        } elseif ($action == 'USER_UPDATE_SESSION') {
            // Warning: To increase performances, this action is triggered only if
            // constant MAIN_ACTIVATE_UPDATESESSIONTRIGGER is set to 1.
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
        } elseif ($action == 'OPERATIONORDER_CREATE') {
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
			/**
			 * @var $object OperationOrder
			 */
            if(!empty($object->array_options['options_fk_dolifleet_vehicule'])){

//            	$vehicule = new doliFleetVehicule($object->db);
//            	$res = $vehicule->fetch($object->array_options['options_fk_dolifleet_vehicule']);
//            	if($res>0){
//					$object->array_options['options_km_on_creation'] = $vehicule->km;
//					$object->update($user, true);
//				}
			}
        } elseif ($action == 'PRODUCT_PRICE_MODIFY' || ($action == 'PRODUCT_CREATE' && !empty($object->price))) {
			if ($conf->orderfromsupplierordermulticompany->enabled)
			{
				/** @var Product $object */
				define('INC_FROM_DOLIBARR', true);
				dol_include_once('/clitheobald/config.php');
				dol_include_once('/orderfromsupplierordermulticompany/class/telink.class.php');

				$linkHelper = new TTELink;
				$PDOdb = new TPDOdb;

				$TLinks = $linkHelper->getList($PDOdb);

				$TOtherEntities = array();

				if (!empty($TLinks))
				{
					foreach ($TLinks as $link)
					{
						if ($link->fk_entity == $conf->entity)
						{
							$masterLink = $link;
						}
						else if (!empty($link->fk_entity)) $TOtherEntities[] = $link;
					}
				}

				if (!empty($masterLink) && !empty($masterLink->fk_soc) && !empty($TOtherEntities))
				{
					$fournId = $masterLink->fk_soc;

					$fourn = new Societe($this->db);
					$ret = $fourn->fetch($fournId);
					if ($ret <= 0)
					{
						setEventMessage("Impossible de récupérer la société associée à l'entité courante", 'errors');
						return 0;
					}

					$refFourn = $object->ref;
					$qty = 1;
					$tva_tx = $object->tva_tx;
					$price = $object->price;

					// majoration pour les entités filles
					$majPercent = (int) $conf->global->THEO_PRICE_MAJORATION_PERCENT_ON_PRODUCT_PRICE_MODIFY;
					if (!empty($majPercent) && is_numeric($conf->global->THEO_PRICE_MAJORATION_PERCENT_ON_PRODUCT_PRICE_MODIFY))
					{
						$price = round($object->price + ($object->price * $majPercent / 100), 2);
					}

					$priceBaseType = $object->price_base_type;

					foreach ($TOtherEntities as $otherLink)
					{
						$conf->entity = $otherLink->fk_entity;

						$fournPrice = new ProductFournisseur($this->db);
						$fournPrice->fetch($object->id);

						/**
						 * Récupérer le prix d'achat présent sur l'entité s'il existe
						 * fournPrice->add_fournisseur retourne :
						 * - "-1" ou "-2" en cas d'erreur sql
						 * - "-3" si un prix existe avec la ref_fourn indiquée mais pour un produit différent (dont l'id est dans l"attribut fournPrice->product_id_already_linked)
						 * - "0" et garnie fournPrice->product_fourn_price_id, si le prix existe déjà pour ce produit et ce fournisseur
						 * - "1" et garnie fournPrice->product_fourn_price_id, si le prix n'existait pas il le crée mais vide et y a plus qu"à l'update
						 */

						$ret = $fournPrice->add_fournisseur($user, $fournId, $refFourn, $qty);

						if ($ret == -3) // la référence fournisseur prix existe pour un autre produit
						{
							$prod = new Product($this->db);
							$prod->fetch($fournPrice->product_id_already_linked);

							setEventMessage($langs->trans("ErrorExistingPrice", $refFourn, $conf->entity, $prod->ref), "errors");
						}
						else if ($ret < 0) // erreur
						{
							setEventMessage($langs->trans("ErrorCreationPriceEntity", $conf->entity, $fournPrice->error), "errors");
						}

						if ($ret >= 0) // prix existant
						{
							$fournPrice->fetch_product_fournisseur_price($fournPrice->product_fourn_price_id);
							$fournPrice->update_buyprice(
								$qty
								,$price
								,$user
								,$priceBaseType
								,$fourn
								,$fournPrice->fk_availability
								,$fournPrice->ref_supplier
								,$tva_tx
								,$fournPrice->fourn_charges
								,$fournPrice->fourn_remise_percent
								,$fournPrice->fourn_remise
								,$fournPrice->fourn_tva_npr
								,$fournPrice->delivery_time_days
								,$fournPrice->supplier_reputation
								,array()
								,''
								,0
								,'HT'
								,1
								,''
								,$fournPrice->description
								,$fournPrice->barcode
								,$fournPrice->barcode_type
							);
						}

					}

					$conf->entity = $masterLink->fk_entity;
				}

			}
        }
        return 0;
    }
}
