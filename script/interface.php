<?php

require '../config.php';
dol_include_once('operationorder/class/operationorder.class.php');
dol_include_once('operationorder/lib/operationorder.lib.php');
dol_include_once('clitheobald/class/clitheobald.class.php');

$get = GETPOST('get');
$action = GETPOST('action');
$data = $_POST;

$langs->load('clitheobald@clitheobald');

if($action=='getTableDialogToCreate') echo json_encode(_getTableDialogToCreate($data['date']));
if($action=='createOrFromEvent') echo _createOrFromEvent($data['fk_actioncomm']);


if($action == 'getToPlannedOperationOrder')
{

    $TDays = array();
    $TDatas = array();

    $timeZone = GETPOST('timeZone');
    $range_start = OO_parseFullCalendarDateTime(GETPOST('start'), $timeZone);
    $range_end = OO_parseFullCalendarDateTime(GETPOST('end'), $timeZone);

    $date_start_details = date_parse($range_start->format('Y-m-d'));
    $date_end_details = date_parse($range_end->format('Y-m-d'));

    $debut_date = mktime(0, 0, 0, $date_start_details['month'], $date_start_details['day'], $date_start_details['year']);
    $fin_date = mktime(0, 0, 0, $date_end_details['month'], $date_end_details['day'], $date_end_details['year']);

    for ($i = $debut_date; $i < $fin_date; $i += 86400)
    {
        $TDays[] = date("Y-m-d", $i);
    }

    foreach ($TDays as $day)
    {
        if(count(CliTheobald::getOperationOrderToCreateIds($day)) > 0) {
            $TDatas[$day] = 1;
        }
        else $TDatas[$day] = 0;

    }

    print json_encode($TDatas);
    exit;
}

switch ($get) {
    case 'get-fksoc-of-vehicule':

        print get_fksoc_of_vehicule(GETPOST('vehicule_id'));

        break;

	case 'get-info-of-vehicule':

		print get_info_of_vehicule(GETPOST('vehicule_id'));

		break;

    case 'get-operationorder-info-from-vehicule':

        print get_operationorder_info_from_vehicule(GETPOST('vehicule_id','int'));

        break;

    case 'get-product-add-info':

        print get_operationorder_product_add_info(GETPOST('fk_operationOrder','int'), GETPOST('fk_product','int'));

        break;

    default:

        break;
}

function get_info_of_vehicule($vehicule_id){

	global $db;

	$data = array();
	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['vehicule'] = 0;
	$data['msg'] = '';

	if(!empty($vehicule_id)) {
		dol_include_once('/dolifleet/class/vehicule.class.php');

		$veh = new doliFleetVehicule($db);
		$result = $veh->fetch($vehicule_id);

		if(empty($veh->km)) { //On prend le dernier kilométrage saisit dans un OR, s'il n'y a pas de kilometrage associé à un véhicule
			$sql = "SELECT ooe.km_on_creation FROM ".MAIN_DB_PREFIX."operationorder_extrafields ooe
					WHERE ooe.fk_dolifleet_vehicule = ".$vehicule_id."
					ORDER BY ooe.rowid DESC
					LIMIT 1";
			$resql = $db->query($sql);
			if(!empty($resql) && $db->num_rows($resql) > 0) {
				$obj = $db->fetch_object($resql);
				if(!empty($obj->km_on_creation)) $veh->km = $obj->km_on_creation;
			}

		}
		if ($result<0) {
			$data['errorMsg'] = $veh->error;
		} else {
			$data['result'] = $result;
			$data['vehicule'] = $veh;
		}
	}

	return json_encode($data);

}

function get_fksoc_of_vehicule($vehicule_id){

    global $db, $langs, $conf;

	$data = array();
	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['societe'] = 0;
	$data['msg'] = '';

    $sql = "SELECT fk_soc FROM ".MAIN_DB_PREFIX."dolifleet_vehicule WHERE rowid ='" . $vehicule_id . "'";
    $resql = $db->query($sql);
    if($resql && !empty($vehicule_id)){
        $obj = $db->fetch_object($resql);

		$societe = new Societe($db);
		if($societe->fetch($obj->fk_soc) > 0){
			$data['result'] = 1;

			$societeOut = new stdClass();
			$societeOut->id = $societe->id;
			$societeOut->name = $societe->name;

			$data['societe'] = $societeOut;

			if(class_exists('OperationOrder')){
				// TK1766
				// Lors de la création d’un OR, l’outil doit alerter l’utilisateur si il existe déjà un OR non clôturer sur le véhicule
				$sql = "SELECT o.rowid, o.ref FROM ".MAIN_DB_PREFIX."operationorder o ";
				$sql.= " INNER JOIN ".MAIN_DB_PREFIX."operationorder_extrafields oext ON (o.rowid = oext.fk_object) ";
				$sql.= " WHERE  oext.fk_dolifleet_vehicule =".intval($vehicule_id);

				if(!empty($conf->global->OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER)){
					$TStatusSearch = explode(',', $conf->global->OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER);
					$TStatusSearch = array_map('intval', $TStatusSearch);

					$sql.= ' AND o.status IN ('.implode(',', $TStatusSearch).')';

				}

				$resql = $db->query($sql);
				if($resql) {
					$num = $db->num_rows($resql);
					if ($num > 0) {
						if ($obj = $db->fetch_object($resql)) {
							$operationOrder = new OperationOrder($db);
							$operationOrder->id = $obj->rowid;
							$operationOrder->ref = $obj->ref;
							$data['warningMsg'] = $langs->trans('NoClosedOperationOrderForThisVehiculeFound', $operationOrder->getNomUrl());
						}
					}
				}
				else{$data['errorMsg']='OperationOrder test error';}
			}
		}
    }
    else{
		$data['errorMsg'] = $langs->trans('fkSocVehiculeError');
	}

	return json_encode($data);
}

function get_operationorder_info_from_vehicule($vehicule_id, $forceCompatible = false){

    global $db, $conf, $langs;

	$data = array();
	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['operationorder_type'] = array();
	$data['msg'] = '';

	if(empty($vehicule_id)){
		return  json_encode($data);
	}

	dol_include_once('clitheobald/class/clitheobald.class.php');

	$object = new CliTheobald($db);

	$TCombination = $object->getCombinations();

	$sql = "SELECT fk_contract_type FROM ".MAIN_DB_PREFIX."dolifleet_vehicule WHERE rowid ='" . $vehicule_id . "'";
	$resql = $db->query($sql);
	if($resql){
		$data['result'] = 1;

		$obj = $db->fetch_object($resql);

		$TCompatibleType = array();
		if(!empty($TCombination)){
			if(isset($TCombination[$obj->fk_contract_type])){
				// recuperation des id de type dans un tableau simple
				$TCompatibleType = array_keys($TCombination[$obj->fk_contract_type]);
				$TCompatibleType = array_map('intval', $TCompatibleType);
			}
		}

		if(!empty($TCompatibleType) || !$forceCompatible) {

			$sql = 'SELECT rowid as id, code, label FROM ' . MAIN_DB_PREFIX . 'c_operationorder_type';
			$sql .= ' WHERE active = 1 AND entity IN (0,1,'.$conf->entity.')';
			if (!empty($TCompatibleType)) {
				$sql .= ' AND  rowid IN ('.implode(',', $TCompatibleType).')';
			}
			$sql .= ' ORDER BY `position`';

			$resql = $db->query($sql);
			if ($resql) {
				while ($type = $db->fetch_object($resql)) {
					$data['operationorder_type'][] = $type;
				}
			}
		}
	}

	return  json_encode($data);
}

function get_operationorder_product_add_info($fk_operationOrder, $fk_product){

    global $db, $conf, $langs;

	$data = array();
	$data['result'] = 0; // by default if no action result is false
	$data['errorMsg'] = ''; // default message for errors
	$data['warningMsg'] = ''; // default message for warnings
	$data['operationorders'] = array();
	$data['msg'] = '';

	if(empty($fk_operationOrder) || empty($fk_product)){
		return  json_encode($data);
	}

	// for static usage
	$operationOrder = new OperationOrder($db);
	$operationOrderDet = new OperationOrderDet($db);

	$TStatusIn = array();
	if(!empty($conf->global->OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER)){
		$TStatusIn = array_map('intval', explode(',', $conf->global->OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER)) ;
	}

	// Select
	$sql = "SELECT oo.rowid as id FROM ".MAIN_DB_PREFIX.$operationOrder->table_element . " oo";
	$sql.= " JOIN ".MAIN_DB_PREFIX.$operationOrderDet->table_element . " ood ON ( ood.fk_operation_order = oo.rowid )";
	$sql.= " WHERE oo.rowid != " . intval($fk_operationOrder);
	if(!empty($TStatusIn)){
		$sql.= " AND oo.status IN (" . implode(',', $TStatusIn) . ")";
	}
	$sql.= " AND ood.fk_product = " . intval($fk_product);
	$sql.= " GROUP BY oo.rowid";


	$resql = $db->query($sql);
	if($resql){
		$data['result'] = 1;
		while ($obj = $db->fetch_object($resql)) {

			$operationOrder = new OperationOrder($db);
			$operationOrder->fetch($obj->id);

			$odjData = new stdClass();
			$odjData->id = $operationOrder->id;
			$odjData->ref = $operationOrder->ref;
			$odjData->getNomUrl = $operationOrder->getNomUrl();
			$odjData->array_options = $operationOrder->array_options;

			$odjData->htmlAlert = $langs->trans('ThisProductWasFoundInAnotherOperationOrder').' : <br/>';
			$odjData->htmlAlert.= $odjData->getNomUrl.'<br/>';
			$odjData->htmlAlert.= dol_print_date($operationOrder->date_operation_order).'<br/>';
			$odjData->htmlAlert.= $operationOrder->array_options['options_km_on_creation'].'Km';

			$data['operationorders'][] = $odjData;
		}
	}

	return  json_encode($data);
}

function _getTableDialogToCreate($date) {
    global $db, $langs, $hookmanager;
    dol_include_once('/comm/action/class/actioncomm.class.php');
    dol_include_once('/dolifleet/class/vehicule.class.php');
    $TActionCommIds = CliTheobald::getOperationOrderToCreateIds($date);


    $out= '<table id="orToCreate" class="table" style="width:800px;" >';

    $out.= '<thead>';

    $out.= '<tr>';
    $out.= ' <th class="text-center" >'.$langs->trans('Customer').'</th>';
    $out.= ' <th class="text-center" >'.$langs->trans('Immatriculation').'</th>';
    $out.= ' <th class="text-center"  >'.$langs->trans('VIN').'</th>';
    $out.= ' <th class="text-center" >'.$langs->trans('Product').'</th>';
    $out.= ' <th class="text-center" >'.$langs->trans('Action').'</th>';
    $out.= '</tr>';

    $out.= '</thead>';

    $out.= '<tbody>';

    foreach ($TActionCommIds as $fk_actioncomm)
    {
        $action = new ActionComm($db);
        $action->fetch($fk_actioncomm);
        if(empty($action->array_options)) $action->fetch_optionals();
        if(empty($action->thirdparty)) $action->fetch_thirdparty();
        if(empty($action->vehicule) && !empty($action->array_options['options_fk_vehicule'])) {
            $vehicule = new doliFleetVehicule($db);
            $vehicule->fetch($action->array_options['options_fk_vehicule']);
            $action->vehicule = $vehicule;

        }
        if(empty($action->product) && !empty($action->array_options['options_fk_product'])) {
            $product = new Product($db);
            $product->fetch($action->array_options['options_fk_product']);
            $action->product = $product;

        }
        $out.= '<tr data-fk_actioncomm="'.$fk_actioncomm.'" data-date="'.$date.'">';

        //Tiers
        if(!empty($action->thirdparty))$out.= ' <td>'.$action->thirdparty->getNomUrl(1).'</td>';
        else $out .=  '<td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;'.$langs->trans('MissingField').'</td>';
        if(!empty($action->vehicule)) {
            //Immat
            $out .= ' <td>'.$action->vehicule->immatriculation.'</td>';
            //VIN
            $out .= ' <td>'.$action->vehicule->getNomUrl(1).'</td>';
        }
        else $out .=  '<td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;'.$langs->trans('MissingField').'</td><td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;'.$langs->trans('MissingField').'</td>';

        if(!empty($action->product)) {
            //Prod
            $out .= ' <td>'.$action->product->getNomUrl(1).'</td>';
        }         else $out .=  '<td><i class="fa fa-exclamation-triangle" style="color:orange" aria-hidden="true"></i>&nbsp;'.$langs->trans('MissingField').'</td>';


        if(!empty($action->vehicule) && !empty($action->product)) $out .= ' <td><i style="cursor: pointer;" class="fa fa-plus createOR"></i></td>';
        else $out .=  '<td><i class="fa fa-times" aria-hidden="true" style="color:red;"></i></i>&nbsp;'.$langs->trans('CantCreate').'</td>';



        $out.= '</tr>';
    }
    $out.= '</tbody>';

    $out.= '</table>';

    $out.= '<script src="'. dol_buildpath('/operationorder/vendor/data-tables/datatables.min.js',1).'"></script>';
    $out.='<script src="'.dol_buildpath('/operationorder/vendor/data-tables/jquery.dataTables.min.js',1).'"></script>';

    $out.= '<script type="text/javascript" >
					$(document).ready(function(){

					    $("#orToCreate").DataTable({
						"pageLength" : 10,
						"language": {
							"url": "'.dol_buildpath('/operationorder/vendor/data-tables/french.json',1).'"
						},
//						responsive: true
					});

					});

			   </script>';

    return $out;
}

function _createOrFromEvent($fk_actioncomm) {
    global $db, $conf, $langs, $user;
    $langs->load('operationorder@operationorder');
    dol_include_once('/comm/action/class/actioncomm.class.php');
    $object = new ActionComm($db);
    $object->fetch($fk_actioncomm);
    if(empty($object->array_options)) $object->fetch_optionals();

    if(empty($conf->global->OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT) || $conf->global->OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT == '-1') {
        return '<i class="fa fa-times" aria-hidden="true" style="color:red;"></i>&nbsp;'.$langs->trans('ErrORStatusConfiguration');
    }

    // l'event est fetché avant le doActions
    /**
     * @var ActionComm $object
     */
    if(
        ! empty($object->id)
        && $object->code === 'AC_OR'
        && $object->percentage == 0
        && ! empty($object->array_options['options_fk_vehicule'])
        && ! empty($object->array_options['options_fk_product'])
        && $conf->operationorder->enabled
        && $conf->dolifleet->enabled
    ) {
//					$db->begin();

        dol_include_once('/dolifleet/class/vehicule.class.php');
        $veh = new doliFleetVehicule($db);
        $ret = $veh->fetch($object->array_options['options_fk_vehicule']);

        dol_include_once('/operationorder/class/operationorder.class.php');
        $OR = new OperationOrder($db);

        $OR->fk_soc = $veh->fk_soc;
        $OR->date_operation_order = dol_now();
        $OR->array_options['options_fk_dolifleet_vehicule'] = $veh->id;

        $ret = $OR->save($user);

        //récup du code paramétré dans l'entité de l'OR traité
        $sql = "SELECT value as code FROM ".MAIN_DB_PREFIX."const WHERE name ='OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT' AND entity = '".$OR->entity."'";
        $resql = $db->query($sql);

        if($resql) {
            $obj = $db->fetch_object($resql);

            $sql = "SELECT rowid as id FROM ".MAIN_DB_PREFIX."operationorder_status WHERE code ='".$obj->code."' AND entity = '".$OR->entity."'";
            $resql = $db->query($sql);
            if($resql) {
                $obj = $db->fetch_object($resql);
                $fk_status = $obj->id;

                $OR->setStatus($user, $fk_status);
            }
        }

        // création de l'OR effectuée, maintenant on ajoute le service
        $prod = new Product($db);
        $retProd = $prod->fetch($object->array_options['options_fk_product']);
        if($retProd > 0) {
            $retadd = $OR->addline($prod->description, 1, $prod->price, 0, 0, 0, 0, $prod->id);
            if($retadd > 0) {
                $retrec = $OR->recurciveAddChildLines($retadd, $prod->id, 1);
            }
        }

        $object->elementtype = 'operationorder';
        $object->fk_element = $ret;
        $object->percentage = 100;
        $object->update($user);
        return '<i class="fa fa-check" aria-hidden="true" style="color: green;"></i>&nbsp;'.$OR->getNomUrl();
    }
    return '<i class="fa fa-times" aria-hidden="true" style="color:red;"></i>';
}

