<?php
/**
 * \file	   prestataire.class.php
 * \brief	  Classe représentant un prestataire d'Euskal Moneta
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';


function clean_html_string($str)
{
	$str = strip_tags($str);
	$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
	$str = trim($str);
	$str = str_replace("\r\n", "\n", $str);
	return $str;
}


/**
 *  Classe représentant un contact d'un prestataire d'Euskal Moneta
 */
class ContactPrestataire extends Contact
{
	public $commune_eu;
	public $commune_fr;
	public $latitude;
	public $longitude;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db    Database handler
	 */
	function __construct($db)
	{
		parent::__construct($db);
	}

	function fetch($rowid)
	{
		parent::fetch($rowid);

		$this->address = clean_html_string($this->address);

		$tab = explode("/", $this->town);
		if (count($tab) === 2) {
			$this->commune_eu = trim($tab[0]);
			$this->commune_fr = trim($tab[1]);
		} else {
			$this->commune_eu = NULL;
			$this->commune_fr = $this->town;
		}

		// liste des champs personnalisés des fiches Contact
		$extrafields = new ExtraFields($this->db);
		$extralabels = $extrafields->fetch_name_optionals_label('socpeople');
		$result = $this->fetch_optionals($this->id, $extralabels);
		if ($result < 0) {
			$error; dol_print_error($this->db,$this->error);
		} else {
			$this->latitude = $this->array_options['options_latitude'];
			$this->longitude = $this->array_options['options_longitude'];
		}
	}

}

/**
 *  Classe représentant un prestataire d'Euskal Moneta
 */
class Prestataire extends Societe
{
	private $niveau_euskara;
	public $description_eu;
	public $description_fr;
	public $horaires_eu;
	public $horaires_fr;
	public $autres_lieux_activite_eu;
	public $autres_lieux_activite_fr;
	private $bureau_de_change;
	private $pays_basque_au_coeur;
	public $categorie_annuaire;
	public $categorie_annuaire_eu;
	public $categorie_annuaire_fr;
	public $date_agrement;
	public $activites;
	public $etiquettes;
	private $url_photo;
	public $adresses_activite;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db    Database handler
	 */
	function __construct($db)
	{
		parent::__construct($db);

		$this->bureau_de_change = FALSE;
		$this->pays_basque_au_coeur = FALSE;
		$this->activites = array();
		$this->etiquettes = array();
		$this->adresses_activite = array();
	}

	function fetch($rowid)
	{
		parent::fetch($rowid);

		$this->address = clean_html_string($this->address);

		// FIXME Bidouille pour contourner le fait que la colonne "town" de la table "societe" n'est pas assez grande.
		if ($this->town === "Mitikile-Larrori-Mendibile / Moncayolle-Larrory-Me") {
			$this->town = "Mitikile-Larrori-Mendibile / Moncayolle-Larrory-Mendibieu";
		}

		// liste des champs personnalisés des fiches Tiers
		$extrafields = new ExtraFields($this->db);
		$extralabels = $extrafields->fetch_name_optionals_label('company');
		$result = $this->fetch_optionals($this->id, $extralabels);
		if ($result < 0) {
			$error; dol_print_error($this->db,$this->error);
		} else {
			$this->niveau_euskara = $this->array_options['options_euskara'];
			$this->description_eu = clean_html_string($this->array_options['options_description_euskara']);
			$this->description_fr = clean_html_string($this->array_options['options_description_francais']);
			$this->horaires_eu = clean_html_string($this->array_options['options_horaires_euskara']);
			$this->horaires_fr = clean_html_string($this->array_options['options_horaires_francais']);
			$this->autres_lieux_activite_eu = clean_html_string($this->array_options['options_autres_lieux_activite_euskara']);
			$this->autres_lieux_activite_fr = clean_html_string($this->array_options['options_autres_lieux_activite_francais']);
			$this->date_agrement = $this->array_options['options_date_agrement'];
			$this->url_photo = $this->array_options['options_photo'];
		}

		// charge la liste des catégories assignées à ce prestataire
		// les catégories sont utilisées pour stocker :
		//  - si le prestataire est bureau de change
		//  - si le prestataire est d'accord pour être ambassadeur (3 catégories)
		//  - les catégories pour l'annuaire
		//  - les étiquettes
		$categorie = new Categorie($this->db);
		$categories = $categorie->containing($this->id, 'customer');
		foreach ($categories as $cat) {
			if (strpos($cat->label, 'Ambassadeur') !== FALSE) {
				// on ignore les catégories "Ambassadeur"
			} else if ($cat->label === 'Bureau de change') {
				// FIXME supprimer ce vieux code
				$this->bureau_de_change = TRUE;
				$this->etiquettes[] = $cat->id;
			} else if ($cat->label === 'Pays Basque au Coeur') {
				// FIXME supprimer ce vieux code
				$this->pays_basque_au_coeur = TRUE;
				$this->etiquettes[] = $cat->id;
			} else if ($cat->fk_parent == 360) {
				// Catégories filles de "--- Etiquettes"
				$this->etiquettes[] = $cat->id;
			} else {
				$this->activites[] = $cat->id;

				// FIXME supprimer ce vieux code qui suppose qu'un
				// prestataire n'a qu'une catégorie pour l'annuaire
				// Attention ce code est utilisé pour générer les 2
				// pages "annuaire par commune" et "annuaire par
				// catégorie" du site web.
				$this->categorie_annuaire = $cat->label;

				$tab = explode("/", $this->categorie_annuaire);
				if (count($tab) > 0) {
					$this->categorie_annuaire_eu = trim($tab[0]);
				}
				if (count($tab) > 1) {
					$this->categorie_annuaire_fr = trim($tab[1]);
				}
			}
		}

		// charge la liste des contacts de ce prestataire
		// pour chaque contact, s'il a le tag "Adresse d'activité", on le garde, sinon on l'ignore
		$contacts = $this->contact_array();
		foreach ($contacts as $contact_id => $contact_label) {
			$contact = new ContactPrestataire($this->db);
			$contact->fetch($contact_id);

			// on récupère les catégories/tags de ce contact
			$c = new Categorie($this->db);
			$categories = $c->containing($contact->id, 'contact');
			foreach ($categories as $cat) {
				if ($cat->label === "Adresse d'activité") {
					$this->adresses_activite[] = $contact;
				}
			}

		}
	}

	/**
	 *  Indique si le prestataire est une entreprise
	 *
	 *  @return bool    TRUE si le prestataire est une entreprise, FALSE sinon
	 */
	function estEntreprise()
	{
		return $this->typent_code === 'TE_PRO';
	}

	/**
	 *  Indique si le prestataire est une association
	 *
	 *  @return bool    TRUE si le prestataire est une association, FALSE sinon
	 */
	function estAssociation()
	{
		return $this->typent_code === 'TE_ASSO';
	}

	/**
	 *  Renvoie le niveau d'euskara du prestataire
	 *
	 *  @return int    niveau d'euskara (1, 2 ou 3)
	 */
	function getNiveauEuskara()
	{
		return $this->niveau_euskara;
	}

	/**
	 *  Renvoie la description en basque de l'activité du prestataire
	 *
	 *  @return string    description
	 */
	function getDescriptionBasque()
	{
		return $this->description_eu;
	}

	/**
	 *  Renvoie la description en français de l'activité du prestataire
	 *
	 *  @return string    description
	 */
	function getDescriptionFrancais()
	{
		return $this->description_fr;
	}

	/**
	 *  Indique si le prestataire est bureau de change
	 *
	 *  @return bool    TRUE si le prestataire est bureau de change, FALSE sinon
	 */
	function estBureauDeChange()
	{
		return $this->bureau_de_change;
	}

	/**
	 *  Indique si le prestataire est adhérent à Pays Basque au Coeur
	 *
	 *  @return bool    TRUE si le prestataire est adhérent à Pays Basque au Coeur, FALSE sinon
	 */
	function estPaysBasqueAuCoeur()
	{
		return $this->pays_basque_au_coeur;
	}

	/**
	 *  Renvoie la date d'adhésion du prestataire
	 *
	 *  @return date    date d'adhésion
	 */
	function getDateAdhesion()
	{
	}

	/**
	 *  Renvoie l'URL de la photo du prestataire
	 *
	 *  @return string    URL de la photo
	 */
	function getUrlPhoto()
	{
		return $this->url_photo;
	}

}
?>

