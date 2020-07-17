<?php

class cron_theobald
{

	private $db;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	public function updateTimeAndPriceFromVirtualProduct()
	{
		global $conf;

		$this->langs = new Translate('', $conf);
		$this->langs->setDefaultLang('fr_FR');
		$this->langs->loadLangs(array('main', 'admin', 'cron', 'dict'));
		$this->langs->load('clitheobald@clitheobald');

		$this->errors = array();
		$this->output = '';
		$this->debug = false;

		$this->TprodIdUpdated=array();

		$now = dol_now();
		$test = dol_print_date($now, "%d/%m/%Y %H:%M:%S");



		$this->output .= '<p>' . $this->langs->trans('2lTrucksCRONUpdateTimeAndPriceFromVirttualProduct', $test) . '</p>';

		/*
		 * Selectionner les produits qui sont des parents
		 */
		$sql = "SELECT DISTINCT pa.fk_product_pere FROM " . MAIN_DB_PREFIX . "product_association as pa ";
		$sql .= " WHERE pa.fk_product_pere NOT IN (SELECT DISTINCT pf.fk_product_fils FROM " . MAIN_DB_PREFIX . "product_association as pf) ";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$this->db->begin();

				$result=$this->recurUpdateTimeAndPrice($obj->fk_product_pere);

				if ($result < 0) {
					$this->db->rollback();
					$this->errors[$obj->rowid] = $this->langs->trans("2lTrucksCRONErrProduct", $obj->fk_product_pere);
				} else {
					$TprodIdUpdated[$obj->fk_product_pere] = $obj->fk_product_pere;
					$this->db->commit();
				}
			}
		} else $this->errors[] = "SQL error : " . $sql ."\n".$this->db->lasterror();

		// Error reporting
		if (!empty($this->errors)) {
			$comment = '<p>' . $this->langs->trans('2lTrucksCRONKO', count($this->errors), count($this->TprodIdUpdated)) . '</p>';

			$output = array();
			foreach ($this->errors as $id => $errorMessage) {
				$output[] = $errorMessage;
			}
			$this->output .= '<ul><li>' . implode('</li><li>', $output) . '</li></ul>';
		} else {
			$now = dol_now();
			$test = dol_print_date($now, "%d/%m/%Y %H:%M:%S");
			$comment = '<p>' . $this->langs->trans('2lTrucksCRONOK', $test, count($this->TprodIdUpdated)) .'</p>';
		}

		$this->output .= $comment;

		return 0;
	}

	private function recurUpdateTimeAndPrice($idProduct = 0)
	{
		//If product ID empty or already done, exit
		// Basic of recurring function => Exit condition
		if (empty($idProduct) || in_array($idProduct, $this->TprodIdUpdated)) {
			return 0;
		}

		$error=0;

		$sql = "SELECT DISTINCT pa.fk_product_fils FROM " . MAIN_DB_PREFIX . "product_association as pa WHERE pa.fk_product_pere=" . $idProduct;

		if ($this->debug) {
			$this->output .= '<p>Pére ' . $idProduct . '</p>';
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if ($this->debug) {
					$this->output .= '<p>Child ' . $obj->fk_product_fils . '</p>';
				}
				$result=$this->recurUpdateTimeAndPrice($obj->fk_product_fils);
				if ($result<0) {
					return $result;
				}
			}
		}

		$sql = "SELECT pa.qty, p.price, p.ref, p.duration
				FROM " . MAIN_DB_PREFIX . "product_association as pa
				INNER JOIN " . MAIN_DB_PREFIX . "product as p  ON p.rowid=pa.fk_product_fils
				WHERE pa.fk_product_pere=" . $idProduct;

		if ($this->debug) {
			$this->output.='<p>'.$sql.'</p>';
		}
		$this->db->begin();

		$resql = $this->db->query($sql);
		if ($resql) {

			$totalTimeSecond = 0;
			$totalAmount = 0;

			while ($obj = $this->db->fetch_object($resql)) {

				$duration_value = substr($obj->duration, 0, dol_strlen($obj->duration) - 1);
				$duration_unit = substr($obj->duration, -1);

				if (!empty($duration_value)) {
					switch ($duration_unit) {
						default:
						case 'i':
							$mult = 60;
							break;
						case 'h':
							$mult = 3600;
							break;
						case 'd':
							$mult = 3600 * 24;
							break;
						case 'w':
							$mult = 3600 * 24 * 7;
							break;
						case 'm':
							$mult = (int)3600 * 24 * (365 / 12); // Average month duration
							break;
						case 'y':
							$mult = 3600 * 24 * 365;
							break;
					}
					$totalTimeSecond += $duration_value * $mult * $obj->qty;
				}
				$totalAmount += ((double)$obj->price) * $obj->qty;
				if ($this->debug) {
					$this->output .= '<p>$obj->price ' . ((double)$obj->price_min) . ' Qty=' . $obj->qty . '</p>';
				}
			}

			if ($this->debug) {
				$this->output.='<p>$totalTimeSecond '.$totalTimeSecond.'</p>';
				$this->output.='<p>$totalAmount '.$totalAmount.'</p>';
			}

			$this->TprodIdUpdated[$idProduct] = $idProduct;

			if (!empty($totalAmount)) {
				$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'product SET price=\'' . price2num($totalAmount, 'MU') .'\'';
				//$sql = ',price_min=\'' . price2num($totalAmount, 'MU') . '\' ';
				$sql .= ' WHERE rowid=' . $idProduct;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->errors[$idProduct] = "SQL UPDATE error : " . $sql."\n".$this->db->lasterror();
					$error++;
				}
				if ($this->debug) {
					$this->output .= '<p>' . $sql . '</p>';
				}
			}

			if (!empty($totalTimeSecond)) {
				$TimeInDay=0;
				$TimeInHour = price2num($totalTimeSecond / 3600, 'MT');
				//llx_product.duration column is varchar(5), so if time in hour xx.xxh is too long we convert in day
				if (strlen($TimeInHour)>4) {
					$TimeInDay=price2num($TimeInHour / 24, 'MT');
				}
				if (empty($TimeInDay)) {
					$timeDuration=$TimeInHour.'h';
				} else {
					$timeDuration=$TimeInDay.'d';
				}

				$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'product SET duration=\'' . $timeDuration.'\' ';
				$sql .= ' WHERE rowid=' . $idProduct;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$this->errors[$idProduct] = "SQL UPDATE error : " . $sql."\n".$this->db->lasterror();
					$error++;
				}
				if ($this->debug) {
					$this->output .= '<p>' . $sql . '</p>';
				}
			}

		} else {
			$this->errors[$idProduct] = "SQL error : " . $sql."\n".$this->db->lasterror();
			$error++;
		}

		if (!empty($error)) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}


    public function getKmVehicles()
    {
       	global $user;

	    set_time_limit(0);

        dol_include_once('/clitheobald/lib/clitheobald.lib.php');
        dol_include_once('/dolifleet/class/vehicule.class.php');

        $this->errors = array();
        $this->output = '';
        $nbLinesProcessed = 0;

        $now = dol_now();
        $date = dol_print_date($now,"%d/%m/%Y %H:%M:%S");
        $this->output.= '<p>Début de la tâche planifiée de mise à jour des véhicules</p>';

        //liste des véhicules sur le dolibarr

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_dolifleet_vehicule_mark WHERE code = 'VOLVO'";
        $resql = $this->db->query($sql);

        if($resql > 0)
        {
            $obj = $this->db->fetch_object($resql);
            $vehicle = new doliFleetVehicule($this->db);
            $TVehiclesDoli = $vehicle->fetchAll('', '', array('fk_vehicule_mark' => $obj->rowid, 'status' => 1));
        }
        else {
            $this->errors[] = "Marque VOLVO introuvable";
        }

        if(!empty($TVehiclesDoli))
        {

            //liste des véhicules dont on a accès depuis l'API
            $TVehiclesAPI = array();

            $moreDataAvailable = true;
            $data = array();
            $lastVin = 0;

            while($moreDataAvailable == true)
            {
                if(!empty($lastVin)) $data = array('lastVin' => $lastVin);

                $rep = $this->callAPI('GET', 'https://api.volvotrucks.com/vehicle/vehicles', $data, array('Accept: application/x.volvogroup.com.vehicles.v1.0+json; UTF-8'));

                if ($rep != -1)
                {
                    foreach ($rep['vehicleResponse']['vehicles'] as $vehicle)
                    {
                        $TVehiclesAPI[] = $vehicle['vin'];
                    }
                }
                else
                {
                    $this->errors[] = "Connexion avec l'API impossible";
                }

                $moreDataAvailable = $rep['moreDataAvailable'];

                if($moreDataAvailable) $lastVin = end($TVehiclesAPI);
            }

            foreach ($TVehiclesDoli as $vehicle)
            {
                //on traite seulement les véhicules disponibles dans l'API
                if (in_array($vehicle->vin, $TVehiclesAPI))
                {
                    $rep = $this->callAPI('GET', 'https://api.volvotrucks.com/vehicle/vehiclestatuses', array('vin' => $vehicle->vin, 'latestOnly' => 'true', 'trigger' => 'DISTANCE_TRAVELLED'), array('Accept: application/x.volvogroup.com.vehiclestatuses.v1.0+json; UTF-8'));

                    if ($rep != -1)
                    {
                        $km = $rep['vehicleStatusResponse']['vehicleStatuses'][0]['hrTotalVehicleDistance'] / 1000;
                        $date_km = $rep['vehicleStatusResponse']['vehicleStatuses'][0]['receivedDateTime'];

                        if(!empty($date_km)) {
                            $TDateStr = explode('T', $date_km);
                            $date_km = $TDateStr[0];
                        }

                        if (!empty($km))
                        {
                            $this->db->begin();

                            //Màj du nombre de kilomètres du véhicule
                            $vehicle->km = $km;
                            $vehicle->km_date = $date_km;
                            $res = $vehicle->update($user);

                            if($res < 0 ){
                                $this->errors[] = 'Impossible de màj ce véhicule : '.$vehicle->vin." (fonction update())";
                                $this->db->rollback();
                            } else {

                                $res = clitheobaldCreateEventOperationOrder($vehicle);
                                if($res <= 0 ){
                                    $this->errors[] = "Impossible de créer d'événement pour ce véhicule : ".$vehicle->vin;
                                }

                                $this->db->commit();

                                $nbLinesProcessed++;
                            }
                        } else {
                            $this->errors[] = "Impossible de màj ce véhicule : ".$vehicle->vin." (aucune information de kilométrage provenant de l'API)";
                        }
                    } else {
                            $this->errors[] = "Impossible de màj ce véhicule : ".$vehicle->vin;
                    }
                } else {
                    $this->errors[] = "Impossible de màj ce véhicule : ".$vehicle->vin." (aucun accès API à ce véhicule)";
                }
            }

        } else {
            $this->errors[] = "Impossible de récupérer la liste des véhicules";
        }

        if (empty($this->errors))
        {
            $now = dol_now();
            $date = dol_print_date($now,"%d/%m/%Y %H:%M:%S");
            $comment = '<p>Traitement terminé avec succés ('.$nbLinesProcessed.' véhicules traités)</p>';
        } else {

            $comment = '<p>Erreur lors du traitement. Les modifications sur les lignes en erreur n\'ont pas été appliquées. '.count($this->errors).' lignes en erreur sur '.$nbLinesProcessed.' lignes traités</p>';

            foreach ($this->errors as $id => $errorMessage)
            {
                $output[] = $errorMessage;
            }

            $this->output .= '<ul><li>' . join('</li><li>', $output) . '</li></ul>';
        }

        $this->output.= $comment;

        return empty($errors) ? 0 : 1;
    }

	function CallAPI($method, $url, $data = false, $header = false)
    {

        global $conf;

        $curl = curl_init();

        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;

            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;

            default:
                if ($data) $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $conf->global->THEO_API_USER.':'. $conf->global->THEO_API_PASS);

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        if(curl_getinfo($curl,  CURLINFO_HTTP_CODE) == 200){
            $TVehicleStatus = dol_json_decode($result, true);
            curl_close($curl);
            return $TVehicleStatus;
        } else {
            curl_close($curl);
            return -1;
        }
    }
}
