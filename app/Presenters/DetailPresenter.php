<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;

class DetailPresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $databaseparam)
	{
		$this->database = $databaseparam;
	}

    public function renderShow(int $jedenId): void
	{
        $jeden = $this->database->table('rozpocet')->get($jedenId);
        
        if (!$jeden) {
            $this->error('Stránka nebyla nalezena');
        }
        
        $this->template->rozpocty = $this->database->table('rozpocet');
        $source = $this->mapRozpocet(1);

        $this->template->mySum = $this->sumColumn($source, 'mySum');
        $this->template->castka = $this->sumColumn($source, 'castka');
        $this->template->mySum2 = $this->sumColumn($source, 'mySum2');
        $this->template->percent = round(($this->template->mySum /  $this->template->castka) * 100, 0);

        $this->template->zbyva = $this->template->castka - $this->template->mySum;
    }

    public function getSetup($id)
    {
         return $this->database->table('setup')->where('id',$id)->fetch();
    }

    private function JakyRok()
    {

        $vysledek = $this->database->table('setup')->where('id',1);

        foreach ($vysledek as $radka) {
            $vyslednyrok = $radka->rok;      //ziskam rok;
        }

        
    return $vyslednyrok;

    }

        private function JakaVerze(){

            $vysledek = $this->database->table('setup')->where('id',1);

            foreach ($vysledek as $radka) {
                $vyslednaverze = $radka->verze;      //ziskam verzi;
            }

         
        return $vyslednaverze;
    }
    

        private function mapRozpocet($argument)
        {
            $setup = $this->getSetup(1);
            $rozpocets =$this->database->table('rozpocet')->where('rok',$setup->rok)->where('verze',$setup->verze);
          
            bdump($rozpocets);

           
            $fetchedRozpocets = [];
            foreach ($rozpocets as $rozpocet) {
                $item = new stdClass;
                $item->id = $rozpocet->id;
                $item->rozpocet = $rozpocet->rozpocet;
                $item->jmeno = $rozpocet->ref('hospodar')->jmeno;
                $item->jmeno2 = $rozpocet->ref('hospodar2')->jmeno;
                $item->castka = $rozpocet->castka;

                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1);
                $item->mySum = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                    ->sum('castka');

                // $item->mySum = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)
                //                     ->sum('castka');
                               
                $item->mySum = \round($item->mySum, 0);


                $relevantni2 = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
                $item->mySum2 = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)
                            ->where('zakazky',$relevantni2)->sum('castka');

                // $item->mySum = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)
                //                     ->sum('castka');
                               
                $item->mySum2 = \round($item->mySum2, 0);

                $item->rozdil = $item->castka - ( $item->mySum );
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
      
        $grid->addColumnLink('rozpocet', 'Rozpočet', 'Jeden:show', 'rozpocet', ['jedenId' => 'id']);
  
        $grid->addColumnText('jmeno', 'Hospodář');
        $grid->addColumnText('jmeno2', 'Zástupce');
        $grid->addColumnText('castka', 'Částka Kč')->setAlign('right');
        $grid->addColumnText('mySum', 'Vlastní Kč')->setAlign('right');
        $grid->addColumnText('mySum2', 'Šablony Kč')->setAlign('right');
        $grid->addColumnText('rozdil', 'Zbývá Kč')->setAlign('right');

   


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


   

}



