<?php
/* Copyright (C) 2001-2006 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2010 Regis Houssin         <regis@dolibarr.fr>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
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
 *	\file       htdocs/compta/dons/paiement.php
 *	\ingroup    compta
 *	\brief      Page to create a payment to donations
 *	\version    $Id: paiement.php,v 1.114 2011/08/08 01:01:46 eldy Exp $
 */

require('../../main.inc.php');
require_once(DOL_DOCUMENT_ROOT.'/compta/dons/class/paiement.class.php');
require_once(DOL_DOCUMENT_ROOT.'/compta/dons/class/don.class.php');
require_once(DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php');

$langs->load('companies');
$langs->load('bills');
$langs->load('banks');
$langs->load("donations");

$action		= GETPOST('action');
$confirm	= GETPOST('confirm');

$donid		= GETPOST('donid');
$socname	= GETPOST('socname');
$accountid	= GETPOST('accountid');
$paymentnum	= GETPOST('num_paiement');

$sortfield	= GETPOST('sortfield');
$sortorder	= GETPOST('sortorder');
$page		= GETPOST('page');

$amounts=GETPOST('amountpayment');
$amountsresttopay=GETPOST('amountopay');;
$addwarning=0;

// Security check
$socid=0;
if ($user->societe_id > 0)
{
    $socid = $user->societe_id;
}



/*
 * Action add_paiement et confirm_paiement
*/
if ($action == 'add_paiement' || ($action == 'confirm_paiement' && $confirm=='yes'))
{
    $error = 0;

    $datepaye = dol_mktime(12, 0 , 0,
    $_POST['remonth'],
    $_POST['reday'],
    $_POST['reyear']);
    $paiement_id = 0;

    // Verifie si des paiements sont superieurs au montant facture
    // TODO calculate the resting amount in server side , on the contrary if there are concurrent user inserting  payments to the same donation could lead to inconsistency
    if ($amounts > $amountsresttopay)
            {
                $addwarning=1;
                $fiche_erreur_message = '<div class="error">'.$amountsresttopay .'-'.$amounts.img_warning($langs->trans("DonationPaymentHigherThanReminderToPay")).' '.$langs->trans("DonationHelpPaymentHigherThanReminderToPay").'</div>';
                $error++;
                
            }

    // Check parameters
    if (! GETPOST('paiementcode'))
    {
        $fiche_erreur_message = '<div class="error">'.$langs->trans('ErrorFieldRequired',$langs->transnoentities('PaymentMode')).'</div>';
        $error++;
    }

    if ($conf->banque->enabled)
    {
        // Si module bank actif, un compte est obligatoire lors de la saisie
        // d'un paiement
        if (! $_POST['accountid'])
        {
            $fiche_erreur_message = '<div class="error">'.$langs->trans('ErrorFieldRequired',$langs->transnoentities('AccountToCredit')).'</div>';
            $error++;
        }
    }

    if ($amounts == 0)
    {
        $fiche_erreur_message = '<div class="error">'.$langs->transnoentities('ErrorFieldRequired',$langs->trans('PaymentAmount')).'</div>';
        $error++;
    }

    if (empty($datepaye))
    {
        $fiche_erreur_message = '<div class="error">'.$langs->trans('ErrorFieldRequired',$langs->transnoentities('Date')).'</div>';
        $error++;
    }

    
    if(!$error) 
    { $action='confirm_paiement';
        $confirm='yes';}
        else
        { $action='create' ;}
}


/*
 * Action confirm_paiement
 */
if ($action == 'confirm_paiement' && $confirm == 'yes')
{
    $error=0;

    $datepaye = dol_mktime(12, 0, 0, $_POST['remonth'], $_POST['reday'], $_POST['reyear']);

    $db->begin();

    // Creation of payment line
    $paiement = new Paiement($db);
    $paiement->datec = date('Y-m-d');
    $paiement->datep     = $datepaye;
    $paiement->amount     = $amounts;   // Array with all payments dispatching
    $paiement->fk_paiement   = dol_getIdFromCode($db,$_POST['paiementcode'],'c_paiement');
    $paiement->num_paiement = $_POST['num_paiement'];//
    $paiement->note         = $_POST['comment'];
    $paiement->fk_bank = 0;
    $paiement->statut = 0; //deletable
    $paiement->fk_export_compta=0;
    
    if (! $error)
    {
        $paiement_id = $paiement->create($user);
        if ($paiement_id < 0)
        {
            $errmsg=$paiement->error;
            $error++;
        }
    }

    if (! $error)
    {
        $result=$paiement->addPaymentToBank($user,'payment_donation','('.$langs->trans("PaymentDonation").')',$_POST['accountid'],$_POST['chqemetteur'],$_POST['chqbank']);
        if ($result < 0)
        {   
            $errmsg=$paiement->error;
            $error++;
        }
        else
        {
            $paiement->update_fk_bank($result);
        }
    }

      if (! $error)
          {
           $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'paiement_don';
     $sql.= " set  fk_paiement=".$paiement_id.",fk_don=".$donid.",amount='".$amounts."'";
     $result = $db->query($sql);
     if (!$result) {
         $error++ ;
         $errmsg="Error while inserting in intermediate table".$sql.$db->error; }
      }
    

// insert in many to many table
    
         
         
     if (! $error)
    {
        $db->commit();
        // If payment dispatching on more than one invoice, we keep on summary page, otherwise go on invoice card
        $loc = DOL_URL_ROOT.'/compta/dons/fiche.php?rowid='.$donid;
        Header('Location: '.$loc);
        exit;
    }
    else
    {
        $db->rollback();
    die("Errornum".$error.'---'.$errmsg);
        
    }
}


/*
 * View
 */

llxHeader();

$html=new Form($db);

        // Message d'erreur
 if ($fiche_erreur_message)
        {
            print $fiche_erreur_message;
        }

if ($action == 'create')
{
    $don = new don($db);
    $result=$don->fetch($donid);

    if ($result >= 0)
    {

        $title='';
        if ($don->type != 2) $title.=$langs->trans("EnterPaymentReceivedDonation");
        if ($don->type == 2) $title.=$langs->trans("EnterPaymentDue");
        print_fiche_titre($title);

        dol_htmloutput_errors($errmsg);

        print '<form id="payment_form" name="add_paiement" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="add_paiement">';
        print '<input type="hidden" name="donid" value="'.$don->id.'">';
        print '<input type="hidden" name="socid" value="'.$don->socid.'">';
        print '<input type="hidden" name="type" value="'.$don->type.'">';
        print '<input type="hidden" name="thirdpartylabel" id="thirdpartylabel" value="'.dol_escape_htmltag($don->nom . '' .$don->prenom. ' / '.$don->societe).'">';

        print '<table class="border" width="100%">';

         // Reference du don
        print '<tr><td><span class="fieldrequired">'.$langs->trans('Donation').'</span></td><td colspan="2">'.$don->getNomUrl(1)."</td></tr>\n";

        // Third party
        print '<tr><td><span class="fieldrequired">'.$langs->trans('Name').' / '. $langs->trans('Company').'</span></td><td colspan="2">'.dol_escape_htmltag($don->nom . '' .$don->prenom. ' / '.$don->societe)."</td></tr>\n";
        print '<tr><td>'.$langs->trans('DonationAmount').'</td>';
            print '<td>'.price($don->amount).'</td></tr>';
        // Date payment
        print '<tr><td><span class="fieldrequired">'.$langs->trans('Date').'</span></td><td>';
        $datepayment = dol_mktime(12, 0 , 0, $_POST['remonth'], $_POST['reday'], $_POST['reyear']);
        $datepayment= ($datepayment == '' ? (empty($conf->global->MAIN_AUTOFILL_DATE)?-1:0) : $datepayment);
        $html->select_date($datepayment,'','','',0,"add_paiement",1,1);
        print '</td>';
        print '<td>'.$langs->trans('Comments').'</td></tr>';

        $rowspan=5;
        if ($conf->use_javascript_ajax && !empty($conf->global->MAIN_JS_ON_PAYMENT)) $rowspan++;

        // Payment mode
        print '<tr><td><span class="fieldrequired">'.$langs->trans('PaymentMode').'</span></td><td>';
        $html->select_types_paiements((GETPOST('paiementcode')?GETPOST('paiementcode'):$don->mode_reglement_code),'paiementcode','',2);
        print "</td>\n";
        print '<td rowspan="'.$rowspan.'" valign="top">';
        print '<textarea name="comment" wrap="soft" cols="60" rows="'.ROWS_4.'">'.(empty($_POST['comment'])?'':$_POST['comment']).'</textarea></td>';
        print '</tr>';

        // Payment amount
         
        print '<tr><td>'.$langs->trans('DonationAmountPayed').'</td>';
        print '<td>'.price($don->sum_paymentdonation($don->id)).'</td></tr>';
            // here we obtain the next payment number and the remaining amount to pay
         print '<tr><td>'.$langs->trans('RemainderToPay').'</td>';
           print '<td>'.($don->amount-$don->amountpayed).'<input name="amountopay" type="hidden" value="'.($don->amount-$don->amountpayed).'"></td></tr>';
      
            print '<tr><td><span class="fieldrequired">'.$langs->trans('AmountPayment').'</span></td>';
            print '<td>';
                print '<input id="amountpayment" name="amountpayment" size="8" type="text" value="'.(empty($_POST['amountpayment'])?($don->amount-$don->amounttopay):$_POST['amountpayment']).'">';
            print '</td>';
            print '</tr>';


        print '<tr>';
        if ($conf->banque->enabled)
        {
            print '<td><span class="fieldrequired">'.$langs->trans('AccountToDebit').'</span></td>';
            print '<td>';
            $html->select_comptes($accountid,'accountid',0,'',2);
            print '</td>';
        }
        else
        {
            print '<td colspan="2">&nbsp;</td>';
        }
        print "</tr>\n";

        // Cheque number
        print '<tr><td>'.$langs->trans('Numero');
        print ' <em>('.$langs->trans("ChequeOrTransferNumber").')</em>';
        print '</td>';
        print '<td><input name="num_paiement" type="text" value="'.$don->nextpaymentnumber.'"></td></tr>';

        // Check transmitter
        print '<tr><td class="'.(GETPOST('paiementcode')=='CHQ'?'fieldrequired ':'').'fieldrequireddyn">'.$langs->trans('CheckTransmitter');
        print ' <em>('.$langs->trans("ChequeMaker").')</em>';
        print '</td>';
        print '<td><input id="fieldchqemetteur" name="chqemetteur" size="30" type="text" value="'.GETPOST('chqemetteur').'"></td></tr>';

        // Bank name
        print '<tr><td>'.$langs->trans('Bank');
        print ' <em>('.$langs->trans("ChequeBank").')</em>';
        print '</td>';
        print '<td><input name="chqbank" size="30" type="text" value="'.GETPOST('chqbank').'"></td></tr>';

        print '</table>';

        // Bouton Enregistrer
        if ($action != 'add_paiement')
        {
// TODO: add confirmation dialog
            print '<center><br><input type="submit" class="button" value="'.$langs->trans('Save').'"><br><br></center>';

        }




        print "</form>\n";
    }
}


/**
 *  Show list of payments
 */
if (! GETPOST('action'))
{
    if ($page == -1) $page = 0 ;
    $limit = $conf->liste_limit;
    $offset = $limit * $page ;

    if (! $sortorder) $sortorder='DESC';
    if (! $sortfield) $sortfield='p.datep';

    $sql='SELECT dp.`fk_paiement`  idpaiement, dp.`fk_don` iddon , 
        dp.`amount` amounpt, p.`datep` ,  
        p.`num_paiement` , d.`ref` , d.`entity` , 
        d.`fk_statut` , d.`prenom` , d.`nom` , 
        d.`societe` ,  d.`fk_paiement` 
        FROM llx_paiement_don as dp
        LEFT JOIN llx_paiement  as p  ON dp.`fk_paiement` = p.`rowid`
        LEFT JOIN llx_don d ON dp.`fk_don` = d.`rowid` WHERE 1=1';
    
    if(!empty($donid)){
        $sql .= ' AND d.`rowid` = '.$donid;
    }
        
  
    $sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
    $sql .= $db->plimit( $limit +1 ,$offset);
    $resql = $db->query($sql);

    if ($resql)
    {
        $num = $db->num_rows($resql);
        $i = 0;
        $var=True;

        print_barre_liste($langs->trans('Payments'), $page, 'paiement.php','',$sortfield,$sortorder,'',$num);
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print_liste_field_titre($langs->trans('Don'),'paiement.php','facnumber','','','',$sortfield,$sortorder);
        print_liste_field_titre($langs->trans('Date'),'paiement.php','dp','','','',$sortfield,$sortorder);
        print_liste_field_titre($langs->trans('Type'),'paiement.php','libelle','','','',$sortfield,$sortorder);
        print_liste_field_titre($langs->trans('Amount'),'paiement.php','fa_amount','','','align="right"',$sortfield,$sortorder);
        print '<td>&nbsp;</td>';
        print "</tr>\n";

        while ($i < min($num,$limit))
        {
            $objp = $db->fetch_object($resql);
            $var=!$var;
            print '<tr '.$bc[$var].'>';
            print '<td><a href="facture.php?facid='.$objp->facid.'">'.$objp->facnumber."</a></td>\n";
            print '<td>'.dol_print_date($db->jdate($objp->dp))."</td>\n";
            print '<td>'.$objp->paiement_type.' '.$objp->num_paiement."</td>\n";
            print '<td align="right">'.price($objp->amount).'</td><td>&nbsp;</td>';
            print '</tr>';
            $i++;
        }
        print '</table>';
    }
}

$db->close();

llxFooter('$Date: 2011/08/08 01:01:46 $ - $Revision: 1.114 $');
?>
