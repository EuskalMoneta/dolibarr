<?php
define("NOLOGIN",1);		// This means this output page does not require to be logged.
define("NOCSRFCHECK",1);	// We accept to go on this page from external web site.

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';

$langs->load("main");
$langs->load("other");
$langs->load("dict");
$langs->load("errors");
$langs->load("paybox");
$langs->load("paypal");
$langs->load("members");

// Ce formulaire est utilisé pour payer une cotisation à Euskal Moneta, aussi
// bien lors de la première adhésion que pour renouveler sa cotisation :
// - première adhésion : dans ce cas il n'y a aucun paramètre particulier dans
//   l'URL; l'utilisateur doit saisir ses nom et prénom
// - renouvellement de cotisation avec numéro d'adhérent donné dans l'URL
//   (paramètre numadh) : dans ce cas les informations de l'adhérent seront
//   automatiquement chargées à partir de Dolibarr

// Montant minimum de la cotisation pour les particuliers.
define('MONTANT_MINIMUM', 5);

// Année jusqu'à la fin de laquelle la cotisation sera valable.
define('ANNEE_FIN_COTISATION', 2019);

// Numéro de l'adhérent.
$numadh=GETPOST("numadh",'alpha');

// Variable optionnelle qui indique par où l'utilisateur est arrivé sur cette
// page (site web, mail, Facebook, ...)
// Si cette information est fournie, elle est ajoutée au tag décrivant le
// paiement, que l'on retrouvera dans le relevé PayPal.
// Pour cela, la variable $src est transmise lors de la soumission du formulaire,
// via un champ caché.
$src=GETPOST("src",'alpha');

// Variables transmises lorsque le formulaire est validé
$memberid=GETPOST("memberid",'int');
$prenomadh=GETPOST("prenomadh",'alpha');
$nomadh=GETPOST("nomadh",'alpha');
$amount=GETPOST("amount",'int');

$currency=$conf->currency;


/*
 * Actions
 */

if (GETPOST("action") == 'validate')
{
	$mesg='';

	// Lorsque le formulaire est validé, les paramètres suivants doivent obligatoirement être renseignés :
	// - identifiant de l'adhérent OU nom et prénom
	// - montant de la cotisation
	if (empty($numadh)) {
		if (empty($prenomadh) or empty($nomadh)) {
			$mesg = "Kidetzen den pertsonaren deitura eta izena sar hitzatu otoi.";
			$mesg .= " / <em>Veuillez indiquer les nom et prénom de la personne qui adhère</em>";
		}
	} else if (empty($memberid)) {
		$mesg = "Kide zenbaki okerra.";
		$mesg .= " / <em>Identifiant d'adhérent invalide.</em>";
	}

	if ($mesg=='') {
		if (empty($amount)) {
			$mesg = "Urtesariaren zenbatekoa sar ezazu otoi.";
			$mesg .= " / <em>Veuillez indiquer le montant de votre cotisation.</em>";
		} else if ($amount < MONTANT_MINIMUM) {
			$mesg = "Urtesaria ".MONTANT_MINIMUM."&nbsp;€koa bederen izan behar da.";
			$mesg .= " / <em>La cotisation doit être de ".MONTANT_MINIMUM."&nbsp;€ minimum.</em>";
		}
	}

	// S'il n'y a pas d'erreur, on redirige vers la page de paiement PayPal.
	// On utilise le tag pour noter le numéro d'adhérent.
	if (empty($mesg)) {
		if (empty($numadh)) {
			$tag = "Nouvelle adhesion de $prenomadh $nomadh (cotisation jusqu'a fin ".ANNEE_FIN_COTISATION.").";

			// Le tag sera inclus dans le numéro de facture, et celui-ci ne
			// doit comporter que des caractères ASCII, il faut donc transcrire
			// le tag en ASCII. On utilise pour cela la méthode iconv() mais il
			// faut d'abord définir la locale correctement (source :
			// https://secure.php.net/manual/fr/function.iconv.php#74101).
			// Remarque : si le numéro de facture contient des caractères ASCII
			// PayPal renvoie l'erreur suivante :
			//   SetExpressCheckout API call failed.
			//   Detailed Error Message: Non-ASCII invoice id is not supported
			//   Short Error Message: Invalid Invoice
			//   Error Code: 10010
			//   Error Severity Code: Error
			setlocale(LC_CTYPE, 'fr_FR.utf8');
			$tag = iconv('UTF-8', 'ASCII//TRANSLIT', $tag);

			if (!empty($src)) {
				$tag .= "SRC=$src.";
			}
			header('Location: '.DOL_URL_ROOT.'/public/paypal/newpayment.php?amount='.$amount.'&tag='.$tag);
		} else {
			$tag = "Cotisation de $numadh jusqu'a fin ".ANNEE_FIN_COTISATION.".";
			if (!empty($src)) {
				$tag .= "SRC=$src.";
			}
			header('Location: '.DOL_URL_ROOT.'/public/paypal/newpayment.php?source=membersubscription&ref='.$memberid.'&amount='.$amount.'&tag='.$tag);
		}
		exit;
	}
}


/*
 * View
 */

$title = "Urtesariaren ordainketa - <em>Paiement de la cotisation</em>";

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
if (empty($numadh)) {
	// Cas d'une nouvelle adhésion
	$text .= "Euskal Monetari kidetze inprimakia bete berri duzu. Orai zure urtesaria ordaindu behar duzu, ".ANNEE_FIN_COTISATION."eko bukaera arte bali izanen dena.<br />";
	$text .= "Zure deitura eta izena sar hitzatu otoi, bai eta zure urtesariaren zenbatekoa:";
} else {
	// Cas d'un renouvellement de cotisation
	$text .= "Orai Euskal Monetari urtesaria berritzen ahal duzu. Hauta zazu zenbateko urtesaria ordaindu nahi duzun (".ANNEE_FIN_COTISATION."eko bukaera arte bali izanen da):";
}
$text .= "<ul>
<li>1 € hilabetean / 12 € urtean</li>
<li>2 € hilabetean / 24 € urtean</li>
<li>3 € hilabetean / 36 € urtean</li>
<li>5 € urtean (langabeak, gutieneko ahalak dituztenak)</li>
</ul>";
$text .= "Zure kide karta postaz igorria izanen zauzu.<br /><br />";

// Intro en français
$text .= "<em>";
if (empty($numadh)) {
	// Cas d'une nouvelle adhésion
	$text .= "Vous venez de remplir le formulaire d'adhésion à Euskal Moneta. Vous devez maintenant régler votre cotisation, celle-ci sera valable jusqu'à fin ".ANNEE_FIN_COTISATION.".<br />";
	$text .= "Veuillez indiquer vos nom et prénom ainsi que le montant de votre cotisation&nbsp;:";
} else {
	// Cas d'un renouvellement de cotisation
	$text .= "Vous allez maintenant pouvoir renouveler votre cotisation à Euskal Moneta. Veuillez indiquer le montant de votre cotisation (celle-ci sera valable jusqu'à fin ".ANNEE_FIN_COTISATION.")&nbsp;:";
}
$text .= "<ul>
<li>1 € par mois / 12 € par an</li>
<li>2 € par mois / 24 € par an</li>
<li>3 € par mois / 36 € par an</li>
<li>5 € par an (chômeurs, minima sociaux)</li>
</ul>";
$text .= "Votre carte d'adhérent(e) vous sera envoyée par courrier.</em><br /><br />";
$text .= "</td></tr>\n";

print $text;


// Output payment summary form
print '<tr><td align="center">'."\n";
print '<table with="100%" id="tablepublicpayment">'."\n";

$found=false;
$error=0;
$var=false;

if (empty($numadh)) {
	// Si le numéro d'adhérent n'a pas été donné en paramètre, il s'agit d'une nouvelle adhésion,
	// il faut que l'utilisateur indique le nom de la personne qui adhère.
	$var=!$var;
	print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'" style="width:50%">Deitura / <em>Nom</em>';
	print '</td><td class="CTableRow'.($var?'1':'2').'" style="width:50%">';
	print '<input class="flat" size="30" maxlength="50" type="text" id="nomadh" name="nomadh" value="'.$nomadh.'">';
	print '</td></tr>'."\n";

	$var=!$var;
	print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'">Izena / <em>Prénom</em>';
	print '</td><td class="CTableRow'.($var?'1':'2').'">';
	print '<input class="flat" size="30" maxlength="50" type="text" id="prenomadh" name="prenomadh" value="'.$prenomadh.'">';
	print '</td></tr>'."\n";
} else {
	// Si le numéro d'adhérent a été donné en paramètre, on charge ses infos à partir de la BD.
	$member=new Adherent($db);
	$member->fetch_login($numadh);

	// Si l'adhérent a été correctment chargé, son identifiant doit être positif.
	// Dans ce cas on affiche le numéro d'adhérent et son nom (valeurs non éditables).
	if ($member->id == 0) {
		$mesg = "Kide zenbaki okerra. / <em>Identifiant d'adhérent invalide.</em>";
	} else {
		// Numéro d'adhérent
		$var=!$var;
		print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'" style="width:50%">Kide zenbakia / <em>Numéro d\'adhérent</em>';
		print '</td><td class="CTableRow'.($var?'1':'2').' style="width:50%""><b>';
		print $member->login;
		print '</b>';
		// on met l'identifiant d'adhérent dans un champ caché de manière à transmettre cette valeur lors de la validation du formulaire
		print '<input type="hidden" name="memberid" value="'.$member->id.'">';
		print '</td></tr>'."\n";

		// Nom de l'adhérent
		$nomadh = $member->lastname;
		$var=!$var;
		print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'">Deitura / <em>Nom</em>';
		print '</td><td class="CTableRow'.($var?'1':'2').'"><b>'.$nomadh.'</b>';
//		// on met le nom de l'adhérent dans un champ caché de manière à transmettre cette valeur lors de la validation du formulaire
//		print '<input type="hidden" name="nomadh" value="'.$nomadh.'">';
		print '</td></tr>'."\n";

		// Prénom de l'adhérent
		$prenomadh = $member->firstname;
		$var=!$var;
		print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'">Izena / <em>Prénom</em>';
		print '</td><td class="CTableRow'.($var?'1':'2').'"><b>'.$prenomadh.'</b>';
//		// on met le prénom de l'adhérent dans un champ caché de manière à transmettre cette valeur lors de la validation du formulaire
//		print '<input type="hidden" name="prenomadh" value="'.$prenomadh.'">';
		print '</td></tr>'."\n";
	}
}

// Montant de la cotisation
$var=!$var;
print '<tr class="CTableRow'.($var?'1':'2').'"><td class="CTableRow'.($var?'1':'2').'">Urtesariaren zenbatekoa / <em>Montant de la cotisation</em>';
print '</td><td class="CTableRow'.($var?'1':'2').'">';
print '<input class="flat" size="3" maxlength="3" type="text" id="amount" name="amount" value="'.$amount.'">&nbsp;€';

// Currency
print '<input type="hidden" name="currency" value="'.$currency.'">';
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
