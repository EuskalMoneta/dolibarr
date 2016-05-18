#!/usr/bin/php
<?php
$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit;
}

// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------

/**
  *    Compare deux prestataires. La comparaison se fait en fonction de leur
  *    commune (en français), puis de leur catégorie, puis de leur nom.
  *
  *    @param        Prestataire    $p1      premier prestataire
  *    @param        Prestataire    $p2      second prestataire
  *    @return   int                         < 0 if p1 est inférieur à p2; > 0 if p1 is est supérier à p2, et 0 s'ils sont égaux
  */
function cmp_par_commune_categorie_nom($p1, $p2)
{
	$collator = Collator::create('fr_FR');
	$res = $collator->compare($p1->commune_fr, $p2->commune_fr);
	if ($res == 0) {
		$res = $collator->compare($p1->categorie_annuaire, $p2->categorie_annuaire);
	}
	if ($res == 0) {
		$res = $collator->compare($p1->name, $p2->name);
	}
	return $res;
}

/**
  *    Compare deux prestataires. La comparaison se fait en fonction de leur
  *    catégorie, puis de leur commune, puis de leur nom.
  *
  *    @param        Prestataire    $p1      premier prestataire
  *    @param        Prestataire    $p2      second prestataire
  *    @return   int                         < 0 if p1 est inférieur à p2; > 0 if p1 is est supérier à p2, et 0 s'ils sont égaux
  */
function cmp_par_categorie_commune_nom($p1, $p2)
{
	$collator = Collator::create('fr_FR');
	$res = $collator->compare($p1->categorie_annuaire, $p2->categorie_annuaire);
	if ($res == 0) {
		$res = $collator->compare($p1->commune_fr, $p2->commune_fr);
	}
	if ($res == 0) {
		$res = $collator->compare($p1->name, $p2->name);
	}
	return $res;
}

/**
  *    Ecrit dans un fichier les informations à afficher dans l'annuaire pour le prestataire donné.
  *
  *    @param        resource       $fp      pointeur de fichier (obtenu avec fopen)
  *    @param        Prestataire    $p       prestataire
  *    @param        bool           $annuaire_pdf   TRUE si ecriture pour l'annuaire PDF, FALSE si écriture pour l'annuaire en ligne
  */
function ecrit_prestataire($fp, $p, $annuaire_pdf)
{
	if (!$annuaire_pdf) { fwrite($fp, "<p>\n"); }

	if (!$annuaire_pdf && $p->estPaysBasqueAuCoeur()) {
		fwrite($fp, '<img src="http://www.euskalmoneta.org/wp-content/uploads/2014/11/Logo-PBAC-108x150.jpg" style="float:right;" />'."\n");
	}

	fwrite($fp, $p->name."\n");

	// description en basque puis description en français (avec un retour à la ligne entre les 2 pour plus de clarté)
	if ($p->description_eu != "") {
		fwrite($fp, $p->description_eu."\n");
	}
	if ($p->description_fr != "") {
		if (!$annuaire_pdf) { fwrite($fp, "<em>"); }
		fwrite($fp, $p->description_fr);
		if (!$annuaire_pdf) { fwrite($fp, "</em>"); }
		fwrite($fp, "\n");
	}

	if ($p->adresse_dans_annuaire == 1 && $p->address != "") {
		fwrite($fp, $p->address."\n");
	}

	fwrite($fp, $p->zip.' '.$p->town."\n");

	if ($p->tel_dans_annuaire == 1) {
		if($p->phone != "") {
			fwrite($fp, $p->phone);
		}
		if($p->phone != "" && $p->telephone2 != "") {
			fwrite($fp, ", ");
		}
		if($p->telephone2 != "") {
			fwrite($fp, $p->telephone2);
		}
		fwrite($fp, "\n");
	}

	if ($p->url != "") {
		if (!$annuaire_pdf) { fwrite($fp, '<a href="http://'.$p->url.'" target="_blank">'); }
		fwrite($fp, "http://".$p->url);
		if (!$annuaire_pdf) { fwrite($fp, "</a>"); }
		fwrite($fp, "\n");
	}

	if ($p->email_dans_annuaire == 1 && $p->email != "") {
		fwrite($fp, $p->email."\n");
	}

	if (!$annuaire_pdf) { fwrite($fp, "</p>\n"); }

	fwrite($fp, "\n");
}

/**
  *    Ecrit le nom de la commune du prestataire dans le fichier donné.
  *    S'il s'agit de l'annuaire en ligne, le nom de la commune est écrit avec le niveau de titre donné en paramètre.
  *
  *    @param        resource       $fp      pointeur de fichier (obtenu avec fopen)
  *    @param        Prestataire    $p       prestataire
  *    @param        bool           $annuaire_pdf   TRUE si ecriture pour l'annuaire PDF, FALSE si écriture pour l'annuaire en ligne
  *    @param        int            $niveau  niveau du titre (1, 2, ...)
  */
function ecrit_commune($fp, $p, $annuaire_pdf, $niveau)
{
	if (!$annuaire_pdf) { fwrite($fp, "<h".$niveau.">"); }
	if (!$annuaire_pdf) { fwrite($fp, "<em>"); }
	fwrite($fp, mb_strtoupper($p->commune_fr, 'UTF-8'));
	if (!$annuaire_pdf) { fwrite($fp, "</em>"); }
	if ($p->commune_eu != NULL) {
		fwrite($fp, " / ");
		fwrite($fp, mb_strtoupper($p->commune_eu, 'UTF-8'));
	}
	if (!$annuaire_pdf) { fwrite($fp, "</h".$niveau.">"); }
	fwrite($fp, "\n\n");
}

/**
  *    Ecrit le nom de la catégorie du prestataire dans le fichier donné.
  *    S'il s'agit de l'annuaire en ligne, le nom de la catégorie est écrit avec le niveau de titre donné en paramètre.
  *
  *    @param        resource       $fp      pointeur de fichier (obtenu avec fopen)
  *    @param        Prestataire    $p       prestataire
  *    @param        bool           $annuaire_pdf   TRUE si ecriture pour l'annuaire PDF, FALSE si écriture pour l'annuaire en ligne
  *    @param        int            $niveau  niveau du titre (1, 2, ...)
  */
function ecrit_categorie($fp, $p, $annuaire_pdf, $niveau)
{
	if (!$annuaire_pdf) { fwrite($fp, "<h".$niveau.">"); }
	fwrite($fp, $p->categorie_annuaire_eu);
	fwrite($fp, " / ");
	if (!$annuaire_pdf) { fwrite($fp, "<em>"); }
	fwrite($fp, $p->categorie_annuaire_fr);
	if (!$annuaire_pdf) { fwrite($fp, "</em>"); }
	if (!$annuaire_pdf) { fwrite($fp, "</h".$niveau.">"); }
	fwrite($fp, "\n\n");
}

/**
  *    Renvoie la date courante formatée en français (jour/mois/année).
  *
  *    @return   string                         date courante
  */
function date_en_francais()
{
	return date("d/m/Y");
}

/**
  *    Renvoie la date courante formatée en basque (année/mois/jour).
  *
  *    @return   string                         date courante
  */
function date_en_basque()
{
	return date("Y/m/d");
}

/**
  *    Ecrit l'en-tête de l'annuaire (date de dernière mise à jour, table des matières) dans le fichier donné.
  *
  *    @param        resource       $fp      pointeur de fichier (obtenu avec fopen)
  */
function ecrit_entete($fp)
{
	fwrite($fp, "Azken eguneratze data: ".date_en_basque()."\n");
	fwrite($fp, "Date de dernière mise à jour : ".date_en_francais()."\n");
	fwrite($fp, "\n");
	fwrite($fp, '[toc heading_levels="1"]'."\n");
	fwrite($fp, "\n");
}

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


print "***** ".$script_file." (".$version.") *****\n";
print '--- start'."\n";

$options = getopt("p");
$annuaire_pdf = array_key_exists("p", $options);

// Start of transaction
$db->begin();

require_once 'prestataire.class.php';

// récupérer la liste des prestataires agréés en activité
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
					//if (preg_match('/^2014-09/', $prest->date_agrement))
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

// tri de la liste des prestataires par communes
usort($prestataires, "cmp_par_commune_categorie_nom");

// écriture du fichier "annuaire par communes"
if ($annuaire_pdf) {
	$annuaire_par_communes = "annuaire_pdf_par_communes.txt";
} else {
	$annuaire_par_communes = "annuaire_par_communes.txt";
}
$fp = fopen($annuaire_par_communes, "wb");
if (!$annuaire_pdf) {
	ecrit_entete($fp);
}

if ($annuaire_pdf) {
	// listes des communes pour les sommaires en basque et en français de l'annuaire PDF
	$liste_communes_eu = array();
	$liste_communes_fr = array();
}

$commune_precedente = "";
$categorie_precedente = "";
foreach ($prestataires as $p) {
	if (strcasecmp($p->commune_fr, $commune_precedente) != 0) {
		ecrit_commune($fp, $p, $annuaire_pdf, 1);
		// on change de commune donc on force l'affichage du nom de la catégorie
		$categorie_precedente = "";

		if ($annuaire_pdf) {
			// si la commune n'a pas de nom en basque,
			// on utilise le nom en français
			if ($p->commune_eu != NULL) {
				$liste_communes_eu[] = $p->commune_eu;
			} else {
				$liste_communes_eu[] = $p->commune_fr;
			}
			$liste_communes_fr[] = $p->commune_fr;
		}
	}

	if (strcmp($p->categorie_annuaire, $categorie_precedente) != 0) {
		ecrit_categorie($fp, $p, $annuaire_pdf, 2);
	}

	ecrit_prestataire($fp, $p, $annuaire_pdf);

	$commune_precedente = $p->commune_fr;
	$categorie_precedente = $p->categorie_annuaire;
}
fclose($fp);

if ($annuaire_pdf) {
	// tri des listes de communes
	usort($liste_communes_eu, "strcmp");
	usort($liste_communes_fr, "strcmp");

	// écriture des fichiers "listes des communes"
	$fp = fopen("liste_des_communes_eu.txt", "wb");
	foreach ($liste_communes_eu as $str) {
		fwrite($fp, $str."\n");
	}
	fclose($fp);
	$fp = fopen("liste_des_communes_fr.txt", "wb");
	foreach ($liste_communes_fr as $str) {
		fwrite($fp, $str."\n");
	}
	fclose($fp);
}

// tri de la liste des prestataires par catégories
usort($prestataires, "cmp_par_categorie_commune_nom");

// écriture du fichier "annuaire par catégories"
if ($annuaire_pdf) {
	$annuaire_par_categories = "annuaire_pdf_par_categories.txt";
} else {
	$annuaire_par_categories = "annuaire_par_categories.txt";
}
$fp = fopen($annuaire_par_categories, "wb");
if (!$annuaire_pdf) {
	ecrit_entete($fp);
}

$commune_precedente = "";
$categorie_precedente = "";
foreach ($prestataires as $p) {
	if (strcmp($p->categorie_annuaire, $categorie_precedente) != 0) {
		ecrit_categorie($fp, $p, $annuaire_pdf, 1);
		// on change de catégorie donc on force l'affichage du nom de la commune
		$commune_precedente = "";
	}

	if (strcasecmp($p->commune_fr, $commune_precedente) != 0) {
		ecrit_commune($fp, $p, $annuaire_pdf, 2);
	}

	ecrit_prestataire($fp, $p, $annuaire_pdf);

	$commune_precedente = $p->commune_fr;
	$categorie_precedente = $p->categorie_annuaire;
}
fclose($fp);

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

