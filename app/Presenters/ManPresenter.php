<?php

//Manažérský report zobrazující detail rozpočtu - stejné jako Jeden, ale není vázaný na uživatele a zobrazí všechny objednávky dohoromady bez ohledu na stav

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use stdClass;


class ManPresenter extends ObjednavkyBasePresenter
{

    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('ManPresenter');
        }
    }

	public function renderShow(?int $manId): void
	{
        if (isset($manId)) {
            $this->sessionSection->manId = $manId;
        } elseif (isset($this->sessionSection->manId)) {
            $manId = $this->sessionSection->manId;
        } else {
            $this->error('Rozpočet nebyl nalezen');
        }
        $jeden = $this->database->table('rozpocet')->get($manId);
        $this->template->jeden = $jeden;
        $this->template->hospodar = $jeden->ref('hospodar')->jmeno;
        $this->template->hospodar2 = $jeden->ref('hospodar2')->jmeno;

        $source = $this->mapDenik(1,$manId);
        $this->template->denikVlastni = $this->sumColumn($source, 'vlastni');
        $this->template->denikDotace = $this->sumColumn($source, 'dotace');
        $this->template->denikSablony = $this->sumColumn($source, 'sablony');

        //$nacti = $this->database->table('rozpocet')->where('id',$manId)->fetch();
        $this->template->planVlastni = $jeden->castka;      //ziskam castku vlastni;
        $this->template->planSablony = $jeden->sablony;    //ziskam castku sablony;
        $this->template->planCelkem = $this->template->planVlastni + $this->template->planSablony;

/*
        $relevantniCinnost =$this->database->table('cinnost')->select('id')->where('id_rozpocet',$manId);
        $source = $this->database->table('objednavky')->where('cinnost', $relevantniCinnost)->where('zakazka.vlastni',1)->where('stav', [0,1,3,4,9]);
        $source2 = $this->database->table('objednavky')->where('cinnost', $relevantniCinnost)->where('zakazka.dotace',1)->where('stav', [0,1,3,4,9]); 
*/
        $this->template->objednanoVlastni = $this->sumColumn(
            $this->database->table('objednavky')
                ->where('cinnost.id_rozpocet', $manId)
                ->whereOr(['zakazka.vlastni = 1', 'zakazka.normativ = 1'])
                ->where('stav', [0,1,3,4,9]),
            'castka');

        $this->template->objednanoSablony = $this->sumColumn(
            $this->database->table('objednavky')
                ->where('cinnost.id_rozpocet', $manId)
                ->where('zakazka.sablony', 1)
                ->where('stav', [0,1,3,4,9]),
            'castka');
    
        $this->template->objednanoDotace =  $this->sumColumn(
            $this->database->table('objednavky')
                ->where('cinnost.id_rozpocet', $manId)
                ->where('zakazka.dotace',1)
                ->where('stav', [0,1,3,4,9]),
            'castka'); 
        
        $this->template->utracenoVlastni = $this->template->denikVlastni + $this->template->objednanoVlastni;
        $this->template->zbyvaVlastni = $this->template->planVlastni - $this->template->utracenoVlastni;

        $this->template->utracenoSablony = $this->template->denikSablony + $this->template->objednanoSablony;
        $this->template->zbyvaSablony = $this->template->planSablony - $this->template->utracenoSablony;

        $this->template->utracenoCelkem = $this->template->utracenoVlastni + $this->template->utracenoSablony;
        $this->template->zbyvaCelkem = $this->template->planCelkem - $this->template->utracenoCelkem;

        $this->template->percent = $this->template->planCelkem == 0 ? 0: round(($this->template->utracenoCelkem / $this->template->planCelkem ) * 100, 0);
        //vypocet procent a kontrola deleni nulou

        //zda sleduji tento rozpocet na uvodni strance
        $sleduji = $this->database->table('skupiny')->where('uzivatel', $this->prihlasenyId())->where('rozpocet', $manId)->count() > 0;
        $this->template->sleduji = $sleduji;
    } 

    private function mapDenik($argument,$zasejedenID)
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
            $item->vlastni = \round(($relatedZakazka->vlastni == 1 || $relatedZakazka->normativ == 1) ? $denik->castka : 0, 0);       //vlastni (+ normativ)
            $item->sablony = \round($relatedZakazka->sablony == 1  ? $denik->castka : 0, 0);          //sablony
            $item->dotace = \round($relatedZakazka->dotace == 1 ? $denik->castka : 0, 0);             //dotace
            $fetchedRozpocets[] = json_decode(json_encode($item), true);
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedRozpocets;
    }

    public function createComponentDenikGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->sessionSection->manId;
        $grid->setDataSource($this->mapDenik(1,$zasejedenID));
        $grid->addColumnDateTime('datum', 'Datum');
        $grid->addColumnText('cinnost_d', 'Činnost');
        $grid->addColumnText('doklad', 'Doklad');
        $grid->addColumnText('firma', 'Firma');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnText('stredisko_d', 'Středisko');
        $grid->addColumnText('zakazky', 'Zakázka');
        $grid->addColumnNumber('vlastni', 'Vlastní Kč')->setAlign('right');
        $grid->addColumnNumber('normativ', 'Normativ Kč')->setAlign('right');
        $grid->addColumnNumber('dotace', 'Dotace Kč')->setAlign('right');
        $grid->addColumnText('cisloObjednavky', 'Číslo objednávky');
        // $grid->addFilterRange('vlastni', 'Částka Kč');
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');

        $grid->setTranslator($this->getTranslator());        

    //    $grid->setMultiSortEnabled($enabled = TRUE);
    //    $grid->addAggregationFunction('castka', new FunctionSum('castka'));
    } 

    private function mapObjednavky($zasejedenID)
    {
//        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
//        $uz = 8 ;       // tady bude nacteny uzivatel
//        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [0,1,3,4,9]);
        return $this->objednavkyManager->mapObjednavkyRozpocetStav($zasejedenID, [0,1,3,4,9], $this->getUser()->getIdentity()->rok);
/*
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
            $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
            $item->castka = $objednavky->castka;
            $item->stav = $objednavky->stav;
            $item->overeni = ($objednavky->zamitnul2) == NULL  ? $item->overeni : "zamítnuto";
            $item->schvaleni = $objednavky->schvalil == NULL  ? "čeká na schválení" : "schvaleno" ;
            $item->schvaleni = ($objednavky->zamitnul) == NULL  ? $item->schvaleni : "zamítnuto";
            $fetchedSource[] = json_decode(json_encode($item), true);
        }
        return $fetchedSource;
*/
    }

    public function createComponentObjednavkyGrid($name)
    {
        $zasejedenID = $this->sessionSection->manId;
        $grid = new DataGrid($this, $name);
        $source = $this->mapObjednavky($zasejedenID);
        $grid->setDataSource($source);        // schválené a OVERENE
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item) { return $item['id_prehled'] . '/' .  $item['radka']; });
        $grid->addColumnText('stav','Stav')->setSortable()->setSortableResetPagination()->setTemplateEscaping(FALSE);
        $grid->addColumnCallback('stav', function($column, $item) {
            $column->setRenderer(function() use ($item):string { return $this->renderujIkonuStavu($item); });
        });
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('schvalovatel','Schvalovatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('schvalil','Schváleno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item) { return $item['nutno_overit'] == 1 ? "ANO" : "ne"; })->setFilterText();
        $grid->addColumnText('overovatel','Ověřovatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('overil','Ověřeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost','Činnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnostP','Popis činnosti')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('zakazka','Zakázka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko','Středisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { return (number_format($item['castka'],0,","," ") .' Kč'); })->setFilterText();
        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav']);
        });
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());        
    } 

    public function createComponentObjednavkyNeschvaleneGrid($name)
    {
    $zasejedenID = $this->sessionSection->manId;
        $grid = new DataGrid($this, $name);
        $obsah = $this->database->table('cinnost')->where('id_rozpocet',$zasejedenID);
        $grid->setDataSource($obsah);        // seznam činností zahrnutých rozpočtem
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('nazev_cinnosti','Název činnosti');
        $grid->setPagination(count($obsah)>10);
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