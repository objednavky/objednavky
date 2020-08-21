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

    public function renderShow(int $detailId): void
	{
        $jeden = $this->database->table('rozpocet')->get($detailId);
        
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

                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1)->where('normativ', 0);
                $item->mySumV = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                    ->sum('castka');
                $item->mySumV = \round($item->mySumV, 0);

                // vlastni 

                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('normativ', 1);
                $item->mySumN = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                    ->sum('castka');
                $item->mySumN = \round($item->mySumV, 0);


                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1);
                $item->mySumS = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                    ->sum('castka');
                $item->mySumS = \round($item->mySumV, 0);            
               


                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('dotace', 1);
                $item->mySumD = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)
                            ->where('zakazky',$relevantni)->sum('castka');
                               
                $item->mySumD = \round($item->mySumD, 0);

                
                $relevantni_cin = $this->database->table('cinnost')->where('id_rozpocet', $rozpocet->id);
                $item->objednanoR = $this->database->table('objednavky')->where('cinnost', $relevantni_cin)
                                        ->where('vlastni = ? OR sablony = ?', 1,1)->sum('castka');

                                        

                $item->rozdil = $item->castka -  $item->mySumV  - $item->mySumN - $item->mySumS - $item->objednano;
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



