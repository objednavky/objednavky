<?php
//Uživatelský report zobrazující detail rozpočtu včetně objednávek schválených a nezaúčtovaných

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;
use stdClass;

use function Symfony\Component\String\b;

class JedenPresenter extends ObjednavkyBasePresenter //změna
{
    private $sessionSection;

    protected function startup()
    {
        parent::startup();
        if (!isset($this->sessionSection)) {
            $this->sessionSection = $this->getSession('JedenPresenter');
        }    }
    
    public function actionShow(?int $jedenId): void
	{
        if (isset($jedenId)) {
            // když je nastaven jedenId, ulož ho do session pro příště
            $this->sessionSection->jedenId = $jedenId;
        } elseif (isset($this->sessionSection->jedenId)) {
            // když není nastaven jedenId, zkus ho vytáhnout ze session
            $jedenId = $this->sessionSection->jedenId;
        } else {
            $this->error('Rozpočet nebyl nalezen');
        }

    }

    public function renderShow(): void
    {
        //zda sleduji tento rozpocet na uvodni strance
        $sleduji = $this->database->table('skupiny')->where('uzivatel', $this->prihlasenyId())->where('rozpocet', $this->sessionSection->jedenId)->count() > 0;
        $this->template->sleduji = $sleduji;

        $jeden = $this->database->table('rozpocet')->get($this->sessionSection->jedenId);
        if (!$jeden) {
            $this->error('Stránka nebyla nalezena');
        }
        $this->template->jeden = $jeden;
        $this->template->hospodar = $jeden->ref('hospodar')->jmeno;
        $this->template->hospodar2 = $jeden->ref('hospodar2')->jmeno;

        $source = $this->mapDenik(1,$this->sessionSection->jedenId);
        $this->template->vlastni = $this->sumColumn($source, 'vlastni');
        $this->template->dotace = $this->sumColumn($source, 'dotace');
        $this->template->sablony = $this->sumColumn($source, 'sablony');

        $nacti = $this->database->table('rozpocet')->where('id',$this->sessionSection->jedenId)->fetch();;
        $this->template->castka = $jeden->castka;      //ziskam castku vlastni;
        $this->template->sablonyplan = $nacti->sablony;    //ziskam castku sablony;
        $this->template->zbyva = $this->template->castka - ($this->template->vlastni) ;

        $utraceno = ($this->template->vlastni) + ($this->template->sablony);
        $plan = ($this->template->castka) + ($this->template->sablonyplan);
        $this->template->percent = $utraceno == 0 ? 0: round(($utraceno / $plan ) * 100, 0);

        //vypocet procent a kontrola deleni nulou
        $relevantni =$this->database->table('cinnost')->select('id')->where('id_rozpocet',$this->sessionSection->jedenId);
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
            $fetchedDeniky[] = json_decode(json_encode($item), true);
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedDeniky;
    }

    private function mapDenik($argument,$zasejedenID)
    {
        $relevantniV_zak = $this->database->table('zakazky')->select('zakazka')->where('vlastni', 1 );
        $relevantniS_zak = $this->database->table('zakazky')->select('zakazka')->where('sablony', 1 );
        $deniky =$this->database->table('denik')->where('rozpocet',$zasejedenID)->where('petky', $argument)->order('datum DESC');
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
            $item->cisloObjednavky = $denik->id_prehled;
            $relatedZakazka = $this->database->table('zakazky')->where('zakazka' , $denik->zakazky)->fetch();
            $item->vlastni = $relatedZakazka->vlastni == 1  ? $denik->castka : 0;      //vlastni 
            $item->vlastni = \round($item->vlastni, 0);
            $item->sablony = $relatedZakazka->sablony == 1  ? $denik->castka : 0;      //sablony
            $item->sablony = \round($item->sablony, 0);
            $item->dotace = $relatedZakazka->dotace == 1 ? $denik->castka : 0;
            $item->dotace = \round($item->dotace, 0);
            $item->preuctovani = $relatedZakazka->preuctovani == 1 ? $denik->castka : 0;
            $item->preuctovani = \round($item->preuctovani, 0);
            $fetchedDeniky[] = json_decode(json_encode($item), true);
        }
        //$item->vlastni = $this->database->query('')
        return $fetchedDeniky;
    }

    public function createComponentPrehledGrid($name)      
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->sessionSection->jedenId;
        $source = $this->mapCinnost(1,$zasejedenID);
        $grid->setDataSource($source);
        $grid->addColumnText('cinnost', 'Činnost');
        $grid->addColumnText('nazev_cinnosti', 'Název činnosti');
        $grid->addColumnNumber('rozpocetV', 'Plánovaný rozpočet')->addCellAttributes(['class' => 'text-success'])
            ->setRenderer(function($item) { return (' - '); });
        $grid->addColumnNumber('vlastni', 'Utraceno vlastní')
            ->setRenderer(function($item) { return (number_format($item['vlastni'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('vlastniObj', 'Objednáno vlastní')
            ->setRenderer(function($item) { return (number_format($item['vlastniObj'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('celkemV', 'Celkem vlastní')
            ->setRenderer(function($item) { return (number_format($item['celkemV'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('zbyvaV', 'Zbývá z rozpočtu')
            ->setRenderer(function($item) { return (number_format($item['zbyvaV'],0,","," ") .' Kč'); });
        $grid->addColumnCallback('zbyvaV', function($column, $item) { $item['zbyvaV'] < 0 ? $column->getElementPrototype('td')->addAttributes(['class' => 'text-danger font-weight-bold',]) : $column->getElementPrototype('td')->removeAttributes(['class' => 'class',]); } );
        $grid->addColumnNumber('rozpocetS', 'Plánované šablony')->addCellAttributes(['class' => 'text-success'])
            ->setRenderer(function($item) { return (' - '); });
        $grid->addColumnNumber('sablony', 'Utraceno šablony')
            ->setRenderer(function($item) { return (number_format($item['sablony'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('sablonyObj', 'Objednáno šablony')
            ->setRenderer(function($item) { return (number_format($item['sablonyObj'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('celkemS', 'Celkem šablony')
            ->setRenderer(function($item) { return (number_format($item['celkemS'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('zbyvaS', 'Zbývá z šablon')
           ->setRenderer(function($item) { return (number_format($item['zbyvaS'],0,","," ") .' Kč'); });
        $grid->addColumnCallback('zbyvaS', function($column, $item) { $item['zbyvaS'] < 0 ? $column->getElementPrototype('td')->addAttributes(['class' => 'text-danger font-weight-bold',]) : $column->getElementPrototype('td')->removeAttributes(['class' => 'class',]); } );
        $grid->addColumnNumber('dotace', 'Utraceno účelové dotace')
            ->setRenderer(function($item) { return (number_format($item['dotace'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('dotaceObj', 'Objednáno účelové dotace')
            ->setRenderer(function($item) { return (number_format($item['dotaceObj'],0,","," ") .' Kč'); });
        $grid->setColumnsSummary(['rozpocetV','vlastni','vlastniObj','zbyvaV','celkemV','celkemS',
            'rozpocetS','sablony','sablonyObj','dotace','zbyvaS','dotace','dotaceObj'])
            ->setRenderer(function($sum, string $column) { return (number_format($sum,0,","," ") .' Kč'); });
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        // ->setSplitWordsSearch(FALSE);     bude to hledat při více slovech jen celý řetězec

        $grid->setTranslator($this->getTranslator());
    } 
 
    public function createComponentSimpleGrid($name)
    {
        $grid = new DataGrid($this, $name);
        $zasejedenID = $this->sessionSection->jedenId;
        //     $relevantni = $this->database->table('cinnost')->select('cinnost')->where('id_rozpocet',$zasejedenID);   //seznam cinnosti patrici k vybranemu rozpoctu
        //    $vysledek = $this->database->table('denik')->where('cinnost_d', $relevantni)->where('petky',  1) ;   //polozky deniku dle seznamu cinnosti
        $source = $this->mapDenik(1,$zasejedenID);
        $grid->setDataSource($source);
        $grid->addColumnDateTime('datum', 'Datum')->setFormat('d.m.Y');
        $grid->addColumnText('cinnost_d', 'Činnost');
        $grid->addColumnText('doklad', 'Doklad');
        $grid->addColumnText('firma', 'Firma');
        $grid->addColumnText('popis', 'Popis');
        $grid->addColumnText('stredisko_d', 'Středisko');
        $grid->addColumnText('zakazky', 'Zakázka');
        $grid->addColumnNumber('vlastni', 'Vlastní Kč')
            ->setRenderer(function($item) { return (number_format($item['vlastni'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('sablony', 'Šablony Kč')
            ->setRenderer(function($item) { return (number_format($item['sablony'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('dotace', 'Účelové dotace Kč')
            ->setRenderer(function($item) { return (number_format($item['dotace'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('preuctovani', 'Přeúčtování Kč')
            ->setRenderer(function($item) { return (number_format($item['preuctovani'],0,","," ") .' Kč'); });
        $grid->addColumnNumber('cisloObjednavky', 'Číslo objednávky');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsSummary(['vlastni','sablony','dotace', 'preuctovani'])
            ->setRenderer(function($sum, string $column): string { return number_format($sum,0,","," ") . ' Kč'; });
        // $grid->addFilterRange('vlastni', 'Částka Kč');
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');

        $grid->setTranslator($this->getTranslator());
    //    $grid->setMultiSortEnabled($enabled = TRUE);
    // $grid->addAggregationFunction('castka', new FunctionSum('castka'));
    } 

    public function createComponentSimpleGrid2($name)
    {
        $zasejedenID = $this->sessionSection->jedenId;
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [3,4,9])->order('id DESC');
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source);
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('id_prehled', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['id_prehled'] . '/' .  $item['radka'];
            });
        });
        //$grid->addColumnNumber('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel','zakladatel.jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('stav','Stav', 'stav')->setSortable()->setSortableResetPagination()->setTemplateEscaping(FALSE);
        $grid->addColumnCallback('stav', function($column, $item) {
            $column->setRenderer(function() use ($item):string { return $this->renderujIkonuStavu($item); });
        });
        $grid->addColumnText('schvalovatel','Schvalovatel','uzivatel.jmeno:kdo')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('schvalil','Schváleno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('nutno_overit', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['nutno_overit'] == 1 ? "ANO" : "ne";   
            });
        });
        $grid->addColumnText('overovatel','Ověřovatel','uzivatel.jmeno:kdo2')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('overil','Ověřeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnostP','Popis činnosti','cinnost.nazev_cinnosti:cinnost')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('castka', function($column, $item) {
            $column->setRenderer(function() use ($item):string {
                return ($item['castka'] .' Kč');   
            });
        });
        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav']);
        });
        $grid->setPagination(false);
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());
    } 

    public function createComponentSimpleGrid3($name)
    {
        $zasejedenID = $this->sessionSection->jedenId;
        $relevantni =   $this->database->table('cinnost')->select('id')->where('id_rozpocet',  $zasejedenID );
        $source = $this->database->table('objednavky')->where('cinnost', $relevantni)->where('stav', [0,1])->order('id DESC');
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source);
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('id_prehled', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['id_prehled'] . '/' .  $item['radka'];
            });
        });
        //$grid->addColumnNumber('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel','zakladatel.jmeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('stav','Stav', 'stav')->setSortable()->setSortableResetPagination()->setTemplateEscaping(FALSE);
        $grid->addColumnCallback('stav', function($column, $item) {
            $column->setRenderer(function() use ($item):string { return $this->renderujIkonuStavu($item); });
        });
        $grid->addColumnText('schvalovatel','Schvalovatel','uzivatel.jmeno:kdo')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('schvalil','Schváleno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('nutno_overit', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['nutno_overit'] == 1 ? "ANO" : "ne";   
            });
        });
        $grid->addColumnText('overovatel','Ověřovatel','uzivatel.jmeno:kdo2')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('overil','Ověřeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost','Činnost','cinnost.cinnost:cinnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnostP','Popis činnosti','cinnost.nazev_cinnosti:cinnost')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('zakazka','Zakázka','zakazky.zakazka:zakazka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko','Středisko','stredisko.stredisko:stredisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('castka', function($column, $item) {
            $column->setRenderer(function() use ($item):string {
                return ($item['castka'] .' Kč');   
            });
        });
        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav']);
        });
        $grid->setPagination(false);
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());
    } 
 

/**********************************************************************************************************************
 * POMOCNÉ FUNKCE
 **********************************************************************************************************************/

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
            'rozpocet' => $this->sessionSection->jedenId,
        ]);
        $this->redrawControl();
    }

    public function handlePrestanSledovat()
    {
        $this->database->table('skupiny')->where([
            'uzivatel' => $this->prihlasenyId(),
            'rozpocet' => $this->sessionSection->jedenId,
        ])->delete();
        $this->redrawControl();
    }

}