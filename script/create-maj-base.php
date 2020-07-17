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
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 */

if(!defined('INC_FROM_DOLIBARR')) {
	define('INC_FROM_CRON_SCRIPT', true);

	require '../config.php';
} else {
	global $db;
}

global $langs;

// AGENDA TRIGGERS
// Get max rank triggers available
$sqlAgenda = 'SELECT MAX(rang) as maxRank';
$sqlAgenda.= ' FROM '.MAIN_DB_PREFIX.'c_action_trigger';
$agendaTriggerRank=0;
$resql=$db->query($sqlAgenda);
if ($resql){
    $num = $db->num_rows($resql);
    if($num > 0){
        $obj = $db->fetch_object($resql);
        $agendaTriggerRank = $obj->maxRank;
    }
    $db->free($resql);
} else {
    dol_print_error($db);
}
$TAgendaTriggers = array();

$agendaTriggerRank ++;
$TAgendaTriggers[] = array(
    'code' => 'DOLIFLEETVEHICULE_KM', // This trigger does not exist but it used to trigger event creation by object imself, search this code with MAIN_AGENDA_ACTIONAUTO_ prefix
    'label' => $langs->transnoentities('DoliFleetVehiculeKm'),
    'description' => $langs->transnoentities('DoliFleetVehiculeChangeKm'),
    'elementtype' => 'dolifleet',
    'rang' => $agendaTriggerRank
);


foreach ($TAgendaTriggers as $agendaTrigger){

    // check if agenda trigger conf already exist before add it
    $sqlAgenda = 'SELECT COUNT(*) as alreadyExists  FROM '.MAIN_DB_PREFIX.'c_action_trigger as a WHERE  a.code = \''.$db->escape($agendaTrigger['code']).'\' LIMIT 1';
    $resql=$db->query($sqlAgenda);
    if ($resql){
        $obj = $db->fetch_object($resql);

        if(empty($obj->alreadyExists)){
            $sqlAgenda = 'insert into '.MAIN_DB_PREFIX.'c_action_trigger (code,label,description,elementtype,rang)';
            $sqlAgenda.= ' values (\''.$agendaTrigger['code'].'\',\''.$db->escape($agendaTrigger['label']).'\',\''.$db->escape($agendaTrigger['description']).'\',\''.$agendaTrigger['elementtype'].'\','.$agendaTrigger['rang'].');';
            dolibarr_set_const($db, 'MAIN_AGENDA_ACTIONAUTO_'.$agendaTrigger['code'], 1, 'chaine', 0, '', $conf->entity);
        }
        else{
            $sqlAgenda = 'UPDATE '.MAIN_DB_PREFIX.'c_action_trigger SET ';
            $Tfields = array();
            foreach ($agendaTrigger as $key => $value){
                $Tfields[] = $key.' = \''.$this->db->escape($value).'\'';
            }
            $sqlAgenda.= implode(', ', $Tfields);
            $sqlAgenda.= ' WHERE code = \''.$this->db->escape($agendaTrigger['code']).'\' ';
        }
        $resqlsave=$db->query($sqlAgenda);

        if(!$resqlsave){
            dol_print_error($db, 'UPDATE/SAVE AGENDA TRIGGER');
        }

    }
    $db->free($resql);
}


// Add default product warehouse table in database
dol_include_once('clitheobald/class/defaultproductwarehouse.class.php');
$o=new DefaultProductWarehouse($db);
$o->init_db_by_vars();

