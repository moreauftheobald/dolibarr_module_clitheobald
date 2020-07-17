<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('clitheobald/class/clitheobald.class.php');
dol_include_once('clitheobald/lib/clitheobald.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once __DIR__.'/../class/defaultproductwarehouse.class.php';



$backtopage = GETPOST('backtopage', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$Tfk_default_warehouse = GETPOST('Tfk_default_warehouse', 'array');


$object = new Product($db);
$object->type = $type; // so test later to fill $usercancxxx is correct
//$extrafields = new ExtraFields($db);
$formproduct = new FormProduct($db);
$defaultWarehouse = new DefaultProductWarehouse($db);

// fetch optionals attributes and labels
//$extrafields->fetch_name_optionals_label($object->table_element);

if ($id > 0 || !empty($ref))
{
	$result = $object->fetch($id, $ref);
	if($result<1){
		exit($langs->trans("productNotFound"));
	}
	if (!empty($conf->product->enabled)) $upload_dir = $conf->product->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 0, $object, 'product').dol_sanitizeFileName($object->ref);
	elseif (!empty($conf->service->enabled)) $upload_dir = $conf->service->multidir_output[$object->entity].'/'.get_exdir(0, 0, 0, 0, $object, 'product').dol_sanitizeFileName($object->ref);

	if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO))    // For backward compatiblity, we scan also old dirs
	{
		if (!empty($conf->product->enabled)) $upload_dirold = $conf->product->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2), 1, 1).'/'.substr(substr("000".$object->id, -2), 0, 1).'/'.$object->id."/photos";
		else $upload_dirold = $conf->service->multidir_output[$object->entity].'/'.substr(substr("000".$object->id, -2), 1, 1).'/'.substr(substr("000".$object->id, -2), 0, 1).'/'.$object->id."/photos";
	}
}
else{
	accessForbidden();
}


$modulepart = 'product';


// Load translation files required by the page
$langs->loadLangs(array('products', 'other',"stocks", 'languages', 'multicompany@multicompany'));
if (empty($conf->stock->enabled))  accessforbidden();


if(empty($user->rights->produit->lire)) accessforbidden();


$dao = new DaoMulticompany($db);
$dao->getEntities();

/*
 * ACTIONS
 */
if($action == 'save_multicompany_default_warehouse'){

	$errors = 0;
	$multicompanypriceshare=array();
	foreach ($dao->entities as $entity)
	{

		$multicompanypriceshare[$entity->id] = GETPOST('fk_default_warehouse_'.$entity->id, 'int');

		if (intval($entity->id) === intval($object->entity) && intval($conf->entity) === intval($entity->id))
		{
			$object->fk_default_warehouse = $multicompanypriceshare[$entity->id];
			$res = $object->update($object->id, $user);

			if($res<1){
				setEventMessage($object->errors, 'errors');
				$errors++;
			}
		}else{
			$dWarehouse = new DefaultProductWarehouse($db);
			$res = $dWarehouse->updateWarehouse($user, $id, $entity->id, $multicompanypriceshare[$entity->id]);

			if($res<1){
				setEventMessage($dWarehouse->errors, 'errors');
				$errors++;
			}
		}


	}


	if($errors>0){
		setEventMessage('SaveError', 'errors');
	}
	else{
		setEventMessage('Saved');
	}
}



/*
 * View
 */

$entityDefaultWarehouse = $defaultWarehouse->getEachEntityDefaultWarehouse($object->id);

$title = $langs->trans('ProductServiceCard');
$helpurl = '';
$shortlabel = dol_trunc($object->label, 16);
if (GETPOST("type") == '0' || ($object->type == Product::TYPE_PRODUCT))
{
	$title = $langs->trans('Product')." ".$shortlabel." - ".$langs->trans('Card');
	$helpurl = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos';
}
if (GETPOST("type") == '1' || ($object->type == Product::TYPE_SERVICE))
{
	$title = $langs->trans('Service')." ".$shortlabel." - ".$langs->trans('Card');
	$helpurl = 'EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios';
}

llxHeader('', $title, $helpurl);

$head = product_prepare_head($object);
$titre = $langs->trans("CardProduct".$object->type);
$picto = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');

dol_fiche_head($head, 'entitydefautwarehouse', $titre, -1, $picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/product/list.php?restore_lastsearch_values=1&type='.$object->type.'">'.$langs->trans("BackToList").'</a>';
$object->next_prev_filter = " fk_product_type = ".$object->type;

$shownav = 1;
if ($user->socid && !in_array('product', explode(',', $conf->global->MAIN_MODULES_FOR_EXTERNAL))) $shownav = 0;

dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');






if (!empty($conf->multicompany->enabled))
{

//	if ($object->isProduct() && !empty($conf->stock->enabled))
//	{
//	}

	print '<br><br>';

	//var_dump($mc);
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="save_multicompany_default_warehouse">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';


	print load_fiche_titre($langs->trans("Multicompany"), '', '');

	print '<table class="noborder centpercent" width="100%">';


	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Entity").'</td>'."\n";
	print '<td align="center" >'.$langs->trans("Warehouse").'</td>';
	print '</tr>';

	$m = new ActionsMulticompany($db);

	$dao = new DaoMulticompany($db);
	$dao->getEntities();

	if (is_array($dao->entities))
	{
		foreach ($dao->entities as $entitie)
		{
			if (intval($entitie->id) === intval($object->entity) && intval($conf->entity) !== intval($entitie->id))
			{

				print '<tr class="oddeven" >';
				print '<td align="left" >';
				print $entitie->name.' <em>('.$entitie->label.')</em> ';
				print '</td>';
				print '<td align="center" >';
				// Default warehouse
					$warehouse = new Entrepot($db);
					$warehouse->fetch($object->fk_default_warehouse);
					print (!empty($warehouse->id) ? $warehouse->getNomUrl(1) : '');
				print '</td>';
				print '</tr>';
			}
			elseif (intval($conf->entity) === 1 || intval($conf->entity) === intval($entitie->id))
			{

				print '<tr class="oddeven" >';
				print '<td align="left" >';
				print $entitie->name.' <em>('.$entitie->label.')</em> ';
				print '<input type="hidden" name="fk_entity['.$entitie->id.']" value="'.$entitie->id.'"  />';
				print '</td>';
				print '<td align="center" >';

				$valueSelected = '';
				if(intval($entitie->id) === intval($object->entity)){
					$valueSelected = $object->fk_default_warehouse;
				}elseif (intval($entitie->id) !== intval($object->entity) && intval($entityDefaultWarehouse[$entitie->id])>0) {
					$valueSelected = $entityDefaultWarehouse[$entitie->id]->fk_warehouse;
				}

				if(!empty($user->rights->stock->creer)){
					print $formproduct->selectWarehouses($valueSelected, 'fk_default_warehouse_'.$entitie->id, 'warehouseopen', 1);
				}
				elseif(!empty($valueSelected)){
					$warehouse = new Entrepot($db);
					$warehouse->fetch($valueSelected);
					print (!empty($warehouse->id) ? $warehouse->getNomUrl(1) : '');
				}
				print '</td>';
				print '</tr>';
			}
		}

		print '<tr>';
		print '<td colspan="2" style="text-align:right;" >';
		print '<button type="submit" class="button" name="btnaction value="modify">'.$langs->trans("Modify").'</button>';
		print '</td>';
		print '</tr>';
	}
	print '</table>';

	print '</form>';


}
else{
	print '<div class="error" >'.$langs->trans('MulticompanyNotActivated').'</div>';
}

llxFooter();
