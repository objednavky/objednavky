<?php
//Manažérský report zobrazující vybrané  rozpočety z Hezkého rozpočtu v detailu


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class DetailPresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('DetailPresenter');
        }
    }

    public function renderShow(?int $detailId): void
	{
        $setup = $this->getSetup(1);
        
        if (isset($detailId)) {
            $this->sessionSection->detailId= $detailId;
        } elseif (isset($this->sessionSection->detailId)) {
            $detailId = $this->sessionSection->detailId;
        } else {
            $detailId = -1;
        }

        if (($detailId > -1) && null !== $this->database->table('hezky')->get($detailId) ) {
            $this->template->hezkyRozpocetNazev = 'Podrobný rozpočet: '.$this->database->table('hezky')->where('id',$detailId)->fetch()->hezky_rozpocet;
            $source = $this->mapDetailniRozpocet(1, $this->database->table('rozpocet')->where('rok',$setup->rok)->where('verze',$setup->verze)->where('hezky',  $detailId)->order('rozpocet'));
        } else {
            $this->template->hezkyRozpocetNazev = 'Podrobný rozpočet kompletní';
            $source = $this->mapDetailniRozpocet(1, $this->database->table('rozpocet')->where('rok',$setup->rok)->where('verze',$setup->verze)->order('rozpocet'));
        }
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


    private function mapDetailniRozpocet($argument, $rozpocets)
    {
        $setup = $this->getSetup(1);
        
        $fetchedRozpocets = [];
        foreach ($rozpocets as $rozpocet) {
            $item = new stdClass;
            $item->id = $rozpocet->id;
            $item->rozpocet = $rozpocet->rozpocet;
            $item->jmeno = $rozpocet->ref('hospodar')->jmeno;
            $item->jmeno2 = $rozpocet->ref('hospodar2')->jmeno;
            $item->castka = $rozpocet->castka;
            $item->sablony = $rozpocet->sablony;
            // $item->castka = $this->database->table('rozpocet')->where('hezky', $zasejedenID)->where('rok', $setup->rok)->where('verze',$setup->verze);
            // $item->sablony = $this->database->table('rozpocet')->where('hezky', $zasejedenID)->where('rok', $setup->rok)->where('verze',$setup->verze)
            //                 ->sum('sablony');
            
            $item->plan = $item->castka+$item->sablony;

            // denik vlastni mimo normativ
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1)->where('normativ', 0);
            $item->mySumV = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                ->sum('castka');
            $item->mySumV = is_null($item->mySumV) ? 0 : round($item->mySumV. 0);

            // denik vlastni v normativu
            $relevantniN = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1);
            $item->mySumN = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantniN)
                                ->sum('castka');
            $item->mySumN = is_null($item->mySumN) ? 0 : round($item->mySumN, 0);

            // denik sablony
            $relevantniS = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1);
            $item->mySumS = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantniS)
                                ->sum('castka');
            $item->mySumS = is_null($item->mySumS) ? 0 : round($item->mySumS, 0);            
            
            // denik dotace
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
            $item->mySumD = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)
                        ->where('zakazky',$relevantni)->sum('castka');
            $item->mySumD = is_null($item->mySumD) ? 0 : round($item->mySumD, 0);

            $item->soucetVNS =  $item->mySumV + $item->mySumN + $item->mySumS;


            $item->objednanoV = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $rozpocet->id)->where('stav', [0,1,3,4,9])
                                ->where('zakazka.vlastni', 1)->sum('castka');
            $item->objednanoS = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $rozpocet->id)->where('stav', [0,1,3,4,9])
                                ->where('zakazka.sablony', 1)->sum('castka');
            $item->objednanoD = $this->database->table('objednavky')->where('cinnost.id_rozpocet', $rozpocet->id)->where('stav', [0,1,3,4,9])
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

        $grid->addColumnLink('rozpocet', 'Rozpočet', 'Man:show', 'rozpocet', ['manId' => 'id'])->setSortable()->setSortableResetPagination()->setFilterText();
        // $grid->addColumnText('rozpocet', 'Rozpočet')->setAlign('left');
        $grid->addColumnText('hospodar', 'Hospodář', 'jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('hospodar2', 'Zástupce', 'jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Plán vlastní')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['castka'] === null ? '' : number_format($item['castka'],0,","," ") .' Kč'); })
            
            
            
            
            //TODO dodělat řazení



            
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('sablony', 'Plán šablony')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['sablony'] === null ? '' : number_format($item['sablony'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('plan', 'Plán CELKEM (vlastní+šablony)')->setAlign('right')->setRenderer(function($item):string { return ($item['plan'] === null ? '' : number_format($item['plan'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumV', 'Náklady vlastní')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['mySumV'] === null ? '' : number_format($item['mySumV'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumN', 'Náklady vlastní (normativ)')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['mySumN'] === null ? '' : number_format($item['mySumN'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumS', 'Náklady šablony')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['mySumS'] === null ? '' : number_format($item['mySumS'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('mySumD', 'Náklady dotace (mimo rozpočet)')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['mySumD'] === null ? '' : number_format($item['mySumD'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('soucetVNS', 'Náklady CELKEM (vlastní+šablony)')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['soucetVNS'] === null ? '' : number_format($item['soucetVNS'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoV', 'Objednávky vlastní')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['objednanoV'] === null ? '' : number_format($item['objednanoV'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoS', 'Objednávky šablony')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['objednanoS'] === null ? '' : number_format($item['objednanoS'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('objednanoD', 'Objednávky dotace (mimo rozpočet)')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['objednanoD'] === null ? '' : number_format($item['objednanoD'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnNumber('rozdil', 'Zbývá z rozpočtu')
            ->setAlign('right')
            ->setRenderer(function($item):string { return ($item['rozdil'] === null ? '' : number_format($item['rozdil'],0,","," ") .' Kč'); })
            ->getElementPrototype('td')->setClass('nowrap');
        $grid->addColumnCallback('rozdil', function($column, $item) { $item['rozdil'] < 0 ? $column->getElementPrototype('td')->addAttributes(['class' => 'text-danger font-weight-bold',]) : $column->getElementPrototype('td')->removeAttributes(['class' => 'class',]); } );
        $grid->setColumnsSummary(['castka','sablony','mySumV', 'mySumN', 'mySumS','mySumD', 'rozdil','objednanoV','objednanoS','objednanoD','soucetVNS','plan'])
            ->setRenderer(function($sum, $column):string { return ($sum === null ? '' : number_format($sum,0,","," ") .' Kč'); });
        $grid->setPagination(false);
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setColumnsHideable();


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
}



