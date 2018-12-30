<?php
/*
 * Copyright (C) 2016 Xebax Christy <xebax@wanadoo.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

/**
 * API class for associations.
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class Associations
{
    /**
     * Constructor
     */
    function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * Get the list of associations.
     *
     * @return array List of associations
     *
     * @throws RestException
     */
    function index()
    {
        $list = array();

        if (! DolibarrApiAccess::$user->rights->societe->lire) {
            throw new RestException(401);
        }

        // fk_adherent_type = 1 <=> 'Association'
        // client = 1 <=> 'Client'
        // status = 1 <=> 'active'
        $sql = "SELECT asso.rowid AS id, asso.code_client, asso.nom,"
                ." COUNT(parrain.login) AS nb_parrains"
                ." FROM ".MAIN_DB_PREFIX."societe AS asso"
                ." LEFT JOIN ".MAIN_DB_PREFIX."adherent AS parrain ON asso.rowid = parrain.fk_asso"
                ." JOIN ".MAIN_DB_PREFIX."adherent AS adh ON asso.rowid = adh.fk_soc"
                ." WHERE adh.fk_adherent_type = 1"
                ." AND asso.client = 1"
                ." AND asso.status = 1"
                ." GROUP BY asso.rowid"
                ." ORDER BY asso.rowid";

        $result = $this->db->query($sql);

        if ($result) {
            $num = $this->db->num_rows($result);
            for ($i = 0; $i < $num; $i++) {
                $list[] = $this->db->fetch_object($result);
            }
        } else {
            throw new RestException(503, 'Error when retrieving list of associations : '.$this->db->lasterror());
        }

        return $list;
    }

    /**
     * Get association by ID.
     *
     * @param int    $id    ID of association
     * @return array Association object
     *
     * @throws RestException
     */
    function get($id)
    {
        $list = $this->index();

        $found = false;
        foreach ($list as $asso) {
            if ($asso->id == $id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RestException(404, 'association not found');
        }

        return $asso;
    }
}
