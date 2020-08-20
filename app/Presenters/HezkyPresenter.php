<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class HezkyPresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $databaseparam)
	{
		$this->database = $databaseparam;
	}

    public function renderShow() {                   //renderShow

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

   

    public function getSetup($id)
    {
         return $this->database->table('setup')->where('id',$id)->fetch();
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

            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1 );
            $item->mySumN = $this->database->table('denik')->where('hezky', $hezky->id)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                                ->sum('castka');      // normativ
                            
            $item->mySumN = \round($item->mySumN, 0);     

            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1 );
            $item->mySumV = $this->database->table('denik')->where('hezky', $hezky->id)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                                ->sum('castka');      // vlastní vcetne normativu

            $item->mySumV -= $item->mySumV;    // vlastní bez normativu
            $item->mySumV = \round($item->mySumV, 0);     


            $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1 );
            $item->mySumS = $this->database->table('denik')->where('hezky', $hezky->id)->where('petky', $argument)->where('zakazky',$relevantni_zak)
                                ->sum('castka');      // šablona
                            
            $item->mySumS = \round($item->mySumS, 0); 


            $relevantni2 = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
            $item->mySumD = $this->database->table('denik')->where('hezky', $hezky->id)->where('petky', $argument)
                        ->where('zakazky',$relevantni2)->sum('castka');
            $item->mySumD = \round($item->mySumD, 0);  // dotace



            $item->rozdil = ($item->castka) +($item->sablony) - ( $item->mySumV)- ($item->mySumS) -  ($item->mySumN);
            // doplnit objednávky!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!





            $fetchedRozpocets[] = $item;
        }

        return $fetchedRozpocets;
    }

    private function sumColumn($array ,$columnArgument)
    {
        $sum = 0;
        foreach ($array as $item) {
            $sum += $item->$columnArgument;
        }

        return $sum;
    }

    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        
        $source = $this->mapRozpocet(1);

        
        $grid->setDataSource($source);
      
        // $grid->addColumnLink('hezz', 'Rozpočet', 'Detail:show', 'hezz', ['detailId' => 'id']);
        $grid->addColumnText('hezz', 'Rozpočet')->setAlign('left');
        $grid->addColumnNumber('castka', 'Plán vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('sablony', 'Plán šablony Kč')->setAlign('right');
        $grid->addColumnNumber('mySumV', 'Náklady vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('mySumN', 'Náklady normativ Kč')->setAlign('right');
        $grid->addColumnNumber('mySumS', 'Náklady šablony Kč')->setAlign('right');
        $grid->addColumnNumber('mySumD', 'Jiné účelové dotace Kč')->setAlign('right');
        $grid->addColumnNumber('rozdil', 'Zbývá Kč')->setAlign('right');
        
        $grid->setColumnsSummary(['mySumV', 'mySumN', 'mySumS','mySumD', 'rozdil']);
        // $grid->setColumnsSummary(['mySumV', 'mySumV'])
        //         ->setRenderer(function($sum, string $column): string {
        //             if ($column === 'price') {
        //                 return $sum . ' $';
        //             }

        //             return $sum . ' items';
        //         });
   


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

