<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use stdClass;
use Ublaboo\DataGrid\DataGrid;
use Ublaboo\DataGrid\GroupAction\GroupAction;
use Ublaboo\DataGrid\AggregationFunction\FunctionSum;
use Ublaboo\DataGrid\AggregationFunction\ISingleColumnAggregationFunction;


class SchvalenePresenter extends ObjednavkyBasePresenter
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
        $this->database->table('objednavky')->where('id',$ids)->update([
            'stav' => 9
        ]);
        $this->redirect('this');
    }
    
    private function mapRozpocet($argument)
    {
        $uz = $this->prihlasenyId();   // přihlášený uživatel
        $source = $this->database->table('objednavky')
           ->where('stav', [3,4])->order('id DESC');
        // $source = $this->database->table('objednavky')->where('stav', [3,4])->select('DISTINCT id_prehled');

        $fetchedRozpocets = []; //iniciace promene pred blokem cyklu

        foreach ($source as $objednavky) {
            $item = new stdClass;
            $item->id = $objednavky->id;
            $item->id_prehled = $objednavky->id_prehled;
            $item->radka = $objednavky->radka;
            $pomoc =  $this->database->table('objednavky')->where('id_prehled',$item->id_prehled )->fetch();
            // $pom2 = $this->database->table('prehled')->where('id',$pomoc->id_prehled)->fetch();
            // $pom3 = $this->database->table('uzivatel')->where('id',$pom2->id_uzivatel)->fetch();
            // bdump($pom2);
            // $item->zadavatel =$pom3->jmeno ;
            $item->zadavatel = $objednavky->ref('zakladatel')->jmeno;
            $item->zalozil = $objednavky->zalozil;
            $item->schvalovatel = $objednavky->ref('kdo')->jmeno;
            $item->schvalil = $objednavky->schvalil;
            $item->overovatel = $objednavky->ref('kdo2')->jmeno;
            $item->overil = $objednavky->overil;             
            $item->nutno_overit = $objednavky->nutno_overit;
            $item->stav = $objednavky->ref('stav')->popis;
            $item->firma = $objednavky->firma;
            $item->popis = $objednavky->popis;
            $item->cinnost = $objednavky->ref('cinnost')->cinnost;
            $item->cinnostP = $objednavky->ref('cinnost')->nazev_cinnosti;
            $item->zakazka = $objednavky->ref('zakazka')->zakazka;
            $item->stredisko = $objednavky->ref('stredisko')->stredisko;
            $item->castka = $objednavky->castka;
            $fetchedRozpocets[] = json_decode(json_encode($item), true);
          
        }
       
        return $fetchedRozpocets;
    }
    
    public function createComponentSimpleGrid2($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['mazaciGrid'] = $grid;
        $source = $this->mapRozpocet(1);
        $grid->setDataSource($source);
        $grid->addColumnNumber('id_prehled','Č. obj.')->setSortable()->setSortableResetPagination();
        $grid->addColumnCallback('id_prehled', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                return $item['id_prehled'] . '/' .  $item['radka'];
            });
        });
//        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnText('zadavatel','Zadavatel')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnDateTime('zalozil','Založeno')->setFormat('d.m.Y H:i:s')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stav','stav objednávky')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('schvalovatel','Schvalovatel')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('schvalil','Schváleno')->setFormat('d.m.Y')->setSortable()->setSortableResetPagination();
        $grid->addColumnText('nutno_overit','Nutno ověřit')->setAlign('center')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('nutno_overit', function($column, $item) {
            $column->setRenderer(function() use ($item) {
                bdump($item);
                return $item['nutno_overit'] == 1 ? "ANO" : "ne";   
            });
        });
        $grid->addColumnText('overovatel','Ověřovatel')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnDateTime('overil','Ověřeno')->setFormat('d.m.Y')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('firma','firma')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('popis','popis')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnost','Činnost')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('cinnostP','Popis činnosti')->setSortable()->setSortableResetPagination()->setDefaultHide()->setFilterText();
        $grid->addColumnText('zakazka','Zakázka')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnText('stredisko','Středisko')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnNumber('castka', 'Částka položky')->setSortable()->setSortableResetPagination()->setFilterText();
        $grid->addColumnCallback('castka', function($column, $item) {
            $column->setRenderer(function() use ($item):string {
                return ($item['castka'] .' Kč');   
            });
        });
        $grid->setPagination(false);
        $grid->addGroupAction('Zpracovat - zmizí ze seznamu')->onSelect[] = [$this, 'deleteOdl'];
        $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];
        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
            ->setTitle('Export do csv');
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);
        $grid->setColumnsHideable();

        $translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
            'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
            'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
            'ublaboo_datagrid.here' => 'zde',
            'ublaboo_datagrid.items' => 'Položky',
            'ublaboo_datagrid.all' => 'všechny',
            'ublaboo_datagrid.from' => 'z',
            'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
            'ublaboo_datagrid.group_actions' => 'Vyber objednávky',
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
        $grid->setTranslator($translator);
    } 
}