<?php
//Úvodní uživatelská obrazovka, zobrazí rozpočty přihlášených skupin

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;








class HomepagePresenter extends Nette\Application\UI\Presenter
{

/** @var Nette\Database\Context */
    private $database;
	public function __construct(Nette\Database\Context $databaseparam, Nette\Http\Session $session)
  
	{
		$this->database = $databaseparam;
    }
    
    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('Prihlas:show');

            
        }
    }
   
    

    public function prihlasenyId()
    {
       
        $uzivatel = $this->getUser()->getIdentity()->jmeno; 
         return $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();
    }
    
   


    public function renderDefault(): void
    {
        $user = $this->getUser();
        $user->setExpiration('20 minutes');
        bdump($this->getUser());



        // $uzivatel = $this->getUser()->getIdentity()->jmeno;      //   jméno uživatel

        $uz = $this->prihlasenyId();
        // $uz = $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();  //id prihlaseny uzivatel
       

      
        $this->template->prihlasen = $this->getUser()->getIdentity()->jmeno . ' v roli ' . implode(';', $this->getUser()->getRoles());

      
        $this->template->rozpocty = $this->database->table('rozpocet');
        
        $source = $this->mapRozpocet(1);

        $this->template->mySum = $this->sumColumn($source, 'mySum');
        $this->template->castkaSablony = $this->sumColumn($source, 'castkaSablony');

        $this->template->objed_ja_sch = $this->database->table('objednavky')->where('kdo = ? OR kdo2 = ?', $uz,$uz)
        ->where('schvalil', NULL)->count('id');    //    počet objednávek čekající na mé schválení

        $this->template->objed_jiny_sch = $this->database->table('prehled')->where('zadavatel', $uz)->where('schvaleno', 0)->where('zamitnuto', 0)
        ->count('id');    //    počet objednávek, které jsem zadal a ještě nejsou schválené

        $this->template->objed_zamitnute = $this->database->table('prehled')->where('zadavatel', $uz)->where('zamitnuto', 1)->where('zobrazovat', 1)
        ->count('id');    //    počet objednávek, které jsem zadal a byly zamítnuté




        
      
        $this->template->percent = $this->template->mySum == 0 ? 0: round(($this->template->mySum /  $this->template->castkaSablony) * 100, 0);

        $this->template->zbyva = $this->template->castkaSablony - $this->template->mySum;
   
        
      



       
                                      


    }

  
    
        public function getSetup($id)
    {
         return $this->database->table('setup')->where('id',$id)->fetch();
    }

  
    

        private function mapRozpocet($argument)
    {
           

            $rok=$this->getSetup(1)->rok;      //zjitim rok a verzi;
            $verze=$this->getSetup(1)->verze;

            $uz = $this->prihlasenyId();   // přihlášený uživatel
            
              
           
            $skupina = $this->database->table('skupiny')->where('uzivatel',$uz)->select('rozpocet');   //vyberu nastavené skupiny 

            bdump($skupina);
            $rozpocets =$this->database->table('rozpocet')->where('rok',$rok)->where('verze',$verze)->where('id',$skupina);
          
           
           
           
            $fetchedRozpocets = [];
            foreach ($rozpocets as $rozpocet) {
                $item = new stdClass;
                $item->id = $rozpocet->id;
                $item->rozpocet = $rozpocet->rozpocet;
                $item->jmeno = $rozpocet->ref('hospodar')->jmeno;
                $item->jmeno2 = $rozpocet->ref('hospodar2')->jmeno;
                $item->castkaSablony = $rozpocet->castka + $rozpocet->sablony;

                $relevantni = $this->database->table('zakazky')->select('zakazka')->where('NOT preuctovani', 1); //    zakázky, které se počítají
                $utraceno = $this->database->table('denik')->where('rozpocet', $rozpocet->id)->where('petky', $argument)->where('zakazky',$relevantni)
                                    ->sum('castka');
                $utraceno = \round($utraceno, 0);
              
                $objednavky_suma = $this->database->table('objednavky')->where('cinnost', ':cinnost.id_rozpocet')->where('NOT zamitnuto', 1)->sum('castka');
                $objednavky_suma = \round($objednavky_suma, 0);     //    nezamítnuté objednávky na rozpočet - celková částka

                $item->mySum = $utraceno + $objednavky_suma;
               

                $item->rozdil = $item->castkaSablony - ( $item->mySum );

               
                

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
        $grid->addColumnText('castkaSablony', 'Plán na celý rok schválený  Kč')->setAlign('right');
        $grid->addColumnText('mySum', 'Již utraceno nebo objednáno  Kč')->setAlign('right');
        $grid->addColumnText('rozdil', 'Zbývá Kč')->setAlign('right');

   


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


    public function createComponentSimpleGrid2($name)
    {

        $uz = 8 ;       // tady bude nacteny uzivatel
        $grid = new DataGrid($this, $name);
        
        $source = $this->database->table('objednavky')->where('kdo', $uz)->where('schvalil', NULL);
      

        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost');
     
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka');
        $grid->addColumnText('zakazkap','Popis zakázky','zakazky.popis:zakazka');
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko');
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


}



