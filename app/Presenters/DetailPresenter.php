<?php
//Manažérský report zobrazující vybrané  rozpočety z Hezkého rozpočtu v detailu


declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class DetailPresenter extends ObjednavkyBasePresenter
{

    public function renderShow(int $detailId): void
	{
        $jeden = $this->database->table('rozpocet')->get($detailId);
        
        if (!$jeden) {
            $this->error('Stránka nebyla nalezena');
        }
        
        $this->template->rozpocty = $this->database->table('rozpocet');
        $source = $this->mapRozpocet(1);

        $this->template->mySumV = $this->sumColumn($source, 'mySumV');
        $this->template->mySumN = $this->sumColumn($source, 'mySumN');
        $this->template->mySumD = $this->sumColumn($source, 'mySumD');
        $this->template->mySumS = $this->sumColumn($source, 'mySumS');
        $this->template->naklady = $this->sumColumn($source, 'mySumV') + $this->sumColumn($source, 'mySumN') + $this->sumColumn($source, 'mySumS');
        $this->template->plan = $this->sumColumn($source, 'castka') + $this->sumColumn($source, 'sablony');
        $this->template->castka = $this->sumColumn($source, 'castka');
        $this->template->sablony = $this->sumColumn($source, 'sablony');
        $this->template->percent = $this->template->naklady == 0 ? 0 : round(($this->template->plan /  $this->template->naklady) * 100, 0);
        $this->template->zbyva = $this->template->plan - $this->template->naklady;
    }


    private function mapRozpocet($argument)
    {
        $setup = $this->getSetup(1);
        $zasejedenID = $this->getParameter('detailId');
        $rozpocets =$this->database->table('rozpocet')->where('rok',$setup->rok)->where('verze',$setup->verze)->where('hezky',$zasejedenID);
        
        // jen vybrané rozpočty podle hezky
        bdump($rozpocets);

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
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1)->where('normativ', 0);
            $item->mySumV = $this->database->table('denik')->where('rozpocet', $item->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                ->sum('castka');
            $item->mySumV = \round($item->mySumV, 0);

            // vlastni 
            $relevantniN = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1);
            $item->mySumN = $this->database->table('denik')->where('rozpocet', $item->id)->where('petky', $argument)->where('zakazky',$relevantniN)
                                ->sum('castka');
            $item->mySumN = \round($item->mySumN, 0);

            $relevantniS = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1);
            $item->mySumS = $this->database->table('denik')->where('rozpocet', $item->id)->where('petky', $argument)->where('zakazky',$relevantniS)
                                ->sum('castka');
            $item->mySumS = \round($item->mySumS, 0);            
            
            $relevantni = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
            $item->mySumD = $this->database->table('denik')->where('rozpocet', $item->id)->where('petky', $argument)
                        ->where('zakazky',$relevantni)->sum('castka');
            $item->mySumD = \round($item->mySumD, 0);

            $relevantni_cin = $this->database->table('cinnost')->where('id_rozpocet', $rozpocet->id);
            $item->objednanoVS = $this->database->table('objednavky')->where('cinnost', $relevantni_cin)->where('stav', [0,1,3,4,9])
                                ->where('zakazka.vlastni = ? OR zakazka.sablony = ?', 1,1)->sum('castka');
            $item->objednanoD = $this->database->table('objednavky')->where('cinnost', $relevantni_cin)->where('stav', [0,1,3,4,9])
                                    ->where('zakazka.dotace = 1')->sum('castka');
            $item->objednano = $item->objednanoD + $item->objednanoVS;
            
            $item->soucetV =  ( $item->mySumV)+ ($item->mySumS) +  ($item->mySumN) ;

            $item->rozdil = $item->castka -  $item->mySumV  - $item->mySumN - $item->mySumS - $item->objednanoVS;
            $fetchedRozpocets[] = $item;
        }

        return $fetchedRozpocets;
    }

    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $source = $this->mapRozpocet(1);
        $grid->setDataSource($source);
        $grid->addColumnLink('rozpocet', 'Rozpočet', 'Man:show', 'rozpocet', ['manId' => 'id']);
        // $grid->addColumnText('rozpocet', 'Rozpočet')->setAlign('left');
        $grid->addColumnNumber('castka', 'Plán vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('sablony', 'Plán šablony Kč')->setAlign('right');
        $grid->addColumnNumber('plan', 'Celkem plán rozpočet  vlastní + šablony Kč')->setAlign('right');
        $grid->addColumnNumber('mySumV', 'Náklady vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('mySumN', 'Náklady normativ Kč')->setAlign('right');
        $grid->addColumnNumber('mySumS', 'Náklady šablony')->setAlign('right');
        $grid->addColumnNumber('soucetV', 'Součet nákladů vlastní+normativ+šablony')->setAlign('right');
        $grid->addColumnNumber('objednanoVS', 'Objednávky z rozpočtu')->setAlign('right');
        $grid->addColumnNumber('mySumD', 'Jiné účelové dotace')->setAlign('right');
        $grid->addColumnNumber('objednanoD', 'Objednávky dotace')->setAlign('right');
        $grid->addColumnNumber('rozdil', 'Zbývá z rozpočtu')->setAlign('right');
        $grid->setColumnsSummary(['castka','sablony','mySumV', 'mySumN', 'mySumS','mySumD', 'rozdil','objednanoVS','objednanoD','plan','soucetV']);
        $grid->setPagination(false);
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
}



