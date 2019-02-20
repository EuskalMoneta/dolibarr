<?php
define("NOLOGIN",1);		// This means this output page does not require to be logged.
define("NOCSRFCHECK",1);	// We accept to go on this page from external web site.

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';


// Ce formulaire permet à un adhérent qui souhaite renouveler sa cotisation de
// saisir son numéro d'adhérent. Une fois ce numéro validé, l'adhérent est
// redirigé vers la page de paiement de la cotisation.

define('NUMADH_LEN', 6);

// Numéro de l'adhérent.
$numadh=GETPOST("numadh",'alpha');

// Variable optionnelle qui indique par où l'utilisateur est arrivé sur cette
// page (site web, mail, Facebook, ...)
$src=GETPOST("src",'alpha');


/*
 * Actions
 */

if (GETPOST("action") == 'validate')
{
	$mesg='';

	$numadh = mb_strtoupper($numadh);
	if (empty($numadh)) {
		$mesg = "Zure kide zenbakia sar ezazu otoi.<br />";
		$mesg .= "<em>Veuillez indiquer votre numéro d'adhérent.</em>";
	} else {
		// Le numéro d'adhérent est normalement de la forme Exxxxx
		// ('E' suivi de 5 chiffres) mais la 2è série de cartes
		// qui a été imprimée n'avait que 4 chiffres donc on
		// vérifie ce que l'utilisateur a saisi et on ajoute un
		// 0 si nécessaire.
                // Le numéro d'adhérent peut aussi être de la forme Zxxxxx.
		if ($numadh[0] == 'E' && mb_strlen($numadh) == 5) {
			$numadh = $numadh[0].'0'.mb_substr($numadh, 1);
		}

		if (($numadh[0] != 'E' && $numadh[0] != 'Z') || mb_strlen($numadh) != NUMADH_LEN) {
			$mesg = "Kide zenbaki okerra: 'E' batekin hasi behar da erabiltzaileentzat edo 'Z' batekin zerbitzu emaileentzat, eta ondotik 5 zifra ukan.<br />";
			$mesg .= "<em>Numéro d'adhérent invalide&nbsp;: il doit commencer par 'E' pour les utilisateurs ou 'Z' pour les prestataires, puis comporter 5 chiffres.</em>";
		} else {
			// Si le numéro fourni a une forme correcte, on vérifie
			// s'il est valide.
			$member=new Adherent($db);
			$member->fetch_login($numadh);

			// Si l'adhérent a été correctment chargé, son identifiant doit être positif.
			// Dans ce cas on affiche le numéro d'adhérent et son nom (valeurs non éditables).
			if ($member->id == 0) {
				$mesg = "Kide zenbaki ez ezaguna. Sartu duzun zenbakia kontrolatu ezazu otoi.<br />";
				$mesg .= "<em>Numéro d'adhérent inconnu. Veuillez vérifier le numéro que vous avez saisi.</em>";
			}
		}
	}

	if (empty($mesg)) {
		$url = DOL_URL_ROOT.'/public/paypal/cotiser.php?numadh='.$numadh;
		if (!empty($src)) {
			$url .= "&src=$src";
		}
		header('Location: '.$url);
		exit;
	}
}


/*
 * View
 */

$title = "Urtesariaren berritzea - <em>Renouvellement de la cotisation</em>";

print '<span id="dolpaymentspan"></span>'."\n";
print '<center>'."\n";
print '<form id="dolpaymentform" name="paymentform" action="'.$_SERVER["PHP_SELF"].'" method="POST">'."\n";
print '<input type="hidden" name="numadh" value="'.$numadh.'">'."\n";
print '<input type="hidden" name="src" value="'.$src.'">'."\n";
print '<input type="hidden" name="action" value="validate">'."\n";
print '<table id="dolpaymenttable" summary="Payment form">'."\n";

// Show logo (search order: logo defined by PAYBOX_LOGO_suffix, then PAYBOX_LOGO, then small company logo, large company logo, theme logo, common logo)
$width=0;
// Define logo and logosmall
$logosmall=$mysoc->logo_small;
$logo=$mysoc->logo;
$paramlogo='PAYBOX_LOGO_'.$suffix;
if (! empty($conf->global->$paramlogo)) $logosmall=$conf->global->$paramlogo;
else if (! empty($conf->global->PAYBOX_LOGO)) $logosmall=$conf->global->PAYBOX_LOGO;
//print '<!-- Show logo (logosmall='.$logosmall.' logo='.$logo.') -->'."\n";
// Define urllogo
$urllogo='';
if (! empty($logosmall) && is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$logosmall))
{
	$urllogo=DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode('thumbs/'.$logosmall);
}
elseif (! empty($logo) && is_readable($conf->mycompany->dir_output.'/logos/'.$logo))
{
	$urllogo=DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode($logo);
	$width=96;
}
// Output html code for logo
if ($urllogo)
{
	print '<tr>';
	print '<td align="center"><img id="dolpaymentlogo" title="'.$title.'" src="'.$urllogo.'"';
	if ($width) print ' width="'.$width.'"';
	print '></td>';
	print '</tr>'."\n";
}

// Output introduction text
$text  = "<tr><td><br><center><b>$title</b></center><br>";

// Intro en euskara
$text .= 'Euskal Monetari zure urtesariaren berritzeko, zure kide zenbakia sar ezazu otoi (adibidez: <strong>E</strong>12345 erabiltzaile bat baldin bazira edo <strong>Z</strong>12345 zerbitzu emaile bat baldin bazira).<br />';
$text .= 'Arazo bat baldin bada, <a href="http://www.euskalmoneta.org/eu/harremanak/">gurekin harremanetan sar zaite</a> otoi seinalatzeko.<br /><br />';

// Intro en français
$text .= '<em>Pour renouveler votre cotisation à Euskal Moneta, veuillez indiquer votre numéro d\'adhérent (par exemple&nbsp;: <strong>E</strong>12345 si vous êtes un(e) utilisateur(trice) ou <strong>Z</strong>12345 si vous êtes un(e) prestataire).<br />';
$text .= '<em>Si vous rencontrez un problème, veuillez <a href="http://www.euskalmoneta.org/fr/contact/">nous contacter</a> pour nous le signaler.</em><br /><br />';
$text .= "</td></tr>\n";

print $text;


// Output payment summary form
print '<tr><td align="center">'."\n";
print '<table with="100%" id="tablepublicpayment">'."\n";

$error=0;
$var=false;

// Si le numéro d'adhérent n'a pas été donné en paramètre, il s'agit d'une nouvelle adhésion,
// il faut que l'utilisateur indique le nom de la personne qui adhère.
$var=!$var;
print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'" style="width:50%">Kide zenbakia / <em>Numéro d\'adhérent</em>';
print '</td><td class="CTableRow'.($var?'1':'2').'" style="width:50%">';
print '<input class="flat" size="'.NUMADH_LEN.'" maxlength="'.NUMADH_LEN.'" type="text" id="numadh" name="numadh" value="'.$numadh.'">';
print '</td></tr>'."\n";

if ($mesg) print '<tr><td align="center" colspan="2"><br><div class="warning">'.$mesg.'</div></td></tr>'."\n";

print '</table>'."\n";
print "\n";

print '<br><input class="button" type="submit" name="validate" value="Baieztatu / Valider">';

print '</td></tr>'."\n";

print '</table>'."\n";
print '</form>'."\n";
print '</center>'."\n";
print '<br>';

$db->close();
