<?php
//Základ všech ostatních prezenterů


declare(strict_types=1);

namespace App\Presenters;

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
        switch ($item['stav']) {
            case 0: 
               return "<i class='fa fa-user stav-ikona btn-warning' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 1: 
                return "<i class='fa fa-user-friends stav-ikona btn-warning' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 2: 
                return "<i class='fa fa-user stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 3: 
                return "<i class='fa fa-user stav-ikona btn-success' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 4: 
                return "<i class='fa fa-user-friends stav-ikona btn-success' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 5: 
                return "<i class='fa fa-user-friends stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 6: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-secondary' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 7: 
                return "<i class='fa fa-trash stav-ikona btn-light' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 8: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-danger' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
            case 9: 
                return "<i class='fa fa-file-invoice-dollar stav-ikona btn-primary' data-toggle='tooltip' data-placement='right' title='".$item['stav']."'/>";
        }

    }

}



