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
        if ($mojerole[0] == 1)
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
        $grid = new DataGrid($this, $name);
        $this->grids['mazaciGrid'] = $grid;
        $source = $this->mapRozpocet([0,1]);
    
        $grid->setDataSource($source);
        
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnNumber('castka', 'Částka položky');

        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');

        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        
        $grid->addColumnText('stredisko','Středisko');
       
        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);
        
        // $grid->addGroupAction('Odložit ze seznamu - již zpracované')->onSelect[] = [$this, 'deleteOdl'];
        $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];

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

    public function createComponentSimpleGrid3($name)
    {
        $grid = new DataGrid($this, $name);
        $this->grids['mazaciGrid'] = $grid;
        $source = $this->mapRozpocet([9]);
    
        $grid->setDataSource($source);
        
        $grid->addColumnText('id_prehled','Č. obj.');
        $grid->addColumnText('radka','Č. pol.');
        $grid->addColumnNumber('castka', 'Částka položky');
        $grid->addColumnText('zadavatel','Zadavatel');
        $grid->addColumnText('stav','stav objednávky');
        $grid->addColumnText('schvalovatel','Schvalovatel');
        $grid->addColumnDateTime('schvalil','Schváleno');
        $grid->addColumnText('nutno_overit','Nutno ověřit');
        $grid->addColumnText('overovatel','Ověřovatel');
        $grid->addColumnDateTime('overil','Ověřeno');
        $grid->addColumnText('firma','firma');
        $grid->addColumnText('popis','popis');
        $grid->addColumnText('cinnost','Činnost');
        $grid->addColumnText('cinnostP','Popis činnosti');
        $grid->addColumnText('zakazka','Zakázka');
        $grid->addColumnText('stredisko','Středisko');
       
        $grid->addGroupAction('Zpět odznačit objednávky - vrátit do seznamu')->onSelect[] = [$this, 'deleteOdl'];
        $grid->addGroupAction('Smazat - nebude se realizovat')->onSelect[] = [$this, 'deleteObj2'];

        // $grid->addExportCsvFiltered('Export do csv s filtrem', 'tabulka.csv', 'windows-1250')
        // ->setTitle('Export do csv s filtrem');
        $grid->addExportCsv('Export do csv', 'tabulka.csv', 'windows-1250')
        ->setTitle('Export do csv');

        $grid->setPagination(true);
        $grid->setItemsPerPageList([10, 20, 50]);

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