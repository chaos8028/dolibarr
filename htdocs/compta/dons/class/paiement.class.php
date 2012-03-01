<?php
/* Copyright (C) 2007-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *      \file       dev/skeletons/paiement.class.php
 *      \ingroup    mymodule othermodule1 othermodule2
 *      \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *		\version    $Id: paiement.class.php,v 1.32 2011/07/31 22:21:58 eldy Exp $
 *		\author		Put author name here
 *		\remarks	Initialy built by build_class_from_table on 2011-11-05 18:39
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *      \class      Paiement
 *      \brief      Put here description of your class
 *		\remarks	Initialy built by build_class_from_table on 2011-11-05 18:39
 */
class Paiement  extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='paiement';			//!< Id that identify managed objects
	//var $table_element='paiement';	//!< Name of table without prefix where object is stored

    var $id;
       var $table_element='paiement';
	var $datec='';
	var $tms='';
	var $datep='';
	var $amount;
	var $fk_paiement;
	var $num_paiement;
	var $note;
	var $fk_bank;
	var $fk_user_creat;
	var $fk_user_modif;
	var $statut;
	var $fk_export_compta;
        
   	function update_fk_bank($id_bank)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' set fk_bank = '.$id_bank;
		$sql.= ' WHERE rowid = '.$this->id;

		dol_syslog(get_class($this).'::update_fk_bank sql='.$sql);
		$result = $this->db->query($sql);
		if ($result)
		{
			return 1;
		}
		else
		{
            $this->error=$this->db->lasterror();
            dol_syslog(get_class($this).'::update_fk_bank '.$this->error);
			return -1;
		}
	}


    
    function addPaymentToBank($user,$mode,$label,$accountid,$emetteur_nom,$emetteur_banque,$notrigger=0)
    {
        global $conf,$langs,$user;

        $error=0;
        $bank_line_id=0;
        $this->fk_account=$accountid;

        if ($conf->banque->enabled)
        {
            require_once(DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php');

            dol_syslog("$user->id,$mode,$label,$this->fk_account,$emetteur_nom,$emetteur_banque");

            $acc = new Account($this->db);
            $acc->fetch($this->fk_account);

            $totalamount=$this->amount;
            if (empty($totalamount)) $totalamount=$this->total; // For backward compatibility
            if ($mode == 'payment_donation') $totalamount=$totalamount;
     

            // Insert payment into llx_bank
            $bank_line_id = $acc->addline($this->datep,
            $this->fk_paiement,  // Payment mode id or code ("CHQ or VIR for example")
            $label,
            $totalamount,
            $this->num_paiement,
            '',
            $user,
            $emetteur_nom,
            $emetteur_banque);

            // Mise a jour fk_bank dans llx_paiement
            // On connait ainsi le paiement qui a genere l'ecriture bancaire
            if ($bank_line_id > 0)
            {
                $result=$this->update_fk_bank($bank_line_id);
                if ($result <= 0)
                {
                    $error++;
                    dol_print_error($this->db);
                }

                // Add link 'payment', 'payment_supplier' in bank_url between payment and bank transaction
                if ( ! $error)
                {
                    $url='';
                    if ($mode == 'payment_donation') $url=DOL_URL_ROOT.'/compta/paiement_don/fiche.php?id=';
                   
                    
                    if ($url)
                    {
                        $result=$acc->add_url_line($bank_line_id, $this->id, $url, '(paiement)', $mode);
                        if ($result <= 0)
                        {
                            $error++;
                            dol_print_error($this->db);
                        }
                    }
                }
// TODO add link to donation
                // Add link 'company' in bank_url between invoice and bank transaction (for each invoice concerned by payment)
//                if (! $error)
//                {
//                   
//                
//                        if ($mode == 'payment')
//                        {
//                         
//                            if (! in_array($fac->thirdparty->id,$linkaddedforthirdparty)) // Not yet done for this thirdparty
//                            {
//                                $result=$acc->add_url_line($bank_line_id, $fac->thirdparty->id,
//                                DOL_URL_ROOT.'/comm/fiche.php?socid=', $fac->thirdparty->nom, 'company');
//                                if ($result <= 0) dol_print_error($this->db);
//                                $linkaddedforthirdparty[$fac->thirdparty->id]=$fac->thirdparty->id;  // Mark as done for this thirdparty
//                            }
//                        }
//                    
//                    
//                }

	            if (! $error && ! $notrigger)
				{
					// Appel des triggers
					include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
					$interface=new Interfaces($this->db);
					$result=$interface->run_triggers('PAYMENT_ADD_TO_BANK',$this,$user,$langs,$conf);
					if ($result < 0) { $error++; $this->errors=$interface->errors; }
					// Fin appel triggers
				}
            }
            else
            {
                $this->error=$acc->error;
                $error++;
            }
        }

        if (! $error)
        {
            return $bank_line_id;
        }
        else
        {
            return -1;
        }
    }



    /**
     *      Constructor
     *      @param      DB      Database handler
     */
    function Paiement($DB)
    {
        $this->db = $DB;
        return 1;
    }


    /**
     *      Create object into database
     *      @param      user        	User that create
     *      @param      notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int         	<0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;
                $this->fk_user_create=$user->id;
		// Clean parameters
        
		if (isset($this->amount)) $this->amount=trim($this->amount);
		if (isset($this->fk_paiement)) $this->fk_paiement=trim($this->fk_paiement);
		if (isset($this->num_paiement)) $this->num_paiement=trim($this->num_paiement);
		if (isset($this->note)) $this->note=trim($this->note);
		if (isset($this->fk_bank)) $this->fk_bank=trim($this->fk_bank);
		if (isset($this->fk_user_creat)) $this->fk_user_creat=trim($this->fk_user_creat);
		if (isset($this->fk_user_modif)) $this->fk_user_modif=trim($this->fk_user_modif);
		if (isset($this->statut)) $this->statut=trim($this->statut);
		if (isset($this->fk_export_compta)) $this->fk_export_compta=trim($this->fk_export_compta);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."paiement(";
		
		$sql.= "datec,";
		$sql.= "datep,";
		$sql.= "amount,";
		$sql.= "fk_paiement,";
		$sql.= "num_paiement,";
		$sql.= "note,";
		$sql.= "fk_bank,";
		$sql.= "fk_user_creat,";
		$sql.= "fk_user_modif,";
		$sql.= "statut,";
		$sql.= "fk_export_compta";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($this->datec) || dol_strlen($this->datec)==0?'NULL':$this->db->idate($this->datec)).",";
		$sql.= " ".(! isset($this->datep) || dol_strlen($this->datep)==0?'NULL':$this->db->idate($this->datep)).",";
		$sql.= " ".(! isset($this->amount)?'NULL':"'".$this->amount."'").",";
		$sql.= " ".(! isset($this->fk_paiement)?'NULL':"'".$this->fk_paiement."'").",";
		$sql.= " ".(! isset($this->num_paiement)?'NULL':"'".$this->db->escape($this->num_paiement)."'").",";
		$sql.= " ".(! isset($this->note)?'NULL':"'".$this->db->escape($this->note)."'").",";
		$sql.= " ".(! isset($this->fk_bank)?'NULL':"'".$this->fk_bank."'").",";
		$sql.= " ".(! isset($user->fk_user_creat)?'NULL':"'".$this->fk_user_creat."'").",";
		$sql.= " ".(! isset($this->fk_user_modif)?'NULL':"'".$this->fk_user_modif."'").",";
		$sql.= " ".(! isset($this->statut)?'NULL':"'".$this->statut."'").",";
		$sql.= " ".(! isset($this->fk_export_compta)?'NULL':"'".$this->fk_export_compta."'")."";

        
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."paiement");

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *    Load object in memory from database
     *    @param      id          id object
     *    @return     int         <0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		
		$sql.= " t.datec,";
		$sql.= " t.tms,";
		$sql.= " t.datep,";
		$sql.= " t.amount,";
		$sql.= " t.fk_paiement,";
		$sql.= " t.num_paiement,";
		$sql.= " t.note,";
		$sql.= " t.fk_bank,";
		$sql.= " t.fk_user_creat,";
		$sql.= " t.fk_user_modif,";
		$sql.= " t.statut,";
		$sql.= " t.fk_export_compta";

		
        $sql.= " FROM ".MAIN_DB_PREFIX."paiement as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->datec = $this->db->jdate($obj->datec);
				$this->tms = $this->db->jdate($obj->tms);
				$this->datep = $this->db->jdate($obj->datep);
				$this->amount = $obj->amount;
				$this->fk_paiement = $obj->fk_paiement;
				$this->num_paiement = $obj->num_paiement;
				$this->note = $obj->note;
				$this->fk_bank = $obj->fk_bank;
				$this->fk_user_creat = $obj->fk_user_creat;
				$this->fk_user_modif = $obj->fk_user_modif;
				$this->statut = $obj->statut;
				$this->fk_export_compta = $obj->fk_export_compta;

                
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *      Update object into database
     *      @param      user        	User that modify
     *      @param      notrigger	    0=launch triggers after, 1=disable triggers
     *      @return     int         	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->amount)) $this->amount=trim($this->amount);
		if (isset($this->fk_paiement)) $this->fk_paiement=trim($this->fk_paiement);
		if (isset($this->num_paiement)) $this->num_paiement=trim($this->num_paiement);
		if (isset($this->note)) $this->note=trim($this->note);
		if (isset($this->fk_bank)) $this->fk_bank=trim($this->fk_bank);
		if (isset($this->fk_user_creat)) $this->fk_user_creat=trim($this->fk_user_creat);
		if (isset($this->fk_user_modif)) $this->fk_user_modif=trim($this->fk_user_modif);
		if (isset($this->statut)) $this->statut=trim($this->statut);
		if (isset($this->fk_export_compta)) $this->fk_export_compta=trim($this->fk_export_compta);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."paiement SET";
        
		$sql.= " datec=".(dol_strlen($this->datec)!=0 ? "'".$this->db->idate($this->datec)."'" : 'null').",";
		$sql.= " tms=".(dol_strlen($this->tms)!=0 ? "'".$this->db->idate($this->tms)."'" : 'null').",";
		$sql.= " datep=".(dol_strlen($this->datep)!=0 ? "'".$this->db->idate($this->datep)."'" : 'null').",";
		$sql.= " amount=".(isset($this->amount)?$this->amount:"null").",";
		$sql.= " fk_paiement=".(isset($this->fk_paiement)?$this->fk_paiement:"null").",";
		$sql.= " num_paiement=".(isset($this->num_paiement)?"'".$this->db->escape($this->num_paiement)."'":"null").",";
		$sql.= " note=".(isset($this->note)?"'".$this->db->escape($this->note)."'":"null").",";
		$sql.= " fk_bank=".(isset($this->fk_bank)?$this->fk_bank:"null").",";
		$sql.= " fk_user_creat=".(isset($this->fk_user_creat)?$this->fk_user_creat:"null").",";
		$sql.= " fk_user_modif=".(isset($this->fk_user_modif)?$this->fk_user_modif:"null").",";
		$sql.= " statut=".(isset($this->statut)?$this->statut:"null").",";
		$sql.= " fk_export_compta=".(isset($this->fk_export_compta)?$this->fk_export_compta:"null")."";

        
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }


 	/**
	 *   Delete object in database
     *	 @param     user        	User that delete
     *   @param     notrigger	    0=launch triggers after, 1=disable triggers
	 *   @return	int				<0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paiement";
		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::delete sql=".$sql);
		$resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}



	/**
	 *		Load an object from its id and create a new one in database
	 *		@param      fromid     		Id of object to clone
	 * 	 	@return		int				New id of clone
	 */
	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Paiement($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{



		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *		Initialisz object with example values
	 *		Id must be 0 if object instance is a specimen.
	 */
	function initAsSpecimen()
	{
		$this->id=0;
		
		$this->datec='';
		$this->tms='';
		$this->datep='';
		$this->amount='';
		$this->fk_paiement='';
		$this->num_paiement='';
		$this->note='';
		$this->fk_bank='';
		$this->fk_user_creat='';
		$this->fk_user_modif='';
		$this->statut='';
		$this->fk_export_compta='';

		
	}

}
?>
