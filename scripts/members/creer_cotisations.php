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
require_once(DOL_DOCUMENT_ROOT."/adherents/class/cotisation.class.php");
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
);
$options = getopt($shortopts, $longopts);
$num_adherent = $options["adherent"];
$annee = $options["annee"];
if ($num_adherent == "" || $annee == "") {
	print "Usage : $script_file --adherent=NUM_ADHERENT --annee=ANNEE\n";
	return 1;
}

// Start of transaction
$db->begin();

// Charger l'adhérent dans la base et vérifier les infos qui
// concernent la cotisation par prélèvement automatique.
$adherent = new Adherent($db);
$adherent->fetch_login($num_adherent);
if ($adherent->id <= 0) {
	print "Erreur : l'adhérent $num_adherent n'existe pas.\n";
	return 1;
}
$prelevement_auto_cotisation = boolval($adherent->array_options["options_prelevement_auto_cotisation"]);
$montant = floatval($adherent->array_options["options_prelevement_cotisation_montant"]);
$periodicite = intval($adherent->array_options["options_prelevement_cotisation_periodicite"]);
if (!$prelevement_auto_cotisation) {
	print "Erreur : cet adhérent ($num_adherent) n'a pas choisi le prélèvement automatique (en €) pour le paiement de sa cotisation.";
	return 1;
}
if ($montant <= 0 || !in_array($periodicite, array(1, 3, 6, 12))) {
	print "Erreur : le montant ($montant) ou la périodicité ($periodicite) du prélèvement est incorrect.\n";
	return 1;
}
$annee_fin_adhesion = getdate($adherent->datefin)["year"];
if (intval($annee) <= $annee_fin_adhesion) {
	print "Erreur : cet adhérent ($num_adherent)  est déjà à jour de cotisation jusqu'à fin $annee_fin_adhesion.\n";
	return 1;
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
	$date_debut = dol_stringtotime($cotis["debut"]);
	$date_fin = dol_stringtotime($cotis["fin"]);
	// La date de paiement est le 5 du mois en début de période.
	$date_paiement = dol_stringtotime(preg_replace("/^01/", "05", $cotis["debut"]));
	$label = "Adhésion/cotisation $annee";
	$accountid = 2; // Compte bancaire: CREDIT COOPERATIF
	$emetteur_nom = '';
	$num_chq = '';
	$emetteur_banque = '';
	$operation = 'PRE';

	// Création de la cotisation
	$cotis_id = $adherent->cotisation($date_debut, $montant, $accountid, $operation, $label, $num_chq, $emetteur_nom, $emetteur_banque, $date_fin);
	if ($cotis_id <= 0) {
		print "Erreur lors de la création de la cotisation : $member->error.\n";
		$error = 1;
		break;
	}

	// Création de l'écriture bancaire (option 'bankdirect')
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
			$sql_majcotis = "UPDATE ".MAIN_DB_PREFIX."cotisation SET fk_bank=".$insertid;
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

