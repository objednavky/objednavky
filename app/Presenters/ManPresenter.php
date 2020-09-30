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

	public function renderShow(int $manId): void
	{
        $jeden = $this->database->table('rozpocet')->get($manId);
        if (!$jeden) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->jeden = $jeden;
        $this->template->hospodar = $jeden->ref('hospodar')->jmeno;
        $this->template->hospodar2 = $jeden->ref('hospodar2')->jmeno;
        $source = $this->mapRozpocet(1,$manId);
        $this->template->vlastni = $this->sumColumn($source, 'vlastni');
        $this->template->normativ = $this->sumColumn($source, 'normativ');
        $this->template->dotace = $this->sumColumn($source, 'dotace');
        $this->template->sablony = $this->sumColumn($source, 'sablony');
        $nacti = $this->database->table('rozpocet')->where('id',$manId)->fetch();;
        $this->template->castka = $jeden->castka;      //ziskam castku vlastni;
        $this->template->sablonyplan = $nacti->sablony;    //ziskam castku sablony;
        $this->template->zbyva = $this->template->castka - ($this->template->vlastni) ;
        $this->template->zbyvatab = $this->template->castka - ($this->template->vlastni) - $this->template->normativ ;
        $utraceno = ($this->template->vlastni) + ($this->template->sablony);
        $plan = ($this->template->castka) + ($this->template->sablonyplan);
        $this->template->percent = $utraceno == 0 ? 0: round(($utraceno / $plan ) * 100, 0);
        //vypocet procent a kontrola deleni nulou
        $relevantni =$this->database->table('cinnost')->select('id')->where('id_rozpocet',$manId);
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.vlastni',1)->where('stav', [0,1,3,4,9]);
        $source2 = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.dotace',1)->where('stav', [0,1,3,4,9]); 
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
            $item->vlastni0 = $relatedZakazka->vlastni == 1  ? $denik->castka : 0;      //vlastni 
            $item->vlastni0 = \round($item->vlastni0, 0);
            $item->normativ = $relatedZakazka->normativ == 1  ? $denik->castka : 0;      //vlastni 
            $item->normativ = \round($item->normativ, 0);
            $item->vlastni = $item->vlastni0 - $item->normativ;
            $item->sablony = $relatedZakazka->sablony == 1  ? $denik->castka : 0;      //sablony
            $item->sablony = \round($item->sablony, 0);
            $item->dotace = $relatedZakazka->dotace == 1 ? $denik->castka : 0;
            $item->dotace = \round($item->dotace, 0);
            $fetchedRozpocets[] = $item;
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedRozpocets;
    }

    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->getParameter('manId');
        $grid->setDataSource($this->mapRozpocet(1,$zasejedenID));
        $grid->addColumnText('datum', 'Datum');
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
    //    $grid->addAggregationFunction('castka', new FunctionSum('castka'));
    } 

    private function mapObjednavky($zasejedenID)
    {
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
        $uz = 8 ;       // tady bude nacteny uzivatel
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [0,1,3,4,9]);
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
            $item->stav = $objednavky->stav;
            if ($objednavky->nutno_overit == 0) {
                $item->overeni = "neověřuje se";
            } elseif ($objednavky->overil == NULL) {
                $item->overeni = "čeká na ověření";
            } else {
                $item->overeni = "ověřeno";
            }
            $item->overeni = ($objednavky->zamitnul2) == NULL  ? $item->overeni : "zamítnuto";
            $item->schvaleni = $objednavky->schvalil == NULL  ? "čeká na schválení" : "schvaleno" ;
            $item->schvaleni = ($objednavky->zamitnul) == NULL  ? $item->schvaleni : "zamítnuto";
            $fetchedSource[] = $item;
        }
        return $fetchedSource;
    }

    public function createComponentSimpleGrid2($name)
    {
        $zasejedenID = $this->getParameter('manId');
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($this->mapObjednavky($zasejedenID));        // schválené a OVERENE
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('firma','Firma')->setFilterText();
        $grid->addColumnText('popis','Popis')->setFilterText();
        $grid->addColumnNumber('stav', 'Stav');
        $grid->addColumnText('cinnost','Činnost')->setFilterText();
        $grid->addColumnText('zakazka','Zakázka')->setFilterText();
        $grid->addColumnText('zakazkap','Popis zakázky')->setFilterText();
        $grid->addColumnText('stredisko','Středisko')->setFilterText();
        $grid->addColumnText('hospodar','Hospodář')->setFilterText();
        $grid->addColumnNumber('castka', 'Částka');
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
        $zasejedenID = $this->getParameter('manId');
        $grid = new DataGrid($this, $name);
        $obsah = $this->database->table('cinnost')->where('id_rozpocet',$zasejedenID);
        $grid->setDataSource($obsah);        // seznam činností zahrnutých rozpočtem
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('nazev_cinnosti','Název činnosti');
        $grid->setPagination(false);
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