<?php
/* Copyright (C) 2005 		Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2010 		Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010-2011 	Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/societe/fournisseurs.php
 *  \ingroup    societe
 *  \brief      Page of links to suppilers
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

$langs->load("companies");
$langs->load("fournisseur");
$langs->load("customers");
$langs->load("suppliers");
$langs->load("banks");

// Security check
$socid = isset($_GET["socid"])?$_GET["socid"]:'';
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe','','');


/*
 *	Actions
 */

if($_GET["socid"] && $_GET["fourid"])
{
	if ($user->rights->societe->creer)
	{
		$soc = new Societe($db);
		$soc->id = $_GET["socid"];
		$soc->fetch($_GET["socid"]);
		$soc->add_fournisseur($user, $_GET["fourid"]);

		header("Location: fournisseurs.php?socid=".$soc->id);
		exit;
	}
	else
	{
		header("Location: fournisseurs.php?socid=".$_GET["socid"]);
		exit;
	}
}

if($_GET["socid"] && $_GET["delfourid"])
{
	if ($user->rights->societe->creer)
	{
		$soc = new Societe($db);
		$soc->id = $_GET["socid"];
		$soc->fetch($_GET["socid"]);
		$soc->del_fournisseur($user, $_GET["delfourid"]);

		header("Location: fournisseurs.php?socid=".$soc->id);
		exit;
	}
	else
	{
		header("Location: fournisseurs.php?socid=".$_GET["socid"]);
		exit;
	}
}


/*
 *	View
 */

$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('',$langs->trans("ThirdParty"),$help_url);

$form = new Form($db);

if ($_GET["socid"])
{
	$soc = new Societe($db);
	$soc->id = $_GET["socid"];
	$result=$soc->fetch($_GET["socid"]);

	$head=societe_prepare_head2($soc);

	dol_fiche_head($head, 'supplier', $langs->trans("ThirdParty"),0,'company');

	/*
	 * Fiche societe en mode visu
	 */

	print '<table class="border" width="100%">';

    print '<tr><td width="20%">'.$langs->trans('ThirdPartyName').'</td>';
    print '<td colspan="3">';
    print $form->showrefnav($soc,'socid','',($user->societe_id?0:1),'rowid','nom');
    print '</td></tr>';

	print '<tr>';
    print '<td>'.$langs->trans('CustomerCode').'</td><td'.(empty($conf->global->SOCIETE_USEPREFIX)?' colspan="3"':'').'>';
    print $soc->code_client;
    if ($soc->check_codeclient() <> 0) print ' '.$langs->trans("WrongCustomerCode");
    print '</td>';
    if (! empty($conf->global->SOCIETE_USEPREFIX))  // Old not used prefix field
    {
       print '<td>'.$langs->trans('Prefix').'</td><td>'.$soc->prefix_comm.'</td>';
    }
    print '</td>';
    print '</tr>';

	print "<tr><td valign=\"top\">".$langs->trans('Address')."</td><td colspan=\"3\">".nl2br($soc->address)."</td></tr>";

	print '<tr><td>'.$langs->trans('Zip').'</td><td width="20%">'.$soc->cp."</td>";
	print '<td>'.$langs->trans('Town').'</td><td>'.$soc->town."</td></tr>";

	print '<tr><td>'.$langs->trans('Country').'</td><td colspan="3">'.$soc->pays.'</td>';

	print '<tr><td>'.$langs->trans('Phone').'</td><td>'.dol_print_phone($soc->tel,$soc->country_code,0,$soc->id,'AC_TEL').'</td>';
	print '<td>'.$langs->trans('Fax').'</td><td>'.dol_print_phone($soc->fax,$soc->country_code,0,$soc->id,'AC_FAX').'</td></tr>';

	print '<tr><td>'.$langs->trans('Web').'</td><td colspan="3">';
	if ($soc->url) { print '<a href="http://'.$soc->url.'">http://'.$soc->url.'</a>'; }
	print '</td></tr>';

	// Liste les fournisseurs
	print '<tr><td valign="top">'.$langs->trans("Suppliers").'</td>';
	print '<td colspan="3">';

	$sql = "SELECT s.rowid, s.nom";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql .= " , ".MAIN_DB_PREFIX."societe_fournisseurs as sf";
	$sql .= " WHERE sf.fk_soc =".$soc->id;
	$sql .= " AND sf.fk_fournisseur = s.rowid";
	$sql .= " ORDER BY s.nom ASC ";

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		while ($i < $num)
		{
			$socm = $db->fetch_object($resql);

			print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$socm->rowid.'">'.img_object($langs->trans("ShowCompany"),'company').' '.$socm->nom.'</a>'.($socm->code_client?" (".$socm->code_client.")":"");
			print ($socm->town?' - '.$socm->town:'');
			print '&nbsp;<a href="'.$_SERVER["PHP_SELF"].'?socid='.$_GET["socid"].'&amp;delfourid='.$socm->rowid.'">';
			print img_delete();
			print '</a><br>';

			$i++;
		}

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
	if($i == 0) { print $langs->trans("NoSupplierAffected"); }

	print "</td></tr>";

	print '</table>';
	print "</div>\n";


	if ($user->rights->societe->creer && $user->rights->societe->client->voir)
	{
		$page=$_GET["page"];

		if ($page == -1) { $page = 0 ; }

		$offset = $conf->liste_limit * $page ;
		$pageprev = $page - 1;
		$pagenext = $page + 1;

		/*
		 * Liste
		 *
		 */

		$title=$langs->trans("CompanyList");

		$sql = "SELECT s.rowid as socid, s.nom, s.town, s.prefix_comm, s.client, s.fournisseur,";
		$sql.= " te.code, te.libelle";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql.= ", ".MAIN_DB_PREFIX."c_typent as te";
		if (! $user->rights->societe->client->voir) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		$sql.= " WHERE s.fk_typent = te.id";
		$sql.= " AND s.entity IN (".getEntity('societe', 1).")";
		if (! $user->rights->societe->client->voir) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
		if (dol_strlen(trim($_GET["search_nom"]))) $sql.= " AND s.nom LIKE '%".$_GET["search_nom"]."%'";
		$sql.= $db->order("s.nom","ASC");
		$sql.= $db->plimit($conf->liste_limit+1, $offset);

		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			$i = 0;

			$params = "&amp;socid=".$_GET["socid"];

			print_barre_liste($title, $page, "fournisseurs.php",$params,$sortfield,$sortorder,'',$num,0,'');

			// Lignes des titres
			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("Company").'</td>';
			print '<td>'.$langs->trans("Town").'</td>';
			print '<td>'.$langs->trans("ThirdPartyType").'<td>';
			print '<td colspan="2" align="center">&nbsp;</td>';
			print "</tr>\n";

			// Lignes des champs de filtre
			print '<form action="fournisseurs.php" method="GET" >';
			print '<input type="hidden" name="socid" value="'.$_GET["socid"].'">';
			print '<tr class="liste_titre">';
			print '<td valign="right">';
			print '<input type="text" name="search_nom" value="'.$_GET["search_nom"].'">';
			print '</td><td colspan="5" align="right">';
			print '<input type="image" name="button_search" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
			print '</td>';
			print "</tr>\n";
			print '</form>';

			$var=True;

			while ($i < min($num,$conf->liste_limit))
			{
				$obj = $db->fetch_object($resql);
				$var=!$var;
				print "<tr $bc[$var]><td>";
				print $obj->nom."</td>\n";
				print "<td>".$obj->town."&nbsp;</td>\n";
				print "<td>".$langs->getLabelFromKey($db,$obj->code,'c_typent','code','libelle')."</td>\n";
				print '<td align="center">';
				if ($obj->client==1)
				{
					print $langs->trans("Customer")."\n";
				}
				elseif ($obj->client==2)
				{
					print $langs->trans("Prospect")."\n";
				}
				else
				{
					print "&nbsp;";
				}
				print "</td><td align=\"center\">";
				if ($obj->fournisseur)
				{
					print $langs->trans("Supplier");
				}
				else
				{
					print "&nbsp;";
				}

				print '</td>';
				// Lien Ajouter
				print '<td align="center"><a href="fournisseurs.php?socid='.$_GET["socid"].'&amp;fourid='.$obj->socid.'">'.$langs->trans("Add").'</a>';
				print '</td>';

				print '</tr>'."\n";
				$i++;
			}

			print "</table>";
			print '<br>';
			$db->free($resql);
		}
		else
		{
			dol_print_error($db);
		}
	}

}


$db->close();

llxFooter();

