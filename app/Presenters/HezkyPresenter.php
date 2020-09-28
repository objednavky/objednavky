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

    protected function startup()
    {
        parent::startup();

        
    }


    public function renderShow() 
    {                   //renderShow
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
        bdump($source);
    } 

 
    private function mapRozpocet($argument)
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

            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1 );
            $item->mySumN = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                        ->where('rozpocet',$relevantni_roz)->sum('castka');      // normativ
            $item->mySumN = \round($item->mySumN, 0);    

            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1 );
            $item->mySumV = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                    ->where('rozpocet',$relevantni_roz)->sum('castka');      // vlastní vcetne normativu

                    $item->mySumV -= $item->mySumN;    // vlastní bez normativu
            $item->mySumV = \round($item->mySumV, 0);     

            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1 );
            $item->mySumS = $this->database->table('denik')->where('rozpocet', $relevantni_roz)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                            ->where('rozpocet',$relevantni_roz)->sum('castka');      // šablona
                            
            $item->mySumS = \round($item->mySumS, 0); 


            $relevantni2 = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);

            $item->mySumD = $this->database->table('denik')->where('rozpocet', $relevantni2)->where('petky', $argument)
                                ->where('rozpocet',$relevantni_roz)->where('zakazky',$relevantni2)->sum('castka');
            $item->mySumD = \round($item->mySumD, 0);  // dotace

            $relevantni_cin = $this->database->table('cinnost')->where('id_hezky', $hezky->id)->where('rok', $rok);
            $item->objednanoVS = $this->database->table('objednavky')->where('cinnost', $relevantni_cin)
                                                ->where('zakazka.vlastni = ? OR zakazka.sablony = ?', 1,1)->where('stav ?', [0,1,3,4,9])->sum('castka');
            $item->objednanoD = $this->database->table('objednavky')->where('cinnost', $relevantni_cin)->where('stav ?', [0,1,3,4,9])
                                                ->where('zakazka.dotace = 1')->sum('castka');
            
            $item->objednano = $item->objednanoD + $item->objednanoVS;
            $item->rozdil = ($item->castka) +($item->sablony) - ( $item->mySumV)- ($item->mySumS) -  ($item->mySumN) - $item->objednanoVS;
            $item->soucetV =  ( $item->mySumV)+ ($item->mySumS) +  ($item->mySumN) ;

            $fetchedRozpocets[] = $item;
        }
        return $fetchedRozpocets;
    }

    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        
        $source = $this->mapRozpocet(1);

        
        $grid->setDataSource($source);
      
        $grid->addColumnLink('hezz', 'Rozpočet', 'Detail:show', 'hezz', ['detailId' => 'id']);
        // $grid->addColumnText('hezz', 'Rozpočet')->setAlign('left');
        $grid->addColumnNumber('castka', 'Plán vlastní - součást rozpočtu Kč')->setAlign('right');
        $grid->addColumnNumber('sablony', 'Plán šablony - součást rozpočtu Kč')->setAlign('right');
        $grid->addColumnNumber('plan', 'Celkem plán rozpočet  vlastní + šablony Kč')->setAlign('right');
        $grid->addColumnNumber('mySumV', 'Náklady vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('mySumN', 'Náklady normativ Kč')->setAlign('right');
        $grid->addColumnNumber('mySumS', 'Náklady šablony')->setAlign('right');
        $grid->addColumnNumber('soucetV', 'Součet nákladů vlastní+normativ+šablony')->setAlign('right');
        $grid->addColumnNumber('objednanoVS', 'Objednávky z rozpočtu')->setAlign('right');
        $grid->addColumnNumber('mySumD', 'Jiné účelové dotace - ne z rozpočtu')->setAlign('right');
       
        $grid->addColumnNumber('objednanoD', 'Objednávky dotace - ne z rozpočtu')->setAlign('right');
       
        $grid->addColumnNumber('rozdil', 'Zbývá z rozpočtu')->setAlign('right');
        $grid->setColumnsSummary(['castka','sablony','mySumV', 'mySumN', 'mySumS','mySumD', 'rozdil','objednanoVS','objednanoD','soucetV','plan']);
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

