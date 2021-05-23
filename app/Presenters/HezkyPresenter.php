<?php
//Manažérský report zobrazující Hezký rozpočet


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class HezkyPresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('HezkyPresenter');
        }
    }


    public function renderShow() 
    {                   //renderShow
        $source = $this->mapHezkyRozpocet(1);

        //uloz vysledek databazove query do session pro dalsi pouziti v createComponentXX (setrime databazi)
        $this->sessionSection->source = $source;

        $this->template->mySumV = $this->sumColumn($source, 'mySumV');
        $this->template->mySumN = $this->sumColumn($source, 'mySumN');
        $this->template->mySumD = $this->sumColumn($source, 'mySumD');
        $this->template->mySumS = $this->sumColumn($source, 'mySumS');
        $this->template->naklady = $this->sumColumn($source, 'mySumV') + $this->sumColumn($source, 'mySumN') + $this->sumColumn($source, 'mySumS');
        $this->template->plan = $this->sumColumn($source, 'castka') + $this->sumColumn($source, 'sablony');
        $this->template->castka = $this->sumColumn($source, 'castka');
        $this->template->sablony = $this->sumColumn($source, 'sablony');
        $this->template->objednano = $this->sumColumn($source, 'objednanoVS');
        $this->template->percentNaklady = $this->template->naklady == 0 ? 0 : round(($this->template->naklady / $this->template->plan) * 100, 0);
        $this->template->percentObjednano = $this->template->objednano == 0 ? 0 : round(($this->template->objednano / $this->template->plan) * 100, 0);
        $this->template->percent = $this->template->percentObjednano + $this->template->percentNaklady;
        $this->template->rozdil = $this->sumColumn($source, 'rozdil');
    } 

 
    private function mapHezkyRozpocet($argument)
    {
        $rok=$this->getSetup(1)->rok;      //zjitim rok a verzi;
        $verze=$this->getSetup(1)->verze;
        $rozpocets =$this->database->table('hezky');
        $fetchedRozpocets = [];
        foreach ($rozpocets as $hezky) {
            $item = new stdClass;
            $item->id = $hezky->id;
            $item->hezz = $hezky->hezky_rozpocet;
            $item->castka = $this->database->table('rozpocet')->where('hezky', $hezky->id)->where('rok', $rok)->where('verze',$verze)
                                ->sum('castka');
            $item->sablony = $this->database->table('rozpocet')->where('hezky', $hezky->id)->where('rok', $rok)->where('verze',$verze)
                                ->sum('sablony');
            $item->plan = $item->castka+$item->sablony;


            $relevantni_roz = $this->database->table('rozpocet')->where('hezky', $hezky->id)->where('rok', $rok)->where('verze',$verze)->select('id');

            // denik vlastni mimo normativ
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1)->where('normativ', 0);
            $item->mySumV = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantni)
                                ->sum('castka');
            $item->mySumV = $item->mySumV === null ? null : \round($item->mySumV, 0);

            // denik vlastni v normativu
            $relevantniN = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1);
            $item->mySumN = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantniN)
                                ->sum('castka');
            $item->mySumN = $item->mySumN === null ? null : \round($item->mySumN, 0);

            // denik sablony
            $relevantniS = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1);
            $item->mySumS = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantniS)
                                ->sum('castka');
            $item->mySumS = $item->mySumS === null ? null : \round($item->mySumS, 0);            
            
            // denik dotace
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
            $item->mySumD = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)
                        ->where('zakazky',$relevantni)->sum('castka');
            $item->mySumD = $item->mySumD === null ? null : \round($item->mySumD, 0);

            $item->soucetVNS =  $item->mySumV + $item->mySumN + $item->mySumS;


            $item->objednanoV = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $relevantni_roz)->where('stav', [0,1,3,4,9])
                                ->where('zakazka.vlastni', 1)->sum('castka');
            $item->objednanoS = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $relevantni_roz)->where('stav', [0,1,3,4,9])
                                ->where('zakazka.sablony', 1)->sum('castka');
            $item->objednanoD = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $relevantni_roz)->where('stav', [0,1,3,4,9])
                                    ->where('zakazka.dotace', 1)->sum('castka');
            
            $item->objednanoVS = $item->objednanoV + $item->objednanoS;
            $item->rozdil = $item->castka + $item->sablony - $item->mySumV - $item->mySumN - $item->mySumS - $item->objednanoV - $item->objednanoS;

            $fetchedRozpocets[] = json_decode(json_encode($item), true);
        }
        return $fetchedRozpocets;
    }

    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        
        $grid->setDataSource($this->sessionSection->source);
      
        $grid->addColumnLink('hezz', 'Rozpočet', 'Detail:show', 'hezz', ['detailId' => 'id']);
        // $grid->addColumnText('hezz', 'Rozpočet')->setAlign('left');
        $grid->addColumnNumber('castka', 'Plán vlastní')->setAlign('right')->setRenderer(function($item):string { return ($item['castka'] === null ? '' : number_format($item['castka'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('sablony', 'Plán šablony')->setAlign('right')->setRenderer(function($item):string { return ($item['sablony'] === null ? '' : number_format($item['sablony'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('plan', 'Plán CELKEM (vlastní+šablony)')->setAlign('right')->setRenderer(function($item):string { return ($item['plan'] === null ? '' : number_format($item['plan'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumV', 'Náklady vlastní')->setAlign('right')->setRenderer(function($item):string { return ($item['mySumV'] === null ? '' : number_format($item['mySumV'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumN', 'Náklady vlastní (normativ)')->setAlign('right')->setRenderer(function($item):string { return ($item['mySumN'] === null ? '' : number_format($item['mySumN'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumS', 'Náklady šablony')->setAlign('right')->setRenderer(function($item):string { return ($item['mySumS'] === null ? '' : number_format($item['mySumS'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumD', 'Náklady dotace (mimo rozpočet)')->setAlign('right')->setRenderer(function($item):string { return ($item['mySumD'] === null ? '' : number_format($item['mySumD'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('soucetVNS', 'Náklady CELKEM (vlastní+šablony)')->setAlign('right')->setRenderer(function($item):string { return ($item['soucetVNS'] === null ? '' : number_format($item['soucetVNS'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoV', 'Objednávky vlastní')->setAlign('right')->setRenderer(function($item):string { return ($item['objednanoV'] === null ? '' : number_format($item['objednanoV'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoS', 'Objednávky šablony')->setAlign('right')->setRenderer(function($item):string { return ($item['objednanoS'] === null ? '' : number_format($item['objednanoS'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoD', 'Objednávky dotace (mimo rozpočet)')->setAlign('right')->setRenderer(function($item):string { return ($item['objednanoD'] === null ? '' : number_format($item['objednanoD'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('rozdil', 'Zbývá z rozpočtu')->setAlign('right')->setRenderer(function($item):string { return ($item['rozdil'] === null ? '' : number_format($item['rozdil'],0,","," ") .' Kč'); })->getElementPrototype('td')->setClass('nowrap');
        $grid->setColumnsSummary(['castka','sablony','mySumV', 'mySumN', 'mySumS','mySumD', 'rozdil','objednanoV','objednanoS','objednanoD','soucetVNS','plan'])
                ->setRenderer(function($sum, $column):string { return ($sum === null ? '' : number_format($sum,0,","," ") .' Kč'); });
        $grid->setPagination(false);
       
   


        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');





        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Hromadné akce',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Vyberte',
            'ublaboo_datagrid.execute' => 'Provést',
    
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
    
        $grid->setTranslator($translator);
    
      
    } 

//     public function handleClick($param): void
//     {
//         if ($this->isAjax()) {
//             $this->template->detailItem = 'param';
//         }
//     }
}

