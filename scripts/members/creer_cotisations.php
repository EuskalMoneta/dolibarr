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

// Include Dolibarr environment
require_once($path."../../htdocs/master.inc.php");
// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/subscription.class.php");
require_once DOL_DOCUMENT_ROOT."/compta/bank/class/account.class.php";

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");			// To load language file for default language
@set_time_limit(0);			// No timeout for this script

// Load user and its permissions
$result=$user->fetch('','administrateur');
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


print "***** ".$script_file." (".$version.") *****\n";
print '--- start'."\n";

$shortopts  = "";
$longopts  = array(
	"adherent:",
	"annee:",
	"premier-mois:",
	"debut-d-annee",
);
$options = getopt($shortopts, $longopts);
$num_adherent = $options["adherent"];
$annee = $options["annee"];
$premier_mois = intval($options["premier-mois"]);
$debut_d_annee = array_key_exists("debut-d-annee", $options);
if ($num_adherent == "" || $annee == "") {
	print "Usage : $script_file --adherent=NUM_ADHERENT --annee=ANNEE [--premier-mois=MOIS] [--debut-d-annee]\n";
	return 1;
}

// Libellé des cotisations : toutes les cotisations sont pour l'année
// indiquée en paramètre.
$label = "Adhésion/cotisation $annee";

// Start of transaction
$db->begin();

try {

// Charger l'adhérent dans la base et récupérer les infos qui concernent
// la cotisation : cotisation offerte, prélèvement automatique, etc.
$adherent = new Adherent($db);
$adherent->fetch_login($num_adherent);
if ($adherent->id <= 0) {
	throw new Exception("Erreur : l'adhérent $num_adherent n'existe pas.\n");
}
$cotisation_offerte = boolval($adherent->array_options["options_cotisation_offerte"]);
$prelevement_auto_cotisation_euro = boolval($adherent->array_options["options_prelevement_auto_cotisation"]);
$prelevement_auto_cotisation_eusko = boolval($adherent->array_options["options_prelevement_auto_cotisation_eusko"]);
$montant = floatval($adherent->array_options["options_prelevement_cotisation_montant"]);
$periodicite = intval($adherent->array_options["options_prelevement_cotisation_periodicite"]);

// Si l'option "début d'année" a été passée en argument, on crée une
// cotisation d'un montant de zéro et valable jusqu'à la date du premier
// prélèvement automatique de la cotisation en eusko.
// Si la case "Cotisation offerte" est cochée, on crée une cotisation
// d'un montant de zéro.
// C'est donc la même chose, avec uniquement la date de fin de
// cotisation qui change.
if ($debut_d_annee || $cotisation_offerte) {
	if ($debut_d_annee) {
		print "Adhérent $num_adherent : cotisation de zéro pour le début de l'année.\n";
	} else if ($cotisation_offerte) {
		print "Adhérent $num_adherent : cotisation offerte.\n";
	}
	$montant = 0;

	if ($debut_d_annee) {
		if ($adherent->login[0] === 'E') {
			$jour_fin = "15/01";
		} else if ($adherent->login[0] === 'Z') {
			$jour_fin = "05/01";
		}
	} else if ($cotisation_offerte) {
		$jour_fin = "31/12";
	}

	$date_debut = dol_stringtotime("01/01/".$annee);
	$date_fin = dol_stringtotime($jour_fin."/".$annee);
	$accountid = 0;
	$operation = '';
	$num_chq = '';
	$emetteur_nom = '';
	$emetteur_banque = '';

	// Création de la cotisation
	$cotis_id = $adherent->subscription($date_debut, $montant, $accountid, $operation, $label, $num_chq, $emetteur_nom, $emetteur_banque, $date_fin);
	if ($cotis_id <= 0) {
		throw new Exception("Erreur lors de la création de la cotisation : $adherent->error.\n");
	}
} else {
	// Si la cotisation n'est pas offerte, c'est qu'elle doit être en
	// prélèvement automatique (ce script ne peut pas gérer d'autres cas
	// de figure).
	// On vérifie les options du prélèvement automatique.
	if (!$prelevement_auto_cotisation_euro && !$prelevement_auto_cotisation_eusko) {
		throw new Exception("Erreur : cet adhérent ($num_adherent) n'a pas choisi le prélèvement automatique pour le paiement de sa cotisation.");
	}
	if ($prelevement_auto_cotisation_euro && $prelevement_auto_cotisation_eusko) {
		throw new Exception("Erreur : pour cet adhérent ($num_adherent), les 2 prélèvements automatiques (€ et eusko) sont cochés pour le paiement de la cotisation.");
	}
	if ($montant <= 0 || !in_array($periodicite, array(1, 3, 6, 12))) {
		throw new Exception("Erreur : le montant ($montant) ou la périodicité ($periodicite) du prélèvement est incorrect.\n");
	}

	// Tous les paramètres ont été validés donc on crée les cotisations.
	// La liste des cotisations à créer dépend de la périodicité choisie
	// (1 = mensuel, 3 = trimestriel, 6 = semestriel, 12 = annuel).
	if ($periodicite === 1) {
		$cotisations_a_creer = array(
			array("debut" => "01/01/".$annee, "fin" => "31/01/".$annee),
			array("debut" => "01/02/".$annee, "fin" => "28/02/".$annee),
			array("debut" => "01/03/".$annee, "fin" => "31/03/".$annee),
			array("debut" => "01/04/".$annee, "fin" => "30/04/".$annee),
			array("debut" => "01/05/".$annee, "fin" => "31/05/".$annee),
			array("debut" => "01/06/".$annee, "fin" => "30/06/".$annee),
			array("debut" => "01/07/".$annee, "fin" => "31/07/".$annee),
			array("debut" => "01/08/".$annee, "fin" => "31/08/".$annee),
			array("debut" => "01/09/".$annee, "fin" => "30/09/".$annee),
			array("debut" => "01/10/".$annee, "fin" => "31/10/".$annee),
			array("debut" => "01/11/".$annee, "fin" => "30/11/".$annee),
			array("debut" => "01/12/".$annee, "fin" => "31/12/".$annee),
		);
	} else if ($periodicite === 3) {
		$cotisations_a_creer = array(
			array("debut" => "01/01/".$annee, "fin" => "31/03/".$annee),
			array("debut" => "01/04/".$annee, "fin" => "30/06/".$annee),
			array("debut" => "01/07/".$annee, "fin" => "30/09/".$annee),
			array("debut" => "01/10/".$annee, "fin" => "31/12/".$annee),
		);
	} else if ($periodicite === 6) {
		$cotisations_a_creer = array(
			array("debut" => "01/01/".$annee, "fin" => "30/06/".$annee),
			array("debut" => "01/07/".$annee, "fin" => "31/12/".$annee),
		);
	} else if ($periodicite === 12) {
		$cotisations_a_creer = array(
			array("debut" => "01/01/".$annee, "fin" => "31/12/".$annee),
		);
	}

	foreach ($cotisations_a_creer as $cotis) {
		// Si l'option "premier mois" a été passée en argument, on ne
		// génère pas les cotisations pour les mois précédents celui-là.
		$mois = intval(explode("/", $cotis["debut"])[1]);
		if ($premier_mois && $mois < $premier_mois) {
			continue;
		}
		$date_debut = dol_stringtotime($cotis["debut"]);
		$date_fin = dol_stringtotime($cotis["fin"]);
		if ($prelevement_auto_cotisation_euro) {
			$accountid = 2; // Compte bancaire: CREDIT COOPERATIF
		} else if ($prelevement_auto_cotisation_eusko) {
			$accountid = 4; // Compte bancaire: Compte numérique Eusko
		}
		$operation = 'PRE';
		$num_chq = '';
		$emetteur_nom = '';
		$emetteur_banque = '';

		// Création de la cotisation
		$cotis_id = $adherent->subscription($date_debut, $montant, $accountid, $operation, $label, $num_chq, $emetteur_nom, $emetteur_banque, $date_fin);
		if ($cotis_id <= 0) {
			print "Erreur lors de la création de la cotisation : $adherent->error.\n";
			$error = 1;
			break;
		}

		// Création de l'écriture bancaire (option 'bankdirect')
		// La date de paiement est le 5 du mois en début de période.
		$date_paiement = dol_stringtotime(preg_replace("/^01/", "05", $cotis["debut"]));
		$account = new Account($db);
		$result = $account->fetch($accountid);
		$insertid = $account->addline($date_paiement, $operation, $label, $montant, $num_chq, '', $user, $emetteur_nom, $emetteur_banque);

		if ($insertid <= 0) {
			print "Erreur lors de la création de l'écriture bancaire : $acount->error.\n";
			$error = 2;
			break;
		} else {
			// Ajout de l'URL vers la fiche adhérent dans l'écriture bancaire
			$inserturlid = $account->add_url_line($insertid, $adherent->id, DOL_URL_ROOT.'/adherents/fiche.php?rowid=', $adherent->getFullname($langs), 'member');

			if ($inserturlid <= 0) {
				print "Erreur lors de l'ajout de l'URL vers la fiche Adhérent dans l'écriture bancaire : $account->error.\n";
				$error = 3;
				break;
			} else {
				// Création du lien entre la cotisation et l'enregistrement bancaire
				$sql_majcotis = "UPDATE ".MAIN_DB_PREFIX."subscription SET fk_bank=".$insertid;
				$sql_majcotis .= " WHERE rowid=".$cotis_id;
				$resql_majcotis = $db->query($sql_majcotis);
				if (! $resql_majcotis) {
					dol_print_error($db);
					$error = 4;
					break;
				}
			}
		}
	}
}
} catch (Exception $e) {
	$error = 1;
	print $e->getMessage()."\n";
}

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

