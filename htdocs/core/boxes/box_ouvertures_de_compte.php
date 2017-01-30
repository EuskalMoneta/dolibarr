<?php
/**
 * Ce module est utilisé pour afficher une boite "Ouvertures de compte"
 * dans la page d'accueil.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


class box_ouvertures_de_compte extends ModeleBoxes
{
	var $boxcode="ouverture_compte";
	var $boximg="object_generic";
	var $boxlabel="Ouvertures de compte Eusko numérique";
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
		$langs->load("box_ouvertures_de_compte");

		$this->info_box_head = array('text' => $langs->trans("BoxTitleOuverturesCompte"));

		if ($user->rights->adherent->lire)
		{
			$sql_comptes = "SELECT COUNT(*) AS nb";
			$sql_comptes.= " FROM ".MAIN_DB_PREFIX."adherent adh";
			$sql_comptes.= " JOIN ".MAIN_DB_PREFIX."adherent_extrafields adh_extra";
			$sql_comptes.= " ON adh.rowid = adh_extra.fk_object";
			$sql_comptes.= " WHERE adh_extra.documents_pour_ouverture_du_compte_valides = 1";
			$sql_comptes.= " AND adh.login LIKE ";

			$result_comptes_prestataires = $db->query($sql_comptes . "'Z%'");
			$result_comptes_utilisateurs = $db->query($sql_comptes . "'E%'");

			$sql_prelevements = "SELECT COUNT(*) AS nb, SUM(prelevement_change_montant) AS total";
			$sql_prelevements.= " FROM ".MAIN_DB_PREFIX."adherent_extrafields";
			$sql_prelevements.= " WHERE prelevement_change_montant > 0";

			$result_prelevements = $db->query($sql_prelevements);

			if ($result_comptes_prestataires && $result_comptes_utilisateurs && $result_prelevements)
			{
				$obj_comptes_prestataires = $db->fetch_object($result_comptes_prestataires);
				$obj_comptes_utilisateurs = $db->fetch_object($result_comptes_utilisateurs);
				$obj_prelevements = $db->fetch_object($result_prelevements);

				$ligne = 0;

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombreComptesOuvertsPrestataires"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_comptes_prestataires->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombreComptesOuvertsUtilisateurs"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_comptes_utilisateurs->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombrePrelevementsAuto"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_prelevements->nb,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("TotalDesChanges"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => $obj_prelevements->total,
				);

				$this->info_box_contents[++$ligne][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("MoyenneDesChanges"),
				);

				$this->info_box_contents[$ligne][] = array(
					'td' => 'align="right"',
					'text' => round($obj_prelevements->total/$obj_prelevements->nb, 2),
				);

				$db->free($result_comptes_prestataires);
				$db->free($result_comptes_utilisateurs);
				$db->free($result_prelevements);
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
	 *	Method to show box
	 *
	 *	@param	array	$head       Array with properties of box title
	 *	@param  array	$contents   Array with properties of box lines
	 *	@return	void
	 */
	function showBox($head = null, $contents = null)
	{
		parent::showBox($this->info_box_head, $this->info_box_contents);
	}

}

