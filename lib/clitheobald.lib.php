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
 *	\file		lib/clitheobald.lib.php
 *	\ingroup	clitheobald
 *	\brief		This file is an example module library
 *				Put some comments here
 */

/**
 * @return array
 */
function clitheobaldAdminPrepareHead()
{
    global $langs, $conf, $db;

    $langs->load('clitheobald@clitheobald');

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/clitheobald/admin/clitheobald_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;

    dol_include_once('clitheobald/class/clitheobald.class.php');
    $object = new CliTheobald($db);
    if ($object->isextrafieldmanaged)
    {
        $head[$h][0] = dol_buildpath("/clitheobald/admin/clitheobald_extrafields.php", 1);
        $head[$h][1] = $langs->trans("ExtraFields");
        $head[$h][2] = 'extrafields';
        $h++;
    }

    $head[$h][0] = dol_buildpath("/clitheobald/admin/clitheobald_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@clitheobald:/clitheobald/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@clitheobald:/clitheobald/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'clitheobald');

    return $head;
}

/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param 	CliTheobald	$object		Object company shown
 * @return 	array				Array of tabs
 */
function clitheobald_prepare_head(CliTheobald $object)
{
    global $langs, $conf;
    $h = 0;
    $head = array();
    $head[$h][0] = dol_buildpath('/clitheobald/card.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("CliTheobaldCard");
    $head[$h][2] = 'card';
    $h++;

	// Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@clitheobald:/clitheobald/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@clitheobald:/clitheobald/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'clitheobald');

	return $head;
}

/**
 * @param Form      $form       Form object
 * @param CliTheobald  $object     CliTheobald object
 * @param string    $action     Triggered action
 * @return string
 */
function getFormConfirmCliTheobald($form, $object, $action)
{
    global $langs, $user;

    $formconfirm = '';

    if ($action === 'valid' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmValidateCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmValidateCliTheobaldTitle'), $body, 'confirm_validate', '', 0, 1);
    }
    elseif ($action === 'accept' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmAcceptCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmAcceptCliTheobaldTitle'), $body, 'confirm_accept', '', 0, 1);
    }
    elseif ($action === 'refuse' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmRefuseCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmRefuseCliTheobaldTitle'), $body, 'confirm_refuse', '', 0, 1);
    }
    elseif ($action === 'reopen' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmReopenCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmReopenCliTheobaldTitle'), $body, 'confirm_refuse', '', 0, 1);
    }
    elseif ($action === 'delete' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmDeleteCliTheobaldBody');
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmDeleteCliTheobaldTitle'), $body, 'confirm_delete', '', 0, 1);
    }
    elseif ($action === 'clone' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmCloneCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCloneCliTheobaldTitle'), $body, 'confirm_clone', '', 0, 1);
    }
    elseif ($action === 'cancel' && !empty($user->rights->clitheobald->write))
    {
        $body = $langs->trans('ConfirmCancelCliTheobaldBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCancelCliTheobaldTitle'), $body, 'confirm_cancel', '', 0, 1);
    }

    return $formconfirm;
}

function clitheobaldCreateEventOperationOrder($vehicle)
{

    global $db;

    $error = 0;

    dol_include_once('operationorder/class/operationorder.class.php');

    //Liste des opérations du véhicule
    $res = $vehicle->getOperations();
    $TOperations = $vehicle->operations;

    //Liste OR liés au véhicule
    $TOperationOrders = array();

    $sql = "SELECT fk_object, km_on_creation FROM ".MAIN_DB_PREFIX."operationorder_extrafields WHERE fk_dolifleet_vehicule = '".$vehicle->id."'";
    $resql = $db->query($sql);

    if ($db->num_rows($resql) > 0)
    {
        $num = $db->num_rows($resql);
        $i = 0;

        while ($i < $num)
        {
            $obj = $db->fetch_object($resql);

            $operationOrder = new OperationOrder($db);
            $res = $operationOrder->fetch($obj->fk_object);

            $TOperationOrders[] = $operationOrder;

            $i++;
        }
    }

    //pour chaque opération
    foreach ($TOperations as $operation)
    {
        $eventtocreate = false;

        //pour chaque OR
        foreach ($TOperationOrders as $operationOrder)
        {
            //lignes de l'OR qui comporte le service de l'operation
            $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."operationorderdet WHERE fk_operation_order = '".$operationOrder->id."' AND fk_product = '".$operation->fk_product."'";
            $resql = $db->query($sql);

            if ($resql)
            {
                if ($db->num_rows($resql) > 0)
                {
                    if (empty($operation->km) && !empty($operation->delai_from_last_op))
                    {
                        //diff des dates
                        if (!empty($operationOrder->date_creation))
                        {
                            $interval  = checkORDate($operationOrder);

                            if($interval >= $operation->delai_from_last_op)
                            {
                                $eventtocreate = true;
                            }
                            else {
                                $eventtocreate = false;
                                break;
                            }
                        }

                    } elseif (!empty($operation->km) && empty($operation->delai_from_last_op))
                    {
                        //diff des km
                        if (!empty($vehicle->km))
                        {
                            $diff_km = checkORKm($operationOrder, $vehicle);

                            if ($diff_km >= $operation->km)
                            {
                                $eventtocreate = true;
                            }
                            else {
                                $eventtocreate = false;
                                break;
                            }
                        }

                    } elseif (!empty($operation->km) && !empty($operation->delai_from_last_op))
                    {
                        if (!empty($operationOrder->date_creation) && !empty($vehicle->km))
                        {
                            $interval = checkORDate($operationOrder);
                            $diff_km = checkORKm($operationOrder, $vehicle);

                            if (($interval >= $operation->delai_from_last_op) || ($diff_km >= $operation->km))
                            {
                                $eventtocreate = true;
                            }
                            else
                            {
                                $eventtocreate = false;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if($eventtocreate == true){

            $db->begin();

            $res = addActionCommEventKmVehiculeUpdate($vehicle, $operation);

            if($res <= 0){
                $db->rollback();
                $error++;
            } else {
                $db->commit();
            }
        }
    }

    if($error) return -1;
    else return 1;
}

function addActionCommEventKmVehiculeUpdate($vehicle, $operation){

    global $conf, $langs, $db, $user;

    $langs = new Translate('', $conf);
    $langs->setDefaultLang('fr_FR');
    $langs->loadLangs(array('main', 'admin', 'cron', 'dict'));
    $langs->load('clitheobald@clitheobald');

    $error = 0;

    if(empty($vehicle->array_options['options_atelier'])) return -1;

    $actionTriggerKey = 'MAIN_AGENDA_ACTIONAUTO_DOLIFLEETVEHICULE_KM';

    //ajout de l'événement
    if (!empty($conf->agenda->enabled) && !empty($conf->global->{$actionTriggerKey}))
    {
        $eventLabel = $langs->transnoentities('OperationOrderToCreate');
        $res = $vehicle->addActionComEvent($eventLabel, '', 'AC_OR', 0);

        //ajouter des éléments à l'évenement
        if ($res > 0)
        {
            $event = new ActionComm($db);
            $res = $event->fetch($res);

            if ($res > 0)
            {
                $atVehicle = $vehicle->array_options['options_atelier'];

                //entité de l'évenement = atelier du véhicule
                $sql = "UPDATE ".MAIN_DB_PREFIX."actioncomm SET entity = '" . $atVehicle. "' WHERE id='".$event->id."'";
                $resql = $db->query($sql);

                if($resql > 0)
                {
                    //objets liés (vehicule + operation)
                    $event->array_options['options_fk_product'] = $operation->fk_product;
                    $event->array_options['options_fk_vehicule'] = $vehicle->id;

                    //utilisateurs assignés à l'événement
                    $sql = "SELECT fk_user FROM ".MAIN_DB_PREFIX."usergroup_user WHERE entity = '".$atVehicle."'";
                    $resql = $db->query($sql);

                    if ($resql > 0)
                    {
                        $TUsers = array();
                        while ($obj = $db->fetch_object($resql))
                        {
                            $TUsers[]['id'] = $obj->fk_user;
                        }
                        if (!empty($TUsers))
                        {
                            $event->userassigned = $TUsers;
                            $event->userownerid = $TUsers[0]['id'];

                            $res = $event->update($user);

                            return $res;
                        }
                    } else
                    {
                        $error++;
                    }
                } else {
                    $error++;
                }
            } else {
                $error++;
            }
        } else {
            $error++;
        }

    }

    if($error) return -1;
    else return 0;
}

function checkORDate($operationOrder){

    $OR_date = date('Y-m-d', $operationOrder->date_operation_order);
    $today_date = date('Y-m-d', dol_now());

    $OR_date = new DateTime($OR_date);
    $today_date = new DateTime($today_date);

    $interval = $OR_date->diff($today_date);

    if(!empty($interval->y))  $interval_m = ($interval->y * 12) + $interval ->m;
    elseif(!empty($interval->y)) $interval_m = $interval->m;
    else $interval_m = 0;

    return $interval_m;
}

function checkORKm($operationOrder, $vehicle){

    $res = $operationOrder->fetch_optionals();

    if($res > 0 ) {
        $diff_km = $vehicle->km - intval($operationOrder->array_options['options_km_on_creation']);
        return $diff_km;
    } else {
        return -1;
    }
}

function getNbORVehicle ($idvehicle)
{
    global $db;

    $sql = "SELECT COUNT(rowid) as nb FROM ".MAIN_DB_PREFIX."operationorder_extrafields WHERE fk_dolifleet_vehicule = '". $idvehicle ."'";
    $resql = $db->query($sql);

    if($resql){
        $obj = $db->fetch_object($resql);

        $nbOperationOrder = $obj->nb;

        return $nbOperationOrder;

    } else{
        return -1;
    }
}

function getORByVehicle($idvehicle){


    global $db;

    $TOrs = array();

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."operationorder_extrafields WHERE fk_dolifleet_vehicule = '". $idvehicle ."'";
    $resql = $db->query($sql);

    if($resql){

        while($obj = $db->fetch_object($resql)){

            $TOrs[] = $obj->rowid;

        };

        return $TOrs;

    } else{
        return -1;
    }
}


/**
 * Return array of OR with fk_product and for id_vehicle
 *
 * @param 	int	$id_vehicle
 * @param   int $fk_product
 * @return 	array	if OK, 0 if no result, -1 if KO
 */


function getORByProductAndByVehicle($id_vehicle, $fk_product){

    global $conf, $db;

    $operationOrder = new OperationOrder($db);
    $operationOrderDet = new OperationOrderDet($db);

    $TORs = array();

    $error = 0;

    // Select
    $sql = "SELECT oo.rowid as id FROM ".MAIN_DB_PREFIX.$operationOrder->table_element." oo";
    $sql .= " JOIN ".MAIN_DB_PREFIX.$operationOrderDet->table_element." ood ON ( ood.fk_operation_order = oo.rowid )";
    $sql .= " JOIN ".MAIN_DB_PREFIX."operationorder_extrafields ooe ON ( ood.fk_operation_order = oo.rowid )";
    $sql .= " WHERE ood.fk_product = ".intval($fk_product);
    $sql .= " AND ooe.fk_dolifleet_vehicule = '".$id_vehicle."'";

    $resql = $db->query($sql);

    if($resql){
        if($db->num_rows($resql) > 0){
            while($obj = $db->fetch_object($resql)){

                $res = $operationOrder->fetch($obj->rowid);

                if($res) $TORs[] = $operationOrder;
                else $error++;
            }

            return $TORs;

        } else {
            return 0;
        }
    } else {
        return -1;
    }


}
