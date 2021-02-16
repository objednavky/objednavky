<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class MojeObjednavkyPresenter extends ObjednavkyBasePresenter
{
    private $grids = [];

    protected function startup() {
        parent::startup();
    }


    public function renderMazani(): void {
        // $uzivatel = $this->getUser()->getIdentity()->jmeno;      //   jméno uživatel
        $uz = $this->prihlasenyId();
        // $uz = $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();  //id prihlaseny uzivatel
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
        bdump($this->grids);
    }

    public function renderPrehled(): void {
        $uz = $this->prihlasenyId();
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
        bdump($this->grids);
    }


    public function smazAktivniObjednavky(array $ids): void {
        $this->objednavkyManager->smazObjednavkyDb($ids);
        // TODO zjisit proc ajax reload nefunguje
//        if ($this->isAjax()) {
//            $this->grids['aktivniObjednavkyGrid']->reload();
//        } else {
            $this->redirect('this');
//        }
    }
     
    public function smazZamitnuteObjednavky(array $ids): void {
        $this->objednavkyManager->smazObjednavkyDb($ids);
//        if ($this->isAjax()) {
//            $this->grids['zamitnuteObjednavkyGrid']->reload();
//        } else {
            $this->redirect('this');
//        }
    }


    public function createComponentAktivniObjednavkyMazaniGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [0,1,3,4]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['aktivniObjednavkyGrid'] = $grid;
        $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'smazAktivniObjednavky'];
    } 

    public function createComponentZamitnuteObjednavkyMazaniGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [2,5,8]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['zamitnuteObjednavkyGrid'] = $grid;
        $grid->addGroupAction('Ano - smazat!')->onSelect[] = [$this, 'smazZamitnuteObjednavky'];
    } 

    public function createComponentNeschvaleneObjednavkyGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [0,1]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['archivGrid'] = $grid;
    } 

    public function createComponentSchvaleneObjednavkyGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [3,4,9]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['archivGrid'] = $grid;
    } 

    public function createComponentZamitnuteObjednavkyGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [2,5,8]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['archivGrid'] = $grid;
    } 

    public function createComponentZauctovaneObjednavkyGrid($name) {
        $source = $this->objednavkyManager->mapRozpocetMojeObjednavky($this->prihlasenyId(), [6]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $this->grids['archivGrid'] = $grid;
    } 



/**********************************************************************************************************************
 * POMOCNÉ FUNKCE
 **********************************************************************************************************************/



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
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('stav','Stav objednávky')->setSortable()->setSortableResetPagination()->setFilterText();
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