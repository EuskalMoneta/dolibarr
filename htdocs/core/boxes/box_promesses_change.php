<?php
/**
 * Ce module est utilisé pour afficher une boite "Promesses de change"
 * dans la page d'accueil.
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';


/**
 * Class to manage the box to show last members
 */
class box_promesses_change extends ModeleBoxes
{
	var $boxcode="promesses_change";
	var $boximg="object_generic";
	var $boxlabel="Promesses de change en eusko numériques";
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
		$langs->load("box_promesses_change");

		$this->info_box_head = array('text' => $langs->trans("BoxTitlePromessesChange"));

		if ($user->rights->adherent->lire)
		{
			$nb = 0;

			$sql = "SELECT COUNT(*) AS nb, SUM(promesse_change_mensuel_eusko_numerique) AS somme";
			$sql.= " FROM ".MAIN_DB_PREFIX."adherent_extrafields";
			$sql.= " WHERE promesse_change_mensuel_eusko_numerique IS NOT NULL";

			$result = $db->query($sql);
			if ($result)
			{
				$obj = $db->fetch_object($result);
				$nb = $obj->nb;

				$this->info_box_contents[0][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("NombreDePromesses"),
				);

				$this->info_box_contents[0][] = array(
					'td' => 'align="right"',
					'text' => $nb,
				);

				$this->info_box_contents[1][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("TotalDesPromesses"),
				);

				$this->info_box_contents[1][] = array(
					'td' => 'align="right"',
					'text' => $obj->somme,
				);

				$this->info_box_contents[2][] = array(
					'td' => 'align="left"',
					'text' => $langs->trans("MoyenneDesPromesses"),
				);

				$this->info_box_contents[2][] = array(
					'td' => 'align="right"',
					'text' => round($obj->somme/$nb, 2),
				);

				if ($nb==0)
					$this->info_box_contents[$line][0] = array(
						'td' => 'align="center"',
						'text'=>$langs->trans("PasDePromesse"),
					);

				$db->free($result);
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

