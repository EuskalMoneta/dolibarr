#!/usr/bin/php
<?php
$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI to execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit;
}

// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------

// Include Dolibarr environment
require_once($path."../../htdocs/master.inc.php");
// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('','administrateur');	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


require_once 'prestataire.class.php';

/**
 * Charge la liste de tous les prestataires agréés en activité.
 * 
 * @param $db database handler
 * @return Prestataire[] tableau de prestataires
 */
function getPrestataires($db)
{
	print "--- Chargement de la liste des prestataires.\n";

	$prestataires = array();

	$sql = "SELECT rowid";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe";
	$sql.= " WHERE code_client IS NOT NULL AND client = 1 AND status = 1";
	$sql.= " ORDER BY code_client ASC";

	dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num)
		{
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj)
				{
					$prest = new Prestataire($db);
					$result = $prest->fetch($obj->rowid);
					if ($result < 0) { $error; dol_print_error($db,$prest->error); }
					else {
						$prestataires[] = $prest;
					}
				}
				$i++;
			}
		}
	}
	else
	{
		$error++;
		dol_print_error($db);
	}

	print "--- Chargement de la liste des prestataires terminé.\n";

	return $prestataires;
}

/**
 * Charge la liste de toutes les activités.
 * 
 * @param $db database handler
 * @return Categorie[] tableau d'activités
 */
function getActivites($db)
{
	print "--- Chargement de la liste des activités.\n";

	$activites = array();

	// chargement de toutes les catégories prestataires de 1er niveau
	$categories = array();
	$tmp = new Categorie($db);
	$categories = $tmp->get_main_categories(2);

	// filtrage de la liste de catégories pour enlever celles qui ne
	// sont pas des activités
	foreach ($categories as $cat) {
		if ($cat->label !== '--- Etiquettes'
			&& $cat->label !== '--- Euskal Moneta') {
			$activites[] = $cat;
		}
	}

	print "--- Chargement de la liste des activités terminé.\n";

	return $activites;
}

/**
 * Charge la liste de toutes les étiquettes.
 * 
 * @param $db database handler
 * @return Categorie[] tableau d'étiquettes
 */
function getEtiquettes($db)
{
	print "--- Chargement de la liste des étiquettes.\n";

	$etiquettes = array();

	// chargement de la catégories "Etiquettes"
	$cat_etiquettes = new Categorie($db);
	$result = $cat_etiquettes->fetch(0, '--- Etiquettes');
	if ($result < 0) {
		dol_print_error('', $user->error);
		$etiquettes = null;
	} else {
		// récupération de toutes les catégories filles de "Etiquettes"
		$etiquettes = $cat_etiquettes->get_filles();
	}

	print "--- Chargement de la liste des étiquettes terminé.\n";

	return $etiquettes;
}

class Ville
{
	public $id;
	public $code_postal;
	public $nom;
}

/**
 * Charge la liste de toutes les villes dans lesquelles il y a des prestataires.
 * 
 * @param $db database handler
 * @return tableau de villes avec des clés-valeurs [nom de la ville -> objet Ville]
 * Note : le tableau des villes est indexé avec le nom des villes car dans un objet
 * Prestataire, nous n'avons que cette information. De cette manière, ce tableau
 * permet de récupérer les autres infos (id, code postal) à partir du nom de la ville.
 */
function getVilles($db)
{
	print "--- Chargement de la liste des villes.\n";

	$villes = array();

	// chargement des villes des prestataires agréés en activité
	$sql = "SELECT ".MAIN_DB_PREFIX."c_ziptown.rowid,";
	$sql.= " ".MAIN_DB_PREFIX."c_ziptown.zip,";
	$sql.= " ".MAIN_DB_PREFIX."c_ziptown.town";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe";
	$sql.= " JOIN ".MAIN_DB_PREFIX."c_ziptown ON ".MAIN_DB_PREFIX."societe.town = ".MAIN_DB_PREFIX."c_ziptown.town";
	$sql.= " WHERE code_client IS NOT NULL AND client = 1 AND status = 1";

	dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num)
		{
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj)
				{
					$ville = new Ville();
					$ville->id = $obj->rowid;
					$ville->code_postal = $obj->zip;
					$ville->nom = $obj->town;
					$villes[$obj->town] = $ville;
				}
				$i++;
			}
		}
	}
	else
	{
		$error++;
		dol_print_error($db);
	}

	// FIXME Bidouille pour contourner le fait que la colonne "town" de la table "societe" n'est pas assez grande.
	$ville = new Ville();
	$ville->id = 28076;
	$ville->code_postal = "64130";
	$ville->nom = "Mitikile-Larrori-Mendibile / Moncayolle-Larrory-Mendibieu";
	$villes[$ville->nom] = $ville;

	print "--- Chargement de la liste des villes terminé.\n";

	return $villes;
}

// Codes des langues
define('EU', 'eu');
define('FR', 'fr');

/**
 * Prend en entrée un texte bilingue et extrait la valeur correspondant à la langue donnée.
 * 
 * @param $text texte bilingue
 * @param $lang code de la langue (EU ou FR)
 *
 * @return string texte dans la langue donnée
 */
function extraitValeur($text, $lang)
{
	$tab = explode("/", $text);
	if (count($tab) === 2) {
		if ($lang === EU) {
			return trim($tab[0]);
		} else if ($lang === FR) {
			return trim($tab[1]);
		}
	} else {
		return "";
	}
}

/**
 * Génère l'export XML pour la langue donnée.
 * 
 * @param $activites liste des activités
 * @param $etiquettes liste des étiquettes
 * @param $villes liste des villes
 * @param $prestataires liste des prestataires
 * @param $lang code de la langue (EU ou FR)
 */
function genereXml($activites, $etiquettes, $villes, $prestataires, $lang)
{
	print "--- Génération du XML pour la langue $lang.\n";

	$xml = new DOMDocument();

	$racine = $xml->createElement('racine');
	$xml->appendChild($racine);

	// écriture de la liste de toutes les activités
	$elt_activites = $xml->createElement('activites');
	foreach ($activites as $activite) {
		$elt_activite = $xml->createElement('activite');

		$elt_id = $xml->createElement('id');
		$elt_id->nodeValue = $activite->id;
		$elt_activite->appendChild($elt_id);

		$elt_parent_id = $xml->createElement('parent_id');
		$elt_parent_id->nodeValue = $activite->fk_parent;
		$elt_activite->appendChild($elt_parent_id);

		$elt_nom = $xml->createElement('nom');
		$elt_nom->nodeValue = extraitValeur($activite->label, $lang);
		$elt_activite->appendChild($elt_nom);

		$elt_activites->appendChild($elt_activite);
	}
	$racine->appendChild($elt_activites);

	// écriture de la liste de toutes les étiquettes
	$elt_etiquettes = $xml->createElement('etiquettes');
	foreach ($etiquettes as $etiquette) {
		$elt_etiquette = $xml->createElement('etiquette');

		$elt_id = $xml->createElement('id');
		$elt_id->nodeValue = $etiquette->id;
		$elt_etiquette->appendChild($elt_id);

		$elt_nom = $xml->createElement('nom');
		$elt_nom->nodeValue = $etiquette->label;
		$elt_etiquette->appendChild($elt_nom);

		$elt_image = $xml->createElement('image');
		$elt_image->nodeValue = $etiquette->description;
		$elt_etiquette->appendChild($elt_image);

		$elt_etiquettes->appendChild($elt_etiquette);
	}
	$racine->appendChild($elt_etiquettes);

	// écriture de la liste de toutes les villes
	$elt_villes = $xml->createElement('villes');
	foreach ($villes as $ville) {
		$elt_ville = $xml->createElement('ville');

		$elt_id = $xml->createElement('id');
		$elt_id->nodeValue = $ville->id;
		$elt_ville->appendChild($elt_id);

		$elt_code_postal = $xml->createElement('code_postal');
		$elt_code_postal->nodeValue = $ville->code_postal;
		$elt_ville->appendChild($elt_code_postal);

		$elt_nom = $xml->createElement('nom');
		$elt_nom->nodeValue = extraitValeur($ville->nom, $lang);
		$elt_ville->appendChild($elt_nom);

		$elt_villes->appendChild($elt_ville);
	}
	$racine->appendChild($elt_villes);

	// écriture de la liste de tous les prestataires
	$elt_prestataires = $xml->createElement('prestataires');
	foreach ($prestataires as $prestataire) {
		$elt_prestataire = $xml->createElement('prestataire');

		$elt_id = $xml->createElement('id');
		$elt_id->nodeValue = $prestataire->id;
		$elt_prestataire->appendChild($elt_id);

		$elt_nom = $xml->createElement('nom');
		$elt_nom->nodeValue = htmlspecialchars($prestataire->name);
		$elt_prestataire->appendChild($elt_nom);

		$description = '';
		if ($lang === EU) {
			$description = $prestataire->description_eu;
		} else if ($lang === FR) {
			$description = $prestataire->description_fr;
		}
		$elt_description = $xml->createElement('description');
		$elt_description->nodeValue = htmlspecialchars($description);
		$elt_prestataire->appendChild($elt_description);

		$horaires = '';
		if ($lang === EU) {
			$horaires = $prestataire->horaires_eu;
		} else if ($lang === FR) {
			$horaires = $prestataire->horaires_fr;
		}
		$elt_horaires = $xml->createElement('horaires');
		$elt_horaires->nodeValue = htmlspecialchars($horaires);
		$elt_prestataire->appendChild($elt_horaires);

		$autres_lieux_activite = '';
		if ($lang === EU) {
			$autres_lieux_activite = $prestataire->autres_lieux_activite_eu;
		} else if ($lang === FR) {
			$autres_lieux_activite = $prestataire->autres_lieux_activite_fr;
		}
		$elt_autres_lieux_activite = $xml->createElement('autres_lieux_activite');
		$elt_autres_lieux_activite->nodeValue = htmlspecialchars($autres_lieux_activite);
		$elt_prestataire->appendChild($elt_autres_lieux_activite);

		$elt_adresse = $xml->createElement('adresse');
		$elt_adresse->nodeValue = $prestataire->address;
		$elt_prestataire->appendChild($elt_adresse);

		$elt_longitude = $xml->createElement('longitude');
		$elt_longitude->nodeValue = $prestataire->getLongitude();
		$elt_prestataire->appendChild($elt_longitude);

		$elt_latitude = $xml->createElement('latitude');
		$elt_latitude->nodeValue = $prestataire->getLatitude();
		$elt_prestataire->appendChild($elt_latitude);

		$elt_telephone = $xml->createElement('telephone');
		$elt_telephone->nodeValue = $prestataire->phone;
		$elt_prestataire->appendChild($elt_telephone);

		$elt_telephone2 = $xml->createElement('telephone2');
		$elt_telephone2->nodeValue = $prestataire->telephone2;
		$elt_prestataire->appendChild($elt_telephone2);

		$elt_email = $xml->createElement('email');
		$elt_email->nodeValue = $prestataire->email;
		$elt_prestataire->appendChild($elt_email);

		$elt_site_web = $xml->createElement('site_web');
		$elt_site_web->nodeValue = $prestataire->url;
		$elt_prestataire->appendChild($elt_site_web);

		$elt_url_photo = $xml->createElement('url_photo');
		$elt_url_photo->nodeValue = $prestataire->getUrlPhoto();
		$elt_prestataire->appendChild($elt_url_photo);

		$elt_id_ville = $xml->createElement('id_ville');
		// on récupère la ville dans le tableau de toutes les villes
		// pour avoir son id
		$elt_id_ville->nodeValue = $villes[$prestataire->town]->id;
		$elt_prestataire->appendChild($elt_id_ville);

		// liste des ID d'activités séparés par des virgules
		$elt_activites = $xml->createElement('activites');
		foreach ($prestataire->activites as $id) {
			if ($elt_activites->nodeValue !== "")
				$elt_activites->nodeValue .= ', ';
			$elt_activites->nodeValue .= $id;
		}
		$elt_prestataire->appendChild($elt_activites);

		// liste des ID d'étiquettes séparés par des virgules
		$elt_etiquettes = $xml->createElement('etiquettes');
		foreach ($prestataire->etiquettes as $id) {
			if ($elt_etiquettes->nodeValue !== "")
				$elt_etiquettes->nodeValue .= ', ';
			$elt_etiquettes->nodeValue .= $id;
		}
		$elt_prestataire->appendChild($elt_etiquettes);

		$elt_prestataires->appendChild($elt_prestataire);
	}
	$racine->appendChild($elt_prestataires);

	$xml->formatOutput = true;
	$xml->save('annuaire_'.$lang.'.xml');

	print "--- Génération du XML pour la langue $lang terminée.\n";
}


print "***** ".$script_file." (".$version.") *****\n";
print '--- start'."\n";


// Start of transaction
$db->begin();


$activites = getActivites($db);
$etiquettes = getEtiquettes($db);
$villes = getVilles($db);
$prestataires = getPrestataires($db);

genereXml($activites, $etiquettes, $villes, $prestataires, EU);
genereXml($activites, $etiquettes, $villes, $prestataires, FR);

// -------------------- END OF YOUR CODE --------------------

if (! $error)
{
	$db->commit();
	print '--- end ok'."\n";
}
else
{
	print '--- end error code='.$error."\n";
	$db->rollback();
}

$db->close();	// Close database opened handler

return $error;
?>

