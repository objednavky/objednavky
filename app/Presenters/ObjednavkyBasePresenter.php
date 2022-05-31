<?php
//Základ všech ostatních prezenterů


declare(strict_types=1);

namespace App\Presenters;

use Exception;
use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

abstract class ObjednavkyBasePresenter extends BasePresenter
{
    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage($this->name,'info');
            $this->redirect('Prihlas:login');
        }
    }

    protected function beforeRender(): void {
        $this->zkontrolujRokAVerzi();
    }

    public final function getSetup($id = 1)
    {
        //return $this->database->table('setup')->where('id',$id)->fetch();
        $setup = new stdClass;
        $setup->rok = $this->getUser()->getIdentity()->rok; 
        $setup->verze = $this->getUser()->getIdentity()->verze; 
        return $setup;
    }

    public final function prihlasenyId()
    {
       
        return $this->getUser()->getIdentity()->id; 
    }

    protected final function sumColumn($array ,$columnArgument)
    {
        $sum = 0;
        foreach ($array as $item) {
            $sum += $item[$columnArgument];
        }
        return $sum;
    }

    protected final function nactiRoky() {
        return $this->database->table('rozpocet')->select('DISTINCT rok')->fetchPairs('rok','rok');
    }

    protected final function nactiVerze($rok) {
        return $this->database->table('rozpocet')->where('rok', $rok)->select('DISTINCT verze')->fetchPairs('verze','verze');
    }

    protected final function renderujIkonuStavu($item) {
        $stavPopis = $item['stav']; //fallback v pripade chyby
        try {
            $stavPopis = isset($item['stavPopis']) ? $item['stavPopis'] : $item->ref('stav')->popis;
        } catch (Exception $e) {
            $stavPopis =$e;
        }
        switch ($item['stav']) {
            case 0: 
               return "<i class='fa fa-user stav-ikona btn-warning' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 1: 
                return "<i class='fa fa-user-friends stav-ikona btn-warning' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 2: 
                return "<i class='fa fa-user stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 3: 
                return "<i class='fa fa-user stav-ikona btn-success' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 4: 
                return "<i class='fa fa-user-friends stav-ikona btn-success' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 5: 
                return "<i class='fa fa-user-friends stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 6: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-secondary' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 7: 
                return "<i class='fa fa-trash stav-ikona btn-light' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 8: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
            case 9: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-primary' data-toggle='tooltip' data-placement='right' title='".$stavPopis."'/>";
        }

    }

    protected final function zkontrolujRokAVerzi() {
        $rokdb = $this->database->table('setup')->get(1)->rok; //TK: TODO toto předělat na novou funkcionalitu
        $rok = $this->getSetup()->rok;
        if ($rok != $rokdb) {
            $this->flashMessage('POZOR! Jste přihlášeni v jiném hospodářském roce, než který je označen jako aktuálně platný (aktuálně platný rok '.($rokdb-1).'/'.$rokdb.', pracujete v roce '.($rok-1).'/'.$rok.')!','warning');
            $verzemax = $this->database->table('rozpocet')->where('rok',$rok)->max('verze');
            $verze = $this->getSetup()->verze;
            if ($verze != $verzemax) {
                $this->flashMessage('POZOR! Jste přihlášeni v jiné verzi rozpočtu, než která je v tomto neaktuálním hospodářském roce použita jako poslední (poslední verze '.$verzemax.', pracujete ve verzi '.$verze.')!','danger');
            }
            } else {
            $verzedb = $this->database->table('setup')->get(1)->verze; //TK: TODO toto předělat na novou funkcionalitu
            $verze = $this->getSetup()->verze;
            if ($verze != $verzedb) {
                $this->flashMessage('POZOR! Jste přihlášeni v jiné verzi rozpočtu, než která je v tomto roce označena jako aktuálně platná (aktuálně platná verze '.$verzedb.', pracujete ve verzi '.$verze.')!','danger');
            }
        }
    }

}



