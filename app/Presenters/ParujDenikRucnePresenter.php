<?php

//Manažérský report zobrazující detail rozpočtu - stejné jako Jeden, ale není vázaný na uživatele a zobrazí všechny objednávky dohoromady bez ohledu na stav

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use App\MojeServices\ParovaniDenikuService;
use stdClass;


class ParujDenikRucnePresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('ManPresenter');
        }
    }

	public function renderDefault(): void
	{

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
            $item->cisloObjednavky = $denik->id_prehled;
            $relatedZakazka = $this->database->table('zakazky')->where('zakazka' , $denik->zakazky)->fetch();
            bdump($relatedZakazka);
            $item->vlastni0 = $relatedZakazka->vlastni == 1  ? $denik->castka : 0;      //vlastni 
            $item->vlastni0 = \round($item->vlastni0, 0);
            $item->normativ = $relatedZakazka->normativ == 1  ? $denik->castka : 0;      //vlastni 
            $item->normativ = \round($item->normativ, 0);
            $item->vlastni = $item->vlastni0 - $item->normativ;
            $item->sablony = $relatedZakazka->sablony == 1  ? $denik->castka : 0;      //sablony
            $item->sablony = \round($item->sablony, 0);
            $item->dotace = $relatedZakazka->dotace == 1 ? $denik->castka : 0;
            $item->dotace = \round($item->dotace, 0);
            $fetchedRozpocets[] = json_decode(json_encode($item), true);
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedRozpocets;
    }

    public function createComponentNovePolozkyDenikuGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $source = $this->parovaniDenikuService->vratNoveZaznamyDeniku();
        $grid->setPrimaryKey('uuid');
        $grid->setDataSource($source);
        $grid->addColumnText('uuid','ID záznamu')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('madati','MD')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('dal','Dal')->setSortable()->setSortableResetPagination();
/*      
        $grid->addColumnCallback('stav_id', function($column, $item) {
            $column->setRenderer(function() use ($item):string { return $this->renderujIkonuStavu($item); });
        }); 
*/
        $grid->addColumnDateTime('datum','Datum')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('doklad','Č.dokladu')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','Firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost_d','Činnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('zakazky','Zakázka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko_d','Středisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('psymbol','Pár.symbol')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { return (number_format($item['castka'],0,","," ") .' Kč'); })->setFilterText();
/*
        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav_id']);
        });
*/    
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination($source->getCount()>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());        
    } 

    public function createComponentSmazanePolozkyDenikuGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $source = $this->parovaniDenikuService->vratSmazaneZaznamyDeniku();
        $grid->setPrimaryKey('uuid');
        $grid->setDataSource($source);
        $grid->addColumnText('uuid','ID záznamu')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('madati','MD')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('dal','Dal')->setSortable()->setSortableResetPagination();
/*      
        $grid->addColumnCallback('stav_id', function($column, $item) {
            $column->setRenderer(function() use ($item):string { return $this->renderujIkonuStavu($item); });
        }); 
*/
        $grid->addColumnDateTime('datum','Datum')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('doklad','Č.dokladu')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','Firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost_d','Činnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('zakazky','Zakázka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko_d','Středisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('psymbol','Pár.symbol')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { return (number_format($item['castka'],0,","," ") .' Kč'); })->setFilterText();
/*
        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav_id']);
        });
*/    
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination($source->getCount()>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());        
    } 

    /**
     * pomocná funkce na vytvoření translatoru pro grid
     */
    private function getTranslator() {
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
        return $translator;
    }





// TK: tohle je zkopirovane odjinud pro inspiraci, prijde pryc

    public function handleZacniSledovat()
    {
        $this->database->table('skupiny')->insert([
            'uzivatel' => $this->prihlasenyId(),
            'rozpocet' => $this->sessionSection->manId,
        ]);
        $this->redrawControl();
    }

    public function handlePrestanSledovat()
    {
        $this->database->table('skupiny')->where([
            'uzivatel' => $this->prihlasenyId(),
            'rozpocet' => $this->sessionSection->manId,
        ])->delete();
        $this->redrawControl();
    }

}