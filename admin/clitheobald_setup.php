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
 * 	\file		admin/clitheobald.php
 * 	\ingroup	clitheobald
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include '../../main.inc.php'; // From htdocs directory
if (! $res) {
    $res = @include '../../../main.inc.php'; // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/clitheobald.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');
dol_include_once('clitheobald/class/clitheobald.class.php');

$object = new CliTheobald($db);

// Translations
$langs->loadLangs(array('clitheobald@clitheobald', 'admin', 'other'));

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/[set\_|del\_]TOperationTypeIdByContractTypeId(.*)/', $action, $reg))
{
    $value=$reg[1];

    preg_match('/\[(\d+)\]\[(\d+)\]/', $value, $TId);
    if (!empty($TId[1]) && !empty($TId[2]))
    {
        $fk_c_dolifleet_contract_type = $TId[1];
        $fk_c_operationorder_type = $TId[2];

        if (preg_match('/set_.*/', $action)){
			$res = $object->addCombination($fk_c_dolifleet_contract_type, $fk_c_operationorder_type);
		}else{
        	$res = $object->removeCombination($fk_c_dolifleet_contract_type, $fk_c_operationorder_type);
		}

        if ($res > 0)
        {
            header("Location: ".$_SERVER["PHP_SELF"]);
            exit;
        }
        else
        {
            dol_print_error($db);
        }
    }
}
elseif (preg_match('/set_(.*)/', $action, $reg))
{
	$code=$reg[1];

	$theValue = GETPOST($code);
	if(is_array($theValue)){
		$theValue = implode(',',$theValue);
	}

	if (dolibarr_set_const($db, $code, $theValue, 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/', $action, $reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "CliTheobaldSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = clitheobaldAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104003Name"),
    -1,
    "clitheobald@clitheobald"
);

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';


if(!function_exists('setup_print_title')){
    print '<div class="error" >'.$langs->trans('AbricotNeedUpdate').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
    exit;
}

setup_print_title("Parameters");

// Example with imput
dol_include_once('operationorder/class/operationorderstatus.class.php');
$confKey = 'OPENED_OPERATION_ORDER_SEARCH_STATUS_VEHICULES_FILTER';
if(class_exists('OperationOrderStatus')){
	$customInputHtml = OperationOrderStatus::formSelectStatus($confKey, explode(',', $conf->global->{$confKey}),0,true,0,array('entity' => $conf->entity));
	setup_print_input_form_part($confKey, $langs->trans('DoSearchOpenedOperationOrderOnThisStatus'), '', array(), $customInputHtml);
}
else{
	setup_print_input_form_part($confKey, $langs->trans('DoSearchOpenedOperationOrderOnThisStatus'));
}

$confKey = 'OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT';
if(class_exists('OperationOrderStatus')){

	$oOStatus = new OperationOrderStatus($db);
	$TStatus = $oOStatus->fetchAll(0, false, array('status' => 1, 'entity' => $conf->entity));

	if(!empty($TStatus)){
		$TAvailableStatus = array();
        foreach ($TStatus as $status ){
                $TAvailableStatus[$status->code] = $status->label;
        }

		$customInputHtml = $form->selectarray('OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT', $TAvailableStatus, $conf->global->OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT, 1);

	} else {
		setEventMessage($langs->trans('MissingOperationOrderStatus'),'errors');
	}
	setup_print_input_form_part($confKey, $langs->trans('OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT'), '', array(), $customInputHtml);
}
else{
	setup_print_input_form_part($confKey, $langs->trans('OPERATION_ORDER_STATUS_USED_TO_CREATE_OR_FROM_EVENT'));
}
setup_print_input_form_part('THEO_PRICE_MAJORATION_PERCENT_ON_PRODUCT_PRICE_MODIFY', '','', array(), 'input', $langs->trans('THEO_PRICE_MAJORATION_PERCENT_ON_PRODUCT_PRICE_MODIFY_help'));


//BarCodeSetup
$confKey='OPERATION_ORDER_BARCODE_TYPE';
$TAvailableBarCode = array();
$sql = "SELECT rowid, code as encoding, libelle as label, coder, example";
$sql.= " FROM ".MAIN_DB_PREFIX."c_barcode_type";
$sql.= " WHERE entity = ".$conf->entity;
$sql.= " ORDER BY code";

dol_syslog("clitheobald/admin/clitheobald_setup.php", LOG_DEBUG);
$resql=$db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	if ($num > 0) {
		while ($obj = $db->fetch_object($resql)) {
			$TAvailableBarCode[$obj->encoding] = $obj->label . ' ' . $langs->trans('BarcodeDesc' . $obj->encoding);
		}
	}
} else {
	setEventMessage($db->lasterror,'errors');
}

$customInputHtml = $form->selectarray($confKey, $TAvailableBarCode, $conf->global->OPERATION_ORDER_BARCODE_TYPE, 1);

setup_print_input_form_part($confKey, $langs->trans($confKey), '', array(), $customInputHtml);

setup_print_title($langs->trans('CliTheobaldParametersAPITitle'));

setup_print_input_form_part('THEO_API_USER',$langs->trans('Login'),'',array(),'input');
setup_print_input_form_part('THEO_API_PASS',$langs->trans('Password'),'',array('type'=> 'password'),'input');


print '</table><br /><br />';


print '<table class="noborder" width="100%">';

//setup_print_title("Parameters");


$TOperationOrderType = $object->getTOperationOrderType();
$TDolifleetContractType = $object->getTDolifleetContractType();

print '<tr class="liste_titre">';
print '<th width="15%"></th>';
print '<th class="center" colspan="'.count($TOperationOrderType).'"><b>Type d\'OR</b></th>';
print '</tr>';


$TCombination = $object->getCombinations();

print '<tr class="oddeven">';
print '<td><b>Type contrat VH</b></td>';
foreach ($TOperationOrderType as $fk_c_operationorder_type => $obj)
{
    print '<td class="center">'.$obj->label.'</td>';

}
print '</tr>';

$use_javascript_ajax = $conf->use_javascript_ajax;
$conf->use_javascript_ajax = 0;

foreach ($TDolifleetContractType as $fk_c_dolifleet_contract_type => $obj)
{
    print '<tr class="oddeven">';

    print '<td>'.$obj->label.'</td>';
    foreach ($TOperationOrderType as $fk_c_operationorder_type => $obj)
    {
        $value = isset($TCombination[$fk_c_dolifleet_contract_type][$fk_c_operationorder_type]) ? 1 : 0;
        if ($value) $conf->global->{'TOperationTypeIdByContractTypeId['.$fk_c_dolifleet_contract_type.']['.$fk_c_operationorder_type.']'} = 1;

        print '<td class="center">';
        ajax_constantonoff('TOperationTypeIdByContractTypeId['.$fk_c_dolifleet_contract_type.']['.$fk_c_operationorder_type.']');
        print '</td>';

        if ($value) unset($conf->global->{'TOperationTypeIdByContractTypeId['.$fk_c_dolifleet_contract_type.']['.$fk_c_operationorder_type.']'});

    }

    print '</tr>';
}

$conf->use_javascript_ajax = $use_javascript_ajax;

print '</table>';


dol_fiche_end(-1);

llxFooter();

$db->close();
