<?php
/**
 * Ce module est utilisé pour afficher une boite "Equipement des
 * prestataires en TPE" dans la page d'accueil.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


class box_equipement_tpe extends ModeleBoxes
{
	var $boxcode="equipement_tpe";
	var $boximg="object_generic";
	var $boxlabel="Equipement des prestataires en TPE";
	var $depends = array("adherent");

	var $db;
	var $param;

	var $info_box_head = array();
	var $info_box_contents = array();


	/**
	 *  Constructor
	 *
	 *  @param  DoliDB	$db      	Database handler
	 *  @param	string	$param		More parameters
	 */
	function __construct($db,$param='')
	{
		global $conf, $user;

		$this->db = $db;

		// disable module for such cases
		$listofmodulesforexternal=explode(',',$conf->global->MAIN_MODULES_FOR_EXTERNAL);
		if (! in_array('adherent',$listofmodulesforexternal) && ! empty($user->societe_id)) $this->enabled=0;	// disabled for external users
	}

	/**
	 *  Load data into info_box_contents array to show array later.
	 *
	 *  @return	void
	 */
	function loadBox()
	{
		global $user, $langs, $db, $conf;
		$langs->load("boxes");
		$langs->load("box_equipement_tpe");

		$this->info_box_head = array('text' => $langs->trans("BoxTitleEquipementTPE"));

		if ($user->rights->adherent->lire)
		{
			$sql = "SELECT COUNT(*) AS nb";
			$sql.= " FROM ".MAIN_DB_PREFIX."societe soc";
			$sql.= " JOIN ".MAIN_DB_PREFIX."socpeople sp ON soc.rowid = sp.fk_soc";
			$sql.= " JOIN ".MAIN_DB_PREFIX."socpeople_extrafields spe ON sp.rowid = spe.fk_object";
			$sql.= " JOIN ".MAIN_DB_PREFIX."categorie_contact cc ON sp.rowid = cc.fk_socpeople";
			$sql.= " JOIN ".MAIN_DB_PREFIX."categorie cat ON cc.fk_categorie = cat.rowid AND cat.label = 'Adresse d''activité'";
			$sql.= " WHERE soc.code_client IS NOT NULL AND soc.client = 1 AND soc.status = 1";
			$sql.= " AND spe.equipement_pour_euskokart = ";

			$result_nb_famoco = $db->query($sql . "'oui_famoco'");
			$result_nb_smartphone = $db->query($sql . "'oui_smartphone_perso'");
			$result_nb_pb_technique = $db->query($sql . "'non_techniquement_impossible'");
			$result_nb_pas_necessaire = $db->query($sql . "'non_pas_necessaire'");

			if ($result_nb_famoco && $result_nb_smartphone && $result_nb_pb_technique && $result_nb_pas_necessaire)
			{
				$obj_nb_famoco = $db->fetch_object($result_nb_famoco);
				$obj_nb_smartphone = $db->fetch_object($result_nb_smartphone);
				$obj_nb_pb_technique = $db->fetch_object($result_nb_pb_technique);
				$obj_nb_pas_necessaire = $db->fetch_object($result_nb_pas_necessaire);

				$ligne = 0;

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("TotalPrestatairesEquipes"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_nb_famoco->nb + $obj_nb_smartphone->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombreFamoco"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_nb_famoco->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombreSmartphone"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_nb_smartphone->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombrePbTechnique"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_nb_pb_technique->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombrePasNecessaire"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_nb_pas_necessaire->nb,
				);

				$db->free($result_nb_famoco);
				$db->free($result_nb_smartphone);
				$db->free($result_nb_pb_technique);
				$db->free($result_nb_pas_necessaire);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => 'align="left"',
					'maxlength'=>500,
					'text' => ($db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'align' => 'left',
				'text' => $langs->trans("ReadPermissionNotAllowed"),
			);
		}
	}

	/**
	 * Method to show box
	 *
	 * @param   array   $head       Array with properties of box title
	 * @param   array   $contents   Array with properties of box lines
	 * @param   int     $nooutput   No print, only return string
	 * @return  string
	 */
	function showBox($head = null, $contents = null, $nooutput=0)
	{
		parent::showBox($this->info_box_head, $this->info_box_contents);
	}

}

