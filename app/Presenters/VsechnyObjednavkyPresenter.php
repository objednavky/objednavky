<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class VsechnyObjednavkyPresenter extends ObjednavkyBasePresenter
{
    private $grids = [];

    /* @persistent */
    public bool $smazane = false;

    protected function startup() {
        parent::startup();
    }


    public function actionDefault(?int $prehledId = null, ?string $smazane): void {
        $uz = $this->prihlasenyId();
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
        $this->template->prehledId = $prehledId;
        $this->template->smazane = isset($smazane) ? $smazane : false;
        $this->smazane = isset($smazane) ? $smazane : false;
    }


    public function createComponentPrehledObjednavekGrid($name) {
        bdump($this->smazane);
        $source = $this->objednavkyManager->mapPrehledObjednavek($this->smazane);
        $grid = $this->vytvorDataGridPrehledObjednavek($name, $source);
        $this->grids['prehledObjednavekGrid'] = $grid;
    } 

    public function createComponentDetailObjednavkyGrid($name) {
        $source = $this->objednavkyManager->mapObjednavka($this->template->prehledId);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['detailObjednavkyGrid'] = $grid;
    } 



/**********************************************************************************************************************
 * POMOCNÉ FUNKCE
 **********************************************************************************************************************/



    private function vytvorDataGridPrehledObjednavek($name, $source) : DataGrid {
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source);
        $grid->addColumnNumber('id','Č. obj.')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','Popis objednávky')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnosti','Činnosti')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','Firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka_neschvalene', 'Neschváleno Kč (pol)')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { 
                return $item['pocet_neschvalene'] == 0 ? '' : (number_format($item['castka_neschvalene'],0,","," ") .' Kč ('.$item['pocet_neschvalene'].')'); })->setFilterText();
        $grid->addColumnNumber('castka_schvalene', 'Schváleno Kč (pol)')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { 
                return $item['pocet_schvalene'] == 0 ? '' : (number_format($item['castka_schvalene'],0,","," ") .' Kč ('.$item['pocet_schvalene'].')'); })->setFilterText();
        $grid->addColumnNumber('castka_zamitnute', 'Zamítnuto Kč (pol)')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { 
                return $item['pocet_zamitnute'] == 0 ? '' : (number_format($item['castka_zamitnute'],0,","," ") .' Kč ('.$item['pocet_zamitnute'].')'); })->setFilterText();
        $grid->addColumnNumber('castka_uctarna', 'V účtárně Kč (pol)')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { 
                return $item['pocet_uctarna'] == 0 ? '' : (number_format($item['castka_uctarna'],0,","," ") .' Kč ('.$item['pocet_uctarna'].')'); })->setFilterText();
        $grid->addColumnNumber('castka_celkem', 'CELKEM Kč (pol)')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { 
                return $item['pocet_celkem'] == 0 ? '' : (number_format($item['castka_celkem'],0,","," ") .' Kč ('.$item['pocet_celkem'].')'); })->setFilterText();

        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('click-prehled click-prehled-' . $item['id']);
            $tr->addClass(($item['id'] == $this->template->prehledId ? ' click-prehled-selected btn-info' : ''));
            $tr->addAttributes(['data-prehled-id' => $item['id']]);
            
        });

        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());        
        return $grid;
    }


    /**
     * pomocná funkce na vytvoření gridu
     */
    private function vytvorDataGridObjednavky($name, $source) : DataGrid
    {
        $grid = new DataGrid($this, $name);
        $grid->setDataSource($source);
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item) {
                return $item['id_prehled'] . '/' .  $item['radka'];
        });
//        $grid->addColumnNumber('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnText('stav','Stav objednávky')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('stav_id','Stav č.')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnText('schvalovatel','Schvalovatel')->setSortable()->setSortableResetPagination();
        $grid->addColumnDateTime('schvalil','Schváleno')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item) { return $item['nutno_overit'] == 1 ? "ANO" : "ne"; });
        $grid->addColumnText('overovatel','Ověřovatel')->setSortable()->setSortableResetPagination();
        $grid->addColumnDateTime('overil','Ověřeno')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('cinnost','Činnost')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('cinnostP','Popis činnosti')->setSortable()->setSortableResetPagination()->setDefaultHide();
        $grid->addColumnText('zakazka','Zakázka')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('stredisko','Středisko')->setSortable()->setSortableResetPagination();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()
            ->setRenderer(function($item):string { return (number_format($item['castka'],0,","," ") .' Kč'); });

        $grid->setRowCallback(function($item, $tr) {
            $tr->addClass('tr-objednavky-stav-'.$item['stav_id']);
        });
    
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(count($source)>10);
        $grid->setItemsPerPageList([10, 30, 100]);
        $grid->setColumnsHideable();
        $grid->setTranslator($this->getTranslator());        
        return $grid;
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
            'ublaboo_datagrid.group_actions' => 'Zaškrtni objednávky ke smazání',
            'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
            'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
            'ublaboo_datagrid.action' => 'Akce',
            'ublaboo_datagrid.previous' => 'Předchozí',
            'ublaboo_datagrid.next' => 'Další',
            'ublaboo_datagrid.choose' => 'Opravdu smazat?',
            'ublaboo_datagrid.execute' => 'Smazat vybrané objednávky!',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        return $translator;
    }


}