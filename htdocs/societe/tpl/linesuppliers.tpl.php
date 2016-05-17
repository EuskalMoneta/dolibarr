<?php
        // Fournisseurs
        print '<tr><td>';
        print '<table width="100%" class="nobordernopadding"><tr><td>';
        print $langs->trans('Suppliers');
        print '<td><td align="right">';
        if ($user->rights->societe->creer)
        print '<a href="'.DOL_URL_ROOT.'/societe/fournisseurs.php?socid='.$object->id.'">'.img_edit().'</a>';
        else
        print '&nbsp;';
        print '</td></tr></table>';
        print '</td>';
        print '<td colspan="3">';

        $listefournisseurs=$object->getFournisseurs($user);
        $nbfournisseurs=count($listefournisseurs);
        if ($nbfournisseurs > 3)   // We print only number
        {
            print '<a href="'.DOL_URL_ROOT.'/societe/fournisseurs.php?socid='.$object->id.'">';
            print $nbfournisseurs;
            print '</a>';
        }
        else if ($nbfournisseurs > 0)
        {
            $societestatic=new Societe($db);
            $i=0;
            foreach($listefournisseurs as $val)
            {
                $societestatic->id=$val['id'];
                $societestatic->name=$val['name'];
                print $societestatic->getNomUrl(1);
                $i++;
                if ($i < $nbfournisseurs) print ', ';
            }
        }
        else print $langs->trans("NoSupplierAffected");
        print '</td></tr>';

