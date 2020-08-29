<?php
//Uživatelský report zobrazující detail rozpočtu včetně objednávek schválených a nezaúčtovaných

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use stdClass;


class JedenPresenter extends Nette\Application\UI\Presenter
{
	/** @var Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $database)
	{
        $this->database = $database;
       
	}


    


	public function renderShow(int $jedenId): void
	{
    
    $jeden = $this->database->table('rozpocet')->get($jedenId);
   
	if (!$jeden) {
		$this->error('Stránka nebyla nalezena');
	}


    $this->template->jeden = $jeden;
  

    $this->template->hospodar = $jeden->ref('hospodar')->jmeno;
    $this->template->hospodar2 = $jeden->ref('hospodar2')->jmeno;



    $source = $this->mapRozpocet(1,$jedenId);

    $this->template->vlastni = $this->sumColumn($source, 'vlastni');
    $this->template->dotace = $this->sumColumn($source, 'dotace');
    $this->template->sablony = $this->sumColumn($source, 'sablony');



    $nacti = $this->database->table('rozpocet')->where('id',$jedenId)->fetch();;
    $this->template->castka = $jeden->castka;      //ziskam castku vlastni;
    $this->template->sablonyplan = $nacti->sablony;    //ziskam castku sablony;
   

    

    $this->template->zbyva = $this->template->castka - ($this->template->vlastni) ;

    $utraceno = ($this->template->vlastni) + ($this->template->sablony);
    $plan = ($this->template->castka) + ($this->template->sablonyplan);
    $this->template->percent = $utraceno == 0 ? 0: round(($utraceno / $plan ) * 100, 0);
    
            //vypocet procent a kontrola deleni nulou



    $relevantni =$this->database->table('cinnost')->select('id')->where('id_rozpocet',$jedenId);
    $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.vlastni',1);
    $source2 = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.dotace',1); 
    $this->template->objednanoV = $this->sumColumn($source, 'castka');
    $this->template->objednanoD =  $this->sumColumn($source2, 'castka'); 
  
    

    } 
    



    private function mapRozpocet($argument,$zasejedenID)
    {
        $relevantni_zak = $this->database->table('zakazky')->select('zakazka')->where('NOT preuctovani', 1 );
        
        $rozpocets =$this->database->table('denik')->where('rozpocet',$zasejedenID)->where('petky', $argument)->where('zakazky', $relevantni_zak);
        //bdump($rozpocets);

       
        $fetchedRozpocets = [];
        foreach ($rozpocets as $denik) {
            $item = new stdClass;
            $item->id = $denik->id;
            $item->datum = $denik->datum;
            $item->cinnost_d = $denik->cinnost_d;
            $item->doklad = $denik->doklad;
            $item->firma = $denik->firma;
            $item->popis = $denik->popis;
            $item->stredisko_d = $denik->stredisko_d;
            $item->zakazky = $denik->zakazky;
            
            $item->cisloObjednavky = "nějaké číslo";
           
            $relatedZakazka = $this->database->table('zakazky')->where('zakazka' , $denik->zakazky)->fetch();
            
            bdump($relatedZakazka);
           
            $item->vlastni = $relatedZakazka->vlastni == 1  ? $denik->castka : 0;      //vlastni 
            $item->vlastni = \round($item->vlastni, 0);
          
            $item->sablony = $relatedZakazka->sablony == 1  ? $denik->castka : 0;      //sablony
            $item->sablony = \round($item->sablony, 0);

            $item->dotace = $relatedZakazka->dotace == 1 ? $denik->castka : 0;
            $item->dotace = \round($item->dotace, 0);


            $fetchedRozpocets[] = $item;
        }
                //$item->vlastni = $this->database->query('')
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

   

        $zasejedenID = $this->getParameter('jedenId');

     
    //     $relevantni = $this->database->table('cinnost')->select('cinnost')->where('id_rozpocet',$zasejedenID);   //seznam cinnosti patrici k vybranemu rozpoctu

    //    $vysledek = $this->database->table('denik')->where('cinnost_d', $relevantni)->where('petky',  1) ;   //polozky deniku dle seznamu cinnosti
     
       $grid->setDataSource($this->mapRozpocet(1,$zasejedenID));

      
        
        $grid->addColumnText('datum', 'Datum');
        $grid->addColumnText('cinnost_d', 'Činnost');
        $grid->addColumnText('doklad', 'Doklad');
        $grid->addColumnText('firma', 'Firma');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnText('stredisko_d', 'Středisko');
        $grid->addColumnText('zakazky', 'Zakázka');
        $grid->addColumnText('vlastni', 'Vlastní Kč')->setAlign('right');
        $grid->addColumnText('dotace', 'Dotace Kč')->setAlign('right');
        $grid->addColumnText('cisloObjednavky', 'Číslo objednávky');
        // $grid->addFilterRange('vlastni', 'Částka Kč');
      
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


      
    //    $grid->setMultiSortEnabled($enabled = TRUE);
        
    // $grid->addAggregationFunction('castka', new FunctionSum('castka'));



    } 



    private function mapObjednavky($argument,$zasejedenID)
    {

        
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );


        $uz = 8 ;       // tady bude nacteny uzivatel
       
        
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('prehled.schvaleno', $argument);


       

       
        $fetchedSource = [];
        foreach ($source as $objednavky) 
        {
            $item = new stdClass;
            $item->id = $objednavky->id;
            $item->id_prehled = $objednavky->id_prehled;
            $item->radka = $objednavky->radka;
            $item->firma = $objednavky->firma;
            $item->popis = $objednavky->popis;
            $item->cinnost = $objednavky->ref('cinnost')->cinnost;
            $item->zakazka = $objednavky->ref('zakazka')->zakazka;
            $item->zakazkap = $objednavky->ref('zakazka')->popis;
            $item->stredisko = $objednavky->ref('stredisko')->stredisko;
            $item->hospodar = $objednavky->ref('kdo')->jmeno;
            $item->castka = $objednavky->castka;

        
         if ($objednavky->nutno_overit == 0) {
            $item->overeni = "neověřuje se";
         }
            
          
          elseif 
            ($objednavky->overil == NULL)
            {
                $item->overeni = "čeká na ověření";
            }
           else {$item->overeni = "ověřeno";}

           $item->overeni = ($objednavky->zamitnul2) == NULL  ? $item->overeni : "zamítnuto";

           $item->schvaleni = $objednavky->schvalil == NULL  ? "čeká na schválení" : "schvaleno" ;
           $item->schvaleni = ($objednavky->zamitnul) == NULL  ? $item->schvaleni : "zamítnuto";
           

        $fetchedSource[] = $item;
        }
        return $fetchedSource;
    }





    public function createComponentSimpleGrid2($name)

    {
        $zasejedenID = $this->getParameter('jedenId');

        $grid = new DataGrid($this, $name);
       $grid->setDataSource($this->mapObjednavky(1,$zasejedenID));        // schválené a OVERENE



        

     
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
     
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('zakazkap','Popis zakázky');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnText('hospodar','Hospodář');

        $grid->addColumnText('castka', 'Částka');
        
        $grid->setPagination(false);
        
       

   


        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');

        $grid->setPagination(false);



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





    public function createComponentSimpleGrid3($name)

    {
        $zasejedenID = $this->getParameter('jedenId');

        $grid = new DataGrid($this, $name);
       $grid->setDataSource($this->mapObjednavky(0,$zasejedenID));        // 0 - neschválené



        

     
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
     
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('zakazkap','Popis zakázky');
        $grid->addColumnText('stredisko','Středisko');
        $grid->addColumnText('hospodar','Hospodář');
        $grid->addColumnText('schvaleni', 'Schválení');
        $grid->addColumnText('castka', 'Částka');
        $grid->addColumnText('overeni', 'Ověření');
        
        $grid->setPagination(false);
        
       

   


        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');

        $grid->setPagination(false);



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