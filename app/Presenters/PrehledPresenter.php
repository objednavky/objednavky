<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class PrehledPresenter extends ObjednavkyBasePresenter
{
    private $grids = [];
    protected function startup()
    {
        parent::startup();
        $mojerole = $this->getUser()->getRoles();
        if (empty($mojerole))
        {
            $this->redirect('Homepage:default');
            $this->flashMessage('Nemáte oprávnění.');
        } 
    }
    
    public function renderShow(): void
	{
        // $uzivatel = $this->getUser()->getIdentity()->jmeno;      //   jméno uživatel
        $uz = $this->prihlasenyId();
        // $uz = $this->database->table('uzivatel')->where('jmeno',$uzivatel)->fetch();  //id prihlaseny uzivatel
        $this->template->prihlasen = ($this->database->table('uzivatel')->where('id',$uz)->fetch())->jmeno;
    }

    public function deleteObj2(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 8
        ]);
        $this->redirect('this');
    }

    public function deleteOdl(array $ids): void
    {
        $this->database->table('objednavky')->where('id',$ids)->where('nutno_overit',1)->update([
            'stav' => 4
        ]);
        $this->database->table('objednavky')->where('id',$ids)->where('nutno_overit',0)->update([
            'stav' => 3
        ]);
        $this->redirect('this');
    }
    
    
    public function createComponentSimpleGrid2($name)
    {
        $source = $this->objednavkyManager->mapRozpocetPrehled([0,1]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        // $grid->addGroupAction('Odložit ze seznamu - již zpracované')->onSelect[] = [$this, 'deleteOdl'];
        $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];
        $this->grids['mazaciGrid'] = $grid;
    } 

    public function createComponentSimpleGrid3($name)
    {
        $source = $this->objednavkyManager->mapRozpocetPrehled([9]);
        $grid = $this->vytvorDataGridObjednavky($name, $source);
        $grid->addGroupAction('Zpět odznačit objednávky - vrátit do seznamu')->onSelect[] = [$this, 'deleteOdl'];
        $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];
        $this->grids['mazaciGrid'] = $grid;      
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
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination();
        $grid->addColumnCallback('id_prehled', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['id_prehled'] . '/' .  $item['radka'];
            });
        });
        //$grid->addColumnNumber('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('stav','Stav objednávky')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('schvalovatel','Schvalovatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('schvalil','Schváleno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('nutno_overit', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['nutno_overit'] == 1 ? "ANO" : "ne";   
            });
        });
        $grid->addColumnText('overovatel','Ověřovatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('overil','Ověřeno')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost','Činnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnostP','Popis činnosti')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('zakazka','Zakázka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko','Středisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('castka', function($column, $item) {
            $column->setRenderer(function() use ($item):string {
                return ($item['castka'] .' Kč');   
            });
        });
        $grid->setPagination(false);
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
            'ublaboo_datagrid.choose' => 'Vyber činnost',
            'ublaboo_datagrid.execute' => 'Vykonej',
            'Name' => 'Jméno',
            'Inserted' => 'Vloženo'
        ]);
        return $translator;
    }

}