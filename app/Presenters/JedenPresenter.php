<?php
//Uživatelský report zobrazující detail rozpočtu včetně objednávek schválených a nezaúčtovaných

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use stdClass;


class JedenPresenter extends ObjednavkyBasePresenter //změna
{
    private int $jedenId;

    protected function startup()
    {
        parent::startup();
    }
    
    public function actionShow(int $jedenId = null): void
	{
        if (null == $jedenId) {
            // když není nastaven jedenId, zkus ho vytáhnout ze session
            $jedenId = $this->getSession('JedenPresenter')->jedenId;
        } else {
            // když je nastaven jedenId, ulož ho do session pro příště
            $this->getSession('JedenPresenter')->jedenId = $jedenId;
        }
        $this->jedenId = $jedenId;
    }

    public function renderShow(): void
    {
        $jeden = $this->database->table('rozpocet')->get($this->jedenId);
        if (!$jeden) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->jeden = $jeden;
        $this->template->hospodar = $jeden->ref('hospodar')->jmeno;
        $this->template->hospodar2 = $jeden->ref('hospodar2')->jmeno;

        $source = $this->mapDenik(1,$this->jedenId);
        $this->template->vlastni = $this->sumColumn($source, 'vlastni');
        $this->template->dotace = $this->sumColumn($source, 'dotace');
        $this->template->sablony = $this->sumColumn($source, 'sablony');

        $nacti = $this->database->table('rozpocet')->where('id',$this->jedenId)->fetch();;
        $this->template->castka = $jeden->castka;      //ziskam castku vlastni;
        $this->template->sablonyplan = $nacti->sablony;    //ziskam castku sablony;
        $this->template->zbyva = $this->template->castka - ($this->template->vlastni) ;

        $utraceno = ($this->template->vlastni) + ($this->template->sablony);
        $plan = ($this->template->castka) + ($this->template->sablonyplan);
        $this->template->percent = $utraceno == 0 ? 0: round(($utraceno / $plan ) * 100, 0);

        //vypocet procent a kontrola deleni nulou
        $relevantni =$this->database->table('cinnost')->select('id')->where('id_rozpocet',$this->jedenId);
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.vlastni',1);
        $source2 = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('zakazka.dotace',1); 
        $this->template->objednanoV = $this->sumColumn($source, 'castka');
        $this->template->objednanoD =  $this->sumColumn($source2, 'castka'); 
    } 

    private function mapCinnost($argument,$zasejedenID)
    {
        $relevantni = $this->database->table('cinnost')->where('id_rozpocet',$zasejedenID)->where('vyber',1);
        $deniky =$this->database->table('denik')->where('cinnost_d',$relevantni)->where('petky', $argument)->where('rozpocet',$zasejedenID);
        //vypisuji všechny položky daného rozpočtu pro relevantní činnost - musí splňovat rozpočet, tím máme zajištěný rok;
        $kolikRade = 0;
        $fetchedDeniky = [];
        foreach ($relevantni as $jednacinnost) {
            $item = new stdClass;
            $kolikRade = ++$kolikRade;
            $item->id = $jednacinnost->id;
            $item->cinnost = $jednacinnost->cinnost;
            $item->nazev_cinnosti = $jednacinnost->nazev_cinnosti;
            $zakazkaV = $this->database->table('zakazky')->where('vlastni' , 1)->fetch();
            $item->vlastni = $this->database->table('denik')->where('cinnost_d', $jednacinnost->cinnost)
                             ->where('petky', $argument)->where('rozpocet',$zasejedenID)->where('zakazky', $zakazkaV->zakazka)->sum('castka');
            $item->vlastni = \round($item->vlastni, 0);
            $item->vlastniObj = $this->database->table('objednavky')->where('cinnost',$jednacinnost)->where('stav',[0,1,3,4,9])
                ->where('zakazka', $zakazkaV)->sum('castka');
            $item->vlastniObj = \round($item->vlastniObj, 0);
            $item->rozpocetV = $kolikRade== 1 ? ($this->database->table('rozpocet')->where('id',$zasejedenID)->fetch())->castka : 0;
            // rozpočet se počítá jen pro první výskyt
            $item->celkemV = $item->vlastni + $item->vlastniObj ;
            $item->zbyvaV = $item->rozpocetV - ($item->vlastni + $item->vlastniObj);
            $zakazkaS = $this->database->table('zakazky')->where('sablony' , 1)->fetch();
            $item->sablony = $this->database->table('denik')->where('cinnost_d', $jednacinnost->cinnost)
                ->where('petky', $argument)->where('rozpocet',$zasejedenID)->where('zakazky', $zakazkaS->zakazka)->sum('castka');
            $item->sablony = \round($item->sablony, 0);
            $item->sablonyObj = $this->database->table('objednavky')->where('cinnost',$jednacinnost)->where('stav',[0,1,3,4,9])
                ->where('zakazka', $zakazkaS)->sum('castka');
            $item->sablonyObj = \round($item->sablonyObj, 0);
            $item->rozpocetS = $kolikRade== 1 ? ($this->database->table('rozpocet')->where('id',$zasejedenID)->fetch())->sablony : 0;
            $item->celkemS = $item->sablony + $item->sablonyObj;
            $item->zbyvaS = $item->rozpocetS - ($item->sablony + $item->sablonyObj);
            $zakazkaD = $this->database->table('zakazky')->where('dotace' , 1)->fetch();
            $item->dotace = $this->database->table('denik')->where('cinnost_d', $jednacinnost->cinnost)
                ->where('petky', $argument)->where('rozpocet',$zasejedenID)->where('zakazky', $zakazkaD->zakazka)->sum('castka');
            $item->dotace = \round($item->dotace, 0);
            $item->dotaceObj = $this->database->table('objednavky')->where('cinnost',$jednacinnost)->where('stav',[0,1,3,4,9])
                ->where('zakazka', $zakazkaD)->sum('castka');
            $item->dotaceObj = \round($item->dotaceObj, 0);
            // $zakazkaP = $this->database->table('zakazky')->where('preuctovani' , 1)->fetch();
            // $item->preuctovani = $this->database->table('denik')->where('cinnost_d',$jednacinnost)
            //                  ->where('petky', $argument)->where('rozpocet',$zasejedenID)->where('zakazky', $zakazkaP)->sum('castka');
            // $item->preuctovani = \round($item->preuctovani, 0);
            // $item->preuctovaniObj = $this->database->table('objednavky')->where('cinnost',$jednacinnost)->where('stav',[0,1,3,4,9])
            // ->where('zakazky', $zakazkaP)->sum('castka');
            // $item->preuctovaniObj = \round($item->preuctovaniObj, 0);
            $fetchedDeniky[] = $item;
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedDeniky;
    }

    private function mapDenik($argument,$zasejedenID)
    {
        $relevantniV_zak = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1 );
        $relevantniS_zak = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1 );
        $deniky =$this->database->table('denik')->where('rozpocet',$zasejedenID)->where('petky', $argument);
        //vypisuji všechny položky daného rozpočtu;
        $fetchedDeniky = [];
        foreach ($deniky as $denik) {
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
            $item->vlastni = $relatedZakazka->vlastni == 1  ? $denik->castka : 0;      //vlastni 
            $item->vlastni = \round($item->vlastni, 0);
            $item->sablony = $relatedZakazka->sablony == 1  ? $denik->castka : 0;      //sablony
            $item->sablony = \round($item->sablony, 0);
            $item->dotace = $relatedZakazka->dotace == 1 ? $denik->castka : 0;
            $item->dotace = \round($item->dotace, 0);
            $item->preuctovani = $relatedZakazka->preuctovani == 1 ? $denik->castka : 0;
            $item->preuctovani = \round($item->preuctovani, 0);
            $fetchedDeniky[] = $item;
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedDeniky;
    }

    public function createComponentPrehledGrid($name)      
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->jedenId;
        $grid->setDataSource($this->mapCinnost(1,$zasejedenID));
        $grid->addColumnText('cinnost', 'Činnost');
        $grid->addColumnText('nazev_cinnosti', 'Název činnosti');
        $grid->addColumnNumber('rozpocetV', 'Plánovaný rozpočet - při více činnostech lze použít na všechny')->addCellAttributes(['class' => 'text-success']);
        $grid->addColumnNumber('vlastni', 'Utraceno vlastní Kč');
        $grid->addColumnNumber('vlastniObj', 'Objednáno vlastní Kč');
        $grid->addColumnNumber('celkemV', 'Celkem Kč');
        $grid->addColumnNumber('zbyvaV', 'Zbývá v rozpočtu Kč');
        $grid->addColumnNumber('rozpocetS', 'Plánované šablony na celý rok Kč')->addCellAttributes(['class' => 'text-success']);
        $grid->addColumnNumber('sablony', 'Šablony již utraceno Kč');
        $grid->addColumnNumber('sablonyObj', 'Šablony objednáno Kč');
        $grid->addColumnNumber('celkemS', 'Celkem Kč');
        $grid->addColumnNumber('zbyvaS', 'V šablonách zbývá Kč');
        $grid->addColumnNumber('dotace', 'Účelové dotace již utraceno Kč');
        $grid->addColumnNumber('dotaceObj', 'Účelové dotace objednáno Kč');
        $grid->setColumnsSummary(['rozpocetV','vlastni','vlastniObj','zbyvaV','celkemV','celkemS',
            'rozpocetS','sablony','sablonyObj','dotace','zbyvaS','dotace','dotaceObj']);
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(false);
        // ->setSplitWordsSearch(FALSE);     bude to hledat při více slovech jen celý řetězec

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
 
    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->jedenId;
        //     $relevantni = $this->database->table('cinnost')->select('cinnost')->where('id_rozpocet',$zasejedenID);   //seznam cinnosti patrici k vybranemu rozpoctu
        //    $vysledek = $this->database->table('denik')->where('cinnost_d', $relevantni)->where('petky',  1) ;   //polozky deniku dle seznamu cinnosti
       $grid->setDataSource($this->mapDenik(1,$zasejedenID));
        $grid->addColumnText('datum', 'Datum');
        $grid->addColumnText('cinnost_d', 'Činnost');
        $grid->addColumnText('doklad', 'Doklad');
        $grid->addColumnText('firma', 'Firma');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnText('stredisko_d', 'Středisko');
        $grid->addColumnText('zakazky', 'Zakázka');
        $grid->addColumnNumber('vlastni', 'Vlastní Kč');
        $grid->addColumnNumber('sablony', 'Šablony Kč');
        $grid->addColumnNumber('dotace', 'Účelové dotace Kč');
        $grid->addColumnNumber('preuctovani', 'Přeúčtování Kč');
        $grid->addColumnNumber('cisloObjednavky', 'Číslo objednávky');
        $grid->setPagination(true);
        $grid->setItemsPerPageList([30, 50, 100]);
        $grid->setColumnsSummary(['vlastni','sablony','dotace', 'preuctovani']);
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

    public function createComponentSimpleGrid2($name)
    {
        $zasejedenID = $this->jedenId;
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [3,4,9]);
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source );        
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('prehled_popis','Popis objednávky','prehled.popis:id_prehled');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('zakladatel','Zakladatel','uzivatel.jmeno:zakladatel' );
        $grid->addColumnText('firma','Firma');
        $grid->addColumnText('popis','Popis položky');
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost');
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka');
        $grid->addColumnText('zakazkap','Popis zakázky','zakazky.popis:zakazka');
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko');
        $grid->addColumnText('castka', 'Částka');
        $grid->addColumnText('stav', 'Stav objednávky','lidsky_status.popis:stav');
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
        $zasejedenID = $this->jedenId;
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [0,1]);
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source );        
        $grid->setDataSource($source);
        $grid->addColumnText('id_prehled','Číslo objednávky');
        $grid->addColumnText('prehled_popis','Popis objednávky','prehled.popis:id_prehled');
        $grid->addColumnText('radka','Číslo položky');
        $grid->addColumnText('zakladatel','Zakladatel','uzivatel.jmeno:zakladatel' );
        $grid->addColumnText('firma','Firma');
        $grid->addColumnText('popis','Popis položky');
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost');
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka');
        $grid->addColumnText('zakazkap','Popis zakázky','zakazky.popis:zakazka');
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko');
        $grid->addColumnText('castka', 'Částka');
        $grid->addColumnText('stav', 'Stav objednávky','lidsky_status.popis:stav');
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