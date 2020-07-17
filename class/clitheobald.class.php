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


class CliTheobald extends SeedObject
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

    public function getTOperationOrderType()
    {
        $TRes = array();
        $sql = 'SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_operationorder_type WHERE active = 1 ORDER BY `position`';
        $resql = $this->db->query($sql);
        if ($resql)
        {
            while ($obj = $this->db->fetch_object($resql))
            {
                $TRes[$obj->rowid] = $obj;
            }
        }
        else
        {
            dol_print_error($this->db);
        }

        return $TRes;
    }

    public static function getOperationOrderToCreateIds($day) {
        global $conf, $db;
        $sql = "SELECT id FROM ".MAIN_DB_PREFIX."actioncomm WHERE datep LIKE '%".$day."%' AND entity ='".$conf->entity."' AND code = 'AC_OR' AND percent ='0'";
        $TActionCommIds = array();
        $resql = $db->query($sql);

        if ($resql)
        {
            while ($obj = $db->fetch_object($resql))
            {
                $TActionCommIds[] = $obj->id;
            }
        }
        return $TActionCommIds;
    }
    public function getTDolifleetContractType()
    {
        $TRes = array();
        $sql = 'SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_dolifleet_contract_type WHERE active = 1';
        $resql = $this->db->query($sql);
        if ($resql)
        {
            while ($obj = $this->db->fetch_object($resql))
            {
                $TRes[$obj->rowid] = $obj;
            }
        }
        else
        {
            dol_print_error($this->db);
        }

        return $TRes;
    }

    public function addCombination($fk_c_dolifleet_contract_type, $fk_c_operationorder_type)
    {
        global $conf;

        $TCombination = $this->getCombinations();
        $TCombination[$fk_c_dolifleet_contract_type][$fk_c_operationorder_type] = 'OK';

        if (dolibarr_set_const($this->db, 'CLITHEOBALD_OPERATION_TYPE_ID_BY_TCONTRACT_TYPE_ID', json_encode($TCombination), 'chaine', 0, '', $conf->entity) > 0) return 1;
        else return -1;
    }

    public function removeCombination($fk_c_dolifleet_contract_type, $fk_c_operationorder_type)
    {
        global $conf;

        $TCombination = $this->getCombinations();
        unset($TCombination[$fk_c_dolifleet_contract_type][$fk_c_operationorder_type]);
        if (empty($TCombination[$fk_c_dolifleet_contract_type])) unset($TCombination[$fk_c_dolifleet_contract_type]);

        if (!empty($TCombination))
        {
            if (dolibarr_set_const($this->db, 'CLITHEOBALD_OPERATION_TYPE_ID_BY_TCONTRACT_TYPE_ID', json_encode($TCombination), 'chaine', 0, '', $conf->entity) > 0) return 1;
        }
        else
        {
            if (dolibarr_del_const($this->db, 'CLITHEOBALD_OPERATION_TYPE_ID_BY_TCONTRACT_TYPE_ID', 0) > 0) return 1;
        }

        return -1;
    }

    public function getCombinations()
    {
        global $conf;

        $combinations = json_decode($conf->global->CLITHEOBALD_OPERATION_TYPE_ID_BY_TCONTRACT_TYPE_ID, true);
        if (empty($combinations)) $combinations = array();

        return $combinations;
    }
}
