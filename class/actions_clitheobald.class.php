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

dol_include_once('/clitheobald/lib/clitheobald.lib.php');


/**
 * \file    class/actions_clitheobald.class.php
 * \ingroup clitheobald
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsCliTheobald
 */
class ActionsCliTheobald
{
    /**
     * @var DoliDb		Database handler (result of a new DoliDB)
     */
    public $db;

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
     * @param DoliDB    $db    Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Overloading the recurciveAddChildLines function : replacing the parent's function with the one below
	 *
	 * @param array()         $parameters     Hook metadatas (context, etc...)
	 * @param OperationOrder $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string $action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 * @throws Exception
	 */
	public function recurciveAddChildLines($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $db;

        $contextArray = explode(':', $parameters['context']) ;

		// seulement les operations orders
		if($object->element != 'operationorder'){
			return 0;
		}

		$childLineProduct 	= $parameters['childLineProduct'];
		$fk_line_parent 	= $parameters['fk_line_parent'];
		$fk_product 		= $parameters['fk_product'];
		$qty 				= $parameters['qty'];
		$newLineQty 		= $parameters['newLineQty'];
		$nb 				= $parameters['nb'];
		$product_info 		= $parameters['product_info'];
		$timePlanned 		= $parameters['timePlanned'];

		/** @var $childLineProduct Product */
		if(!empty($childLineProduct->type)){
			// si produit n'est pas de type produit alors pas besoin de surchager
			return 0;
		}

		if(!class_exists('DefaultProductWarehouse')){
			include_once __DIR__ . '/defaultproductwarehouse.class.php';
		}

		$dProductWarehouse = new DefaultProductWarehouse($db);
		$fk_default_warehouse = $dProductWarehouse->getWarehouseFromProduct($childLineProduct ,$object->entity);
		if($fk_default_warehouse < 1){
			// si pas de stock pas defaut trouvé on prend reste sur le comportement normale
			$fk_default_warehouse = $childLineProduct->fk_default_warehouse;
		}

		$childLineProduct->fk_default_warehouse = $fk_default_warehouse; // vu que les lignes enfants se basse sur le produit parent, on force le fk_default_warehouse du produit

//		var_dump(array(
//			$childLineProduct->ref,
//			'entrepot '.$childLineProduct->fk_default_warehouse,
//			'entite '.$object->entity
//		));

		// Ajout de la ligne
		$newLineRes = $object->addline(
			'',
			$newLineQty,
			$childLineProduct->price,
			$fk_default_warehouse,
			0,
			$timePlanned,
			0,
			$childLineProduct->id,
			0,
			'',
			'',
			$childLineProduct->type,
			-1,
			0,
			$fk_line_parent,
			'',
			array(),
			'',
			0
		);

		if($newLineRes>0){
			$recusiveRes = $object->recurciveAddChildLines($newLineRes, $childLineProduct->id, $newLineQty);
			if($recusiveRes<0){
				$object->errors[] = $langs->transnoentities('RecurciveLineaddFail');
				return -2;
			}
		}
		else
		{
			$object->errors[] = $langs->transnoentities('LineaddFail');
			return -1;
		}

		if($newLineRes>0){
			return 1;
		}else{
			return -1;
		}
	}


	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $user, $db;

        $langs->load('clitheobald@clitheobald');

        $contextArray = explode(':', $parameters['context']) ;

		/*
		 * FILE OVERRIDE
		 * Put here redirrections to Dolibarr files replacements
		 */
		if (!in_array('ordersupplierdispatchtheobald', $contextArray ) && in_array('ordersupplierdispatch', $contextArray ))
		{
			$_SESSION['POSTDATA'] = $_POST;
			$url = dol_buildpath('clitheobald/htdocs/fourn/commande/dispatch.php', 1);
			header('Location: ' . $url.'?'.$_SERVER['QUERY_STRING']);
		}

		/*
		 * REAL DO ACTIONS
		 */

		if (in_array('operationorder', $contextArray ) && $object->element == 'operationorder')
		{
			/**
			 * @var $object OperationOrder
			 */
			// Add note on card for accessibility
			$object->fields['note_public']['visible'] = 1;
		}

		if (in_array('actioncard', $contextArray ))
		{
			if ($action == 'createORFromEvent')
			{
				$error = 0;

				if (empty($conf->global->OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT) || $conf->global->OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT == '-1')
				{
					setEventMessage($langs->trans('ErrORStatusConfiguration'), 'errors');
					$error++;
				}

				if (empty($object->array_options['options_fk_vehicule'])) {
					setEventMessage($langs->trans('ErrNoVehiculeLinkedToEvent'), 'errors');
					$error++;
				}

				if (empty($object->array_options['options_fk_product'])) {
					setEventMessage($langs->trans('ErrNoOperationLinkedToEvent'), 'errors');
					$error++;
				}

				if ($error)
				{
					header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
					exit;
				}

				// l'event est fetché avant le doActions
				/**
				 * @var ActionComm $object
				 */
				if (
					!empty($object->id)
					&& $object->code === 'AC_OR'
					&& $object->percentage == 0
					&& !empty($object->array_options['options_fk_vehicule'])
					&& !empty($object->array_options['options_fk_product'])
					&& $conf->operationorder->enabled
					&& $conf->dolifleet->enabled
				)
				{
//					$this->db->begin();

					dol_include_once('/dolifleet/class/vehicule.class.php');
					$veh = new doliFleetVehicule($this->db);
					$ret = $veh->fetch($object->array_options['options_fk_vehicule']);

					if ($ret <= 0)
					{
						setEventMessage($langs->trans('ErrCanFetchVehicule', $object->array_options['options_fk_vehicule']), 'errors');
						header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
						exit;
					}

					dol_include_once('/operationorder/class/operationorder.class.php');
					$OR = new OperationOrder($this->db);

					$OR->fk_soc = $veh->fk_soc;
					$OR->date_operation_order = dol_now();
					$OR->array_options['options_fk_dolifleet_vehicule'] = $veh->id;

					$ret = $OR->save($user);

					if ($ret <= 0)
					{
						setEventMessages($langs->trans('OperationOrderCreationError'), $OR->errors, 'errors');
					}
					else
					{

					    //récup du code paramétré dans l'entité de l'OR traité
                        $sql = "SELECT value as code FROM ".MAIN_DB_PREFIX."const WHERE name ='OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT' AND entity = '". $OR->entity."'";
                        $resql = $db->query($sql);

                        if($resql)
                        {
                            $obj = $db->fetch_object($resql);

                            $sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."operationorder_status WHERE code ='".$obj->code."' AND entity = '".$OR->entity."'";
                            $resql = $db->query($sql);
                            if ($resql)
                            {
                                $obj = $db->fetch_object($resql);
                                $fk_status = $obj->id;

                                $OR->setStatus($user, $fk_status);
                                setEventMessage($langs->trans('OperationOrderCreationSuccess', $OR->ref));
                            }
                        } else {
                            setEventMessage($langs->trans('OperationOrderCreationErrorConf'));
                        }

						// création de l'OR effectuée, maintenant on ajoute le service
						$prod = new Product($this->db);
						$retProd = $prod->fetch($object->array_options['options_fk_product']);
						if ($retProd > 0)
						{
							$retadd = $OR->addline($prod->description, 1, $prod->price, 0, 0, 0, 0, $prod->id);
							if ($retadd > 0) {
								$retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);
							}
							else
							{
								setEventMessages($langs->trans('OperationOrderAddlineError'), $OR->errors, 'errors');
							}
//							var_dump($retrec); exit;
						}

						$object->elementtype = 'operationorder';
						$object->fk_element = $ret;
						$object->percentage = 100;
						$object->update($user);
					}

					header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
					exit;

//					$this->db->rollback();

				}
			}
		}

        if(in_array('vehiculelist', $contextArray ))
        {
            dol_include_once('/operationorder/class/operationorder.class.php');
            dol_include_once('operationorder/lib/operationorder.lib.php');

            //MASSACTION "CREATION OR" : après validation produit première popin, vérifie si il existe déjà des OR de ce produit pour ce véhicule
            if ($action == 'addline')
            {
                $TVehicles = $_SESSION['toselect'];

                $fk_product = GETPOST('fk_product');

                //on enregistre en session les données renseignées pour la suite
                $_SESSION['POST_addline'] = $_POST;

                $TVehicleToConfirm = array();                                               //véhicules confirmés : pour lesquels il faut créer l'OR automatiquement
                $TVehicleConfirmed = array();                                               //véhicules à confirmer par l'utilisateur

                foreach ($TVehicles as $idvehicle)
                {

                    $res = getORByProductAndByVehicle($idvehicle, $fk_product);

                    if (is_array($res)) $TVehicleToConfirm[] = intval($idvehicle);

                    elseif ($res == 0) $TVehicleConfirmed[] = intval($idvehicle);

                    else return -1;

                }

                //on enregistre les véhicules dans la session pour la suite
                $_SESSION['TVehicleToConfirm'] = $TVehicleToConfirm;
                $_SESSION['TVehicleConfirmed'] = $TVehicleConfirmed;

                //si pas de véhicules à confirmer par l'utilisateur, création des OR directement
                if(empty($TVehicleToConfirm))
                {
                    header('Location: '.$_SERVER['PHP_SELF'].'?action=confirm');
                }

                return 1;
            }


            //MASSACTION "CREATION OR" : création des OR pour tous les véhicules confirmés
            elseif ($action == 'confirm')
            {

                $error = 0;

                $db->begin();

                $TVehiclesConfirmed = $_SESSION['TVehicleConfirmed'];

                $_POST = $_SESSION['POST_addline'];     //informations renseignées précédemment par l'utilisateur dans la première popin

                $TVehiclesConfirmedByUser = array_keys($_GET, 'on');        //récupération de tous les véhicules confirmés par l'utilisateur dont un OR est déjà existant

                foreach ($TVehiclesConfirmedByUser as $idvehicle)
                {
                    $TVehiclesConfirmed[] = intval($idvehicle);
                }

                foreach ($TVehiclesConfirmed as $vehicleid)
                {
                    $res = $object->fetch($vehicleid);

                    //création de l'OR
                    if ($res)
                    {

                        $fk_c_operationorder_type = GETPOST('fk_c_operationorder_type');
                        $OR = new OperationOrder($db);

                        $OR->fk_soc = $object->fk_soc;
                        $OR->date_operation_order = dol_now();
                        $OR->array_options['options_fk_dolifleet_vehicule'] = $object->id;
                        $OR->fk_c_operationorder_type = $fk_c_operationorder_type;

                        $ret = $OR->save($user);

                        //changement de statut
                        $sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."operationorder_status WHERE code = 'TO_PLAN'";
                        $resql = $db->query($sql);
                        if($resql)
                        {
                            $obj = $db->fetch_object($resql);
                            $OR->setStatus($user, $obj->id);
                        }

                        if ($ret > 0)
                        {
                            $res = $OR->fetch($ret);

                            //ajout du produit dans l'OR
                            if ($res > 0)
                            {

                                $fk_product = GETPOST('fk_product');

                                $product_desc = (GETPOST('description') ?GETPOST('description') : '');
                                $time_plannedhour = intval(GETPOST('time_plannedhour', 'int'));
                                $time_plannedmin = intval(GETPOST('time_plannedmin', 'int'));
                                $time_spenthour = intval(GETPOST('time_spenthour', 'int'));
                                $time_spentmin = intval(GETPOST('time_spentmin', 'int'));
                                $qty = GETPOST('qty'.$predef);
                                $price = GETPOST('price'.$predef);
                                $fk_warehouse = GETPOST('fk_warehouse');
                                $pc = GETPOST('pc'.$predef);
                                $date_start = dol_mktime(GETPOST('date_start'.$predef.'hour'), GETPOST('date_start'.$predef.'min'), GETPOST('date_start'.$predef.'sec'), GETPOST('date_start'.$predef.'month'), GETPOST('date_start'.$predef.'day'), GETPOST('date_start'.$predef.'year'));
                                $date_end = dol_mktime(GETPOST('date_end'.$predef.'hour'), GETPOST('date_end'.$predef.'min'), GETPOST('date_end'.$predef.'sec'), GETPOST('date_end'.$predef.'month'), GETPOST('date_end'.$predef.'day'), GETPOST('date_end'.$predef.'year'));
                                $label = (GETPOST('product_label') ? GETPOST('product_label') : '');


                                $idLine = addLineAndChildToOR(
                                        $OR
                                        ,$fk_product
                                        ,$qty
                                        ,$price
                                        ,$type
                                        ,$product_desc
                                        ,$predef
                                        ,$time_plannedhour
                                        ,$time_plannedmin
                                        ,$time_spenthour
                                        ,$time_spentmin
                                        ,$fk_warehouse
                                        ,$pc
                                        ,$date_start
                                        ,$date_end
                                        ,$label
                                );

                                if ($idLine <= 0) $error++;
                                else {

                                    $vehicule = new doliFleetVehicule($db);
                                    $res = $vehicule->fetch($vehicleid);

                                    if($res) setEventMessage($langs->trans('ConfirmORCreationVehicle', $OR->getNomUrl(), $vehicule->getNomUrl()));
                                }

                            }
                        }
                        else
                        {
                            $error++;
                        }
                    }

                }

                if (!$error) {
                    $db->commit();
                    header('Location: '.$_SERVER['PHP_SELF']);
                    exit;
                }
                else {
                    $db->rollback();
                    setEventMessage('Error', 'errors');
                    return -1;
                }
            }

        }

        else if (in_array('oordermanagerinterface', $contextArray))
		{
			if ($action == "getORLines")
			{
				$orBarcode = GETPOST('or_barcode');
				$orRef = substr($orBarcode, 2);
				$OR = new OperationOrder($db);
				$OR->fetchBy($orRef, 'ref');
				$OR->fetchLines();

				$parameters['data']['oOrderLines'] = array();

				if (!empty($OR->lines))
				{
					$TPointable = $ProdErrors = $TLastLines = array();

					$alreadyUsed = array();
					$sql = "SELECT mvt.fk_product, SUM(mvt.value) as total FROM ".MAIN_DB_PREFIX."stock_mouvement as mvt";
					$sql.= " WHERE mvt.origintype = 'operationorder'";
					$sql.= " AND mvt.fk_origin = ".$OR->id;
					$sql.= " GROUP BY mvt.fk_product";

					$resql = $db->query($sql);
					if ($resql)
					{
						while ($obj = $db->fetch_object($resql))
						{
							$alreadyUsed[$obj->fk_product] = abs($obj->total);
						}
					}

					// récupération de la dernière ligne de chaque produit pour affichage sortie de stock
					foreach ($OR->lines as $line)
					{
						if ($line->fk_product)
						{
							$TLastLines[$line->fk_product] = $line->id;
						}
					}

					foreach ($OR->lines as $line)
					{

						// Pour les non pointables, vérifier si un code barre existe, s'il y a un entrepot par défaut...
						if ($line->fk_product && ! array_key_exists(intval($line->fk_product), $TPointable))
						{
							$TPointable[$line->fk_product] = false;
							$line->fetch_product();
							//$parameters['data']['debug'][$line->fk_product] = $line->product->array_options;
							if ($line->product->array_options['options_or_scan'] == "1")
							{
								$TPointable[$line->fk_product] = true;
							}
							else if ($line->product->type == Product::TYPE_SERVICE) continue;

							$ProdErrors[$line->fk_product] = '';

							if (empty($conf->global->STOCK_SUPPORTS_SERVICES) && $line->product->type == Product::TYPE_SERVICE && !$TPointable[$line->fk_product])
								$ProdErrors[$line->fk_product].=$langs->trans('ErrorStockMVTService')."<br />";

							if (empty($line->product->barcode))
								$ProdErrors[$line->fk_product].=$langs->trans('ErrorProductHasNoBarCode')."<br />";

							dol_include_once('/clitheobald/class/defaultproductwarehouse.class.php');
							$dpw = new DefaultProductWarehouse($db);
							$defaultWarhouse = $dpw->getWarehouseFromProduct($line->product, $conf->entity);
//							$parameters['data']['debug'][$line->product->ref] = $defaultWarhouse;
							if ($defaultWarhouse <= 0) $ProdErrors[$line->fk_product].=$langs->trans('ErrorNoDefaultWarehouse')."<br />";
						}

						$used = 0;
						if (isset($alreadyUsed[$line->fk_product]))
						{
							if ($alreadyUsed[$line->fk_product] > $line->qty)
							{
								if ($TLastLines[$line->fk_product] != $line->id)
								{
									$used = $line->qty;
									$alreadyUsed[$line->fk_product] -= $line->qty;
								}
								else
								{
									$used = $alreadyUsed[$line->fk_product];
									unset($alreadyUsed[$line->fk_product]);
								}
							}
							else
							{
								$used = $alreadyUsed[$line->fk_product];
								unset($alreadyUsed[$line->fk_product]);
							}
						}

						$parameters['data']['oOrderLines'][] = array(
							'ref' 		=> $line->product_ref
							,'qty' 		=> $line->qty
							,'qtyUsed'	=> $used
							,'action' 	=> $TPointable[$line->fk_product] ? "Démarrer" : (empty($ProdErrors[$line->fk_product]) ? "Sortie de stock" : $ProdErrors[$line->fk_product])
							,'barcode' 	=> 'LIG'.$line->id
							,'bars'		=> $TPointable[$line->fk_product] ? displayBarcode('LIG'.$line->id) : ""
							,'pointable'=> $TPointable[$line->fk_product]
						);
					}
				}
				$parameters['data']['result'] = 1;
				return 1;
			}
			else if ($action == 'stockMouvement')
			{
				$or_barcode = GETPOST('or_barcode');
				$lig = GETPOST('lig');

				$prod_barcode = GETPOST('prod');
				$prod = new Product($db);
				$ret = $prod->fetch('','','', $prod_barcode);
				dol_include_once('/clitheobald/class/defaultproductwarehouse.class.php');
				$dpw = new DefaultProductWarehouse($db);
				$prod->fk_default_warehouse = $dpw->getWarehouseFromProduct($prod, $conf->entity);

//				$parameters['data']['debug'] = $prod->id;
				if ($ret > 0)
				{
					$orRef = substr($or_barcode, 2);

					$OR = new OperationOrder($db);
					$ret = $OR->fetchBy($orRef, 'ref');
					if ($ret > 0)
					{
						$alreadyUsed = array();
						$sql = "SELECT mvt.fk_product, SUM(mvt.value) as total FROM ".MAIN_DB_PREFIX."stock_mouvement as mvt";
						$sql.= " WHERE mvt.origintype = 'operationorder'";
						$sql.= " AND mvt.fk_origin = ".$OR->id;
						$sql.= " GROUP BY mvt.fk_product";

						$resql = $db->query($sql);
						if ($resql)
						{
							while ($obj = $db->fetch_object($resql))
							{
								$alreadyUsed[$obj->fk_product] = abs($obj->total);
							}
						}

//						$parameters['data']['debug'] = $sql;

						$OR->fetchLines();
						if (!empty($OR->lines))
						{
							$prodTotalQty = 0;
							$found = false;
							foreach ($OR->lines as $line)
							{
								if ($line->fk_product == $prod->id) {

									$found = true;
									$prodTotalQty+=$line->qty;
//							$parameters['data']['debug'] = $line;
								}
							}

							if ($found)
							{
//						$parameters['data']['debug'] = "product found";

								if (empty($conf->global->STOCK_SUPPORTS_SERVICES) && $prod->type == Product::TYPE_SERVICE)
								{
									$parameters['data']['errorMsg'] = $langs->trans('ErrorStockMVTService');
								}
								else
								{

									//$parameters['data']['debug'][$line->product->ref] = $prod->fk_default_warehouse;
									if ($prod->fk_default_warehouse <= 0) $prod->fk_default_warehouse = 0;

									if (empty($prod->fk_default_warehouse)) $parameters['data']['errorMsg'] = $langs->trans('ErrorNoDefaultWarehouse');
									else
									{
										// création de mouvement de stock
										$mvt = new MouvementStock($db);
										$mvt->origin = $OR;

										$qty = 1;

										if (!empty($conf->global->PRODUCT_USE_UNITS))
										{
											if (!empty($prod->fk_unit) && $prod->fk_unit != 1) // pièce
												$qty = $prodTotalQty;
										}

										$qtyAfterMvt = (float) $alreadyUsed[$prod->id] + (float) $qty;
//										$parameters['data']['debug'].= $qtyAfterMvt .' , '. (float) $alreadyUsed[$prod->id] .' , '. (float) $qty;

										if ($qtyAfterMvt > $prodTotalQty && !empty($conf->global->OPODER_CANT_EXCEED_SENT_QTY))
										{
											$parameters['data']['errorMsg'] = $langs->trans('ErrorProductqtyExceded');
										}
										else
										{
											$mvt->livraison($user, $prod->id, $prod->fk_default_warehouse, $qty, 0, $langs->trans('productUsedForOorder', $OR->ref));
											$parameters['data']['result'] = 1;
											$parameters['data']['msg'] = $langs->trans('StockMouvementGenerated', $prod->ref);
										}

									}

								}
							}
							else
							{
								// le produit n'existe pas, on le crée si la conf est activée
								if (!empty($conf->global->OPODER_ADD_PRODUCT_IN_OR_IF_MISSING))
								{
									if ($prod->fk_default_warehouse <= 0) $prod->fk_default_warehouse = 0;

									if (empty($prod->fk_default_warehouse)) $parameters['data']['errorMsg'] = $langs->trans('ErrorCannotAddProductNoDefaultWarehouse', $prod->ref, $OR->ref);
									else
									{
										$ret = $OR->addline('',1, $prod->price, $prod->fk_default_warehouse, 1, 0, 0, $prod->id);
										if ($ret > 0)
										{
											// une fois le produit ajouté, on fait la sortie de stock
											$parameters['data']['msg'] = $langs->trans('ProductAddedToOR', $prod->ref, $OR->ref);

											$mvt = new MouvementStock($db);
											$mvt->origin = $OR;

											$qty = 1;

											if (!empty($conf->global->PRODUCT_USE_UNITS))
											{
												if (!empty($prod->fk_unit) && $prod->fk_unit != 1) // pièce
													$qty = $prodTotalQty;
											}

											$mvt->livraison($user, $prod->id, $prod->fk_default_warehouse, $qty, 0, $langs->trans('productUsedForOorder', $OR->ref));
											$parameters['data']['result'] = 1;
											$parameters['data']['msg'].= '<br />'.$langs->trans('StockMouvementGenerated', $prod->ref);
										}
										else
										{
											$parameters['data']['errorMsg'] = $langs->trans('ErrorAddProductInOR', $prod->ref, $OR->ref);
										}
									}
								}
								else
								{
									$parameters['data']['errorMsg'] = $langs->trans('ErrorProductMissingInOR', $prod->ref, $OR->ref);
								}
							}
						}
					}
					else
					{
						$parameters['data']['errorMsg'] = $langs->trans('ErrorCantFetchOR');
					}
				}
				else
				{
					$parameters['data']['errorMsg'] = $langs->trans('ErrorNoProdWithThisBarcode', $prod_barcode);
				}

				return 1;

			}
		}

		return 0;
	}

	/**
	 * Overloading the doActionsAfterAddSupplierOrderFromLine function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActionsAfterAddSupplierOrderFromLine($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		$langs->load('banks');

        $contextArray = explode(':', $parameters['context']) ;

		if (in_array('operationordercard', $contextArray ) && $object->element == 'operationorder' && $action == 'create-supplier-order')
		{
			$supplierOrder = $parameters['supplierOrder'];
			/**
			 * @var $supplierOrder CommandeFournisseur
			 */
			$supplierOrder->array_options['options_supplier_order_type'] = '1';
			if($supplierOrder->insertExtraFields()<0){
				setEventMessage($supplierOrder->error, 'errors');
			}

			if(!empty($object->array_options['options_fk_dolifleet_vehicule']))
			{
				// Lors de création de la commande fournisseur, ajouter dans la note publique de la commande fournisseur :
				// le VIN, Immat du véhicule concerné et le numéro de l'OR
				$langs->load('dolifleet@dolifleet');
				dol_include_once('dolifleet/class/vehicule.class.php');
				$vehicule = new doliFleetVehicule($object->db); // use to get table of element
				$res = $vehicule->fetch($object->array_options['options_fk_dolifleet_vehicule']);
				if($res>0) {
					$noteAppend = '';
					if(!empty($supplierOrder->note_public)){
						$noteAppend.= '<br/>';
					}

                    $noteAppend.= '<br/>'.$langs->trans('ORRef').' : ' .$object->ref;

					$input = 'vin';
					$noteAppend.= '<br/>'.$langs->trans($vehicule->fields[$input]['label']).' : ' .$vehicule->showOutputFieldQuick($input);

					$input = 'immatriculation';
					$noteAppend.= '<br/>'.$langs->trans($vehicule->fields[$input]['label']).' : ' .$vehicule->showOutputFieldQuick($input);

					//Informations Emetteur (contact qui créé la commande)
					$noteAppend.= '<br/>'.$langs->trans('CheckTransmitter').' : ' . $user->firstname . ' ' . $user->lastname;

					if(!empty($user->office_phone) || !empty($user->user_mobile)) {
					    if(!empty($user->office_phone) && !empty($user->user_mobile)){
                            $noteAppend.= ' ( ' . $user->office_phone .' / ' . $user->user_mobile . ' )';
                        } elseif(!empty($user->office_phone) && empty($user->user_mobile)){
                            $noteAppend.= ' ( ' . $user->office_phone . ' )';
                        } elseif(empty($user->office_phone) && !empty($user->user_mobile)){
                            $noteAppend.= ' ( ' . $user->user_mobile . ' )';
                        }
                    }

					$supplierOrder->update_note_public($supplierOrder->note_public.$noteAppend);
				}
			}
		}

		return 0;
	}

    /**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
	    global $langs;

		$langs->load('clitheobald@clitheobald');

        $contextArray = explode(':', $parameters['context']) ;

        if (in_array('vehiculecard', $contextArray ))
        {
            print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_buildpath('/operationorder/operationorder_card.php', 1).'?action=create&mainmenu=commercial&fk_soc='.$object->fk_soc.'&options_fk_dolifleet_vehicule='.$object->id.'">'.$langs->trans("cliCreateOperationOrderFromVehicule").'</a></div>'."\n";
        }

		if (in_array('operationordercard', $contextArray ))
		{
			?>
			<script type="text/javascript">
				$(document).ready(function () {

					$("#fk_product").on('change', function(){

						var fk_product = $(this).val();


						$.ajax({
							url: "<?php echo dol_buildpath('/clitheobald/script/interface.php', 1) ?>"
							, data: {
								get: 'get-product-add-info'
								,fk_operationOrder : <?php echo intval($object->id); ?>
								,fk_product : fk_product
							}
							, dataType: "json"
							// La fonction à appeler si la requête n'a pas abouti
							,error: function( jqXHR, textStatus ) {
								alert( "Request failed: " + textStatus );
							}
						}).done(function (data) {
							if(data.result>0) {
								// Your code on success

								for(var i= 0; i < data.operationorders.length; i++){
									let item = data.operationorders[i];
									$.jnotify(item.htmlAlert,"warning", true,{ remove: function (){}});
								}

							}
							else{
								// Your code on fail
							}

							if(data.warningMsg.length>0) {
								// display warnings from script
								$.jnotify(data.warningMsg,"warning", true,{ remove: function (){}});
							}

							if(data.errorMsg.length>0) {
								// display errors from script
								$.jnotify(data.errorMsg,"error", true,{ remove: function (){}});
							}
						});

					});
				});
			</script>
			<?php
		}

		if (in_array('actioncard', $contextArray ))
		{
			dol_include_once('/clitheobald/lib/clitheobald.lib.php');
			/**
			 * @var ActionComm $object
			 */
			if (
				$object->element === 'action'
				&& $object->code === 'AC_OR'
				&& $object->percentage == 0
			)
			{
				$error = 0;

				// vérifier la présence d'un véhicule et d'une opération => btnTitle si problème
				$btnLabel = $langs->trans('cliCreateOperationOrderFromVehicule');
				$btnTitle = '';

				if (empty($object->array_options['options_fk_vehicule'])) {
					$btnTitle.= $langs->trans('ErrNoVehiculeLinkedToEvent');
					$error++;
				}

				if (empty($object->array_options['options_fk_product'])) {
					$btnTitle.= (!empty($error) ? '<br>' : '') . $langs->trans('ErrNoOperationLinkedToEvent');
					$error++;
				}

				$btnClass = 'butAction';
				$btnHref = $_SERVER['PHP_SELF'] . '?action=createORFromEvent&id='.$object->id;

				if ($error)
				{
					$btnClass = 'butActionRefused classfortooltip';
					$btnHref = '#';
				}

				print '<div class="inline-block divButAction"><a class="'.$btnClass.'" href="'.$btnHref.'" '.(empty($btnTitle) ? '' : 'title="'.$langs->trans("NotAllowed").'<br>'.$btnTitle.'"').'>'.$btnLabel.'</a></div>';

			}
		}

        return 0;
    }

    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
    	global $langs;
    	$langs->load('clitheobald@clitheobald');
        $action = GETPOST('action');

        $contextArray = explode(':', $parameters['context']) ;

        if (in_array('operationordercard', $contextArray ))
        {
            if($action =='create'){

                $fk_soc = GETPOST('fk_soc');
                $options_fk_dolifleet_vehicule = GETPOST('options_fk_dolifleet_vehicule');

                //Preselect fk_soc of vehicule selected
                ?>
                <script type="text/javascript">
                    $(document).ready(function () {

                        $("#options_fk_dolifleet_vehicule").on('change', function(){

                            var vehicule_id = $(this).val();

                            // Update fk_soc
                            $.ajax({
                                url: "<?php echo dol_buildpath('/clitheobald/script/interface.php', 1) ?>"
                                , data: {
                                    get: 'get-fksoc-of-vehicule'
                                    , vehicule_id : vehicule_id
                                }
                                , dataType: "json"
								// La fonction à appeler si la requête n'a pas abouti
								,error: function( jqXHR, textStatus ) {
									alert( "Request failed: " + textStatus );
								}
                            }).done(function (data) {
								if(data.result>0) {
									$("#fk_soc").val(data.societe.id);
									$("#search_fk_soc").val(data.societe.name);
									$("#fk_soc").change();
									//$("#options_km_on_creation").val(data.vehicule.km);
								}
								else{
									$("#fk_soc").val("");
									$("#search_fk_soc").val("");
									$("#fk_soc").change();
									//$("#options_km_on_creation").val("");
								}

								if(data.errorMsg.length>0) {
									$.jnotify(data.errorMsg,"error", true,{ remove: function (){}});
								}
								if(data.warningMsg.length>0) {
									$.jnotify(data.warningMsg,"warning", true,{ remove: function (){}});
								}
                            });

							// Update km from vehicule
							$.ajax({
								url: "<?php echo dol_buildpath('/clitheobald/script/interface.php', 1) ?>"
								, data: {
									get: 'get-info-of-vehicule'
									, vehicule_id : vehicule_id
								}
								, dataType: "json"
								// La fonction à appeler si la requête n'a pas abouti
								,error: function( jqXHR, textStatus ) {
									alert( "Request failed: " + textStatus );
								}
							}).done(function (data) {
								if(data.result>0) {
									$("#options_km_on_creation").val(data.vehicule.km);
									$("#options_km_on_creation").trigger('change');
								}
								else{
									$("#options_km_on_creation").val("");
								}

								if(data.errorMsg.length>0) {
									$.jnotify(data.errorMsg,"error", true,{ remove: function (){}});
								}
							});

							// update type contract list
							$.ajax({
								url: "<?php echo dol_buildpath('/clitheobald/script/interface.php', 1) ?>"
								, data: {
									get: 'get-operationorder-info-from-vehicule'
									, vehicule_id : $("#options_fk_dolifleet_vehicule").val()
									, fk_operationOrder : <?php echo intval($object->id); ?>
								}
								, dataType: "json"
								// La fonction à appeler si la requête n'a pas abouti
								,error: function( jqXHR, textStatus ) {
									alert( "Request failed: " + textStatus );
								}
							}).done(function (calldata) {

								var target = $("#fk_c_operationorder_type");

								var lastTargetValue = target.val();

								/* Remove all options from the select list */
								target.empty();
								target.prop("disabled", true);

								if(Array.isArray(calldata.operationorder_type))
								{
									var data = calldata.operationorder_type;

									// empty field
									let newOption =  $('<option>', {
										value: -1,
										text : '&nbsp;'
									});
									target.append(newOption);


									/* Insert the new ones from the array above */
									for(var i= 0; i < data.length; i++)
									{
										let item = data[i];
										let newOption =  $('<option>', {
											value: item.id,
											text : item.label
										});

										target.append(newOption);
									}

									target.val(lastTargetValue).trigger('change');

									if(data.length > 0){
										target.prop("disabled", false);
									}
								}
							});
                        });


						$("#options_fk_dolifleet_vehicule").change();

						$("#options_km_on_creation").on('change', function(e){
							$.ajax({
								url: "<?php echo dol_buildpath('/clitheobald/script/interface.php', 1) ?>"
								, data: {
									get: 'get-info-of-vehicule'
									, vehicule_id : $("#options_fk_dolifleet_vehicule").val()
								}
								, dataType: "json"
								// La fonction à appeler si la requête n'a pas abouti
								,error: function( jqXHR, textStatus ) {
									alert( "Request failed: " + textStatus );
								}
							}).done(function (data) {
								$('#alert_km_on_creation').remove();
								if(data.result>0) {
									if(parseFloat(data.vehicule.km) > parseFloat($("#options_km_on_creation").val())) {
										$("#options_km_on_creation").after('<i id="alert_km_on_creation" class="fa fa-exclamation-triangle" title="<?php echo $langs->trans("KmIsInferior"); ?>" style="color: orangered;" aria-hidden="true"></i>');
										$('#alert_km_on_creation').tooltip();
									}
								}

								if(data.errorMsg.length>0) {
									$.jnotify(data.errorMsg,"error", true,{ remove: function (){}});
								}
							});
						});
					});
                </script>
                <?php
            } else {
                ?>

                <script type="text/javascript">

                    $(document).ready(function () {

                        var vinfield = $(".trextrafields_collapse").first();
                        $(".trcommonfield_vin").replaceWith(vinfield);

                        $(".trcommonfield_note_public").closest("tr").before($(".trcommonfield_fk_c_operationorder_type"));
                        $(".trcommonfield_note_public").closest("tr").after($(".trextrafields_collapse").last());
                        $(".trcommonfield_note_public").closest("tr").after($(".trcommonfield_date_end_contract"));
                        $(".trcommonfield_note_public").closest("tr").after($(".trcommonfield_fk_contract_type"));

                    });

                </script>

                <?php
            }
        } elseif (in_array('operationorderplanning', $contextArray )){

            //ajout d'un eventsources dans le planning : affichage des pastilles pour signaler la présence d'événements de type OR au statut "à faire"
            ?>
            <script>
                operationOrderToPLannedInterfaceUrl = "<?php print dol_buildpath('/clitheobald/script/interface.php', 1); ?>?action=getToPlannedOperationOrder";

                eventSources_parameters.push({
                    url: operationOrderToPLannedInterfaceUrl,
                    success: function (data) {


                        $.each(data, function (index, value) {


                            var indexsplit = index.split("-");
                            $("th[data-date='" + index + "']").find('.spanToDelete').remove();
                            if(value == 1) {
                                $("th[data-date='" + index + "']").append('<span class="spanToDelete">&nbsp;<i style="cursor: pointer;" class="fas fa-plus popOrToCreate"></i></span>');
                            }
                        });
                    }
                });
                $(document).on('click', '.popOrToCreate', function(e){
                    let date = $(this).closest('th').data('date');
                    $.ajax({
                        url: '<?php echo dol_buildpath('/clitheobald/script/interface.php', 1); ?>?action=getTableDialogToCreate',
                        method: 'POST',
                        data: {
                            'url': window.location.href,
                            'date': date,
                        },
                        dataType: 'json',
                        // La fonction à apeller si la requête aboutie
                        success: function (data) {
                            $('#dialog-add-event').html(data);
                            $('#dialog-add-event').dialog("open");
                            $('#dialog-add-event').dialog({height: 'auto', width: 'auto'}); // resize to content
                            $('#dialog-add-event').parent().css({"top":"20%"});
                            $('.classfortooltip').tooltip({
                                content: function () {
                                    return $(this).prop('title');
                                }
                            });
                        }
                    });

                });
                $(document).on("click", ".createOR", function(e){
                    let tr = $(this).closest("tr");
                    let fk_actioncomm = $(tr).data("fk_actioncomm");
                    $.ajax({
                        url: '<?php echo dol_buildpath('/clitheobald/script/interface.php', 1); ?>?action=createOrFromEvent',
                        method: 'POST',
                        data: {
                            'url': window.location.href,
                            'fk_actioncomm': fk_actioncomm,
                        },
                        dataType: 'html',
                        // La fonction à apeller si la requête aboutie
                        success: function (data) {
                            if(data) {
                                $(tr).find('.createOR').closest('td').append(data);
                                $(tr).find('.createOR').remove();
                            } else {
                                $(tr).find('.createOR').closest('td').append(data);
                                $(tr).find('.createOR').remove();
                            }
                        }
                    });
                });
            </script>
        <?php
        }

        return 0;
    }


	/**
	 * formObjectOptions Method Hook Call
	 *
	 * @param array $parameters parameters
	 * @param Object &$object Object to use hooks on
	 * @param string &$action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param object $hookmanager class instance
	 * @return void
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $langs;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('operationordercard', $contextArray ) && !empty($object))
		{
			/** @var $object OperationOrder */
			$out = '';
			$object->array_options['options_fk_dolifleet_vehicule'];

			if(!empty($object->array_options['options_fk_dolifleet_vehicule'])){
				$langs->load('dolifleet@dolifleet');
				dol_include_once('dolifleet/class/vehicule.class.php');
				$vehicule = new doliFleetVehicule($db); // use to get table of element
				$res = $vehicule->fetch($object->array_options['options_fk_dolifleet_vehicule']);

				if($res>0)
				{
					$fields = array('vin', 'immatriculation', 'date_immat', 'fk_vehicule_type', 'fk_vehicule_mark', 'fk_contract_type', 'date_end_contract');

					foreach ($fields as $field){
						$out.= '<tr class="trcommonfield_'.$field.'">';
						$out.= '<td class="titlefield"  >';
						$out.= $langs->trans($vehicule->fields[$field]['label']);
						$out.= '</td>';
						$out.= '<td >'.$vehicule->showOutputFieldQuick($field).'</td>';

						$out.= '</tr>';
					}
				}
			}

			$this->resprints = $out;
		}

		if (in_array('productcard', $contextArray ))
		{
			// la modification de l'entrepot par défaut est déportée sur l'onglet correspondant.

			$out = '<script type="text/javascript" >';
			$out.= 'jQuery(document).ready(function() { $( "td:contains(\''.html_entity_decode($langs->trans("DefaultWarehouse")).'\')" ).parent().hide(); });';
			$out.= '</script>';

			$this->resprints = $out;
		}

		return 0;
	}

    public function listViewConfig($parameters, &$object, &$action, $hookmanager){

        global $db, $langs;

        $contextArray = explode(':', $parameters['context']) ;

        if (in_array('operationorderlist', $contextArray ) && !empty($object))
        {
			$listViewConfig = & $parameters['listViewConfig'];

            $origin = GETPOST('origin');

            if(!empty($origin))
            {
                dol_include_once('/custom/dolifleet/class/vehicule.class.php');
                dol_include_once('/custom/dolifleet/lib/dolifleet.lib.php');

                $vehicleid = GETPOST('Listview_operationorder_search_options_fk_dolifleet_vehicule');

                $vehicle = new doliFleetVehicule($db);
                $res = $vehicle->fetch($vehicleid);

                if($res > 0)
                {
                    $h = 0;
                    $head = array();

                    $head[$h][0] = dol_buildpath('/dolifleet/vehicule_card.php', 1).'?id='.$vehicleid;
                    $head[$h][1] = $langs->trans("doliFleetVehiculeCard");
                    $head[$h][2] = 'card';
                    $h++;

                    $head[$h][0] = DOL_URL_ROOT.'/custom/operationorder/list.php?Listview_operationorder_search_options_fk_dolifleet_vehicule='.$vehicleid.'&origin=vehicle';
                    $head[$h][1] = $langs->trans("CliTheobaldORList");

                    $nbOperationOrder = getNbORVehicle($vehicleid);
                    if($nbOperationOrder >= 0) $head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbOperationOrder.'</span>';

                    $hselected = $h;

                    dol_fiche_head($head, $hselected, $langs->trans('doliFleet'), -1, '');

                    printBannerVehicleCard($vehicle);

                } else {
                    return -1;
                }
            }

            /*
             * ADD VEHICULE IMMATRICULATION COLUMN
             */

			// Ajout du vin juste apres le vehicule
			$newTTitle = array();
			foreach ($listViewConfig['title'] as $key => $val){
				$newTTitle[$key] = $val;
				if($key == 'options_fk_dolifleet_vehicule'){
					$newTTitle['immatriculation'] = $langs->trans('Immatriculation');
				}
			}
			$listViewConfig['title']=$newTTitle;
			$listViewConfig['search']['immatriculation'] = array('search_type' => true, 'table' => 'vehicule', 'field' => array('immatriculation'));

			// Overriding $listViewConfig
			$this->results = $listViewConfig;

			return 1;
        }

        //AJOUT MASSACTION "CREATION D'OR" DANS LA LISTE DES VEHICULES
        if(in_array('vehiculelist', $contextArray )){

            $listViewConfig = $parameters['listViewConfig'];

            $listViewConfig['list']['massactions']['ORCreation'] = $langs->trans('CreateOR');

            $this->results = $listViewConfig;

            return 1;
        }

        return 0;
    }

    public function completeTabsHead($parameters, &$object, &$action, $hookmanager)
    {
	    global $langs;

        $contextArray = explode(':', $parameters['context']) ;

        if (in_array('vehiculecard', $contextArray ))
        {
            $nbOperationOrder = getNbORVehicle($parameters['object']->id);

            $this->results = $parameters['head'];
            $this->results[] = array(
                    dol_buildpath('operationorder/list.php?Listview_operationorder_search_options_fk_dolifleet_vehicule='.$parameters['object']->id.'&origin=vehicle', 1),
                    $langs->trans('CliTheobaldORList').'<span class="badge marginleftonlyshort">'.($nbOperationOrder>=0?$nbOperationOrder:0).'</span>',
                    'list'
            );

            return 1;
        }

        return 0;

    }

  	public function addSearchEntry($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user, $db;
        $langs->load('clitheobald@clitheobald');

        dol_include_once('/clitheobald/core/modules/modCliTheobald.class.php');
        $modCliTheobald = new modCliTheobald($db);

        if (empty($conf->global->OR_HIDE_QUICK_SEARCH) && $user->rights->operationorder->read) {
            $str_search_driver = '&Listview_operationorder_search_options_driver='. urlencode($parameters['search_boxvalue']);

            $arrayresult['searchintoordriver'] = array(
                'position' => $modCliTheobald->numero,
                'text' => img_object('', 'clitheobald@clitheobald') . ' ' . $langs->trans('Driver'),
                'url' => dol_buildpath('/custom/operationorder/list.php', 1) . '?search_by=Listview_operationorder_search_options_driver'.$str_search_driver
            );

        }

        $this->results = $arrayresult;

        return 0;
    }


	public function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('operationorderlist', $contextArray ) && !empty($object)) {
			/** @var $object OperationOrder */

			$this->resprints = ' , vehicule.immatriculation ';
		}

		return 0;
	}


	public function printFieldListJoin($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('operationorderlist', $contextArray ) && !empty($object)) {
			/** @var $object OperationOrder */

			$this->resprints = ' LEFT JOIN '.MAIN_DB_PREFIX.'dolifleet_vehicule vehicule ON (vehicule.rowid = et.fk_dolifleet_vehicule) ';
		}

		return 0;
	}



	public function jsonInterface($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('oorderinterface', $contextArray ) && !empty($object)) {

			if($action == 'getProductInfos')
			{
				$parameters['data']['log'][] = 'start hook clitheobal';
				/** @var $object Product */
				if(empty($parameters['fromObject'])){
					$parameters['data']['log'][] = 'hook clitheobal empty fromObject param, escape';
					return 0;
				}

				if(!class_exists('DefaultProductWarehouse')){
					include_once __DIR__ . '/defaultproductwarehouse.class.php';
				}

				$dProductWarehouse = new DefaultProductWarehouse($db);
				$fk_default_warehouse = $dProductWarehouse->getWarehouseFromProduct($object ,$parameters['fromObject']->entity);

				if($fk_default_warehouse > 0)
				{
					$parameters['data']['fk_default_warehouse'] = $fk_default_warehouse;
					$parameters['data']['log'][] = 'changed from hook clitheobal';
				}
				else{
					$parameters['data']['log'][] = 'No change from hook clitheobal';
				}
			}
		}

		return 0;
	}


	public function operationorderplanning($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $db, $langs;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('oorderinterface', $contextArray ) && !empty($object)) {

			$operationOrder = $parameters['operationOrder'];
			$obj = $parameters['sqlObj'];
			$T = $parameters['T'];

			$newTitle = '';

			if (!empty($operationOrder->array_options['options_not_presented']))
			{
				$newTitle .= "<br>".img_warning("véhicule non présenté");
				$object->msg .= "<br>".img_warning("véhicule non présenté") . " Véhicule non présenté <br>";
			}

			$newTitle .= $object->title;

			if(!empty($operationOrder->array_options['options_fk_dolifleet_vehicule'])){
				$langs->load('dolifleet@dolifleet');
				dol_include_once('dolifleet/class/vehicule.class.php');
				$vehicule = new doliFleetVehicule($db); // use to get table of element
				$res = $vehicule->fetch($operationOrder->array_options['options_fk_dolifleet_vehicule']);

				if($res>0)
				{
					$newTitle .= "<br>".$vehicule->vin;
					$newTitle .= "<br>".$vehicule->immatriculation;


					$fields = array('vin', 'immatriculation', 'date_immat', 'fk_vehicule_type', 'fk_vehicule_mark', 'fk_contract_type', 'date_end_contract');
					$object->msg .= '<br/><br/><strong>'.strtoupper($langs->trans('Vehicule')).' :</strong>';
					foreach ($fields as $field){

						$object->msg .= '<br/>'.$langs->trans($vehicule->fields[$field]['label']);
						$object->msg .= ' : <strong>'.$vehicule->showOutputFieldQuick($field).'</strong>';
					}
				}
			}

			$object->title = $newTitle;
		}

		return 0;
	}

    public function addOperationorderPlannableTableTitle(&$parameters, &$object, &$action, $hookmanager)
    {

        global $conf, $db, $langs;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('oorderinterface', $contextArray )) {

            $parameters['out'].= ' <th class="" >'.$langs->trans('VIN').'</th>';
            $parameters['out'].= ' <th class="" >'.$langs->trans('Immatriculation').'</th>';

            return 1;
        }

		return 0;
    }

    public function addOperationorderPlannableTableField(&$parameters, &$object, &$action, $hookmanager)
    {

        global $conf, $db, $langs;

		$contextArray = explode(':', $parameters['context']) ;

		if (in_array('oorderinterface', $contextArray )) {

		    $error = 0;

		    $operationOrder = $parameters['operationOrder'];

		    $langs->load('dolifleet@dolifleet');
			dol_include_once('dolifleet/class/vehicule.class.php');

            $vehicule = new doliFleetVehicule($db); // use to get table of element
            $res = $vehicule->fetch($operationOrder->array_options['options_fk_dolifleet_vehicule']);

            if($res > 0){
                $parameters['out'].= ' <td  data-order="'.$vehicule->vin.'" data-search="'.$vehicule->vin.'"  >'.$vehicule->vin.'</td>';
                $parameters['out'].= ' <td  data-order="'.$vehicule->immatriculation.'" data-search="'.$vehicule->immatriculation.'"  >'.$vehicule->immatriculation.'</td>';

            } else {
                $error++;
            }

            if($error) return -1;
            else return 1;
        }

		return 0;
    }


    public function formConfirm($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $user, $conf;

        $contextArray = explode(':', $parameters['context']) ;

        if(in_array('vehiculelist', $contextArray )) {

            dol_include_once('/operationorder/class/operationorder.class.php');
            dol_include_once('operationorder/lib/operationorder.lib.php');

            $massaction = GETPOST('massaction');

            if($massaction == 'ORCreation')
            {
                //MASSACTION "CREATION OR" : affichage première popin "choix produit"
                if (empty($action))
                {
                    $OR = new OperationOrder($db);

                    $TVehicles = GETPOST('toselect');

                    if (!empty($TVehicles))
                    {
                        session_start();
                        $_SESSION['toselect'] = $TVehicles;

                        print '<div id="dialog-form-add" style="display: none;" >';
                        print '<div id="add-item" class="add-line-form-wrap" >';
                        print '<div class="add-line-form-body" >';
                        print displayFormFieldsByOperationOrder($OR, false, false, false);
                        print '</div>';
                        print '</div>';
                        print '</div>';

                        print '
					<script type="text/javascript">

					$(function()
					{
						var cardUrl = "'.$_SERVER['REQUEST_URI'].'";

						var dialogBox = jQuery("#dialog-form-add");
						var width = $(window).width();
						var height = $(window).height();
						if(width > 700){ width = 700; }
						if(height > 600){ height = 600; }

						dialogBox.dialog({
							autoOpen: false,
							resizable: true,
							width: width,
							modal: true,
							buttons: {
								"'.$langs->transnoentitiesnoconv('Create').'": function() {
									dialogBox.find("form").submit();
								}
							},
							close: function( event, ui ) {
								window.location.replace(cardUrl);
							}
						});

						function popOperationOrderAddLineFormDialog()
						{
							var item = $("#add-item");

							dialogBox.dialog({
							  title: item.attr("title")
							});

							dialogBox.dialog( "open" );
						}

						popOperationOrderAddLineFormDialog();

						//Mise en page popin

                        var firstelement = $("table.table-full").find("tbody tr:first");
                        var typeOR =  $("#field_fk_c_operationorder_type");
                        firstelement.before(typeOR);

					});
					</script>';

                        return 1;

                    } else {
                        return -1;
                    }
                }
                else
                {
                    //MASSACTION "CREATION OR" : on affiche la deuxième popin de confirmation de véhicules
                    if ($action == 'addline')
                    {
                        $formquestion = array();

                        foreach($_SESSION['TVehicleToConfirm'] as $vehicleid)
                        {
                            $res = $object->fetch($vehicleid);
                            if ($res)
                            {
                                $formquestion[] = array('type' => 'checkbox', 'name' => $vehicleid, 'label' => $object->vin, 'value' => 1);
                            }
                        }

                        $form = new Form($this->db);
                        $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ConfirmVehicles'), $langs->trans('ConfirmCreationORVehicles', $object->ref), 'confirm', $formquestion, '', 1);

                        $this->resprints = $formconfirm;

                        return 1;
                    }

                }
            }

        }
    }
    public function displayFormFieldsByOperationOrder($parameters, &$object, &$action, $hookmanager)
    {

        if (in_array('vehiculelist', explode(':', $parameters['context'])))
        {
            if($action == 'create')
            {
                global $db, $langs;
                $OR = new OperationOrder($db);
                $parameters['line']->fields['fk_c_operationorder_type'] = $OR->fields['fk_c_operationorder_type'];
                $parameters['line']->fields['fk_c_operationorder_type']['label'] = $langs->trans('OperationOrderTypeLong');
                $parameters['line']->fields['fk_c_operationorder_type']['label'] = $langs->trans('OperationOrderTypeLong');
            }
        }

        return 0;
    }

    public function selectWarehouses($parameters, $object, $action, $hookmanager)
    {

        global $conf, $db;

        $error = 0;
        $warehouse_to_select = array();
		$fk_default_warehouse = 0;

        if ((in_array('massstockmove', explode(':', $parameters['context'])) && $parameters['htmlname'] == 'id_tw'))
        {
			return 0;
		}
        elseif (in_array('stockproductcard', explode(':', $parameters['context'])) && $parameters['htmlname'] == 'id_entrepot_destination')
        {
			global $conf,$langs, $user, $hookmanager;

			/*PERSONNALISATION*/

			$selected = $parameters['selected'];
			$htmlname = $parameters['htmlname'];
			$filterstatus = $parameters['filterstatus'];
			$empty = $parameters['empty'];
			$disabled = $parameters['disabled '];
			$fk_product = $parameters['fk_product'];
			$empty_label = $parameters['empty_label'];
			$showstock = $parameters['showstock'];
			$forcecombo = $parameters['forcecombo'];
			$events = $parameters['events'];
			$morecss = $parameters['morecss'];
			$exclude = (!empty($parameters['exclude'])) ? $parameters['exclude'] : array() ;
			$showfullpath = $parameters['showfullpath'];
			$stockMin = $parameters['stockMin'];
			$orderBy = $parameters['orderBy'];

			dol_syslog(get_class($object)."::selectWarehouses From CliTheobaldHook $selected, $htmlname, $filterstatus, $empty, $disabled, $fk_product, $empty_label, $showstock, $forcecombo, $morecss", LOG_DEBUG);

			//entrepots à sélectionner en fonction de la zone de stockage temporaire définie dans la conf de l'entité
			$sql = 'SELECT stock.rowid, stock.fk_parent FROM '.MAIN_DB_PREFIX.'entity_extrafields  as ent ';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'entrepot as stock ON stock.rowid=ent.fk_default_tmp_warehouse';
			$sql .= ' WHERE stock.entity IN ('.getEntity('stock').')';

			$resql = $db->query($sql);
			$TChildWarehouses = array();
			if ($resql) {
				$num = $db->num_rows($resql);
				if (empty($num)) return 0;

				while ($obj = $db->fetch_object($resql)) {

					$fk_tmp_default_warehouse = $obj->rowid;

					$warehouse_tmp_to_select[] = $fk_tmp_default_warehouse;
					if (!empty($obj->fk_parent)) $warehouse_tmp_to_select[] = $obj->fk_parent;
					if (!empty($fk_tmp_default_warehouse)) {
						dol_syslog(get_class($this) . __METHOD__ . "fk_default_tmp_warehouse=" . $fk_tmp_default_warehouse);
						$warehouse = new Entrepot($db);
						$TChildWarehouses = $warehouse->get_children_warehouses($fk_tmp_default_warehouse, $TChildWarehouses);
						$warehouse_tmp_to_select = array_merge($warehouse_tmp_to_select, $TChildWarehouses);
					}
				}
			}
			else
			{
				setEventMessage($db->lasterror,'errors');
				$error++;
			}

			//entrepôts à retirer de la sélection
			if (!$error && !empty($warehouse_tmp_to_select))
			{
				$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE rowid NOT IN (".implode(',', $warehouse_tmp_to_select).")";
				$resql = $db->query($sql);

				if ($resql)
				{
					while ($obj = $db->fetch_object($resql))
					{
						$exclude[] = $obj->rowid;
					}
				}
				else
				{
					setEventMessage($db->lasterror,'errors');
					$error++;
				}
			}
			$object->cache_warehouses = array();

			/*COPYRIGHT SELECTWAREHOUSE FUNCTION*/
			$out='';

			$out .= '<!-- selectWarehouse from actions_clitheobald.class.php -->';

			if (empty($conf->global->ENTREPOT_EXTRA_STATUS)) $filterstatus = '';
			//            if (!empty($fk_product))  $object->cache_warehouses = array();

			$object->loadWarehouses($fk_product, '', $filterstatus, true, $exclude, $stockMin, $orderBy);
			$nbofwarehouses=count($object->cache_warehouses);

			if ($conf->use_javascript_ajax && ! $forcecombo)
			{
				include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
				$comboenhancement = ajax_combobox($htmlname, $events);
				$out.= $comboenhancement;
			}

			$out.='<select class="flat'.($morecss?' '.$morecss:'').'"'.($disabled?' disabled':'').' id="'.$htmlname.'" name="'.($htmlname.($disabled?'_disabled':'')).'">';
			if ($empty) $out.='<option value="-1">'.($empty_label?$empty_label:'&nbsp;').'</option>';
			foreach($object->cache_warehouses as $id => $arraytypes)
			{
				//Spécifique par rapport à la méthode d'origine pour pas affiché l'netrepot niveau 0
				if (empty($arraytypes['parent_id'])) continue;
				$label='';
				if ($showfullpath) $label.=$arraytypes['full_label'];
				else $label.=$arraytypes['label'];
				if (($fk_product || ($showstock > 0)) && ($arraytypes['stock'] != 0 || ($showstock > 0)))
				{
					if ($arraytypes['stock'] <= 0) {
						$label.=' <span class= \'text-warning\'>('.$langs->trans("Stock").':'.$arraytypes['stock'].')</span>';
					}
					else
					{
						$label.=' <span class=\'opacitymedium\'>('.$langs->trans("Stock").':'.$arraytypes['stock'].')</span>';
					}
				}

				$out.='<option value="'.$id.'"';
				if ($selected == $id || ($selected == 'ifone' && $nbofwarehouses == 1)) {

					$out.=' selected';
				}
				$out.=' data-html="'.dol_escape_htmltag($label).'"';
				$out.='>';
				$out.=$label;
				$out.='</option>';
			}
			$out.='</select>';

			if ($disabled) $out.='<input type="hidden" name="'.$htmlname.'" value="'.(($selected>0)?$selected:'').'">';

			$out .= '<!-- END selectWarehouse from actions_clitheobald.class.php -->';

			$this->resprints = $out;

			//on vide le cache
			$object->cache_warehouses = array();

			if (!$error) return 1;
			else return -1;
        }
        else
        {

            global $conf,$langs, $user, $hookmanager;

            /*PERSONNALISATION*/

            $selected = $parameters['selected'];
            $htmlname = $parameters['htmlname'];
            $filterstatus = $parameters['filterstatus'];
            $empty = $parameters['empty'];
            $disabled = $parameters['disabled '];
            $fk_product = $parameters['fk_product'];
            $empty_label = $parameters['empty_label'];
            $showstock = $parameters['showstock'];
            $forcecombo = $parameters['forcecombo'];
            $events = $parameters['events'];
            $morecss = $parameters['morecss'];
            $exclude = (!empty($parameters['exclude'])) ? $parameters['exclude'] : array() ;
            $showfullpath = $parameters['showfullpath'];
            $stockMin = $parameters['stockMin'];
            $orderBy = $parameters['orderBy'];

			dol_syslog(get_class($object)."::selectWarehouses From CliTheobaldHook $selected, $htmlname, $filterstatus, $empty, $disabled, $fk_product, $empty_label, $showstock, $forcecombo, $morecss", LOG_DEBUG);

            //entrepots à sélectionner en fonction de l'entrepot par défaut de l'entité et de ses enfants
            $sql = "SELECT fk_default_warehouse FROM ".MAIN_DB_PREFIX."entity_extrafields WHERE fk_object = '".$conf->entity."'";
            $resql = $db->query($sql);

            if ($resql) {

                $obj = $db->fetch_object($resql);

                $fk_default_warehouse = $obj->fk_default_warehouse;

                $warehouse_to_select[] = $fk_default_warehouse;
				if (!empty($fk_default_warehouse)) {
					dol_syslog(get_class($this).__METHOD__. "fk_default_warehouse=".$fk_default_warehouse);
					$warehouse = new Entrepot($db);
					$res = $warehouse->fetch($fk_default_warehouse);

					if ($res) {

						$TChildWarehouses = array();
						$TChildWarehouses = $warehouse->get_children_warehouses($fk_default_warehouse, $TChildWarehouses);

						$warehouse_to_select = array_merge($warehouse_to_select, $TChildWarehouses);
					} else {
						setEventMessage($warehouse->error,'errors');
						$error++;
					}
				}
            }
            else
            {
            	setEventMessage($db->lasterror,'errors');
                $error++;
            }

            //entrepôts à retirer de la sélection
            if (!$error && !empty($fk_default_warehouse))
            {
                $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE rowid NOT IN (".implode(',', $warehouse_to_select).")";
                $resql = $db->query($sql);

                if ($resql)
                {
                    while ($obj = $db->fetch_object($resql))
                    {
                        $exclude[] = $obj->rowid;
                    }
                }
                else
                {
					setEventMessage($db->lasterror,'errors');
                    $error++;
                }
            }
            $object->cache_warehouses = array();

            /*COPYRIGHT SELECTWAREHOUSE FUNCTION*/
            $out='';

            $out .= '<!-- selectWarehouse from actions_clitheobald.class.php -->';

            if (empty($conf->global->ENTREPOT_EXTRA_STATUS)) $filterstatus = '';
//            if (!empty($fk_product))  $object->cache_warehouses = array();

            $object->loadWarehouses($fk_product, '', $filterstatus, true, $exclude, $stockMin, $orderBy);
            $nbofwarehouses=count($object->cache_warehouses);

            if ($conf->use_javascript_ajax && ! $forcecombo)
            {
                include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
                $comboenhancement = ajax_combobox($htmlname, $events);
                $out.= $comboenhancement;
            }

            $out.='<select class="flat'.($morecss?' '.$morecss:'').'"'.($disabled?' disabled':'').' id="'.$htmlname.'" name="'.($htmlname.($disabled?'_disabled':'')).'">';
            if ($empty) $out.='<option value="-1">'.($empty_label?$empty_label:'&nbsp;').'</option>';
            foreach($object->cache_warehouses as $id => $arraytypes)
            {
                $label='';
                if ($showfullpath) $label.=$arraytypes['full_label'];
                else $label.=$arraytypes['label'];
                if (($fk_product || ($showstock > 0)) && ($arraytypes['stock'] != 0 || ($showstock > 0)))
                {
                    if ($arraytypes['stock'] <= 0) {
                        $label.=' <span class= \'text-warning\'>('.$langs->trans("Stock").':'.$arraytypes['stock'].')</span>';
                    }
                    else
                    {
                        $label.=' <span class=\'opacitymedium\'>('.$langs->trans("Stock").':'.$arraytypes['stock'].')</span>';
                    }
                }

                $out.='<option value="'.$id.'"';
                if ($selected == $id || ($selected == 'ifone' && $nbofwarehouses == 1)) {

                	$out.=' selected';
				}
                $out.=' data-html="'.dol_escape_htmltag($label).'"';
                $out.='>';
                $out.=$label;
                $out.='</option>';
            }
            $out.='</select>';

            if ($disabled) $out.='<input type="hidden" name="'.$htmlname.'" value="'.(($selected>0)?$selected:'').'">';

            $out .= '<!-- END selectWarehouse from actions_clitheobald.class.php -->';

            $this->resprints = $out;

            //on vide le cache
            $object->cache_warehouses = array();

            if (!$error) return 1;
            else return -1;

        }
    }
}
